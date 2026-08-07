<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(__NAMESPACE__ . '\Resolution') && is_readable(__DIR__ . '/class-resolution.php')) {
    require_once __DIR__ . '/class-resolution.php';
}

final class Status_Resolver
{
    private Api $api;

    public function __construct(?Api $api = null)
    {
        $this->api = $api ?: new Api();
    }

    /**
     * Reads the current gateway status for an order and applies it.
     *
     * The status request lives here; what its answer means is classify()'s
     * business, and what that means for the order is apply()'s.
     */
    public function resolve(\WC_Order $order, string $context): string
    {
        $status = $this->fetch_status($order, $context);
        if ($status === null) {
            return Resolution::ERROR;
        }

        $resolution = self::classify($status);
        $this->apply($order, $resolution, $context);

        return $resolution->outcome;
    }

    /**
     * The BPC status table. Pure: no WordPress, no order, no side effects.
     *
     * This is the single table for every path — browser return, callback,
     * scheduler and subscription renewal — so a status can only ever mean one
     * thing to this plugin.
     *
     * @param array $status A getOrderStatusExtended payload.
     */
    public static function classify(array $status): Resolution
    {
        if ((string) ($status['errorCode'] ?? '') !== '0') {
            return new Resolution(
                Resolution::ERROR,
                $status,
                Resolution::NO_ORDER_STATUS,
                0,
                Resolution::REASON_GATEWAY_ERROR
            );
        }

        if (!isset($status['orderStatus'])) {
            return new Resolution(
                Resolution::ERROR,
                $status,
                Resolution::NO_ORDER_STATUS,
                0,
                Resolution::REASON_MISSING_STATUS
            );
        }

        $order_status = (int) $status['orderStatus'];
        $action_code = isset($status['actionCode']) ? (int) $status['actionCode'] : 0;

        if ($order_status === Config::STATUS_CAPTURED) {
            return new Resolution(Resolution::COMPLETED, $status, $order_status, $action_code);
        }

        if ($order_status === Config::STATUS_REFUNDED) {
            return new Resolution(Resolution::REFUNDED, $status, $order_status, $action_code);
        }

        if ($order_status === Config::STATUS_AUTH_CANCELLED) {
            return new Resolution(Resolution::CANCELLED, $status, $order_status, $action_code);
        }

        if ($order_status === Config::STATUS_DECLINED) {
            return new Resolution(Resolution::FAILED, $status, $order_status, $action_code);
        }

        // A registered order carrying an action code has had a payment attempt
        // rejected; -30001 is BPC's "no attempt yet" placeholder.
        if ($order_status === Config::STATUS_REGISTERED && $action_code !== 0 && $action_code !== -30001) {
            return new Resolution(Resolution::FAILED, $status, $order_status, $action_code);
        }

        if (in_array($order_status, [
            Config::STATUS_REGISTERED,
            Config::STATUS_AUTHORISED,
            Config::STATUS_ACS_INITIATED,
            Config::STATUS_PENDING,
            Config::STATUS_PARTIAL_COMPLETION,
        ], true)) {
            return new Resolution(Resolution::PENDING, $status, $order_status, $action_code);
        }

        return new Resolution(
            Resolution::ERROR,
            $status,
            $order_status,
            $action_code,
            Resolution::REASON_UNEXPECTED_STATUS
        );
    }

