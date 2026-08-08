<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The one way this plugin asks BPC what happened to a payment.
 *
 * Four things start a resolution — the customer's browser returning from the
 * hosted form, a BPC callback, the scheduled sweep, and the merchant pressing
 * Check Pending Orders — and each used to carry its own copy of the pipeline.
 * The copies disagreed about the only interesting question, which orders are
 * still worth asking about: the browser return resolved any unpaid order, the
 * callback resolved open *or* paid ones, and the scheduler guarded on nothing
 * but the gateway reference. That inversion is the reason this module exists.
 *
 * Callers stay adapters. They find the order however their surface finds one,
 * ask is_resolvable(), and report the answer as a redirect, an HTTP status, a
 * log line or an AJAX payload. Whether the gateway is worth asking, and who
 * asks it, is decided here and nowhere else.
 */
final class Payment_Resolution
{
    /** The contexts a resolution runs under, quoted verbatim in notes and logs. */
    public const BROWSER_RETURN = 'browser return';
    public const CALLBACK = 'gateway callback';
    public const SCHEDULED_CHECK = 'scheduled status check';
    public const CANCELLATION_CHECK = 'unpaid order cancellation check';

    /**
     * WooCommerce statuses a gateway answer can still move an order out of.
     *
     * Cancelled is on the list because a customer, typically a guest, can finish
     * paying on the hosted form after WooCommerce has given up on the order; the
     * late Deposited answer is what recovers it.
     */
    private const OPEN_STATUSES = ['pending', 'failed', 'on-hold', 'cancelled'];

    /**
     * Whether asking BPC about this order can still change anything.
     *
     * An order with no gateway reference was never registered, so there is
     * nothing to ask about. Beyond that the rule is that polls and pushes are
     * not the same question. A poll only looks at orders still open, because
     * re-reading a settled payment can only repeat what the order already says.
     * A push is BPC telling us something changed, so a paid order is answered
     * too — that is how a refund or reversal taken in the merchant portal
     * reaches WooCommerce at all.
     *
     * @param mixed $order    A WC_Order, or anything at all.
     * @param bool  $notified Whether BPC itself announced a change to this order.
     */
    public static function is_resolvable($order, bool $notified = false): bool
    {
        if (!$order instanceof \WC_Order || !Order_State::for($order)->is_resolvable()) {
            return false;
        }

        if ($order->has_status(self::OPEN_STATUSES)) {
            return true;
        }

        return $notified && $order->is_paid();
    }

    /**
     * Reads the order's current state from BPC and applies it.
     *
     * @param string $context One of the context constants above.
     * @return string The Resolution outcome the status was classified as.
     */
    public static function resolve(\WC_Order $order, string $context): string
    {
        return (string) (new Status_Resolver())->resolve($order, $context);
    }
}
