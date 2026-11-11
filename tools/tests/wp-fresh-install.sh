#!/usr/bin/env bash
# A clean WordPress with every Scout plugin active, the way a site gets them.
# Used by the Lint workflow's "WordPress tests (fresh install)" job, and safe to
# run locally against a throwaway database:
#
#   bash tools/tests/wp-fresh-install.sh /tmp/wp
#
# Needs wp-cli and a MySQL server (DB_HOST, DB_USER, DB_PASS, DB_NAME, default
# 127.0.0.1, root, root, wp). It deletes and reinstalls WordPress in that
# folder and that database. Never point it at a real site.
#
# Scout Core is activated first, then every other plugin one at a time. The
# run fails when a plugin does not activate, or when activating it or loading
# the home page, the dashboard or any screen in the admin menu logs a PHP
# warning, notice, deprecation or error. A plugin whose "Requires PHP" is above the PHP running
# here is listed as skipped (scout-optimize on the PHP 8.0 run).
set -euo pipefail

WP_DIR="${1:-/tmp/wp}"
SRC="$(cd "$(dirname "$0")/../.." && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_NAME="${DB_NAME:-wp}"
PORT="${WP_PORT:-8089}"
LOG="$WP_DIR/wp-content/debug.log"
WP="${WP_CLI:-wp}"

wpc() { $WP --path="$WP_DIR" "$@"; }

# PHP problems in the debug log since the last call, printed as CI errors.
seen=0
check_log() {
	local what="$1" new
	[ -f "$LOG" ] || return 0
	new="$(tail -n +"$((seen + 1))" "$LOG" | grep -E 'PHP (Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error)' || true)"
	seen="$(wc -l < "$LOG")"
	if [ -n "$new" ]; then
		while IFS= read -r line; do echo "::error::${what}: ${line}"; done <<< "$new"
		status=1
	fi
}

rm -rf "$WP_DIR" && mkdir -p "$WP_DIR"
wpc core download --quiet
wpc config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --skip-check --force --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
PHP
wpc db reset --yes >/dev/null 2>&1 || wpc db create >/dev/null 2>&1 || true
wpc core install --url="http://127.0.0.1:${PORT}" --title=Scout --admin_user=admin --admin_password=admin --admin_email=ci@example.com --skip-email
# WordPress guesses the address from the folder name; say it plainly.
wpc option update home "http://127.0.0.1:${PORT}" >/dev/null
wpc option update siteurl "http://127.0.0.1:${PORT}" >/dev/null

mkdir -p "$WP_DIR/wp-content/mu-plugins" "$WP_DIR/wp-content/plugins/gravityforms"
cp "$SRC/tools/tests/fixture-fieldset.php" "$SRC/tools/tests/fixture-fieldset-register.php" "$WP_DIR/wp-content/mu-plugins/"
cp "$SRC/tools/tests/fixture-gravityforms.php" "$WP_DIR/wp-content/plugins/gravityforms/gravityforms.php"

# Every plugin folder, Scout Core first (the others call it).
slugs=(scout-core)
for dir in "$SRC"/scout-*/; do
	slug="$(basename "$dir")"
	[ "$slug" = "scout-core" ] && continue
	[ -f "$dir/$slug.php" ] && slugs+=("$slug")
done

status=0
active=()
skipped=()
wpc plugin activate gravityforms >/dev/null
check_log "Gravity Forms stand-in"
for slug in "${slugs[@]}"; do
	req="$(grep -m1 -oP 'Requires PHP:\s*\K[0-9.]+' "$SRC/$slug/$slug.php" || echo 0)"
	if ! php -r "exit(version_compare(PHP_VERSION, '$req', '>=') ? 0 : 1);"; then
		skipped+=("$slug (needs PHP $req)")
		continue
	fi
	cp -r "$SRC/$slug" "$WP_DIR/wp-content/plugins/"
	if ! out="$(wpc plugin activate "$slug" 2>&1)"; then
		echo "::error::${slug} did not activate: ${out}"
		status=1
		continue
	fi
	if grep -qE '(Warning|Notice|Deprecated|Fatal error|Parse error):' <<< "$out"; then
		echo "::error::${slug} printed PHP problems while activating: ${out}"
		status=1
	fi
	check_log "Activating ${slug}"
	active+=("$slug")
done

for slug in "${active[@]}"; do
	wpc plugin is-active "$slug" || { echo "::error::${slug} is not active after activation."; status=1; }
done

# The home page and the dashboard, through a real web server, so the front end
# and wp-admin hooks of every plugin run once.
php -S "127.0.0.1:${PORT}" -t "$WP_DIR" >/dev/null 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:${PORT}/wp-login.php" && break; sleep 0.2; done

jar="$(mktemp)"
page() {
	local what="$1" url="$2" want="$3" body code
	body="$(mktemp)"
	code="$(curl -s -o "$body" -w '%{http_code}' -b "$jar" "$url")"
	if [ "$code" != "200" ]; then
		echo "::error::${what} answered HTTP ${code}, not 200."
		status=1
	elif [ -n "$want" ] && ! grep -q "$want" "$body"; then
		echo "::error::${what} loaded without ${want} in it."
		status=1
	fi
	rm -f "$body"
	check_log "$what"
}
page "Home page" "http://127.0.0.1:${PORT}/" ""
curl -s -o /dev/null -c "$jar" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
	--data-urlencode 'log=admin' --data-urlencode 'pwd=admin' -d 'testcookie=1' "http://127.0.0.1:${PORT}/wp-login.php"
page "Dashboard" "http://127.0.0.1:${PORT}/wp-admin/" 'id="wpadminbar"'
page "Plugins screen" "http://127.0.0.1:${PORT}/wp-admin/plugins.php" 'id="wpadminbar"'
# Every screen in the admin menu that a plugin added (admin.php?page=...).
screens="$(curl -s -b "$jar" "http://127.0.0.1:${PORT}/wp-admin/" | { grep -oE "href=['\"]admin\.php\?page=[A-Za-z0-9_-]+" || true; } | sed 's/.*page=//' | sort -u)"
count=0
for screen in $screens; do
	page "Screen ${screen}" "http://127.0.0.1:${PORT}/wp-admin/admin.php?page=${screen}" 'id="wpadminbar"'
	count=$((count + 1))
done
echo "Loaded the home page, the dashboard, the Plugins screen and ${count} plugin screens."
[ "$count" -gt 0 ] || { echo "::error::No plugin screens found in the admin menu."; status=1; }
rm -f "$jar"

echo "Active: ${#active[@]} of ${#slugs[@]} Scout plugins: ${active[*]}"
[ "${#skipped[@]}" -eq 0 ] || echo "Skipped on PHP $(php -r 'echo PHP_VERSION;'): ${skipped[*]}"
exit "$status"
