<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
}

namespace BCI\Woo {
    final class Config
    {
        public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
    }

    final class Log
    {
    }

    require dirname(__DIR__) . '/includes/class-api.php';

    // Synthetic placeholder only; never a real card number.
    const RAW_PAN = '5538070000000000';

    function invoke_private(string $method, array $args)
    {
        return (new \ReflectionMethod(Api::class, $method))->invokeArgs(null, $args);
    }

    function assert_true(bool $condition, string $case): void
    {
        if (!$condition) {
            throw new \RuntimeException($case . ' failed.');
        }
    }

    function assert_no_secrets(array $context, array $needles, string $case): void
    {
        $encoded = json_encode($context);
        foreach ($needles as $needle) {
            if (strpos((string) $encoded, $needle) !== false) {
                throw new \RuntimeException(sprintf('%s failed: %s leaked into %s', $case, $needle, $encoded));
            }
        }
    }

    $redacted = invoke_private('redact_log_body', [[
        'userName' => 'merchant-api',
        'password' => 'sekrit',
        'amount' => 1250,
        'orderNumber' => 'wc-123',
        'email' => 'shopper@example.test',
        'billingPayerData' => '{"billingCity":"Avarua"}',
        'orderBundle' => [
            'customerDetails' => [
                'email' => 'shopper@example.test',
                'phone' => '68200000',
            ],
        ],
        'bindingId' => 'binding-123',
    ]]);

    assert_no_secrets(
        $redacted,
        ['sekrit', 'shopper@example.test', 'Avarua', '68200000', 'binding-123'],
        'Request body redaction'
    );
    assert_true($redacted['amount'] === 1250, 'Amount stays loggable');
    assert_true($redacted['orderNumber'] === 'wc-123', 'Order number stays loggable');
    assert_true(is_array($redacted['orderBundle']['customerDetails']), 'Nested structure is preserved');

    // Non-2xx and malformed responses must never put the raw body in the log.
    $status_body = json_encode([
        'errorCode' => '5',
        'errorMessage' => 'Access denied',
        'bindingInfo' => ['bindingId' => 'binding-789', 'clientId' => 'wc_customer_1'],
        'cardAuthInfo' => ['pan' => RAW_PAN, 'cardholderName' => 'A Shopper'],
    ]);

    $context = invoke_private('response_log_context', [(string) $status_body]);
    assert_no_secrets($context, ['binding-789', RAW_PAN, 'A Shopper'], 'Response body redaction');
    assert_true(($context['error_code'] ?? '') === '5', 'Gateway error code is logged');
    assert_true(($context['error_message'] ?? '') === 'Access denied', 'Gateway error message is logged');
    assert_true(($context['response_length'] ?? 0) === strlen((string) $status_body), 'Response length is logged');

    $html = '<html><body>Gateway error for pan ' . RAW_PAN . '</body></html>';
    $html_context = invoke_private('response_log_context', [$html]);
    assert_no_secrets($html_context, [RAW_PAN, 'Gateway error'], 'Malformed response redaction');
    assert_true(($html_context['response_length'] ?? 0) === strlen($html), 'Malformed response length is logged');

    echo "API log redaction tests passed.\n";
}
