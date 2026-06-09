<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Gateway extends \WC_Payment_Gateway
{
    private Api $api;
    private Status_Resolver $resolver;

    public function __construct()
    {
        $this->id = Config::GATEWAY_ID;
        $this->icon = Config::plugin_url('assets/bci-logo.png');
        $this->has_fields = false;
        $this->method_title = __('TakuEcom - BCI Payments', Config::TEXT_DOMAIN);
        $this->method_description = __('Accept card payments through BCI TakuEcom.', Config::TEXT_DOMAIN);
        $this->supports = ['products'];

        if (Api::subscriptions_enabled() && class_exists(__NAMESPACE__ . '\Subscriptions')) {
            $this->supports = (new Subscriptions($this))->merge_supports($this->supports);
        }

        $this->api = new Api();
        $this->resolver = new Status_Resolver($this->api);

        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title', __('Card (BCI TakuEcom)', Config::TEXT_DOMAIN));
        $this->description = (string) $this->get_option('description', __('Pay securely by card using BCI TakuEcom.', Config::TEXT_DOMAIN));
        $this->enabled = (string) $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_bci_takuecom_return', [$this, 'handle_return']);

        if (Api::subscriptions_enabled() && class_exists(__NAMESPACE__ . '\Renewals')) {
            (new Renewals($this, $this->api, $this->resolver))->register_hooks();
        }
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', Config::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable TakuEcom - BCI Payments', Config::TEXT_DOMAIN),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Checkout title', Config::TEXT_DOMAIN),
                'type' => 'text',
                'default' => __('Card (BCI TakuEcom)', Config::TEXT_DOMAIN),
                'description' => __('Shown to customers during checkout.', Config::TEXT_DOMAIN),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Checkout description', Config::TEXT_DOMAIN),
                'type' => 'textarea',
                'default' => __('Pay securely by card using BCI TakuEcom.', Config::TEXT_DOMAIN),
                'description' => __('Shown below the payment method title during checkout.', Config::TEXT_DOMAIN),
                'desc_tip' => true,
            ],
            'paid_order_status' => [
                'title' => __('Paid order status', Config::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'default',
                'description' => __('WooCommerce default usually means Processing for shippable orders and Completed for virtual/downloadable orders.', Config::TEXT_DOMAIN),
                'options' => [
                    'default' => __('WooCommerce default', Config::TEXT_DOMAIN),
                    'processing' => __('Force Processing', Config::TEXT_DOMAIN),
                    'completed' => __('Force Completed', Config::TEXT_DOMAIN),
                ],
            ],
            'test_mode' => [
                'title' => __('Test mode', Config::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Use sandbox credentials and endpoint', Config::TEXT_DOMAIN),
                'default' => 'yes',
                'description' => __('Disable this only when BCI has issued live credentials and you are ready to process real payments.', Config::TEXT_DOMAIN),
            ],
            'sandbox_force_eur_currency' => [
                'title' => __('Sandbox currency override', Config::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Force EUR for sandbox payments', Config::TEXT_DOMAIN),
                'default' => 'no',
                'description' => __('Debug setting for BPC testing only. When Test mode is enabled, checkout and renewal requests are sent to BPC as EUR instead of the WooCommerce order currency.', Config::TEXT_DOMAIN),
            ],
            'live_section' => [
                'title' => __('Live Configuration', Config::TEXT_DOMAIN),
                'type' => 'title',
                'description' => __('Use these credentials for real transactions. Real payments are collected only when Test mode is disabled.', Config::TEXT_DOMAIN),
            ],
            'live_api_login' => [
                'title' => __('Live API Login', Config::TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',
                'description' => __('The live merchant API username provided by BCI.', Config::TEXT_DOMAIN),
            ],
            'live_api_password' => [
                'title' => __('Live API Password', Config::TEXT_DOMAIN),
                'type' => 'password',
                'default' => '',
                'description' => __('The live merchant API password provided by BCI.', Config::TEXT_DOMAIN),
            ],
            'live_callback_token' => [
                'title' => __('Live Callback Token', Config::TEXT_DOMAIN),
                'type' => 'password',
                'default' => '',
                'description' => __('Generated in the BCI merchant portal under Settings > Callback notifications.', Config::TEXT_DOMAIN),
            ],
            'live_connection_test' => [
                'title' => __('Live Connection Test', Config::TEXT_DOMAIN),
                'type' => 'bci_connection_test',
                'environment' => 'live',
            ],
            'sandbox_section' => [
                'title' => __('Sandbox Configuration', Config::TEXT_DOMAIN),
                'type' => 'title',
                'description' => __('Use these credentials for test transactions. Sandbox is used whenever Test mode is enabled.', Config::TEXT_DOMAIN),
            ],
            'sandbox_api_login' => [
                'title' => __('Sandbox API Login', Config::TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',
                'description' => __('The sandbox merchant API username.', Config::TEXT_DOMAIN),
            ],
            'sandbox_api_password' => [
                'title' => __('Sandbox API Password', Config::TEXT_DOMAIN),
                'type' => 'password',
                'default' => '',
                'description' => __('The sandbox merchant API password.', Config::TEXT_DOMAIN),
            ],
            'sandbox_callback_token' => [
                'title' => __('Sandbox Callback Token', Config::TEXT_DOMAIN),
                'type' => 'password',
                'default' => '',
                'description' => __('Generated in the BCI sandbox merchant portal under Settings > Callback notifications.', Config::TEXT_DOMAIN),
            ],
            'sandbox_connection_test' => [
                'title' => __('Sandbox Connection Test', Config::TEXT_DOMAIN),
                'type' => 'bci_connection_test',
                'environment' => 'sandbox',
            ],
            'callback_url' => [
                'title' => __('Callback URL', Config::TEXT_DOMAIN),
                'type' => 'bci_callback_url',
            ],
            'pending_threshold_minutes' => [
                'title' => __('Pending threshold', Config::TEXT_DOMAIN),
                'type' => 'number',
                'default' => (string) Config::PENDING_THRESHOLD_MINUTES,
                'description' => __('Pending BCI orders older than this many minutes are checked in the background.', Config::TEXT_DOMAIN),
                'custom_attributes' => [
                    'min' => '1',
                    'step' => '1',
                ],
            ],
            'failed_lookback_minutes' => [
                'title' => __('Failed recovery window', Config::TEXT_DOMAIN),
                'type' => 'number',
                'default' => (string) Config::FAILED_LOOKBACK_MINUTES,
                'description' => __('Recently failed BCI orders inside this window are rechecked to recover premature failure classification.', Config::TEXT_DOMAIN),
                'custom_attributes' => [
                    'min' => '0',
                    'step' => '1',
                ],
            ],
            'check_pending_orders' => [
                'title' => __('Pending Orders', Config::TEXT_DOMAIN),
                'type' => 'bci_check_pending',
            ],
            'subscriptions_section' => [
                'title' => __('Subscriptions', Config::TEXT_DOMAIN),
                'type' => 'title',
                'description' => __('Experimental and disabled by default for v1.0. Enable only after BCI confirms stored credential, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permissions for this merchant.', Config::TEXT_DOMAIN),
            ],
            'enable_subscriptions' => [
                'title' => __('Subscription renewals', Config::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable experimental automatic renewals when WooCommerce Subscriptions is active', Config::TEXT_DOMAIN),
                'default' => 'no',
                'description' => __('Leave disabled for the v1.0 one-off payments release unless subscription payments have been validated on this BPC merchant account.', Config::TEXT_DOMAIN),
            ],
            'subscription_readiness' => [
                'title' => __('Subscription readiness', Config::TEXT_DOMAIN),
                'type' => 'bci_subscription_readiness',
                'environment' => 'sandbox',
            ],
            'renewal_retry_attempts' => [
                'title' => __('Renewal retry attempts', Config::TEXT_DOMAIN),
                'type' => 'number',
                'default' => '0',
                'description' => __('Leave at 0 to rely on WooCommerce Subscriptions retry rules. Increase only if plugin-managed retries are required.', Config::TEXT_DOMAIN),
                'custom_attributes' => [
                    'min' => '0',
                    'step' => '1',
                ],
            ],
            'renewal_retry_interval' => [
                'title' => __('Renewal retry interval', Config::TEXT_DOMAIN),
                'type' => 'number',
                'default' => '60',
                'description' => __('Minutes between plugin-managed renewal retry attempts.', Config::TEXT_DOMAIN),
                'custom_attributes' => [
                    'min' => '5',
                    'step' => '5',
                ],
            ],
        ];
    }

    public function admin_options(): void
    {
        echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
        echo '<p>' . esc_html__('Configure BCI TakuEcom payments. Start in sandbox, test a payment, then repeat the callback setup for live credentials before processing real orders.', Config::TEXT_DOMAIN) . '</p>';

        if (class_exists(__NAMESPACE__ . '\Admin')) {
            $admin = new Admin();
            if (method_exists($admin, 'generate_bci_guided_setup_html')) {
                echo '<table class="form-table bci-woo-guided-setup-table">';
                echo $admin->generate_bci_guided_setup_html('guided_setup', []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</table>';
            }
        }

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function generate_bci_callback_url_html($key, $data): string
    {
        $callback_url = rest_url(Config::CALLBACK_NAMESPACE . Config::CALLBACK_ROUTE);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title'] ?? __('Callback URL', Config::TEXT_DOMAIN)); ?></label>
            </th>
            <td class="forminp">
                <code style="display:inline-block;padding:6px 8px;user-select:all;"><?php echo esc_html($callback_url); ?></code>
                <p class="description">
                    <?php echo esc_html__('Enter this URL in the BCI merchant portal as a Static callback URL. Use POST where possible, Symmetric signing, and enable Deposited, Approved, Reversed, Refunded, and Declined by timeout operations.', Config::TEXT_DOMAIN); ?>
                </p>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    public function generate_bci_connection_test_html($key, $data): string
    {
        $environment = (string) ($data['environment'] ?? 'sandbox');
        $nonce = wp_create_nonce('bci_woo_test_connection');

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title'] ?? __('Connection Test', Config::TEXT_DOMAIN)); ?></label>
            </th>
            <td class="forminp">
                <button type="button" class="button bci-woo-connection-test" data-environment="<?php echo esc_attr($environment); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php echo esc_html__('Test connection', Config::TEXT_DOMAIN); ?>
                </button>
                <span class="bci-woo-connection-result" style="margin-left:10px;"></span>
                <p class="description"><?php echo esc_html__('Checks that WordPress can reach the selected BCI gateway endpoint with the saved credentials.', Config::TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        <?php
        $this->print_admin_inline_script();
        return (string) ob_get_clean();
    }

    public function generate_bci_check_pending_html($key, $data): string
    {
        $nonce = wp_create_nonce('bci_woo_check_pending_orders');

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title'] ?? __('Pending Orders', Config::TEXT_DOMAIN)); ?></label>
            </th>
            <td class="forminp">
                <button type="button" class="button" id="bci-woo-check-pending" data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php echo esc_html__('Check Pending Orders', Config::TEXT_DOMAIN); ?>
                </button>
                <span id="bci-woo-check-pending-result" style="margin-left:10px;"></span>
                <p class="description"><?php echo esc_html__('Manually checks pending and recently failed BCI orders against the gateway.', Config::TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        <?php
        $this->print_admin_inline_script();
        return (string) ob_get_clean();
    }

    public function generate_bci_subscription_readiness_html($key, $data): string
    {
        if (class_exists(__NAMESPACE__ . '\Admin')) {
            return (new Admin())->generate_bci_subscription_readiness_html((string) $key, (array) $data, $this);
        }

        return '';
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Order not found.', Config::TEXT_DOMAIN), 'error');
            return ['result' => 'failure'];
        }

        $environment = Api::current_environment();
        $params = $this->build_register_params($order);

        try {
            $result = $this->api->register_payment($params, $environment);
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $order->add_order_note(sprintf(__('BCI payment registration failed: %s', Config::TEXT_DOMAIN), $e->getMessage()));
            return ['result' => 'failure'];
        }

        $order->update_meta_data(Config::META_MD_ORDER, sanitize_text_field((string) $result['orderId']));
        $order->update_meta_data(Config::META_ORDER_NUMBER, sanitize_text_field((string) $params['orderNumber']));
        $order->update_meta_data(Config::META_ENVIRONMENT, $environment);

        if (!empty($params['clientId'])) {
            $order->update_meta_data(Config::META_CLIENT_ID, sanitize_text_field((string) $params['clientId']));
        }

        $order->add_order_note(__('BCI TakuEcom payment registered. Customer redirected to the hosted payment form.', Config::TEXT_DOMAIN));
        $order->save();

        return [
            'result' => 'success',
            'redirect' => esc_url_raw((string) $result['formUrl']),
        ];
    }

    public function handle_return(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        $order = $order_id ? wc_get_order($order_id) : false;

        if (!$order || $order->get_order_key() !== $key) {
            wc_add_notice(__('We could not verify the returned BCI payment order.', Config::TEXT_DOMAIN), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (($order->has_status(['pending', 'failed', 'on-hold']) || !$order->is_paid())
            && $order->get_meta(Config::META_MD_ORDER, true)
        ) {
            $this->resolver->resolve($order, 'browser return');
            $order = wc_get_order($order_id);
        }

        if ($order && $order->is_paid()) {
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
            wp_safe_redirect($this->get_return_url($order));
            exit;
        }

        wc_add_notice(__('The BCI payment was not completed. Please try again or choose another payment method.', Config::TEXT_DOMAIN), 'error');
        wp_safe_redirect($order ? $order->get_checkout_payment_url() : wc_get_checkout_url());
        exit;
    }

    public function build_register_params(\WC_Order $order): array
    {
        $order_number = $this->build_order_number($order);
        $return_url = add_query_arg([
            'wc-api' => 'bci_takuecom_return',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ], home_url('/'));

        $params = [
            'amount' => $this->amount_to_minor_units($order->get_total()),
            'currency' => $this->currency_to_numeric($order->get_currency()),
            'language' => 'en',
            'orderNumber' => $order_number,
            'returnUrl' => $return_url,
            'failUrl' => $return_url,
            'description' => $this->safe_description($order),
            'jsonParams' => wp_json_encode([
                'CMS' => 'WordPress ' . get_bloginfo('version') . ' + WooCommerce ' . (defined('WC_VERSION') ? WC_VERSION : 'unknown'),
                'Module-Version' => Config::VERSION,
                'CMS_paymentType' => 'redirect',
            ]),
        ];

        $email = $order->get_billing_email();
        if ($email) {
            $params['email'] = $email;
        }

        $billing = $this->billing_payer_data($order);
        if (!empty($billing)) {
            $params['billingPayerData'] = wp_json_encode($billing);
        }

        if ($this->order_contains_subscription($order)) {
            $client_id = $this->client_id_for_order($order);
            $params['clientId'] = $client_id;
            $params['features'] = 'FORCE_CREATE_BINDING';
        }

        if (function_exists('apply_filters')) {
            $params = apply_filters('bci_woo_register_payment_params', $params, $order);
        }

        return $params;
    }

    public function client_id_for_order(\WC_Order $order): string
    {
        $customer_id = (int) $order->get_customer_id();
        if ($customer_id > 0) {
            return 'wc_customer_' . $customer_id;
        }

        $email = strtolower((string) $order->get_billing_email());
        return 'wc_guest_' . substr(hash('sha256', $email . '|' . home_url()), 0, 24);
    }

    public function amount_to_minor_units($amount): int
    {
        return (int) round((float) wc_format_decimal($amount, 2) * 100);
    }

    public function currency_to_numeric(?string $currency): string
    {
        return Api::payment_currency_to_numeric($currency);
    }

    public function build_order_number(\WC_Order $order): string
    {
        return substr('WC' . $order->get_id() . '-' . time(), 0, 36);
    }

    public function safe_description(\WC_Order $order): string
    {
        $description = sprintf(
            'WooCommerce order #%s - %s',
            $order->get_order_number(),
            wp_strip_all_tags((string) get_bloginfo('name'))
        );

        if (function_exists('mb_substr')) {
            return mb_substr($description, 0, 598);
        }

        return substr($description, 0, 598);
    }

    private function order_contains_subscription(\WC_Order $order): bool
    {
        if (!Api::subscriptions_enabled()) {
            return false;
        }

        if (class_exists(__NAMESPACE__ . '\Subscriptions')) {
            return (new Subscriptions($this))->contains_subscription($order);
        }

        return function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order);
    }

    private function billing_payer_data(\WC_Order $order): array
    {
        $map = [
            'billingCity' => $order->get_billing_city(),
            'billingCountry' => $order->get_billing_country(),
            'billingAddressLine1' => $order->get_billing_address_1(),
            'billingAddressLine2' => $order->get_billing_address_2(),
            'billingPostalCode' => $order->get_billing_postcode(),
            'billingState' => $order->get_billing_state(),
        ];

        return array_filter(array_map(static function ($value): string {
            return substr(wp_strip_all_tags((string) $value), 0, 50);
        }, $map));
    }

    private function print_admin_inline_script(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <script>
            (function() {
                function post(action, data, callback) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var response = null;
                        try { response = JSON.parse(xhr.responseText); } catch (e) {}
                        callback(xhr.status, response);
                    };
                    xhr.onerror = function() { callback(0, null); };
                    var body = 'action=' + encodeURIComponent(action);
                    Object.keys(data).forEach(function(key) {
                        body += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
                    });
                    xhr.send(body);
                }

                document.addEventListener('click', function(event) {
                    if (event.target.classList.contains('bci-woo-connection-test')) {
                        var button = event.target;
                        var result = button.parentNode.querySelector('.bci-woo-connection-result');
                        button.disabled = true;
                        result.textContent = '<?php echo esc_js(__('Testing...', Config::TEXT_DOMAIN)); ?>';
                        result.style.color = '';
                        post('bci_woo_test_connection', {
                            environment: button.getAttribute('data-environment'),
                            _ajax_nonce: button.getAttribute('data-nonce')
                        }, function(status, response) {
                            button.disabled = false;
                            var ok = status === 200 && response && response.success;
                            result.style.color = ok ? 'green' : 'red';
                            result.textContent = response && response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Connection test failed.', Config::TEXT_DOMAIN)); ?>';
                        });
                    }

                    if (event.target.id === 'bci-woo-check-pending') {
                        var pendingButton = event.target;
                        var pendingResult = document.getElementById('bci-woo-check-pending-result');
                        pendingButton.disabled = true;
                        pendingResult.textContent = '<?php echo esc_js(__('Checking...', Config::TEXT_DOMAIN)); ?>';
                        pendingResult.style.color = '';
                        post('bci_woo_check_pending_orders', {
                            _ajax_nonce: pendingButton.getAttribute('data-nonce')
                        }, function(status, response) {
                            pendingButton.disabled = false;
                            var ok = status === 200 && response && response.success;
                            pendingResult.style.color = ok ? 'green' : 'red';
                            pendingResult.textContent = response && response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Pending check failed.', Config::TEXT_DOMAIN)); ?>';
                        });
                    }
                });
            })();
        </script>
        <?php
    }
}
