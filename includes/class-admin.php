<?php
/**
 * Admin settings helpers for the BCI TakuEcom WooCommerce gateway.
 *
 * This file intentionally avoids extending WooCommerce classes so it can be
 * loaded before WooCommerce has finished initialising. The gateway can delegate
 * settings fields and custom field rendering to this class once it is ready.
 *
 * @package BCI\Woo
 */

namespace BCI\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Admin/settings UI and AJAX handlers.
 */
final class Admin {
	public const TEXT_DOMAIN = 'bci-woo';
	public const GATEWAY_ID  = 'bci_takuecom';
	public const OPTION_KEY  = 'woocommerce_bci_takuecom_settings';

	public const API_URL_LIVE    = 'https://securepayments.bci.co.ck/payment/rest';
	public const API_URL_SANDBOX = 'https://dev.bpcbt.com/payment/rest';
	public const API_TIMEOUT     = 30;

	public const AJAX_TEST_CONNECTION          = 'bci_woo_connection_test';
	public const AJAX_TEST_CONNECTION_LEGACY   = 'bci_woo_test_connection';
	public const AJAX_CHECK_PENDING_ORDERS     = 'bci_woo_check_pending_orders';
	public const AJAX_TEST_SUBSCRIPTION_READY  = 'bci_woo_test_subscription_readiness';
	public const MANUAL_PENDING_ORDERS_FILTER  = 'bci_woo_manual_pending_order_check_result';
	public const PENDING_ORDERS_CALLBACK_FILTER = 'bci_woo_pending_orders_check_callback';
	public const SCHEDULER_HOOK                = 'bci_woo_check_pending_orders';

	private const DEFAULT_PENDING_THRESHOLD_MINUTES = 10;
	private const DEFAULT_FAILED_LOOKBACK_MINUTES   = 60;

	/**
	 * Optional callback supplied by the main plugin or scheduler.
	 *
	 * @var callable|null
	 */
	private $pending_orders_callback;

	/**
	 * Tracks whether inline admin styles have been printed.
	 *
	 * @var bool
	 */
	private static $styles_printed = false;

	/**
	 * @param callable|null $pending_orders_callback Optional manual pending-order checker.
	 */
	public function __construct( $pending_orders_callback = null ) {
		if ( is_callable( $pending_orders_callback ) ) {
			$this->pending_orders_callback = $pending_orders_callback;
		}
	}

	/**
	 * Convenience boot method for the main plugin.
	 *
	 * @param callable|null $pending_orders_callback Optional manual pending-order checker.
	 * @return self
	 */
	public static function init( $pending_orders_callback = null ): self {
		$admin = new self( $pending_orders_callback );
		$admin->register_hooks();

		return $admin;
	}

	/**
	 * Static registration entry point used by the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::init();
	}

	/**
	 * Register AJAX hooks. Safe to call even if WooCommerce classes are absent.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'wp_ajax_' . self::AJAX_TEST_CONNECTION, [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_' . self::AJAX_TEST_CONNECTION_LEGACY, [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_' . self::AJAX_CHECK_PENDING_ORDERS, [ $this, 'ajax_check_pending_orders' ] );
		add_action( 'wp_ajax_' . self::AJAX_TEST_SUBSCRIPTION_READY, [ $this, 'ajax_test_subscription_readiness' ] );
	}

	/**
	 * Return the gateway form fields expected by WC_Settings_API.
	 *
	 * The future gateway class can use:
	 * $this->form_fields = $admin->get_form_fields();
	 *
	 * @param array<string,array<string,mixed>> $existing Existing fields to override defaults.
	 * @return array<string,array<string,mixed>>
	 */
	public function get_form_fields( array $existing = [] ): array {
		return array_replace( $this->default_form_fields(), $existing );
	}

