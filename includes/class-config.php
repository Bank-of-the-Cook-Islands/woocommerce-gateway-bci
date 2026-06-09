<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Config
{
    public const VERSION = '1.0.0';
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

    public static function plugin_url(string $path = ''): string
    {
        $url = plugin_dir_url(BCI_WOO_PLUGIN_FILE);
        return $path === '' ? $url : $url . ltrim($path, '/');
    }

    public static function plugin_path(string $path = ''): string
    {
        return BCI_WOO_PLUGIN_DIR . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
