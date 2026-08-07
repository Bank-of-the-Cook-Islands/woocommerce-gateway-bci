<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

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
        return $thing instanceof \WP_Error;
    }

    function do_action(...$args): void {}

    class WP_Error
    {
        public function get_error_message(): string
        {
            return 'error';
        }
    }

    /**
     * Mimics WC_Data: update_meta_data() only stages a value, get_meta() reads the
     * staged value back, and save() is what actually persists it.
     */
    class WC_Order
    {
        public array $persisted = [];
        public array $staged = [];
        public int $customer_id = 0;
        public string $status = 'pending';
        public bool $paid = false;
        public array $notes = [];
        public string $transaction_id = '';
        public int $saves = 0;

        public function __construct(array $persisted = [])
        {
            $this->persisted = $persisted;
        }

        public function get_id(): int
        {
            return 7;
        }

        public function get_customer_id(): int
        {
            return $this->customer_id;
        }

        public function get_billing_email(): string
        {
            return 'shopper@example.test';
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
            ++$this->saves;

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
        public array $status = [];

        public function get_order_status(string $md_order, string $environment)
        {
            return $this->status;
        }

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
    require dirname(__DIR__) . '/includes/class-tokens.php';
    require dirname(__DIR__) . '/includes/class-status-resolver.php';

    function resolve(array $status, array $persisted = [], int $customer_id = 0): \WC_Order
    {
        $order = new \WC_Order($persisted + ['_bci_woo_md_order' => 'md-123']);
        $order->customer_id = $customer_id;

        $api = new Api();
        $api->status = $status;

        $resolution = (new Status_Resolver($api))->resolve($order, 'test');
        if ($resolution !== 'completed') {
            throw new \RuntimeException('Expected a completed resolution, got ' . $resolution);
        }

        return $order;
    }

    function assert_persisted(\WC_Order $order, string $key, string $expected, string $case): void
    {
        $actual = (string) ($order->persisted[$key] ?? '');
        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s to be persisted as "%s", got "%s"',
                $case,
                $key,
                $expected,
                $actual
            ));
        }

        if ($order->staged !== []) {
            throw new \RuntimeException($case . ': meta was left staged but never saved.');
        }
    }

    // A guest checkout: no WooCommerce payment token can be created, so nothing else
    // triggers a save. The binding must still reach the database.
    $order = resolve([
        'errorCode' => '0',
        'orderStatus' => Config::STATUS_CAPTURED,
        'actionCode' => 0,
        'authRefNum' => 'auth-1',
        'bindingId' => 'binding-guest',
        'bindingInfo' => ['clientId' => 'wc_guest_abc'],
        'cardAuthInfo' => ['expiration' => '202812'],
    ]);

    assert_persisted($order, '_bci_woo_binding_id', 'binding-guest', 'guest order binding id');
    assert_persisted($order, '_bci_woo_client_id', 'wc_guest_abc', 'guest order client id');
    assert_persisted($order, '_bci_woo_card_expiry', '202812', 'guest order card expiry');
    assert_persisted($order, '_bci_woo_last_status', (string) Config::STATUS_CAPTURED, 'guest order last status');

    // The order never recorded an environment, and binding capture must not
    // invent one from the plugin's current setting: a guessed value would become
    // a permanent record and outlive the setting that produced it.
    if (($order->persisted['_bci_woo_environment'] ?? '') !== '') {
        throw new \RuntimeException('binding capture must not persist a guessed environment');
    }

    // Nested binding payload, client id falling back to the value stored at registration.
    $order = resolve(
        [
            'errorCode' => '0',
            'orderStatus' => Config::STATUS_CAPTURED,
            'actionCode' => 0,
            'bindingInfo' => ['bindingId' => 'binding-nested'],
        ],
        ['_bci_woo_client_id' => 'wc_customer_9', '_bci_woo_environment' => 'live'],
        9
    );

    assert_persisted($order, '_bci_woo_binding_id', 'binding-nested', 'nested binding id');
    assert_persisted($order, '_bci_woo_client_id', 'wc_customer_9', 'retained client id');

    // No binding in the response: the order still resolves and nothing is left staged.
    $order = resolve([
        'errorCode' => '0',
        'orderStatus' => Config::STATUS_CAPTURED,
        'actionCode' => 0,
    ]);

    if (($order->persisted['_bci_woo_binding_id'] ?? '') !== '') {
        throw new \RuntimeException('A binding id was stored for a response that carried none.');
    }

    if ($order->staged !== []) {
        throw new \RuntimeException('Captured order without a binding left meta staged.');
    }

    echo "Binding metadata save tests passed.\n";
}
