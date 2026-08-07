<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Config
{
    public const VERSION = '1.0.2';
    public const TEXT_DOMAIN = 'bci-woo';
    public const GATEWAY_ID = 'bci_takuecom';
    public const OPTION_KEY = 'woocommerce_bci_takuecom_settings';
    public const LOG_SOURCE = 'BCI_Woo_Plugin';

    public const API_URL_LIVE = 'https://securepayments.bci.co.ck/payment/rest';
    public const API_URL_SANDBOX = 'https://dev.bpcbt.com/payment/rest';
    public const PAYMENT_URL_LIVE = 'https://securepayments.bci.co.ck/payment';
    public const PAYMENT_URL_SANDBOX = 'https://dev.bpcbt.com/payment';

    public const CALLBACK_NAMESPACE = 'bci-woo/v1';
    public const CALLBACK_ROUTE = '/callback';

    public const API_TIMEOUT = 30;
    public const SCHEDULER_BATCH_SIZE = 50;
    public const SCHEDULER_INTERVAL_SECONDS = 300;
    public const PENDING_THRESHOLD_MINUTES = 10;
    public const FAILED_LOOKBACK_MINUTES = 60;
    public const CANCELLATION_HOLD_MAX_MINUTES = 1440;

    public const META_MD_ORDER = '_bci_woo_md_order';
    public const META_ORDER_NUMBER = '_bci_woo_order_number';
    public const META_ENVIRONMENT = '_bci_woo_environment';
    public const META_LAST_STATUS = '_bci_woo_last_status';
    public const META_LAST_ACTION_CODE = '_bci_woo_last_action_code';
    public const META_BINDING_ID = '_bci_woo_binding_id';
    public const META_CLIENT_ID = '_bci_woo_client_id';
    public const META_MASKED_PAN = '_bci_woo_masked_pan';
    public const META_CARD_EXPIRY = '_bci_woo_card_expiry';

    public const STATUS_REGISTERED = 0;
    public const STATUS_AUTHORISED = 1;
    public const STATUS_CAPTURED = 2;
    public const STATUS_AUTH_CANCELLED = 3;
    public const STATUS_REFUNDED = 4;
    public const STATUS_ACS_INITIATED = 5;
    public const STATUS_DECLINED = 6;
    public const STATUS_PENDING = 7;
    public const STATUS_PARTIAL_COMPLETION = 8;

    /**
     * Longest run of digits a card label may keep readable ahead of a mask
     * character, which is the BIN prefix of a 553807******0000 style label.
     */
    private const MASKED_BIN_MAX = 8;

    public static function plugin_url(string $path = ''): string
    {
        $url = plugin_dir_url(BCI_WOO_PLUGIN_FILE);
        return $path === '' ? $url : $url . ltrim($path, '/');
    }

    public static function plugin_path(string $path = ''): string
    {
        return BCI_WOO_PLUGIN_DIR . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * Mask a card label so that no run of digits leaves more than four readable.
     *
     * BPC normally returns an already masked pan, but the raw `pan` field is used
     * as a fallback in several responses, so a value on its way into order meta is
     * never trusted to have been masked upstream. Every run of digits is inspected
     * on its own and masked to its last four whenever it is long enough to be card
     * data, so idempotency follows from how many digits a run exposes rather than
     * from where the last mask character happens to sit: trailing text such as an
     * expiry, a scheme name or stray punctuation cannot buy a raw pan a free pass.
     * A run continues across a separator only into a further four digit group, so
     * a grouped pan stays one run while an appended `12/30` expiry does not join it.
     * A BIN prefix already followed by a mask character is the single exemption,
     * and it is bounded by length so it can never cover a whole pan.
     */
    public static function mask_pan(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/[0-9]+(?:[ .\-][0-9]{4}(?![0-9]))*/',
            static function (array $match) use ($value): string {
                $run = $match[0][0];
                $digit_count = strlen(preg_replace('/\D+/', '', $run));
                if ($digit_count <= 4) {
                    return $run;
                }

                $trailing = substr($value, $match[0][1] + strlen($run));
                if ($digit_count <= self::MASKED_BIN_MAX && preg_match('/^ *[*xX#]/', $trailing) === 1) {
                    return $run;
                }

                $to_mask = $digit_count - 4;
                $seen = 0;
                $masked = '';

                for ($i = 0, $length = strlen($run); $i < $length; $i++) {
                    $character = $run[$i];

                    if ($character >= '0' && $character <= '9') {
                        $seen++;
                        $masked .= $seen <= $to_mask ? '*' : $character;
                        continue;
                    }

                    $masked .= $character;
                }

                return $masked;
            },
            $value,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );
    }
}
