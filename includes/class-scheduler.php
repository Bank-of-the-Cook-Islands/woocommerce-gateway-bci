<?php
declare(strict_types=1);

namespace BCI\Woo;

if (!defined('ABSPATH')) {
	exit;
}

final class Scheduler {
	public const HOOK = 'bci_woo_check_pending_orders';
	public const GROUP = 'bci-woo';

	/**
	 * Register the Action Scheduler hook and recurring action.
	 */
	public static function register(): void {
		add_action(self::HOOK, [__CLASS__, 'check_pending_orders']);
		add_action('init', [__CLASS__, 'schedule_recurring_action']);
		add_action('action_scheduler_init', [__CLASS__, 'schedule_recurring_action']);
		add_filter('woocommerce_cancel_unpaid_order', [__CLASS__, 'maybe_hold_unpaid_cancellation'], 10, 2);
	}

	/**
	 * Ensure the recurring status check is scheduled.
	 */
	public static function schedule_recurring_action(): void {
		if (!function_exists('as_has_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
			return;
		}

		if (!as_has_scheduled_action(self::HOOK, [], self::GROUP)) {
			as_schedule_recurring_action(time(), self::interval_seconds(), self::HOOK, [], self::GROUP, true);
		}
	}

	/**
	 * Clear recurring actions on plugin deactivation.
	 */
	public static function deactivate(): void {
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions(self::HOOK, [], self::GROUP);
		}

		if (function_exists('wp_clear_scheduled_hook')) {
			wp_clear_scheduled_hook(self::HOOK);
		}
	}

	/**
	 * Find pending and recently failed BCI orders, then refresh gateway status.
	 */
	public static function check_pending_orders(array $args = []): int {
		if (!function_exists('wc_get_orders')) {
			Log::error('BCI scheduler cannot look up orders because WooCommerce is unavailable.');
			return 0;
		}

		$limit                   = self::batch_size();
		$pending_threshold       = self::minutes_arg($args, 'pending_threshold_minutes', self::pending_threshold_minutes(), 1);
		$failed_lookback_minutes = self::minutes_arg($args, 'failed_lookback_minutes', self::failed_lookback_minutes(), 0);
		$pending_after           = time() - ($pending_threshold * MINUTE_IN_SECONDS);
		$failed_lookback         = time() - ($failed_lookback_minutes * MINUTE_IN_SECONDS);

		$pending_orders = self::query_orders([
			'limit'        => $limit,
			'status'       => ['pending'],
			'date_created' => '<' . $pending_after,
			'orderby'      => 'date',
			'order'        => 'ASC',
		]);

		$failed_orders = $failed_lookback_minutes > 0
			? self::query_orders([
				'limit'         => $limit,
				'status'        => ['failed'],
				'date_modified' => '>' . $failed_lookback,
				'orderby'       => 'modified',
				'order'         => 'ASC',
			])
			: [];

		$orders  = array_slice(self::merge_orders($pending_orders, $failed_orders), 0, $limit);
		$checked = 0;

		foreach ($orders as $order) {
			if (!$order instanceof \WC_Order) {
				continue;
			}

			if (!Payment_Resolution::is_resolvable($order)) {
				continue;
			}

			try {
				Payment_Resolution::resolve($order, Payment_Resolution::SCHEDULED_CHECK);
				$checked++;
			} catch (\Throwable $exception) {
				Log::error('BCI scheduled status check failed.', [
					'order_id' => $order->get_id(),
					'error'    => $exception->getMessage(),
				]);
			}
		}

		return $checked;
	}

