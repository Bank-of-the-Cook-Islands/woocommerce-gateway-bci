<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registering a one-off payment at BPC: the request, the answer, and what the
 * order is left holding.
 *
 * Registration is the one step of a checkout that cannot be repeated safely, so
 * everything that decides whether it happened lives here: what /register.do is
 * asked for, what its answer is allowed to mean, and the reference the order
 * keeps. Those used to be three owners — the gateway built the request, Api
 * decided whether the response was usable, and the gateway wrote the reference
 * back — which is why the invariant they add up to could only be read by
 * reading all three.
 *
 * The invariant is: an order that has been sent to a hosted payment form knows
 * the BPC mdOrder, the orderNumber the callback will quote, and the environment
 * both belong to, and it knew them before the customer was redirected. Nothing
 * downstream — the callback, the browser return, the scheduler, a renewal — can
 * find a payment without them. A failed registration therefore records no
 * gateway reference — only a human-facing order note — so there is no partial
 * state for the callback, the scheduler, or a renewal to act on; nothing
 * machine-read is written until BPC has answered with a form to send the
 * customer to.
 *
 * WooCommerce adaptation is not this module's job. Whether the gateway may be
 * offered at all is Gateway::availability_error(), and turning the result into a
 * checkout notice and a WooCommerce result array is Gateway::process_payment().
 * Stored-credential parameters arrive through the bci_woo_register_payment_params
 * filter, which Subscriptions hooks only when the merchant has enabled
 * subscriptions, so this module has no opinion on whether an order wants a
 * binding — only that a clientId that was registered gets recorded.
 */
final class Registration
{
    /** Longest orderNumber BPC accepts. */
    private const ORDER_NUMBER_MAX = 36;

    /** Longest description BPC accepts. */
    private const DESCRIPTION_MAX = 598;

    /** Longest billing payer field BPC accepts. */
    private const BILLING_FIELD_MAX = 50;

    private Api $api;

    public function __construct(?Api $api = null)
    {
        $this->api = $api ?? new Api();
    }

    /**
     * Registers a payment for an order and records what BPC answered with.
     *
     * The environment is read once, here, and is both the host the request is
     * sent to and the environment recorded on the order, so the two can never
     * disagree about where the payment lives.
     */
    public function register(\WC_Order $order): Registration_Result
    {
        $environment = Api::current_environment();
        $params = $this->build_params($order);

        $response = $this->api->register_payment($params, $environment);

        $error = $this->rejection_reason($response);
        if ($error !== '') {
            $order->add_order_note(sprintf(
                /* translators: %s is the reason the registration failed. */
                __('BCI payment registration failed: %s', Config::TEXT_DOMAIN),
                $error
            ));

            return Registration_Result::failed($error);
        }

        Order_State::for($order)->record_registration(
            (string) $response['orderId'],
            (string) $params['orderNumber'],
            $environment,
            (string) ($params['clientId'] ?? '')
        );

        $order->add_order_note(__('BCI TakuEcom payment registered. Customer redirected to the hosted payment form.', Config::TEXT_DOMAIN));
        $order->save();

        return Registration_Result::succeeded(esc_url_raw((string) $response['formUrl']));
    }

    /**
     * Why a /register.do answer cannot be paid against, or '' when it can be.
     *
     * The three ways a registration does not produce a payment are one question,
     * because they have one consequence: the customer has nowhere to be sent and
     * the order must be left as though nothing had been registered. A transport
     * fault, a gateway error code, and a 200 that carries no form to redirect to
     * are therefore answered in the same place and in the same shape.
     *
     * @param array|\WP_Error $response What Api::register_payment() returned.
     */
    private function rejection_reason($response): string
    {
        if (is_wp_error($response)) {
            return sprintf(
                /* translators: %s is the network error from WordPress. */
                __('Cannot start payment at BCI. Network error: %s', Config::TEXT_DOMAIN),
                $response->get_error_message()
            );
        }

        if (!is_array($response)) {
            return __('BCI did not return a payment link. Please check the gateway logs.', Config::TEXT_DOMAIN);
        }

        if (isset($response['errorCode']) && (string) $response['errorCode'] !== '0') {
            Log::notice('BCI payment registration failed.', [
                'error_code' => $response['errorCode'],
                'error_message' => $response['errorMessage'] ?? '',
            ]);

            return sprintf(
                /* translators: 1: gateway error code, 2: gateway error message. */
                __('Cannot start payment at BCI. Error %1$s: %2$s', Config::TEXT_DOMAIN),
                $response['errorCode'],
                $response['errorMessage'] ?? __('Unknown error', Config::TEXT_DOMAIN)
            );
        }

        if (empty($response['formUrl']) || empty($response['orderId'])) {
            return __('BCI did not return a payment link. Please check the gateway logs.', Config::TEXT_DOMAIN);
        }

        return '';
    }

