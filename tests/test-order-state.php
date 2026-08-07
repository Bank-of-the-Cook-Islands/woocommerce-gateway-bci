<?php

/**
 * Order_State owns the _bci_woo_* meta keys and the shape of what is stored
 * under them.
 *
 * The keys are spelled out literally here on purpose: they are the on-disk
 * contract with orders written by v1.0.x, and this is the one place the plugin
 * is still allowed to name them.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_current_environment = 'live';

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    /**
     * Mimics WC_Data: update_meta_data() stages, get_meta() reads the staged
     * value back, and save() is what persists it.
     */
    class WC_Order
    {
        public array $persisted = [];
        public array $staged = [];
        public int $saves = 0;

        public function __construct(array $persisted = [])
        {
            $this->persisted = $persisted;
        }

        public function get_id(): int
        {
            return 21;
        }

        public function get_meta(string $key, bool $single = true)
        {
            if (array_key_exists($key, $this->staged)) {
                return $this->staged[$key];
            }

            return $this->persisted[$key] ?? '';
        }

        public function update_meta_data(string $key, $value): void
        {
            $this->staged[$key] = $value;
        }

        public function save(): int
        {
            $this->persisted = array_merge($this->persisted, $this->staged);
            $this->staged = [];
            ++$this->saves;

            return $this->get_id();
        }
    }

    /** A subscription is order-like, and is all Order_State asks of one. */
    class WC_Subscription_Stub
    {
        public array $meta = [];

        public function __construct(array $meta = [])
        {
            $this->meta = $meta;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }
    }
}

namespace BCI\Woo {
    final class Api
    {
        public static function current_environment(): string
        {
            global $bci_test_current_environment;

            return $bci_test_current_environment;
        }
    }

    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-order-state.php';

