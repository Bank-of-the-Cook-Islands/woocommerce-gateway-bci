<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }

    class WC_Order
    {
        /** @var array<string, mixed> */
        public array $meta = [];

        public function __construct(private int $id = 1)
        {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_customer_id(): int
        {
            return 0;
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function get_meta(string $key, bool $single = true)
        {
            return $this->meta[$key] ?? '';
        }

        public function save(): void
        {
        }
    }
}

namespace BCI\Woo {
    /** Stands in for the plugin logger the bootstrap always loads. */
    final class Log
    {
        public static function __callStatic(string $level, array $args): void {}
    }

    /** Order state asks the plugin which environment a payment belongs to. */
    final class Api
    {
        public static function current_environment(): string
        {
            return 'live';
        }

        /** Masking is not conditional on the subscription setting. */
        public static function subscriptions_enabled(): bool
        {
            return false;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-resolution.php';
    require dirname(__DIR__) . '/includes/class-tokens.php';
    require dirname(__DIR__) . '/includes/class-status-resolver.php';

    // Synthetic placeholder only; never a real card number.
    const RAW_PAN = '5538070000000000';

    function assert_same(string $expected, string $actual, string $case): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf('%s failed: expected %s, got %s', $case, $expected, $actual));
        }
    }

    function assert_not_raw(string $value, string $case): void
    {
        if (strpos($value, RAW_PAN) !== false || preg_match('/\d{5,}/', $value)) {
            throw new \RuntimeException(sprintf('%s failed: unmasked card digits in %s', $case, $value));
        }
    }

    assert_same('', Config::mask_pan(''), 'Empty label');
    assert_same('************0000', Config::mask_pan(RAW_PAN), 'Raw pan is masked to the last four digits');
    assert_same('553807******0000', Config::mask_pan('553807******0000'), 'Already masked pan keeps its BIN prefix');
    assert_same(
        '************0000',
        Config::mask_pan(Config::mask_pan(RAW_PAN)),
        'Masking is idempotent'
    );
    assert_same('**** **** **** 0000', Config::mask_pan('5538 0700 0000 0000'), 'Separated pan is masked');
    assert_same('Visa ending 0000', Config::mask_pan('Visa ending 0000'), 'Short display labels are untouched');
    assert_same('0000', Config::mask_pan('0000'), 'A bare last four is untouched');
    assert_same(
        '553807******0000 12/30',
        Config::mask_pan('553807******0000 12/30'),
        'An already masked label with an expiry keeps its BIN prefix'
    );

    // A raw pan followed by trailing text must still be masked: a label is never
    // trusted just because some non-digit happens to sit after the digits.
    foreach (
        [
            RAW_PAN . ' 12/30',
            RAW_PAN . '/1230',
            RAW_PAN . ' (MASTERCARD)',
            RAW_PAN . 'F',
            RAW_PAN . '*',
            'MASTERCARD ' . RAW_PAN,
            '5538 0700 0000 0000 (MASTERCARD)',
        ] as $case
    ) {
        $masked = Config::mask_pan($case);
        assert_not_raw($masked, sprintf('Trailing text after a raw pan (%s)', $case));
        assert_same($masked, Config::mask_pan($masked), sprintf('Masking stays idempotent (%s)', $case));
    }

    // Tokens must mask both when reading a status payload and when storing meta.
    $token_data = (new Tokens('bci_takuecom'))->token_data_from_status([
        'cardAuthInfo' => ['pan' => RAW_PAN],
        'bindingId' => 'binding-123',
    ]);
    assert_same('************0000', $token_data['masked_pan'], 'token_data_from_status masks the pan fallback');

    $order = new \WC_Order(11);
    (new Tokens('bci_takuecom'))->store_order_token_data($order, [
        'binding_id' => 'binding-123',
        'client_id' => 'wc_customer_1',
        'masked_pan' => RAW_PAN,
    ]);
    assert_not_raw((string) $order->meta['_bci_woo_masked_pan'], 'store_order_token_data masks the pan');
    assert_same('************0000', (string) $order->meta['_bci_woo_masked_pan'], 'store_order_token_data masks the pan');

    // Status_Resolver writes the same meta key straight from the status payload.
    $resolver = (new \ReflectionClass(Status_Resolver::class))->newInstanceWithoutConstructor();
    $store_binding = new \ReflectionMethod(Status_Resolver::class, 'maybe_store_binding');
    $resolver_order = new \WC_Order(12);
    $store_binding->invoke($resolver, $resolver_order, [
        'bindingId' => 'binding-456',
        'bindingInfo' => ['clientId' => 'wc_customer_2'],
        'cardAuthInfo' => ['pan' => RAW_PAN, 'expiration' => '203012'],
    ]);
    assert_not_raw((string) $resolver_order->meta['_bci_woo_masked_pan'], 'maybe_store_binding masks the pan');
    assert_same(
        '************0000',
        (string) $resolver_order->meta['_bci_woo_masked_pan'],
        'maybe_store_binding masks the pan'
    );

    // The same pan reaching order meta as a label with an expiry appended, which
    // is the shape the display label fallback returns.
    $label_data = (new Tokens('bci_takuecom'))->token_data_from_status([
        'cardAuthInfo' => ['displayLabel' => RAW_PAN . ' 12/30'],
        'bindingId' => 'binding-789',
    ]);
    assert_not_raw($label_data['masked_pan'], 'token_data_from_status masks the display label fallback');

    $label_order = new \WC_Order(13);
    (new Tokens('bci_takuecom'))->store_order_token_data($label_order, [
        'binding_id' => 'binding-789',
        'client_id' => 'wc_customer_3',
        'masked_pan' => RAW_PAN . ' 12/30',
    ]);
    assert_not_raw(
        (string) $label_order->meta['_bci_woo_masked_pan'],
        'store_order_token_data masks a label carrying an expiry'
    );

    $label_resolver_order = new \WC_Order(14);
    $store_binding->invoke($resolver, $label_resolver_order, [
        'bindingId' => 'binding-789',
        'bindingInfo' => ['clientId' => 'wc_customer_3'],
        'cardAuthInfo' => ['pan' => RAW_PAN . ' 12/30'],
    ]);
    assert_not_raw(
        (string) $label_resolver_order->meta['_bci_woo_masked_pan'],
        'maybe_store_binding masks a label carrying an expiry'
    );

    // Config::clean() is the one canonical sanitiser since the cleanup sweep;
    // the array/object guard is the behaviour the deleted copies uniquely
    // carried, so it gets pinned here next to the class's other tests.
    if (Config::clean(['an', 'array']) !== '') {
        throw new \RuntimeException('Config::clean must flatten an array to an empty string');
    }
    if (Config::clean(new \stdClass()) !== '') {
        throw new \RuntimeException('Config::clean must flatten an object to an empty string');
    }
    if (Config::clean('  padded text  ') !== 'padded text') {
        throw new \RuntimeException('Config::clean must trim scalar input');
    }

    echo "Pan masking tests passed.\n";
}
