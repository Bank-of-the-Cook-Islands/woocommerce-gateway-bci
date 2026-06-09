<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(): void
    {
        add_action('plugins_loaded', [$this, 'init'], 11);
        add_action('woocommerce_blocks_loaded', [$this, 'register_blocks_support']);
    }

    public function init(): void
    {
        load_plugin_textdomain(
            Config::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(BCI_WOO_PLUGIN_FILE)) . '/languages'
        );

        $this->require_foundation_files();

        if (!class_exists('\WooCommerce') || !class_exists('\WC_Payment_Gateway')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        require_once Config::plugin_path('includes/class-gateway.php');
        $this->require_optional_files();

        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);

        if (class_exists(__NAMESPACE__ . '\Callback')) {
            Callback::register();
        }

        if (class_exists(__NAMESPACE__ . '\Scheduler')) {
            Scheduler::register();
        }

        if (is_admin() && class_exists(__NAMESPACE__ . '\Admin')) {
            Admin::init();
        }

        if (class_exists(__NAMESPACE__ . '\Subscriptions')) {
            (new Subscriptions())->register_hooks();
        }
    }

    public function add_gateway(array $gateways): array
    {
        $gateways[] = Gateway::class;
        return $gateways;
    }

    public function register_blocks_support(): void
    {
        if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        $file = Config::plugin_path('includes/blocks/class-blocks-support.php');
        if (!file_exists($file)) {
            return;
        }

        require_once $file;

        if (!class_exists(__NAMESPACE__ . '\Blocks_Support')) {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry): void {
                $registry->register(new Blocks_Support());
            }
        );
    }

    public function woocommerce_missing_notice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>'
            . esc_html__('TakuEcom - BCI Payments for WooCommerce requires WooCommerce to be installed and active.', Config::TEXT_DOMAIN)
            . '</p></div>';
    }

    private function require_foundation_files(): void
    {
        require_once Config::plugin_path('includes/class-log.php');
        require_once Config::plugin_path('includes/class-exception.php');
        require_once Config::plugin_path('includes/class-api.php');
        require_once Config::plugin_path('includes/class-status-resolver.php');
    }

    private function require_optional_files(): void
    {
        $files = [
            'includes/class-callback.php',
            'includes/class-scheduler.php',
            'includes/class-admin.php',
            'includes/class-tokens.php',
            'includes/class-renewals.php',
            'includes/class-subscriptions.php',
        ];

        foreach ($files as $file) {
            $path = Config::plugin_path($file);
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
