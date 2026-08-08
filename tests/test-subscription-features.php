<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

namespace BCI\Woo {
    require_once dirname(__DIR__) . '/includes/class-config.php';
    require_once dirname(__DIR__) . '/includes/class-tokens.php';
    require_once dirname(__DIR__) . '/includes/class-api.php';

    /**
     * @param mixed $existing
     * @return string|string[]
     */
    function ensure_feature($existing, string $feature)
    {
        $tokens = (new \ReflectionClass(Tokens::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Tokens::class, 'ensure_feature');
        return $method->invoke($tokens, $existing, $feature);
    }

    function form_encode(array $body): string
    {
        $method = new \ReflectionMethod(Api::class, 'form_encode');
        return $method->invoke(null, $body);
    }

    /**
     * Asserts the merged value, and — since only the wire form matters to BPC —
     * the body Api would post for it.
     *
     * @param mixed $existing
     */
    function assert_features(string $expected_body, $existing, string $case): void
    {
        $features = ensure_feature($existing, 'FORCE_CREATE_BINDING');

        if (!is_string($features) && !is_array($features)) {
            throw new \RuntimeException(
                sprintf('%s failed: features must be a string or a list, got %s', $case, gettype($features))
            );
        }

        $actual_body = form_encode(['features' => $features]);

        if ($expected_body !== $actual_body) {
            throw new \RuntimeException(
                sprintf('%s failed: expected %s, got %s', $case, $expected_body, $actual_body)
            );
        }
    }

    assert_features('features=FORCE_CREATE_BINDING', '', 'Empty features gains the binding feature');

    assert_features('features=FORCE_CREATE_BINDING', [], 'Empty array features gains the binding feature');

    assert_features(
        'features=FORCE_CREATE_BINDING',
        'FORCE_CREATE_BINDING',
        'Existing binding feature is not duplicated'
    );

    // Regression: a merchant filter adding another feature used to produce an array
    // that wp_remote_post() encoded as features[0]=...&features[1]=..., which BPC
    // ignores. BPC expects the parameter repeated instead.
    assert_features(
        'features=FORCE_TDS&features=FORCE_CREATE_BINDING',
        'FORCE_TDS',
        'Another feature is kept and the parameter repeated'
    );

    assert_features(
        'features=FORCE_TDS&features=FORCE_SSL&features=FORCE_CREATE_BINDING',
        'FORCE_TDS, FORCE_SSL',
        'Comma separated features are split into repeated parameters'
    );

    assert_features(
        'features=FORCE_TDS&features=FORCE_SSL&features=FORCE_CREATE_BINDING',
        ['FORCE_TDS', 'FORCE_SSL'],
        'Array features are repeated rather than indexed'
    );

    assert_features(
        'features=FORCE_TDS&features=FORCE_CREATE_BINDING',
        ['FORCE_TDS', 'FORCE_CREATE_BINDING'],
        'Array features containing the binding feature are not duplicated'
    );

    assert_features(
        'features=FORCE_TDS&features=FORCE_CREATE_BINDING',
        ['  FORCE_TDS  ', '', 'FORCE_TDS'],
        'Blank and repeated entries are dropped'
    );

    // The rest of the register.do body must still encode exactly as before.
    $body = form_encode([
        'userName' => 'merchant api',
        'amount' => 1000,
        'orderNumber' => 'wc-42',
        'returnUrl' => 'https://example.test/?wc-api=bci_takuecom_return&order_id=42',
        'skipped' => null,
    ]);

    $expected = 'userName=merchant+api&amount=1000&orderNumber=wc-42'
        . '&returnUrl=https%3A%2F%2Fexample.test%2F%3Fwc-api%3Dbci_takuecom_return%26order_id%3D42';

    if ($body !== $expected) {
        throw new \RuntimeException(
            sprintf('Scalar body encoding failed: expected %s, got %s', $expected, $body)
        );
    }

    echo "Subscription features tests passed.\n";
}
