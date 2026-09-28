<?php

/**
 * Repeat gateway answers leave a settled order alone.
 *
 * BPC sends a callback per enabled event and retries them, and the customer's
 * browser return usually lands alongside. Each one resolves the order again, so
 * a second identical answer must not add a note, complete the payment twice or
 * move the order's status. A genuinely new answer — a retry declined on the
 * same order, a refund — still must.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_options = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    class WC_Order
    {
        public array $meta = [];
        public string $status = 'pending';
        public array $notes = [];
        public int $payment_completions = 0;
        public array $transitions = [];
        public string $transaction_id = '';

        public function get_id(): int
        {
            return 7;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function delete_meta_data(string $key): void
        {
            unset($this->meta[$key]);
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

        public function payment_complete(string $transaction_id = ''): void
        {
            $this->payment_completions++;
            $this->transaction_id = $transaction_id;
            $this->set('processing');
        }

        public function set_transaction_id(string $transaction_id): void
        {
            $this->transaction_id = $transaction_id;
        }

        public function update_status(string $status, string $note = ''): void
        {
            if ($note !== '') {
                $this->notes[] = $note;
            }
            $this->set($status);
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }

        public function save(): void {}

        private function set(string $status): void
        {
            if ($status !== $this->status) {
                $this->transitions[] = $this->status . '>' . $status;
                $this->status = $status;
            }
        }
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static function __callStatic(string $level, array $args): void {}

        public static function info(string $message, array $context = []): void {}

        public static function notice(string $message, array $context = []): void {}
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-resolution.php';
    require dirname(__DIR__) . '/includes/class-status-resolver.php';

    function registered_order(string $status = 'pending'): \WC_Order
    {
        $order = new \WC_Order();
        $order->status = $status;
        Order_State::for($order)->record_registration('md-1', 'WC7-1', 'sandbox');

        return $order;
    }

    function answer(int $order_status, int $action_code = 0, array $extra = []): Resolution
    {
        $payload = array_merge(['errorCode' => '0', 'orderStatus' => $order_status, 'actionCode' => $action_code], $extra);

        return Status_Resolver::classify($payload);
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

    function resolve_times(\WC_Order $order, Resolution $resolution, int $times, string $context = 'gateway callback'): void
    {
        $resolver = new Status_Resolver(new Api());
        for ($i = 0; $i < $times; $i++) {
            $resolver->apply($order, $resolution, $context);
        }
    }

    // Paid, then told it is paid three more times: one completion, one note.
    $order = registered_order();
    resolve_times($order, answer(Config::STATUS_CAPTURED, 0, ['authRefNum' => 'RRN-1']), 4);
    assert_same(1, $order->payment_completions, 'repeat deposits complete the payment once');
    assert_same(1, count($order->notes), 'repeat deposits write one note');
    assert_same(['pending>processing'], $order->transitions, 'repeat deposits transition once');
    assert_same('RRN-1', $order->transaction_id, 'transaction reference recorded');

    // "Force Processing" is applied on the transition only. A merchant who has
    // since completed the order must not see a repeat callback reopen it.
    $bci_test_options[Config::OPTION_KEY] = ['paid_order_status' => 'processing'];
    $order = registered_order();
    resolve_times($order, answer(Config::STATUS_CAPTURED), 1);
    $order->status = 'completed';
    resolve_times($order, answer(Config::STATUS_CAPTURED), 2);
    assert_same('completed', $order->status, 'repeat deposit leaves a merchant-completed order alone');
    assert_same(1, count($order->notes), 'repeat deposit after completion writes no note');
    $bci_test_options = [];

    // A cancelled order that is then paid is recovered, with its note.
    $order = registered_order('cancelled');
    resolve_times($order, answer(Config::STATUS_CAPTURED), 2);
    assert_same('processing', $order->status, 'late payment recovers a cancelled order');
    assert_same(1, $order->payment_completions, 'recovered order completes once');
    assert_same(1, count($order->notes), 'recovered order gets one note');

    // Declined, and told so again by the browser return and the callback.
    $order = registered_order();
    resolve_times($order, answer(Config::STATUS_REGISTERED, 71015), 3);
    assert_same('failed', $order->status, 'decline fails the order');
    assert_same(1, count($order->notes), 'repeat declines write one note');
    assert_same(['pending>failed'], $order->transitions, 'repeat declines transition once');

    // A different decline on the same failed order is news.
    resolve_times($order, answer(Config::STATUS_DECLINED, -2025), 1);
    assert_same(2, count($order->notes), 'a new decline code is noted');

    // A retry from order-pay registers a new BPC order. The same decline code
    // on that attempt is a new failure, not an echo of the last one.
    Order_State::for($order)->record_registration('md-2', 'WC7-2', 'sandbox');
    resolve_times($order, answer(Config::STATUS_DECLINED, -2025), 2);
    assert_same(3, count($order->notes), 'same decline on a new registration is noted once');

    // Refunded in the portal, and the refund callback repeated.
    $order = registered_order();
    resolve_times($order, answer(Config::STATUS_CAPTURED), 1);
    resolve_times($order, answer(Config::STATUS_REFUNDED), 3);
    assert_same('refunded', $order->status, 'refund lands');
    assert_same(2, count($order->notes), 'payment and refund noted once each');

    // Reversed, and the reversal repeated: a cancelled order is no longer
    // "paid", so without the guard the second one would fail it.
    $order = registered_order();
    resolve_times($order, answer(Config::STATUS_CAPTURED), 1);
    resolve_times($order, answer(Config::STATUS_AUTH_CANCELLED), 3);
    assert_same('cancelled', $order->status, 'repeat reversal leaves the order cancelled');
    assert_same(['pending>processing', 'processing>cancelled'], $order->transitions, 'reversal transitions once');

    echo "Repeat resolution tests passed.\n";
}
