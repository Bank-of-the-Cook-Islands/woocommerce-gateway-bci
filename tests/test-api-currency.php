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

    function assert_currency(array $settings, string $expected_code, string $expected_numeric): void
    {
        global $bci_test_options;
        $bci_test_options = [Config::OPTION_KEY => $settings];

        $actual_code = Api::payment_currency_code('NZD');
        $actual_numeric = Api::payment_currency_to_numeric('NZD');

        if ($actual_code !== $expected_code || $actual_numeric !== $expected_numeric) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %s/%s, got %s/%s for settings %s',
                    $expected_code,
                    $expected_numeric,
                    $actual_code,
                    $actual_numeric,
                    json_encode($settings)
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

    echo "Currency tests passed.\n";
}
