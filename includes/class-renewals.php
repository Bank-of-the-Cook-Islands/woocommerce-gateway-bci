<?php
/**
 * Renewal charging for WooCommerce Subscriptions using BCI recurrent payments.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\Tokens') && is_readable(__DIR__ . '/class-tokens.php')) {
	require_once __DIR__ . '/class-tokens.php';
}

if (!class_exists(__NAMESPACE__ . '\Subscriptions') && is_readable(__DIR__ . '/class-subscriptions.php')) {
	require_once __DIR__ . '/class-subscriptions.php';
}

final class Renewals {
	private const META_MD_ORDER = '_bci_woo_md_order';
	private const META_ORDER_NUMBER = '_bci_woo_order_number';

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

	public static function register_gateway_hooks($gateway): void {
		if (is_object($gateway) && is_callable([$gateway, 'get_option'])) {
			$enabled = (string) $gateway->get_option('enable_subscriptions', 'no');
			if ($enabled !== 'yes') {
				return;
			}
		}

		(new self($gateway))->register_hooks();
	}

	public function register_hooks(): void {
		if (!$this->subscriptions_enabled()) {
			return;
		}

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
			$this->log('error', 'BCI renewal failed before charging: renewal order was not available.');
			return;
		}

		$subscription = $this->subscriptions->subscription_for_renewal_order($renewal_order);
		$token_data   = $subscription ? $this->tokens->token_data_from_subscription($subscription) : [];

		if (($token_data['binding_id'] ?? '') === '') {
			$token_data = array_merge($token_data, $this->tokens->token_data_from_order($renewal_order));
		}

		$binding_id = $this->clean($token_data['binding_id'] ?? '');
		if ($binding_id === '') {
			$this->fail_order(
				$renewal_order,
				__('BCI renewal failed: no stored credential is available.', 'bci-woo')
			);
			return;
		}

		$environment  = $this->environment_for_order($renewal_order, $subscription, $token_data);
		$order_number = $this->build_order_number($renewal_order);
		$params       = [
			'orderNumber' => $order_number,
			'language'    => 'en',
			'bindingId'   => $binding_id,
			'amount'      => $this->amount_to_minor_units($amount_to_charge),
			'currency'    => $this->currency_to_numeric($renewal_order->get_currency()),
			'description' => $this->safe_description($renewal_order),
		];

		$client_id = $this->clean($token_data['client_id'] ?? '');
		if ($client_id !== '') {
			$params['clientId'] = $client_id;
		}

		$email = $this->clean($renewal_order->get_billing_email());
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
		if ($md_order !== '') {
			$renewal_order->update_meta_data(self::META_MD_ORDER, $md_order);
		}

		$renewal_order->update_meta_data(self::META_ORDER_NUMBER, $order_number);
		$renewal_order->update_meta_data(Tokens::META_ENVIRONMENT, $environment);

		$embedded_status = $this->embedded_order_status($result);
		if ($embedded_status !== []) {
			$this->tokens->capture_from_status($renewal_order, $embedded_status, $client_id);
		}

		if (method_exists($renewal_order, 'save')) {
			$renewal_order->save();
		}

		// The renewal response embeds an order status, but it is not interpreted
		// here: the resolver reads the order back from the gateway and applies the
		// one status table, so a renewal reaches the same order state, meta, notes
		// and bci_woo_payment_status_resolved hook as any other payment.
		$resolution = $this->resolver->resolve($renewal_order, 'subscription renewal');

		$this->log('info', 'Processed BCI subscription renewal.', [
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
		$message = is_array($error) ? $this->clean($error['message'] ?? '') : '';

		if ($message === '') {
			$message = $this->clean($result['errorMessage'] ?? '');
		}

		if ($message === '') {
			$message = $this->clean($result['data']['errorMessage'] ?? '');
		}

		$status = $this->embedded_order_status($result);
		if ($message === '' && $status !== []) {
			$message = $this->clean($status['errorMessage'] ?? '');
		}

		if ($message === '' && is_string($error)) {
			$message = $this->clean($error);
		}

		if ($message === '') {
			$message = __('BCI declined the renewal request.', 'bci-woo');
		}

		return $message;
	}

	private function fail_order(\WC_Order $order, string $message): void {
		$message = $this->clean($message);
		if ($message === '') {
			$message = __('BCI renewal failed.', 'bci-woo');
		}

		try {
			$order->update_status('failed', $message);
		} catch (\Throwable $e) {
			$this->add_order_note($order, $message);
		}

		if (method_exists($order, 'save')) {
			$order->save();
		}

		$this->log('notice', 'BCI subscription renewal failed.', [
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
				return $this->clean($value);
			}
		}

		$status = $this->embedded_order_status($result);
		foreach ((array) ($status['attributes'] ?? []) as $attribute) {
			if (!is_array($attribute)) {
				continue;
			}

			if (($attribute['name'] ?? '') === 'mdOrder' && !empty($attribute['value'])) {
				return $this->clean($attribute['value']);
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

	private function currency_to_numeric(string $currency): string {
		if (is_object($this->gateway) && is_callable([$this->gateway, 'currency_to_numeric'])) {
			try {
				$value = $this->gateway->currency_to_numeric($currency);
				if ($value !== '') {
					return (string) $value;
				}
			} catch (\Throwable $e) {
				// Use local fallback map.
			}
		}

		$map = [
			'NZD' => '554',
			'EUR' => '978',
		];

		if (\class_exists(Api::class) && \is_callable([Api::class, 'payment_currency_to_numeric'])) {
			return Api::payment_currency_to_numeric($currency);
		}

		$currency = strtoupper($currency);
		if ($currency === 'EUR') {
			return $map['EUR'];
		}

		if ($currency !== '' && $currency !== 'NZD') {
			$this->log('notice', 'BCI TakuEcom renewals are fixed to NZD; ignoring WooCommerce renewal currency.', [
				'currency' => $currency,
			]);
		}

		return $map['NZD'];
	}

	private function build_order_number(\WC_Order $order): string {
		if (is_object($this->gateway) && is_callable([$this->gateway, 'build_order_number'])) {
			try {
				$value = $this->gateway->build_order_number($order);
				if ($value !== '') {
					return substr($this->clean($value), 0, 36);
				}
			} catch (\Throwable $e) {
				// Use local fallback below.
			}
		}

		return substr('WC' . $order->get_id() . '-' . gmdate('YmdHis'), 0, 36);
	}

	private function safe_description(\WC_Order $order): string {
		if (is_object($this->gateway) && is_callable([$this->gateway, 'safe_description'])) {
			try {
				$value = $this->gateway->safe_description($order);
				if ($value !== '') {
					return substr($this->clean($value), 0, 598);
				}
			} catch (\Throwable $e) {
				// Use local fallback below.
			}
		}

		$site_name = \function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'WooCommerce';
		$number    = method_exists($order, 'get_order_number') ? $order->get_order_number() : $order->get_id();

		return substr($this->clean(sprintf('WooCommerce order #%s - %s', $number, $site_name)), 0, 598);
	}

	private function environment_for_order(\WC_Order $order, $subscription, array $token_data): string {
		$environment = $this->clean($order->get_meta(Tokens::META_ENVIRONMENT));

		if ($environment === '' && is_object($subscription) && method_exists($subscription, 'get_meta')) {
			$environment = $this->clean($subscription->get_meta(Tokens::META_ENVIRONMENT));
		}

		if ($environment === '') {
			$environment = $this->clean($token_data['environment'] ?? '');
		}

		if ($environment === '' && is_object($this->gateway) && is_callable([$this->gateway, 'environment'])) {
			try {
				$environment = $this->clean($this->gateway->environment());
			} catch (\Throwable $e) {
				$environment = '';
			}
		}

		if ($environment === '' && \function_exists('get_option')) {
			$settings = get_option('woocommerce_' . $this->gateway_id() . '_settings', []);
			if (is_array($settings) && (($settings['test_mode'] ?? 'no') === 'yes')) {
				$environment = 'sandbox';
			}
		}

		return in_array($environment, ['live', 'sandbox'], true) ? $environment : 'live';
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

	private function subscriptions_enabled(): bool {
		if (is_object($this->gateway) && is_callable([$this->gateway, 'get_option'])) {
			return (string) $this->gateway->get_option('enable_subscriptions', 'no') === 'yes';
		}

		if (\class_exists(Api::class) && \is_callable([Api::class, 'subscriptions_enabled'])) {
			return Api::subscriptions_enabled();
		}

		if (\function_exists('get_option')) {
			$settings = get_option('woocommerce_' . $this->gateway_id() . '_settings', []);
			return is_array($settings) && (($settings['enable_subscriptions'] ?? 'no') === 'yes');
		}

		return false;
	}

	private function gateway_id(): string {
		if (is_object($this->gateway) && isset($this->gateway->id) && $this->gateway->id !== '') {
			return (string) $this->gateway->id;
		}

		if (\class_exists(Config::class) && \defined(Config::class . '::GATEWAY_ID')) {
			return (string) Config::GATEWAY_ID;
		}

		return 'bci_takuecom';
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

	private function clean($value): string {
		if (is_array($value) || is_object($value)) {
			return '';
		}

		$value = trim((string) $value);

		if (\function_exists('sanitize_text_field')) {
			return sanitize_text_field($value);
		}

		return trim(strip_tags($value));
	}

	private function log(string $level, string $message, array $context = []): void {
		if (!\class_exists(Log::class) || !\method_exists(Log::class, $level)) {
			return;
		}

		try {
			\call_user_func([Log::class, $level], $message, $context);
		} catch (\Throwable $e) {
			// Logging must never break scheduled renewal processing.
		}
	}
}
