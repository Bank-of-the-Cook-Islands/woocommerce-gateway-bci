<?php
/**
 * WooCommerce Subscriptions integration for BCI TakuEcom.
 *
 * Two jobs, and nothing else: answering what WooCommerce Subscriptions says
 * about an order, and wiring the subscription hooks up when the merchant has
 * turned the feature on. The stored-credential work those hooks do lives in
 * Tokens, so there is one copy of it and one place that knows how a binding is
 * requested, captured and filed.
 *
 * The enable_subscriptions setting is answered here in register_hooks(), and at
 * the gateway's two registration sites. Every callback below runs only because
 * that answer was yes, so none of them ask again: the only place left that reads
 * the setting outside registration is the saved-card gate in Tokens, which runs
 * on one-off payments too and so is not behind any of these.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined('ABSPATH') || exit;

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

	/** @var object|null */
	private $gateway;

	/** @var Tokens */
	private $tokens;

	public function __construct($gateway = null, ?Tokens $tokens = null) {
		$this->gateway = is_object($gateway) ? $gateway : null;
		$this->tokens  = $tokens ?: new Tokens($this->gateway_id());
	}

	/**
	 * Wires the subscription hooks, if the merchant has enabled subscriptions.
	 */
	public function register_hooks(): void {
		if (!Api::subscriptions_enabled()) {
			return;
		}

		if (\function_exists('add_filter')) {
			add_filter('bci_woo_register_payment_params', [$this, 'filter_register_payment_params'], 10, 2);
		}

		if (\function_exists('add_action')) {
			add_action('bci_woo_payment_status_resolved', [$this, 'capture_binding_after_status'], 10, 3);
			add_action('bci_woo_callback_received', [$this, 'capture_binding_from_callback'], 10, 2);
		}
	}

	/**
	 * The gateway supports a subscription store adds to its own.
	 *
	 * Called from the gateway constructor only when the gate is open, so the
	 * supports are advertised exactly when the hooks that honour them exist.
	 */
	public function merge_supports(array $supports): array {
		foreach (self::SUPPORTS as $support) {
			if (!in_array($support, $supports, true)) {
				$supports[] = $support;
			}
		}

		return array_values($supports);
	}

	/**
	 * Asks BPC to create a stored credential for an initial subscription checkout.
	 *
	 * @param array              $params Register-payment parameters.
	 * @param \WC_Order|int|null $order  WooCommerce order or order ID.
	 */
	public function filter_register_payment_params(array $params, $order): array {
		$order = $this->maybe_order($order);

		if (!$order || !$this->contains_subscription($order)) {
			return $params;
		}

		return $this->tokens->add_binding_params($params, $order);
	}

	public function capture_binding_after_status($order, $resolution = '', $status = []): void {
		$order = $this->maybe_order($order);

		if (!$order || !is_array($status) || $status === []) {
			return;
		}

		if ($resolution !== '' && !in_array((string) $resolution, ['completed', 'paid', 'success'], true)) {
			return;
		}

		if (!$this->contains_subscription($order)) {
			return;
		}

		$this->tokens->capture_binding_from_status($order, $status);
	}

	public function capture_binding_from_callback($order, $params): bool {
		$order = $this->maybe_order($order);

		if (!$order || !is_array($params) || !$this->contains_subscription($order)) {
			return false;
		}

		return $this->tokens->capture_from_callback(
			$order,
			$params,
			Order_State::for($order)->client_id()
		);
	}

	public function contains_subscription($order): bool {
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

	private function gateway_id(): string {
		if (is_object($this->gateway) && isset($this->gateway->id) && $this->gateway->id !== '') {
			return (string) $this->gateway->id;
		}

		return Config::GATEWAY_ID;
	}
}
