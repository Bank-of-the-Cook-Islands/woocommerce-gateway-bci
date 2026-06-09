<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class Api
{
    private const CURRENCY_MAP = [
        'NZD' => '554',
        'EUR' => '978',
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

    public static function payment_currency_code(?string $currency): string
    {
        if (self::current_environment() === 'sandbox' && self::get_setting('sandbox_force_eur_currency', 'no') === 'yes') {
            return 'EUR';
        }

        $currency = strtoupper((string) $currency);
        if ($currency !== '' && $currency !== 'NZD') {
            Log::notice('BCI TakuEcom checkout is fixed to NZD; ignoring WooCommerce order currency.', [
                'currency' => $currency,
            ]);
        }

        return 'NZD';
    }

    public static function payment_currency_to_numeric(?string $currency): string
    {
        return self::currency_to_numeric(self::payment_currency_code($currency));
    }

    public static function callback_tokens(): array
    {
        return array_filter([
            'live' => (string) self::get_setting('live_callback_token', ''),
            'sandbox' => (string) self::get_setting('sandbox_callback_token', ''),
        ], static fn($token): bool => $token !== '');
    }

    /**
     * @throws Exception
     */
    public function register_payment(array $params, string $environment): array
    {
        $result = $this->post_form(self::api_url($environment) . '/register.do', $params, $environment);

        if (is_wp_error($result)) {
            throw new Exception(
                sprintf(
                    /* translators: %s is the network error from WordPress. */
                    __('Cannot start payment at BCI. Network error: %s', Config::TEXT_DOMAIN),
                    $result->get_error_message()
                )
            );
        }

        if (isset($result['errorCode']) && (string) $result['errorCode'] !== '0') {
            Log::notice('BCI payment registration failed.', [
                'error_code' => $result['errorCode'],
                'error_message' => $result['errorMessage'] ?? '',
            ]);

            throw new Exception(
                sprintf(
                    /* translators: 1: gateway error code, 2: gateway error message. */
                    __('Cannot start payment at BCI. Error %1$s: %2$s', Config::TEXT_DOMAIN),
                    $result['errorCode'],
                    $result['errorMessage'] ?? __('Unknown error', Config::TEXT_DOMAIN)
                )
            );
        }

        if (empty($result['formUrl']) || empty($result['orderId'])) {
            throw new Exception(__('BCI did not return a payment link. Please check the gateway logs.', Config::TEXT_DOMAIN));
        }

        return $result;
    }

    /**
     * @return array|\WP_Error
     */
    public function get_order_status(string $md_order, string $environment)
    {
        return $this->post_form(
            self::api_url($environment) . '/getOrderStatusExtended.do',
            ['orderId' => $md_order],
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
        $log_body = $body;
        foreach (['password', 'bindingId', 'token', 'cvc'] as $sensitive_key) {
            if (isset($log_body[$sensitive_key])) {
                $log_body[$sensitive_key] = '**removed from log**';
            }
        }

        Log::info('Sending BCI API request.', [
            'url' => $url,
            'body' => $log_body,
        ]);

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $json ? wp_json_encode($body) : $body,
            'timeout' => Config::API_TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            Log::notice('BCI API network error: ' . $response->get_error_message());
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            $message = 'BCI API returned HTTP ' . $http_code;
            Log::notice($message, ['response_body' => wp_remote_retrieve_body($response)]);
            return new \WP_Error('bci_woo_http_error', $message);
        }

        $body_text = wp_remote_retrieve_body($response);
        $decoded = json_decode($body_text, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $message = 'BCI API returned invalid JSON: ' . json_last_error_msg();
            Log::notice($message, ['response_body' => $body_text]);
            return new \WP_Error('bci_woo_json_error', $message);
        }

        return $decoded;
    }

    private function credentials(string $environment): array
    {
        if ($environment === 'sandbox') {
            return [
                'userName' => (string) self::get_setting('sandbox_api_login', ''),
                'password' => (string) self::get_setting('sandbox_api_password', ''),
            ];
        }

        return [
            'userName' => (string) self::get_setting('live_api_login', ''),
            'password' => (string) self::get_setting('live_api_password', ''),
        ];
    }

    private function looks_like_auth_error(array $result): bool
    {
        $message = strtolower(
            trim(($result['errorMessage'] ?? '') . ' ' . ($result['error'] ?? '') . ' ' . ($result['message'] ?? ''))
        );

        if ($message === '') {
            return false;
        }

        foreach (['password', 'login', 'credential', 'authentication', 'authorisation', 'authorization', 'access denied'] as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
