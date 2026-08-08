<?php

/**
 * The admin settings screen reaches BPC only through the Api client.
 *
 * Admin used to carry a second copy of the gateway HTTP stack: its own endpoint
 * and timeout constants, its own credential lookup, its own wp_remote_post ladder
 * and its own authentication heuristic, while Api::test_connection sat with no
 * callers at all. The copies had drifted, so the same gateway reply could be read
 * two ways depending on which button produced it.
 *
 * These tests drive the two admin buttons that talk to BPC against a recording
 * Api. Nothing here defines wp_remote_post, so the verdicts below can only have
 * been built from what Api returned. The composition is pinned in both
 * directions: which environment is asked about, what is asked for, and which
 * message each answer produces.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    function get_option(string $key, $default = false)
    {
        return $default;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function _n(string $single, string $plural, int $number, string $domain = ''): string
    {
        return $number === 1 ? $single : $plural;
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    class WP_Error
    {
        public function __construct(private string $code = '', private string $message = '')
        {
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }

    /** WooCommerce's base class, reduced to what the gateway's field schema needs. */
    class WC_Payment_Gateway
    {
        public array $form_fields = [];
    }
}

namespace BCI\Woo {
    final class Config
    {
        public const TEXT_DOMAIN = 'bci-woo';
        public const GATEWAY_ID = 'bci_takuecom';
        public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
        public const PENDING_THRESHOLD_MINUTES = 10;
        public const FAILED_LOOKBACK_MINUTES = 60;
    }

    final class Log
    {
        public static function __callStatic(string $level, array $args): void
        {
        }
    }

    final class Status_Resolver
    {
    }

    /**
     * Stands in for the real client. Admin builds its own instance, so what it
     * asked for is recorded statically.
     */
    final class Api
    {
        /** @var array<int,string> Every environment has_credentials() was asked about. */
        public static array $credential_questions = [];

        /** @var array<int,string> Every environment test_connection() was asked about. */
        public static array $connection_tests = [];

        /** @var array<int,array{0:string,1:string}> Every get_bindings() call, as [client id, environment]. */
        public static array $binding_requests = [];

        /** Whether the saved settings hold a complete credential pair. */
        public static bool $has_credentials = true;

        /** @var array<string,mixed> The verdict test_connection() returns next. */
        public static array $connection_result = ['success' => true, 'message' => 'The gateway responded.'];

        /** @var array<string,mixed>|\WP_Error What get_bindings() returns next. */
        public static $bindings_result = ['errorCode' => '0'];

        public static function has_credentials(string $environment): bool
        {
            self::$credential_questions[] = $environment;

            return self::$has_credentials;
        }

        public function test_connection(string $environment): array
        {
            self::$connection_tests[] = $environment;

            return self::$connection_result;
        }

        public function get_bindings(string $client_id, string $environment)
        {
            self::$binding_requests[] = [$client_id, $environment];

            return self::$bindings_result;
        }
    }

    require dirname(__DIR__) . '/includes/class-admin.php';
    require dirname(__DIR__) . '/includes/class-gateway.php';

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

