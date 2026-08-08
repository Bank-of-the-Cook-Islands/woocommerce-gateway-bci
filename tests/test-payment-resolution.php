<?php

/**
 * The shared payment-resolution entry point.
 *
 * Pins the one resolvability predicate in both directions, and pins that the
 * pollers and the manual admin check reach the resolver through it rather than
 * through a per-caller copy of the pipeline.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
    define('MINUTE_IN_SECONDS', 60);

    $bci_test_options = [];
    $bci_test_orders = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function wc_get_orders(array $args): array
    {
        global $bci_test_orders;
        $status = is_array($args['status'] ?? null) ? (string) ($args['status'][0] ?? '') : '';

        return $bci_test_orders[$status] ?? [];
    }

    function add_action(...$args): void {}
    function add_filter(...$args): void {}
    function apply_filters(string $hook, $value)
    {
        return $value;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function _n(string $single, string $plural, int $number, string $domain = ''): string
    {
        return $number === 1 ? $single : $plural;
    }

    class WC_Order
    {
        public array $meta = [];
        public string $payment_method = 'bci_takuecom';
        public string $status = 'pending';
        public bool $paid = false;
        private int $id;

        public function __construct(int $id = 1)
        {
            $this->id = $id;
        }

        public function get_id(): int
        {
            return $this->id;
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
            return $this->paid;
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

    /**
     * Stands in for the real resolver, which would reach the gateway.
     *
     * Payment_Resolution builds one per resolution, exactly as the reflection
     * factories it replaced did, so what it saw is recorded statically.
     */
    final class Status_Resolver
    {
        /** @var int[] Ids of the orders resolved, in order. */
        public static array $resolved = [];

        public function resolve(\WC_Order $order, string $context): string
        {
            self::$resolved[] = $order->get_id();

            return 'pending';
        }
    }

    require dirname(__DIR__) . '/includes/class-scheduler.php';
    require dirname(__DIR__) . '/includes/class-admin.php';

    function order(int $id, string $status, bool $paid = false, string $md_order = 'md-123'): \WC_Order
    {
        $order = new \WC_Order($id);
        $order->status = $status;
        $order->paid = $paid;
        $order->meta = $md_order === '' ? [] : ['_bci_woo_md_order' => $md_order];

        return $order;
    }

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

    // ---------------------------------------------------------------------
    // One predicate, pinned in both directions.
    // ---------------------------------------------------------------------

    // Nothing was ever registered at BPC, so there is nothing to ask about.
    assert_same(false, Payment_Resolution::is_resolvable(order(1, 'pending', false, '')), 'order without mdOrder is not resolvable');
    assert_same(false, Payment_Resolution::is_resolvable(order(1, 'pending', false, ''), true), 'a callback cannot resolve an order without mdOrder either');

    // Anything that is not an order at all.
    assert_same(false, Payment_Resolution::is_resolvable(null), 'null is not resolvable');
    assert_same(false, Payment_Resolution::is_resolvable('WC-1'), 'a string is not resolvable');

    // Open orders: every caller resolves these.
    foreach (['pending', 'failed', 'on-hold', 'cancelled'] as $status) {
        assert_same(true, Payment_Resolution::is_resolvable(order(1, $status)), "a poll resolves an order in {$status}");
        assert_same(true, Payment_Resolution::is_resolvable(order(1, $status), true), "a callback resolves an order in {$status}");
    }

    // A settled order: only the gateway announcing a change reopens it. This is
    // the guard that used to be inverted between the browser return and the
    // callback, and it now answers once for both.
    foreach (['processing', 'completed'] as $status) {
        assert_same(false, Payment_Resolution::is_resolvable(order(1, $status, true)), "a poll leaves a paid order in {$status} alone");
        assert_same(true, Payment_Resolution::is_resolvable(order(1, $status, true), true), "a callback still resolves a paid order in {$status}");
    }

    // Statuses the plugin has no business touching, paid or not.
    foreach (['checkout-draft', 'refunded', 'pending-cancel'] as $status) {
        assert_same(false, Payment_Resolution::is_resolvable(order(1, $status)), "a poll ignores an order in {$status}");
        assert_same(false, Payment_Resolution::is_resolvable(order(1, $status), true), "a callback ignores an order in {$status}");
    }

    // ---------------------------------------------------------------------
    // The scheduled sweep resolves through the entry point, guard included.
    // ---------------------------------------------------------------------

    global $bci_test_orders;
    $bci_test_orders = [
        'pending' => [
            order(11, 'pending'),
            order(12, 'pending', false, ''),
            order(13, 'checkout-draft'),
        ],
        'failed' => [
            order(14, 'failed'),
            order(15, 'processing', true),
        ],
    ];

    Status_Resolver::$resolved = [];
    assert_same(2, Scheduler::check_pending_orders(), 'the sweep counts only the orders the predicate accepts');
    assert_same([11, 14], Status_Resolver::$resolved, 'the sweep resolves exactly the open, registered orders');

    // ---------------------------------------------------------------------
    // The manual admin check is that same sweep, called rather than discovered.
    // ---------------------------------------------------------------------

    Status_Resolver::$resolved = [];
    $result = (new Admin())->check_pending_orders();

    assert_same(true, $result['success'], 'the manual check succeeds');
    assert_same(2, $result['checked'], 'the manual check reports the scheduler count');
    assert_same('Checked 2 orders.', $result['message'], 'the manual check reports a plural count');
    assert_same([11, 14], Status_Resolver::$resolved, 'the manual check resolves through the same entry point');

    // A single order reads as singular, so the count really is the scheduler's.
    $bci_test_orders = ['pending' => [order(21, 'pending')], 'failed' => []];
    Status_Resolver::$resolved = [];
    $result = (new Admin())->check_pending_orders();

    assert_same(1, $result['checked'], 'the manual check reports a single order');
    assert_same('Checked 1 order.', $result['message'], 'the manual check reports a singular count');
    assert_same([21], Status_Resolver::$resolved, 'the manual check resolved the one open order');

    // Nothing to do is still a success, not a discovery failure.
    $bci_test_orders = [];
    $result = (new Admin())->check_pending_orders();

    assert_same(true, $result['success'], 'an empty sweep still succeeds');
    assert_same(0, $result['checked'], 'an empty sweep reports zero');

    echo "Payment resolution entry point tests passed.\n";
}
