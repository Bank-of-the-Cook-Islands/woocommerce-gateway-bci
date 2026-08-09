<?php
/**
 * Boot assertions for a real WordPress install with the plugin active.
 *
 * Run inside the boot-test container, never on a developer machine:
 *
 *   wp eval-file /usr/src/bci-assertions.php --allow-root
 *
 * wp eval-file boots WordPress first, so everything here runs against the real
 * WordPress and WooCommerce API rather than the stubs tests/test-*.php define.
 * That is the whole point: stubs encode what we believe WordPress does, and
 * cannot fail when the belief is wrong.
 *
 * Failure is an uncaught exception, matching the standalone suite's contract —
 * wp eval-file then exits non-zero and run.sh reports the run as failed.
 *
 * No declare(strict_types=1) here, unlike the rest of the repo: wp eval-file
 * evals the file contents, and a strict_types declaration is only legal as the
 * very first statement of a script.
 *
 * @package BCI_Woo_Plugin
 */

/**
 * Fails the run unless the condition holds.
 */
function boot_assert(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $description);
    }

    echo '  ok  ' . $description . "\n";
}

/**
 * Replaces the gateway settings wholesale and returns a gateway reading them.
 *
 * The constructor is what calls init_settings(), so a fresh instance is the
 * only honest way to observe a settings change.
 *
 * @param array<string, string> $settings Gateway settings to store.
 */
function boot_gateway_with(array $settings): \BCI\Woo\Gateway
{
    update_option(\BCI\Woo\Config::OPTION_KEY, $settings);

    return new \BCI\Woo\Gateway();
}

echo "== Plugin load\n";

boot_assert(class_exists('WooCommerce'), 'WooCommerce is loaded');
boot_assert(defined('BCI_WOO_PLUGIN_FILE'), 'the plugin bootstrap ran and defined BCI_WOO_PLUGIN_FILE');

$classes = [
    'BCI\Woo\Config',
    'BCI\Woo\Plugin',
    'BCI\Woo\Gateway',
    'BCI\Woo\Api',
    'BCI\Woo\Callback',
    'BCI\Woo\Order_State',
    'BCI\Woo\Payment_Resolution',
    'BCI\Woo\Registration',
    'BCI\Woo\Registration_Result',
    'BCI\Woo\Resolution',
    'BCI\Woo\Scheduler',
    'BCI\Woo\Status_Resolver',
    'BCI\Woo\Tokens',
];

foreach ($classes as $class) {
    boot_assert(class_exists($class), sprintf('%s autoloads inside WordPress', $class));
}

$header = get_file_data(BCI_WOO_PLUGIN_FILE, ['Version' => 'Version']);
boot_assert(
    $header['Version'] === \BCI\Woo\Config::VERSION,
    sprintf('installed plugin header version matches Config::VERSION (%s)', \BCI\Woo\Config::VERSION)
);

echo "== Gateway registration\n";

$gateways = WC()->payment_gateways()->payment_gateways();
boot_assert(
    isset($gateways[\BCI\Woo\Config::GATEWAY_ID]),
    sprintf('WooCommerce registered the "%s" gateway', \BCI\Woo\Config::GATEWAY_ID)
);

$gateway = $gateways[\BCI\Woo\Config::GATEWAY_ID];
boot_assert($gateway instanceof \BCI\Woo\Gateway, 'the registered gateway is our Gateway class');
boot_assert($gateway->method_title !== '', 'the gateway advertises a method title for the payments screen');

$expected_fields = [
    'enabled',
    'title',
    'test_mode',
    'sandbox_currency',
    'live_api_login',
    'live_api_password',
    'live_callback_token',
    'sandbox_api_login',
    'sandbox_api_password',
    'sandbox_callback_token',
    'callback_url',
    'enable_subscriptions',
];

$form_fields = $gateway->get_form_fields();

foreach ($expected_fields as $field) {
    boot_assert(isset($form_fields[$field]), sprintf('the settings form defines the "%s" field', $field));
}

echo "== Settings round trip\n";

