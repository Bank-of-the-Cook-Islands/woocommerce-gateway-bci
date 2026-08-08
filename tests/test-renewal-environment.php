<?php

/**
 * A subscription is renewed wherever it was created, not wherever the plugin
 * happens to be pointed today.
 *
 * Renewals used to walk a five step ladder to answer this, ending in a hard
 * coded 'live', and one of its steps called a method the gateway does not have.
 * The environment is now a property of the order state: the subscription
 * remembers where its binding was created and the renewal order inherits it, so
 * switching the store out of test mode cannot send a sandbox binding to the
 * live host — the mismatch epic #20 describes.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_current_environment = 'live';

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

    function do_action(string $hook, ...$args): void {}

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
            return 88;
        }

        public function get_order_number(): string
        {
            return '88';
        }

        public function get_customer_id(): int
        {
            return 5;
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

    /** The subscription the renewal belongs to, with the credential it stored. */
    class WC_Subscription_Stub
    {
        public array $meta = [];

        public function __construct(array $meta = [])
        {
            $this->meta = $meta;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function get_id(): int
        {
            return 900;
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
        /** @var array Every recurrentPayment.do request, as [params, environment]. */
        public array $renewal_requests = [];

        /** @var array Every getOrderStatusExtended request, as [mdOrder, environment]. */
        public array $status_requests = [];

        public function recurrent_payment(array $params, string $environment)
        {
            $this->renewal_requests[] = [$params, $environment];

            return [
                'errorCode' => '0',
                'orderId' => 'md-renewal-88',
                'orderStatus' => ['errorCode' => '0', 'orderStatus' => Config::STATUS_REGISTERED],
            ];
        }

        public function get_order_status(string $md_order, string $environment)
        {
            $this->status_requests[] = [$md_order, $environment];

            return [
                'errorCode' => '0',
                'orderStatus' => Config::STATUS_CAPTURED,
                'actionCode' => 0,
                'authRefNum' => 'auth-renewal-88',
            ];
        }

        /** How the plugin is configured right now, which is not how it was configured then. */
        public static function current_environment(): string
        {
            global $bci_test_current_environment;

            return $bci_test_current_environment;
        }

        public static function subscriptions_enabled(): bool
        {
            return true;
        }

        public static function get_setting(string $key, $default = null)
        {
            return $default;
        }

        /** Which currency that environment charges in is test-renewal-currency.php. */
        public static function payment_currency_to_numeric(?string $currency, ?string $environment = null): string
        {
            return '554';
        }
    }

    /** Stands in for the WooCommerce Subscriptions lookup. */
    final class Subscriptions
    {
        /** @var object|null */
        public $subscription;

        public function __construct($gateway = null, $tokens = null) {}

        public function subscription_for_renewal_order($order)
        {
            return $this->subscription;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-resolution.php';
    require dirname(__DIR__) . '/includes/class-registration-result.php';
    require dirname(__DIR__) . '/includes/class-registration.php';
    require dirname(__DIR__) . '/includes/class-tokens.php';
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
     * Charges a renewal for $subscription while the plugin is set to $current.
     *
     * @return array{0: \WC_Order, 1: Api}
     */
    function renew($subscription, string $current): array
    {
        global $bci_test_current_environment;
        $bci_test_current_environment = $current;

        // WooCommerce Subscriptions creates the renewal order fresh, so it carries
        // no BCI state of its own until this charge records some.
        $renewal_order = new \WC_Order();

        $api           = new Api();
        $subscriptions = new Subscriptions();
        $subscriptions->subscription = $subscription;

        $renewals = new Renewals(null, $api, new Status_Resolver($api), $subscriptions, new Tokens(Config::GATEWAY_ID));
        $renewals->process_subscription_payment('19.95', $renewal_order);

        return [$renewal_order, $api];
    }

    // The subscription was taken out while the store was in test mode, and the
    // binding it holds only exists on the sandbox host.
    $sandbox_subscription = new \WC_Subscription_Stub([
        '_bci_woo_environment' => 'sandbox',
        '_bci_woo_binding_id' => 'binding-sandbox-1',
        '_bci_woo_client_id' => 'wc_customer_5',
    ]);

    // Test mode has since been switched off. The renewal must still go to sandbox.
    [$renewal_order, $api] = renew($sandbox_subscription, 'live');

    assert_same(1, count($api->renewal_requests), 'the renewal is charged once');
    [$params, $charge_environment] = $api->renewal_requests[0];
    assert_same('sandbox', $charge_environment, 'the charge goes to the environment the binding was created on');
    assert_same('binding-sandbox-1', $params['bindingId'], 'the charge uses the stored credential');

    assert_same(1, count($api->status_requests), 'the renewal is read back once');
    [$md_order, $status_environment] = $api->status_requests[0];
    assert_same('md-renewal-88', $md_order, 'the renewal is read back by its own gateway reference');
    assert_same('sandbox', $status_environment, 'the read back goes to the same environment as the charge');

    // Having been charged there, the renewal order records it, so every later
    // status check on this order resolves without consulting the subscription.
    assert_same('sandbox', (string) ($renewal_order->persisted['_bci_woo_environment'] ?? ''), 'the renewal order records its environment');
    assert_same('md-renewal-88', (string) ($renewal_order->persisted['_bci_woo_md_order'] ?? ''), 'the renewal order records its gateway reference');
    assert_same('processing', $renewal_order->status, 'the renewal completes through the shared status table');

    // The reverse case: a subscription created live keeps renewing live while the
    // store is in test mode. The old ladder read the test_mode setting directly.
    $live_subscription = new \WC_Subscription_Stub([
        '_bci_woo_environment' => 'live',
        '_bci_woo_binding_id' => 'binding-live-1',
    ]);

    [, $api] = renew($live_subscription, 'sandbox');
    assert_same('live', $api->renewal_requests[0][1], 'a live subscription is not dragged into sandbox by test mode');

    // With nothing recorded anywhere there is nothing to inherit, so the plugin's
    // current environment answers — rather than the ladder's hard coded 'live'.
    [, $api] = renew(new \WC_Subscription_Stub(['_bci_woo_binding_id' => 'binding-unknown-1']), 'sandbox');
    assert_same('sandbox', $api->renewal_requests[0][1], 'an unrecorded environment falls back to the current setting');

    echo "Renewal environment tests passed.\n";
}