	/**
	 * Do not let WooCommerce cancel an unpaid BCI order until the gateway has
	 * confirmed that no payment was collected for it.
	 *
	 * Customers, typically guests, can complete payment on the hosted form after
	 * the hold-stock window expires; cancelling without checking the gateway
	 * would strand a paid order in Cancelled.
	 *
	 * @param mixed $cancel Whether WooCommerce intends to cancel the order.
	 * @param mixed $order  The unpaid order under consideration.
	 * @return mixed
	 */
	public static function maybe_hold_unpaid_cancellation($cancel, $order) {
		if (!$cancel || !$order instanceof \WC_Order) {
			return $cancel;
		}

		if ($order->get_payment_method() !== Config::GATEWAY_ID
			|| !Payment_Resolution::is_resolvable($order)
		) {
			return $cancel;
		}

		try {
			$resolution = Payment_Resolution::resolve($order, Payment_Resolution::CANCELLATION_CHECK);
		} catch (\Throwable $exception) {
			Log::error('BCI status check before unpaid-order cancellation failed.', [
				'order_id' => $order->get_id(),
				'error'    => $exception->getMessage(),
			]);

			return self::past_cancellation_hold_limit($order) ? $cancel : false;
		}

		if (in_array($resolution, ['completed', 'refunded', 'cancelled', 'failed'], true)) {
			// The resolver has already moved the order to its final state.
			return false;
		}

		$gateway_status = Order_State::for($order)->last_status();
		if ($resolution === 'pending' && $gateway_status === Config::STATUS_REGISTERED) {
			// Registered with no payment attempt in flight: the customer abandoned
			// the hosted form. Cancelling is safe, and a late callback can still
			// recover the order because Callback also resolves cancelled orders.
			return $cancel;
		}

		// A payment attempt may be in flight (authorisation, 3-D Secure) or the
		// gateway state is unclear. Hold the cancellation so a callback or the
		// next cancellation run can settle it, but not indefinitely.
		if (self::past_cancellation_hold_limit($order)) {
			return $cancel;
		}

		Log::info('BCI unpaid-order cancellation held until the gateway confirms the payment state.', [
			'order_id'   => $order->get_id(),
			'resolution' => $resolution,
		]);

		return false;
	}

	private static function past_cancellation_hold_limit(\WC_Order $order): bool {
		$created   = $order->get_date_created();
		$timestamp = $created ? (int) $created->getTimestamp() : 0;

		if ($timestamp <= 0) {
			return true;
		}

		$max_minutes = max(1, Config::CANCELLATION_HOLD_MAX_MINUTES);

		return (time() - $timestamp) > $max_minutes * MINUTE_IN_SECONDS;
	}

	private static function query_orders(array $args): array {
		$orders = wc_get_orders(array_merge([
			'return'         => 'objects',
			'type'           => 'shop_order',
			'payment_method' => Config::GATEWAY_ID,
			'meta_query'     => [
				Order_State::gateway_reference_query(),
			],
		], $args));

		return is_array($orders) ? $orders : [];
	}

	private static function merge_orders(array ...$order_sets): array {
		$orders = [];

		foreach ($order_sets as $order_set) {
			foreach ($order_set as $order) {
				if ($order instanceof \WC_Order) {
					$orders[$order->get_id()] = $order;
				}
			}
		}

		return array_values($orders);
	}

	private static function pending_threshold_minutes(): int {
		return self::settings_int('pending_threshold_minutes', Config::PENDING_THRESHOLD_MINUTES, 1);
	}

	private static function failed_lookback_minutes(): int {
		return self::settings_int('failed_lookback_minutes', Config::FAILED_LOOKBACK_MINUTES, 0);
	}

	private static function batch_size(): int {
		return max(1, Config::SCHEDULER_BATCH_SIZE);
	}

	private static function interval_seconds(): int {
		return max(60, Config::SCHEDULER_INTERVAL_SECONDS);
	}

	/**
	 * A saved settings value, floored, or $default when the merchant has none.
	 *
	 * The key is one the gateway's settings schema actually produces; a key no
	 * form field writes could only ever answer with its default.
	 */
	private static function settings_int(string $key, int $default, int $minimum): int {
		$settings = Api::settings();

		if (isset($settings[$key]) && is_numeric($settings[$key])) {
			return max($minimum, (int) $settings[$key]);
		}

		return $default;
	}

	private static function minutes_arg(array $args, string $key, int $default, int $minimum): int {
		if (isset($args[$key]) && is_numeric($args[$key])) {
			return max($minimum, (int) $args[$key]);
		}

		return $default;
	}
}