    /**
     * Carries a classification into WooCommerce: order state, meta, notes, logs
     * and the bci_woo_payment_status_resolved hook. Every side effect lives here.
     */
    public function apply(\WC_Order $order, Resolution $resolution, string $context): void
    {
        if ($resolution->reason === Resolution::REASON_GATEWAY_ERROR) {
            $message = sprintf(
                /* translators: 1: gateway code, 2: gateway message. */
                __('BCI status check returned error %1$s: %2$s', Config::TEXT_DOMAIN),
                $resolution->status['errorCode'] ?? __('unknown', Config::TEXT_DOMAIN),
                $resolution->status['errorMessage'] ?? __('Unknown error', Config::TEXT_DOMAIN)
            );
            $order->add_order_note($message);
            Log::notice($message, ['order_id' => $order->get_id(), 'context' => $context]);
            return;
        }

        if ($resolution->reason === Resolution::REASON_MISSING_STATUS) {
            $order->add_order_note(__('BCI status response did not include an order status.', Config::TEXT_DOMAIN));
            Log::notice('BCI status response missing orderStatus.', ['order_id' => $order->get_id()]);
            return;
        }

        $order->update_meta_data(Config::META_LAST_STATUS, $resolution->order_status);
        $order->update_meta_data(Config::META_LAST_ACTION_CODE, $resolution->action_code);

        switch ($resolution->outcome) {
            case Resolution::COMPLETED:
                $this->mark_paid($order, $resolution->status, $context);
                $this->maybe_store_binding($order, $resolution->status);
                break;

            case Resolution::REFUNDED:
                $this->mark_refunded($order, $context);
                break;

            case Resolution::CANCELLED:
                $this->mark_cancelled_or_failed($order, $context);
                break;

            case Resolution::FAILED:
                $this->mark_failed($order, $resolution->status, $context);
                break;

            case Resolution::PENDING:
                $order->save();
                Log::info('BCI payment remains pending.', [
                    'order_id' => $order->get_id(),
                    'context' => $context,
                    'gateway_status' => $resolution->order_status,
                    'action_code' => $resolution->action_code,
                ]);
                break;

            default:
                $order->add_order_note(sprintf(
                    /* translators: 1: BCI order status, 2: context. */
                    __('Unexpected BCI payment status %1$d (%2$s).', Config::TEXT_DOMAIN),
                    $resolution->order_status,
                    $context
                ));
                $order->save();

                Log::notice('Unexpected BCI payment status.', [
                    'order_id' => $order->get_id(),
                    'context' => $context,
                    'gateway_status' => $resolution->order_status,
                ]);
                break;
        }

        $this->fire_resolved_hook($order, $resolution->outcome, $resolution->status, $context);
    }

    /**
     * Asks the gateway for the order status.
     *
     * Returns null when there is nothing to classify — no gateway reference, or
     * the request itself failed — having already noted and logged the reason.
     */
    private function fetch_status(\WC_Order $order, string $context): ?array
    {
        $md_order = (string) $order->get_meta(Config::META_MD_ORDER, true);
        if ($md_order === '') {
            $order->add_order_note(__('BCI status check skipped: no gateway reference is stored on this order.', Config::TEXT_DOMAIN));
            return null;
        }

        $environment = (string) $order->get_meta(Config::META_ENVIRONMENT, true);
        if ($environment === '') {
            $environment = Api::current_environment();
        }

        $status = $this->api->get_order_status($md_order, $environment);
        if (is_wp_error($status)) {
            $order->add_order_note(sprintf(
                /* translators: 1: context, 2: error message. */
                __('BCI status check failed (%1$s): %2$s', Config::TEXT_DOMAIN),
                $context,
                $status->get_error_message()
            ));
            Log::notice('BCI status check failed.', [
                'order_id' => $order->get_id(),
                'context' => $context,
                'error' => $status->get_error_message(),
            ]);
            return null;
        }

        return is_array($status) ? $status : [];
    }

    private function mark_paid(\WC_Order $order, array $status, string $context): void
    {
        $transaction_id = sanitize_text_field((string) ($status['authRefNum'] ?? ''));
        if ($transaction_id === '') {
            $transaction_id = (string) $order->get_meta(Config::META_MD_ORDER, true);
        }

        if (!$order->is_paid()) {
            $order->payment_complete($transaction_id);
        } elseif ($transaction_id !== '') {
            $order->set_transaction_id($transaction_id);
        }

        $note = sprintf(
            /* translators: %s is the context, for example gateway callback. */
            __('Payment completed via BCI TakuEcom (%s).', Config::TEXT_DOMAIN),
            $context
        );

        $mode = (string) Api::get_setting('paid_order_status', 'default');
        if ($mode === 'processing' && $order->get_status() !== 'processing') {
            $order->update_status('processing', $note);
        } elseif ($mode === 'completed' && $order->get_status() !== 'completed') {
            $order->update_status('completed', $note);
        } else {
            $order->add_order_note($note);
        }

        $order->save();

        Log::info('WooCommerce order marked paid from BCI status.', [
            'order_id' => $order->get_id(),
            'context' => $context,
        ]);
    }

