#!/usr/bin/env bash
#
# A long-lived WordPress + WooCommerce store running this checkout, for manual
# and scripted testing against the BPC sandbox (dev.bpcbt.com).
#
#   tests/sandbox/sandbox.sh up [--tunnel]   start the store
#   tests/sandbox/sandbox.sh configure       load sandbox credentials from the environment
#   tests/sandbox/sandbox.sh scenarios       run the checkout scenarios and summarise the orders
#   tests/sandbox/sandbox.sh checkout ARGS   one checkout (see checkout.py --help)
#   tests/sandbox/sandbox.sh inspect IDS     notes and status for comma-separated order IDs
#   tests/sandbox/sandbox.sh wp ARGS         wp-cli as the web user
#   tests/sandbox/sandbox.sh url             the store URL
#   tests/sandbox/sandbox.sh down            remove the store, its data and its tunnel
#
# Unlike tests/wordpress/run.sh, which is CI's throwaway boot check, this store
# stays up between commands and makes real calls to BPC. The plugin is
# bind-mounted read-only from the working tree, so checking out another branch
# changes what the store runs without a rebuild.
#
# By default the store is served on http://localhost only. BPC cannot reach it,
# so callbacks never arrive and only the browser-return paths are exercised.
# --tunnel publishes it through a Cloudflare quick tunnel (a random
# trycloudflare.com URL, changed on every start) so callbacks can be tested;
# point the sandbox merchant portal's callback URL at it, and run `down` as soon
# as testing is finished.
#
# Environment:
#   SANDBOX_PORT                  local port (default 8089)
#   SANDBOX_STATE_DIR             URL, admin password, screenshots (default ~/.local/state/wc-bci-sandbox)
#   BCI_SANDBOX_API_LOGIN         read by configure
#   BCI_SANDBOX_API_PASSWORD      read by configure
#   BCI_SANDBOX_CALLBACK_TOKEN    read by configure; optional, needed for callbacks

set -euo pipefail

readonly REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly HERE="${REPO_ROOT}/tests/sandbox"
readonly PORT="${SANDBOX_PORT:-8089}"
readonly STATE_DIR="${SANDBOX_STATE_DIR:-${HOME}/.local/state/wc-bci-sandbox}"
readonly NETWORK=wc-bci-sandbox
readonly DB=wc-bci-sandbox-db
readonly WP=wc-bci-sandbox-wp
readonly TUNNEL=wc-bci-sandbox-tunnel
readonly SITE_IMAGE=wc-bci-sandbox:latest
readonly BROWSER_IMAGE=wc-bci-pw:latest
readonly PLUGIN_SLUG=woocommerce-gateway-bci
# Same pin as tests/wordpress/run.sh, so both harnesses test one WooCommerce.
readonly WC_VERSION=9.4.2

wpcli() {
	docker exec -u www-data -e HOME=/tmp "$WP" wp --path=/var/www/html "$@"
}

site_url() {
	cat "${STATE_DIR}/url"
}

product_id() {
	cat "${STATE_DIR}/product"
}

step() {
	echo "==> $*"
}

