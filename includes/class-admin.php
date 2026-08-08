<?php
/**
 * Admin settings helpers for the BCI TakuEcom WooCommerce gateway.
 *
 * This file intentionally avoids extending WooCommerce classes so it can be
 * loaded before WooCommerce has finished initialising. It answers the settings
 * screen's AJAX actions and renders the two panels the gateway delegates to it;
 * the gateway owns the settings schema and every other field renderer, and Api
 * owns every request these actions make to BPC.
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

	public const AJAX_TEST_CONNECTION          = 'bci_woo_test_connection';
	public const AJAX_CHECK_PENDING_ORDERS     = 'bci_woo_check_pending_orders';
	public const AJAX_TEST_SUBSCRIPTION_READY  = 'bci_woo_test_subscription_readiness';

	/**
	 * Client id the readiness probe lists bindings for. No customer ever gets it,
	 * so the probe asks whether the merchant account may list bindings at all.
	 */
	private const READINESS_CLIENT_ID = 'bci_woo_readiness_test';

	/**
	 * Tracks whether inline admin styles have been printed.
	 *
	 * @var bool
	 */
	private static $styles_printed = false;

	/**
	 * The gateway client, created on first use.
	 *
	 * Admin is instantiated to render a settings row as well as to answer AJAX,
	 * and only the AJAX handlers talk to BPC, so the client is built when one of
	 * them asks for it rather than in the constructor.
	 *
	 * @var Api|null
	 */
	private $api = null;

	/**
	 * Convenience boot method for the main plugin.
	 *
	 * @return self
	 */
	public static function init(): self {
		$admin = new self();
		$admin->register_hooks();

		return $admin;
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
		add_action( 'wp_ajax_' . self::AJAX_CHECK_PENDING_ORDERS, [ $this, 'ajax_check_pending_orders' ] );
		add_action( 'wp_ajax_' . self::AJAX_TEST_SUBSCRIPTION_READY, [ $this, 'ajax_test_subscription_readiness' ] );
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

		$callback_url = Callback::get_callback_url();
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
		$this->verify_ajax_request( self::AJAX_TEST_CONNECTION );

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

		if ( ! Api::has_credentials( $environment ) ) {
			return $this->result(
				false,
				sprintf(
					/* translators: %s: payment environment label. */
					__( '%s API login and password are required before testing.', self::TEXT_DOMAIN ),
					$this->environment_label( $environment )
				)
			);
		}

		$response = $this->api()->test_connection( $environment );

		if ( empty( $response['success'] ) ) {
			// A decoded response came back only if the gateway answered, which is
			// what separates a rejected credential from an endpoint never reached.
			if ( isset( $response['raw'] ) && is_array( $response['raw'] ) ) {
				return $this->result(
					false,
					sprintf(
						/* translators: 1: payment environment label, 2: gateway response message. */
						__( '%1$s credentials were rejected by BCI: %2$s', self::TEXT_DOMAIN ),
						$this->environment_label( $environment ),
						$this->response_message( $response['raw'] )
					),
					$response
				);
			}

			return $this->result(
				false,
				sprintf(
					/* translators: 1: payment environment label, 2: network or gateway error message. */
					__( 'Could not reach the %1$s BCI endpoint: %2$s', self::TEXT_DOMAIN ),
					$this->environment_label( $environment ),
					$this->safe_text( (string) ( $response['message'] ?? '' ) )
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
		$settings = Api::settings();
		$args     = [
			'pending_threshold_minutes' => $this->positive_int(
				$settings['pending_threshold_minutes'] ?? Config::PENDING_THRESHOLD_MINUTES,
				Config::PENDING_THRESHOLD_MINUTES
			),
			'failed_lookback_minutes'   => $this->positive_int(
				$settings['failed_lookback_minutes'] ?? Config::FAILED_LOOKBACK_MINUTES,
				Config::FAILED_LOOKBACK_MINUTES
			),
			'manual'                    => true,
		];

		// The manual check is the scheduled sweep, run now. It is the same sweep,
		// over the same orders, through the same resolution entry point, so it is
		// called rather than discovered.
		$checked = Scheduler::check_pending_orders( $args );

		return $this->result(
			true,
			sprintf(
				/* translators: %d: number of orders checked. */
				_n( 'Checked %d order.', 'Checked %d orders.', $checked, self::TEXT_DOMAIN ),
				$checked
			),
			[
				'checked' => $checked,
			]
		);
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

		$decoded = $this->api()->get_bindings( self::READINESS_CLIENT_ID, $environment );

		if ( ! is_array( $decoded ) ) {
			return $this->result(
				true,
				__( 'Credentials are valid, but stored credential readiness could not be confirmed from the admin test. Complete a sandbox subscription checkout to verify FORCE_CREATE_BINDING and recurrentPayment.do.', self::TEXT_DOMAIN ),
				[
					'severity' => 'warning',
				]
			);
		}

		if ( $this->is_permission_error( $decoded ) ) {
			return $this->result(
				false,
				__( 'Sandbox credentials are valid, but stored credentials are not enabled for this merchant account. Contact BCI support and request stored credential, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permissions.', self::TEXT_DOMAIN ),
				[
					'details' => $decoded,
				]
			);
		}

		if ( $this->is_success_error_code( $decoded ) ) {
			return $this->result(
				true,
				__( 'Credentials are valid and stored credential listing appears available. Complete a sandbox subscription checkout to verify card binding and recurrentPayment.do before going live.', self::TEXT_DOMAIN ),
				[
					'details' => $decoded,
				]
			);
		}

		return $this->result(
			true,
			__( 'Credentials are valid. Stored credential permission could not be fully confirmed from the admin test, so verify with a sandbox subscription checkout.', self::TEXT_DOMAIN ),
			[
				'severity' => 'warning',
				'details'  => $decoded,
			]
		);
	}

	/**
	 * The one client for BPC requests.
	 *
	 * Every endpoint, timeout, credential and error heuristic this class needs
	 * lives behind it, so a gateway change is made in Api and nowhere else.
	 *
	 * @return Api
	 */
	private function api(): Api {
		if ( null === $this->api ) {
			$this->api = new Api();
		}

		return $this->api;
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
	 * Determine whether a response error code means success.
	 *
	 * @param array<string,mixed> $decoded Gateway response.
	 * @return bool
	 */
	private function is_success_error_code( array $decoded ): bool {
		return isset( $decoded['errorCode'] ) && '0' === (string) $decoded['errorCode'];
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
