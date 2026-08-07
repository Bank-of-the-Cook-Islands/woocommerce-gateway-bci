<?php

/**
 * The gateway's two subscription registration sites answer the setting.
 *
 * Nothing behind them re-checks enable_subscriptions any more, so these two
 * conditions in the constructor are the whole gate: if they open while the
 * merchant has the feature off, the gateway advertises subscription support and
 * accepts a renewal schedule that Subscriptions::register_hooks() never wired
 * anything up for, and every renewal fails with no stored credential.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
    define('BCI_WOO_PLUGIN_FILE', dirname(__DIR__) . '/woocommerce-gateway-bci.php');

    $bci_test_options = [];
    $bci_test_actions = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function plugin_dir_url(string $file): string
    {
        return 'https://example.test/wp-content/plugins/woocommerce-gateway-bci/';
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        global $bci_test_actions;
        $bci_test_actions[] = $hook;
    }

    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
    }

    class WC_Payment_Gateway
    {
        public $id = '';
        public $icon = '';
        public $has_fields = false;
        public $method_title = '';
        public $method_description = '';
        public $title = '';
        public $description = '';
        public $supports = [];
        public $enabled = 'no';
        public $settings = [];
        public $form_fields = [];

        public function init_settings(): void
        {
        }

        public function get_option($key, $empty_value = null)
        {
            return $this->settings[$key] ?? $empty_value;
        }
    }

    class WC_Order
    {
    }
}

namespace BCI\Woo {
    /** Stands in for the resolver the gateway builds; nothing here drives it. */
    final class Status_Resolver
    {
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-tokens.php';
    require dirname(__DIR__) . '/includes/class-subscriptions.php';
    require dirname(__DIR__) . '/includes/class-renewals.php';
    require dirname(__DIR__) . '/includes/class-gateway.php';

    const RENEWAL_HOOK = 'woocommerce_scheduled_subscription_payment_' . Config::GATEWAY_ID;

    const SUBSCRIPTION_SUPPORTS = [
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change',
        'subscription_payment_method_change_customer',
        'subscription_payment_method_change_admin',
        'multiple_subscriptions',
    ];

    function build_gateway(bool $enabled): Gateway
    {
        global $bci_test_options, $bci_test_actions;

        $bci_test_options = [Config::OPTION_KEY => ['enable_subscriptions' => $enabled ? 'yes' : 'no']];
        $bci_test_actions = [];

        return new Gateway();
    }

    function registered_actions(): array
    {
        global $bci_test_actions;
        return $bci_test_actions;
    }

    function fail(string $message): void
    {
        throw new \RuntimeException($message);
    }

    // The renewal hook registers once per process, so the closed gate has to be
    // measured first: a leak there would otherwise be masked by the open one.
    $closed = build_gateway(false);

    if ($closed->supports !== ['products']) {
        fail(sprintf(
            'a gateway with subscriptions disabled advertises %s',
            implode(', ', $closed->supports)
        ));
    }

    if (in_array(RENEWAL_HOOK, registered_actions(), true)) {
        fail('a gateway with subscriptions disabled scheduled renewal payments');
    }

    $open = build_gateway(true);

    foreach (SUBSCRIPTION_SUPPORTS as $support) {
        if (!in_array($support, $open->supports, true)) {
            fail(sprintf('a gateway with subscriptions enabled does not advertise %s', $support));
        }
    }

    if (!in_array('products', $open->supports, true)) {
        fail('the gateway stopped advertising one-off product support');
    }

    if (!in_array(RENEWAL_HOOK, registered_actions(), true)) {
        fail('a gateway with subscriptions enabled did not register the renewal hook');
    }

    echo "Gateway subscription registration tests passed.\n";
}