up() {
	local tunnel=false
	[[ "${1:-}" == "--tunnel" ]] && tunnel=true

	if docker ps -a --format '{{.Names}}' | grep -qx "$WP"; then
		echo "The sandbox store already exists: $(site_url). Run down first." >&2
		exit 1
	fi

	mkdir -p "$STATE_DIR"
	chmod 700 "$STATE_DIR"

	step "Building images"
	docker build --quiet -t "$SITE_IMAGE" -f "${REPO_ROOT}/tests/wordpress/Dockerfile" "$REPO_ROOT" >/dev/null
	docker build --quiet -t "$BROWSER_IMAGE" -f "${HERE}/Dockerfile.playwright" "$HERE" >/dev/null
	docker network create "$NETWORK" >/dev/null

	local url="http://localhost:${PORT}"
	if $tunnel; then
		step "Opening a Cloudflare quick tunnel"
		docker run -d --name "$TUNNEL" --network "$NETWORK" \
			cloudflare/cloudflared:latest tunnel --no-autoupdate --url "http://${WP}:80" >/dev/null
		url=""
		for _ in $(seq 1 60); do
			url="$(docker logs "$TUNNEL" 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true)"
			[[ -n "$url" ]] && break
			sleep 1
		done
		[[ -n "$url" ]] || { echo "The tunnel did not come up." >&2; exit 1; }
	fi
	echo "$url" >"${STATE_DIR}/url"

	step "Starting the database"
	docker run -d --name "$DB" --network "$NETWORK" \
		-e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress \
		-e MARIADB_USER=wordpress -e MARIADB_PASSWORD=wordpress \
		-v wc-bci-sandbox-db:/var/lib/mysql mariadb:11.4 >/dev/null
	# Over TCP as the application user: MariaDB answers a root socket ping from
	# its bootstrap server before the real one is listening.
	for _ in $(seq 1 90); do
		docker exec "$DB" mariadb -h127.0.0.1 --protocol=TCP -uwordpress -pwordpress -e 'SELECT 1' wordpress >/dev/null 2>&1 && break
		sleep 1
	done

	step "Starting WordPress at ${url}"
	# Cloudflare terminates TLS, so trust its X-Forwarded-Proto: WordPress then
	# builds https links and WooCommerce treats checkout as secure.
	docker run -d --name "$WP" --network "$NETWORK" -p "127.0.0.1:${PORT}:80" \
		-e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wordpress \
		-e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
		-e WORDPRESS_DEBUG=1 \
		-e WORDPRESS_CONFIG_EXTRA="if ((\$_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') { \$_SERVER['HTTPS'] = 'on'; } define('WP_HOME', '${url}'); define('WP_SITEURL', '${url}'); define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false);" \
		-v wc-bci-sandbox-wp:/var/www/html \
		-v "${REPO_ROOT}:/var/www/html/wp-content/plugins/${PLUGIN_SLUG}:ro" \
		"$SITE_IMAGE" >/dev/null
	for _ in $(seq 1 60); do
		docker exec "$WP" php -r 'exit(@fsockopen("127.0.0.1", 80) ? 0 : 1);' 2>/dev/null && break
		sleep 1
	done
	sleep 3
	# Everything but the read-only plugin mount belongs to the web user.
	docker exec "$WP" sh -c "find /var/www/html -path /var/www/html/wp-content/plugins/${PLUGIN_SLUG} -prune -o -exec chown www-data:www-data {} +"

	step "Installing WordPress and WooCommerce ${WC_VERSION}"
	local password
	password="$(head -c 24 /dev/urandom | base64 | tr -d '/+=')"
	(umask 077 && printf 'user: admin\npassword: %s\n' "$password" >"${STATE_DIR}/admin-credentials")
	wpcli core install --url="$url" --title="BCI Sandbox Store" --admin_user=admin \
		--admin_password="$password" --admin_email=admin@example.com --skip-email >/dev/null
	wpcli rewrite structure '/%postname%/' --hard >/dev/null 2>&1
	wpcli option update blog_public 0 >/dev/null
	wpcli plugin install woocommerce --version="$WC_VERSION" --activate >/dev/null
	wpcli plugin activate "$PLUGIN_SLUG" >/dev/null
	wpcli wc tool run install_pages --user=admin >/dev/null 2>&1 || true

	step "Setting up a Cook Islands store"
	wpcli option update woocommerce_default_country CK >/dev/null
	wpcli option update woocommerce_currency NZD >/dev/null
	wpcli option update woocommerce_store_city Avarua >/dev/null
	wpcli wc product create --user=admin --name="Test Dorm Bed (1 night)" --type=simple \
		--regular_price=1.00 --virtual=true --porcelain >"${STATE_DIR}/product"

	echo
	echo "Store:    ${url}"
	echo "Admin:    ${url}/wp-admin (credentials in ${STATE_DIR}/admin-credentials)"
	echo "Callback: ${url}/wp-json/bci-woo/v1/callback"
	$tunnel || echo "Local only: BPC callbacks cannot reach this store. Use up --tunnel to test them."
	echo "Next: configure"
}

configure() {
	: "${BCI_SANDBOX_API_LOGIN:?Set BCI_SANDBOX_API_LOGIN}"
	: "${BCI_SANDBOX_API_PASSWORD:?Set BCI_SANDBOX_API_PASSWORD}"
	export BCI_SANDBOX_CALLBACK_TOKEN="${BCI_SANDBOX_CALLBACK_TOKEN:-}"

	# Passed by name, so the values never appear on a command line.
	docker exec -u www-data -e HOME=/tmp \
		-e BCI_SANDBOX_API_LOGIN -e BCI_SANDBOX_API_PASSWORD -e BCI_SANDBOX_CALLBACK_TOKEN \
		"$WP" wp --path=/var/www/html eval '
			$settings = get_option(\BCI\Woo\Config::OPTION_KEY, []);
			$settings = array_merge(is_array($settings) ? $settings : [], [
				"enabled" => "yes",
				"test_mode" => "yes",
				"sandbox_currency" => "EUR",
				"sandbox_api_login" => getenv("BCI_SANDBOX_API_LOGIN"),
				"sandbox_api_password" => getenv("BCI_SANDBOX_API_PASSWORD"),
			]);
			if (getenv("BCI_SANDBOX_CALLBACK_TOKEN") !== "") {
				$settings["sandbox_callback_token"] = getenv("BCI_SANDBOX_CALLBACK_TOKEN");
			}
			update_option(\BCI\Woo\Config::OPTION_KEY, $settings);
			$result = \BCI\Woo\Admin::init()->test_connection("sandbox");
			echo ($result["success"] ? "Connection test passed: " : "Connection test FAILED: "), $result["message"], "\n";
			echo count(\BCI\Woo\Api::callback_tokens()), " callback token(s) configured\n";
		'
}