    function assert_same($expected, $actual, string $case): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf(
                '%s: expected %s, got %s',
                $case,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    function set_current_environment(string $environment): void
    {
        global $bci_test_current_environment;
        $bci_test_current_environment = $environment;
    }

    // What an order written by v1.0.x looks like, read back through the module.
    $stored = new \WC_Order([
        '_bci_woo_md_order' => 'md-9001',
        '_bci_woo_order_number' => 'WC21-20260807',
        '_bci_woo_environment' => 'sandbox',
        '_bci_woo_last_status' => '6',
        '_bci_woo_binding_id' => 'binding-9001',
        '_bci_woo_client_id' => 'wc_customer_21',
        '_bci_woo_masked_pan' => '553807******0000',
        '_bci_woo_card_expiry' => '1230',
    ]);
    $state = Order_State::for($stored);

    foreach ([
        ['md_order', 'md-9001'],
        ['order_number', 'WC21-20260807'],
        ['environment', 'sandbox'],
        ['client_id', 'wc_customer_21'],
        ['binding_id', 'binding-9001'],
        ['masked_pan', '553807******0000'],
        // Written raw as MMYY before the module existed; read back as YYYYMM.
        ['card_expiry', '203012'],
    ] as [$reader, $expected]) {
        assert_same($expected, $state->{$reader}(), 'v1.0.x meta read through ' . $reader . '()');
    }

    assert_same(6, $state->last_status(), 'v1.0.x last status reads back as an int');
    assert_same(true, $state->is_resolvable(), 'an order with a gateway reference is resolvable');
    assert_same(false, Order_State::for(new \WC_Order())->is_resolvable(), 'an order with no reference is not resolvable');
    assert_same(false, Order_State::for(null)->is_resolvable(), 'a missing order is not resolvable');

    assert_same(
        [
            'binding_id' => 'binding-9001',
            'client_id' => 'wc_customer_21',
            'masked_pan' => '553807******0000',
            'expiry' => '203012',
            'environment' => 'sandbox',
        ],
        $state->stored_card(),
        'the stored credential reads back whole'
    );

    // Which environment an order's payments belong to: its own answer first, then
    // the subscription it renews, and only then how the plugin is set up today.
    set_current_environment('live');

    $sandbox_subscription = new \WC_Subscription_Stub(['_bci_woo_environment' => 'sandbox']);
    $live_subscription = new \WC_Subscription_Stub(['_bci_woo_environment' => 'live']);

    foreach ([
        ['sandbox', null, 'sandbox', 'the order decides'],
        ['', $sandbox_subscription, 'sandbox', 'a renewal order inherits from its subscription'],
        // A retry reaches the same renewal order, which now carries the environment
        // its first attempt recorded. That answer outranks the subscription's even
        // once the subscription has been re-stamped, so the binding is charged
        // against the host it was created on.
        ['sandbox', $live_subscription, 'sandbox', 'a retried renewal keeps its own environment over a re-stamped subscription'],
        ['live', $sandbox_subscription, 'live', 'the order outranks the subscription the other way round too'],
        ['', null, 'live', 'nothing recorded anywhere falls back to the current setting'],
        ['nonsense', null, 'live', 'an unreadable environment is not trusted'],
        ['SANDBOX', null, 'sandbox', 'case is not part of the value'],
    ] as [$recorded, $fallback, $expected, $case]) {
        $order = new \WC_Order($recorded === '' ? [] : ['_bci_woo_environment' => $recorded]);
        assert_same($expected, Order_State::for($order)->environment($fallback), 'environment: ' . $case);
    }

    // A sandbox order stays a sandbox order after the merchant leaves test mode.
    set_current_environment('live');
    assert_same(
        'sandbox',
        Order_State::for(new \WC_Order(['_bci_woo_environment' => 'sandbox']))->environment(),
        'environment: test mode off does not move an existing order to live'
    );

    assert_same(true, $state->has_environment(), 'an order carrying an environment says so');
    assert_same(false, Order_State::for(new \WC_Order())->has_environment(), 'an order carrying none says so');

    // Registration records everything the gateway answered with, under the keys
    // the rest of the plugin already has in its database.
    $registered = new \WC_Order();
    Order_State::for($registered)
        ->record_registration('md-4242', 'WC21-20260808', 'sandbox', 'wc_guest_abc')
        ->save();

    assert_same([
        '_bci_woo_md_order' => 'md-4242',
        '_bci_woo_order_number' => 'WC21-20260808',
        '_bci_woo_environment' => 'sandbox',
        '_bci_woo_client_id' => 'wc_guest_abc',
    ], $registered->persisted, 'registration writes the v1.0.x keys');
    assert_same(1, $registered->saves, 'registration saves once');

    // Gateway state is always recorded as integers, whoever records it.
    $resolved = new \WC_Order();
    Order_State::for($resolved)->record_status(6, 116)->save();
    assert_same(6, $resolved->persisted['_bci_woo_last_status'] ?? null, 'last status is an int');
    assert_same(116, $resolved->persisted['_bci_woo_last_action_code'] ?? null, 'last action code is an int');

    // A payment that was never attempted is still a status worth recording.
    $registered_only = new \WC_Order();
    Order_State::for($registered_only)->record_status(0, 0)->save();
    assert_same(0, $registered_only->persisted['_bci_woo_last_status'] ?? null, 'a zero status is recorded');

    // The card is normalised on the way in, so it makes no difference which
    // caller writes last or what shape the gateway answered in.
    foreach ([
        // Sanctioned synthetic placeholder; never a real PAN.
        ['5538070000000000', '202812', '************0000', '202812', 'a raw pan and a YYYYMM expiry'],
        ['553807******0000', '1228', '553807******0000', '202812', 'an already masked pan and an MMYY expiry'],
        ['553807******0000', '12/28', '553807******0000', '202812', 'a punctuated expiry'],
    ] as [$pan, $expiry, $expected_pan, $expected_expiry, $case]) {
        $order = new \WC_Order();
        $state = Order_State::for($order)->record_card([
            'binding_id' => 'binding-1',
            'client_id' => 'wc_customer_21',
            'masked_pan' => $pan,
            'expiry' => $expiry,
            'environment' => 'sandbox',
        ]);
        $state->save();

        assert_same($expected_pan, $order->persisted['_bci_woo_masked_pan'] ?? '', 'card: ' . $case . ' (pan)');
        assert_same($expected_expiry, $order->persisted['_bci_woo_card_expiry'] ?? '', 'card: ' . $case . ' (expiry)');
    }

    // Two writers, one format: recording the same card twice through different
    // shaped payloads cannot leave the second spelling behind.
    $twice = new \WC_Order();
    Order_State::for($twice)->record_card(['binding_id' => 'binding-1', 'expiry' => '202812'])->save();
    $second = Order_State::for($twice)->record_card(['binding_id' => 'binding-1', 'expiry' => '12/28']);
    assert_same(false, $second->has_changes(), 'card: the same expiry in another shape is not a change');
    assert_same(false, $second->save(), 'card: nothing to save when nothing changed');
    assert_same('202812', $twice->persisted['_bci_woo_card_expiry'] ?? '', 'card: the stored format is unchanged');
    assert_same(1, $twice->saves, 'card: no redundant save');

    // Empty fields never blank out what is already recorded.
    $partial = new \WC_Order(['_bci_woo_client_id' => 'wc_customer_21']);
    Order_State::for($partial)->record_card(['binding_id' => 'binding-2'])->save();
    assert_same('wc_customer_21', $partial->persisted['_bci_woo_client_id'] ?? '', 'card: a missing field leaves meta alone');
    assert_same('binding-2', $partial->persisted['_bci_woo_binding_id'] ?? '', 'card: the field that was sent is recorded');

    // The gateway reference can arrive from a callback, without the rest.
    $callback_order = new \WC_Order();
    $callback_state = Order_State::for($callback_order);
    assert_same(false, $callback_state->is_resolvable(), 'an order with no reference cannot be resolved');
    $callback_state->record_md_order('md-late')->save();
    assert_same(true, $callback_state->is_resolvable(), 'a backfilled reference makes the order resolvable');
    assert_same('md-late', $callback_order->persisted['_bci_woo_md_order'] ?? '', 'the backfilled reference is persisted');

    // The customer is told once that no card could be stored.
    $unbound = new \WC_Order();
    $unbound_state = Order_State::for($unbound);
    assert_same(false, $unbound_state->binding_missing_noted(), 'no note has been added yet');
    $unbound_state->note_binding_missing()->save();
    assert_same(true, Order_State::for($unbound)->binding_missing_noted(), 'the note is remembered');
    assert_same('yes', $unbound->persisted['_bci_woo_binding_missing_note_added'] ?? '', 'the v1.0.x note key is used');

    // Anything order-like is readable; anything else is silent rather than fatal.
    assert_same('sandbox', Order_State::for($sandbox_subscription)->environment(), 'a subscription carries state too');
    assert_same('', Order_State::for('not an order')->md_order(), 'a non-order reads as empty');
    assert_same(false, Order_State::for($sandbox_subscription)->record_md_order('md-1')->save(), 'an unsaveable object is not saved');

    echo "Order state tests passed.\n";
}
