<?php
/**
 * Renewal charging for WooCommerce Subscriptions using BCI recurrent payments.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

final class Renewals {
	/** @var object|null */
	private $gateway;

	/** @var Api */
	private $api;

	/** @var Status_Resolver */
	private $resolver;

	/** @var Subscriptions */
	private $subscriptions;

	/** @var Tokens */
	private $tokens;

	public function __construct($gateway = null, ?Api $api = null, ?Status_Resolver $resolver = null, ?Subscriptions $subscriptions = null, ?Tokens $tokens = null) {
		$this->gateway       = is_object($gateway) ? $gateway : null;
		$this->api           = $api ?: new Api();
		$this->resolver      = $resolver ?: new Status_Resolver($this->api);
		$this->tokens        = $tokens ?: new Tokens($this->gateway_id());
		$this->subscriptions = $subscriptions ?: new Subscriptions($this->gateway, $this->tokens);
	}

	/**
	 * Registers the renewal hook.
	 *
	 * Called from the gateway constructor only when subscriptions are enabled, so
	 * the setting is not consulted again here or on the renewal path: a renewal
	 * charges what the subscription recorded when it was set up.
	 */
	public function register_hooks(): void {
		if (!\function_exists('add_action')) {
			return;
		}

		static $registered = [];
		$gateway_id = $this->gateway_id();

		if (isset($registered[$gateway_id])) {
			return;
		}

		$registered[$gateway_id] = true;

		add_action(
			'woocommerce_scheduled_subscription_payment_' . $gateway_id,
			[$this, 'process_subscription_payment'],
			10,
			2
		);
	}

	/**
	 * Processes a WooCommerce Subscriptions renewal order through BPC recurrentPayment.do.
	 *
	 * @param float|string       $amount_to_charge Renewal amount in major currency units.
	 * @param \WC_Order|int|null $renewal_order    Renewal order object or ID.
	 */
	public function process_subscription_payment($amount_to_charge, $renewal_order): void {
		$renewal_order = $this->maybe_order($renewal_order);

		if (!$renewal_order) {
			Log::error('BCI renewal failed before charging: renewal order was not available.');
			return;
		}

		$subscription = $this->subscriptions->subscription_for_renewal_order($renewal_order);
		$token_data   = $subscription ? $this->tokens->token_data_from_subscription($subscription) : [];

		if (($token_data['binding_id'] ?? '') === '') {
			$token_data = array_merge($token_data, $this->tokens->token_data_from_order($renewal_order));
		}

		$binding_id = Config::clean($token_data['binding_id'] ?? '');
		if ($binding_id === '') {
			$this->fail_order(
				$renewal_order,
				__('BCI renewal failed: no stored credential is available.', 'bci-woo')
			);
			return;
		}

		// A renewal is charged wherever the binding was created, which the
		// subscription remembers until this renewal order records it below.
		$environment  = Order_State::for($renewal_order)->environment($subscription);
		$order_number = $this->build_order_number($renewal_order);
		$params       = [
			'orderNumber' => $order_number,
			'language'    => 'en',
			'bindingId'   => $binding_id,
			'amount'      => $this->amount_to_minor_units($amount_to_charge),
			'currency'    => $this->currency_to_numeric($renewal_order->get_currency(), $environment),
			'description' => $this->safe_description($renewal_order),
		];

		$client_id = Config::clean($token_data['client_id'] ?? '');
		if ($client_id !== '') {
			$params['clientId'] = $client_id;
		}

		$email = Config::clean($renewal_order->get_billing_email());
		if ($email !== '') {
			$params['additionalParameters'] = ['email' => $email];
		}

		try {
			$result = $this->call_recurrent_payment($params, $environment);
		} catch (\Throwable $e) {
			$this->fail_order(
				$renewal_order,
				sprintf(
					/* translators: %s: gateway error message */
					__('BCI renewal failed: %s', 'bci-woo'),
					$e->getMessage()
				)
			);
			return;
		}

		if ($this->result_is_error($result)) {
			$this->fail_order($renewal_order, $this->result_error_message($result));
			return;
		}

		$md_order = $this->extract_order_id($result);
		Order_State::for($renewal_order)
			->record_registration($md_order, $order_number, $environment)
			->save();

		$embedded_status = $this->embedded_order_status($result);
		if ($embedded_status !== []) {
			$this->tokens->capture_from_status($renewal_order, $embedded_status, $client_id);
		}

		// The renewal response embeds an order status, but it is not interpreted
		// here: the resolver reads the order back from the gateway and applies the
		// one status table, so a renewal reaches the same order state, meta, notes
		// and bci_woo_payment_status_resolved hook as any other payment.
		$resolution = $this->resolver->resolve($renewal_order, 'subscription renewal');

		Log::info('Processed BCI subscription renewal.', [
			'order_id'    => $renewal_order->get_id(),
			'md_order'    => $md_order,
			'resolution'  => $resolution,
			'environment' => $environment,
		]);
	}

	private function call_recurrent_payment(array $params, string $environment): array {
		$result = $this->api->recurrent_payment($params, $environment);

		if (\function_exists('is_wp_error') && is_wp_error($result)) {
			throw new \RuntimeException($result->get_error_message());
		}

		if (!is_array($result)) {
			throw new \RuntimeException(__('BCI returned an invalid renewal response.', 'bci-woo'));
		}

		return $result;
	}

	private function result_is_error(array $result): bool {
		if (array_key_exists('success', $result) && $result['success'] === false) {
			return true;
		}

		if (isset($result['errorCode']) && (string) $result['errorCode'] !== '0') {
			return true;
		}

		if (isset($result['error']) && $result['error'] === true) {
			return true;
		}

		$status = $this->embedded_order_status($result);
		if (isset($status['errorCode']) && (string) $status['errorCode'] !== '0') {
			return true;
		}

		return false;
	}

	private function result_error_message(array $result): string {
		$error = $result['error'] ?? null;
		$message = is_array($error) ? Config::clean($error['message'] ?? '') : '';

		if ($message === '') {
			$message = Config::clean($result['errorMessage'] ?? '');
		}

		if ($message === '') {
			$message = Config::clean($result['data']['errorMessage'] ?? '');
		}

		$status = $this->embedded_order_status($result);
		if ($message === '' && $status !== []) {
			$message = Config::clean($status['errorMessage'] ?? '');
		}

		if ($message === '' && is_string($error)) {
			$message = Config::clean($error);
		}

		if ($message === '') {
			$message = __('BCI declined the renewal request.', 'bci-woo');
		}

		return $message;
	}

	private function fail_order(\WC_Order $order, string $message): void {
		$message = Config::clean($message);
		if ($message === '') {
			$message = __('BCI renewal failed.', 'bci-woo');
		}

		try {
			$order->update_status('failed', $message);
		} catch (\Throwable $e) {
			$this->add_order_note($order, $message);
		}

		$order->save();

		Log::notice('BCI subscription renewal failed.', [
			'order_id' => $order->get_id(),
			'message'  => $message,
		]);
	}

	private function extract_order_id(array $result): string {
		$paths = [
			'data.orderId',
			'orderId',
			'mdOrder',
			'orderStatus.orderId',
			'orderStatus.mdOrder',
		];

		foreach ($paths as $path) {
			$value = $this->array_get($result, $path);
			if ($value !== null && $value !== '') {
				return Config::clean($value);
			}
		}

		$status = $this->embedded_order_status($result);
		foreach ((array) ($status['attributes'] ?? []) as $attribute) {
			if (!is_array($attribute)) {
				continue;
			}

			if (($attribute['name'] ?? '') === 'mdOrder' && !empty($attribute['value'])) {
				return Config::clean($attribute['value']);
			}
		}

		return '';
	}

	private function embedded_order_status(array $result): array {
		if (isset($result['orderStatus']) && is_array($result['orderStatus'])) {
			return $result['orderStatus'];
		}

		if (isset($result['data']['orderStatus']) && is_array($result['data']['orderStatus'])) {
			return $result['data']['orderStatus'];
		}

		if (isset($result['orderStatus']) && is_numeric($result['orderStatus'])) {
			return $result;
		}

		return [];
	}

	private function amount_to_minor_units($amount): int {
		if (\function_exists('wc_format_decimal')) {
			$amount = wc_format_decimal($amount, 2);
		}

		return (int) round(((float) $amount) * 100);
	}

	/**
	 * The numeric currency for a charge addressed to $environment.
	 *
	 * Api is asked, and asked about that environment: the currency has to match
	 * the host the charge is sent to, which for a renewal is the environment its
	 * binding lives in rather than the one the store is configured for today.
	 */
	private function currency_to_numeric(string $currency, string $environment): string {
		return Api::payment_currency_to_numeric($currency, $environment);
	}

	private function build_order_number(\WC_Order $order): string {
		return Registration::build_order_number($order);
	}

	private function safe_description(\WC_Order $order): string {
		return Registration::safe_description($order);
	}

	private function add_order_note(\WC_Order $order, string $message): void {
		if (method_exists($order, 'add_order_note')) {
			$order->add_order_note($message);
		}
	}

	private function maybe_order($order): ?\WC_Order {
		if ($order instanceof \WC_Order) {
			return $order;
		}

		if ((is_numeric($order) || is_string($order)) && \function_exists('wc_get_order')) {
			$order = wc_get_order($order);
			return $order instanceof \WC_Order ? $order : null;
		}

		return null;
	}

	private function gateway_id(): string {
		if (is_object($this->gateway) && isset($this->gateway->id) && $this->gateway->id !== '') {
			return (string) $this->gateway->id;
		}

		return Config::GATEWAY_ID;
	}

	private function array_get(array $source, string $path) {
		$current = $source;
		foreach (explode('.', $path) as $segment) {
			if (!is_array($current) || !array_key_exists($segment, $current)) {
				return null;
			}

			$current = $current[$segment];
		}

		return $current;
	}
}
