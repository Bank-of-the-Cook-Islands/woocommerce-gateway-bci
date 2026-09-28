<?php

namespace BCI\Woo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cook Islands addresses, as WooCommerce asks for them.
 *
 * WooCommerce ships no address locale for the Cook Islands, so its defaults
 * apply and both State / County and Postcode / ZIP are required. Neither exists
 * here: customers invent a value to get past checkout, and an invented state is
 * what BPC used to reject (Registration::billing_payer_data() omits it for CK).
 * This makes both optional for CK, and calls the state field what it is used
 * for. Both the classic and the Blocks checkout read the country locale, so
 * both follow.
 *
 * A store that wants the fields required anyway can return false from the
 * bci_woo_relax_cook_islands_address filter.
 */
final class Address
{
    public static function register(): void
    {
        add_filter('woocommerce_get_country_locale', [__CLASS__, 'relax_cook_islands'], 20);
    }

    /**
     * @param mixed $locale Address field overrides, keyed by country code.
     * @return mixed
     */
    public static function relax_cook_islands($locale)
    {
        if (!is_array($locale) || !apply_filters('bci_woo_relax_cook_islands_address', true)) {
            return $locale;
        }

        $cook_islands = isset($locale['CK']) && is_array($locale['CK']) ? $locale['CK'] : [];

        $locale['CK'] = array_replace_recursive($cook_islands, [
            'state' => [
                'label' => __('Island', Config::TEXT_DOMAIN),
                'required' => false,
            ],
            'postcode' => [
                'required' => false,
            ],
        ]);

        return $locale;
    }
}