	/**
	 * Optional filter callback for woocommerce_settings_api_form_fields_bci_takuecom.
	 *
	 * @param array<string,array<string,mixed>> $fields Gateway fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function filter_gateway_form_fields( array $fields ): array {
		return $this->get_form_fields( $fields );
	}

	/**
	 * Render guided setup before the gateway settings table.
	 *
	 * The current Gateway::admin_options() calls this statically.
	 *
	 * @return void
	 */
	public static function render_guided_setup(): void {
		$admin = new self();

		echo '<table class="form-table bci-woo-guided-setup-table">';
		echo $admin->generate_bci_guided_setup_html( 'guided_setup', [ 'title' => __( 'Guided setup', self::TEXT_DOMAIN ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</table>';
	}

	/**
	 * Custom field renderer dispatcher for gateway delegation.
	 *
	 * @param string               $key Field key.
	 * @param array<string,mixed>  $data Field data.
	 * @param object|null          $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_custom_field_html( string $key, array $data, $gateway = null ): string {
		$type   = isset( $data['type'] ) ? (string) $data['type'] : '';
		$method = 'generate_' . $type . '_html';

		if ( method_exists( $this, $method ) ) {
			return (string) $this->{$method}( $key, $data, $gateway );
		}

		return '';
	}

	/**
	 * Render the guided setup panel.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_guided_setup_html( string $key, array $data = [], $gateway = null ): string {
		unset( $key, $gateway );

		$callback_url = $this->get_callback_url();
		$docs_url     = $this->get_setup_guide_url();

		ob_start();
		?>
		<?php echo $this->admin_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<tr valign="top" class="bci-woo-guided-setup-row">
			<th scope="row" class="titledesc">
				<?php echo esc_html( $data['title'] ?? __( 'Guided setup', self::TEXT_DOMAIN ) ); ?>
			</th>
			<td class="forminp">
				<div class="bci-woo-admin-panel">
					<p class="bci-woo-lead">
						<?php esc_html_e( 'Follow this guide and save the settings before using the test buttons.', self::TEXT_DOMAIN ); ?>
					</p>

					<div class="bci-woo-grid">
						<div class="bci-woo-card">
							<h3><?php esc_html_e( 'Credentials', self::TEXT_DOMAIN ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Enter the API login and API password supplied for live payments.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Enter the separate sandbox API login and password for test payments.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Enable Use sandbox credentials and endpoint while testing. Sandbox payments default to EUR.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Keep Sandbox Currency aligned with the currency selected in the BPC Dev Merchant Portal.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Untick Use sandbox credentials and endpoint only after confirming that you\'re ready to start receiving live payments.', self::TEXT_DOMAIN ); ?></li>
							</ol>
						</div>

						<div class="bci-woo-card">
							<h3><?php esc_html_e( 'Callback notifications', self::TEXT_DOMAIN ); ?></h3>
							<ol>
								<li>
									<a href="https://merchantportal.bci.co.ck/admin/settings/callbackNotifications" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Open Callback notifications in the merchant portal', self::TEXT_DOMAIN ); ?>
									</a>.
								</li>
								<li><?php esc_html_e( 'Enable callback notifications.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Set Callback type to Static.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Set Method to POST. GET is accepted, but POST is recommended.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Set Signing type to Symmetric.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Generate a callback token and paste it into the matching live or sandbox token field.', self::TEXT_DOMAIN ); ?></li>
								<li><?php esc_html_e( 'Tick Deposited, Approved, Reversed, Refunded, and Declined by timeout.', self::TEXT_DOMAIN ); ?></li>
							</ol>
						</div>

						<div class="bci-woo-card">
							<h3><?php esc_html_e( 'Callback URL', self::TEXT_DOMAIN ); ?></h3>
							<p><?php esc_html_e( 'Use this same callback URL for live and sandbox accounts.', self::TEXT_DOMAIN ); ?></p>
							<code class="bci-woo-copyable"><?php echo esc_html( $callback_url ); ?></code>
						</div>

						<div class="bci-woo-card">
							<h3><?php esc_html_e( 'Subscriptions', self::TEXT_DOMAIN ); ?></h3>
							<p><?php esc_html_e( 'For WooCommerce Subscriptions, ask BCI support to enable stored credentials, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permission if required.', self::TEXT_DOMAIN ); ?></p>
							<p><?php esc_html_e( 'Binding created and binding activity changed callback events are optional for one-off payments, but may help support stored credential diagnostics later.', self::TEXT_DOMAIN ); ?></p>
						</div>
					</div>

					<?php if ( '' !== $docs_url ) : ?>
						<p class="bci-woo-docs-link">
							<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Open the merchant setup guide', self::TEXT_DOMAIN ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render callback URL display field.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_callback_url_html( string $key, array $data = [], $gateway = null ): string {
		unset( $key, $gateway );

		$html  = '<p>' . esc_html__( 'Paste this URL into the BCI merchant portal callback notification Link field.', self::TEXT_DOMAIN ) . '</p>';
		$html .= '<code class="bci-woo-copyable">' . esc_html( $this->get_callback_url() ) . '</code>';
		$html .= '<p class="description">' . esc_html__( 'Use symmetric HMAC-SHA256 signing and paste the generated callback token into the matching token field.', self::TEXT_DOMAIN ) . '</p>';

		return $this->field_row(
			(string) ( $data['title'] ?? __( 'Callback URL', self::TEXT_DOMAIN ) ),
			$html,
			'bci_callback_url'
		);
	}

	/**
	 * Render a connection test button.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_connection_test_html( string $key, array $data = [], $gateway = null ): string {
		unset( $gateway );

		$environment = $this->sanitize_environment( $data['environment'] ?? 'live' );
		$button_id   = 'bci-woo-test-connection-' . $environment;
		$status_id   = $button_id . '-status';
		$label       = sprintf(
			/* translators: %s: payment environment label. */
			__( 'Test %s connection', self::TEXT_DOMAIN ),
			$this->environment_label( $environment )
		);

		$html  = '<button type="button" class="button" id="' . esc_attr( $button_id ) . '">' . esc_html( $label ) . '</button>';
		$html .= '<span class="bci-woo-ajax-status" id="' . esc_attr( $status_id ) . '" aria-live="polite"></span>';
		$html .= '<p class="description">' . esc_html__( 'Sends a harmless order status request to BCI using the saved credentials. Save changes first.', self::TEXT_DOMAIN ) . '</p>';
		$html .= $this->ajax_button_script(
			$button_id,
			$status_id,
			[
				'action'      => self::AJAX_TEST_CONNECTION,
				'nonce'       => $this->create_nonce( self::AJAX_TEST_CONNECTION ),
				'environment' => $environment,
				'waitingText' => __( 'Testing...', self::TEXT_DOMAIN ),
			]
		);

		return $this->field_row(
			(string) ( $data['title'] ?? __( 'Connection test', self::TEXT_DOMAIN ) ),
			$html,
			'bci_connection_test_' . $key
		);
	}

	/**
	 * Render the manual pending-orders button.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_pending_orders_html( string $key, array $data = [], $gateway = null ): string {
		unset( $key, $gateway );

		$button_id = 'bci-woo-check-pending-orders';
		$status_id = $button_id . '-status';

		$html  = '<button type="button" class="button" id="' . esc_attr( $button_id ) . '">' . esc_html__( 'Check pending orders', self::TEXT_DOMAIN ) . '</button>';
		$html .= '<span class="bci-woo-ajax-status" id="' . esc_attr( $status_id ) . '" aria-live="polite"></span>';
		$html .= '<p class="description">' . esc_html__( 'Manually checks older pending BCI orders and recently failed BCI orders against the gateway.', self::TEXT_DOMAIN ) . '</p>';
		$html .= $this->ajax_button_script(
			$button_id,
			$status_id,
			[
				'action'      => self::AJAX_CHECK_PENDING_ORDERS,
				'nonce'       => $this->create_nonce( self::AJAX_CHECK_PENDING_ORDERS ),
				'waitingText' => __( 'Checking...', self::TEXT_DOMAIN ),
			]
		);

		return $this->field_row(
			(string) ( $data['title'] ?? __( 'Pending orders', self::TEXT_DOMAIN ) ),
			$html,
			'bci_pending_orders'
		);
	}

	/**
	 * Backwards-compatible renderer for the gateway's existing custom field type.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_check_pending_html( string $key, array $data = [], $gateway = null ): string {
		return $this->generate_bci_pending_orders_html( $key, $data, $gateway );
	}

	/**
	 * Render subscription readiness test button.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $data Field data.
	 * @param object|null         $gateway Optional gateway instance.
	 * @return string
	 */
	public function generate_bci_subscription_readiness_html( string $key, array $data = [], $gateway = null ): string {
		unset( $key, $gateway );

		$environment = $this->sanitize_environment( $data['environment'] ?? 'sandbox' );
		$button_id   = 'bci-woo-test-subscription-readiness';
		$status_id   = $button_id . '-status';

		$html  = '<button type="button" class="button" id="' . esc_attr( $button_id ) . '">' . esc_html__( 'Test subscription readiness', self::TEXT_DOMAIN ) . '</button>';
		$html .= '<span class="bci-woo-ajax-status" id="' . esc_attr( $status_id ) . '" aria-live="polite"></span>';
		$html .= '<p class="description">' . esc_html__( 'Checks credentials and whether stored credential access appears available. It does not collect card details or charge a card.', self::TEXT_DOMAIN ) . '</p>';
		$html .= $this->ajax_button_script(
			$button_id,
			$status_id,
			[
				'action'      => self::AJAX_TEST_SUBSCRIPTION_READY,
				'nonce'       => $this->create_nonce( self::AJAX_TEST_SUBSCRIPTION_READY ),
				'environment' => $environment,
				'waitingText' => __( 'Testing...', self::TEXT_DOMAIN ),
			]
		);

		return $this->field_row(
			(string) ( $data['title'] ?? __( 'Subscription readiness', self::TEXT_DOMAIN ) ),
			$html,
			'bci_subscription_readiness'
		);
	}

	/**
	 * AJAX handler for connection tests.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		$this->verify_ajax_request( [ self::AJAX_TEST_CONNECTION, self::AJAX_TEST_CONNECTION_LEGACY ] );

		$environment = $this->posted_environment( 'live' );
		$result      = $this->test_connection( $environment );

		$this->send_ajax_result( $result );
	}

	/**
	 * AJAX handler for manual pending-order checks.
	 *
	 * @return void
	 */
	public function ajax_check_pending_orders(): void {
		$this->verify_ajax_request( self::AJAX_CHECK_PENDING_ORDERS );

		$result = $this->check_pending_orders();

		$this->send_ajax_result( $result );
	}

	/**
	 * AJAX handler for subscription readiness tests.
	 *
	 * @return void
	 */
	public function ajax_test_subscription_readiness(): void {
		$this->verify_ajax_request( self::AJAX_TEST_SUBSCRIPTION_READY );

		$environment = $this->posted_environment( 'sandbox' );
		$result      = $this->test_subscription_readiness( $environment );

		$this->send_ajax_result( $result );
	}

	/**
	 * Test gateway connectivity with saved credentials.
	 *
	 * @param string $environment live or sandbox.
	 * @return array<string,mixed>
	 */
	public function test_connection( string $environment ): array {
		$environment = $this->sanitize_environment( $environment );
		$credentials = $this->credentials_for_environment( $environment );

		if ( '' === $credentials['userName'] || '' === $credentials['password'] ) {
			return $this->result(
				false,
				sprintf(
					/* translators: %s: payment environment label. */
					__( '%s API login and password are required before testing.', self::TEXT_DOMAIN ),
					$this->environment_label( $environment )
				)
			);
		}

		$response = $this->gateway_request(
			$environment,
			'getOrderStatusExtended.do',
			[
				'orderId'  => 'BCI-WOO-CONNECTION-TEST',
				'language' => 'en',
			]
		);

		if ( ! $response['success'] ) {
			return $response;
		}

		$decoded = $response['data'];
		if ( $this->is_authentication_error( $decoded ) ) {
			return $this->result(
				false,
				sprintf(
					/* translators: 1: payment environment label, 2: gateway response message. */
					__( '%1$s credentials were rejected by BCI: %2$s', self::TEXT_DOMAIN ),
					$this->environment_label( $environment ),
					$this->response_message( $decoded )
				),
				$response
			);
		}

		return $this->result(
			true,
			sprintf(
				/* translators: %s: payment environment label. */
				__( '%s endpoint is reachable and returned a valid gateway response.', self::TEXT_DOMAIN ),
				$this->environment_label( $environment )
			),
			$response
		);
	}

	/**
	 * Run the manual pending-order checker.
	 *
	 * @return array<string,mixed>
	 */
	public function check_pending_orders(): array {
		$settings = $this->get_settings();
		$args     = [
			'pending_threshold_minutes' => $this->positive_int(
				$settings['pending_threshold_minutes'] ?? self::DEFAULT_PENDING_THRESHOLD_MINUTES,
				self::DEFAULT_PENDING_THRESHOLD_MINUTES
			),
			'failed_lookback_minutes'   => $this->positive_int(
				$settings['failed_lookback_minutes'] ?? $settings['failed_recovery_lookback_minutes'] ?? self::DEFAULT_FAILED_LOOKBACK_MINUTES,
				self::DEFAULT_FAILED_LOOKBACK_MINUTES
			),
			'manual'                    => true,
		];

		$result = null;
		if ( function_exists( 'apply_filters' ) ) {
			$result = apply_filters( self::MANUAL_PENDING_ORDERS_FILTER, null, $args );
		}

		if ( null === $result && is_callable( $this->pending_orders_callback ) ) {
			$result = call_user_func( $this->pending_orders_callback, $args );
		}

		if ( null === $result && function_exists( 'apply_filters' ) ) {
			$callback = apply_filters( self::PENDING_ORDERS_CALLBACK_FILTER, null, $args );
			if ( is_callable( $callback ) ) {
				$result = call_user_func( $callback, $args );
			}
		}

		if ( null === $result ) {
			$result = $this->call_scheduler_pending_check( $args );
		}

		if ( null === $result && function_exists( 'has_action' ) && has_action( self::SCHEDULER_HOOK ) ) {
			do_action( self::SCHEDULER_HOOK, $args );

			return $this->result(
				true,
				__( 'Pending order check was triggered. The scheduler did not return a count.', self::TEXT_DOMAIN ),
				[
					'checked' => null,
				]
			);
		}

		if ( null === $result ) {
			return $this->result(
				false,
				__( 'The pending-order checker is not available yet. Ensure the scheduler or status resolver is loaded before using this button.', self::TEXT_DOMAIN )
			);
		}

		return $this->normalise_pending_check_result( $result );
	}

	/**
	 * Test subscription readiness without collecting card details.
	 *
	 * @param string $environment live or sandbox.
	 * @return array<string,mixed>
	 */
	public function test_subscription_readiness( string $environment = 'sandbox' ): array {
		$environment = $this->sanitize_environment( $environment );
		$connection  = $this->test_connection( $environment );

		if ( empty( $connection['success'] ) ) {
			return $connection;
		}

		$response = $this->gateway_request(
			$environment,
			'getBindings.do',
			[
				'clientId'    => 'bci_woo_readiness_test',
				'bindingType' => 'R',
				'showExpired' => 'true',
				'language'    => 'en',
			]
		);

		if ( ! $response['success'] ) {
			return $this->result(
				true,
				__( 'Credentials are valid, but stored credential readiness could not be confirmed from the admin test. Complete a sandbox subscription checkout to verify FORCE_CREATE_BINDING and recurrentPayment.do.', self::TEXT_DOMAIN ),
				[
					'severity' => 'warning',
					'details'  => $response,
				]
			);
		}

		$decoded = $response['data'];
		if ( $this->is_permission_error( $decoded ) ) {
			return $this->result(
				false,
				__( 'Sandbox credentials are valid, but stored credentials are not enabled for this merchant account. Contact BCI support and request stored credential, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permissions.', self::TEXT_DOMAIN ),
				$response
			);
		}

		if ( $this->is_success_error_code( $decoded ) ) {
			return $this->result(
				true,
				__( 'Credentials are valid and stored credential listing appears available. Complete a sandbox subscription checkout to verify card binding and recurrentPayment.do before going live.', self::TEXT_DOMAIN ),
				$response
			);
		}

		return $this->result(
			true,
			__( 'Credentials are valid. Stored credential permission could not be fully confirmed from the admin test, so verify with a sandbox subscription checkout.', self::TEXT_DOMAIN ),
			[
				'severity' => 'warning',
				'details'  => $response,
			]
		);
	}

	/**
	 * Get saved settings from the gateway option.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$settings = get_option( self::OPTION_KEY, [] );

		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get the public callback URL.
	 *
	 * @return string
	 */
	public function get_callback_url(): string {
		if ( function_exists( 'rest_url' ) ) {
			return rest_url( 'bci-woo/v1/callback' );
		}

		if ( function_exists( 'home_url' ) ) {
			return home_url( '/wp-json/bci-woo/v1/callback' );
		}

		return '/wp-json/bci-woo/v1/callback';
	}

	/**
	 * Gateway settings option key.
	 *
	 * @return string
	 */
	public function get_option_key(): string {
		return self::OPTION_KEY;
	}

	/**
	 * Default gateway form fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function default_form_fields(): array {
		return [
			'guided_setup'                    => [
				'title' => __( 'Guided setup', self::TEXT_DOMAIN ),
				'type'  => 'bci_guided_setup',
			],
			'gateway_section'                 => [
				'title'       => __( 'Gateway', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => __( 'Configure how BCI TakuEcom appears at checkout.', self::TEXT_DOMAIN ),
			],
			'enabled'                         => [
				'title'   => __( 'Enable gateway', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable BCI TakuEcom card payments', self::TEXT_DOMAIN ),
				'default' => 'no',
			],
			'title'                           => [
				'title'       => __( 'Checkout title', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'default'     => __( 'Card (BCI TakuEcom)', self::TEXT_DOMAIN ),
				'description' => __( 'Shown to customers at checkout.', self::TEXT_DOMAIN ),
				'desc_tip'    => true,
			],
			'description'                     => [
				'title'       => __( 'Checkout description', self::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'default'     => __( 'Pay securely by card using BCI TakuEcom.', self::TEXT_DOMAIN ),
				'description' => __( 'Shown below the checkout title.', self::TEXT_DOMAIN ),
				'desc_tip'    => true,
			],
			'paid_order_status'               => [
				'title'       => __( 'Paid order status behaviour', self::TEXT_DOMAIN ),
				'type'        => 'select',
				'default'     => 'default',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Choose whether WooCommerce decides the paid status or this gateway forces a status after payment_complete().', self::TEXT_DOMAIN ),
				'options'     => [
					'default'    => __( 'WooCommerce default', self::TEXT_DOMAIN ),
					'processing' => __( 'Force Processing', self::TEXT_DOMAIN ),
					'completed'  => __( 'Force Completed', self::TEXT_DOMAIN ),
				],
			],
			'gateway_section_end'             => [
				'type' => 'sectionend',
			],
			'live_section'                    => [
				'title'       => __( 'Live configuration', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => __( 'Live credentials process real payments.', self::TEXT_DOMAIN ),
			],
			'live_api_login'                  => [
				'title'       => __( 'Live API login', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Enter the live API login supplied by BCI.', self::TEXT_DOMAIN ),
			],
			'live_api_password'               => [
				'title'       => __( 'Live API password', self::TEXT_DOMAIN ),
				'type'        => 'password',
				'default'     => '',
				'description' => __( 'Enter the live API password supplied by BCI.', self::TEXT_DOMAIN ),
			],
			'live_callback_token'             => [
				'title'       => __( 'Live callback token', self::TEXT_DOMAIN ),
				'type'        => 'password',
				'default'     => '',
				'description' => __( 'Generate this token in the live BCI merchant portal callback notification settings.', self::TEXT_DOMAIN ),
			],
			'callback_url'                    => [
				'title' => __( 'Callback URL', self::TEXT_DOMAIN ),
				'type'  => 'bci_callback_url',
			],
			'live_connection_test'            => [
				'title'       => __( 'Live connection test', self::TEXT_DOMAIN ),
				'type'        => 'bci_connection_test',
				'environment' => 'live',
			],
			'live_section_end'                => [
				'type' => 'sectionend',
			],
			'sandbox_section'                 => [
				'title'       => __( 'Sandbox configuration', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => __( 'Sandbox credentials process test payments only.', self::TEXT_DOMAIN ),
			],
			'test_mode'                       => [
				'title'       => __( 'Test Mode', self::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Use sandbox credentials and endpoint', self::TEXT_DOMAIN ),
				'default'     => 'yes',
				'description' => __( 'Uses the BPC development environment and its EUR default. Disable only when ready to process live payments.', self::TEXT_DOMAIN ),
			],
			'sandbox_currency'                => [
				'title'       => __( 'Sandbox currency', self::TEXT_DOMAIN ),
				'type'        => 'select',
				'default'     => 'EUR',
				'description' => __( 'The BPC development environment defaults to EUR. Change this only after selecting the matching currency in the BPC Dev Merchant Portal.', self::TEXT_DOMAIN ),
				'options'     => [
					'EUR' => __( 'EUR - Euro (default)', self::TEXT_DOMAIN ),
					'NZD' => __( 'NZD - New Zealand dollar', self::TEXT_DOMAIN ),
				],
			],
			'sandbox_api_login'               => [
				'title'       => __( 'Sandbox API login', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Enter the sandbox API login supplied by BCI.', self::TEXT_DOMAIN ),
			],
			'sandbox_api_password'            => [
				'title'       => __( 'Sandbox API password', self::TEXT_DOMAIN ),
				'type'        => 'password',
				'default'     => '',
				'description' => __( 'Enter the sandbox API password supplied by BCI.', self::TEXT_DOMAIN ),
			],
			'sandbox_callback_token'          => [
				'title'       => __( 'Sandbox callback token', self::TEXT_DOMAIN ),
				'type'        => 'password',
				'default'     => '',
				'description' => __( 'Generate this token in the sandbox BCI merchant portal callback notification settings.', self::TEXT_DOMAIN ),
			],
			'sandbox_connection_test'         => [
				'title'       => __( 'Sandbox connection test', self::TEXT_DOMAIN ),
				'type'        => 'bci_connection_test',
				'environment' => 'sandbox',
			],
			'sandbox_section_end'             => [
				'type' => 'sectionend',
			],
			'recovery_section'                => [
				'title'       => __( 'Callback recovery', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => __( 'These settings control scheduled and manual recovery of orders that were not updated by browser return or callback.', self::TEXT_DOMAIN ),
			],
			'pending_threshold_minutes'       => [
				'title'             => __( 'Pending threshold minutes', self::TEXT_DOMAIN ),
				'type'              => 'number',
				'default'           => self::DEFAULT_PENDING_THRESHOLD_MINUTES,
				'description'       => __( 'Pending BCI orders older than this can be checked by scheduled or manual recovery.', self::TEXT_DOMAIN ),
				'custom_attributes' => [
					'min'  => '1',
					'step' => '1',
				],
			],
			'failed_lookback_minutes'         => [
				'title'             => __( 'Failed recovery lookback minutes', self::TEXT_DOMAIN ),
				'type'              => 'number',
				'default'           => self::DEFAULT_FAILED_LOOKBACK_MINUTES,
				'description'       => __( 'Recently failed BCI orders modified within this window can be checked again to recover premature failures.', self::TEXT_DOMAIN ),
				'custom_attributes' => [
					'min'  => '1',
					'step' => '1',
				],
			],
			'check_pending_orders'            => [
				'title' => __( 'Manual recovery', self::TEXT_DOMAIN ),
				'type'  => 'bci_check_pending',
			],
			'recovery_section_end'            => [
				'type' => 'sectionend',
			],
			'subscriptions_section'           => [
				'title'       => __( 'Subscriptions', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => __( 'Experimental and disabled by default for v1.0. Enable only after BCI confirms stored credential, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permissions for this merchant.', self::TEXT_DOMAIN ),
			],
			'enable_subscriptions'            => [
				'title'       => __( 'Subscription renewals', self::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable experimental automatic renewals when WooCommerce Subscriptions is active', self::TEXT_DOMAIN ),
				'default'     => 'no',
				'description' => __( 'Leave disabled for the v1.0 one-off payments release unless subscription payments have been validated on this BPC merchant account.', self::TEXT_DOMAIN ),
			],
			'renewal_retry_attempts'          => [
				'title'             => __( 'Renewal retry attempts', self::TEXT_DOMAIN ),
				'type'              => 'number',
				'default'           => 0,
				'description'       => __( 'Leave at 0 when WooCommerce Subscriptions native retry rules are in use.', self::TEXT_DOMAIN ),
				'custom_attributes' => [
					'min'  => '0',
					'step' => '1',
				],
			],
			'renewal_retry_interval'          => [
				'title'             => __( 'Renewal retry interval minutes', self::TEXT_DOMAIN ),
				'type'              => 'number',
				'default'           => 60,
				'description'       => __( 'Used only if plugin-managed renewal retries are enabled by the renewal handler.', self::TEXT_DOMAIN ),
				'custom_attributes' => [
					'min'  => '1',
					'step' => '1',
				],
			],
			'subscription_readiness_test'     => [
				'title'       => __( 'Readiness test', self::TEXT_DOMAIN ),
				'type'        => 'bci_subscription_readiness',
				'environment' => 'sandbox',
			],
			'subscriptions_section_end'       => [
				'type' => 'sectionend',
			],
		];
	}

	/**
	 * Make a form-encoded gateway request.
	 *
	 * @param string              $environment live or sandbox.
	 * @param string              $endpoint Endpoint filename.
	 * @param array<string,mixed> $body Request body without credentials.
	 * @return array<string,mixed>
	 */
	private function gateway_request( string $environment, string $endpoint, array $body ): array {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return $this->result( false, __( 'WordPress HTTP functions are not available.', self::TEXT_DOMAIN ) );
		}

		$environment = $this->sanitize_environment( $environment );
		$url         = rtrim( $this->api_base_url( $environment ), '/' ) . '/' . ltrim( $endpoint, '/' );
		$body        = array_merge( $this->credentials_for_environment( $environment ), $body );

		$response = wp_remote_post(
			$url,
			[
				'headers'   => [
					'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
				],
				'body'      => $body,
				'timeout'   => self::API_TIMEOUT,
				'sslverify' => true,
			]
		);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return $this->result(
				false,
				sprintf(
					/* translators: %s: network error message. */
					__( 'Could not reach BCI: %s', self::TEXT_DOMAIN ),
					$this->safe_text( $response->get_error_message() )
				)
			);
		}

