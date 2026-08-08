<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Api
{
    /**
     * The only currency the live BCI TakuEcom merchant account can collect in.
     */
    public const LIVE_CURRENCY = 'NZD';

    private const CURRENCY_MAP = [
        'NZD' => '554',
        'EUR' => '978',
    ];

    private const LOG_REDACTION = '**removed from log**';

    /**
     * Request keys that are never written to the log, matched case-insensitively
     * at any depth of the request body.
     */
    private const LOG_SENSITIVE_KEYS = [
        'password',
        'bindingid',
        'token',
        'cvc',
        'pan',
        'maskedpan',
        'cardholdername',
        'expiry',
        'expirydate',
        'email',
        'phone',
        'billingpayerdata',
    ];

    public static function settings(): array
    {
        $settings = get_option(Config::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }

    public static function get_setting(string $key, $default = '')
    {
        $settings = self::settings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function current_environment(): string
    {
        return self::get_setting('test_mode', 'no') === 'yes' ? 'sandbox' : 'live';
    }

    public static function subscriptions_enabled(): bool
    {
        return self::get_setting('enable_subscriptions', 'no') === 'yes';
    }

    public static function api_url(string $environment): string
    {
        return $environment === 'sandbox' ? Config::API_URL_SANDBOX : Config::API_URL_LIVE;
    }

    public static function payment_url(string $environment): string
    {
        return $environment === 'sandbox' ? Config::PAYMENT_URL_SANDBOX : Config::PAYMENT_URL_LIVE;
    }

    public static function currency_to_numeric(?string $currency): string
    {
        $currency = strtoupper((string) $currency);
        if (isset(self::CURRENCY_MAP[$currency])) {
            return self::CURRENCY_MAP[$currency];
        }

        Log::notice('BCI TakuEcom checkout is fixed to NZD; overriding WooCommerce currency.', [
            'currency' => $currency,
        ]);

        return self::CURRENCY_MAP['NZD'];
    }

    /**
     * The currency a payment addressed to $environment must be charged in.
     *
     * Each environment collects in its own currency, so a charge sent to a host
     * has to be priced for that host. Callers that register and pay in the
     * environment the plugin is configured for right now — checkout — omit the
     * argument; callers that address an environment of their own — a renewal
     * charged wherever its binding was created — pass it.
     */
    public static function payment_currency_code(?string $currency, ?string $environment = null): string
    {
        $environment = (string) $environment !== '' ? (string) $environment : self::current_environment();

        if ($environment === 'sandbox') {
            $sandbox_currency = strtoupper((string) self::get_setting('sandbox_currency', ''));

            if ($sandbox_currency === '' && self::get_setting('sandbox_force_eur_currency', 'no') === 'yes') {
                $sandbox_currency = 'EUR';
            }

            if (isset(self::CURRENCY_MAP[$sandbox_currency])) {
                return $sandbox_currency;
            }

            if ($sandbox_currency !== '') {
                Log::notice('Unsupported BCI sandbox currency setting; defaulting to EUR.', [
                    'currency' => $sandbox_currency,
                ]);
            }

            return 'EUR';
        }

        $currency = strtoupper((string) $currency);
        if ($currency !== '' && $currency !== self::LIVE_CURRENCY) {
            Log::notice('BCI TakuEcom checkout is fixed to NZD; ignoring WooCommerce order currency.', [
                'currency' => $currency,
            ]);
        }

        return self::LIVE_CURRENCY;
    }

    public static function has_credentials(string $environment): bool
    {
        $prefix = self::credential_prefix($environment);

        return trim((string) self::get_setting($prefix . '_api_login', '')) !== ''
            && trim((string) self::get_setting($prefix . '_api_password', '')) !== '';
    }

    public static function payment_currency_to_numeric(?string $currency, ?string $environment = null): string
    {
        return self::currency_to_numeric(self::payment_currency_code($currency, $environment));
    }

    public static function callback_tokens(): array
    {
        return array_filter([
            'live' => (string) self::get_setting('live_callback_token', ''),
            'sandbox' => (string) self::get_setting('sandbox_callback_token', ''),
        ], static fn($token): bool => $token !== '');
    }

    /**
     * Sends a payment registration to BPC and returns whatever came back.
     *
     * Like every other call here this is transport only: what an answer means —
     * an error code, a 200 with no form to redirect to — is decided by
     * Registration, which is the one place that knows what a usable registration
     * has to contain and what an order is left holding when it is not one.
     *
     * @return array|\WP_Error
     */
    public function register_payment(array $params, string $environment)
    {
        return $this->post_form(self::api_url($environment) . '/register.do', $params, $environment);
    }

    /**
     * Ask an environment for the extended status of an order.
     *
     * `language` pins the response wording to English, as every other outbound
     * call does. It is not cosmetic here: the wording is what the admin
     * connection probe's rejection heuristic matches on, and what a declined
     * payment's actionCodeDescription puts into the order note, and both were
     * written against the English text. Without it a merchant account whose
     * default response language is not English would have its rejections read
     * as passes.
     *
     * @return array|\WP_Error
     */
    public function get_order_status(string $md_order, string $environment)
    {
        return $this->post_form(
            self::api_url($environment) . '/getOrderStatusExtended.do',
            [
                'orderId' => $md_order,
                'language' => 'en',
            ],
            $environment
        );
    }

    /**
     * List the stored-credential bindings BPC holds for a client id.
     *
     * Recurring bindings are asked for, expired ones included, because the only
     * caller is the admin readiness probe: it wants to know whether the merchant
     * account may list bindings at all, not what any particular binding is.
     *
     * @return array|\WP_Error
     */
    public function get_bindings(string $client_id, string $environment)
    {
        return $this->post_form(
            self::api_url($environment) . '/getBindings.do',
            [
                'clientId' => $client_id,
                'bindingType' => 'R',
                'showExpired' => 'true',
                'language' => 'en',
            ],
            $environment
        );
    }

    /**
     * @return array|\WP_Error
     */
    public function recurrent_payment(array $params, string $environment)
    {
        return $this->post_json(
            self::payment_url($environment) . '/recurrentPayment.do',
            $params,
            $environment
        );
    }

    /**
     * Ask an environment about an order that cannot exist, to see who answers.
     *
     * Returns ['success' => bool, 'message' => string], plus 'raw' => the decoded
     * gateway response whenever the gateway answered at all. A caller reading a
     * failure can therefore tell a rejected credential (the gateway answered, so
     * 'raw' is there and carries its wording) from an endpoint that was never
     * reached (no 'raw').
     */
    public function test_connection(string $environment): array
    {
        $result = $this->get_order_status('bci-connection-test-' . time(), $environment);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
            ];
        }

        if ($this->looks_like_auth_error($result)) {
            return [
                'success' => false,
                'message' => __('The gateway responded, but the credentials appear to be invalid.', Config::TEXT_DOMAIN),
                'raw' => $result,
            ];
        }

        return [
            'success' => true,
            'message' => __('The gateway responded. Credentials and endpoint are reachable.', Config::TEXT_DOMAIN),
            'raw' => $result,
        ];
    }

    /**
     * @return array|\WP_Error
     */
    private function post_form(string $url, array $params, string $environment)
    {
        return $this->post($url, array_merge($this->credentials($environment), $params), [
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ]);
    }

    /**
     * Form-encodes a request body, repeating the key once per value of an array.
     *
     * BPC expresses a multi-valued parameter — `features` is the only one this
     * plugin sends — by repeating it in the same request (features=FORCE_TDS
     * &features=FORCE_CREATE_BINDING). Handing wp_remote_post() an array body
     * would instead produce features[0]=…&features[1]=…, which the gateway does
     * not recognise, and a comma-joined string would be read as one unknown
     * feature name, so the body is encoded here instead.
     */
    private static function form_encode(array $body): string
    {
        $pairs = [];

        foreach ($body as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                if (is_bool($item)) {
                    $item = $item ? '1' : '0';
                }

                $pairs[] = urlencode((string) $key) . '=' . urlencode((string) $item);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * @return array|\WP_Error
     */
    private function post_json(string $url, array $params, string $environment)
    {
        return $this->post($url, array_merge($this->credentials($environment), $params), [
            'Content-Type' => 'application/json',
        ], true);
    }

    /**
     * @return array|\WP_Error
     */
    private function post(string $url, array $body, array $headers, bool $json = false)
    {
        Log::info('Sending BCI API request.', [
            'url' => $url,
            'body' => self::redact_log_body($body),
        ]);

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $json ? wp_json_encode($body) : self::form_encode($body),
            'timeout' => Config::API_TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            Log::notice('BCI API network error: ' . $response->get_error_message());
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            $message = 'BCI API returned HTTP ' . $http_code;
            Log::notice($message, self::response_log_context((string) wp_remote_retrieve_body($response)));
            return new \WP_Error('bci_woo_http_error', $message);
        }

        $body_text = wp_remote_retrieve_body($response);
        $decoded = json_decode($body_text, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $message = 'BCI API returned invalid JSON: ' . json_last_error_msg();
            Log::notice($message, self::response_log_context($body_text));
            return new \WP_Error('bci_woo_json_error', $message);
        }

        return $decoded;
    }

    /**
     * Redact a request body for the log.
     *
     * The gateway body carries credentials, the customer email and the billing
     * address, so sensitive keys are stripped at every depth rather than only at
     * the top level.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function redact_log_body($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = in_array(strtolower((string) $key), self::LOG_SENSITIVE_KEYS, true)
                ? self::LOG_REDACTION
                : self::redact_log_body($item);
        }

        return $redacted;
    }

    /**
     * Summarise an unexpected response for the log.
     *
     * A getOrderStatusExtended body carries binding IDs and card data, so the raw
     * body is never logged: only the gateway error fields and the body size are.
     */
    private static function response_log_context(string $body): array
    {
        $context = ['response_length' => strlen($body)];

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['errorCode' => 'error_code', 'errorMessage' => 'error_message'] as $key => $label) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    $context[$label] = (string) $decoded[$key];
                }
            }
        }

        return $context;
    }

    private function credentials(string $environment): array
    {
        $prefix = self::credential_prefix($environment);

        return [
            'userName' => (string) self::get_setting($prefix . '_api_login', ''),
            'password' => (string) self::get_setting($prefix . '_api_password', ''),
        ];
    }

    private static function credential_prefix(string $environment): string
    {
        return $environment === 'sandbox' ? 'sandbox' : 'live';
    }

    /**
     * Does this response read as the gateway rejecting the credentials?
     *
     * The admin connection test used to carry a second copy of this heuristic,
     * and the copies had drifted: that one looked in five response fields and
     * carried 'forbidden', this one looked in three and did not, so the same
     * reply could be a rejection on one screen and a pass on another. The fields
     * and the needles are the union of the two now, and this is the only copy.
     */
    private function looks_like_auth_error(array $result): bool
    {
        $message = '';
        foreach (['errorMessage', 'error', 'message', 'displayErrorMessage', 'actionCodeDescription'] as $key) {
            if (isset($result[$key]) && is_scalar($result[$key])) {
                $message .= ' ' . (string) $result[$key];
            }
        }

        $message = strtolower(trim($message));

        if ($message === '') {
            return false;
        }

        foreach (['password', 'login', 'credential', 'authentication', 'authorisation', 'authorization', 'access denied', 'forbidden'] as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
