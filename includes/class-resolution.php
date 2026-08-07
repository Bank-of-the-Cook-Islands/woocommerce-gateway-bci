<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * What a BPC order status means, decided before anything is written anywhere.
 *
 * Status_Resolver::classify() produces one of these from a gateway payload and
 * Status_Resolver::apply() is the only thing allowed to act on it, so the
 * decision itself can be read — and tested — without a WooCommerce order.
 */
final class Resolution
{
    /** Outcomes, matching the strings Status_Resolver::resolve() returns to callers. */
    public const COMPLETED = 'completed';
    public const REFUNDED = 'refunded';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';
    public const PENDING = 'pending';
    public const ERROR = 'error';

    /** Why an ERROR outcome was reached. Empty for every other outcome. */
    public const REASON_GATEWAY_ERROR = 'gateway_error';
    public const REASON_MISSING_STATUS = 'missing_status';
    public const REASON_UNEXPECTED_STATUS = 'unexpected_status';

    /** The payload carried no orderStatus, so there is nothing to record. */
    public const NO_ORDER_STATUS = -1;

    public string $outcome;

    public int $order_status;

    public int $action_code;

    public string $reason;

    /** @var array The gateway payload this outcome was read from. */
    public array $status;

    public function __construct(
        string $outcome,
        array $status,
        int $order_status = self::NO_ORDER_STATUS,
        int $action_code = 0,
        string $reason = ''
    ) {
        $this->outcome = $outcome;
        $this->status = $status;
        $this->order_status = $order_status;
        $this->action_code = $action_code;
        $this->reason = $reason;
    }

    /**
     * Whether the gateway told us which state the order is in.
     *
     * False only for the two payload faults — an error code, or a response with
     * no orderStatus — where there is no gateway state to write to the order.
     */
    public function has_order_status(): bool
    {
        return $this->order_status !== self::NO_ORDER_STATUS;
    }
}
