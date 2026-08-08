<?php

/**
 * The two resolution call sites that are not sweeps.
 *
 * tests/test-payment-resolution.php pins the predicate itself and the two
 * pollers that walk a query. This file pins the other two callers, which locate
 * a single order and can only be reached by driving their surface: the browser
 * coming back from the hosted form, and a BPC callback arriving on the REST
 * endpoint. What each one asks for is the whole point of the shared predicate
 * having two modes, so each is exercised where it calls it.
 *
 * A browser return is a poll: an order a callback already settled must be left
 * alone rather than re-read and re-noted on every refresh of the return URL. A
 * callback is a push: a paid order is answered too, which is how a refund or
 * reversal taken in the BPC merchant portal reaches WooCommerce at all.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    /** @var array<int, WC_Order> Orders the store can find, by id. */
    $bci_test_orders = [];

    /** @var array<int, string> Every URL redirected to, in order. */
    $bci_test_redirects = [];

    /** @var array<int, array{0: string, 1: string}> Every notice raised, as [message, type]. */
    $bci_test_notices = [];

    /** Raised in place of the exit() that follows every wp_safe_redirect(). */
    final class BCI_Test_Redirect extends \RuntimeException
    {
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function absint($value): int
    {
        return abs((int) $value);
    }

    function wp_unslash($value)
    {
        return $value;
    }

    function wc_clean($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    function get_option(string $key, $default = false)
    {
        return $default;
    }

    function do_action(...$args): void
    {
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function wc_get_order(int $order_id)
    {
        global $bci_test_orders;

        return $bci_test_orders[$order_id] ?? false;
    }

    function wc_get_orders(array $args): array
    {
        global $bci_test_orders;

        $clause = $args['meta_query'][0] ?? null;
        if (!is_array($clause)) {
            return [];
        }

        $found = [];
        foreach ($bci_test_orders as $order) {
            if ((string) $order->get_meta((string) $clause['key']) === (string) $clause['value']) {
                $found[] = $order;
            }
        }

        return $found;
    }

    function wc_add_notice(string $message, string $type = 'success'): void
    {
        global $bci_test_notices;
        $bci_test_notices[] = [$message, $type];
    }

    function wp_safe_redirect(string $url): void
    {
        global $bci_test_redirects;
        $bci_test_redirects[] = $url;

        throw new BCI_Test_Redirect($url);
    }

    function wc_get_checkout_url(): string
    {
        return 'https://shop.test/checkout/';
    }

    final class BCI_Test_Cart
    {
        public int $emptied = 0;

        public function empty_cart(): void
        {
            $this->emptied++;
        }
    }

    final class BCI_Test_WC
    {
        public BCI_Test_Cart $cart;

        public function __construct()
        {
            $this->cart = new BCI_Test_Cart();
        }
    }

    function WC(): BCI_Test_WC
    {
        static $wc = null;

        if ($wc === null) {
            $wc = new BCI_Test_WC();
        }

        return $wc;
    }

    class WC_Payment_Gateway
    {
        public function get_return_url($order = null): string
        {
            return 'https://shop.test/order-received/' . ($order ? $order->get_id() : 0);
        }
    }

    class WC_Order
    {
        public array $meta = [];
        public string $payment_method = 'bci_takuecom';
        public string $status = 'pending';
        public bool $paid = false;
        public int $saves = 0;
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_order_key(): string
        {
            return 'wc_order_key_' . $this->id;
        }

        public function get_payment_method(): string
        {
            return $this->payment_method;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save(): void
        {
            $this->saves++;
        }

        public function get_status(): string
        {
            return $this->status;
        }

        public function has_status($statuses): bool
        {
            return in_array($this->status, (array) $statuses, true);
        }

        public function is_paid(): bool
        {
            return $this->paid;
        }

        public function get_checkout_payment_url(): string
        {
            return 'https://shop.test/pay/' . $this->id;
        }
    }

    class WP_REST_Request
    {
        public function __construct(private array $params)
        {
        }

        public function get_query_params(): array
        {
            return $this->params;
        }

        public function get_body_params(): array
        {
            return [];
        }

        public function get_body(): string
        {
            return '';
        }

        public function get_method(): string
        {
            return 'GET';
        }
    }

    class WP_REST_Response
    {
        public function __construct(private $data, private int $status)
        {
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static function __callStatic(string $level, array $args): void {}

        public static function error(string $message, array $context = []): void {}

        public static function info(string $message, array $context = []): void {}

        public static function notice(string $message, array $context = []): void {}
    }

    final class Api
    {
        public static function callback_tokens(): array
        {
            return [CALLBACK_TOKEN];
        }
    }

    /**
     * Stands in for the real resolver, which would reach the gateway.
     *
     * Payment_Resolution builds one per resolution, so what it was asked is
     * recorded statically, as [order id, context] pairs.
     */
    final class Status_Resolver
    {
        /** @var array<int, array{0: int, 1: string}> */
        public static array $calls = [];

        /** @var callable|null Applied to the order when a resolution runs. */
        public static $effect = null;

        public function resolve(\WC_Order $order, string $context): string
        {
            self::$calls[] = [$order->get_id(), $context];

            if (self::$effect !== null) {
                (self::$effect)($order);
            }

            return 'pending';
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-resolution.php';
    require dirname(__DIR__) . '/includes/class-registration-result.php';
    require dirname(__DIR__) . '/includes/class-registration.php';
    require dirname(__DIR__) . '/includes/class-payment-resolution.php';
    require dirname(__DIR__) . '/includes/class-gateway.php';
    require dirname(__DIR__) . '/includes/class-callback.php';

    const CALLBACK_TOKEN = 'test-callback-token';

    function assert_same($expected, $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s, got %s',
                $label,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    function register_order(int $id, string $status, bool $paid = false, string $md_order = 'md-123'): \WC_Order
    {
        global $bci_test_orders;

        $order = new \WC_Order($id);
        $order->status = $status;
        $order->paid = $paid;
        $order->meta = $md_order === ''
            ? []
            : ['_bci_woo_md_order' => $md_order, '_bci_woo_order_number' => 'WC' . $id . '-1752541000'];

        $bci_test_orders[$id] = $order;

        return $order;
    }

    /**
     * Drives Gateway::handle_return() for an order and reports where it sent the
     * browser. The redirect stands in for the exit() that follows it.
     */
    function browser_return(int $order_id, string $key): string
    {
        global $bci_test_redirects, $bci_test_notices;
        $bci_test_redirects = [];
        $bci_test_notices = [];

        $_GET = ['order_id' => (string) $order_id, 'key' => $key];
        $gateway = (new \ReflectionClass(Gateway::class))->newInstanceWithoutConstructor();

        try {
            $gateway->handle_return();
        } catch (\BCI_Test_Redirect $redirect) {
            return $redirect->getMessage();
        }

        throw new \RuntimeException('handle_return() did not redirect');
    }

    /**
     * Computes a checksum the way BPC does: sort by name, join key;value pairs
     * with semicolons, trailing semicolon, HMAC-SHA256, uppercase hex.
     */
    function signed_callback(array $params): \WP_REST_Request
    {
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . ';' . $value;
        }

        $params['checksum'] = strtoupper(hash_hmac('sha256', implode(';', $parts) . ';', CALLBACK_TOKEN));

        return new \WP_REST_Request($params);
    }

    function callback(\WC_Order $order): \WP_REST_Response {
        return Callback::handle(signed_callback([
            'mdOrder'     => (string) $order->get_meta('_bci_woo_md_order'),
            'orderNumber' => (string) $order->get_meta('_bci_woo_order_number'),
            'operation'   => 'deposited',
            'status'      => '1',
        ]));
    }

    // ---------------------------------------------------------------------
    // The browser return is a poll.
    // ---------------------------------------------------------------------

    // An order still open is resolved, under the browser-return context, and the
    // customer is sent to the received page once the gateway answers Deposited.
    $order = register_order(41, 'pending');
    Status_Resolver::$calls = [];
    Status_Resolver::$effect = static function (\WC_Order $order): void {
        $order->status = 'processing';
        $order->paid = true;
    };

    assert_same(
        'https://shop.test/order-received/41',
        browser_return(41, $order->get_order_key()),
        'a paid return lands on the order received page'
    );
    assert_same([[41, Payment_Resolution::BROWSER_RETURN]], Status_Resolver::$calls, 'the browser return resolves as a browser return');
    assert_same(1, \WC()->cart->emptied, 'a successful return empties the cart');

    Status_Resolver::$effect = null;

    // An order a callback already settled is left alone. Re-reading it here is
    // what put a duplicate note on the order every time the customer refreshed.
    register_order(42, 'completed', true);
    Status_Resolver::$calls = [];

    assert_same(
        'https://shop.test/order-received/42',
        browser_return(42, 'wc_order_key_42'),
        'an already-completed order still lands on the received page'
    );
    assert_same([], Status_Resolver::$calls, 'the browser return does not re-resolve a settled order');

    // The return URL for an order that never reached BPC resolves nothing, and
    // the customer is sent back to pay.
    register_order(43, 'pending', false, '');
    Status_Resolver::$calls = [];

    assert_same(
        'https://shop.test/pay/43',
        browser_return(43, 'wc_order_key_43'),
        'an unpaid order goes back to the payment page'
    );
    assert_same([], Status_Resolver::$calls, 'an order with no gateway reference is not resolved');

    global $bci_test_notices;
    assert_same(1, count($bci_test_notices), 'the unpaid return raises one notice');
    assert_same('error', $bci_test_notices[0][1], 'the unpaid return notice is an error');

    // A mismatched order key never reaches the predicate at all.
    Status_Resolver::$calls = [];
    assert_same('https://shop.test/checkout/', browser_return(41, 'wrong-key'), 'a bad order key returns to the checkout');
    assert_same([], Status_Resolver::$calls, 'a bad order key resolves nothing');

    // ---------------------------------------------------------------------
    // The gateway callback is a push.
    // ---------------------------------------------------------------------

    // BPC announcing a change to a paid order is answered, which is the only way
    // a refund or reversal taken in the merchant portal reaches WooCommerce.
    $paid = register_order(51, 'completed', true);
    Status_Resolver::$calls = [];

    assert_same(200, callback($paid)->get_status(), 'a callback for a paid order is accepted');
    assert_same([[51, Payment_Resolution::CALLBACK]], Status_Resolver::$calls, 'the callback resolves a paid order as a gateway callback');

    // An open order is resolved by the callback too.
    $open = register_order(52, 'pending');
    Status_Resolver::$calls = [];

    assert_same(200, callback($open)->get_status(), 'a callback for an open order is accepted');
    assert_same([[52, Payment_Resolution::CALLBACK]], Status_Resolver::$calls, 'the callback resolves an open order');

    // A status the plugin has no business touching is acknowledged and ignored,
    // so the guard is a guard and not a formality.
    $refunded = register_order(53, 'refunded');
    Status_Resolver::$calls = [];

    assert_same(200, callback($refunded)->get_status(), 'a callback for a terminal order is acknowledged');
    assert_same([], Status_Resolver::$calls, 'the callback does not resolve a terminal order');

    echo "Resolution call site tests passed.\n";
}
