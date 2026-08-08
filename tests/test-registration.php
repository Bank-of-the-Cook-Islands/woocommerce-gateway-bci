<?php

/**
 * One-off payment registration, through the module's public interface.
 *
 * Registration::register() is the only way a WooCommerce order becomes a payment
 * BPC knows about, so it is driven here end to end: the real Api transport with
 * only wp_remote_post() standing in for the network, so the request BPC would
 * receive is the one asserted on.
 *
 * The invariant this file exists for is that a customer is never sent to a
 * hosted payment form the order cannot be matched back to. A success must have
 * recorded the mdOrder, the orderNumber a callback will quote, and the
 * environment both belong to, and must have saved them before the redirect is
 * handed back. A failure — network fault, gateway error code, or a 200 carrying
 * no payment link — must leave the order exactly as it was, with no half-written
 * reference for the callback, the scheduler or a renewal to find.
 *
 * This file also supersedes tests/test-billing-payer-data.php, which reached
 * into the private billing_payer_data() by reflection. The same rules are now
 * pinned on the billingPayerData that actually reaches the gateway.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    /** @var array The plugin settings the Api reads. */
    $bci_test_settings = [];

    /** @var array{0: string, 1: array}|null The last request wp_remote_post() was given, as [url, params]. */
    $bci_test_request = null;

    /** @var mixed What wp_remote_post() answers with next. */
    $bci_test_response = null;

    /** @var callable|null Stands in for whatever has hooked the register params filter. */
    $bci_test_params_filter = null;

    class WP_Error
    {
        public function __construct(private string $code, private string $message)
        {
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function get_option(string $key, $default = false)
    {
        global $bci_test_settings;

        return $key === 'woocommerce_bci_takuecom_settings' ? $bci_test_settings : $default;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function wc_format_decimal($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    function wp_json_encode($value)
    {
        return json_encode($value);
    }

    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
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
        return $show === 'version' ? '6.5' : 'Test <b>Store</b>';
    }

    function apply_filters(string $hook, $value, ...$args)
    {
        global $bci_test_params_filter;

        if ($hook !== 'bci_woo_register_payment_params' || $bci_test_params_filter === null) {
            return $value;
        }

        return ($bci_test_params_filter)($value, ...$args);
    }

    function wp_remote_post(string $url, array $args)
    {
        global $bci_test_request, $bci_test_response;

        parse_str((string) $args['body'], $params);
        $bci_test_request = [$url, $params];

        return $bci_test_response;
    }

    function wp_remote_retrieve_response_code($response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }

    function wp_remote_retrieve_body($response): string
    {
        return (string) ($response['body'] ?? '');
    }

    class WC_Order
    {
        public array $meta = [];

        /** @var array The meta as it stood the last time the order was saved. */
        public array $saved_meta = [];

        public int $saves = 0;

        /** @var array<int, string> */
        public array $notes = [];

        public function __construct(private int $id, private array $fields = [])
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
            return (string) ($this->fields['total'] ?? '0');
        }

        public function get_currency(): string
        {
            return (string) ($this->fields['currency'] ?? 'NZD');
        }

        public function get_billing_email(): string
        {
            return (string) ($this->fields['email'] ?? '');
        }

        public function get_billing_city(): string
        {
            return (string) ($this->fields['city'] ?? '');
        }

        public function get_billing_country(): string
        {
            return (string) ($this->fields['country'] ?? '');
        }

        public function get_billing_address_1(): string
        {
            return (string) ($this->fields['address_1'] ?? '');
        }

        public function get_billing_address_2(): string
        {
            return (string) ($this->fields['address_2'] ?? '');
        }

        public function get_billing_postcode(): string
        {
            return (string) ($this->fields['postcode'] ?? '');
        }

        public function get_billing_state(): string
        {
            return (string) ($this->fields['state'] ?? '');
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
            $this->saved_meta = $this->meta;
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }
    }
}

namespace BCI\Woo {
    /** Collects what the module logs; nothing here drives the module. */
    final class Log
    {
        /** @var array<int, array{0: string, 1: array}> */
        public static array $notices = [];

        public static function info(string $message, array $context = []): void
        {
        }

        public static function notice(string $message, array $context = []): void
        {
            self::$notices[] = [$message, $context];
        }

        public static function error(string $message, array $context = []): void
        {
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-registration-result.php';
    require dirname(__DIR__) . '/includes/class-registration.php';

    const LIVE_SETTINGS = [
        'test_mode' => 'no',
        'live_api_login' => 'live-login',
        'live_api_password' => 'live-password',
    ];

    const SANDBOX_SETTINGS = [
        'test_mode' => 'yes',
        'sandbox_api_login' => 'sandbox-login',
        'sandbox_api_password' => 'sandbox-password',
        'sandbox_currency' => 'EUR',
    ];

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

    /** Queues a gateway answer: an HTTP status and a decoded body. */
    function answer(int $code, array $body): void
    {
        global $bci_test_response;
        $bci_test_response = ['response' => ['code' => $code], 'body' => (string) json_encode($body)];
    }

    /** Queues a WordPress transport failure. */
    function answer_network_error(string $message): void
    {
        global $bci_test_response;
        $bci_test_response = new \WP_Error('http_request_failed', $message);
    }

    /**
     * Registers an order through the module, under the given settings.
     */
    function register(\WC_Order $order, array $settings = LIVE_SETTINGS): Registration_Result
    {
        global $bci_test_settings, $bci_test_request;

        $bci_test_settings = $settings;
        $bci_test_request = null;
        Log::$notices = [];

        return (new Registration())->register($order);
    }

    /** The URL the last request went to. */
    function request_url(): string
    {
        global $bci_test_request;

        return (string) ($bci_test_request[0] ?? '');
    }

    /** The parameters the last request carried. */
    function sent(): array
    {
        global $bci_test_request;

        return (array) ($bci_test_request[1] ?? []);
    }

    /** The billingPayerData of the last request, decoded. */
    function sent_billing(): array
    {
        $encoded = sent()['billingPayerData'] ?? '';

        return $encoded === '' ? [] : (array) json_decode((string) $encoded, true);
    }

    /** The BCI meta an order holds. */
    function bci_meta(\WC_Order $order): array
    {
        return array_filter(
            $order->meta,
            static fn (string $key): bool => strpos($key, '_bci_woo_') === 0,
            ARRAY_FILTER_USE_KEY
        );
    }

    function billing_order(array $billing): \WC_Order
    {
        return new \WC_Order(77, $billing + ['total' => '10.00', 'currency' => 'NZD']);
    }

    // ------------------------------------------------------------------
    // A successful registration
    // ------------------------------------------------------------------

    $order = new \WC_Order(77, [
        'total' => '12.34',
        'currency' => 'NZD',
        'email' => 'shopper@example.test',
        'city' => 'Avarua',
        'country' => 'CK',
        'address_1' => 'Main Road',
        'postcode' => '0000',
        'state' => 'Rarotonga',
    ]);

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form?mdOrder=md-77', 'orderId' => 'md-77']);
    $result = register($order);

    assert_true($result->is_successful(), 'a registered payment is a successful result');
    assert_same('https://pay.test/form?mdOrder=md-77', $result->redirect_url(), 'the hosted payment form is where the customer is sent');
    assert_same('', $result->error_message(), 'a successful registration carries no error');

    assert_same(Config::API_URL_LIVE . '/register.do', request_url(), 'a live checkout registers against the live host');

    $sent = sent();
    assert_same('1234', $sent['amount'] ?? '', 'the amount is sent in minor units');
    assert_same('554', $sent['currency'] ?? '', 'a live checkout is charged in NZD (554)');
    assert_same('en', $sent['language'] ?? '', 'the hosted form is requested in English');
    assert_same('WooCommerce order #77 - Test Store', $sent['description'] ?? '', 'the description names the order and the store, without markup');
    assert_same('shopper@example.test', $sent['email'] ?? '', 'the customer email is sent for the receipt');
    assert_same('live-login', $sent['userName'] ?? '', 'the request is authenticated with the live credentials');

    assert_true(
        (bool) preg_match('/^WC77-\d+$/', (string) ($sent['orderNumber'] ?? '')),
        'the orderNumber names the order and the attempt, got ' . var_export($sent['orderNumber'] ?? null, true)
    );
    assert_true(strlen((string) $sent['orderNumber']) <= 36, 'the orderNumber fits the 36 characters BPC accepts');

    $return_url = 'https://shop.test/?wc-api=bci_takuecom_return&order_id=77&key=wc_order_key_77';
    assert_same($return_url, $sent['returnUrl'] ?? '', 'the return URL carries the order and its key');
    assert_same($return_url, $sent['failUrl'] ?? '', 'a failed payment comes back the same way');

    $json_params = (array) json_decode((string) ($sent['jsonParams'] ?? ''), true);
    assert_same('redirect', $json_params['CMS_paymentType'] ?? '', 'the request identifies itself as a redirect integration');
    assert_same(Config::VERSION, $json_params['Module-Version'] ?? '', 'the request identifies the module version');

    // The invariant: what the order was left holding, as of the save that ran
    // before the redirect was handed back.
    assert_same(1, $order->saves, 'a successful registration saves the order exactly once');
    assert_same('md-77', $order->saved_meta['_bci_woo_md_order'] ?? '', 'the BPC orderId is persisted before the redirect');
    assert_same(
        $sent['orderNumber'],
        $order->saved_meta['_bci_woo_order_number'] ?? '',
        'the orderNumber a callback will quote is persisted before the redirect'
    );
    assert_same('live', $order->saved_meta['_bci_woo_environment'] ?? '', 'the environment the payment belongs to is persisted before the redirect');
    assert_same(
        1,
        count($order->notes),
        'a successful registration leaves one order note'
    );

    // The environment decides the host and the currency together, and is what the
    // order records: a sandbox checkout is charged in the sandbox currency against
    // the sandbox host, whatever the order itself is priced in.
    $sandbox_order = new \WC_Order(78, ['total' => '20.00', 'currency' => 'NZD']);
    answer(200, ['errorCode' => '0', 'formUrl' => 'https://dev.pay.test/form', 'orderId' => 'md-78']);
    $sandbox_result = register($sandbox_order, SANDBOX_SETTINGS);

    assert_true($sandbox_result->is_successful(), 'a sandbox registration succeeds');
    assert_same(Config::API_URL_SANDBOX . '/register.do', request_url(), 'a sandbox checkout registers against the sandbox host');
    assert_same('978', sent()['currency'] ?? '', 'a sandbox checkout is charged in the sandbox currency EUR (978)');
    assert_same('sandbox', $sandbox_order->saved_meta['_bci_woo_environment'] ?? '', 'the sandbox environment is recorded on the order');
    assert_same('sandbox-login', sent()['userName'] ?? '', 'the sandbox credentials are used');

    // The minor-unit conversion is the only arithmetic in the module, and the
    // totals it has to survive are the ones a binary float cannot hold: 0.29
    // multiplies out to 28.999…, so anything that truncates instead of rounding
    // registers the order a cent short of what WooCommerce recorded, and every
    // such order settles short.
    foreach (['0.29' => '29', '1.13' => '113', '2.01' => '201'] as $total => $minor_units) {
        answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-83']);
        register(new \WC_Order(83, ['total' => $total, 'currency' => 'NZD']));

        assert_same(
            $minor_units,
            sent()['amount'] ?? '',
            sprintf('a total of %s is registered as %s minor units, not a cent less', $total, $minor_units)
        );
    }

    // ------------------------------------------------------------------
    // The stored-credential contract with Tokens
    // ------------------------------------------------------------------

    // Subscription behaviour reaches registration only through the filter, which is
    // hooked at the registration sites when the merchant has enabled subscriptions.
    // Whatever it adds is sent, and a clientId it added is recorded with the
    // reference, because that is the identity the binding is created against.
    global $bci_test_params_filter;
    $filtered = [];
    $bci_test_params_filter = static function (array $params, $order) use (&$filtered): array {
        $filtered[] = [$params, $order];
        $params['clientId'] = 'wc_customer_7';
        $params['features'] = 'FORCE_CREATE_BINDING';

        return $params;
    };

    $subscription_order = new \WC_Order(79, ['total' => '5.00', 'currency' => 'NZD']);
    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-79']);
    $subscription_result = register($subscription_order);

    assert_true($subscription_result->is_successful(), 'a subscription checkout registers');
    assert_same(1, count($filtered), 'the register params are offered to the filter exactly once');
    assert_same($subscription_order, $filtered[0][1], 'the filter is given the order being registered');
    assert_true(isset($filtered[0][0]['amount']), 'the filter is given the built request');
    assert_same('wc_customer_7', sent()['clientId'] ?? '', 'what the filter added is sent to BPC');
    assert_same('FORCE_CREATE_BINDING', sent()['features'] ?? '', 'the binding request the filter added is sent to BPC');
    assert_same(
        'wc_customer_7',
        $subscription_order->saved_meta['_bci_woo_client_id'] ?? '',
        'the clientId the payment registered with is persisted with the reference'
    );

    $bci_test_params_filter = null;

    // ------------------------------------------------------------------
    // Failures leave no registration behind
    // ------------------------------------------------------------------

    /**
     * Registers an order that cannot succeed and asserts the order is untouched.
     */
    function assert_failed(string $expected_message, string $case, ?\WC_Order $order = null): void
    {
        $order = $order ?? new \WC_Order(80, ['total' => '10.00', 'currency' => 'NZD']);
        $result = register($order);

        assert_true(!$result->is_successful(), $case . ': the result must be a failure');
        assert_same('', $result->redirect_url(), $case . ': a failure has nowhere to send the customer');
        assert_same($expected_message, $result->error_message(), $case . ': the customer is told why');
        assert_same([], bci_meta($order), $case . ': a failed registration records no BCI state');
        assert_same(0, $order->saves, $case . ': a failed registration does not save the order');
        assert_same(1, count($order->notes), $case . ': a failed registration leaves one order note');
        assert_true(
            strpos($order->notes[0], 'BCI payment registration failed') === 0,
            $case . ': the order note says the registration failed, got ' . $order->notes[0]
        );
    }

    answer_network_error('Connection refused');
    assert_failed(
        'Cannot start payment at BCI. Network error: Connection refused',
        'a WordPress transport failure'
    );

    answer(500, ['errorCode' => '0']);
    assert_failed(
        'Cannot start payment at BCI. Network error: BCI API returned HTTP 500',
        'a gateway HTTP error'
    );

    answer(200, ['errorCode' => '5', 'errorMessage' => 'Access denied']);
    assert_failed(
        'Cannot start payment at BCI. Error 5: Access denied',
        'a BPC error response'
    );

    assert_same(
        [['BCI payment registration failed.', ['error_code' => '5', 'error_message' => 'Access denied']]],
        Log::$notices,
        'a BPC error code is logged with its message'
    );

    answer(200, ['errorCode' => '1']);
    assert_failed(
        'Cannot start payment at BCI. Error 1: Unknown error',
        'a BPC error response with no message'
    );

    answer(200, ['errorCode' => '0', 'orderId' => 'md-80']);
    assert_failed(
        'BCI did not return a payment link. Please check the gateway logs.',
        'a registration with no formUrl'
    );

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form']);
    assert_failed(
        'BCI did not return a payment link. Please check the gateway logs.',
        'a registration with no orderId'
    );

    // A retry after a failure is a registration like any other: the order that was
    // left clean is the one that ends up holding the reference.
    $retried = new \WC_Order(81, ['total' => '10.00', 'currency' => 'NZD']);
    answer(200, ['errorCode' => '5', 'errorMessage' => 'Access denied']);
    assert_failed('Cannot start payment at BCI. Error 5: Access denied', 'the first attempt', $retried);
    $first_attempt_number = (string) (sent()['orderNumber'] ?? '');

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/again', 'orderId' => 'md-81']);
    $retry = register($retried);

    assert_true($retry->is_successful(), 'a retry after a failed registration succeeds');
    assert_same('md-81', $retried->saved_meta['_bci_woo_md_order'] ?? '', 'the retry records its own reference');

    // What is numbered is the attempt, not the order. Both attempts above run in
    // the same second, as a double-submitted Place Order or a retry from the
    // order-pay page does, and BPC rejects an orderNumber it has already seen —
    // so a number that only counts seconds fails the second attempt outright.
    assert_true($first_attempt_number !== '', 'the failed attempt was numbered too');
    assert_true(
        (string) (sent()['orderNumber'] ?? '') !== $first_attempt_number,
        'two attempts a moment apart are numbered apart, got ' . $first_attempt_number . ' twice'
    );

    // ------------------------------------------------------------------
    // Billing payer rules, as BPC receives them
    // ------------------------------------------------------------------

    // CK has no ISO subdivisions, so a Cook Islands island name must never be sent
    // as billingState: BPC rejects it and the whole registration goes with it.
    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-1']);
    register(billing_order([
        'city' => 'Avarua',
        'country' => 'ck',
        'address_1' => 'Test <b>Address</b>',
        'postcode' => '0000',
        'state' => 'Rarotonga',
    ]));

    assert_same(
        [
            'billingCity' => 'Avarua',
            'billingCountry' => 'CK',
            'billingAddressLine1' => 'Test Address',
            'billingPostalCode' => '0000',
        ],
        sent_billing(),
        'a Cook Islands address is sent without a state, with an uppercased country and no markup'
    );

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-2']);
    register(billing_order([
        'city' => 'Los Angeles',
        'country' => 'US',
        'address_1' => 'Test Address',
        'state' => 'CA',
    ]));

    assert_same(
        [
            'billingCity' => 'Los Angeles',
            'billingCountry' => 'US',
            'billingAddressLine1' => 'Test Address',
            'billingState' => 'CA',
        ],
        sent_billing(),
        'a recognised state is sent'
    );

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-3']);
    register(billing_order(['country' => 'US', 'state' => str_repeat('A', 60)]));

    assert_same(
        str_repeat('A', 50),
        sent_billing()['billingState'] ?? '',
        'a billing field is cut to the 50 characters BPC accepts'
    );

    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-4']);
    register(billing_order(['country' => 'NZ', 'state' => '', 'address_2' => '']));
    $empty_filtered = sent_billing();

    assert_true(!array_key_exists('billingState', $empty_filtered), 'an empty state is not sent as a blank field');
    assert_true(!array_key_exists('billingAddressLine2', $empty_filtered), 'an empty address line is not sent as a blank field');
    assert_same('NZ', $empty_filtered['billingCountry'] ?? '', 'the fields that are filled are still sent');

    // An order with no billing address at all sends no billingPayerData, rather than
    // an empty object, and no email.
    answer(200, ['errorCode' => '0', 'formUrl' => 'https://pay.test/form', 'orderId' => 'md-5']);
    register(new \WC_Order(90, ['total' => '10.00', 'currency' => 'NZD']));

    assert_true(!array_key_exists('billingPayerData', sent()), 'an order with no billing address sends no billingPayerData');
    assert_true(!array_key_exists('email', sent()), 'an order with no email sends no email parameter');

    echo "Registration tests passed.\n";
}
