<?php

/**
 * Resolution is serialised per order.
 *
 * The browser return and the callback for one payment arrive together, so the
 * second to take the order's lock must see what the first wrote and not resolve
 * the order again. The lock must always be released, and a database without
 * named locks must still resolve.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    function get_option(string $key, $default = false)
    {
        return $default;
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    /** Stands in for $wpdb: answers GET_LOCK as told and records every query. */
    class Test_Wpdb
    {
        public string $prefix = 'wp_';
        public array $queries = [];
        /** @var string|null What GET_LOCK answers: '1' granted, '0' timed out, null unsupported. */
        public $get_lock = '1';

        public function prepare(string $sql, ...$args): string
        {
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $sql), $args);
        }

        public function get_var(string $sql)
        {
            $this->queries[] = $sql;
            return $this->get_lock;
        }

        public function query(string $sql): int
        {
            $this->queries[] = $sql;
            return 1;
        }
    }

    /** What the other request left in the database, read back by refresh(). */
    class Test_Order_Store
    {
        public ?string $persisted_status = null;
        public int $reads = 0;

        public function read(WC_Order $order): void
        {
            $this->reads++;
            if ($this->persisted_status !== null) {
                $order->status = $this->persisted_status;
            }
        }
    }

    class WC_Order
    {
        public array $meta = ['_bci_woo_md_order' => 'md-1'];
        public string $payment_method = 'bci_takuecom';
        public string $status = 'pending';
        public Test_Order_Store $store;

        public function __construct()
        {
            $this->store = new Test_Order_Store();
        }

        public function get_id(): int
        {
            return 9;
        }

        public function get_payment_method(): string
        {
            return $this->payment_method;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
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
            return in_array($this->status, ['processing', 'completed'], true);
        }

        public function get_data_store(): Test_Order_Store
        {
            return $this->store;
        }

        public function read_meta_data(bool $force = false): void {}
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static array $entries = [];

        public static function __callStatic(string $level, array $args): void
        {
            self::$entries[] = $args[0] ?? '';
        }

        public static function info(string $message, array $context = []): void
        {
            self::$entries[] = $message;
        }
    }

    /** Stands in for the real resolver, which would reach the gateway. */
    final class Status_Resolver
    {
        public static array $calls = [];
        public static bool $throw = false;

        public function resolve(\WC_Order $order, string $context): string
        {
            self::$calls[] = [$order->status, $context];
            if (self::$throw) {
                throw new \RuntimeException('gateway unreachable');
            }

            return Resolution::COMPLETED;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-resolution.php';
    require dirname(__DIR__) . '/includes/class-payment-resolution.php';

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

    function reset_world(): \Test_Wpdb
    {
        global $wpdb;
        $wpdb = new \Test_Wpdb();
        Status_Resolver::$calls = [];
        Status_Resolver::$throw = false;

        return $wpdb;
    }

    function released(\Test_Wpdb $db): bool
    {
        return in_array("SELECT RELEASE_LOCK('wp_bci_woo_resolve_9')", $db->queries, true);
    }

    // Lock granted, order still pending: resolved once, lock taken and released.
    $db = reset_world();
    $order = new \WC_Order();
    $outcome = Payment_Resolution::resolve($order, Payment_Resolution::BROWSER_RETURN);
    assert_same(Resolution::COMPLETED, $outcome, 'open order is resolved');
    assert_same([['pending', Payment_Resolution::BROWSER_RETURN]], Status_Resolver::$calls, 'resolver asked once');
    assert_same("SELECT GET_LOCK('wp_bci_woo_resolve_9', " . (Config::API_TIMEOUT + 5) . ')', $db->queries[0], 'per-order lock outlasts a gateway call');
    assert_same(1, $order->store->reads, 'order re-read once the lock is held');
    assert_same(true, released($db), 'lock released');

    // The request this one waited on paid the order: the browser return reads
    // that back into the caller's object and does not resolve again.
    $db = reset_world();
    $order = new \WC_Order();
    $order->store->persisted_status = 'processing';
    $outcome = Payment_Resolution::resolve($order, Payment_Resolution::BROWSER_RETURN);
    assert_same([], Status_Resolver::$calls, 'a settled order is not resolved again');
    assert_same(Resolution::COMPLETED, $outcome, 'settled paid order reports completed');
    assert_same('processing', $order->status, "caller's object carries the settled state");
    assert_same(true, released($db), 'lock released after skipping');

    // The same race won by the browser return: the callback still answers a
    // paid order, because BPC may be announcing a refund.
    reset_world();
    $order = new \WC_Order();
    $order->store->persisted_status = 'processing';
    Payment_Resolution::resolve($order, Payment_Resolution::CALLBACK);
    assert_same([['processing', Payment_Resolution::CALLBACK]], Status_Resolver::$calls, 'callback resolves a paid order against its fresh state');

    // Settled into a refund or a cancellation by the other request: never
    // reported as pending, which the unpaid-order cancellation filter would
    // read as permission to cancel.
    reset_world();
    $order = new \WC_Order();
    $order->store->persisted_status = 'refunded';
    assert_same(Resolution::REFUNDED, Payment_Resolution::resolve($order, Payment_Resolution::CANCELLATION_CHECK), 'settled refund reports refunded');

    // The resolver throwing still releases the lock.
    $db = reset_world();
    Status_Resolver::$throw = true;
    try {
        Payment_Resolution::resolve(new \WC_Order(), Payment_Resolution::CALLBACK);
        throw new \LogicException('resolver exception should propagate');
    } catch (\RuntimeException $expected) {
    }
    assert_same(true, released($db), 'lock released when the resolver throws');

    // Lock not granted in time: nothing resolved, nothing released, and the
    // caller is told so it can answer accordingly (the callback returns 500 and
    // BPC retries).
    $db = reset_world();
    $db->get_lock = '0';
    $order = new \WC_Order();
    try {
        Payment_Resolution::resolve($order, Payment_Resolution::CALLBACK);
        throw new \LogicException('a busy lock should throw');
    } catch (\RuntimeException $expected) {
    }
    assert_same([], Status_Resolver::$calls, 'busy lock resolves nothing');
    assert_same(false, released($db), 'a lock not held is not released');
    assert_same(0, $order->store->reads, 'order not re-read without the lock');

    // No named locks available: resolves as before, without refresh or release.
    $db = reset_world();
    $db->get_lock = null;
    $order = new \WC_Order();
    Payment_Resolution::resolve($order, Payment_Resolution::SCHEDULED_CHECK);
    assert_same(1, count(Status_Resolver::$calls), 'resolves without named-lock support');
    assert_same(false, released($db), 'nothing to release without a lock');

    // No $wpdb at all.
    unset($GLOBALS['wpdb']);
    Status_Resolver::$calls = [];
    Payment_Resolution::resolve(new \WC_Order(), Payment_Resolution::SCHEDULED_CHECK);
    assert_same(1, count(Status_Resolver::$calls), 'resolves without a database handle');

    echo "Resolution lock tests passed.\n";
}
