<?php
declare(strict_types=1);

namespace BCI\Woo;

if (!defined('ABSPATH')) {
	exit;
}

final class Callback {
	private const META_MD_ORDER = '_bci_woo_md_order';
	private const META_ORDER_NUMBER = '_bci_woo_order_number';

	/**
	 * Register the BCI callback REST endpoint.
	 */
	public static function register(): void {
		add_action('rest_api_init', [__CLASS__, 'register_route']);
	}

	/**
	 * Register /wp-json/bci-woo/v1/callback for BPC notifications.
	 */
	public static function register_route(): void {
		register_rest_route(self::callback_namespace(), self::callback_route(), [
			'methods'             => ['GET', 'POST'],
			'callback'            => [__CLASS__, 'handle'],
			'permission_callback' => '__return_true',
		]);
	}

	/**
	 * Return the callback URL for display in merchant settings.
	 */
	public static function get_callback_url(): string {
		return rest_url(trim(self::callback_namespace(), '/') . self::callback_route());
	}

	/**
	 * Back-compat camelCase alias for settings screens that prefer it.
	 */
	public static function getCallbackUrl(): string {
		return self::get_callback_url();
	}

	/**
	 * Handle an incoming BPC callback notification.
	 */
	public static function handle(\WP_REST_Request $request): \WP_REST_Response {
		$params       = self::extract_params($request);
		$order_number = self::first_scalar($params, ['orderNumber']);
		$md_order     = self::first_scalar($params, ['mdOrder', 'mdorder', 'orderId']);

		if ($order_number === '' && $md_order === '') {
			self::log('notice', 'BCI callback rejected: missing orderNumber and mdOrder.');
			return new \WP_REST_Response(null, 400);
		}

		$tokens = self::callback_tokens();
		if (empty($tokens)) {
			self::log('notice', 'BCI callback rejected: no live or sandbox callback token is configured.');
			return new \WP_REST_Response(null, 403);
		}

		if (!self::verify_against_tokens($params, $tokens)) {
			self::log('notice', 'BCI callback rejected: checksum verification failed.', [
				'order_number' => self::safe_log_value($order_number),
				'md_order'     => self::safe_log_value($md_order),
			]);
			return new \WP_REST_Response(null, 400);
		}

		$order = self::find_order($order_number, $md_order);
		if (!$order) {
			self::log('notice', 'BCI callback order was not found.', [
				'order_number' => self::safe_log_value($order_number),
				'md_order'     => self::safe_log_value($md_order),
			]);
			return new \WP_REST_Response(null, 200);
		}

		if ($md_order !== '') {
			$stored_md_order = (string) $order->get_meta(self::META_MD_ORDER);

			if ($stored_md_order !== '' && !hash_equals($stored_md_order, $md_order)) {
				self::log('notice', 'BCI callback rejected: mdOrder does not match order meta.', [
					'order_id' => $order->get_id(),
				]);
				return new \WP_REST_Response(null, 400);
			}

			if ($stored_md_order === '') {
				$order->update_meta_data(self::META_MD_ORDER, $md_order);
				$order->save();
			}
		}

		if ((string) $order->get_meta(self::META_MD_ORDER) === '') {
			self::log('notice', 'BCI callback cannot resolve status because the order has no mdOrder.', [
				'order_id' => $order->get_id(),
			]);
			return new \WP_REST_Response(null, 200);
		}

		if (!self::should_resolve($order)) {
			self::log('info', 'BCI callback ignored for order in terminal status.', [
				'order_id' => $order->get_id(),
				'status'   => $order->get_status(),
			]);
			return new \WP_REST_Response(null, 200);
		}

		try {
			if (function_exists('do_action')) {
				do_action('bci_woo_callback_received', $order, $params);
			}
			self::resolve_order($order, 'gateway callback');
		} catch (\Throwable $exception) {
			self::log('error', 'BCI callback status resolution failed.', [
				'order_id' => $order->get_id(),
				'error'    => $exception->getMessage(),
			]);
			return new \WP_REST_Response(null, 500);
		}

		return new \WP_REST_Response(null, 200);
	}

	/**
	 * Extract raw BPC callback parameters without WordPress routing noise.
	 */
	private static function extract_params(\WP_REST_Request $request): array {
		$params = $request->get_query_params();

		if ($request->get_method() === 'POST') {
			$body_params = $request->get_body_params();

			if (empty($body_params)) {
				parse_str($request->get_body(), $body_params);
			}

			$params = array_merge($params, $body_params);
		}

		unset($params['rest_route']);

		return $params;
	}