checkout() {
	mkdir -p "${STATE_DIR}/run"
	# Host networking, so a local-only store is reachable at localhost.
	docker run --rm --network host --user "$(id -u):$(id -g)" -e HOME=/tmp \
		-v "${HERE}/checkout.py:/checkout.py:ro" -v "${STATE_DIR}/run:/work" \
		"$BROWSER_IMAGE" python3 /checkout.py --site "$(site_url)" --product "$(product_id)" "$@"
}

newest_order() {
	wpcli wc shop_order list --user=admin --field=id --orderby=id --order=desc --per_page=1
}

cancel_like_woocommerce() {
	docker exec -u www-data -e HOME=/tmp -e ORDER_ID="$1" "$WP" wp --path=/var/www/html eval \
		'wc_get_order((int) getenv("ORDER_ID"))->update_status("cancelled", "Unpaid order cancelled - time limit reached.");'
}

inspect() {
	docker cp "${HERE}/inspect-orders.php" "${WP}:/tmp/inspect-orders.php"
	docker exec -u www-data -e HOME=/tmp -e ORDER_IDS="$1" "$WP" wp --path=/var/www/html eval-file /tmp/inspect-orders.php
}

# One checkout per scenario, then every order's status and notes. Each order
# should end with exactly one outcome note and no duplicated _bci_woo_* meta.
scenarios() {
	local tunnel=false ids=() summary=()
	[[ "$(site_url)" == https://* ]] && tunnel=true
	mkdir -p "${STATE_DIR}/run"

	run() {
		local name="$1"
		shift
		echo "--- ${name}"
		checkout --tag "$name" "$@" >"${STATE_DIR}/run/${name}.json" 2>&1 || true
		ids+=("$(newest_order)")
		summary+=("${name}=#${ids[-1]}")
	}

	# Cancels the order while the customer is on BPC's page, then lets them pay:
	# the late payment must bring the order back.
	run_cancelled() {
		local name="$1"
		shift
		echo "--- ${name}"
		rm -f "${STATE_DIR}/run/at-bpc" "${STATE_DIR}/run/go"
		checkout --tag "$name" --pause "$@" >"${STATE_DIR}/run/${name}.json" 2>&1 &
		local browser=$!
		for _ in $(seq 1 120); do
			[[ -f "${STATE_DIR}/run/at-bpc" ]] && break
			sleep 1
		done
		if [[ ! -f "${STATE_DIR}/run/at-bpc" ]]; then
			echo "    never reached the BPC page; see ${STATE_DIR}/run/${name}.json" >&2
			wait "$browser" || true
			return
		fi
		local order
		order="$(newest_order)"
		cancel_like_woocommerce "$order"
		touch "${STATE_DIR}/run/go"
		wait "$browser" || true
		ids+=("$order")
		summary+=("${name}=#${order}")
	}

	run paid --mode pay
	run declined-cvc --mode pay --cvc 999
	run declined-3ds --mode pay --card 5168494895055780 --acs Fail
	run paid-3ds-challenge --mode pay --card 5555555555555599 --expiry 12/34
	run_cancelled cancelled-then-paid --mode pay
	if $tunnel; then
		run paid-never-returned --mode abandon
		run_cancelled cancelled-then-paid-never-returned --mode abandon
	else
		echo "--- skipping the never-returned scenarios: they need callbacks (up --tunnel)"
	fi

	sleep 10
	echo
	echo "${summary[*]}"
	echo
	inspect "$(IFS=,; echo "${ids[*]}")"
}

down() {
	docker rm -f "$TUNNEL" "$WP" "$DB" >/dev/null 2>&1 || true
	docker network rm "$NETWORK" >/dev/null 2>&1 || true
	docker volume rm wc-bci-sandbox-db wc-bci-sandbox-wp >/dev/null 2>&1 || true
	docker image rm "$SITE_IMAGE" >/dev/null 2>&1 || true
	rm -rf "$STATE_DIR"
	echo "Sandbox store removed."
}

command="${1:-}"
shift || true
case "$command" in
	up) up "$@" ;;
	configure) configure ;;
	checkout) checkout "$@" ;;
	inspect) inspect "$@" ;;
	scenarios) scenarios ;;
	wp) wpcli "$@" ;;
	url) site_url ;;
	down) down ;;
	*)
		sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//'
		exit 2
		;;
esac
