<?php

declare(strict_types=1);

/**
 * The plugin has three version statements — Config::VERSION, the plugin header,
 * and readme.txt's Stable tag — and nothing but discipline kept them equal.
 * Issue #19 found them drifted once already; this pins them to each other so a
 * release bump that misses one fails CI instead of shipping split.
 */

namespace {
    define('ABSPATH', __DIR__);

    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

namespace BCI\Woo {
    require_once dirname(__DIR__) . '/includes/class-config.php';

    function extract_one(string $file, string $pattern, string $label): string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Could not read ' . $file);
        }

        if (!preg_match($pattern, $contents, $matches)) {
            throw new \RuntimeException($label . ' not found in ' . basename($file));
        }

        return trim($matches[1]);
    }

    $root = dirname(__DIR__);

    $header = extract_one(
        $root . '/woocommerce-gateway-bci.php',
        '/^\s*\*\s*Version:\s*(\S+)\s*$/m',
        'Version header'
    );

    $stable_tag = extract_one(
        $root . '/readme.txt',
        '/^Stable tag:\s*(\S+)\s*$/m',
        'Stable tag'
    );

    foreach (['plugin header' => $header, 'readme.txt Stable tag' => $stable_tag] as $label => $found) {
        if ($found !== Config::VERSION) {
            throw new \RuntimeException(sprintf(
                'Version drift: Config::VERSION is %s but the %s says %s',
                Config::VERSION,
                $label,
                $found
            ));
        }
    }

    echo "Version consistency tests passed.\n";
}