    function assert_contains(string $needle, string $haystack, string $label): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new \RuntimeException(sprintf('%s: %s is missing from %s', $label, $needle, $haystack));
        }
    }

    function reset_api(): void
    {
        Api::$credential_questions = [];
        Api::$connection_tests = [];
        Api::$binding_requests = [];
        Api::$has_credentials = true;
        Api::$connection_result = ['success' => true, 'message' => 'The gateway responded.'];
        Api::$bindings_result = ['errorCode' => '0'];
    }

    // ---------------------------------------------------------------------
    // Connection test: the credential check, and the environment asked about.
    // ---------------------------------------------------------------------

    // Incomplete credentials are answered from the settings, without a request.
    reset_api();
    Api::$has_credentials = false;
    $result = (new Admin())->test_connection('live');

    assert_same(false, $result['success'], 'an empty credential pair fails the test');
    assert_contains('Live API login and password', $result['message'], 'the message names the environment being tested');
    assert_same(['live'], Api::$credential_questions, 'the credentials are read through Api, not from a second copy');
    assert_same([], Api::$connection_tests, 'nothing is sent to BPC before the credentials are saved');

    // The environment reaches Api sanitised, whatever the button posted.
    reset_api();
    (new Admin())->test_connection('LIVE');
    assert_same(['live'], Api::$connection_tests, 'a live test is addressed to the live environment');

    reset_api();
    (new Admin())->test_connection('anything-else');
    assert_same(['sandbox'], Api::$connection_tests, 'an unrecognised environment falls back to the sandbox');

    // ---------------------------------------------------------------------
    // Connection test: each Api verdict becomes one admin message.
    // ---------------------------------------------------------------------

    reset_api();
    $result = (new Admin())->test_connection('live');
    assert_same(true, $result['success'], 'a reachable gateway passes the test');
    assert_contains('Live endpoint is reachable', $result['message'], 'success names the environment that answered');

    // The gateway answered and Api read the answer as a rejection: the admin
    // quotes the gateway's own wording back to the merchant.
    reset_api();
    Api::$connection_result = [
        'success' => false,
        'message' => 'The gateway responded, but the credentials appear to be invalid.',
        'raw' => ['errorCode' => '5', 'errorMessage' => 'Access denied'],
    ];
    $result = (new Admin())->test_connection('sandbox');

    assert_same(false, $result['success'], 'a rejected credential fails the test');
    assert_contains('Sandbox credentials were rejected by BCI: Access denied', $result['message'], 'the gateway wording is quoted');

    // No decoded response means the endpoint was never reached, which is a
    // different thing to say to a merchant than "your credentials are wrong".
    reset_api();
    Api::$connection_result = [
        'success' => false,
        'message' => 'BCI API returned HTTP 502',
    ];
    $result = (new Admin())->test_connection('sandbox');

    assert_same(false, $result['success'], 'an unreachable endpoint fails the test');
    assert_contains('Could not reach the Sandbox BCI endpoint: BCI API returned HTTP 502', $result['message'], 'the transport failure is reported as one');

    // The verdict is Api's. This response would have tripped the admin copy of
    // the authentication heuristic, which no longer exists to disagree.
    reset_api();
    Api::$connection_result = [
        'success' => true,
        'message' => 'The gateway responded.',
        'raw' => ['errorCode' => '2', 'errorMessage' => 'Forbidden operation for this order'],
    ];
    $result = (new Admin())->test_connection('live');

    assert_same(true, $result['success'], 'the admin does not re-judge a response Api accepted');

    // ---------------------------------------------------------------------
    // Subscription readiness: the connection test, then one getBindings call.
    // ---------------------------------------------------------------------

    // A failed connection test is returned as it stands; there is nothing to
    // ask about stored credentials until the credentials themselves work.
    reset_api();
    Api::$has_credentials = false;
    $result = (new Admin())->test_subscription_readiness('sandbox');

    assert_same(false, $result['success'], 'readiness fails when the connection test fails');
    assert_contains('Sandbox API login and password', $result['message'], 'readiness reports the connection failure it hit');
    assert_same([], Api::$binding_requests, 'no bindings are listed while the credentials are unusable');

    // The probe lists bindings for a client id no customer ever gets.
    reset_api();
    $result = (new Admin())->test_subscription_readiness('sandbox');

    assert_same(true, $result['success'], 'a listable binding set reads as ready');
    assert_same([['bci_woo_readiness_test', 'sandbox']], Api::$binding_requests, 'readiness asks Api for the probe client bindings');
    assert_contains('stored credential listing appears available', $result['message'], 'the ready message says what was confirmed');

    // The environment travels through both calls.
    reset_api();
    (new Admin())->test_subscription_readiness('live');
    assert_same(['live'], Api::$connection_tests, 'readiness tests the connection of the environment it was asked about');
    assert_same([['bci_woo_readiness_test', 'live']], Api::$binding_requests, 'and lists that environment\'s bindings');

    // A refused listing is a definite answer: the merchant has to ask BCI.
    reset_api();
    Api::$bindings_result = ['errorCode' => '5', 'errorMessage' => 'Stored credential is not allowed for this merchant'];
    $result = (new Admin())->test_subscription_readiness('sandbox');

    assert_same(false, $result['success'], 'a refused binding listing fails readiness');
    assert_contains('stored credentials are not enabled', $result['message'], 'the failure says which permission to request');

    // A transport failure is not a verdict on the merchant account.
    reset_api();
    Api::$bindings_result = new \WP_Error('bci_woo_http_error', 'BCI API returned HTTP 500');
    $result = (new Admin())->test_subscription_readiness('sandbox');

    assert_same(true, $result['success'], 'a transport failure leaves the working credentials standing');
    assert_same('warning', $result['severity'], 'but readiness itself is unconfirmed');

    // An answer nobody recognises is also unconfirmed rather than a pass or fail.
    reset_api();
    Api::$bindings_result = ['errorCode' => '7', 'errorMessage' => 'System error'];
    $result = (new Admin())->test_subscription_readiness('sandbox');

    assert_same(true, $result['success'], 'an unrecognised answer leaves the working credentials standing');
    assert_same('warning', $result['severity'], 'and is reported as unconfirmed');

    // ---------------------------------------------------------------------
    // Everything above ran without WordPress HTTP existing at all.
    // ---------------------------------------------------------------------

    assert_same(false, function_exists('wp_remote_post'), 'the admin verdicts above were reached with no HTTP function in the process');

    foreach (
        [
            'gateway_request',
            'credentials_for_environment',
            'api_base_url',
            'settings_string',
            'is_authentication_error',
        ] as $forked
    ) {
        assert_same(false, method_exists(Admin::class, $forked), sprintf('Admin no longer carries its own %s()', $forked));
    }

    // ---------------------------------------------------------------------
    // One settings schema, and one renderer per field type.
    // ---------------------------------------------------------------------

    foreach (['default_form_fields', 'get_form_fields', 'filter_gateway_form_fields'] as $schema) {
        assert_same(false, method_exists(Admin::class, $schema), sprintf('Admin no longer carries a second settings schema via %s()', $schema));
    }

    $gateway = (new \ReflectionClass(Gateway::class))->newInstanceWithoutConstructor();
    $gateway->init_form_fields();

    // WC_Settings_API dispatches generate_{type}_html on the gateway instance, so
    // a renderer that is not on the gateway is never reached for a declared type.
    $custom_types = [];
    foreach ($gateway->form_fields as $field) {
        $type = (string) ($field['type'] ?? '');
        if (strpos($type, 'bci_') === 0) {
            $custom_types[$type] = true;
        }
    }

    assert_same(true, $custom_types !== [], 'the gateway schema declares custom field types');

    foreach (array_keys($custom_types) as $type) {
        assert_same(
            true,
            method_exists(Gateway::class, 'generate_' . $type . '_html'),
            sprintf('the gateway renders its own %s field', $type)
        );
    }

    // What is left on Admin is only what the gateway hands to it by name:
    // admin_options() prints the guided setup, and the gateway's readiness
    // renderer forwards to Admin's.
    $admin_renderers = array_values(array_filter(
        get_class_methods(Admin::class),
        static fn(string $method): bool => preg_match('/^generate_bci_.+_html$/', $method) === 1
    ));
    sort($admin_renderers);

    assert_same(
        ['generate_bci_guided_setup_html', 'generate_bci_subscription_readiness_html'],
        $admin_renderers,
        'Admin renders only the two panels the gateway delegates to it'
    );

    // The surviving schema really does produce the keys the manual check reads.
    foreach (['pending_threshold_minutes', 'failed_lookback_minutes'] as $key) {
        assert_same(true, isset($gateway->form_fields[$key]), sprintf('the one settings schema produces %s', $key));
    }

    echo "Admin to Api routing tests passed.\n";
}
