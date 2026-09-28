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

    /** How a resolution's attempt to take the order's lock turned out. */
    private const LOCK_HELD = 'held';
    private const LOCK_BUSY = 'busy';
    private const LOCK_UNAVAILABLE = 'unavailable';

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
     * The browser return and the callback for one payment usually arrive in
     * the same second. Unserialised, both read the order while it is pending,
     * both ask BPC and both apply the answer: two status transitions, two sets
     * of notes, and on the paid path payment_complete() run twice. So each
     * resolution holds a per-order lock, re-reads the order once it has it,
     * and leaves the order alone if the request it waited on settled it.
     *
     * @param string $context One of the context constants above.
     * @return string The Resolution outcome the status was classified as.
     * @throws \RuntimeException When another request holds the order's lock for
     *                           longer than a gateway request can take.
     */
    public static function resolve(\WC_Order $order, string $context): string
    {
        $lock = self::acquire_lock($order);
        if ($lock === self::LOCK_BUSY) {
            throw new \RuntimeException('Another request is still resolving this order.');
        }

        try {
            if ($lock === self::LOCK_HELD) {
                self::refresh($order);

                if (!self::is_resolvable($order, $context === self::CALLBACK)) {
                    Log::info('BCI order was settled by a concurrent request; not resolving again.', [
                        'order_id' => $order->get_id(),
                        'context'  => $context,
                        'status'   => $order->get_status(),
                    ]);

                    return self::settled_outcome($order);
                }
            }

            return (string) (new Status_Resolver())->resolve($order, $context);
        } finally {
            if ($lock === self::LOCK_HELD) {
                self::release_lock($order);
            }
        }
    }

    /**
     * Takes a MySQL named lock for the order.
     *
     * Long enough to outlast the other request's gateway call. A database that
     * cannot answer GET_LOCK leaves resolution unserialised, as it always was,
     * rather than refusing to resolve at all.
     */
    private static function acquire_lock(\WC_Order $order): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var')) {
            return self::LOCK_UNAVAILABLE;
        }

        $acquired = $wpdb->get_var($wpdb->prepare(
            'SELECT GET_LOCK(%s, %d)',
            self::lock_name($order),
            Config::API_TIMEOUT + 5
        ));

        if ($acquired === null) {
            return self::LOCK_UNAVAILABLE;
        }

        return (string) $acquired === '1' ? self::LOCK_HELD : self::LOCK_BUSY;
    }

    private static function release_lock(\WC_Order $order): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name($order)));
    }

    /** Named locks are server-wide, so the table prefix keeps sites on one server apart. */
    private static function lock_name(\WC_Order $order): string
    {
        global $wpdb;

        return substr((string) ($wpdb->prefix ?? '') . 'bci_woo_resolve_' . $order->get_id(), 0, 64);
    }

    /**
     * Re-reads the order into the caller's own object.
     *
     * In place rather than a fresh wc_get_order(), because every caller goes
     * on to act on the object it passed in — the browser return redirects on
     * it, the cancellation filter reads its gateway status, WooCommerce itself
     * holds it while deciding whether to cancel. The caches are cleared first
     * because this request may already hold the order as it was before the
     * lock was granted.
     */
    private static function refresh(\WC_Order $order): void
    {
        $order_id = $order->get_id();

        if (function_exists('clean_post_cache')) {
            clean_post_cache($order_id);
        }

        $order_cache = 'Automattic\\WooCommerce\\Caches\\OrderCache';
        if (function_exists('wc_get_container') && class_exists($order_cache)) {
            try {
                wc_get_container()->get($order_cache)->remove($order_id);
            } catch (\Throwable $exception) {
                // An unavailable order cache only means there is nothing to clear.
            }
        }

        $order->get_data_store()->read($order);
        $order->read_meta_data(true);
    }

    /** What an order a concurrent request already settled amounts to. */
    private static function settled_outcome(\WC_Order $order): string
    {
        if ($order->is_paid()) {
            return Resolution::COMPLETED;
        }

        if ($order->has_status('refunded')) {
            return Resolution::REFUNDED;
        }

        return Resolution::CANCELLED;
    }
}
