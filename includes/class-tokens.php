<?php
/**
 * Stored credential helpers for BCI TakuEcom WooCommerce payments.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\Order_State') && is_readable(__DIR__ . '/class-order-state.php')) {
	require_once __DIR__ . '/class-order-state.php';
}

if (!class_exists(__NAMESPACE__ . '\Api') && is_readable(__DIR__ . '/class-api.php')) {
	require_once __DIR__ . '/class-api.php';
}

final class Tokens {
	/** @var string */
	private $gateway_id;

	public function __construct(string $gateway_id = '') {
		$this->gateway_id = $gateway_id !== '' ? $gateway_id : self::default_gateway_id();
	}

	public static function store_from_order_and_status(\WC_Order $order, array $status): bool {
		return (new self())->capture_from_status($order, $status, Order_State::for($order)->client_id());
	}

	public static function default_gateway_id(): string {
		if (\class_exists(Config::class) && \defined(Config::class . '::GATEWAY_ID')) {
			return (string) Config::GATEWAY_ID;
		}

		return 'bci_takuecom';
	}

	/**
	 * Adds the BPC stored-credential fields to a register.do request.
	 *
	 * This is the only place the clientId and FORCE_CREATE_BINDING pair is built.
	 * The clientId is recorded on the order, and on the subscriptions it backs, as
	 * it is sent: the binding that comes back later belongs to whichever clientId
	 * was actually registered, and a renewal has nothing else to charge against.
	 *
	 * The caller decides whether the order wants a stored credential at all.
	 */
	public function add_binding_params(array $params, \WC_Order $order): array {
		$client_id = $this->clean($params['clientId'] ?? '');
		if ($client_id === '') {
			$client_id = $this->client_id_for_order($order);
		}

		$params['clientId'] = $client_id;
		$params['features'] = $this->ensure_feature($params['features'] ?? '', 'FORCE_CREATE_BINDING');

		Order_State::for($order)->record_client_id($client_id)->save();

		foreach ($this->related_subscriptions_for_order($order) as $subscription) {
			$this->store_subscription_token_data($subscription, [
				'client_id' => $client_id,
			]);
		}

		return $params;
	}

	public function client_id_for_order(\WC_Order $order): string {
		$customer_id = (int) $order->get_customer_id();

		if ($customer_id > 0) {
			return 'wc_customer_' . $customer_id;
		}

		$email = strtolower((string) $order->get_billing_email());
		$site  = $this->site_url_for_hash();

		return 'wc_guest_' . substr(hash('sha256', $email . '|' . $site), 0, 24);
	}

	public function capture_from_status(\WC_Order $order, array $status, string $fallback_client_id = ''): bool {
		$token_data = $this->token_data_from_status($status, $fallback_client_id);

		if ($token_data['binding_id'] === '' && $token_data['client_id'] === '') {
			return false;
		}

		return $this->store_order_token_data($order, $token_data);
	}

	public function capture_from_callback(\WC_Order $order, array $params, string $fallback_client_id = ''): bool {
		$token_data = $this->token_data_from_status($params, $fallback_client_id);

		if ($token_data['binding_id'] === '' && $token_data['client_id'] === '') {
			return false;
		}

		return $this->store_order_token_data($order, $token_data);
	}

	/**
	 * Captures the stored credential a subscription order's status came back with.
	 *
	 * The clientId comes from what the order recorded when it was registered, so
	 * the credential is filed against the identity the binding was created for
	 * even when the gateway answers without one. Only an environment the order
	 * actually recorded is carried onto the credential; the plugin's current
	 * setting is never stamped onto an older order.
	 *
	 * A completed subscription payment that carries no binding is the merchant
	 * permission failure, not a payment failure, so it is noted on the order once.
	 */
	public function capture_binding_from_status(\WC_Order $order, array $status): bool {
		$state     = Order_State::for($order);
		$client_id = $state->client_id();
		if ($client_id === '') {
			$client_id = $this->client_id_for_order($order);
		}

		$token_data = $this->token_data_from_status($status, $client_id);
		if ($state->has_environment()) {
			$token_data['environment'] = $state->environment();
		}

		if ($token_data['binding_id'] === '') {
			$this->note_binding_missing($order);
			return false;
		}

		return $this->store_order_token_data($order, $token_data);
	}

	public function token_data_from_status(array $status, string $fallback_client_id = ''): array {
		return [
			'binding_id' => $this->first_value($status, [
				'bindingId',
				'data.bindingId',
				'bindingInfo.bindingId',
				'orderStatus.bindingId',
				'orderStatus.bindingInfo.bindingId',
			]),
			'client_id'  => $this->first_value($status, [
				'clientId',
				'data.clientId',
				'bindingInfo.clientId',
				'orderStatus.clientId',
				'orderStatus.bindingInfo.clientId',
			], $fallback_client_id),
			'masked_pan' => Config::mask_pan($this->first_value($status, [
				'maskedPan',
				'displayLabel',
				'pan',
				'data.maskedPan',
				'bindingInfo.maskedPan',
				'cardAuthInfo.maskedPan',
				'cardAuthInfo.pan',
				'orderStatus.maskedPan',
				'orderStatus.cardAuthInfo.maskedPan',
				'orderStatus.cardAuthInfo.pan',
			])),
			'expiry'     => $this->first_value($status, [
				'expiryDate',
				'expiration',
				'cardExpiry',
				'data.expiryDate',
				'bindingInfo.expiryDate',
				'bindingInfo.expiry',
				'cardAuthInfo.expiration',
				'orderStatus.expiryDate',
				'orderStatus.cardAuthInfo.expiration',
			]),
			'card_type'  => $this->first_value($status, [
				'paymentSystem',
				'cardType',
				'bindingInfo.paymentSystem',
				'cardAuthInfo.paymentSystem',
				'orderStatus.cardAuthInfo.paymentSystem',
			]),
		];
	}

	public function store_order_token_data(\WC_Order $order, array $token_data): bool {
		$state = Order_State::for($order)->record_card($token_data);

		// The token is built from the recorded state rather than from the payload,
		// so the card it shows a customer cannot disagree with the order's meta.
		$this->maybe_create_payment_token($order, $state, $token_data);

		$changed = $state->has_changes();
		$state->save();

		foreach ($this->related_subscriptions_for_order($order) as $subscription) {
			$changed = $this->store_subscription_token_data($subscription, $token_data) || $changed;
		}

		if (($token_data['binding_id'] ?? '') !== '') {
			$this->log('info', 'Stored BCI binding metadata for order.', [
				'order_id' => method_exists($order, 'get_id') ? $order->get_id() : null,
			]);
		}

		return $changed;
	}

	/**
	 * @param object $subscription Expected WC_Subscription-compatible object.
	 */
	public function store_subscription_token_data($subscription, array $token_data): bool {
		if (!is_object($subscription) || !method_exists($subscription, 'update_meta_data')) {
			return false;
		}

		$state   = Order_State::for($subscription)->record_card($token_data);
		$changed = $state->has_changes();

		$state->save();

		return $changed;
	}

	public function token_data_from_order(\WC_Order $order): array {
		return Order_State::for($order)->stored_card();
	}

	/**
	 * @param object $subscription Expected WC_Subscription-compatible object.
	 */
	public function token_data_from_subscription($subscription): array {
		if (!is_object($subscription) || !method_exists($subscription, 'get_meta')) {
			return [];
		}

		return Order_State::for($subscription)->stored_card();
	}

	public function related_subscriptions_for_order(\WC_Order $order): array {
		$subscriptions = [];

		if (\function_exists('wcs_get_subscriptions_for_order')) {
			foreach (['any', 'parent', 'switch'] as $order_type) {
				try {
					$subscriptions = array_merge(
						$subscriptions,
						(array) wcs_get_subscriptions_for_order($order, ['order_type' => $order_type])
					);
				} catch (\Throwable $e) {
					continue;
				}
			}
		}

		if (\function_exists('wcs_get_subscriptions_for_renewal_order')) {
			try {
				$subscriptions = array_merge($subscriptions, (array) wcs_get_subscriptions_for_renewal_order($order));
			} catch (\Throwable $e) {
				// Keep the explicit parent-order lookup above usable on older WCS versions.
			}
		}

		$unique = [];
		foreach ($subscriptions as $subscription) {
			if (!is_object($subscription) || !method_exists($subscription, 'get_id')) {
				continue;
			}

			$unique[(int) $subscription->get_id()] = $subscription;
		}

		return array_values($unique);
	}

	private function maybe_create_payment_token(\WC_Order $order, Order_State $state, array $token_data): int {
		// Saved cards belong to the experimental subscriptions feature. While it is off the
		// gateway does not declare tokenisation support, so a token would be unusable at
		// checkout and would surface an unconsented card under My Account. Unlike the
		// registration hooks, this runs on every one-off payment that comes back with
		// binding data, so it is the gate itself rather than a re-check behind one.
		if (!Api::subscriptions_enabled()) {
			return 0;
		}

		if (!\class_exists('\WC_Payment_Token_CC') || !\class_exists('\WC_Payment_Tokens')) {
			return 0;
		}

		$binding_id = $state->binding_id();
		$user_id    = (int) $order->get_customer_id();
		$last4      = $this->last4_from_mask($state->masked_pan());
		$expiry     = $state->card_expiry();

		if ($binding_id === '' || $user_id <= 0 || $last4 === '' || strlen($expiry) !== 6) {
			return 0;
		}

		try {
			$tokens = \WC_Payment_Tokens::get_customer_tokens($user_id, $this->gateway_id);
			foreach ($tokens as $token) {
				if (method_exists($token, 'get_token') && hash_equals((string) $token->get_token(), $binding_id)) {
					return (int) $token->get_id();
				}
			}

			$token = new \WC_Payment_Token_CC();
			$token->set_token($binding_id);
			$token->set_gateway_id($this->gateway_id);
			$token->set_user_id($user_id);
			$token->set_last4($last4);
			$token->set_expiry_year(substr($expiry, 0, 4));
			$token->set_expiry_month(substr($expiry, 4, 2));

			$card_type = $this->normalise_card_type($token_data['card_type'] ?? '');
			if ($card_type !== '') {
				$token->set_card_type($card_type);
			}

			return (int) $token->save();
		} catch (\Throwable $e) {
			$this->log('notice', 'Could not create WooCommerce payment token for BCI binding.', [
				'order_id' => $order->get_id(),
				'error'    => $e->getMessage(),
			]);

			return 0;
		}
	}

	private function note_binding_missing(\WC_Order $order): void {
		$state = Order_State::for($order);

		if ($state->binding_missing_noted()) {
			return;
		}

		$message = __(
			'BCI TakuEcom could not store a card for future subscription renewals. The initial payment may be complete, but automatic renewals will fail until stored credential permission is enabled for this merchant account.',
			'bci-woo'
		);

		if (method_exists($order, 'add_order_note')) {
			$order->add_order_note($message);
		}

		$state->note_binding_missing()->save();

		$this->log('notice', 'BCI subscription payment completed without a binding ID.', [
			'order_id' => $order->get_id(),
		]);
	}

	/**
	 * Merges a feature into an existing features value.
	 *
	 * BPC takes each feature as its own `features` parameter, repeated within the
	 * one request, so several features are returned as a list and Api encodes
	 * them as features=A&features=B. A comma-joined string would instead reach
	 * the gateway as a single unrecognised feature name, which either drops
	 * FORCE_CREATE_BINDING or fails register.do validation, so it is never
	 * produced here.
	 *
	 * @param mixed $existing Existing features value (string or array).
	 * @return string|string[] A single feature as a string, several as a list.
	 */
	private function ensure_feature($existing, string $feature) {
		$features = [];

		foreach (is_array($existing) ? $existing : [$existing] as $value) {
			$value = $this->clean($value);
			if ($value === '') {
				continue;
			}

			foreach (preg_split('/[\s,]+/', $value) ?: [] as $part) {
				if ($part !== '' && !in_array($part, $features, true)) {
					$features[] = $part;
				}
			}
		}

		if (!in_array($feature, $features, true)) {
			$features[] = $feature;
		}

		return count($features) === 1 ? $features[0] : $features;
	}

	private function first_value(array $source, array $paths, string $fallback = ''): string {
		foreach ($paths as $path) {
			$value = $this->array_get($source, $path);
			if ($value !== null && $value !== '') {
				return $this->clean($value);
			}
		}

		return $this->clean($fallback);
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

	private function last4_from_mask($value): string {
		$digits = preg_replace('/\D+/', '', $this->clean($value));

		return strlen($digits) >= 4 ? substr($digits, -4) : '';
	}

	private function normalise_card_type($value): string {
		$value = strtolower($this->clean($value));

		$map = [
			'mastercard' => 'mastercard',
			'master card' => 'mastercard',
			'visa' => 'visa',
			'amex' => 'amex',
			'american express' => 'amex',
			'jcb' => 'jcb',
		];

		return $map[$value] ?? '';
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

	private function site_url_for_hash(): string {
		if (\function_exists('home_url')) {
			return (string) home_url();
		}

		if (\function_exists('site_url')) {
			return (string) site_url();
		}

		return '';
	}

	private function log(string $level, string $message, array $context = []): void {
		if (!\class_exists(Log::class) || !\method_exists(Log::class, $level)) {
			return;
		}

		try {
			\call_user_func([Log::class, $level], $message, $context);
		} catch (\Throwable $e) {
			// Logging must never break payment processing.
		}
	}
}
