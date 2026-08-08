<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Log
{
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write('notice', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $context = array_merge([
            'source' => Config::LOG_SOURCE,
            'plugin_version' => Config::VERSION,
        ], $context);

        try {
            wc_get_logger()->log($level, $message, $context);
        } catch (\Throwable $e) {
            // Logging must never break payment processing: a throwing log
            // handler (a DB handler on a failed write, a third-party handler)
            // is swallowed here, in the one place every log line passes
            // through, rather than in per-call-site shims.
        }
    }
}
