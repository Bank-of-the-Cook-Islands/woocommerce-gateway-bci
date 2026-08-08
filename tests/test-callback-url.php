<?php

declare(strict_types=1);

/**
 * Callback::get_callback_url() is the single derivation behind the callback URL
 * a merchant registers with BPC, shown on three admin surfaces. A silent change
 * to the route breaks every configured merchant portal at once, so the exact
 * path is pinned.
 */

namespace {
    define('ABSPATH', __DIR__);

    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }

    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

namespace BCI\Woo {
    require_once dirname(__DIR__) . '/includes/class-config.php';
    require_once dirname(__DIR__) . '/includes/class-callback.php';

    $url = Callback::get_callback_url();
    $expected = 'https://example.test/wp-json/bci-woo/v1/callback';

    if ($url !== $expected) {
        throw new \RuntimeException(sprintf(
            'Callback URL changed: expected %s, got %s — merchants have this registered with BPC',
            $expected,
            $url
        ));
    }

    echo "Callback URL tests passed.\n";
}
