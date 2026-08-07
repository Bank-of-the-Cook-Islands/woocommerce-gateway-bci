<?php

/**
 * The BPC status table, read without a WooCommerce order.
 *
 * Status_Resolver::classify() is pure, so every row of the table is asserted
 * directly here — including the rows the renewal path used to answer
 * differently from its own copy (auth cancelled, refunded) and the resolved
 * hook it never fired.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $GLOBALS['fired_actions'] = [];

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function __($text, $domain = '')
    {
        return $text;
    }

    function is_wp_error($thing): bool
    {
        return false;
    }

    function do_action(string $hook, ...$args): void
    {
        $GLOBALS['fired_actions'][] = [$hook, $args];
    }

    class WC_Order
    {
        public array $persisted = [];
        public array $staged = [];
        public string $status = 'pending';
        public bool $paid = false;
        public array $notes = [];
        public string $transaction_id = '';

        public function get_id(): int
        {
            return 21;
        }

        public function get_customer_id(): int
        {
            return 0;
        }

        public function get_meta(string $key, bool $single = true)
        {
            if (array_key_exists($key, $this->staged)) {
                return $this->staged[$key];
            }

            return $this->persisted[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->staged[$key] = $value;
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }

        public function is_paid(): bool
        {
            return $this->paid;
        }

        public function has_status($status): bool
        {
            return in_array($this->status, (array) $status, true);
        }

        public function payment_complete(string $transaction_id = ''): void
        {
            $this->paid = true;
            $this->status = 'processing';
            $this->transaction_id = $transaction_id;
        }

        public function set_transaction_id(string $transaction_id): void
        {
            $this->transaction_id = $transaction_id;
        }

        public function get_status(): string
        {
            return $this->status;
        }

        public function update_status(string $status, string $note = ''): void
        {
            $this->status = $status;
            $this->notes[] = $note;
        }

        public function save(): int
        {
            $this->persisted = array_merge($this->persisted, $this->staged);
            $this->staged = [];

            return $this->get_id();
        }
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static function __callStatic(string $level, array $args): void {}
    }

    final class Api
    {
        public static function current_environment(): string
        {
            return 'sandbox';
        }

        public static function get_setting(string $key, $default = null)
        {
            return $default;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-status-resolver.php';

    function fail(string $message): void
    {
        throw new \RuntimeException($message);
    }

    function assert_same($expected, $actual, string $case): void
    {
        if ($expected !== $actual) {
            fail(sprintf(
                '%s: expected %s, got %s',
                $case,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    // Table: [case, status payload, outcome, order status, action code, reason].
    $rows = [
        ['captured', ['errorCode' => '0', 'orderStatus' => 2, 'actionCode' => 0], Resolution::COMPLETED, 2, 0, ''],
        ['captured as strings', ['errorCode' => '0', 'orderStatus' => '2', 'actionCode' => '0'], Resolution::COMPLETED, 2, 0, ''],

        // The example from issue #14: a registered order carrying a decline.
        ['registered with a decline', ['errorCode' => '0', 'orderStatus' => 0, 'actionCode' => 5], Resolution::FAILED, 0, 5, ''],
        ['registered, no attempt', ['errorCode' => '0', 'orderStatus' => 0, 'actionCode' => 0], Resolution::PENDING, 0, 0, ''],
        ['registered, no action code', ['errorCode' => '0', 'orderStatus' => 0], Resolution::PENDING, 0, 0, ''],
        ['registered, BPC placeholder code', ['errorCode' => '0', 'orderStatus' => 0, 'actionCode' => -30001], Resolution::PENDING, 0, -30001, ''],

        ['authorised', ['errorCode' => '0', 'orderStatus' => 1], Resolution::PENDING, 1, 0, ''],
        ['acs initiated', ['errorCode' => '0', 'orderStatus' => 5], Resolution::PENDING, 5, 0, ''],
        ['pending', ['errorCode' => '0', 'orderStatus' => 7], Resolution::PENDING, 7, 0, ''],
        ['partial completion', ['errorCode' => '0', 'orderStatus' => 8], Resolution::PENDING, 8, 0, ''],

        // Renewals used to read status 3 as failed and never reached the paid-state
        // distinction the one-off path makes.
        ['auth cancelled', ['errorCode' => '0', 'orderStatus' => 3], Resolution::CANCELLED, 3, 0, ''],

        // Renewals had no row for status 4 at all, so a refund fell through to pending.
        ['refunded', ['errorCode' => '0', 'orderStatus' => 4], Resolution::REFUNDED, 4, 0, ''],

        ['declined', ['errorCode' => '0', 'orderStatus' => 6, 'actionCode' => 116], Resolution::FAILED, 6, 116, ''],

        ['unknown status', ['errorCode' => '0', 'orderStatus' => 99], Resolution::ERROR, 99, 0, Resolution::REASON_UNEXPECTED_STATUS],
        ['no order status', ['errorCode' => '0'], Resolution::ERROR, Resolution::NO_ORDER_STATUS, 0, Resolution::REASON_MISSING_STATUS],
        ['gateway error code', ['errorCode' => '5', 'errorMessage' => 'Access denied'], Resolution::ERROR, Resolution::NO_ORDER_STATUS, 0, Resolution::REASON_GATEWAY_ERROR],
        ['no error code at all', ['orderStatus' => 2], Resolution::ERROR, Resolution::NO_ORDER_STATUS, 0, Resolution::REASON_GATEWAY_ERROR],
        ['error code outranks a status', ['errorCode' => '5', 'orderStatus' => 6], Resolution::ERROR, Resolution::NO_ORDER_STATUS, 0, Resolution::REASON_GATEWAY_ERROR],
    ];

    foreach ($rows as [$case, $status, $outcome, $order_status, $action_code, $reason]) {
        $resolution = Status_Resolver::classify($status);

        assert_same($outcome, $resolution->outcome, $case . ': outcome');
        assert_same($order_status, $resolution->order_status, $case . ': order status');
        assert_same($action_code, $resolution->action_code, $case . ': action code');
        assert_same($reason, $resolution->reason, $case . ': reason');
        assert_same($status, $resolution->status, $case . ': payload is carried through');
    }

    /**
     * Applies a classification to a fresh order and returns the order plus the
     * resolutions the bci_woo_payment_status_resolved hook was fired with.
     *
     * @return array{0: \WC_Order, 1: string[]}
     */
    function apply_status(array $status, bool $paid = false): array
    {
        $GLOBALS['fired_actions'] = [];

        $order = new \WC_Order();
        $order->paid = $paid;
        $order->status = $paid ? 'processing' : 'pending';

        $resolver = (new \ReflectionClass(Status_Resolver::class))->newInstanceWithoutConstructor();
        $resolver->apply($order, Status_Resolver::classify($status), 'subscription renewal');

        $resolved = [];
        foreach ($GLOBALS['fired_actions'] as [$hook, $args]) {
            if ($hook === 'bci_woo_payment_status_resolved') {
                $resolved[] = (string) $args[1];
            }
        }

        return [$order, $resolved];
    }

    // Auth cancelled: cancelled for an order that was paid, failed for one that
    // never was — and the hook fires either way, which the renewal copy never did.
    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 3], false);
    assert_same('failed', $order->status, 'auth cancelled, unpaid: order status');
    assert_same(['cancelled'], $resolved, 'auth cancelled, unpaid: resolved hook');

    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 3], true);
    assert_same('cancelled', $order->status, 'auth cancelled, paid: order status');
    assert_same(['cancelled'], $resolved, 'auth cancelled, paid: resolved hook');

    // Refunds reach the order instead of being dropped.
    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 4], true);
    assert_same('refunded', $order->status, 'refunded: order status');
    assert_same(['refunded'], $resolved, 'refunded: resolved hook');

    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 0, 'actionCode' => 5]);
    assert_same('failed', $order->status, 'registered with a decline: order status');
    assert_same(['failed'], $resolved, 'registered with a decline: resolved hook');

    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 2, 'authRefNum' => 'auth-9']);
    assert_same('processing', $order->status, 'captured: order status');
    assert_same('auth-9', $order->transaction_id, 'captured: transaction id');
    assert_same(['completed'], $resolved, 'captured: resolved hook');

    // Pending is the one outcome that changes nothing about the order, so the
    // only thing it leaves behind is the gateway state — and it has to be saved,
    // because Scheduler reads _bci_woo_last_status straight back to decide
    // whether the order was abandoned. An attempt still in flight (3-D Secure,
    // statuses 1/5/7/8) must not be failed on the way past.
    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 7]);
    assert_same(['pending'], $resolved, 'pending: resolved hook');
    assert_same(7, $order->persisted['_bci_woo_last_status'] ?? null, 'pending: last status persisted');
    assert_same(0, $order->persisted['_bci_woo_last_action_code'] ?? null, 'pending: last action code persisted');
    assert_same('pending', $order->status, 'pending: order status untouched');

    [$order, $resolved] = apply_status(['errorCode' => '0', 'orderStatus' => 99]);
    assert_same(['error'], $resolved, 'unknown status: resolved hook');

    // The gateway state recorded on the order is the integer the table read, not
    // a string: the two write the same meta keys and must agree on their type.
    [$order] = apply_status(['errorCode' => '0', 'orderStatus' => 6, 'actionCode' => 116]);
    assert_same(6, $order->persisted['_bci_woo_last_status'] ?? null, 'declined: last status is an int');
    assert_same(116, $order->persisted['_bci_woo_last_action_code'] ?? null, 'declined: last action code is an int');

    // A payload fault is not a gateway state: nothing is recorded and nothing is
    // told that the payment resolved.
    foreach ([['errorCode' => '5', 'errorMessage' => 'Access denied'], ['errorCode' => '0']] as $faulty) {
        [$order, $resolved] = apply_status($faulty);

        assert_same([], $resolved, 'payload fault: no resolved hook');
        assert_same([], $order->persisted, 'payload fault: nothing persisted');
        assert_same([], $order->staged, 'payload fault: nothing staged');
        assert_same('pending', $order->status, 'payload fault: order status untouched');

        if ($order->notes === []) {
            fail('payload fault: the order was not told why the status check failed.');
        }
    }

    echo "Status classification tests passed.\n";
}
