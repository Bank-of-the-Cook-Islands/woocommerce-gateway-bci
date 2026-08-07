<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_options = [];
    $bci_test_store_currency = 'NZD';
    $bci_test_orders = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function get_woocommerce_currency(): string
    {
        global $bci_test_store_currency;
        return $bci_test_store_currency;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    class WC_Payment_Gateway
    {
        public $enabled = 'yes';

        public function is_available(): bool
        {
            return $this->enabled === 'yes';
        }
    }

    class WC_Order
    {
        public function __construct(private string $currency)
        {
        }

        public function get_currency(): string
        {
            return $this->currency;
        }
    }

    function wc_get_order($order_id)
    {
        global $bci_test_orders;
        return $bci_test_orders[(int) $order_id] ?? false;
    }

    function wc_add_notice(string $message, string $type = 'success'): void
    {
    }
}

namespace BCI\Woo {
    final class Config
    {
        public const TEXT_DOMAIN = 'bci-woo';
        public const GATEWAY_ID = 'bci_takuecom';
        public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
    }

    final class Log
    {
        public static function notice(string $message, array $context = []): void
        {
        }
    }

    final class Status_Resolver
    {
    }

    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-gateway.php';

    function gateway(string $enabled = 'yes'): Gateway
    {
        $gateway = (new \ReflectionClass(Gateway::class))->newInstanceWithoutConstructor();
        $gateway->enabled = $enabled;
        return $gateway;
    }

    /**
     * @param array<string,string> $settings
     */
    function assert_availability(
        array $settings,
        string $store_currency,
        bool $expected_available,
        string $expected_error_fragment,
        string $case
    ): void {
        global $bci_test_options, $bci_test_store_currency;

        $bci_test_options = [Config::OPTION_KEY => $settings];
        $bci_test_store_currency = $store_currency;

        $available = gateway()->is_available();
        $error = Gateway::availability_error();

        if ($available !== $expected_available) {
            throw new \RuntimeException(sprintf(
                '%s: expected is_available() %s, got %s (error: %s)',
                $case,
                $expected_available ? 'true' : 'false',
                $available ? 'true' : 'false',
                $error === '' ? '(none)' : $error
            ));
        }

        if ($expected_error_fragment === '') {
            if ($error !== '') {
                throw new \RuntimeException($case . ': expected no availability error, got ' . $error);
            }

            return;
        }

        if (strpos($error, $expected_error_fragment) === false) {
            throw new \RuntimeException(sprintf(
                '%s: expected availability error containing "%s", got "%s"',
                $case,
                $expected_error_fragment,
                $error
            ));
        }
    }

    $live_credentials = [
        'test_mode' => 'no',
        'live_api_login' => 'live-login',
        'live_api_password' => 'live-password',
    ];

    $sandbox_credentials = [
        'test_mode' => 'yes',
        'sandbox_api_login' => 'sandbox-login',
        'sandbox_api_password' => 'sandbox-password',
    ];

    assert_availability($live_credentials, 'NZD', true, '', 'live NZD store with credentials');
    assert_availability($live_credentials, 'nzd', true, '', 'live store currency is compared case-insensitively');
    assert_availability($live_credentials, 'USD', false, 'store currency is USD', 'live USD store must not be offered');
    assert_availability($live_credentials, 'EUR', false, 'store currency is EUR', 'live EUR store must not be offered');

    // Sandbox collects in EUR by design, so the store currency must not hide the gateway there.
    assert_availability($sandbox_credentials, 'NZD', true, '', 'sandbox NZD store with credentials');
    assert_availability($sandbox_credentials, 'USD', true, '', 'sandbox USD store with credentials');

    assert_availability(['test_mode' => 'no'], 'NZD', false, 'live API login and password are empty', 'live without credentials');
    assert_availability(
        ['test_mode' => 'no', 'live_api_login' => 'live-login', 'live_api_password' => '   '],
        'NZD',
        false,
        'live API login and password are empty',
        'whitespace-only live password does not count as configured'
    );
    assert_availability(['test_mode' => 'yes'], 'NZD', false, 'sandbox API login and password are empty', 'sandbox without credentials');
    assert_availability(
        ['test_mode' => 'yes', 'sandbox_api_login' => 'sandbox-login'],
        'NZD',
        false,
        'sandbox API login and password are empty',
        'sandbox login without password'
    );

    // A disabled gateway stays unavailable even when fully configured.
    global $bci_test_options, $bci_test_store_currency;
    $bci_test_options = [Config::OPTION_KEY => $live_credentials];
    $bci_test_store_currency = 'NZD';

    if (gateway('no')->is_available()) {
        throw new \RuntimeException('A disabled gateway must never be available.');
    }

    // The request is built from the order currency, so an explicit currency wins over the store
    // currency: a multi-currency plugin or an admin currency switch can leave the two diverged.
    if (Gateway::availability_error('USD') === '') {
        throw new \RuntimeException('A USD order must be refused in live mode even when the store currency is NZD.');
    }

    if (Gateway::availability_error('nzd') !== '') {
        throw new \RuntimeException('An NZD order must be allowed regardless of letter case.');
    }

    $bci_test_store_currency = 'USD';
    if (Gateway::availability_error('NZD') !== '') {
        throw new \RuntimeException('An NZD order must be allowed even when the store currency has moved to USD.');
    }

    // process_payment() must refuse the order before building a request that would collect the
    // order total in NZD, and must not be fooled by an NZD store currency.
    global $bci_test_orders;
    $bci_test_store_currency = 'NZD';
    $bci_test_orders = [
        11 => new \WC_Order('USD'),
        12 => new \WC_Order('NZD'),
    ];

    try {
        $result = gateway()->process_payment(11);
    } catch (\Throwable $e) {
        throw new \RuntimeException(
            'process_payment() built a request for a USD order in live mode instead of refusing it: ' . $e->getMessage()
        );
    }

    if (($result['result'] ?? '') !== 'failure') {
        throw new \RuntimeException('process_payment() must refuse a USD order in live mode.');
    }

    // The NZD order gets past the guard; it fails later only because the API is not wired up here.
    $reached_request = false;
    try {
        gateway()->process_payment(12);
    } catch (\Throwable $e) {
        $reached_request = true;
    }

    if (!$reached_request) {
        throw new \RuntimeException('process_payment() must not block an NZD order in live mode.');
    }

    echo "Gateway availability tests passed.\n";
}
