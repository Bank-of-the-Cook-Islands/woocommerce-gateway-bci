<?php

/**
 * Cook Islands addresses need neither a state nor a postcode at checkout.
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $bci_test_filters = [];
    $bci_test_relax = true;

    function add_filter(string $hook, $callback, int $priority = 10): void
    {
        global $bci_test_filters;
        $bci_test_filters[] = [$hook, $callback, $priority];
    }

    function apply_filters(string $hook, $value)
    {
        global $bci_test_relax;
        return $hook === 'bci_woo_relax_cook_islands_address' ? $bci_test_relax : $value;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

namespace BCI\Woo {
    require dirname(__DIR__) . '/includes/class-config.php';
    require dirname(__DIR__) . '/includes/class-address.php';

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

    Address::register();
    assert_same(
        [['woocommerce_get_country_locale', [Address::class, 'relax_cook_islands'], 20]],
        $GLOBALS['bci_test_filters'],
        'hooked onto the country locale'
    );

    $locale = [
        'NZ' => ['postcode' => ['required' => true], 'state' => ['label' => 'Region', 'required' => false]],
    ];

    $relaxed = Address::relax_cook_islands($locale);
    assert_same(false, $relaxed['CK']['state']['required'], 'CK state optional');
    assert_same('Island', $relaxed['CK']['state']['label'], 'CK state labelled Island');
    assert_same(false, $relaxed['CK']['postcode']['required'], 'CK postcode optional');
    assert_same($locale['NZ'], $relaxed['NZ'], 'other countries untouched');

    // Whatever else a store or another plugin set for CK survives.
    $relaxed = Address::relax_cook_islands(['CK' => ['postcode' => ['hidden' => true], 'city' => ['label' => 'Village']]]);
    assert_same(true, $relaxed['CK']['postcode']['hidden'], 'existing CK postcode settings kept');
    assert_same('Village', $relaxed['CK']['city']['label'], 'existing CK fields kept');
    assert_same(false, $relaxed['CK']['postcode']['required'], 'existing CK postcode still made optional');

    // Switched off by the store.
    $GLOBALS['bci_test_relax'] = false;
    assert_same($locale, Address::relax_cook_islands($locale), 'filter can turn it off');
    $GLOBALS['bci_test_relax'] = true;

    // Something else broke the locale: leave it for WooCommerce to deal with.
    assert_same(null, Address::relax_cook_islands(null), 'non-array locale passed through');

    echo "Cook Islands address tests passed.\n";
}
