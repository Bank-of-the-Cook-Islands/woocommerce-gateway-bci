<?php
declare(strict_types=1);

namespace BCI\Woo;

if (!defined('ABSPATH')) {
	exit;
}

final class Scheduler {
	public const HOOK = 'bci_woo_check_pending_orders';
	public const GROUP = 'bci-woo';
	public const INTERVAL_SECONDS = 300;
	public const PENDING_THRESHOLD_MINUTES = 10;
	public const FAILED_LOOKBACK_MINUTES = 60;

	private const META_MD_ORDER = '_bci_woo_md_order';

	/**
	 * Register the Action Scheduler hook and recurring action.
	 */
	public static function register(): void {
		add_action(self::HOOK, [__CLASS__, 'check_pending_orders']);
		add_action('init', [__CLASS__, 'schedule_recurring_action']);
		add_action('action_scheduler_init', [__CLASS__, 'schedule_recurring_action']);
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

			if ((string) $order->get_meta(self::META_MD_ORDER) === '') {
				continue;
			}

			try {
				self::resolve_order($order, 'scheduled status check');
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

	private static function query_orders(array $args): array {
		$orders = wc_get_orders(array_merge([
			'return'         => 'objects',
			'type'           => 'shop_order',
			'payment_method' => self::gateway_id(),
			'meta_query'     => [
				[
					'key'     => self::META_MD_ORDER,
					'compare' => 'EXISTS',
				],
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

	private static function resolve_order(\WC_Order $order, string $context): void {
		$resolver = self::make_status_resolver();
		$resolver->resolve($order, $context);
	}

	private static function make_status_resolver(): object {
		$resolver = function_exists('apply_filters')
			? apply_filters('bci_woo_status_resolver', null)
			: null;

		if (is_object($resolver) && is_callable([$resolver, 'resolve'])) {
			return $resolver;
		}

		if (!class_exists(Status_Resolver::class)) {
			throw new \RuntimeException('BCI Status_Resolver is unavailable.');
		}

		$reflection  = new \ReflectionClass(Status_Resolver::class);
		$constructor = $reflection->getConstructor();

		if (!$constructor || $constructor->getNumberOfRequiredParameters() === 0) {
			return $reflection->newInstance();
		}

		$args = [];
		foreach ($constructor->getParameters() as $parameter) {
			if ($parameter->isOptional()) {
				break;
			}

			$type = $parameter->getType();
			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
				$type_name = $type->getName();

				if ($type_name === Api::class && class_exists(Api::class)) {
					$args[] = new Api();
					continue;
				}

				if ($type_name === Config::class && class_exists(Config::class)) {
					$args[] = new Config();
					continue;
				}
			}

			if (empty($args) && class_exists(Api::class)) {
				$args[] = new Api();
				continue;
			}

			throw new \RuntimeException('BCI Status_Resolver constructor cannot be inferred.');
		}

		return $reflection->newInstanceArgs($args);
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