    private function mark_failed(\WC_Order $order, array $status, string $context): void
    {
        $description = (string) ($status['actionCodeDescription'] ?? __('Payment declined or abandoned.', Config::TEXT_DOMAIN));
        $action_code = isset($status['actionCode']) ? (string) $status['actionCode'] : '';

        $note = sprintf(
            /* translators: 1: gateway decline message, 2: action code, 3: context. */
            __('BCI payment failed: %1$s (actionCode: %2$s, %3$s).', Config::TEXT_DOMAIN),
            $description,
            $action_code !== '' ? $action_code : __('n/a', Config::TEXT_DOMAIN),
            $context
        );

        if (!$order->has_status('failed')) {
            $order->update_status('failed', $note);
        } else {
            $order->add_order_note($note);
        }

        $order->save();

        Log::info('WooCommerce order marked failed from BCI status.', [
            'order_id' => $order->get_id(),
            'context' => $context,
            'action_code' => $action_code,
        ]);
    }

    private function mark_refunded(\WC_Order $order, string $context): void
    {
        $note = sprintf(
            __('BCI reports this payment was refunded in the merchant portal (%s).', Config::TEXT_DOMAIN),
            $context
        );

        if (!$order->has_status('refunded')) {
            $order->update_status('refunded', $note);
        } else {
            $order->add_order_note($note);
        }

        $order->save();
    }

    private function mark_cancelled_or_failed(\WC_Order $order, string $context): void
    {
        $note = sprintf(
            __('BCI reports this payment was reversed or cancelled (%s).', Config::TEXT_DOMAIN),
            $context
        );

        if ($order->is_paid()) {
            $order->update_status('cancelled', $note);
        } else {
            $order->update_status('failed', $note);
        }

        $order->save();
    }

    private function maybe_store_binding(\WC_Order $order, array $status): void
    {
        $binding_id = $this->extract_binding_id($status);
        if ($binding_id === '') {
            return;
        }

        $order->update_meta_data(Config::META_BINDING_ID, $binding_id);

        $client_id = (string) ($status['bindingInfo']['clientId'] ?? $order->get_meta(Config::META_CLIENT_ID, true));
        if ($client_id !== '') {
            $order->update_meta_data(Config::META_CLIENT_ID, $client_id);
        }

        $masked_pan = Config::mask_pan((string) ($status['cardAuthInfo']['maskedPan'] ?? $status['cardAuthInfo']['pan'] ?? ''));
        if ($masked_pan !== '') {
            $order->update_meta_data(Config::META_MASKED_PAN, $masked_pan);
        }

        $expiry = (string) ($status['cardAuthInfo']['expiration'] ?? '');
        if ($expiry !== '') {
            $order->update_meta_data(Config::META_CARD_EXPIRY, $expiry);
        }

        if (class_exists(__NAMESPACE__ . '\Tokens')) {
            (new Tokens(Config::GATEWAY_ID))->store_order_token_data($order, [
                'binding_id' => $binding_id,
                'client_id' => $client_id,
                'masked_pan' => $masked_pan,
                'expiry' => $expiry,
                'environment' => (string) $order->get_meta(Config::META_ENVIRONMENT, true),
            ]);
        }

        // mark_paid() has already saved the order, and Tokens only saves when its own
        // change detection fires — which it cannot, because it compares against the
        // meta staged just above. Persist the binding here so it never gets dropped.
        $order->save();
    }

    private function extract_binding_id(array $status): string
    {
        if (!empty($status['bindingId'])) {
            return sanitize_text_field((string) $status['bindingId']);
        }

        if (!empty($status['bindingInfo']['bindingId'])) {
            return sanitize_text_field((string) $status['bindingInfo']['bindingId']);
        }

        return '';
    }

    private function fire_resolved_hook(\WC_Order $order, string $resolution, array $status, string $context): void
    {
        if (function_exists('do_action')) {
            do_action('bci_woo_payment_status_resolved', $order, $resolution, $status, $context);
        }
    }
}
