<?php

/**
 * The enable_subscriptions setting is answered once, when hooks are registered.
 *
 * Everything the subscription feature does hangs off hooks that only exist
 * because the setting was on, so those callbacks never ask again — and the
 * clientId/FORCE_CREATE_BINDING pair is built in exactly one place, Tokens.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_options = [];
    $bci_test_hooks = [];
    $bci_test_subscription = null;

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

    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        global $bci_test_hooks;
        $bci_test_hooks[] = ['filter', $hook, $callback];
    }

    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        global $bci_test_hooks;
        $bci_test_hooks[] = ['action', $hook, $callback];
    }

    /** Stands in for the WooCommerce Subscriptions lookups. */
    function wcs_order_contains_subscription($order, string $type = 'any'): bool
    {
        return isset($order->is_subscription_order) && $order->is_subscription_order;
    }

    function wcs_get_subscriptions_for_order($order, array $args = []): array
    {
        global $bci_test_subscription;

        if (!isset($order->is_subscription_order) || !$order->is_subscription_order) {
            return [];
        }

        return $bci_test_subscription ? [$bci_test_subscription] : [];
    }

    class WC_Order
    {
        public array $meta = [];
        public int $customer_id = 0;
        public int $saves = 0;
        public array $notes = [];
        public bool $is_subscription_order = false;
        private int $id;

        public function __construct(int $id = 4242, int $customer_id = 0)
        {
            $this->id = $id;
            $this->customer_id = $customer_id;
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_customer_id(): int
        {
            return $this->customer_id;
        }

        public function get_billing_email(): string
        {
            return 'shopper@example.test';
        }

        public function get_items(): array
        {
            return [];
        }

        public function get_meta(string $key, bool $single = true)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }

        public function save(): int
        {
            $this->saves++;
            return $this->id;
        }
    }
}

namespace BCI\Woo {
    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-tokens.php';
    require dirname(__DIR__) . '/includes/class-subscriptions.php';

    function set_subscriptions_enabled(bool $enabled): void
    {
        global $bci_test_options;
        $bci_test_options[Config::OPTION_KEY] = ['enable_subscriptions' => $enabled ? 'yes' : 'no'];
    }

    function registered_hooks(): array
    {
        global $bci_test_hooks;
        return $bci_test_hooks;
    }

    function reset_hooks(): void
    {
        global $bci_test_hooks;
        $bci_test_hooks = [];
    }

    /** @return callable The callback registered for a hook. */
    function hook_callback(string $hook): callable
    {
        foreach (registered_hooks() as [$type, $name, $callback]) {
            if ($name === $hook) {
                return $callback;
            }
        }

        throw new \RuntimeException(sprintf('No callback is registered for %s', $hook));
    }

