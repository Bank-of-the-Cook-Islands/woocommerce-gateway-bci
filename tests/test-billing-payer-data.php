<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }

    class WC_Payment_Gateway
    {
    }

    class WC_Order
    {
        public function __construct(private array $billing)
        {
        }

        public function get_billing_city(): string
        {
            return (string) ($this->billing['city'] ?? '');
        }

        public function get_billing_country(): string
        {
            return (string) ($this->billing['country'] ?? '');
        }

        public function get_billing_address_1(): string
        {
            return (string) ($this->billing['address_1'] ?? '');
        }

        public function get_billing_address_2(): string
        {
            return (string) ($this->billing['address_2'] ?? '');
        }

        public function get_billing_postcode(): string
        {
            return (string) ($this->billing['postcode'] ?? '');
        }

        public function get_billing_state(): string
        {
            return (string) ($this->billing['state'] ?? '');
        }
    }
}

namespace BCI\Woo {
    final class Api
    {
    }

    final class Status_Resolver
    {
    }

    final class Config
    {
        public const TEXT_DOMAIN = 'bci-woo';
        public const GATEWAY_ID = 'bci_takuecom';
    }

    require dirname(__DIR__) . '/includes/class-gateway.php';

    function payer_data(array $billing): array
    {
        $gateway = (new \ReflectionClass(Gateway::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Gateway::class, 'billing_payer_data');
        return $method->invoke($gateway, new \WC_Order($billing));
    }

    function assert_same(array $expected, array $actual, string $case): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                sprintf('%s failed: expected %s, got %s', $case, json_encode($expected), json_encode($actual))
            );
        }
    }

    $base = [
        'city' => 'Avarua',
        'address_1' => 'Test <b>Address</b>',
        'postcode' => '0000',
    ];

    assert_same(
        [
            'billingCity' => 'Avarua',
            'billingCountry' => 'CK',
            'billingAddressLine1' => 'Test Address',
            'billingPostalCode' => '0000',
        ],
        payer_data($base + ['country' => 'ck', 'state' => 'Rarotonga']),
        'Cook Islands state is omitted'
    );

    assert_same(
        [
            'billingCity' => 'Los Angeles',
            'billingCountry' => 'US',
            'billingAddressLine1' => 'Test Address',
            'billingState' => 'CA',
        ],
        payer_data([
            'city' => 'Los Angeles',
            'country' => 'US',
            'address_1' => 'Test Address',
            'state' => 'CA',
        ]),
        'Recognized state is preserved'
    );

    $long_state = str_repeat('A', 60);
    $long_result = payer_data(['country' => 'US', 'state' => $long_state]);
    if (($long_result['billingState'] ?? '') !== str_repeat('A', 50)) {
        throw new \RuntimeException('Billing state length limit failed.');
    }

    $empty_result = payer_data(['country' => 'NZ', 'state' => '']);
    if (array_key_exists('billingState', $empty_result)) {
        throw new \RuntimeException('Empty billing state should be filtered out.');
    }

    echo "Billing payer data tests passed.\n";
}
