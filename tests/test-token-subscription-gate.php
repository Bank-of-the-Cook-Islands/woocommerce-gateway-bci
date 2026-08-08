<?php

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_options = [];
    $bci_test_saved_tokens = [];

    function get_option(string $key, $default = false)
    {
        global $bci_test_options;
        return $bci_test_options[$key] ?? $default;
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    class WC_Order
    {
        public array $meta = [];
        public int $customer_id = 0;
        public int $saves = 0;

        public function __construct(int $customer_id = 0)
        {
            $this->customer_id = $customer_id;
        }

        public function get_id(): int
        {
            return 4242;
        }

        public function get_customer_id(): int
        {
            return $this->customer_id;
        }

        public function get_billing_email(): string
        {
            return 'shopper@example.test';
        }

        public function get_meta(string $key, bool $single = true)
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save(): int
        {
            $this->saves++;
            return $this->get_id();
        }
    }

    class WC_Payment_Token_CC
    {
        private int $id = 0;
        private string $token = '';
        private string $gateway_id = '';
        private int $user_id = 0;
        private string $last4 = '';
        private string $expiry_year = '';
        private string $expiry_month = '';
        private string $card_type = '';

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_token(): string
        {
            return $this->token;
        }

        public function get_user_id(): int
        {
            return $this->user_id;
        }

        public function get_gateway_id(): string
        {
            return $this->gateway_id;
        }

        public function get_last4(): string
        {
            return $this->last4;
        }

        public function set_token(string $token): void
        {
            $this->token = $token;
        }

        public function set_gateway_id(string $gateway_id): void
        {
            $this->gateway_id = $gateway_id;
        }

        public function set_user_id(int $user_id): void
        {
            $this->user_id = $user_id;
        }

        public function set_last4(string $last4): void
        {
            $this->last4 = $last4;
        }

        public function set_expiry_year(string $year): void
        {
            $this->expiry_year = $year;
        }

        public function set_expiry_month(string $month): void
        {
            $this->expiry_month = $month;
        }

        public function set_card_type(string $card_type): void
        {
            $this->card_type = $card_type;
        }

        public function save(): int
        {
            global $bci_test_saved_tokens;

            $this->id = count($bci_test_saved_tokens) + 1;
            $bci_test_saved_tokens[] = $this;

            return $this->id;
        }
    }

    class WC_Payment_Tokens
    {
        public static function get_customer_tokens(int $user_id, string $gateway_id = ''): array
        {
            global $bci_test_saved_tokens;

            $matches = [];
            foreach ($bci_test_saved_tokens as $token) {
                if ($token->get_user_id() === $user_id && $token->get_gateway_id() === $gateway_id) {
                    $matches[] = $token;
                }
            }

            return $matches;
        }
    }
}

namespace BCI\Woo {
    /** Stands in for the plugin logger the bootstrap always loads. */
    final class Log
    {
        public static function __callStatic(string $level, array $args): void {}
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-api.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';
    require dirname(__DIR__) . '/includes/class-tokens.php';

    function set_subscriptions_enabled(bool $enabled): void
    {
        global $bci_test_options;
        $bci_test_options[Config::OPTION_KEY] = ['enable_subscriptions' => $enabled ? 'yes' : 'no'];
    }

    function token_data(): array
    {
        return [
            'binding_id' => 'binding-abc-123',
            'client_id'  => 'wc_customer_7',
            // Sanctioned synthetic placeholder; never a real PAN.
            'masked_pan' => '5538070000000000',
            'expiry'     => '202912',
            'card_type'  => 'mastercard',
        ];
    }

    function assert_same($expected, $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s, got %s',
                $label,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    global $bci_test_saved_tokens;

    // Subscriptions disabled (the v1.0 default): a one-off payment that comes back with
    // binding data must still record the order meta, but must not create a WC token.
    set_subscriptions_enabled(false);
    $tokens = new Tokens(Config::GATEWAY_ID);
    $order  = new \WC_Order(7);

    assert_same(true, $tokens->store_order_token_data($order, token_data()), 'order meta is stored');
    assert_same([], $bci_test_saved_tokens, 'no WC payment token is created while subscriptions are disabled');
    assert_same('binding-abc-123', $order->get_meta('_bci_woo_binding_id'), 'binding id meta is stored');

    // Subscriptions enabled: tokenisation is supported, so the token is created as before.
    set_subscriptions_enabled(true);
    $tokens = new Tokens(Config::GATEWAY_ID);
    $order  = new \WC_Order(7);

    $tokens->store_order_token_data($order, token_data());
    assert_same(1, count($bci_test_saved_tokens), 'a WC payment token is created while subscriptions are enabled');
    assert_same('0000', $bci_test_saved_tokens[0]->get_last4(), 'token carries the last four digits');

    // The WooCommerce token id is not mirrored into order meta: nothing ever read
    // it back, and the tokens API is the record of which cards a customer has.
    assert_same('', $order->get_meta('_bci_woo_wc_token_id'), 'no WC token id meta is written');

    // Repeat captures reuse the existing token rather than stacking duplicates.
    $repeat = new \WC_Order(7);
    $tokens->store_order_token_data($repeat, token_data());
    assert_same(1, count($bci_test_saved_tokens), 'an existing token is reused');

    // Guests never get a token, enabled or not.
    $guest = new \WC_Order(0);
    $tokens->store_order_token_data($guest, token_data());
    assert_same(1, count($bci_test_saved_tokens), 'guest orders never create a token');

    // Credential data recorded onto a subscription must actually be saved, not
    // merely staged — the never-saved-binding failure class of issue #9.
    $subscription = new \WC_Order(7);
    assert_same(true, $tokens->store_subscription_token_data($subscription, token_data()), 'subscription credential recording reports a change');
    assert_same('binding-abc-123', (string) $subscription->get_meta('_bci_woo_binding_id'), 'subscription carries the binding id');
    if ($subscription->saves < 1) {
        throw new \RuntimeException('subscription meta was staged but never saved');
    }

    echo "Token subscription gate tests passed.\n";
}
