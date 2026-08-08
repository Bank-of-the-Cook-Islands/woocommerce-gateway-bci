<?php

/**
 * A subscription renewal resolves through the same status table as any other
 * payment.
 *
 * Renewals used to carry its own copy of the table, which read an authorisation
 * cancellation as a plain failure, had no row for a refund, and never fired
 * bci_woo_payment_status_resolved. The copy is gone, so a renewal now reaches
 * whatever Status_Resolver says the gateway state means.
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

    function get_bloginfo(string $key = 'name'): string
    {
        return 'Test Store';
    }

    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }

    class WC_Order
    {
        public array $persisted = [];
        public array $staged = [];
        public string $status = 'pending';
        public bool $paid = false;
        public array $notes = [];
        public string $transaction_id = '';

        public function __construct(array $persisted = [])
        {
            $this->persisted = $persisted;
        }

        public function get_id(): int
        {
            return 42;
        }

        public function get_order_number(): string
        {
            return '42';
        }

        public function get_customer_id(): int
        {
            return 3;
        }

        public function get_currency(): string
        {
            return 'NZD';
        }

        public function get_billing_email(): string
        {
            return 'subscriber@example.test';
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
        /** @var array The status getOrderStatusExtended answers with. */
        public array $status = [];

        /** @var array The recurrentPayment.do response. */
        public array $renewal_result = [];

        /** @var array[] Every recurrentPayment.do request made. */
        public array $renewal_requests = [];

        /** @var array[] Every getOrderStatusExtended request made. */
        public array $status_requests = [];

        public function recurrent_payment(array $params, string $environment)
        {
            $this->renewal_requests[] = [$params, $environment];

            return $this->renewal_result;
        }

        public function get_order_status(string $md_order, string $environment)
        {
            $this->status_requests[] = [$md_order, $environment];

            return $this->status;
        }

        public static function current_environment(): string
        {
            return 'live';
        }

        public static function get_setting(string $key, $default = null)
        {
            return $default;
        }

        public static function payment_currency_to_numeric(?string $currency, ?string $environment = null): string
        {
            return '554';
        }
    }

    /** Stands in for the real Tokens; the renewal path only reads stored credentials. */
    final class Tokens
    {
        public array $captured = [];

        public function __construct(string $gateway_id = '') {}

        public function token_data_from_subscription($subscription): array
        {
            return [];
        }

        public function token_data_from_order($order): array
        {
            return [
                'binding_id' => 'binding-sub-1',
                'client_id' => 'wc_customer_3',
            ];
        }

        public function capture_from_status($order, array $status, string $client_id = ''): bool
        {
            $this->captured[] = $status;

            return true;
        }

        public function store_order_token_data($order, array $token_data): bool
        {
            return true;
        }
    }

    final class Subscriptions
    {
        /** @var object|null The subscription every renewal order here belongs to. */
        public $subscription;

        public function subscription_for_renewal_order($order)
        {
            return $this->subscription;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-status-resolver.php';
    require dirname(__DIR__) . '/includes/class-renewals.php';

    function assert_same($expected, $actual, string $case): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s, got %s',
                $case,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    /**
     * Charges a renewal whose gateway status reads back as $status.
     *
     * recurrentPayment.do answers with the pre-capture state the order was in at
     * charge time — a bare registration — which is deliberately never the state
     * $status settles on. Only a renewal that re-reads the order through
     * getOrderStatusExtended can reach $status, so interpreting the embedded
     * response instead is visible here rather than green.
     *
     * @return array{0: \WC_Order, 1: string[], 2: Api}
     */
    function renew(array $status, bool $paid = false): array
    {
        $GLOBALS['fired_actions'] = [];

        $order = new \WC_Order();
        $order->paid = $paid;
        $order->status = $paid ? 'processing' : 'pending';

        $api = new Api();
        $api->status = $status;
        $api->renewal_result = [
            'errorCode' => '0',
            'orderId' => 'md-renewal-1',
            'orderStatus' => [
                'errorCode' => '0',
                'orderStatus' => Config::STATUS_REGISTERED,
            ],
        ];

        // The subscription was taken out in sandbox, and remembers it. The renewal
        // order WooCommerce Subscriptions just created carries no BCI state at all.
        $subscriptions = new Subscriptions();
        $subscriptions->subscription = new \WC_Order(['_bci_woo_environment' => 'sandbox']);

        $renewals = new Renewals(null, $api, new Status_Resolver($api), $subscriptions, new Tokens());
        $renewals->process_subscription_payment('12.50', $order);

        $resolved = [];
        foreach ($GLOBALS['fired_actions'] as [$hook, $args]) {
            if ($hook === 'bci_woo_payment_status_resolved') {
                $resolved[] = (string) $args[1];
            }
        }

        return [$order, $resolved, $api];
    }

    // A refund taken in the merchant portal used to fall through the renewal copy
    // of the table as "pending", leaving the order untouched.
    [$order, $resolved] = renew(['errorCode' => '0', 'orderStatus' => Config::STATUS_REFUNDED], true);
    assert_same('refunded', $order->status, 'refunded renewal: order status');
    assert_same(['refunded'], $resolved, 'refunded renewal: resolved hook');

    // An authorisation cancellation is a cancellation, not a bare failure.
    [$order, $resolved] = renew(['errorCode' => '0', 'orderStatus' => Config::STATUS_AUTH_CANCELLED], true);
    assert_same('cancelled', $order->status, 'auth cancelled renewal: order status');
    assert_same(['cancelled'], $resolved, 'auth cancelled renewal: resolved hook');

    // A successful renewal: paid, and the hook that drives binding capture fires.
    [$order, $resolved, $api] = renew([
        'errorCode' => '0',
        'orderStatus' => Config::STATUS_CAPTURED,
        'actionCode' => 0,
        'authRefNum' => 'auth-renewal',
    ]);
    assert_same('processing', $order->status, 'captured renewal: order status');
    assert_same('auth-renewal', $order->transaction_id, 'captured renewal: transaction id');
    assert_same(['completed'], $resolved, 'captured renewal: resolved hook');
    assert_same('md-renewal-1', (string) ($order->persisted['_bci_woo_md_order'] ?? ''), 'captured renewal: gateway reference');

    // The renewal charge itself still goes out against the stored credential and
    // the environment the subscription was created in.
    [$params, $environment] = $api->renewal_requests[0];
    assert_same('sandbox', $environment, 'renewal request: environment');
    assert_same('binding-sub-1', $params['bindingId'], 'renewal request: binding');
    assert_same(1250, $params['amount'], 'renewal request: minor units');

    // The order that gets read back is this renewal's order, in the environment
    // the subscription was created in. Falling back to the plugin's current
    // environment would ask live for a sandbox order and be told it does not
    // exist, which is the renewal environment mismatch epic #20 describes.
    assert_same(1, count($api->status_requests), 'status request: read back exactly once');
    [$status_md_order, $status_environment] = $api->status_requests[0];
    assert_same('md-renewal-1', $status_md_order, 'status request: gateway reference');
    assert_same('sandbox', $status_environment, 'status request: environment');

    // A declined renewal reaches the same failed state the one-off path uses.
    [$order, $resolved] = renew([
        'errorCode' => '0',
        'orderStatus' => Config::STATUS_DECLINED,
        'actionCode' => 116,
        'actionCodeDescription' => 'Insufficient funds',
    ]);
    assert_same('failed', $order->status, 'declined renewal: order status');
    assert_same(['failed'], $resolved, 'declined renewal: resolved hook');

    // Gateway state is recorded once, as the integer the shared table read.
    assert_same(
        Config::STATUS_DECLINED,
        $order->persisted['_bci_woo_last_status'] ?? null,
        'declined renewal: last status is an int'
    );
    assert_same(116, $order->persisted['_bci_woo_last_action_code'] ?? null, 'declined renewal: last action code is an int');

    echo "Renewal status resolution tests passed.\n";
}
