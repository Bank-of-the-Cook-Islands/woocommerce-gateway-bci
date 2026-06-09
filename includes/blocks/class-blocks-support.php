<?php
/**
 * WooCommerce Checkout Blocks support for BCI TakuEcom.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( AbstractPaymentMethodType::class ) ) {
	return;
}

/**
 * Registers the BCI TakuEcom payment method with WooCommerce Checkout Blocks.
 */
final class Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'bci_takuecom';

	/**
	 * Gateway instance.
	 *
	 * @var object|null
	 */
	private $gateway = null;

	/**
	 * Whether initialize() has been run.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Initialize block integration settings and gateway instance.
	 */
	public function initialize() {
		$this->name        = $this->gateway_id();
		$this->settings    = $this->gateway_settings();
		$this->gateway     = $this->resolve_gateway();
		$this->initialized = true;
	}

	/**
	 * Whether the payment method should be available in Checkout Blocks.
	 *
	 * @return bool
	 */
	public function is_active() {
		$this->ensure_initialized();

		if ( ! is_object( $this->gateway ) || ! method_exists( $this->gateway, 'is_available' ) ) {
			return false;
		}

		try {
			return (bool) $this->gateway->is_available();
		} catch ( \Throwable $exception ) {
			return false;
		}
	}

	/**
	 * Register and return the frontend script handle.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		if ( ! function_exists( 'wp_register_script' ) ) {
			return array();
		}

		$script_url = $this->plugin_url( 'assets/js/frontend/blocks.js' );
		if ( '' === $script_url ) {
			return array();
		}

		$handle       = 'bci-woo-blocks';
		$script_asset = $this->script_asset();

		wp_register_script(
			$handle,
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				$this->text_domain(),
				dirname( $this->plugin_file() ) . '/languages'
			);
		}

		return array( $handle );
	}

	/**
	 * Register and return the editor script handle when supported by WooCommerce.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * Data exposed to assets/js/frontend/blocks.js as bci_takuecom_data.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$this->ensure_initialized();

		return array(
			'title'       => $this->gateway_title(),
			'description' => $this->gateway_description(),
			'logoUrl'     => $this->logo_url(),
			'supports'    => $this->gateway_supports(),
		);
	}

	/**
	 * Ensure gateway data is ready for methods that may be called directly.
	 */
	private function ensure_initialized() {
		if ( ! $this->initialized ) {
			$this->initialize();
		}
	}

	/**
	 * Resolve the configured gateway ID.
	 *
	 * @return string
	 */
	private function gateway_id() {
		return (string) $this->config_constant( 'GATEWAY_ID', 'bci_takuecom' );
	}

	/**
	 * Resolve the plugin text domain.
	 *
	 * @return string
	 */
	private function text_domain() {
		return (string) $this->config_constant( 'TEXT_DOMAIN', 'bci-woo' );
	}

	/**
	 * Resolve the plugin version.
	 *
	 * @return string
	 */
	private function version() {
		return (string) $this->config_constant( 'VERSION', '1.0.0' );
	}

	/**
	 * Resolve a Config class constant with a safe fallback.
	 *
	 * @param string $name    Constant name.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	private function config_constant( $name, $default ) {
		$constant = __NAMESPACE__ . '\\Config::' . $name;

		if ( defined( $constant ) ) {
			return constant( $constant );
		}

		return $default;
	}

	/**
	 * Load gateway settings.
	 *
	 * @return array
	 */
	private function gateway_settings() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$settings = get_option( 'woocommerce_' . $this->gateway_id() . '_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Resolve the WooCommerce payment gateway instance.
	 *
	 * @return object|null
	 */
	private function resolve_gateway() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();
		if ( ! is_object( $woocommerce ) ) {
			return null;
		}

		$manager = null;
		if ( method_exists( $woocommerce, 'payment_gateways' ) ) {
			$manager = $woocommerce->payment_gateways();
		} elseif ( isset( $woocommerce->payment_gateways ) ) {
			$manager = $woocommerce->payment_gateways;
		}

		if ( ! is_object( $manager ) || ! method_exists( $manager, 'payment_gateways' ) ) {
			return null;
		}

		$gateways = $manager->payment_gateways();
		if ( ! is_array( $gateways ) || empty( $gateways[ $this->name ] ) ) {
			return null;
		}

		return $gateways[ $this->name ];
	}

	/**
	 * Resolve script asset metadata, using a generated asset file if present.
	 *
	 * @return array
	 */
	private function script_asset() {
		$asset = array(
			'dependencies' => array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-i18n',
				'wp-html-entities',
			),
			'version'      => $this->version(),
		);

		$asset_path = $this->plugin_path( 'assets/js/frontend/blocks.asset.php' );
		if ( file_exists( $asset_path ) ) {
			$generated_asset = require $asset_path;
			if ( is_array( $generated_asset ) ) {
				if ( ! empty( $generated_asset['dependencies'] ) && is_array( $generated_asset['dependencies'] ) ) {
					$asset['dependencies'] = array_values(
						array_unique(
							array_merge( $asset['dependencies'], $generated_asset['dependencies'] )
						)
					);
				}

				if ( ! empty( $generated_asset['version'] ) ) {
					$asset['version'] = (string) $generated_asset['version'];
				}
			}
		}

		return $asset;
	}

	/**
	 * Get the checkout title.
	 *
	 * @return string
	 */
	private function gateway_title() {
		$title = '';

		if ( is_object( $this->gateway ) && method_exists( $this->gateway, 'get_title' ) ) {
			$title = $this->gateway->get_title();
		} elseif ( is_object( $this->gateway ) && isset( $this->gateway->title ) ) {
			$title = $this->gateway->title;
		}

		if ( '' === (string) $title ) {
			$title = $this->setting_value( 'title', $this->__( 'Card (BCI TakuEcom)' ) );
		}

		return $this->clean_text( $title );
	}

	/**
	 * Get the checkout description.
	 *
	 * @return string
	 */
	private function gateway_description() {
		$description = '';

		if ( is_object( $this->gateway ) && method_exists( $this->gateway, 'get_description' ) ) {
			$description = $this->gateway->get_description();
		} elseif ( is_object( $this->gateway ) && isset( $this->gateway->description ) ) {
			$description = $this->gateway->description;
		}

		if ( '' === (string) $description ) {
			$description = $this->setting_value(
				'description',
				$this->__( 'Pay securely by card using BCI TakuEcom.' )
			);
		}

		return $this->clean_text( $description );
	}

	/**
	 * Get supported gateway features.
	 *
	 * @return array
	 */
	private function gateway_supports() {
		if ( ! is_object( $this->gateway ) || empty( $this->gateway->supports ) || ! is_array( $this->gateway->supports ) ) {
			return array();
		}

		$supports = array();

		foreach ( $this->gateway->supports as $feature ) {
			if ( ! is_string( $feature ) || '' === $feature ) {
				continue;
			}

			if ( method_exists( $this->gateway, 'supports' ) ) {
				try {
					if ( ! $this->gateway->supports( $feature ) ) {
						continue;
					}
				} catch ( \Throwable $exception ) {
					continue;
				}
			}

			$supports[] = $feature;
		}

		return array_values( array_unique( $supports ) );
	}

	/**
	 * Get the BCI logo URL.
	 *
	 * @return string
	 */
	private function logo_url() {
		return $this->plugin_url( 'assets/bci-logo.png' );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	private function setting_value( $key, $default = '' ) {
		if ( is_array( $this->settings ) && array_key_exists( $key, $this->settings ) ) {
			return $this->settings[ $key ];
		}

		return $default;
	}

	/**
	 * Translate text if WordPress i18n is available.
	 *
	 * @param string $text Text to translate.
	 * @return string
	 */
	private function __( $text ) {
		if ( function_exists( '__' ) ) {
			return __( $text, $this->text_domain() );
		}

		return $text;
	}

	/**
	 * Strip markup and normalize scalar values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_text( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $value );
		}

		return strip_tags( $value );
	}

	/**
	 * Get an absolute plugin path.
	 *
	 * @param string $path Relative plugin path.
	 * @return string
	 */
	private function plugin_path( $path ) {
		return dirname( __DIR__, 2 ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Get the main plugin file path.
	 *
	 * @return string
	 */
	private function plugin_file() {
		if ( defined( 'BCI_WOO_PLUGIN_FILE' ) && BCI_WOO_PLUGIN_FILE ) {
			return BCI_WOO_PLUGIN_FILE;
		}

		return $this->plugin_path( 'woocommerce-gateway-bci.php' );
	}

	/**
	 * Build a plugin asset URL.
	 *
	 * @param string $path Relative plugin path.
	 * @return string
	 */
	private function plugin_url( $path ) {
		if ( ! function_exists( 'plugins_url' ) ) {
			return '';
		}

		return plugins_url( ltrim( $path, '/' ), $this->plugin_file() );
	}
}