    /**
     * The /register.do request for an order.
     *
     * Stored-credential fields are added by Subscriptions off the filter below,
     * which is hooked only when the merchant has enabled subscriptions. Adding
     * them here too would be a second copy of the clientId and
     * FORCE_CREATE_BINDING rules, and a second answer to whether this order
     * wants a binding at all.
     */
    private function build_params(\WC_Order $order): array
    {
        $return_url = add_query_arg([
            'wc-api' => 'bci_takuecom_return',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ], home_url('/'));

        $params = [
            'amount' => $this->amount_to_minor_units($order->get_total()),
            'currency' => Api::payment_currency_to_numeric($order->get_currency()),
            'language' => 'en',
            'orderNumber' => self::build_order_number($order),
            'returnUrl' => $return_url,
            'failUrl' => $return_url,
            'description' => self::safe_description($order),
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

        if (function_exists('apply_filters')) {
            $params = apply_filters('bci_woo_register_payment_params', $params, $order);
        }

        return $params;
    }

    /**
     * @param mixed $amount
     */
    private function amount_to_minor_units($amount): int
    {
        return (int) round((float) wc_format_decimal($amount, 2) * 100);
    }

    /**
     * A merchant order number BPC has not seen before.
     *
     * A WooCommerce order can be registered more than once — the customer
     * abandons the hosted form and pays again from the order-pay page, or
     * double-submits Place Order — and BPC rejects a repeated orderNumber, so
     * the attempt is what is numbered, not the order. Two attempts can fall in
     * the same second, so an attempt is counted in microseconds with a random
     * tail rather than in seconds. The order id is the readable part, and the
     * part that gives way if the 36 characters BPC accepts run out, because
     * only the attempt has to be unique.
     */
    public static function build_order_number(\WC_Order $order): string
    {
        [$microseconds, $seconds] = explode(' ', microtime());
        $attempt = sprintf('-%d%06d%03d', (int) $seconds, (int) ((float) $microseconds * 1000000), random_int(0, 999));

        return substr('WC' . $order->get_id(), 0, self::ORDER_NUMBER_MAX - strlen($attempt)) . $attempt;
    }

    /**
     * The order description shown on the hosted form and in the BPC portal.
     *
     * It names the order and the store and nothing else: this string reaches the
     * gateway's own records, so it never carries card or customer data.
     */
    public static function safe_description(\WC_Order $order): string
    {
        $description = sprintf(
            'WooCommerce order #%s - %s',
            $order->get_order_number(),
            wp_strip_all_tags((string) get_bloginfo('name'))
        );

        if (function_exists('mb_substr')) {
            return mb_substr($description, 0, self::DESCRIPTION_MAX);
        }

        return substr($description, 0, self::DESCRIPTION_MAX);
    }

    /**
     * The billing address BPC is told about, in the shape it accepts.
     *
     * The country is normalised to its uppercase code and the state is omitted
     * for Cook Islands addresses: CK has no ISO subdivisions, so a WooCommerce
     * island name sent as billingState is rejected by BPC and takes the whole
     * registration with it. Empty fields are dropped rather than sent blank, and
     * every field is cut to the length BPC accepts.
     */
    private function billing_payer_data(\WC_Order $order): array
    {
        $country = strtoupper((string) $order->get_billing_country());
        $map = [
            'billingCity' => $order->get_billing_city(),
            'billingCountry' => $country,
            'billingAddressLine1' => $order->get_billing_address_1(),
            'billingAddressLine2' => $order->get_billing_address_2(),
            'billingPostalCode' => $order->get_billing_postcode(),
        ];

        if ($country !== 'CK') {
            $map['billingState'] = $order->get_billing_state();
        }

        return array_filter(array_map(static function ($value): string {
            return substr(wp_strip_all_tags((string) $value), 0, self::BILLING_FIELD_MAX);
        }, $map));
    }
}
