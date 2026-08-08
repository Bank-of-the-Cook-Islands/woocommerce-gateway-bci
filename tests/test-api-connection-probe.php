<?php

/**
 * The connection probe and the binding listing, as Api actually sends them.
 *
 * The admin connection test used to build its own request and judge the reply
 * with its own copy of the authentication heuristic; the two copies had drifted
 * to different needle lists over different response fields, so the same reply
 * could be an authentication failure on one screen and a clean pass on another.
 * There is one implementation now, and this pins what it puts on the wire and
 * how it reads what comes back — the union of what the two copies used to catch,
 * without catching an ordinary "no such order" reply.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    /** @var array<int,array{0:string,1:array}> Every request, as [url, args]. */
    $bci_test_requests = [];

    /** @var array<int,mixed> Responses handed back in turn. */
    $bci_test_responses = [];

    function wp_remote_post(string $url, array $args = [])
    {
        global $bci_test_requests, $bci_test_responses;

        $bci_test_requests[] = [$url, $args];

        return array_shift($bci_test_responses) ?? ['code' => 200, 'body' => '{}'];
    }

    function wp_remote_retrieve_response_code($response): int
    {
        return (int) ($response['code'] ?? 0);
    }

    function wp_remote_retrieve_body($response): string
    {
        return (string) ($response['body'] ?? '');
    }

    function wp_json_encode($value): string
    {
        return (string) json_encode($value);
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    $bci_test_settings = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_settings;

        return $bci_test_settings;
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
}

namespace BCI\Woo {
    final class Config
    {
        public const TEXT_DOMAIN = 'bci-woo';
        public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
        public const API_URL_LIVE = 'https://securepayments.bci.co.ck/payment/rest';
        public const API_URL_SANDBOX = 'https://dev.bpcbt.com/payment/rest';
        public const API_TIMEOUT = 30;
    }

    final class Log
    {
        public static function __callStatic(string $level, array $args): void
        {
        }
    }

    require dirname(__DIR__) . '/includes/class-api.php';

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

    /** Queue one gateway reply and forget the previous request log. */
    function queue(int $code, $body): void
    {
        global $bci_test_requests, $bci_test_responses;

        $bci_test_requests = [];
        $bci_test_responses = [['code' => $code, 'body' => is_string($body) ? $body : (string) json_encode($body)]];
    }