$saved = boot_gateway_with([
    'enabled' => 'yes',
    'test_mode' => 'yes',
    'title' => 'Boot test card',
    'sandbox_api_login' => 'boot-test-login',
    'sandbox_api_password' => 'boot-test-password',
    'sandbox_callback_token' => 'boot-test-token',
]);

boot_assert($saved->get_option('title') === 'Boot test card', 'a saved setting reads back through the gateway');
boot_assert($saved->title === 'Boot test card', 'the checkout title follows the saved setting');
boot_assert(\BCI\Woo\Api::current_environment() === 'sandbox', 'Test mode resolves the environment to sandbox');
boot_assert(\BCI\Woo\Api::has_credentials('sandbox'), 'saved sandbox credentials are visible to the API layer');

echo "== Settings screen\n";

// generate_settings_html rather than admin_options, because a CLI bootstrap is
// not an admin request and admin_options assumes one.
$html = $saved->generate_settings_html($saved->get_form_fields(), false);
boot_assert(is_string($html) && $html !== '', 'the settings form renders to HTML');
boot_assert(
    strpos($html, 'sandbox_api_login') !== false,
    'the rendered settings form contains the sandbox credential fields'
);
boot_assert(
    strpos($html, 'enable_subscriptions') !== false,
    'the rendered settings form contains the subscriptions toggle'
);

echo "== Checkout availability\n";

boot_assert($saved->is_available(), 'the gateway offers itself with sandbox credentials saved and Test mode on');

$unconfigured = boot_gateway_with(['enabled' => 'yes', 'test_mode' => 'yes']);
boot_assert(!$unconfigured->is_available(), 'the gateway withholds itself when no credentials are saved');

// The live currency guard: a store priced in anything but NZD must not be able
// to collect its totals as NZD through the live endpoint.
update_option('woocommerce_currency', 'USD');
$live = boot_gateway_with([
    'enabled' => 'yes',
    'test_mode' => 'no',
    'live_api_login' => 'boot-test-login',
    'live_api_password' => 'boot-test-password',
]);
boot_assert(!$live->is_available(), 'the gateway withholds itself in live mode on a non-NZD store');

update_option('woocommerce_currency', 'NZD');
$live_nzd = boot_gateway_with([
    'enabled' => 'yes',
    'test_mode' => 'no',
    'live_api_login' => 'boot-test-login',
    'live_api_password' => 'boot-test-password',
]);
boot_assert($live_nzd->is_available(), 'the gateway offers itself in live mode on an NZD store');

echo "== Callback route\n";

$routes = rest_get_server()->get_routes();
$callback_route = '/' . \BCI\Woo\Config::CALLBACK_NAMESPACE . \BCI\Woo\Config::CALLBACK_ROUTE;
boot_assert(isset($routes[$callback_route]), sprintf('the REST route %s is registered', $callback_route));

// Served over real HTTP against Apache in this container, so the assertion
// covers REST routing and rewrite rules rather than just the route table.
$callback_url = rest_url(\BCI\Woo\Config::CALLBACK_NAMESPACE . \BCI\Woo\Config::CALLBACK_ROUTE);
$response = wp_remote_post($callback_url, ['timeout' => 20, 'body' => ['orderId' => 'boot-test']]);

boot_assert(!is_wp_error($response), sprintf('the callback endpoint answers over HTTP at %s', $callback_url));
boot_assert(
    strpos($callback_url, '/wp-json/') !== false,
    'the callback URL is the /wp-json/ form merchants are told to configure'
);

$code = (int) wp_remote_retrieve_response_code($response);
boot_assert($code !== 404, sprintf('the callback endpoint is routed, not a 404 (got %d)', $code));
boot_assert($code >= 400 && $code < 500, sprintf('an unsigned callback is rejected (got %d)', $code));

echo "== Front end\n";

$home = wp_remote_get(home_url('/'), ['timeout' => 20]);
boot_assert(!is_wp_error($home), 'the site front page answers over HTTP');
boot_assert(
    (int) wp_remote_retrieve_response_code($home) === 200,
    sprintf('the front page returns 200 (got %d)', (int) wp_remote_retrieve_response_code($home))
);

echo "\nAll boot assertions passed.\n";
