<?php
/**
 * Stored credential helpers for BCI TakuEcom WooCommerce payments.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

final class Tokens {
	public const META_BINDING_ID = '_bci_woo_binding_id';
	public const META_CLIENT_ID = '_bci_woo_client_id';
	public const META_MASKED_PAN = '_bci_woo_masked_pan';
	public const META_CARD_EXPIRY = '_bci_woo_card_expiry';
	public const META_ENVIRONMENT = '_bci_woo_environment';
	public const META_WC_TOKEN_ID = '_bci_woo_wc_token_id';

	/** @var string */
	private $gateway_id;

	public function __construct(string $gateway_id = '') {
		$this->gateway_id = $gateway_id !== '' ? $gateway_id : self::default_gateway_id();
	}

	public static function store_from_order_and_status(\WC_Order $order, array $status): bool {
		return (new self())->capture_from_status($order, $status, (string) $order->get_meta(self::META_CLIENT_ID, true));
	}

	public static function default_gateway_id(): string {
		if (\class_exists(Config::class) && \defined(Config::class . '::GATEWAY_ID')) {
			return (string) Config::GATEWAY_ID;
		}

		return 'bci_takuecom';
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
			'masked_pan' => $this->first_value($status, [
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
			]),
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
		$changed = false;

		$changed = $this->update_meta_if_present($order, self::META_BINDING_ID, $token_data['binding_id'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($order, self::META_CLIENT_ID, $token_data['client_id'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($order, self::META_MASKED_PAN, $token_data['masked_pan'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($order, self::META_CARD_EXPIRY, $this->normalise_expiry($token_data['expiry'] ?? '')) || $changed;

		$environment = $this->clean($token_data['environment'] ?? '');
		if ($environment === '' && method_exists($order, 'get_meta')) {
			$environment = (string) $order->get_meta(self::META_ENVIRONMENT);
		}
		$changed     = $this->update_meta_if_present($order, self::META_ENVIRONMENT, $environment) || $changed;

		$wc_token_id = $this->maybe_create_payment_token($order, $token_data);
		if ($wc_token_id > 0) {
			$changed = $this->update_meta_if_present($order, self::META_WC_TOKEN_ID, (string) $wc_token_id) || $changed;
		}

		if ($changed && method_exists($order, 'save')) {
			$order->save();
		}

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

		$changed = false;
		$changed = $this->update_meta_if_present($subscription, self::META_BINDING_ID, $token_data['binding_id'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($subscription, self::META_CLIENT_ID, $token_data['client_id'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($subscription, self::META_MASKED_PAN, $token_data['masked_pan'] ?? '') || $changed;
		$changed = $this->update_meta_if_present($subscription, self::META_CARD_EXPIRY, $this->normalise_expiry($token_data['expiry'] ?? '')) || $changed;

		if (($token_data['environment'] ?? '') !== '') {
			$changed = $this->update_meta_if_present($subscription, self::META_ENVIRONMENT, $token_data['environment']) || $changed;
		}

		if ($changed && method_exists($subscription, 'save')) {
			$subscription->save();
		}

		return $changed;
	}

	public function token_data_from_order(\WC_Order $order): array {
		return [
			'binding_id'  => $this->clean($order->get_meta(self::META_BINDING_ID)),
			'client_id'   => $this->clean($order->get_meta(self::META_CLIENT_ID)),
			'masked_pan'  => $this->clean($order->get_meta(self::META_MASKED_PAN)),
			'expiry'      => $this->clean($order->get_meta(self::META_CARD_EXPIRY)),
			'environment' => $this->clean($order->get_meta(self::META_ENVIRONMENT)),
		];
	}

	/**
	 * @param object $subscription Expected WC_Subscription-compatible object.
	 */
	public function token_data_from_subscription($subscription): array {
		if (!is_object($subscription) || !method_exists($subscription, 'get_meta')) {
			return [];
		}

		return [
			'binding_id'  => $this->clean($subscription->get_meta(self::META_BINDING_ID)),
			'client_id'   => $this->clean($subscription->get_meta(self::META_CLIENT_ID)),
			'masked_pan'  => $this->clean($subscription->get_meta(self::META_MASKED_PAN)),
			'expiry'      => $this->clean($subscription->get_meta(self::META_CARD_EXPIRY)),
			'environment' => $this->clean($subscription->get_meta(self::META_ENVIRONMENT)),
		];
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

	private function maybe_create_payment_token(\WC_Order $order, array $token_data): int {
		if (!\class_exists('\WC_Payment_Token_CC') || !\class_exists('\WC_Payment_Tokens')) {
			return 0;
		}

		$binding_id = $this->clean($token_data['binding_id'] ?? '');
		$user_id    = (int) $order->get_customer_id();
		$last4      = $this->last4_from_mask($token_data['masked_pan'] ?? '');
		$expiry     = $this->normalise_expiry($token_data['expiry'] ?? '');

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

	private function update_meta_if_present($object, string $key, $value): bool {
		$value = $this->clean($value);
		if ($value === '' || !is_object($object) || !method_exists($object, 'update_meta_data')) {
			return false;
		}

		if (method_exists($object, 'get_meta') && (string) $object->get_meta($key) === $value) {
			return false;
		}

		$object->update_meta_data($key, $value);
		return true;
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

	private function normalise_expiry($value): string {
		$value = preg_replace('/\D+/', '', $this->clean($value));

		if (strlen($value) === 4) {
			// Accept MMYY from legacy/token displays and normalise to YYYYMM.
			return '20' . substr($value, 2, 2) . substr($value, 0, 2);
		}

		return strlen($value) >= 6 ? substr($value, 0, 6) : $value;
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