    function queue_error(): void
    {
        global $bci_test_requests, $bci_test_responses;

        $bci_test_requests = [];
        $bci_test_responses = [new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out')];
    }

    /** @return array{0:string,1:array<string,string>} The URL and decoded body of the one request sent. */
    function sent_request(): array
    {
        global $bci_test_requests;

        assert_same(1, count($bci_test_requests), 'exactly one request was sent');

        $body = [];
        parse_str((string) ($bci_test_requests[0][1]['body'] ?? ''), $body);

        return [$bci_test_requests[0][0], $body];
    }

    global $bci_test_settings;
    $bci_test_settings = [
        'live_api_login' => 'live-merchant',
        'live_api_password' => 'live-secret',
        'sandbox_api_login' => 'sandbox-merchant',
        'sandbox_api_password' => 'sandbox-secret',
    ];

    $api = new Api();

    // ---------------------------------------------------------------------
    // getBindings.do: the call the admin readiness probe needed.
    // ---------------------------------------------------------------------

    queue(200, ['errorCode' => '0', 'bindings' => []]);
    $result = $api->get_bindings('bci_woo_readiness_test', 'sandbox');

    [$url, $body] = sent_request();
    assert_same('https://dev.bpcbt.com/payment/rest/getBindings.do', $url, 'a sandbox listing goes to the sandbox host');
    assert_same('sandbox-merchant', $body['userName'], 'with the sandbox credentials');
    assert_same('sandbox-secret', $body['password'], 'with the sandbox password');
    assert_same('bci_woo_readiness_test', $body['clientId'], 'for the client id it was asked about');
    assert_same('R', $body['bindingType'], 'recurring bindings are the ones asked for');
    assert_same('true', $body['showExpired'], 'expired bindings count, because the question is about permission');
    assert_same(['errorCode' => '0', 'bindings' => []], $result, 'the decoded gateway response is returned');

    queue(200, ['errorCode' => '0']);
    $api->get_bindings('bci_woo_readiness_test', 'live');

    [$url, $body] = sent_request();
    assert_same('https://securepayments.bci.co.ck/payment/rest/getBindings.do', $url, 'a live listing goes to the live host');
    assert_same('live-merchant', $body['userName'], 'with the live credentials');

    // ---------------------------------------------------------------------
    // test_connection: what it sends, and the three verdicts it returns.
    // ---------------------------------------------------------------------

    queue(200, ['errorCode' => '6', 'errorMessage' => 'Order not found']);
    $result = $api->test_connection('live');

    [$url, $body] = sent_request();
    assert_same('https://securepayments.bci.co.ck/payment/rest/getOrderStatusExtended.do', $url, 'the probe asks for an order status');
    assert_same('live-merchant', $body['userName'], 'signed with the credentials of the environment under test');
    assert_same(true, isset($body['orderId']), 'about an order id that cannot exist');
    // The heuristic below matches English needles only, so the probe has to ask
    // for English. A merchant account answering in its own default language
    // would otherwise have its rejections read as passes.
    assert_same('en', $body['language'], 'and pins the response wording to English, as every other call does');

    assert_same(true, $result['success'], 'an ordinary "no such order" reply means the endpoint and credentials work');
    assert_same(['errorCode' => '6', 'errorMessage' => 'Order not found'], $result['raw'], 'and the reply is passed back for the caller to quote');

    // A gateway that answered is reported with its answer attached, so a caller
    // can tell a rejected credential from an endpoint that was never reached.
    queue(200, ['errorCode' => '5', 'errorMessage' => 'Access denied']);
    $result = $api->test_connection('live');

    assert_same(false, $result['success'], 'a rejected credential fails the probe');
    assert_same(['errorCode' => '5', 'errorMessage' => 'Access denied'], $result['raw'], 'the gateway answer is attached to the failure');

    queue_error();
    $result = $api->test_connection('live');

    assert_same(false, $result['success'], 'a network failure fails the probe');
    assert_same('cURL error 28: Operation timed out', $result['message'], 'and reports the transport error');
    assert_same(false, isset($result['raw']), 'with no gateway answer, because there was none');

    queue(502, 'Bad Gateway');
    $result = $api->test_connection('live');

    assert_same(false, $result['success'], 'a non-2xx response fails the probe');
    assert_same(false, isset($result['raw']), 'and is not a gateway answer either');

    queue(200, '<html>maintenance</html>');
    $result = $api->test_connection('live');

    assert_same(false, $result['success'], 'a response that is not JSON fails the probe');
    assert_same(false, isset($result['raw']), 'and is not a gateway answer either');

    // ---------------------------------------------------------------------
    // The reconciled heuristic: the union of the two copies that used to exist.
    // ---------------------------------------------------------------------

    /** @return bool Whether the probe read this gateway reply as a credential rejection. */
    function reads_as_auth_failure(Api $api, array $reply): bool
    {
        queue(200, $reply);

        return empty($api->test_connection('live')['success']);
    }

    // Needles both copies carried.
    foreach (
        [
            ['errorMessage' => 'Access denied'],
            ['errorMessage' => 'Authentication failed'],
            ['errorMessage' => 'Wrong login or password'],
            ['error' => 'authorisation error'],
            ['message' => 'Invalid credential supplied'],
        ] as $reply
    ) {
        assert_same(true, reads_as_auth_failure($api, $reply), 'a shared needle still reads as a credential rejection: ' . json_encode($reply));
    }

    // 'forbidden' only ever existed in the admin copy of the needle list.
    assert_same(true, reads_as_auth_failure($api, ['errorMessage' => 'Forbidden']), 'the admin copy\'s extra needle survives the merge');

    // These two fields were only ever read by the admin copy.
    assert_same(true, reads_as_auth_failure($api, ['displayErrorMessage' => 'Access denied']), 'displayErrorMessage is read');
    assert_same(true, reads_as_auth_failure($api, ['actionCodeDescription' => 'Authentication unavailable']), 'actionCodeDescription is read');

    // Replies with nothing to do with credentials must still pass, or the button
    // would tell every merchant with a clean account that their login is wrong.
    foreach (
        [
            ['errorCode' => '6', 'errorMessage' => 'Order not found'],
            ['errorCode' => '0', 'orderStatus' => 2, 'actionCodeDescription' => 'Approved'],
            ['errorCode' => '1', 'errorMessage' => 'Order number is duplicated'],
            [],
        ] as $reply
    ) {
        assert_same(false, reads_as_auth_failure($api, $reply), 'an unrelated reply passes: ' . json_encode($reply));
    }

    // A structured field is not text, and must not derail the scan.
    assert_same(
        false,
        reads_as_auth_failure($api, ['errorCode' => '0', 'message' => ['nested' => 'value']]),
        'a non-scalar field is skipped rather than concatenated'
    );

    echo "API connection probe tests passed.\n";
}