		$http_code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		$raw_body  = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';

		if ( $http_code < 200 || $http_code >= 300 ) {
			return $this->result(
				false,
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'BCI returned HTTP %d.', self::TEXT_DOMAIN ),
					$http_code
				),
				[
					'http_code' => $http_code,
				]
			);
		}

		$decoded = json_decode( $raw_body, true );
		if ( ! is_array( $decoded ) ) {
			return $this->result(
				false,
				sprintf(
					/* translators: %s: JSON parser error. */
					__( 'BCI returned invalid JSON: %s', self::TEXT_DOMAIN ),
					json_last_error_msg()
				),
				[
					'http_code' => $http_code,
				]
			);
		}

		return $this->result(
			true,
			__( 'BCI returned a structured response.', self::TEXT_DOMAIN ),
			[
				'http_code' => $http_code,
				'data'      => $decoded,
			]
		);
	}

	/**
	 * Get credentials for an environment.
	 *
	 * @param string $environment live or sandbox.
	 * @return array{userName:string,password:string}
	 */
	private function credentials_for_environment( string $environment ): array {
		$settings    = $this->get_settings();
		$environment = $this->sanitize_environment( $environment );
		$prefix      = 'live' === $environment ? 'live' : 'sandbox';

		return [
			'userName' => $this->settings_string( $settings, $prefix . '_api_login' ),
			'password' => $this->settings_string( $settings, $prefix . '_api_password' ),
		];
	}

	/**
	 * Get the API base URL for an environment.
	 *
	 * @param string $environment live or sandbox.
	 * @return string
	 */
	private function api_base_url( string $environment ): string {
		return 'live' === $this->sanitize_environment( $environment ) ? self::API_URL_LIVE : self::API_URL_SANDBOX;
	}

	/**
	 * Get a string setting.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 * @param string              $key Settings key.
	 * @return string
	 */
	private function settings_string( array $settings, string $key ): string {
		if ( ! isset( $settings[ $key ] ) ) {
			return '';
		}

		$value = $settings[ $key ];
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Verify nonce and capability for AJAX handlers.
	 *
	 * @param string|array<int,string> $nonce_action Nonce action or accepted actions.
	 * @return void
	 */
	private function verify_ajax_request( $nonce_action ): void {
		if ( function_exists( 'check_ajax_referer' ) ) {
			$verified = false;
			foreach ( (array) $nonce_action as $action ) {
				if ( false !== check_ajax_referer( (string) $action, false, false ) ) {
					$verified = true;
					break;
				}
			}

			if ( ! $verified ) {
				if ( function_exists( 'wp_send_json_error' ) ) {
					wp_send_json_error(
						[
							'message' => __( 'The BCI TakuEcom admin request could not be verified. Refresh the settings page and try again.', self::TEXT_DOMAIN ),
						],
						403
					);
				}

				exit;
			}
		}

		if ( ! $this->current_user_can_manage() ) {
			if ( function_exists( 'wp_send_json_error' ) ) {
				wp_send_json_error(
					[
						'message' => __( 'You do not have permission to manage BCI TakuEcom settings.', self::TEXT_DOMAIN ),
					],
					403
				);
			}

			exit;
		}
	}

	/**
	 * Capability check for settings actions.
	 *
	 * @return bool
	 */
	private function current_user_can_manage(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Send an AJAX response.
	 *
	 * @param array<string,mixed> $result Normalised result.
	 * @return void
	 */
	private function send_ajax_result( array $result ): void {
		$success = ! empty( $result['success'] );
		$payload = [
			'message'  => isset( $result['message'] ) ? (string) $result['message'] : '',
			'severity' => isset( $result['severity'] ) ? (string) $result['severity'] : ( $success ? 'success' : 'error' ),
		];

		if ( isset( $result['checked'] ) ) {
			$payload['checked'] = $result['checked'];
		}

		if ( function_exists( 'wp_send_json_success' ) && function_exists( 'wp_send_json_error' ) ) {
			if ( $success ) {
				wp_send_json_success( $payload );
			}

			wp_send_json_error( $payload, 400 );
		}

		exit;
	}

	/**
	 * Build a normalised result array.
	 *
	 * @param bool                $success Whether the operation succeeded.
	 * @param string              $message User-facing message.
	 * @param array<string,mixed> $extra Extra data.
	 * @return array<string,mixed>
	 */
	private function result( bool $success, string $message, array $extra = [] ): array {
		return array_merge(
			$extra,
			[
				'success' => $success,
				'message' => $message,
			]
		);
	}

	/**
	 * Get posted environment.
	 *
	 * @param string $default Default environment.
	 * @return string
	 */
	private function posted_environment( string $default ): string {
		$environment = $default;

		if ( isset( $_POST['environment'] ) ) {
			$environment = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['environment'] ) : $_POST['environment'];
		}

		return $this->sanitize_environment( (string) $environment );
	}

	/**
	 * Sanitize environment value.
	 *
	 * @param mixed $environment Environment value.
	 * @return string
	 */
	private function sanitize_environment( $environment ): string {
		$environment = is_scalar( $environment ) ? strtolower( (string) $environment ) : '';

		return 'live' === $environment ? 'live' : 'sandbox';
	}

	/**
	 * Human-readable environment label.
	 *
	 * @param string $environment live or sandbox.
	 * @return string
	 */
	private function environment_label( string $environment ): string {
		return 'live' === $this->sanitize_environment( $environment )
			? __( 'Live', self::TEXT_DOMAIN )
			: __( 'Sandbox', self::TEXT_DOMAIN );
	}

	/**
	 * Convert a value to a positive integer with fallback.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $fallback Fallback value.
	 * @return int
	 */
	private function positive_int( $value, int $fallback ): int {
		$value = (int) $value;

		return $value > 0 ? $value : $fallback;
	}

	/**
	 * Try known scheduler method names without requiring the class.
	 *
	 * @param array<string,mixed> $args Pending check arguments.
	 * @return mixed|null
	 */
	private function call_scheduler_pending_check( array $args ) {
		$scheduler_class = __NAMESPACE__ . '\\Scheduler';

		if ( ! class_exists( $scheduler_class ) ) {
			return null;
		}

		foreach ( [ 'check_pending_orders', 'checkPendingOrders', 'check_pending_orders_now', 'checkPendingOrdersNow' ] as $method ) {
			if ( is_callable( [ $scheduler_class, $method ] ) ) {
				return call_user_func( [ $scheduler_class, $method ], $args );
			}
		}

		return null;
	}

	/**
	 * Normalise scheduler/callback result into an AJAX payload.
	 *
	 * @param mixed $result Raw result.
	 * @return array<string,mixed>
	 */
	private function normalise_pending_check_result( $result ): array {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return $this->result( false, $this->safe_text( $result->get_error_message() ) );
		}

		if ( is_int( $result ) ) {
			return $this->result(
				true,
				sprintf(
					/* translators: %d: number of orders checked. */
					_n( 'Checked %d order.', 'Checked %d orders.', $result, self::TEXT_DOMAIN ),
					$result
				),
				[
					'checked' => $result,
				]
			);
		}

		if ( is_array( $result ) ) {
			$success = array_key_exists( 'success', $result ) ? (bool) $result['success'] : true;
			$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Pending order check completed.', self::TEXT_DOMAIN );

			if ( ! isset( $result['checked'] ) ) {
				if ( isset( $result['count'] ) ) {
					$result['checked'] = (int) $result['count'];
				} elseif ( isset( $result['orders_checked'] ) ) {
					$result['checked'] = (int) $result['orders_checked'];
				}
			}

			return $this->result( $success, $message, $result );
		}

		if ( true === $result ) {
			return $this->result( true, __( 'Pending order check completed.', self::TEXT_DOMAIN ) );
		}

		return $this->result( false, __( 'Pending order check did not complete.', self::TEXT_DOMAIN ) );
	}

	/**
	 * Determine whether a response error code means success.
	 *
	 * @param array<string,mixed> $decoded Gateway response.
	 * @return bool
	 */
	private function is_success_error_code( array $decoded ): bool {
		return isset( $decoded['errorCode'] ) && '0' === (string) $decoded['errorCode'];
	}

	/**
	 * Heuristic for authentication failures.
	 *
	 * @param array<string,mixed> $decoded Gateway response.
	 * @return bool
	 */
	private function is_authentication_error( array $decoded ): bool {
		$message = strtolower( $this->response_message( $decoded ) );

		foreach ( [ 'authentication', 'authorisation', 'authorization', 'access denied', 'forbidden', 'login', 'password', 'credential' ] as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Heuristic for missing stored credential permissions.
	 *
	 * @param array<string,mixed> $decoded Gateway response.
	 * @return bool
	 */
	private function is_permission_error( array $decoded ): bool {
		$message = strtolower( $this->response_message( $decoded ) );

		foreach ( [ 'permission', 'not permitted', 'not allowed', 'access denied', 'forbidden', 'stored credential', 'binding' ] as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract a concise gateway response message.
	 *
	 * @param array<string,mixed> $decoded Gateway response.
	 * @return string
	 */
	private function response_message( array $decoded ): string {
		foreach ( [ 'errorMessage', 'error', 'message', 'displayErrorMessage', 'actionCodeDescription' ] as $key ) {
			if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) && '' !== (string) $decoded[ $key ] ) {
				return $this->safe_text( (string) $decoded[ $key ] );
			}
		}

		if ( isset( $decoded['errorCode'] ) ) {
			return sprintf(
				/* translators: %s: gateway error code. */
				__( 'Gateway error code %s', self::TEXT_DOMAIN ),
				$this->safe_text( (string) $decoded['errorCode'] )
			);
		}

		return __( 'No message returned.', self::TEXT_DOMAIN );
	}

	/**
	 * Remove markup/control characters from text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function safe_text( string $text ): string {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$text = wp_strip_all_tags( $text );
		} else {
			$text = strip_tags( $text );
		}

		return trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
	}

	/**
	 * Create nonce with fallback for non-WP contexts.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	private function create_nonce( string $action ): string {
		return function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $action ) : '';
	}

	/**
	 * Render a WooCommerce settings table row.
	 *
	 * @param string $title Row title.
	 * @param string $html Row HTML.
	 * @param string $class CSS class suffix.
	 * @return string
	 */
	private function field_row( string $title, string $html, string $class ): string {
		ob_start();
		?>
		<?php echo $this->admin_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<tr valign="top" class="<?php echo esc_attr( 'bci-woo-field-row bci-woo-field-row-' . $class ); ?>">
			<th scope="row" class="titledesc">
				<?php echo esc_html( $title ); ?>
			</th>
			<td class="forminp">
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Inline script for an AJAX button.
	 *
	 * @param string              $button_id Button element ID.
	 * @param string              $status_id Status element ID.
	 * @param array<string,mixed> $config Button config.
	 * @return string
	 */
	private function ajax_button_script( string $button_id, string $status_id, array $config ): string {
		$config = array_merge(
			[
				'ajaxUrl'     => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : 'admin-ajax.php',
				'buttonId'    => $button_id,
				'statusId'    => $status_id,
				'waitingText' => __( 'Working...', self::TEXT_DOMAIN ),
			],
			$config
		);

		ob_start();
		?>
		<script>
			(function(config) {
				var button = document.getElementById(config.buttonId);
				var status = document.getElementById(config.statusId);

				if (!button || !status) {
					return;
				}

				button.addEventListener('click', function() {
					var params = [];
					var key;

					button.disabled = true;
					status.textContent = config.waitingText;
					status.className = 'bci-woo-ajax-status is-working';

					for (key in config) {
						if (!Object.prototype.hasOwnProperty.call(config, key)) {
							continue;
						}

						if (['ajaxUrl', 'buttonId', 'statusId', 'waitingText'].indexOf(key) !== -1) {
							continue;
						}

						params.push(encodeURIComponent(key === 'nonce' ? '_ajax_nonce' : key) + '=' + encodeURIComponent(config[key]));
					}

					var xhr = new XMLHttpRequest();
					xhr.open('POST', config.ajaxUrl);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

					xhr.onload = function() {
						var response;
						var payload;

						button.disabled = false;

						try {
							response = JSON.parse(xhr.responseText);
						} catch (error) {
							status.textContent = 'Invalid response from server.';
							status.className = 'bci-woo-ajax-status is-error';
							return;
						}

						payload = response.data || {};
						status.textContent = payload.message || 'Request completed.';
						status.className = 'bci-woo-ajax-status is-' + (payload.severity || (response.success ? 'success' : 'error'));
					};

					xhr.onerror = function() {
						button.disabled = false;
						status.textContent = 'Network error.';
						status.className = 'bci-woo-ajax-status is-error';
					};

					xhr.send(params.join('&'));
				});
			})(<?php echo wp_json_encode( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>);
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Inline styles for admin helper panels.
	 *
	 * @return string
	 */
	private function admin_styles(): string {
		if ( self::$styles_printed ) {
			return '';
		}

		self::$styles_printed = true;

		return '<style>
			.bci-woo-admin-panel{max-width:960px}
			.bci-woo-lead{font-size:14px;margin:0 0 14px}
			.bci-woo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin:12px 0}
			.bci-woo-card{border:1px solid #dcdcde;background:#fff;padding:14px;border-radius:4px}
			.bci-woo-card h3{margin:0 0 8px;font-size:14px}
			.bci-woo-card ol{margin:0 0 0 18px}
			.bci-woo-card li{margin:0 0 6px}
			.bci-woo-copyable{display:inline-block;max-width:100%;padding:5px 8px;background:#f6f7f7;border:1px solid #dcdcde;white-space:normal;word-break:break-all;user-select:all}
			.bci-woo-ajax-status{display:inline-block;margin-left:10px;vertical-align:middle}
			.bci-woo-ajax-status.is-success{color:#008a20}
			.bci-woo-ajax-status.is-warning{color:#996800}
			.bci-woo-ajax-status.is-error{color:#b32d2e}
			.bci-woo-ajax-status.is-working{color:#50575e}
			.bci-woo-docs-link{margin:12px 0 0}
		</style>';
	}

	/**
	 * Merchant setup guide URL when the main plugin file constant is available.
	 *
	 * @return string
	 */
	private function get_setup_guide_url(): string {
		if ( defined( 'BCI_WOO_PLUGIN_FILE' ) && function_exists( 'plugins_url' ) ) {
			return plugins_url( 'docs/merchant-setup-guide.md', BCI_WOO_PLUGIN_FILE );
		}

		return '';
	}
}
