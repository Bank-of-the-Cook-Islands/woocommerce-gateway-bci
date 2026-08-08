<?php
declare(strict_types=1);

namespace BCI\Woo;

if (!defined('ABSPATH')) {
	exit;
}

final class Callback {
	/**
	 * Lower-cased names of every parameter BPC is documented to send in
	 * callback notifications (docs/bpc/api-reference/17-callback-notifications.md).
	 * Used to ignore query variables injected by the store's own stack.
	 */
	private const BPC_CALLBACK_PARAMS = [
		'actioncode', 'actioncodedescription', 'amount', 'amountformatted',
		'approvalcode', 'approvedamount', 'approvedamountformatted', 'authcode',
		'authvalue', 'avscode', 'bankname', 'bindingid', 'callbackcreationdate',
		'cardholdername', 'cavv', 'checksum', 'clientid', 'creditbankname',
		'creditpancountrycode', 'currency', 'currencyname',
		'currentrefundamountformatted', 'currentreverseamountformatted', 'date',
		'debitbankname', 'debitpancountrycode', 'declinedate', 'depositedamount',
		'depositedamountformatted', 'depositeddate', 'depositedtotalamountformatted',
		'depositflag', 'eci', 'email', 'enabled', 'externalrefundid', 'feeamount',
		'finishcheckurl', 'ip', 'ipcountrycode', 'isinternationalp2p', 'maskedpan',
		'mdorder', 'merchantfullname', 'merchantlogin', 'operation',
		'operationrefundedamount', 'operationrefundedamountformatted',
		'operationtype', 'orderdescription', 'orderid', 'ordernumber',
		'p2pdebitrrn', 'pancountrycode', 'panmasked', 'paymentamount',
		'paymentdate', 'paymentrefnum', 'paymentstate', 'paymentsystem',
		'paymentway', 'phone', 'processingid', 'recipientdata', 'refnum',
		'refundedamount', 'refundedamountformatted', 'refundeddate',
		'reverseddate', 'sessionexpireddate', 'sign_alias', 'status',
		'terminalid', 'threedstype', 'tokenizecryptogram',
		'transactionattributes', 'transactiontypeindicator', 'xid',
	];

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
		register_rest_route(Config::CALLBACK_NAMESPACE, Config::CALLBACK_ROUTE, [
			'methods'             => ['GET', 'POST'],
			'callback'            => [__CLASS__, 'handle'],
			'permission_callback' => '__return_true',
		]);
	}

	/**
	 * The URL BPC posts notifications to, for display in merchant settings.
	 *
	 * The route is registered above from the same two constants, so what the
	 * settings screens tell a merchant to configure cannot drift from what this
	 * plugin actually answers on.
	 */
	public static function get_callback_url(): string {
		return rest_url(trim(Config::CALLBACK_NAMESPACE, '/') . Config::CALLBACK_ROUTE);
	}

	/**
	 * Handle an incoming BPC callback notification.
	 */
	public static function handle(\WP_REST_Request $request): \WP_REST_Response {
		$params       = self::extract_params($request);
		$order_number = self::first_scalar($params, ['orderNumber']);
		$md_order     = self::first_scalar($params, ['mdOrder', 'mdorder', 'orderId']);

		if ($order_number === '' && $md_order === '') {
			Log::notice('BCI callback rejected: missing orderNumber and mdOrder.');
			return new \WP_REST_Response(null, 400);
		}

		$tokens = self::callback_tokens();
		if (empty($tokens)) {
			Log::notice('BCI callback rejected: no live or sandbox callback token is configured.');
			return new \WP_REST_Response(null, 403);
		}

		if (!self::verify_against_tokens($params, $tokens)) {
			Log::notice('BCI callback rejected: checksum verification failed.', [
				'order_number' => self::safe_log_value($order_number),
				'md_order'     => self::safe_log_value($md_order),
			]);
			return new \WP_REST_Response(null, 400);
		}

		$order = self::find_order($order_number, $md_order);
		if (!$order) {
			Log::notice('BCI callback order was not found.', [
				'order_number' => self::safe_log_value($order_number),
				'md_order'     => self::safe_log_value($md_order),
			]);
			return new \WP_REST_Response(null, 200);
		}

		$state = Order_State::for($order);

		if ($md_order !== '') {
			$stored_md_order = $state->md_order();

			if ($stored_md_order !== '' && !hash_equals($stored_md_order, $md_order)) {
				Log::notice('BCI callback rejected: mdOrder does not match order meta.', [
					'order_id' => $order->get_id(),
				]);
				return new \WP_REST_Response(null, 400);
			}

			if ($stored_md_order === '') {
				$state->record_md_order($md_order)->save();
			}
		}

		if (!$state->is_resolvable()) {
			Log::notice('BCI callback cannot resolve status because the order has no mdOrder.', [
				'order_id' => $order->get_id(),
			]);
			return new \WP_REST_Response(null, 200);
		}

		// A callback is BPC announcing a change, so paid orders are answered too:
		// that is how a refund or reversal taken in the merchant portal lands.
		if (!Payment_Resolution::is_resolvable($order, true)) {
			Log::info('BCI callback ignored for order in terminal status.', [
				'order_id' => $order->get_id(),
				'status'   => $order->get_status(),
			]);
			return new \WP_REST_Response(null, 200);
		}

		try {
			if (function_exists('do_action')) {
				do_action('bci_woo_callback_received', $order, $params);
			}
			Payment_Resolution::resolve($order, Payment_Resolution::CALLBACK);
		} catch (\Throwable $exception) {
			Log::error('BCI callback status resolution failed.', [
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

		// The checksum covers exactly the parameters BPC sent, so anything the
		// store's own stack appends to the callback request (security plugin,
		// WAF token, tracking variable) breaks the digest. Retry against the
		// BPC-documented parameters only.
		$known = self::known_bpc_params($params);
		if (count($known) === count($params)) {
			return false;
		}

		foreach ($tokens as $token) {
			if (self::verify_checksum($known, $token)) {
				Log::info('BCI callback checksum verified after ignoring non-BPC parameters.', [
					'ignored' => implode(', ', array_map('strval', array_keys(array_diff_key($params, $known)))),
				]);
				return true;
			}
		}

		return false;
	}

	private static function known_bpc_params(array $params): array {
		foreach (array_keys($params) as $key) {
			if (!in_array(strtolower((string) $key), self::BPC_CALLBACK_PARAMS, true)) {
				unset($params[$key]);
			}
		}

		return $params;
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
			Log::error('BCI callback cannot look up orders because WooCommerce is unavailable.');
			return null;
		}

		if ($order_number !== '') {
			$order = Order_State::find_by_order_number($order_number);
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
			return Order_State::find_by_md_order($md_order);
		}

		return null;
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
		$state = Order_State::for($order);

		return $order->get_payment_method() === Config::GATEWAY_ID
			|| $state->order_number() !== ''
			|| $state->is_resolvable();
	}

	/**
	 * The signing tokens a callback may have been signed with.
	 *
	 * Both environments are accepted because a callback carries no environment:
	 * a store that has switched to live can still be told about a sandbox order.
	 */
	private static function callback_tokens(): array {
		$tokens = array_filter(array_map(static function ($token): string {
			return is_scalar($token) ? trim((string) $token) : '';
		}, Api::callback_tokens()));

		return array_values(array_unique($tokens));
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
}
