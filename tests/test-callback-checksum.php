<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);
}

namespace BCI\Woo {
    final class Log
    {
        public static array $entries = [];

        public static function notice(string $message, array $context = []): void
        {
            self::$entries[] = ['notice', $message, $context];
        }

        public static function info(string $message, array $context = []): void
        {
            self::$entries[] = ['info', $message, $context];
        }

        public static function error(string $message, array $context = []): void
        {
            self::$entries[] = ['error', $message, $context];
        }
    }

    require dirname(__DIR__) . '/includes/class-callback.php';

    const TOKEN = 'test-callback-token';

    /**
     * Compute a checksum the way BPC does: sort by name, join key;value pairs
     * with semicolons, trailing semicolon, HMAC-SHA256, uppercase hex.
     */
    function bpc_checksum(array $params, string $token): string
    {
        unset($params['checksum'], $params['sign_alias']);
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . ';' . $value;
        }

        return strtoupper(hash_hmac('sha256', implode(';', $parts) . ';', $token));
    }

    function verify(array $params, array $tokens): bool
    {
        $method = new \ReflectionMethod(Callback::class, 'verify_against_tokens');
        $method->setAccessible(true);

        return $method->invoke(null, $params, $tokens);
    }

    function assert_verify(bool $expected, array $params, array $tokens, string $label): void
    {
        if (verify($params, $tokens) !== $expected) {
            throw new \RuntimeException(sprintf('%s: expected %s', $label, var_export($expected, true)));
        }
    }

    $signed = [
        'mdOrder' => '1234567890-098776-234-522',
        'orderNumber' => 'WC38-1752541000',
        'operation' => 'deposited',
        'status' => '1',
        'amount' => '100',
    ];
    $signed['checksum'] = bpc_checksum($signed, TOKEN);

    // A clean BPC notification verifies against the full parameter set.
    assert_verify(true, $signed, [TOKEN], 'clean notification verifies');

    // Parameters injected by the store's stack no longer break verification.
    $injected = $signed + ['utm_source' => 'newsletter', 'waf_token' => 'abc123'];
    Log::$entries = [];
    assert_verify(true, $injected, [TOKEN], 'notification with injected parameters verifies');

    $logged = array_filter(Log::$entries, static fn(array $entry): bool =>
        $entry[0] === 'info' && str_contains($entry[1], 'ignoring non-BPC parameters'));
    if (count($logged) !== 1) {
        throw new \RuntimeException('ignored parameters were not logged');
    }

    // The second token is also accepted (live + sandbox tokens are both configured).
    assert_verify(true, $injected, ['other-token', TOKEN], 'later token in the list verifies');

    // Tampering with a signed value still fails, with or without injected noise.
    $tampered = $injected;
    $tampered['amount'] = '999999';
    assert_verify(false, $tampered, [TOKEN], 'tampered value fails');

    // A tampered BPC-known parameter cannot hide behind the filtered retry.
    $forged = $signed + ['maskedPan' => '999999**0000'];
    assert_verify(false, $forged, [TOKEN], 'unsigned BPC-known parameter fails');

    // Wrong token fails outright.
    assert_verify(false, $injected, ['wrong-token'], 'wrong token fails');

    // Missing checksum fails.
    $unsigned = $signed;
    unset($unsigned['checksum']);
    assert_verify(false, $unsigned, [TOKEN], 'missing checksum fails');

    echo "Callback checksum tests passed.\n";
}
