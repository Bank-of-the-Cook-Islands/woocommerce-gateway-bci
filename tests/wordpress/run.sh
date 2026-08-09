#!/usr/bin/env bash
#
# Boots a real WordPress + WooCommerce install with the plugin active and runs
# tests/wordpress/assertions.php against it.
#
#   tests/wordpress/run.sh
#
# Everything is thrown away afterwards: two containers and a network, all
# removed on exit whether the run passed or failed. Nothing touches the host
# beyond the docker daemon.
#
# Environment:
#   BOOT_TEST_PORT       publish the site on this host port for a look around
#   BOOT_TEST_SABOTAGE   break the plugin on purpose; the run must then fail
#
# This is shell rather than PHP — unlike scripts/build-*.php it orchestrates
# docker and nothing else, and it has to run before any PHP we control exists.

set -euo pipefail

readonly REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly RUN_ID="${BOOT_TEST_RUN_ID:-$$}"
readonly IMAGE="wc-bci-boot:${RUN_ID}"
readonly NETWORK="wc-bci-boot-${RUN_ID}"
readonly DB_CONTAINER="wc-bci-boot-db-${RUN_ID}"
readonly WP_CONTAINER="wc-bci-boot-wp-${RUN_ID}"

# Pinned so an upstream release cannot turn CI red overnight. Bumping this is a
# deliberate change: it is how we find out the plugin still works on newer
# WooCommerce.
readonly WC_VERSION="9.4.2"

readonly PLUGIN_SLUG="woocommerce-gateway-bci"
readonly WP_PATH="/var/www/html"
readonly DEBUG_LOG="/tmp/wp-debug.log"

cleanup() {
	local status=$?

	if [[ $status -ne 0 ]]; then
		echo
		echo "--- WordPress container log (last 40 lines) ---"
		docker logs --tail 40 "$WP_CONTAINER" 2>&1 || true
	fi

	docker rm -f "$WP_CONTAINER" "$DB_CONTAINER" >/dev/null 2>&1 || true
	docker network rm "$NETWORK" >/dev/null 2>&1 || true
	docker image rm -f "$IMAGE" >/dev/null 2>&1 || true

	exit $status
}
trap cleanup EXIT

# wp-cli runs as the web user, not root. Running it as root leaves root-owned
# directories under wp-content — WooCommerce's log directory in particular —
# which Apache then cannot write to, so the plugin's own logging fails and fills
# the debug log with permission warnings that would mask a real error.
wpcli() {
	docker exec -u www-data -e HOME=/tmp "$WP_CONTAINER" wp --path="$WP_PATH" "$@"
}

# Anything created as root (the entrypoint, a docker cp) handed back to Apache.
fix_permissions() {
	docker exec "$WP_CONTAINER" chown -R www-data:www-data "$WP_PATH"
}

step() {
	echo
	echo "==> $*"
}

# The Dockerfile copies the plugin in path by path so the boot test exercises
# the set that actually ships. That duplicates RELEASE_PATHS, so check the two
# have not drifted rather than trusting them to stay in step.
step "Checking the shipped path list matches scripts/build-release.php"
missing=()
while IFS= read -r path; do
	[[ -z "$path" ]] && continue
	if ! grep -q "^COPY ${path}\b" "${REPO_ROOT}/tests/wordpress/Dockerfile"; then
		missing+=("$path")
	fi
done < <(sed -n "/^const RELEASE_PATHS/,/^);/p" "${REPO_ROOT}/scripts/build-release.php" |
	sed -n "s/^\s*'\([^']*\)',\s*$/\1/p")

