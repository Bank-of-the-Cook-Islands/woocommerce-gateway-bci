<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * What a one-off registration attempt came back with.
 *
 * A registration either produced a hosted payment form to send the customer to,
 * or it produced a reason the customer has to be told. There is no third state:
 * the reference BPC answers with is recorded before this object exists, and a
 * failure records nothing at all, so no caller can be handed a half-registered
 * payment to decide about.
 */
final class Registration_Result
{
    /** The hosted payment form to send the customer to. Empty on failure. */
    private string $redirect_url;

    /** Why the payment could not be started, for the customer. Empty on success. */
    private string $error_message;

    private function __construct(string $redirect_url, string $error_message)
    {
        $this->redirect_url = $redirect_url;
        $this->error_message = $error_message;
    }

    public static function succeeded(string $redirect_url): self
    {
        return new self($redirect_url, '');
    }

    public static function failed(string $error_message): self
    {
        return new self('', $error_message);
    }

    public function is_successful(): bool
    {
        return $this->error_message === '';
    }

    public function redirect_url(): string
    {
        return $this->redirect_url;
    }

    public function error_message(): string
    {
        return $this->error_message;
    }
}