    function subscription_order(int $id = 4242, int $customer_id = 7): \WC_Order
    {
        $order = new \WC_Order($id, $customer_id);
        $order->is_subscription_order = true;

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

    // A closed gate registers nothing at all, which is what makes a re-check inside
    // any of these callbacks dead weight rather than a safety net.
    set_subscriptions_enabled(false);
    reset_hooks();
    (new Subscriptions())->register_hooks();
    assert_same([], registered_hooks(), 'no hooks are registered while subscriptions are disabled');

    // An open gate registers the three subscription hooks, and only those.
    set_subscriptions_enabled(true);
    reset_hooks();
    (new Subscriptions())->register_hooks();

    $hook_names = array_map(static fn (array $hook): string => $hook[1], registered_hooks());
    assert_same(
        [
            'bci_woo_register_payment_params',
            'bci_woo_payment_status_resolved',
            'bci_woo_callback_received',
        ],
        $hook_names,
        'the subscription hooks are registered once each'
    );

    // Initial subscription checkout: the register.do request gains the stored
    // credential fields, and the clientId is recorded where a renewal can find it.
    global $bci_test_subscription;
    $bci_test_subscription = new \WC_Order(99, 7);
    $order = subscription_order();

    $params = (hook_callback('bci_woo_register_payment_params'))(['amount' => 1000], $order);

    assert_same('wc_customer_7', $params['clientId'] ?? '', 'the request carries a clientId');
    assert_same('FORCE_CREATE_BINDING', $params['features'] ?? '', 'the request asks BPC to create a binding');
    assert_same(1000, $params['amount'] ?? 0, 'the rest of the request is untouched');
    assert_same('wc_customer_7', $order->get_meta('_bci_woo_client_id'), 'the order records the clientId it registered');
    if ($order->saves < 1) {
        throw new \RuntimeException('the recorded clientId was staged but never saved');
    }
    assert_same('wc_customer_7', $bci_test_subscription->get_meta('_bci_woo_client_id'), 'the subscription records the clientId too');

    // A one-off checkout is left exactly as it was: no clientId, no binding request.
    $one_off = new \WC_Order(4343, 7);
    $one_off_params = (hook_callback('bci_woo_register_payment_params'))(['amount' => 1000], $one_off);
    assert_same(['amount' => 1000], $one_off_params, 'a one-off order is not asked to store a credential');

    // The features value goes through the same merge every other caller uses, so a
    // merchant filter's own feature survives and FORCE_CREATE_BINDING is added to
    // it. Overwriting either way is a live failure: drop the merchant's feature,
    // or drop the binding request and no renewal can ever be charged.
    $with_features = (hook_callback('bci_woo_register_payment_params'))(
        ['amount' => 1000, 'features' => 'FORCE_TDS'],
        subscription_order(4747)
    );
    assert_same(
        ['FORCE_TDS', 'FORCE_CREATE_BINDING'],
        $with_features['features'] ?? null,
        'a merchant feature is kept and the binding request added to it'
    );

    $already_binding = (hook_callback('bci_woo_register_payment_params'))(
        ['features' => 'FORCE_CREATE_BINDING'],
        subscription_order(4848)
    );
    assert_same(
        'FORCE_CREATE_BINDING',
        $already_binding['features'] ?? null,
        'an existing binding request is not duplicated'
    );

    // The callback answers to the registration that created it, not to the setting
    // as it stands now — the gate has one answer per request.
    set_subscriptions_enabled(false);
    $late = subscription_order(4444);
    $late_params = (hook_callback('bci_woo_register_payment_params'))([], $late);
    assert_same('wc_customer_7', $late_params['clientId'] ?? '', 'the registered hook does not re-read the setting');
    set_subscriptions_enabled(true);

    // Capture runs through the hook the open gate registered, not by hand: the
    // binding the gateway returns is filed against the clientId the order
    // registered with even when the status payload carries none, and the
    // environment the order recorded rides along to the subscription that will be
    // renewed against it.
    $capture = hook_callback('bci_woo_payment_status_resolved');

    $bci_test_subscription = new \WC_Order(99, 7);
    $captured = subscription_order(4545);
    $captured->update_meta_data('_bci_woo_client_id', 'wc_customer_7');
    $captured->update_meta_data('_bci_woo_environment', 'sandbox');

    $capture($captured, 'completed', ['orderStatus' => ['bindingId' => 'binding-abc-123']]);

    assert_same('binding-abc-123', $captured->get_meta('_bci_woo_binding_id'), 'the binding is stored on the order');
    assert_same('wc_customer_7', $captured->get_meta('_bci_woo_client_id'), 'the recorded clientId is kept');
    assert_same('sandbox', $captured->get_meta('_bci_woo_environment'), 'the environment the order recorded is kept');
    assert_same('binding-abc-123', $bci_test_subscription->get_meta('_bci_woo_binding_id'), 'the subscription is given the binding to renew against');
    assert_same('sandbox', $bci_test_subscription->get_meta('_bci_woo_environment'), 'the subscription is given the environment the order recorded');

    // A completed subscription payment with no binding is a merchant permission
    // problem, so the customer-facing note is added once and only once.
    $unbound = subscription_order(4646);
    $capture($unbound, 'completed', ['orderStatus' => ['orderStatus' => 2]]);
    assert_same(1, count($unbound->notes), 'the missing binding is noted on the order');
    assert_same('yes', $unbound->get_meta('_bci_woo_binding_missing_note_added'), 'the note is recorded as added');

    $capture($unbound, 'completed', ['orderStatus' => ['orderStatus' => 2]]);
    assert_same(1, count($unbound->notes), 'the missing binding is not noted twice');

    // The same hook fires for every payment once the gate is open, so an ordinary
    // one-off order reaches it too and must not be told a card could not be stored
    // for renewals it does not have.
    $one_off_completed = new \WC_Order(4949, 7);
    $capture($one_off_completed, 'completed', ['orderStatus' => ['orderStatus' => 2]]);
    assert_same([], $one_off_completed->notes, 'a one-off order is not warned about subscription renewals');
    assert_same('', $one_off_completed->get_meta('_bci_woo_binding_missing_note_added'), 'a one-off order records nothing');

    echo "Subscription gate registration tests passed.\n";
}
