<?php
/**
 * WooCommerce Subscriptions integration for BCI TakuEcom.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\Tokens') && is_readable(__DIR__ . '/class-tokens.php')) {
	require_once __DIR__ . '/class-tokens.php';
}

final class Subscriptions {
	private const SUPPORTS = [
		'products',
		'subscriptions',
		'subscription_cancellation',
		'subscription_suspension',
		'subscription_reactivation',
		'subscription_amount_changes',
		'subscription_date_changes',
		'subscription_payment_method_change',
		'subscription_payment_method_change_customer',
		'subscription_payment_method_change_admin',
		'multiple_subscriptions',
	];

	private const META_BINDING_MISSING_NOTE = '_bci_woo_binding_missing_note_added';

	/** @var object|null */
	private $gateway;

	/** @var Tokens */
	private $tokens;

	public function __construct($gateway = null, ?Tokens $tokens = null) {
		$this->gateway = is_object($gateway) ? $gateway : null;
		$this->tokens  = $tokens ?: new Tokens($this->gateway_id());
	}

	public static function register(): void {
		(new self())->register_hooks();
	}

	public static function gateway_supports(array $supports): array {
		return (new self())->merge_supports($supports);
	}

	public static function order_contains_subscription($order): bool {
		return (new self())->order_contains_subscription_instance($order);
	}

	public function contains_subscription($order): bool {
		return $this->order_contains_subscription_instance($order);
	}

	public function register_hooks(): void {
		if (!$this->subscriptions_enabled()) {
			return;
		}

		if (\function_exists('add_filter')) {
			add_filter('bci_woo_gateway_supports', [$this, 'filter_gateway_supports']);
			add_filter('bci_woo_register_payment_params', [$this, 'filter_register_payment_params'], 10, 2);
		}

		if (\function_exists('add_action')) {
			add_action('bci_woo_payment_status_resolved', [$this, 'capture_binding_after_status'], 10, 3);
			add_action('bci_woo_callback_received', [$this, 'capture_binding_from_callback'], 10, 2);
		}
	}

	public function add_gateway_supports($gateway = null): void {
		$gateway = is_object($gateway) ? $gateway : $this->gateway;

		if (!is_object($gateway)) {
			return;
		}

		$supports = [];
		if (isset($gateway->supports) && is_array($gateway->supports)) {
			$supports = $gateway->supports;
		}

		$gateway->supports = $this->merge_supports($supports);
	}

	public function filter_gateway_supports(array $supports): array {
		return $this->merge_supports($supports);
	}

	public function merge_supports(array $supports): array {
		if (!$this->subscriptions_enabled()) {
			return array_values($supports);
		}

		foreach (self::SUPPORTS as $support) {
			if (!in_array($support, $supports, true)) {
				$supports[] = $support;
			}
		}

		return array_values($supports);
	}

	public function filter_register_payment_params(array $params, $order): array {
		return $this->maybe_add_subscription_registration_params($params, $order);
	}

	/**
	 * Adds BPC stored-credential registration fields for initial subscription checkout.
	 *
	 * @param array              $params Register-payment parameters.
	 * @param \WC_Order|int|null $order  WooCommerce order or order ID.
	 */
	public function maybe_add_subscription_registration_params(array $params, $order): array {
		if (!$this->subscriptions_enabled()) {
			return $params;
		}

		$order = $this->maybe_order($order);

		if (!$order || !$this->order_contains_subscription_instance($order)) {
			return $params;
		}

		$client_id = $this->clean($params['clientId'] ?? '');
		if ($client_id === '') {
			$client_id = $this->tokens->client_id_for_order($order);
		}

		$params['clientId'] = $client_id;
		$params['features'] = $this->ensure_feature($params['features'] ?? '', 'FORCE_CREATE_BINDING');

		$order->update_meta_data(Tokens::META_CLIENT_ID, $client_id);
		if (method_exists($order, 'save')) {
			$order->save();
		}

		foreach ($this->tokens->related_subscriptions_for_order($order) as $subscription) {
			$this->tokens->store_subscription_token_data($subscription, [
				'client_id' => $client_id,
			]);
		}

		return $params;
	}

	public function capture_binding_after_status($order, $resolution = '', $status = []): void {
		if (!$this->subscriptions_enabled()) {
			return;
		}

		$order = $this->maybe_order($order);

		if (!$order || !is_array($status) || $status === []) {
			return;
		}

		if ($resolution !== '' && !in_array((string) $resolution, ['completed', 'paid', 'success'], true)) {
			return;
		}

		$this->capture_binding_from_status($order, $status);
	}

	public function capture_binding_from_status($order, array $status): bool {
		$order = $this->maybe_order($order);

		if (!$order || !$this->order_contains_subscription_instance($order)) {
			return false;
		}

		$client_id = $this->clean($order->get_meta(Tokens::META_CLIENT_ID));
		if ($client_id === '') {
			$client_id = $this->tokens->client_id_for_order($order);
		}

		$token_data = $this->tokens->token_data_from_status($status, $client_id);
		$environment = $this->clean($order->get_meta(Tokens::META_ENVIRONMENT));
		if ($environment !== '') {
			$token_data['environment'] = $environment;
		}

		if ($token_data['binding_id'] === '') {
			$this->maybe_note_missing_binding($order);
			return false;
		}

		return $this->tokens->store_order_token_data($order, $token_data);
	}

	public function capture_binding_from_callback($order, $params): bool {
		if (!$this->subscriptions_enabled()) {
			return false;
		}

		$order = $this->maybe_order($order);

		if (!$order || !is_array($params) || !$this->order_contains_subscription_instance($order)) {
			return false;
		}

		return $this->tokens->capture_from_callback(
			$order,
			$params,
			$this->clean($order->get_meta(Tokens::META_CLIENT_ID))
		);
	}

	public function cart_contains_subscription(): bool {
		if (\function_exists('wcs_cart_contains_subscription')) {
			try {
				return (bool) wcs_cart_contains_subscription();
			} catch (\Throwable $e) {
				// Continue to slower fallbacks.
			}
		}

		if (\class_exists('\WC_Subscriptions_Cart') && \method_exists('\WC_Subscriptions_Cart', 'cart_contains_subscription')) {
			try {
				return (bool) \WC_Subscriptions_Cart::cart_contains_subscription();
			} catch (\Throwable $e) {
				// Continue to product-type fallback.
			}
		}

		if (!\function_exists('WC') || !WC() || !isset(WC()->cart) || !method_exists(WC()->cart, 'get_cart')) {
			return false;
		}

		foreach ((array) WC()->cart->get_cart() as $cart_item) {
			$product = $cart_item['data'] ?? null;
			if ($this->product_is_subscription($product)) {
				return true;
			}
		}

		return false;
	}

	private function order_contains_subscription_instance($order): bool {
		$order = $this->maybe_order($order);
		if (!$order) {
			return false;
		}

		if (\function_exists('wcs_order_contains_subscription')) {
			foreach (['any', 'parent', 'switch'] as $type) {
				try {
					if (wcs_order_contains_subscription($order, $type)) {
						return true;
					}
				} catch (\Throwable $e) {
					continue;
				}
			}
		}

		if ($this->order_contains_renewal($order)) {
			return true;
		}

		if ($this->tokens->related_subscriptions_for_order($order) !== []) {
			return true;
		}

		foreach ((array) $order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}

			if ($this->product_is_subscription($item->get_product())) {
				return true;
			}
		}

		return false;
	}

	public function order_contains_renewal($order): bool {
		$order = $this->maybe_order($order);
		if (!$order) {
			return false;
		}

		if (\function_exists('wcs_order_contains_renewal')) {
			try {
				if (wcs_order_contains_renewal($order)) {
					return true;
				}
			} catch (\Throwable $e) {
				// Continue to relationship fallback.
			}
		}

		return $this->subscription_for_renewal_order($order) !== null;
	}

	/**
	 * @return object|null WC_Subscription-compatible object.
	 */
	public function subscription_for_renewal_order($renewal_order) {
		$renewal_order = $this->maybe_order($renewal_order);
		if (!$renewal_order) {
			return null;
		}

		if (\function_exists('wcs_get_subscriptions_for_renewal_order')) {
			try {
				$subscriptions = (array) wcs_get_subscriptions_for_renewal_order($renewal_order);
				$subscription  = reset($subscriptions);
				if (is_object($subscription)) {
					return $subscription;
				}
			} catch (\Throwable $e) {
				// Continue to older helper fallback.
			}
		}

		if (\function_exists('wcs_get_subscriptions_for_order')) {
			try {
				$subscriptions = (array) wcs_get_subscriptions_for_order($renewal_order, ['order_type' => 'renewal']);
				$subscription  = reset($subscriptions);
				if (is_object($subscription)) {
					return $subscription;
				}
			} catch (\Throwable $e) {
				// Continue to meta fallback.
			}
		}

		$subscription_id = 0;
		foreach (['_subscription_renewal', '_subscription_id'] as $key) {
			$value = method_exists($renewal_order, 'get_meta') ? (int) $renewal_order->get_meta($key) : 0;
			if ($value > 0) {
				$subscription_id = $value;
				break;
			}
		}

		if ($subscription_id > 0 && \function_exists('wcs_get_subscription')) {
			try {
				$subscription = wcs_get_subscription($subscription_id);
				return is_object($subscription) ? $subscription : null;
			} catch (\Throwable $e) {
				return null;
			}
		}

		return null;
	}

	private function maybe_note_missing_binding(\WC_Order $order): void {
		if ((string) $order->get_meta(self::META_BINDING_MISSING_NOTE) === 'yes') {
			return;
		}

		$message = __(
			'BCI TakuEcom could not store a card for future subscription renewals. The initial payment may be complete, but automatic renewals will fail until stored credential permission is enabled for this merchant account.',
			'bci-woo'
		);

		if (method_exists($order, 'add_order_note')) {
			$order->add_order_note($message);
		}

		$order->update_meta_data(self::META_BINDING_MISSING_NOTE, 'yes');
		if (method_exists($order, 'save')) {
			$order->save();
		}

		$this->log('notice', 'BCI subscription payment completed without a binding ID.', [
			'order_id' => $order->get_id(),
		]);
	}

	private function ensure_feature($existing, string $feature) {
		if (is_array($existing)) {
			if (!in_array($feature, $existing, true)) {
				$existing[] = $feature;
			}

			return $existing;
		}

		$existing = $this->clean($existing);
		if ($existing === '') {
			return $feature;
		}

		$features = preg_split('/[\s,]+/', $existing);
		if (in_array($feature, $features, true)) {
			return $existing;
		}

		return [$existing, $feature];
	}

	private function product_is_subscription($product): bool {
		if (!is_object($product) || !method_exists($product, 'is_type')) {
			return false;
		}

		foreach (['subscription', 'subscription_variation', 'variable-subscription'] as $type) {
			if ($product->is_type($type)) {
				return true;
			}
		}

		return false;
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
			// Logging must never interrupt checkout.
		}
	}
}
