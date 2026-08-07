<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The BCI payment state carried by a WooCommerce order.
 *
 * Every _bci_woo_* meta key, and every rule about the shape of what is stored
 * under it, lives in this file and nowhere else. Callers ask for what they need
 * — the gateway reference, the environment the payment was taken in, the stored
 * credential — and never name a key or decide a format, so the plugin cannot
 * disagree with itself about how order state is written.
 *
 * The keys themselves are unchanged from v1.0.x and stay readable in the
 * database for support queries; what changed is that only this module knows
 * them. Values written by earlier versions are normalised on the way out, so a
 * card expiry stored raw before this module existed reads back in one format.
 *
 * Writers stage onto the order the way WC_Data expects: record_*() only calls
 * update_meta_data(), and nothing reaches the database until save() — or the
 * caller's own save() — runs. A record_*() call that would not change anything
 * is dropped, so has_changes() answers whether a save is worth making.
 *
 * Subscriptions are WC_Order descendants and carry the same credential meta, so
 * anything order-like with get_meta()/update_meta_data() is accepted here.
 */
final class Order_State
{
    /** The two environments a payment can belong to. */
    public const LIVE = 'live';
    public const SANDBOX = 'sandbox';

    private const META_MD_ORDER = '_bci_woo_md_order';
    private const META_ORDER_NUMBER = '_bci_woo_order_number';
    private const META_ENVIRONMENT = '_bci_woo_environment';
    private const META_LAST_STATUS = '_bci_woo_last_status';
    private const META_LAST_ACTION_CODE = '_bci_woo_last_action_code';
    private const META_BINDING_ID = '_bci_woo_binding_id';
    private const META_CLIENT_ID = '_bci_woo_client_id';
    private const META_MASKED_PAN = '_bci_woo_masked_pan';
    private const META_CARD_EXPIRY = '_bci_woo_card_expiry';
    private const META_BINDING_MISSING_NOTE = '_bci_woo_binding_missing_note_added';

    /** @var object|null The order or subscription this state belongs to. */
    private $order;

    /** @var bool Whether a record_*() call has staged anything not yet saved. */
    private $changed = false;

    /**
     * @param mixed $order A WC_Order, a WC_Subscription, or anything order-like.
     */
    private function __construct($order)
    {
        $this->order = is_object($order) ? $order : null;
    }

    /**
     * The BCI state of an order.
     *
     * @param mixed $order A WC_Order, a WC_Subscription, or anything order-like.
     */
    public static function for($order): self
    {
        return new self($order);
    }

    /** The BPC order id (mdOrder) the payment was registered as. */
    public function md_order(): string
    {
        return $this->read(self::META_MD_ORDER);
    }

    /** The orderNumber sent to BPC, which callbacks are matched against. */
    public function order_number(): string
    {
        return $this->read(self::META_ORDER_NUMBER);
    }

    /**
     * Whether this order can be asked about at the gateway.
     *
     * A payment that never reached BPC has no reference to quote, so there is
     * nothing to resolve, hold a cancellation for, or schedule a check on.
     */
    public function is_resolvable(): bool
    {
        return $this->md_order() !== '';
    }

    /**
     * The environment this order's payments belong to.
     *
     * The environment is decided once, when the payment is registered, and read
     * back from the order forever after: a renewal must reach the same BPC
     * instance the binding was created on however the plugin is configured
     * today. A renewal order is created fresh by WooCommerce Subscriptions and
     * carries none of its own until it is charged, so the subscription it
     * renews is passed as the fallback. Only an order with no BCI state at all
     * falls through to the plugin's current setting.
     *
     * @param mixed $fallback An order-like object to consult when this one is silent.
     */
    public function environment($fallback = null): string
    {
        $environment = $this->stored_environment();

        if ($environment === '' && $fallback !== null) {
            $environment = self::for($fallback)->stored_environment();
        }

        return $environment !== '' ? $environment : Api::current_environment();
    }

    /** Whether the order itself records an environment, rather than inheriting one. */
    public function has_environment(): bool
    {
        return $this->stored_environment() !== '';
    }

    /** The BPC clientId the stored credential belongs to. */
    public function client_id(): string
    {
        return $this->read(self::META_CLIENT_ID);
    }

    /** The BPC bindingId of the stored credential. */
    public function binding_id(): string
    {
        return $this->read(self::META_BINDING_ID);
    }

    /** The masked card label, safe to display. */
    public function masked_pan(): string
    {
        return $this->read(self::META_MASKED_PAN);
    }

    /** The card expiry, always YYYYMM however it was stored. */
    public function card_expiry(): string
    {
        return self::normalise_expiry($this->read(self::META_CARD_EXPIRY));
    }

    /** The last BPC order status recorded for this order. */
    public function last_status(): int
    {
        return (int) $this->read(self::META_LAST_STATUS);
    }

    /**
     * The stored credential, in the shape the token helpers pass around.
     *
     * @return array{binding_id: string, client_id: string, masked_pan: string, expiry: string, environment: string}
     */
    public function stored_card(): array
    {
        return [
            'binding_id'  => $this->binding_id(),
            'client_id'   => $this->client_id(),
            'masked_pan'  => $this->masked_pan(),
            'expiry'      => $this->card_expiry(),
            'environment' => $this->stored_environment(),
        ];
    }

    /** Whether the customer has already been told no card could be stored. */
    public function binding_missing_noted(): bool
    {
        return $this->read(self::META_BINDING_MISSING_NOTE) === 'yes';
    }