if [[ ${#missing[@]} -gt 0 ]]; then
	echo "RELEASE_PATHS entries missing from tests/wordpress/Dockerfile: ${missing[*]}" >&2
	echo "The boot test would not cover them. Add a COPY line for each." >&2
	exit 1
fi
echo "  ok  every RELEASE_PATHS entry is copied into the boot image"

step "Building the boot image"
docker build --quiet -t "$IMAGE" -f "${REPO_ROOT}/tests/wordpress/Dockerfile" "$REPO_ROOT"

step "Starting the database"
docker network create "$NETWORK" >/dev/null
docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
	-e MARIADB_ROOT_PASSWORD=root \
	-e MARIADB_DATABASE=wordpress \
	-e MARIADB_USER=wordpress \
	-e MARIADB_PASSWORD=wordpress \
	mariadb:11.4 >/dev/null

# Over TCP as the application user, not a root socket ping: MariaDB answers a
# socket ping from the temporary server it runs during bootstrap, long before
# the real server is listening or the application user exists.
db_ready() {
	docker exec "$DB_CONTAINER" mariadb \
		-h127.0.0.1 --protocol=TCP -uwordpress -pwordpress \
		-e 'SELECT 1' wordpress >/dev/null 2>&1
}

for _ in $(seq 1 90); do
	if db_ready; then
		break
	fi
	sleep 1
done
db_ready

step "Starting WordPress"
publish=()
if [[ -n "${BOOT_TEST_PORT:-}" ]]; then
	publish=(-p "${BOOT_TEST_PORT}:80")
fi

# WP_DISABLE_FATAL_ERROR_HANDLER matters more than it looks: without it
# WordPress catches a fatal, deactivates the offending plugin and serves a
# recovery page, which would turn the exact failure this harness exists to
# catch into a quiet pass.
docker run -d --name "$WP_CONTAINER" --network "$NETWORK" "${publish[@]}" \
	-e WORDPRESS_DB_HOST="$DB_CONTAINER" \
	-e WORDPRESS_DB_USER=wordpress \
	-e WORDPRESS_DB_PASSWORD=wordpress \
	-e WORDPRESS_DB_NAME=wordpress \
	-e WORDPRESS_DEBUG=1 \
	-e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', '${DEBUG_LOG}'); define('WP_DEBUG_DISPLAY', false); define('WP_DISABLE_FATAL_ERROR_HANDLER', true);" \
	"$IMAGE" >/dev/null

for _ in $(seq 1 60); do
	if docker exec "$WP_CONTAINER" php -r '$s=@fsockopen("127.0.0.1",80); exit($s?0:1);' >/dev/null 2>&1; then
		break
	fi
	sleep 1
done
docker exec "$WP_CONTAINER" php -r '$s=@fsockopen("127.0.0.1",80); exit($s?0:1);'

step "Installing WordPress"
fix_permissions
wpcli core install \
	--url=http://localhost \
	--title="BCI boot test" \
	--admin_user=boot \
	--admin_password=boot-test-password \
	--admin_email=boot@example.com \
	--skip-email >/dev/null

# Pretty permalinks, so the callback assertion exercises the /wp-json/ route
# merchants are told to configure rather than the query-string fallback.
wpcli rewrite structure '/%postname%/' --hard >/dev/null
wpcli rewrite flush --hard >/dev/null

step "Installing WooCommerce ${WC_VERSION}"
wpcli plugin install woocommerce --version="$WC_VERSION" --activate >/dev/null

step "Installing the plugin under test"
docker exec "$WP_CONTAINER" cp -r /usr/src/bci-plugin "${WP_PATH}/wp-content/plugins/${PLUGIN_SLUG}"

if [[ -n "${BOOT_TEST_SABOTAGE:-}" ]]; then
	# Proves the harness can fail. A missing class file is the cheapest stand-in
	# for the load-time breakage this whole tier exists to catch.
	echo "  !!  BOOT_TEST_SABOTAGE set — removing includes/class-order-state.php"
	docker exec "$WP_CONTAINER" rm "${WP_PATH}/wp-content/plugins/${PLUGIN_SLUG}/includes/class-order-state.php"
fi

fix_permissions

wpcli plugin activate "$PLUGIN_SLUG"

step "Running boot assertions"
wpcli eval-file /usr/src/bci-assertions.php

step "Checking for PHP errors"
log_output="$(docker exec "$WP_CONTAINER" sh -c "cat ${DEBUG_LOG} 2>/dev/null" || true)"

if [[ -n "$log_output" ]]; then
	echo "--- $DEBUG_LOG ---"
	echo "$log_output"
	echo "-------------------"
fi

# Third-party deprecations are noise we do not control; a fatal anywhere, or
# anything naming this plugin, is ours and fails the run.
if grep -qiE 'fatal error|woocommerce-gateway-bci|bci_woo|BCI\\Woo' <<<"$log_output"; then
	echo "PHP errors involving this plugin were logged during the boot test." >&2
	exit 1
fi

if docker logs "$WP_CONTAINER" 2>&1 | grep -q 'PHP Fatal error'; then
	echo "A PHP fatal error was logged by Apache during the boot test." >&2
	exit 1
fi

echo "  ok  no fatal errors and nothing logged against this plugin"

echo
echo "Boot test passed."
