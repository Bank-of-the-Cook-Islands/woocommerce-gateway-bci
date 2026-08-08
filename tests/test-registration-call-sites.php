<?php

/**
 * The checkout surfaces that reach one-off registration.
 *
 * tests/test-registration.php pins what the registration module does. This file
 * pins how a checkout gets there, which is the other half of the invariant: a
 * customer must not be able to reach a hosted payment form by a route that skips
 * the module and its persistence.
 *
 * There is one route. Classic checkout calls Gateway::process_payment(), and so
 * does Checkout Blocks — the blocks integration supplies the payment method's
 * appearance and availability and nothing else, resolving the very gateway
 * object WooCommerce registered rather than carrying a payment path of its own.
 * Both facts are asserted here: process_payment() is driven end to end against a
 * gateway built the way WooCommerce builds it, and the blocks integration is
 * shown to be looking at that same gateway instance.
 *
 * What the gateway is left owning after the module took the payment over is the
 * WooCommerce half of the answer: the checkout notice, and the result array that
 * decides whether the customer is redirected at all.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
    define('BCI_WOO_PLUGIN_FILE', dirname(__DIR__) . '/woocommerce-gateway-bci.php');

    /** @var array<int, WC_Order> Orders the store can find, by id. */
    $bci_test_orders = [];

    /** @var array<int, array{0: string, 1: string}> Every checkout notice raised, as [message, type]. */
    $bci_test_notices = [];

    function get_option(string $key, $default = false)
    {
        return $key === 'woocommerce_bci_takuecom_settings' ? ['enabled' => 'yes', 'title' => 'Card (BCI TakuEcom)'] : $default;
    }

    function plugin_dir_url(string $file): string
    {
        return 'https://shop.test/wp-content/plugins/woocommerce-gateway-bci/';
    }

    function plugins_url(string $path, string $file): string
    {
        return 'https://shop.test/wp-content/plugins/woocommerce-gateway-bci/' . $path;
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
    }

    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
    }

    function wc_format_decimal($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    function wp_json_encode($value)
    {
        return json_encode($value);
    }

    function esc_url_raw(string $url): string
    {
        return $url;
    }

    function home_url(string $path = '/'): string
    {
        return 'https://shop.test' . $path;
    }

    function add_query_arg(array $args, string $url): string
    {
        return $url . '?' . http_build_query($args);
    }

    function get_bloginfo(string $show = ''): string
    {
        return $show === 'version' ? '6.5' : 'Test Store';
    }

    class WP_Error
    {
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function wc_get_order($order_id)
    {
        global $bci_test_orders;

        return $bci_test_orders[(int) $order_id] ?? false;
    }

    function wc_add_notice(string $message, string $type = 'success'): void
    {
        global $bci_test_notices;
        $bci_test_notices[] = [$message, $type];
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

        public function get_title(): string
        {
            return $this->title;
        }

        public function get_description(): string
        {
            return $this->description;
        }

        public function supports($feature): bool
        {
            return in_array($feature, $this->supports, true);
        }

        public function is_available(): bool
        {
            return $this->enabled === 'yes';
        }
    }

    class WC_Order
    {
        public array $meta = [];
        public int $saves = 0;

        /** @var array<int, string> */
        public array $notes = [];

        public function __construct(private int $id)
        {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_order_number(): string
        {
            return (string) $this->id;
        }

        public function get_order_key(): string
        {
            return 'wc_order_key_' . $this->id;
        }

        public function get_total(): string
        {
            return '25.00';
        }

        public function get_currency(): string
        {
            return 'NZD';
        }

        public function get_billing_email(): string
        {
            return '';
        }

        public function get_billing_city(): string
        {
            return '';
        }

        public function get_billing_country(): string
        {
            return '';
        }

        public function get_billing_address_1(): string
        {
            return '';
        }

        public function get_billing_address_2(): string
        {
            return '';
        }

        public function get_billing_postcode(): string
        {
            return '';
        }

        public function get_billing_state(): string
        {
            return '';
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save(): void
        {
            $this->saves++;
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }
    }

    /** The gateway registry WooCommerce hands the blocks integration. */
    final class BCI_Test_Payment_Gateways
    {
        /** @var array<string, object> */
        public array $gateways = [];

        public function payment_gateways(): array
        {
            return $this->gateways;
        }
    }

    final class BCI_Test_WC
    {
        public BCI_Test_Payment_Gateways $gateways;

        public function __construct()
        {
            $this->gateways = new BCI_Test_Payment_Gateways();
        }

        public function payment_gateways(): BCI_Test_Payment_Gateways
        {
            return $this->gateways;
        }
    }

    function WC(): BCI_Test_WC
    {
        static $wc = null;

        if ($wc === null) {
            $wc = new BCI_Test_WC();
        }

        return $wc;
    }
}

namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
    /** Stands in for the WooCommerce Blocks base class the integration extends. */
    abstract class AbstractPaymentMethodType
    {
        protected $name = '';

        protected $settings = [];

        abstract public function initialize();

        abstract public function is_active();

        abstract public function get_payment_method_script_handles();

        abstract public function get_payment_method_data();
    }
}

namespace BCI\Woo {
    final class Log
    {
        public static function info(string $message, array $context = []): void
        {
        }

        public static function notice(string $message, array $context = []): void
        {
        }

        public static function error(string $message, array $context = []): void
        {
        }
    }

    /**
     * Stands in for the gateway client. Registration is driven for real; only the
     * BPC answer is canned, so what the gateway does with a success or a failure
     * is what is being measured.
     */
    final class Api
    {
        public const LIVE_CURRENCY = 'NZD';

        /** @var array<int, array{0: array, 1: string}> Every registration asked for. */
        public static array $registrations = [];

        /** @var array|\WP_Error What the next registration answers with. */
        public static $response = [];

        public static function current_environment(): string
        {
            return 'live';
        }

        public static function subscriptions_enabled(): bool
        {
            return false;
        }

        public static function has_credentials(string $environment): bool
        {
            return true;
        }

        public static function get_setting(string $key, $default = '')
        {
            return $default;
        }

        public static function payment_currency_to_numeric(?string $currency, ?string $environment = null): string
        {
            return '554';
        }

        public function register_payment(array $params, string $environment)
        {
            self::$registrations[] = [$params, $environment];

            return self::$response;
        }
    }

    /** Stands in for the resolver the gateway builds; nothing here drives it. */
    final class Status_Resolver
    {
        public function __construct($api = null)
        {
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-registration-result.php';
    require dirname(__DIR__) . '/includes/class-registration.php';
    require dirname(__DIR__) . '/includes/class-gateway.php';
    require dirname(__DIR__) . '/includes/blocks/class-blocks-support.php';

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

    function assert_true(bool $condition, string $label): void
    {
        if (!$condition) {
            throw new \RuntimeException($label);
        }
    }

    function checkout(Gateway $gateway, int $order_id): array
    {
        global $bci_test_notices;
        $bci_test_notices = [];
        Api::$registrations = [];

        return $gateway->process_payment($order_id);
    }

    function notices(): array
    {
        global $bci_test_notices;

        return $bci_test_notices;
    }

    global $bci_test_orders;
    $bci_test_orders = [21 => new \WC_Order(21), 22 => new \WC_Order(22)];

    $gateway = new Gateway();
    $gateway->enabled = 'yes';

    // ------------------------------------------------------------------
    // Classic checkout
    // ------------------------------------------------------------------

    Api::$response = ['errorCode' => '0', 'formUrl' => 'https://pay.test/form?mdOrder=md-21', 'orderId' => 'md-21'];
    $result = checkout($gateway, 21);

    assert_same('success', $result['result'] ?? '', 'a registered payment lets checkout continue');
    assert_same('https://pay.test/form?mdOrder=md-21', $result['redirect'] ?? '', 'checkout redirects to the hosted payment form');
    assert_same([], notices(), 'a successful checkout raises no notice');
    assert_same(1, count(Api::$registrations), 'checkout registers the payment once');

    $order = $bci_test_orders[21];
    assert_same('md-21', $order->get_meta('_bci_woo_md_order'), 'the gateway reference is on the order before the redirect');
    assert_same(
        Api::$registrations[0][0]['orderNumber'],
        $order->get_meta('_bci_woo_order_number'),
        'the orderNumber that was registered is the one recorded'
    );
    assert_same('live', $order->get_meta('_bci_woo_environment'), 'the environment the payment was registered in is recorded');
    assert_same(1, $order->saves, 'the reference is saved, not just staged');

    // A failed registration stops checkout with the module's reason, and leaves the
    // order with nothing that would make the callback or the scheduler act on it.
    Api::$response = ['errorCode' => '5', 'errorMessage' => 'Access denied'];
    $failed = checkout($gateway, 22);

    assert_same('failure', $failed['result'] ?? '', 'a failed registration fails checkout');
    assert_true(!array_key_exists('redirect', $failed), 'a failed checkout has nowhere to redirect to');
    assert_same(
        [['Cannot start payment at BCI. Error 5: Access denied', 'error']],
        notices(),
        'the customer is told once why the payment could not start'
    );

    $failed_order = $bci_test_orders[22];
    assert_same('', $failed_order->get_meta('_bci_woo_md_order'), 'a failed registration leaves no gateway reference');
    assert_same(0, $failed_order->saves, 'a failed registration does not save the order');

    // An order id checkout cannot resolve never reaches registration at all.
    $missing = checkout($gateway, 999);
    assert_same('failure', $missing['result'] ?? '', 'an order that cannot be found fails checkout');
    assert_same([], Api::$registrations, 'an order that cannot be found is never registered');

    // ------------------------------------------------------------------
    // Checkout Blocks
    // ------------------------------------------------------------------

    // The blocks integration has no payment path of its own: it registers the
    // gateway's own id, and WooCommerce routes the block checkout to the same
    // process_payment() driven above.
    assert_true(
        !method_exists(Blocks_Support::class, 'process_payment'),
        'the blocks integration must not carry a registration path of its own'
    );

    \WC()->gateways->gateways = [Config::GATEWAY_ID => $gateway];

    $blocks = new Blocks_Support();
    $blocks->initialize();

    assert_true($blocks->is_active(), 'the blocks integration offers the gateway while it is available');

    // The integration is looking at the registered gateway object itself, which is
    // what makes the block checkout and the classic checkout the same payment path:
    // a change to that instance is visible through the block payment method data.
    $gateway->title = 'Card (BCI TakuEcom) - shared instance';
    assert_same(
        'Card (BCI TakuEcom) - shared instance',
        $blocks->get_payment_method_data()['title'] ?? '',
        'the blocks integration reads the gateway WooCommerce registered'
    );

    $gateway->enabled = 'no';
    assert_true(!$blocks->is_active(), 'the blocks integration hides the gateway when the gateway itself is unavailable');
    $gateway->enabled = 'yes';

    // And that same instance still registers payments through the module.
    Api::$response = ['errorCode' => '0', 'formUrl' => 'https://pay.test/form?mdOrder=md-23', 'orderId' => 'md-23'];
    $bci_test_orders[23] = new \WC_Order(23);
    $blocks_result = checkout($gateway, 23);

    assert_same('success', $blocks_result['result'] ?? '', 'the gateway the block checkout uses registers payments');
    assert_same('md-23', $bci_test_orders[23]->get_meta('_bci_woo_md_order'), 'a block checkout order records its gateway reference');

    echo "Registration call site tests passed.\n";
}