	private static function verify_against_tokens(array $params, array $tokens): bool {
		foreach ($tokens as $token) {
			if (self::verify_checksum($params, $token)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify the HMAC-SHA256 checksum using the BPC signing algorithm.
	 */
	private static function verify_checksum(array $params, string $token): bool {
		$received = self::extract_checksum($params);
		if ($received === '') {
			return false;
		}

		$params = self::remove_signature_params($params);
		ksort($params);

		$parts = [];
		foreach ($params as $key => $value) {
			if (is_array($value)) {
				return false;
			}

			$parts[] = $key . ';' . (string) $value;
		}

		$signed   = implode(';', $parts) . ';';
		$computed = strtoupper(hash_hmac('sha256', $signed, $token));

		return hash_equals($computed, strtoupper($received));
	}

	private static function extract_checksum(array $params): string {
		foreach ($params as $key => $value) {
			if (strtolower((string) $key) === 'checksum') {
				return is_scalar($value) ? (string) $value : '';
			}
		}

		return '';
	}

	private static function remove_signature_params(array $params): array {
		foreach (array_keys($params) as $key) {
			if (in_array(strtolower((string) $key), ['checksum', 'sign_alias'], true)) {
				unset($params[$key]);
			}
		}

		return $params;
	}

	private static function find_order(string $order_number, string $md_order): ?\WC_Order {
		if (!function_exists('wc_get_orders')) {
			self::log('error', 'BCI callback cannot look up orders because WooCommerce is unavailable.');
			return null;
		}

		if ($order_number !== '') {
			$order = self::find_order_by_meta(self::META_ORDER_NUMBER, $order_number);
			if ($order) {
				return $order;
			}

			$order_id = self::parse_order_id($order_number);
			if ($order_id > 0) {
				$order = wc_get_order($order_id);

				if ($order instanceof \WC_Order && self::is_bci_order($order)) {
					return $order;
				}
			}
		}

		if ($md_order !== '') {
			return self::find_order_by_meta(self::META_MD_ORDER, $md_order);
		}

		return null;
	}

	private static function find_order_by_meta(string $meta_key, string $meta_value): ?\WC_Order {
		$orders = wc_get_orders([
			'limit'      => 1,
			'return'     => 'objects',
			'type'       => 'shop_order',
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => [
				[
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => '=',
				],
			],
		]);

		$order = $orders[0] ?? null;

		return $order instanceof \WC_Order ? $order : null;
	}

	private static function parse_order_id(string $order_number): int {
		if (ctype_digit($order_number)) {
			return absint($order_number);
		}

		if (preg_match('/^WC-?(\d+)(?:\D|$)/i', $order_number, $matches)) {
			return absint($matches[1]);
		}

		if (preg_match('/(?:^|\D)order[_-]?(\d+)(?:\D|$)/i', $order_number, $matches)) {
			return absint($matches[1]);
		}

		return 0;
	}

	private static function is_bci_order(\WC_Order $order): bool {
		return $order->get_payment_method() === self::gateway_id()
			|| (string) $order->get_meta(self::META_ORDER_NUMBER) !== ''
			|| (string) $order->get_meta(self::META_MD_ORDER) !== '';
	}

	private static function should_resolve(\WC_Order $order): bool {
		return $order->has_status(['pending', 'failed', 'on-hold']) || $order->is_paid();
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

	private static function callback_tokens(): array {
		$tokens = [];

		if (is_callable([Api::class, 'callback_tokens'])) {
			$api_tokens = Api::callback_tokens();
			if (is_array($api_tokens)) {
				$tokens = array_merge($tokens, $api_tokens);
			}
		}

		$settings = self::gateway_settings();
		$tokens[] = self::setting($settings, [
			'live_callback_token',
			'callback_token_live',
			'live_callback_checksum_token',
		]);
		$tokens[] = self::setting($settings, [
			'sandbox_callback_token',
			'callback_token_sandbox',
			'test_callback_token',
			'sandbox_callback_checksum_token',
		]);

		$tokens = array_filter(array_map(static function ($token): string {
			return is_scalar($token) ? trim((string) $token) : '';
		}, $tokens));

		return array_values(array_unique($tokens));
	}

	private static function gateway_settings(): array {
		if (!function_exists('get_option')) {
			return [];
		}

		$settings = get_option(self::settings_option_key(), []);

		return is_array($settings) ? $settings : [];
	}

	private static function setting(array $settings, array $keys): string {
		foreach ($keys as $key) {
			if (isset($settings[$key]) && is_scalar($settings[$key])) {
				return trim((string) $settings[$key]);
			}
		}

		return '';
	}

	private static function first_scalar(array $params, array $keys): string {
		foreach ($keys as $key) {
			if (isset($params[$key]) && is_scalar($params[$key])) {
				return trim((string) $params[$key]);
			}
		}

		return '';
	}

	private static function safe_log_value(string $value): string {
		return $value === '' ? '' : substr($value, 0, 80);
	}

	private static function callback_namespace(): string {
		return (string) self::config_constant('CALLBACK_NAMESPACE', 'bci-woo/v1');
	}

	private static function callback_route(): string {
		return (string) self::config_constant('CALLBACK_ROUTE', '/callback');
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
