<?php
declare(strict_types=1);

namespace BCI\Woo;

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists(__NAMESPACE__ . '\Order_State') && is_readable(__DIR__ . '/class-order-state.php')) {
	require_once __DIR__ . '/class-order-state.php';
}

if (!class_exists(__NAMESPACE__ . '\Payment_Resolution') && is_readable(__DIR__ . '/class-payment-resolution.php')) {
	require_once __DIR__ . '/class-payment-resolution.php';
}

final class Scheduler {
	public const HOOK = 'bci_woo_check_pending_orders';
	public const GROUP = 'bci-woo';
	public const INTERVAL_SECONDS = 300;
	public const PENDING_THRESHOLD_MINUTES = 10;
	public const FAILED_LOOKBACK_MINUTES = 60;
	public const CANCELLATION_HOLD_MAX_MINUTES = 1440;

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
	 * CamelCase alias for bootstrap code that follows older plugin style.
	 */
	public static function scheduleRecurringAction(): void {
		self::schedule_recurring_action();
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
			self::log('error', 'BCI scheduler cannot look up orders because WooCommerce is unavailable.');
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
				self::log('error', 'BCI scheduled status check failed.', [
					'order_id' => $order->get_id(),
					'error'    => $exception->getMessage(),
				]);
			}
		}

		return $checked;
	}

	/**
	 * CamelCase alias for manual admin actions that follow older plugin style.
	 */
	public static function checkPendingOrders(array $args = []): int {
		return self::check_pending_orders($args);
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

		if ($order->get_payment_method() !== self::gateway_id()
			|| !Payment_Resolution::is_resolvable($order)
		) {
			return $cancel;
		}

		try {
			$resolution = Payment_Resolution::resolve($order, Payment_Resolution::CANCELLATION_CHECK);
		} catch (\Throwable $exception) {
			self::log('error', 'BCI status check before unpaid-order cancellation failed.', [
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
		if ($resolution === 'pending' && $gateway_status === (int) self::config_constant('STATUS_REGISTERED', 0)) {
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

		self::log('info', 'BCI unpaid-order cancellation held until the gateway confirms the payment state.', [
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

		$max_minutes = max(1, (int) self::config_constant('CANCELLATION_HOLD_MAX_MINUTES', self::CANCELLATION_HOLD_MAX_MINUTES));

		return (time() - $timestamp) > $max_minutes * MINUTE_IN_SECONDS;
	}

	private static function query_orders(array $args): array {
		$orders = wc_get_orders(array_merge([
			'return'         => 'objects',
			'type'           => 'shop_order',
			'payment_method' => self::gateway_id(),
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
		return self::settings_int([
			'pending_threshold_minutes',
			'callback_pending_threshold_minutes',
		], (int) self::config_constant('PENDING_THRESHOLD_MINUTES', self::PENDING_THRESHOLD_MINUTES), 1);
	}

	private static function failed_lookback_minutes(): int {
		return self::settings_int([
			'failed_recovery_lookback_minutes',
			'failed_lookback_minutes',
		], (int) self::config_constant('FAILED_LOOKBACK_MINUTES', self::FAILED_LOOKBACK_MINUTES), 0);
	}

	private static function batch_size(): int {
		$batch_size = (int) self::config_constant('SCHEDULER_BATCH_SIZE', 50);

		return max(1, $batch_size);
	}

	private static function interval_seconds(): int {
		$interval = (int) self::config_constant('SCHEDULER_INTERVAL_SECONDS', self::INTERVAL_SECONDS);

		return max(60, $interval);
	}

	private static function settings_int(array $keys, int $default, int $minimum): int {
		if (!function_exists('get_option')) {
			return $default;
		}

		$settings = get_option(self::settings_option_key(), []);
		if (!is_array($settings)) {
			return $default;
		}

		foreach ($keys as $key) {
			if (isset($settings[$key]) && is_numeric($settings[$key])) {
				return max($minimum, (int) $settings[$key]);
			}
		}

		return $default;
	}

	private static function minutes_arg(array $args, string $key, int $default, int $minimum): int {
		if (isset($args[$key]) && is_numeric($args[$key])) {
			return max($minimum, (int) $args[$key]);
		}

		return $default;
	}

	private static function gateway_id(): string {
		return (string) self::config_constant('GATEWAY_ID', 'bci_takuecom');
	}

	private static function settings_option_key(): string {
		return (string) self::config_constant('OPTION_KEY', 'woocommerce_' . self::gateway_id() . '_settings');
	}

	private static function config_constant(string $name, $default) {
		$constant = Config::class . '::' . $name;

		return defined($constant) ? constant($constant) : $default;
	}

	private static function log(string $level, string $message, array $context = []): void {
		if (class_exists(Log::class) && is_callable([Log::class, $level])) {
			call_user_func([Log::class, $level], $message, $context);
			return;
		}

		if (function_exists('wc_get_logger')) {
			$logger = wc_get_logger();
			if (is_callable([$logger, $level])) {
				$logger->{$level}($message, array_merge(['source' => 'BCI_Woo_Plugin'], $context));
			}
		}
	}
}