    /**
     * Records a payment registered at BPC.
     *
     * The environment belongs to the registration, which is why it is recorded
     * with the reference rather than looked up later.
     */
    public function record_registration(string $md_order, string $order_number, string $environment, string $client_id = ''): self
    {
        $this->record_md_order($md_order);
        $this->write(self::META_ORDER_NUMBER, $this->clean($order_number));
        $this->write(self::META_ENVIRONMENT, self::normalise_environment($environment));
        $this->write(self::META_CLIENT_ID, $this->clean($client_id));

        return $this;
    }

    /**
     * Records the gateway reference on its own.
     *
     * A callback can be the first thing to tell the plugin which BPC order a
     * WooCommerce order became, when the redirect that would have recorded it
     * never made it back.
     */
    public function record_md_order(string $md_order): self
    {
        $this->write(self::META_MD_ORDER, $this->clean($md_order));

        return $this;
    }

    /** Records the gateway state an order was last seen in, always as integers. */
    public function record_status(int $order_status, int $action_code): self
    {
        $this->write(self::META_LAST_STATUS, $order_status);
        $this->write(self::META_LAST_ACTION_CODE, $action_code);

        return $this;
    }

    /** Records the BPC clientId a stored credential will be created against. */
    public function record_client_id(string $client_id): self
    {
        $this->write(self::META_CLIENT_ID, $this->clean($client_id));

        return $this;
    }

    /**
     * Records a stored credential, in the shape the token helpers pass around.
     *
     * The card label is masked and the expiry normalised here, so it makes no
     * difference which caller writes last or what shape the gateway answered in.
     *
     * @param array $card binding_id, client_id, masked_pan, expiry, environment.
     */
    public function record_card(array $card): self
    {
        $this->write(self::META_BINDING_ID, $this->clean($card['binding_id'] ?? ''));
        $this->write(self::META_CLIENT_ID, $this->clean($card['client_id'] ?? ''));
        $this->write(self::META_MASKED_PAN, Config::mask_pan($this->clean($card['masked_pan'] ?? '')));
        $this->write(self::META_CARD_EXPIRY, self::normalise_expiry($card['expiry'] ?? ''));
        $this->write(self::META_ENVIRONMENT, self::normalise_environment($card['environment'] ?? ''));

        return $this;
    }

    /** Records that the customer has been told no card could be stored. */
    public function note_binding_missing(): self
    {
        $this->write(self::META_BINDING_MISSING_NOTE, 'yes');

        return $this;
    }

    /** Whether anything staged here still needs saving. */
    public function has_changes(): bool
    {
        return $this->changed;
    }

    /**
     * Persists what this state staged, if anything.
     *
     * @return bool Whether a save was made.
     */
    public function save(): bool
    {
        if (!$this->changed || $this->order === null || !method_exists($this->order, 'save')) {
            return false;
        }

        $this->order->save();
        $this->changed = false;

        return true;
    }

    /**
     * The meta clause that selects orders holding a BCI gateway reference.
     *
     * @return array A wc_get_orders() meta_query entry.
     */
    public static function gateway_reference_query(): array
    {
        return [
            'key'     => self::META_MD_ORDER,
            'compare' => 'EXISTS',
        ];
    }

    /** The order a BPC orderNumber was registered for, if it is still around. */
    public static function find_by_order_number(string $order_number): ?\WC_Order
    {
        return self::find_by_meta(self::META_ORDER_NUMBER, $order_number);
    }

    /** The order a BPC mdOrder belongs to, if it is still around. */
    public static function find_by_md_order(string $md_order): ?\WC_Order
    {
        return self::find_by_meta(self::META_MD_ORDER, $md_order);
    }

    private static function find_by_meta(string $meta_key, string $meta_value): ?\WC_Order
    {
        if ($meta_value === '' || !\function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders([
            'limit'      => 1,
            'return'     => 'objects',
            'type'       => 'shop_order',
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => [
                [
                    'key'     => $meta_key,
                    'value'   => $meta_value,
                    'compare' => '=',
                ],
            ],
        ]);

        $order = is_array($orders) ? ($orders[0] ?? null) : null;

        return $order instanceof \WC_Order ? $order : null;
    }

    /** The environment recorded on this order, or '' when it records none we know. */
    private function stored_environment(): string
    {
        return self::normalise_environment($this->read(self::META_ENVIRONMENT));
    }

    /**
     * @param mixed $value
     */
    private static function normalise_environment($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, [self::LIVE, self::SANDBOX], true) ? $value : '';
    }

    /**
     * Card expiries reach the plugin as YYYYMM, as MMYY from token displays, and
     * with punctuation from callback labels. YYYYMM is what gets stored.
     *
     * @param mixed $value
     */
    private static function normalise_expiry($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 4) {
            return '20' . substr($digits, 2, 2) . substr($digits, 0, 2);
        }

        return strlen($digits) >= 6 ? substr($digits, 0, 6) : $digits;
    }

    private function read(string $key): string
    {
        if ($this->order === null || !method_exists($this->order, 'get_meta')) {
            return '';
        }

        return $this->clean($this->order->get_meta($key));
    }

    /**
     * Stages a value, unless there is nothing to say or nothing to change.
     *
     * @param string|int $value
     */
    private function write(string $key, $value): void
    {
        if ($value === '' || $this->order === null || !method_exists($this->order, 'update_meta_data')) {
            return;
        }

        if (method_exists($this->order, 'get_meta') && (string) $this->order->get_meta($key) === (string) $value) {
            return;
        }

        $this->order->update_meta_data($key, $value);
        $this->changed = true;
    }

    /**
     * @param mixed $value
     */
    private function clean($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = trim((string) $value);

        if (\function_exists('sanitize_text_field')) {
            return sanitize_text_field($value);
        }

        return trim(strip_tags($value));
    }
}
