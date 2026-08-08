<?php

/**
 * A renewal is charged in the currency of the environment it is sent to.
 *
 * The environment half of this was fixed first: a renewal goes to the host its
 * binding was created on. The currency followed the global test_mode setting for
 * a while longer, so a sandbox binding renewing after test mode was switched off
 * reached dev.bpcbt.com priced in NZD, which the sandbox merchant cannot
 * collect — and a live subscription renewing while someone had test mode on sent
 * the sandbox currency to the live host. Currency and host now come from the one
 * environment, whatever the setting says today.
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

        public function get_id(): int
        {
            return 91;
        }

        public function get_order_number(): string
        {
            return '91';
        }

        public function get_customer_id(): int
        {
            return 7;
        }

        /** WooCommerce prices the renewal order in the store's own currency. */
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
            return 910;
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

        /** @var array Every environment payment_currency_to_numeric() was asked about. */
        public static array $currency_questions = [];

        public function recurrent_payment(array $params, string $environment)
        {
            $this->renewal_requests[] = [$params, $environment];

            return [
                'errorCode' => '0',
                'orderId' => 'md-renewal-91',
                'orderStatus' => ['errorCode' => '0', 'orderStatus' => Config::STATUS_REGISTERED],
            ];
        }

        public function get_order_status(string $md_order, string $environment)
        {
            return [
                'errorCode' => '0',
                'orderStatus' => Config::STATUS_CAPTURED,
                'actionCode' => 0,
                'authRefNum' => 'auth-renewal-91',
            ];
        }

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

        /**
         * Mirrors the real method: the sandbox merchant collects in EUR, the live
         * merchant in NZD, and the environment argument wins over the setting.
         */
        public static function payment_currency_to_numeric(?string $currency, ?string $environment = null): string
        {
            $environment = (string) $environment !== '' ? (string) $environment : self::current_environment();
            self::$currency_questions[] = $environment;

            return $environment === 'sandbox' ? '978' : '554';
        }
    }

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

    /**
     * A gateway that offers a currency helper of its own, which is what Renewals
     * falls back to when Api cannot answer. Any such helper knows only the
     * environment the store is configured for right now, which is exactly what a
     * renewal must not be priced by, so Api's environment-addressed answer has to
     * win over it.
     */
    final class Gateway_Stub
    {
        public string $id = 'bci_takuecom';

        public function currency_to_numeric(?string $currency): string
        {
            return Api::current_environment() === 'sandbox' ? '978' : '554';
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
     * @return array{0: array, 1: string} The charge params and the environment sent to.
     */
    function renew($subscription, string $current): array
    {
        global $bci_test_current_environment;
        $bci_test_current_environment = $current;
        Api::$currency_questions = [];

        $api           = new Api();
        $subscriptions = new Subscriptions();
        $subscriptions->subscription = $subscription;

        $renewals = new Renewals(
            new Gateway_Stub(),
            $api,
            new Status_Resolver($api),
            $subscriptions,
            new Tokens(Config::GATEWAY_ID)
        );
        $renewals->process_subscription_payment('19.95', new \WC_Order());

        assert_same(1, count($api->renewal_requests), 'the renewal is charged once');

        return $api->renewal_requests[0];
    }

    // Taken out while the store was in test mode; test mode has since been
    // switched off. The charge goes to sandbox, so it is priced for sandbox.
    $sandbox_subscription = new \WC_Subscription_Stub([
        '_bci_woo_environment' => 'sandbox',
        '_bci_woo_binding_id' => 'binding-sandbox-1',
    ]);

    [$params, $environment] = renew($sandbox_subscription, 'live');
    assert_same('sandbox', $environment, 'the charge goes to the environment the binding was created on');
    assert_same('978', $params['currency'], 'a sandbox renewal is priced in the sandbox currency with test mode off');
    assert_same(['sandbox'], Api::$currency_questions, 'the currency is asked about the environment being charged, not the setting');

    // The reverse, and the worse one: a live subscription renewing while someone
    // has test mode on must not send the sandbox currency to the live host.
    $live_subscription = new \WC_Subscription_Stub([
        '_bci_woo_environment' => 'live',
        '_bci_woo_binding_id' => 'binding-live-1',
    ]);

    [$params, $environment] = renew($live_subscription, 'sandbox');
    assert_same('live', $environment, 'a live subscription is not dragged into sandbox by test mode');
    assert_same('554', $params['currency'], 'a live renewal is priced in the live currency with test mode on');
    assert_same(['live'], Api::$currency_questions, 'the currency is asked about the environment being charged, not the setting');

    // With nothing recorded there is nothing to inherit: the charge goes to the
    // current environment, and the currency goes with it.
    [$params, $environment] = renew(new \WC_Subscription_Stub(['_bci_woo_binding_id' => 'binding-unknown-1']), 'sandbox');
    assert_same('sandbox', $environment, 'an unrecorded environment falls back to the current setting');
    assert_same('978', $params['currency'], 'the currency follows that fallback rather than diverging from it');

    echo "Renewal currency tests passed.\n";
}
