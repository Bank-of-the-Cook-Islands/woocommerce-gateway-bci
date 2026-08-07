<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
    define('MINUTE_IN_SECONDS', 60);

    $bci_test_resolver = null;
    $bci_test_options = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function apply_filters(string $hook, $value)
    {
        global $bci_test_resolver;
        if ($hook === 'bci_woo_status_resolver' && $bci_test_resolver !== null) {
            return $bci_test_resolver;
        }
        return $value;
    }

    function add_action(...$args): void {}
    function add_filter(...$args): void {}

    class WC_DateTime_Stub
    {
        private int $timestamp;

        public function __construct(int $timestamp)
        {
            $this->timestamp = $timestamp;
        }

        public function getTimestamp(): int
        {
            return $this->timestamp;
        }
    }

    class WC_Order
    {
        public array $meta = [];
        public string $payment_method = 'bci_takuecom';
        public ?WC_DateTime_Stub $date_created = null;
        public string $status = 'pending';
        public bool $paid = false;

        public function get_id(): int
        {
            return 42;
        }

        public function get_payment_method(): string
        {
            return $this->payment_method;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function get_date_created(): ?WC_DateTime_Stub
        {
            return $this->date_created;
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

    class Fake_Resolver
    {
        public string $resolution = 'pending';
        public $last_status = null;
        public bool $throw = false;

        public function resolve(WC_Order $order, string $context): string
        {
            if ($this->throw) {
                throw new \RuntimeException('gateway unreachable');
            }

            if ($this->last_status !== null) {
                $order->meta['_bci_woo_last_status'] = $this->last_status;
            }

            return $this->resolution;
        }
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static array $entries = [];

        public static function __callStatic(string $level, array $args): void
        {
            self::$entries[] = [$level, $args[0] ?? ''];
        }

        public static function error(string $message, array $context = []): void
        {
            self::$entries[] = ['error', $message];
        }

        public static function info(string $message, array $context = []): void
        {
            self::$entries[] = ['info', $message];
        }

        public static function notice(string $message, array $context = []): void
        {
            self::$entries[] = ['notice', $message];
        }
    }

    require dirname(__DIR__) . '/includes/class-scheduler.php';
    require dirname(__DIR__) . '/includes/class-callback.php';

    function make_order(array $overrides = []): \WC_Order
    {
        $order = new \WC_Order();
        $order->meta = ['_bci_woo_md_order' => 'md-123'];
        $order->date_created = new \WC_DateTime_Stub(time() - 90 * 60);

        foreach ($overrides as $property => $value) {
            $order->{$property} = $value;
        }

        return $order;
    }

    function assert_hold($expected, $cancel, \WC_Order $order, string $label): void
    {
        $actual = Scheduler::maybe_hold_unpaid_cancellation($cancel, $order);
        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s, got %s',
                $label,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    global $bci_test_resolver;
    $resolver = new \Fake_Resolver();
    $bci_test_resolver = $resolver;

    // Orders that are not BCI-managed are untouched.
    assert_hold(false, false, make_order(), 'cancel=false passes through');
    assert_hold(true, true, make_order(['payment_method' => 'stripe']), 'non-BCI order is untouched');
    assert_hold(true, true, make_order(['meta' => []]), 'order without mdOrder is untouched');

    // The resolver settled the order into a final state: never cancel over the top.
    foreach (['completed', 'refunded', 'cancelled', 'failed'] as $resolution) {
        $resolver->resolution = $resolution;
        assert_hold(false, true, make_order(), "resolution {$resolution} blocks cancellation");
    }

    // Still merely REGISTERED at the gateway: the customer abandoned the form, cancel normally.
    $resolver->resolution = 'pending';
    $resolver->last_status = 0;
    assert_hold(true, true, make_order(), 'abandoned REGISTERED order cancels normally');

    // A payment attempt is in flight (e.g. ACS/3-D Secure): hold the cancellation.
    $resolver->last_status = 5;
    assert_hold(false, true, make_order(), 'in-flight payment holds cancellation');

    // But not indefinitely: past the hold limit the cancellation proceeds.
    $old = make_order();
    $old->date_created = new \WC_DateTime_Stub(time() - 2 * 24 * 60 * 60);
    assert_hold(true, true, $old, 'in-flight hold expires after the limit');

    // Gateway unreachable: hold within the limit, cancel past it.
    $resolver->throw = true;
    assert_hold(false, true, make_order(), 'gateway error holds cancellation');
    assert_hold(true, true, $old, 'gateway error past the limit cancels');
    $resolver->throw = false;

    // Callback::should_resolve() treats cancelled orders as resolvable.
    $should_resolve = new \ReflectionMethod(Callback::class, 'should_resolve');
    $should_resolve->setAccessible(true);

    $cancelled = make_order(['status' => 'cancelled']);
    if ($should_resolve->invoke(null, $cancelled) !== true) {
        throw new \RuntimeException('cancelled order should be resolvable by the callback');
    }

    $completed = make_order(['status' => 'completed', 'paid' => true]);
    if ($should_resolve->invoke(null, $completed) !== true) {
        throw new \RuntimeException('paid order should stay resolvable by the callback');
    }

    $draft = make_order(['status' => 'checkout-draft']);
    if ($should_resolve->invoke(null, $draft) !== false) {
        throw new \RuntimeException('draft order should not be resolvable by the callback');
    }

    echo "Cancelled order recovery tests passed.\n";
}
