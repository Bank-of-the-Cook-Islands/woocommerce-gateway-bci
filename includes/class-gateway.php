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

        // Subscription behaviour is decided here and at the two other registration
        // sites (the renewal hooks below, and Subscriptions::register_hooks()). What
        // gets registered while the setting is on is not asked to re-check it later.
        if (Api::subscriptions_enabled()) {
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

        if (Api::subscriptions_enabled()) {
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
                'description' => __('Uses the BPC development environment and its EUR default. Disable only when BCI has issued live credentials and you are ready to process real payments.', Config::TEXT_DOMAIN),
            ],
            'sandbox_currency' => [
                'title' => __('Sandbox currency', Config::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'EUR',
                'description' => __('The BPC development environment defaults to EUR. Change this only after selecting the matching currency in the BPC Dev Merchant Portal.', Config::TEXT_DOMAIN),
                'options' => [
                    'EUR' => __('EUR - Euro (default)', Config::TEXT_DOMAIN),
                    'NZD' => __('NZD - New Zealand dollar', Config::TEXT_DOMAIN),
                ],
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

        echo '<table class="form-table bci-woo-guided-setup-table">';
        echo (new Admin())->generate_bci_guided_setup_html('guided_setup', []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</table>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function generate_bci_callback_url_html($key, $data): string
    {
        $callback_url = Callback::get_callback_url();

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
        return (new Admin())->generate_bci_subscription_readiness_html((string) $key, (array) $data, $this);
    }

    public function is_available(): bool
    {
        if (!parent::is_available()) {
            return false;
        }

        return self::availability_error() === '';
    }

    /**
     * Explains why the gateway cannot be offered, or an empty string when it can.
     *
     * Pass the currency the request will actually be built from (an order currency) so the
     * check is not fooled by a store currency that has since changed or been swapped by a
     * multi-currency plugin. Defaults to the current WooCommerce store currency.
     */
    public static function availability_error(?string $order_currency = null): string
    {
        $environment = Api::current_environment();

        if (!Api::has_credentials($environment)) {
            return $environment === 'sandbox'
                ? __('The sandbox API login and password are empty. Save the sandbox credentials, or add live credentials and disable Test mode.', Config::TEXT_DOMAIN)
                : __('The live API login and password are empty. Save the live credentials issued by BCI, or enable Test mode to use the sandbox.', Config::TEXT_DOMAIN);
        }

        if ($environment !== 'live') {
            return '';
        }

        $currency = $order_currency !== null
            ? strtoupper(trim($order_currency))
            : (function_exists('get_woocommerce_currency') ? strtoupper((string) get_woocommerce_currency()) : '');

        if ($currency === '' || $currency === Api::LIVE_CURRENCY) {
            return '';
        }

        if ($order_currency !== null) {
            return sprintf(
                /* translators: 1: the order currency code, 2: the currency BCI collects in. */
                __('The order currency is %1$s but BCI TakuEcom collects live payments in %2$s, so the order total would be charged as the same number of %2$s.', Config::TEXT_DOMAIN),
                $currency,
                Api::LIVE_CURRENCY
            );
        }

        return sprintf(
            /* translators: 1: WooCommerce store currency code, 2: the currency BCI collects in. */
            __('The store currency is %1$s but BCI TakuEcom collects live payments in %2$s, so a %1$s order would be charged as the same number of %2$s. Price the store in %2$s to accept BCI payments.', Config::TEXT_DOMAIN),
            $currency,
            Api::LIVE_CURRENCY
        );
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Order not found.', Config::TEXT_DOMAIN), 'error');
            return ['result' => 'failure'];
        }

        // The request is built from the order currency, so guard on that rather than on the
        // store currency, which a multi-currency plugin or an admin edit can have moved on from.
        $order_currency = trim((string) $order->get_currency());

        $availability_error = self::availability_error($order_currency !== '' ? $order_currency : null);
        if ($availability_error !== '') {
            Log::notice('BCI payment blocked by gateway configuration.', ['reason' => $availability_error]);
            wc_add_notice(__('This payment method is currently unavailable. Please choose another payment method.', Config::TEXT_DOMAIN), 'error');
            return ['result' => 'failure'];
        }

        // Everything the payment itself is made of — the request, what BPC's answer is
        // allowed to mean, and the reference the order keeps — belongs to Registration.
        // What is left here is the WooCommerce half: telling the customer, and saying
        // where checkout goes next.
        $registration = (new Registration($this->api))->register($order);

        if (!$registration->is_successful()) {
            wc_add_notice($registration->error_message(), 'error');
            return ['result' => 'failure'];
        }

        return [
            'result' => 'success',
            'redirect' => $registration->redirect_url(),
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

        // A browser return is a poll, so it asks only about orders still open —
        // an order a callback has already settled is left alone rather than
        // re-read and re-noted on every refresh of this URL.
        if (Payment_Resolution::is_resolvable($order)) {
            Payment_Resolution::resolve($order, Payment_Resolution::BROWSER_RETURN);
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
