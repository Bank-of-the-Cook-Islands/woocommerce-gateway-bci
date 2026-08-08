<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_options = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }
}

namespace BCI\Woo {
    final class Config
    {
        public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
    }

    final class Log
    {
        public static array $notices = [];

        public static function notice(string $message, array $context = []): void
        {
            self::$notices[] = [$message, $context];
        }
    }

    require dirname(__DIR__) . '/includes/class-api.php';

    /**
     * Asserts the currency a payment is charged in under $settings.
     *
     * With no environment named the answer follows the test_mode setting, which
     * is the checkout contract: a one off payment registers and pays in the
     * environment the store is pointed at right now. With an environment named
     * the answer follows that environment instead, whatever test_mode says.
     */
    function assert_currency(array $settings, string $expected_code, string $expected_numeric, ?string $environment = null): void
    {
        global $bci_test_options;
        $bci_test_options = [Config::OPTION_KEY => $settings];

        if ($environment === null) {
            $actual_code = Api::payment_currency_code('NZD');
            $actual_numeric = Api::payment_currency_to_numeric('NZD');
        } else {
            $actual_code = Api::payment_currency_code('NZD', $environment);
            $actual_numeric = Api::payment_currency_to_numeric('NZD', $environment);
        }

        if ($actual_code !== $expected_code || $actual_numeric !== $expected_numeric) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %s/%s, got %s/%s for settings %s in environment %s',
                    $expected_code,
                    $expected_numeric,
                    $actual_code,
                    $actual_numeric,
                    json_encode($settings),
                    $environment ?? 'current'
                )
            );
        }
    }

    assert_currency(['test_mode' => 'yes'], 'EUR', '978');
    assert_currency(['test_mode' => 'yes', 'sandbox_currency' => 'EUR'], 'EUR', '978');
    assert_currency(['test_mode' => 'yes', 'sandbox_currency' => 'NZD'], 'NZD', '554');
    assert_currency(['test_mode' => 'yes', 'sandbox_currency' => 'USD'], 'EUR', '978');
    assert_currency(['test_mode' => 'yes', 'sandbox_force_eur_currency' => 'yes'], 'EUR', '978');
    assert_currency(['test_mode' => 'no', 'sandbox_currency' => 'EUR'], 'NZD', '554');

    // A charge addressed to an environment is priced for that environment. This
    // is what a renewal needs: its binding only exists on one host, and test mode
    // may have been switched either way since the subscription was taken out.
    assert_currency(['test_mode' => 'no'], 'EUR', '978', 'sandbox');
    assert_currency(['test_mode' => 'no', 'sandbox_currency' => 'NZD'], 'NZD', '554', 'sandbox');
    assert_currency(['test_mode' => 'no', 'sandbox_force_eur_currency' => 'yes'], 'EUR', '978', 'sandbox');
    assert_currency(['test_mode' => 'yes'], 'NZD', '554', 'live');
    assert_currency(['test_mode' => 'yes', 'sandbox_currency' => 'EUR'], 'NZD', '554', 'live');

    // Naming the environment the store is already in changes nothing, and an
    // empty environment is no environment at all: both keep the setting's answer.
    assert_currency(['test_mode' => 'yes'], 'EUR', '978', 'sandbox');
    assert_currency(['test_mode' => 'no'], 'NZD', '554', 'live');
    assert_currency(['test_mode' => 'yes'], 'EUR', '978', '');
    assert_currency(['test_mode' => 'no'], 'NZD', '554', '');

    echo "Currency tests passed.\n";
}
