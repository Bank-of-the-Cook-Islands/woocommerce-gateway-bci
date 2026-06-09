<?php
/**
 * Plugin Name: TakuEcom - BCI Payments for WooCommerce
 * Description: Official Bank of the Cook Islands TakuEcom payment gateway for WooCommerce.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Bank of the Cook Islands
 * Author URI: https://bci.co.ck/
 * Text Domain: bci-woo
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 4.0
 * WC tested up to: 8.9.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BCI_WOO_PLUGIN_FILE', __FILE__);
define('BCI_WOO_PLUGIN_DIR', __DIR__);

require_once BCI_WOO_PLUGIN_DIR . '/includes/class-config.php';
require_once BCI_WOO_PLUGIN_DIR . '/includes/class-plugin.php';

add_action('before_woocommerce_init', static function (): void {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            BCI_WOO_PLUGIN_FILE,
            true
        );
    }
});

\BCI\Woo\Plugin::instance()->register();

register_deactivation_hook(BCI_WOO_PLUGIN_FILE, static function (): void {
    $scheduler_file = BCI_WOO_PLUGIN_DIR . '/includes/class-scheduler.php';
    if (!class_exists('\BCI\Woo\Scheduler') && file_exists($scheduler_file)) {
        require_once $scheduler_file;
    }

    if (class_exists('\BCI\Woo\Scheduler')) {
        \BCI\Woo\Scheduler::deactivate();
    }
});
