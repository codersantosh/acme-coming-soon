#!/usr/bin/env bash
set -euo pipefail

fail() {
	echo "ERROR: $*" >&2
	exit 1
}

: "${RUNNER_TEMP:?RUNNER_TEMP must be set}"
: "${WP_VERSION:?WP_VERSION must be set}"
: "${EXPECTED_VERSION:?EXPECTED_VERSION must be set}"
: "${DB_NAME:?DB_NAME must be set}"
: "${DB_USER:?DB_USER must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"
: "${ADMIN_USER:?ADMIN_USER must be set}"
: "${ADMIN_PASSWORD:?ADMIN_PASSWORD must be set}"
: "${ADMIN_EMAIL:?ADMIN_EMAIL must be set}"
: "${EDITOR_USER:?EDITOR_USER must be set}"
: "${EDITOR_PASSWORD:?EDITOR_PASSWORD must be set}"
: "${SUBSCRIBER_USER:?SUBSCRIBER_USER must be set}"
: "${SUBSCRIBER_PASSWORD:?SUBSCRIBER_PASSWORD must be set}"

[[ "$WP_VERSION" == '7.0.9' || "$WP_VERSION" == '7.1.5' ]] \
	|| fail "Unsupported runtime WordPress version: $WP_VERSION"
[[ "$EXPECTED_VERSION" == '1.0.7' ]] || fail "This runtime suite requires version 1.0.7."

PROJECT_ROOT="$(realpath "${GITHUB_WORKSPACE:-$(pwd)}")"
DB_HOST="${DB_HOST:-127.0.0.1:3306}"
WP_DIR="${RUNNER_TEMP}/wordpress-${WP_VERSION}"
TEST_URL='http://127.0.0.1:8080'
MAINTENANCE_SLUG='acme-runtime-maintenance'
PRIVATE_SLUG='acme-runtime-private'
OPEN_SLUG='acme-runtime-open'
TARGET_URL="${TEST_URL}/${MAINTENANCE_SLUG}/"
COOKIE_DIR="${RUNNER_TEMP}/acme-coming-soon-cookies"
FIXTURE_FILE="${RUNNER_TEMP}/acme-runtime-fixtures.json"
FIXTURE_ENV_FILE="${RUNNER_TEMP}/acme-runtime-fixtures.env"
ASSERTIONS="${PROJECT_ROOT}/.github/scripts/wordpress-runtime-assertions.php"
SERVER_PID=''
WP=( wp --path="$WP_DIR" )

cleanup() {
	if [[ -n "$SERVER_PID" ]]; then
		kill "$SERVER_PID" 2>/dev/null || true
		wait "$SERVER_PID" 2>/dev/null || true
	fi
}
trap cleanup EXIT

run_assertions() {
	local phase="$1"
	shift
	env \
		ACS_SMOKE_PHASE="$phase" \
		ACS_EXPECTED_VERSION="$EXPECTED_VERSION" \
		"$@" \
		"${WP[@]}" eval-file "$ASSERTIONS"
}

configure_options() {
	local on="$1"
	local access="$2"
	local roles="$3"
	local search_bot="$4"
	local ips="$5"
	local slugs="$6"
	local template="$7"
	local delete_all="${8:-false}"
	run_assertions configure \
		ACS_ON="$on" \
		ACS_ACCESS="$access" \
		ACS_ROLES_JSON="$roles" \
		ACS_SEARCH_BOT="$search_bot" \
		ACS_IPS_JSON="$ips" \
		ACS_SLUGS_JSON="$slugs" \
		ACS_TEMPLATE_ID="$template" \
		ACS_DELETE_ALL="$delete_all"
}

assert_options() {
	local expected="$1"
	local exists="${2:-true}"
	run_assertions assert-options \
		ACS_EXPECTED_OPTIONS="$expected" \
		ACS_EXPECTED_OPTION_EXISTS="$exists"
}

assert_json() {
	local file="$1"
	local expected="$2"
	local no_option_key="${3:-false}"
	run_assertions assert-rest-response \
		ACS_RESPONSE_FILE="$file" \
		ACS_EXPECTED_JSON="$expected" \
		ACS_ASSERT_NO_OPTION_KEY="$no_option_key"
}

http_status() {
	local url="$1"
	local output="$2"
	local headers="$3"
	shift 3
	curl --silent --show-error --max-time 30 \
		--output "$output" \
		--dump-header "$headers" \
		--write-out '%{http_code}' \
		"$@" "$url"
}

assert_status() {
	local url="$1"
	local expected="$2"
	local output="$3"
	shift 3
	local headers="${output}.headers"
	local status
	status="$(http_status "$url" "$output" "$headers" "$@")"
	if [[ "$status" != "$expected" ]]; then
		head -c 2000 "$output" >&2 || true
		fail "$url returned HTTP $status, expected $expected."
	fi
}

assert_body_contains() {
	local file="$1"
	local expected="$2"
	local label="$3"
	if ! grep -Fq "$expected" "$file"; then
		fail "$label did not contain '$expected'."
	fi
}

assert_body_excludes() {
	local file="$1"
	local forbidden="$2"
	local label="$3"
	if grep -Fq "$forbidden" "$file"; then
		fail "$label leaked '$forbidden'."
	fi
}

location_header() {
	local headers="$1"
	awk 'tolower($1) == "location:" { sub("\r$", "", $2); print $2; exit }' "$headers"
}

assert_redirect() {
	local url="$1"
	local output="$2"
	shift 2
	local headers="${output}.headers"
	local status
	local location
	status="$(http_status "$url" "$output" "$headers" "$@")"
	[[ "$status" == '302' ]] || {
		head -c 2000 "$output" >&2 || true
		fail "$url returned HTTP $status, expected a 302 maintenance redirect."
	}
	location="$(location_header "$headers")"
	[[ "$location" == "$TARGET_URL" || "$location" == "$TARGET_URL"?* ]] \
		|| fail "$url redirected to '$location' instead of '$TARGET_URL'."
}

assert_policy_response() {
	local url="$1"
	local output="$2"
	shift 2
	local headers="${output}.headers"
	local status
	local location
	status="$(http_status "$url" "$output" "$headers" "$@")"
	case "$status" in
		302)
			location="$(location_header "$headers")"
			[[ "$location" == "$TARGET_URL" || "$location" == "$TARGET_URL"?* ]] \
				|| fail "$url redirected to '$location' instead of the maintenance target."
			;;
		503|404)
			assert_body_excludes "$output" 'ACS_PRIVATE_MARKER' "Maintenance policy response for $url"
			assert_body_excludes "$output" 'ACS_POST_MARKER' "Maintenance policy response for $url"
			;;
		*)
			head -c 2000 "$output" >&2 || true
			fail "$url returned HTTP $status; expected a maintenance redirect or fail-closed 404/503."
			;;
	esac
}

login_user() {
	local user="$1"
	local password="$2"
	local cookie_jar="$3"
	local status
	rm -f "$cookie_jar"
	status="$(curl --silent --show-error --max-time 30 \
		--cookie-jar "$cookie_jar" \
		--output /dev/null \
		--write-out '%{http_code}' \
		--data-urlencode "log=${user}" \
		--data-urlencode "pwd=${password}" \
		--data-urlencode 'wp-submit=Log In' \
		"${TEST_URL}/wp-login.php")"
	[[ "$status" == '302' ]] || fail "Login for $user returned HTTP $status."
}

rest_request() {
	local method="$1"
	local url="$2"
	local authorization="$3"
	local output="$4"
	local expected_status="$5"
	local data="${6:-}"
	local headers="${output}.headers"
	local status
	local args=(
		--silent --show-error --max-time 30
		--output "$output"
		--dump-header "$headers"
		--write-out '%{http_code}'
		--header "Authorization: ${authorization}"
		--request "$method"
	)
	if [[ -n "$data" ]]; then
		args+=( --header 'Content-Type: application/json' --data "$data" )
	fi
	status="$(curl "${args[@]}" "$url")"
	if [[ "$status" != "$expected_status" ]]; then
		head -c 2000 "$output" >&2 || true
		fail "$method $url returned HTTP $status, expected $expected_status."
	fi
}

rm -rf "$WP_DIR" "$COOKIE_DIR"
mkdir -p "$WP_DIR" "$COOKIE_DIR"

echo "Installing WordPress $WP_VERSION for the Acme Coming Soon runtime smoke test."
wp core download --path="$WP_DIR" --version="$WP_VERSION" --locale=en_US --force
mkdir -p "$WP_DIR/wp-content/mu-plugins"
cp "${PROJECT_ROOT}/.github/scripts/runtime-mu-plugin.php" \
	"$WP_DIR/wp-content/mu-plugins/00-acme-coming-soon-runtime.php"
wp config create \
	--path="$WP_DIR" \
	--dbname="$DB_NAME" \
	--dbuser="$DB_USER" \
	--dbpass="$DB_PASSWORD" \
	--dbhost="$DB_HOST" \
	--skip-check
wp config set --path="$WP_DIR" WP_DEBUG true --raw
wp config set --path="$WP_DIR" WP_DEBUG_DISPLAY false --raw
wp config set --path="$WP_DIR" WP_DEBUG_LOG "$WP_DIR/debug.log"
wp config set --path="$WP_DIR" WP_ENVIRONMENT_TYPE local
wp config set --path="$WP_DIR" AUTOMATIC_UPDATER_DISABLED true --raw
wp config set --path="$WP_DIR" DISABLE_WP_CRON true --raw
wp config set --path="$WP_DIR" WP_HTTP_BLOCK_EXTERNAL true --raw
wp db create --path="$WP_DIR"
wp core install \
	--path="$WP_DIR" \
	--url="$TEST_URL" \
	--title='Acme Coming Soon Runtime Test' \
	--admin_user="$ADMIN_USER" \
	--admin_password="$ADMIN_PASSWORD" \
	--admin_email="$ADMIN_EMAIL" \
	--skip-email
wp option update --path="$WP_DIR" home "$TEST_URL"
wp option update --path="$WP_DIR" siteurl "$TEST_URL"
wp option update --path="$WP_DIR" permalink_structure '/%postname%/'
wp rewrite flush --hard --path="$WP_DIR"

version="$(node -p "require('${PROJECT_ROOT}/package.json').version")"
[[ "$version" == "$EXPECTED_VERSION" ]] || fail "Package version $version does not match $EXPECTED_VERSION."
wp plugin install \
	--path="$WP_DIR" \
	"${PROJECT_ROOT}/deploy/acme-coming-soon-${version}.zip" \
	--activate

run_assertions fixtures \
	ACS_FIXTURE_FILE="$FIXTURE_FILE" \
	ACS_FIXTURE_ENV_FILE="$FIXTURE_ENV_FILE"
# shellcheck source=/dev/null
source "$FIXTURE_ENV_FILE"
"${WP[@]}" rewrite flush --hard
run_assertions initial

ACS_WORDPRESS_DIR="$WP_DIR" php -S 127.0.0.1:8080 \
	-t "$WP_DIR" \
	"${PROJECT_ROOT}/.github/scripts/wp-router.php" \
	> "${RUNNER_TEMP}/acme-coming-soon-server.log" 2>&1 &
SERVER_PID=$!

for _ in $( seq 1 30 ); do
	if curl --silent --fail --output /dev/null "$TEST_URL/"; then
		break
	fi
	if ! kill -0 "$SERVER_PID" 2>/dev/null; then
		cat "${RUNNER_TEMP}/acme-coming-soon-server.log" >&2
		fail 'The WordPress test server exited during startup.'
	fi
	sleep 1
done
curl --silent --fail --output /dev/null "$TEST_URL/"

# Establish healthy public baselines while maintenance mode is off.
configure_options false admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/baseline-private.html"
assert_body_contains "${RUNNER_TEMP}/baseline-private.html" 'ACS_PRIVATE_MARKER' 'Baseline private page'
assert_status "$TARGET_URL" '200' "${RUNNER_TEMP}/baseline-target.html"
assert_body_contains "${RUNNER_TEMP}/baseline-target.html" 'ACS_MAINTENANCE_MARKER' 'Baseline maintenance page'
assert_status "$TEST_URL/wp-sitemap.xml" '200' "${RUNNER_TEMP}/baseline-sitemap.xml"
assert_status "$TEST_URL/feed/" '200' "${RUNNER_TEMP}/baseline-feed.xml"
assert_status "$TEST_URL/wp-json/wp/v2/posts" '200' "${RUNNER_TEMP}/baseline-rest.json"
assert_status "$TEST_URL/wp-json/oembed/1.0/embed" '200' "${RUNNER_TEMP}/baseline-oembed.json" \
	--get --data-urlencode "url=${TEST_URL}/${PRIVATE_SLUG}/"

# Anonymous settings endpoints must require authentication.
assert_status "$TEST_URL/wp-json/acme-coming-soon/v1/settings" '401' "${RUNNER_TEMP}/settings-anonymous.json"
assert_status "$TEST_URL/wp-json/wp/v2/settings" '401' "${RUNNER_TEMP}/core-settings-anonymous.json"

# Administrator login and rendered build assets.
login_user "$ADMIN_USER" "$ADMIN_PASSWORD" "$COOKIE_DIR/admin.cookies"
login_user "$EDITOR_USER" "$EDITOR_PASSWORD" "$COOKIE_DIR/editor.cookies"
login_user "$SUBSCRIBER_USER" "$SUBSCRIBER_PASSWORD" "$COOKIE_DIR/subscriber.cookies"
assert_status "$TEST_URL/wp-admin/admin.php?page=acme-coming-soon" '200' \
	"${RUNNER_TEMP}/admin.html" --cookie "$COOKIE_DIR/admin.cookies"
run_assertions assert-admin-html ACS_ADMIN_HTML="${RUNNER_TEMP}/admin.html"
assert_status "$TEST_URL/wp-content/plugins/acme-coming-soon/build/admin/admin.js" '200' "${RUNNER_TEMP}/admin.js"
assert_status "$TEST_URL/wp-content/plugins/acme-coming-soon/build/admin/admin.css" '200' "${RUNNER_TEMP}/admin.css"
assert_status "$TEST_URL/wp-content/plugins/acme-coming-soon/build/admin/admin-rtl.css" '200' "${RUNNER_TEMP}/admin-rtl.css"
for asset in "${RUNNER_TEMP}/admin.js" "${RUNNER_TEMP}/admin.css" "${RUNNER_TEMP}/admin-rtl.css"; do
	[[ -s "$asset" ]] || fail "Admin asset was empty: $asset"
done

# REST permissions and deep partial-update preservation.
configure_options false roles '["editor"]' true '["198.51.100.10"]' '["keep-slug"]' "$MAINTENANCE_ID" false
rest_request GET "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/settings-admin.json" '200'
assert_json "${RUNNER_TEMP}/settings-admin.json" \
	'{"on":false,"access":"roles","exclude":{"useRoles":["editor"],"searchBot":true,"ips":["198.51.100.10"],"slugs":["keep-slug"]},"template":'"$MAINTENANCE_ID"',"deleteAll":false}'
rest_request GET "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$SUBSCRIBER_AUTHORIZATION" "${RUNNER_TEMP}/settings-subscriber.json" '403'
rest_request PUT "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/settings-partial.json" '200' \
	'{"exclude":{"ips":["192.0.2.55"]}}'
partial_expected='{"on":false,"access":"roles","exclude":{"useRoles":["editor"],"searchBot":true,"ips":["192.0.2.55"],"slugs":["keep-slug"]},"template":'"$MAINTENANCE_ID"',"deleteAll":false}'
assert_json "${RUNNER_TEMP}/settings-partial.json" "$partial_expected"
assert_options "$partial_expected"

rest_request PUT "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/settings-invalid.json" '400' \
	'{"exclude":{"ips":"not-an-array"}}'
assert_options "$partial_expected"
rest_request PUT "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$SUBSCRIBER_AUTHORIZATION" "${RUNNER_TEMP}/settings-forbidden.json" '403' \
	'{"on":true}'
assert_options "$partial_expected"
rest_request GET "$TEST_URL/wp-json/wp/v2/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/core-settings-admin.json" '200'
assert_json "${RUNNER_TEMP}/core-settings-admin.json" '{}' true
rest_request GET "$TEST_URL/wp-json/wp/v2/settings" \
	"$SUBSCRIBER_AUTHORIZATION" "${RUNNER_TEMP}/core-settings-subscriber.json" '403'

# Enabling maintenance mode with an invalid merged template must be rejected.
configure_options false admin '[]' false '[]' '[]' '99999999' false
rest_request PUT "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/settings-invalid-template.json" '400' \
	'{"on":true}'
assert_options '{"on":false,"template":99999999}'

# Maintenance off/on, administrator bypass, target rendering, and no target loop.
configure_options true admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/admin-private.html" \
	--cookie "$COOKIE_DIR/admin.cookies"
assert_body_contains "${RUNNER_TEMP}/admin-private.html" 'ACS_PRIVATE_MARKER' 'Administrator bypass'
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/anonymous-private.html"
assert_status "$TARGET_URL" '200' "${RUNNER_TEMP}/target.html"
assert_body_contains "${RUNNER_TEMP}/target.html" 'ACS_MAINTENANCE_MARKER' 'Maintenance target'
assert_status "${TARGET_URL}?runtime=target-loop" '200' "${RUNNER_TEMP}/target-query.html"
assert_body_contains "${RUNNER_TEMP}/target-query.html" 'ACS_MAINTENANCE_MARKER' 'Maintenance target with query string'
configure_options false admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/disabled-private.html"
configure_options true admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false

# Access modes and capabilities.
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/subscriber-admin-access.html" \
	--cookie "$COOKIE_DIR/subscriber.cookies"
run_assertions check-user ACS_USER_ID="$ADMIN_ID" ACS_EXPECTED_BOOL=true
run_assertions check-user ACS_USER_ID="$EDITOR_ID" ACS_EXPECTED_BOOL=false
run_assertions check-user ACS_USER_ID="$SUBSCRIBER_ID" ACS_EXPECTED_BOOL=false

configure_options true login '[]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/subscriber-login-access.html" \
	--cookie "$COOKIE_DIR/subscriber.cookies"
run_assertions check-user ACS_USER_ID="$SUBSCRIBER_ID" ACS_EXPECTED_BOOL=true

configure_options true roles '["editor"]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/editor-role-access.html" \
	--cookie "$COOKIE_DIR/editor.cookies"
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/subscriber-role-access.html" \
	--cookie "$COOKIE_DIR/subscriber.cookies"
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/admin-role-access.html" \
	--cookie "$COOKIE_DIR/admin.cookies"
run_assertions check-user ACS_USER_ID="$EDITOR_ID" ACS_EXPECTED_BOOL=true
run_assertions check-user ACS_USER_ID="$SUBSCRIBER_ID" ACS_EXPECTED_BOOL=false
run_assertions check-user ACS_USER_ID="$ADMIN_ID" ACS_EXPECTED_BOOL=false

# Search-bot setting, including a missing User-Agent.
configure_options true '' '[]' true '[]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/bot-allowed.html" \
	--user-agent 'Googlebot/2.1 (+http://www.google.com/bot.html)'
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/ordinary-agent.html" \
	--user-agent 'Mozilla/5.0'
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/missing-agent.html" --user-agent ''
run_assertions check-bot ACS_USER_AGENT='Googlebot/2.1' ACS_EXPECTED_BOT=true ACS_EXPECTED_PASS=true
run_assertions check-bot ACS_USER_AGENT='<missing>' ACS_EXPECTED_BOT=false ACS_EXPECTED_PASS=false
configure_options true '' '[]' false '[]' '[]' "$MAINTENANCE_ID" false
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/bot-denied.html" \
	--user-agent 'Googlebot/2.1'
run_assertions check-bot ACS_USER_AGENT='Googlebot/2.1' ACS_EXPECTED_BOT=true ACS_EXPECTED_PASS=false

# IP exclusions must be exact, not substring matches.
configure_options true '' '[]' false '["127.0.0.2"]' '[]' "$MAINTENANCE_ID" false
assert_redirect "$TEST_URL/$PRIVATE_SLUG/" "${RUNNER_TEMP}/ip-mismatch.html"
configure_options true '' '[]' false '["127.0.0.1"]' '[]' "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$PRIVATE_SLUG/" '200' "${RUNNER_TEMP}/ip-match.html"
run_assertions check-ip ACS_REMOTE_ADDR='127.0.0.1' ACS_EXPECTED_BOOL=true
run_assertions check-ip ACS_REMOTE_ADDR='127.0.0.10' ACS_EXPECTED_BOOL=false

# Slug exclusions must match a complete path segment, never query strings or tails.
configure_options true '' '[]' false '[]' "[\"$OPEN_SLUG\"]" "$MAINTENANCE_ID" false
assert_status "$TEST_URL/$OPEN_SLUG/" '200' "${RUNNER_TEMP}/slug-open.html"
assert_body_contains "${RUNNER_TEMP}/slug-open.html" 'ACS_OPEN_MARKER' 'Excluded slug page'
assert_status "$TEST_URL/$OPEN_SLUG/?runtime=open" '200' "${RUNNER_TEMP}/slug-open-query.html"
assert_redirect "$TEST_URL/$OPEN_SLUG-tail/" "${RUNNER_TEMP}/slug-tail.html"
assert_redirect "$TEST_URL/$PRIVATE_SLUG/?next=$OPEN_SLUG" "${RUNNER_TEMP}/slug-query.html"
run_assertions check-slug ACS_REQUEST_URI="/$OPEN_SLUG/?runtime=open" ACS_EXPECTED_BOOL=true
run_assertions check-slug ACS_REQUEST_URI="/$OPEN_SLUG-tail/" ACS_EXPECTED_BOOL=false
run_assertions check-slug ACS_REQUEST_URI="/$PRIVATE_SLUG/?next=/$OPEN_SLUG/" ACS_EXPECTED_BOOL=false

# Missing and unpublished maintenance templates fail closed with HTTP 503.
configure_options true '' '[]' false '[]' '[]' '99999999' false
assert_status "$TEST_URL/" '503' "${RUNNER_TEMP}/invalid-template.html"
assert_body_excludes "${RUNNER_TEMP}/invalid-template.html" 'ACS_PRIVATE_MARKER' 'Invalid-template response'
configure_options true '' '[]' false '[]' '[]' "$DRAFT_ID" false
assert_status "$TEST_URL/" '503' "${RUNNER_TEMP}/draft-template.html"
assert_body_excludes "${RUNNER_TEMP}/draft-template.html" 'ACS_PRIVATE_MARKER' 'Draft-template response'

# Public sitemap, feed, REST, oEmbed, XML-RPC, and AJAX boundaries.
configure_options true admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
rest_request GET "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$ADMIN_AUTHORIZATION" "${RUNNER_TEMP}/settings-admin-maintenance.json" '200'
rest_request GET "$TEST_URL/wp-json/acme-coming-soon/v1/settings" \
	"$SUBSCRIBER_AUTHORIZATION" "${RUNNER_TEMP}/settings-subscriber-maintenance.json" '403'
assert_status "$TEST_URL/wp-json/acme-coming-soon/v1/settings" '401' \
	"${RUNNER_TEMP}/settings-anonymous-maintenance.json"
policy_paths=(
	'/wp-sitemap.xml'
	'/wp-sitemap-posts-page-1.xml'
	'/feed/'
	'/comments/feed/'
	'/wp-json/'
	'/wp-json/wp/v2/posts'
	'/wp-json/wp/v2/users'
	'/xmlrpc.php'
	'/wp-admin/admin-ajax.php'
)
for path in "${policy_paths[@]}"; do
	output="${RUNNER_TEMP}/policy-$(printf '%s' "$path" | tr '/?&=' '____').out"
	assert_policy_response "${TEST_URL}${path}" "$output"
done
assert_policy_response "$TEST_URL/wp-json/oembed/1.0/embed" "${RUNNER_TEMP}/policy-oembed.json" \
	--get --data-urlencode "url=${TEST_URL}/${PRIVATE_SLUG}/"
assert_policy_response "$TEST_URL/xmlrpc.php" "${RUNNER_TEMP}/policy-xmlrpc-post.xml" \
	--request POST --header 'Content-Type: text/xml' \
	--data '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>'

# Authorized administrators retain normal public endpoint behavior.
assert_status "$TEST_URL/wp-sitemap.xml" '200' "${RUNNER_TEMP}/admin-sitemap.xml" \
	--cookie "$COOKIE_DIR/admin.cookies"
assert_status "$TEST_URL/feed/" '200' "${RUNNER_TEMP}/admin-feed.xml" \
	--cookie "$COOKIE_DIR/admin.cookies"
assert_status "$TEST_URL/wp-json/wp/v2/posts" '200' "${RUNNER_TEMP}/admin-rest.json" \
	--header "Authorization: ${ADMIN_AUTHORIZATION}"
assert_status "$TEST_URL/wp-json/oembed/1.0/embed" '200' "${RUNNER_TEMP}/admin-oembed.json" \
	--header "Authorization: ${ADMIN_AUTHORIZATION}" --get --data-urlencode "url=${TEST_URL}/${PRIVATE_SLUG}/"
assert_status "$TEST_URL/wp-admin/admin-ajax.php" '400' "${RUNNER_TEMP}/admin-ajax.txt" \
	--cookie "$COOKIE_DIR/admin.cookies"

# Optional browser smoke: only runs after the main agent adds Playwright.
configure_options true admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
if [[ "${RUN_PLAYWRIGHT:-false}" == 'true' ]]; then
	ACS_TEST_URL="$TEST_URL" \
	ACS_ADMIN_USER="$ADMIN_USER" \
	ACS_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
		node .github/scripts/admin-smoke.mjs
fi
configure_options false admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false

# Settings survive ordinary deactivation/reactivation.
persistence_expected='{"on":false,"access":"roles","exclude":{"useRoles":["editor"],"searchBot":true,"ips":["192.0.2.55"],"slugs":["keep-slug"]},"template":'"$MAINTENANCE_ID"',"deleteAll":false}'
configure_options false roles '["editor"]' true '["192.0.2.55"]' '["keep-slug"]' "$MAINTENANCE_ID" false
assert_options "$persistence_expected"
"${WP[@]}" plugin deactivate acme-coming-soon
run_assertions deactivated \
	ACS_EXPECTED_OPTION_EXISTS=true \
	ACS_EXPECTED_OPTIONS="$persistence_expected"
"${WP[@]}" plugin activate acme-coming-soon
run_assertions persistence ACS_EXPECTED_OPTIONS="$persistence_expected"

# Opt-in deactivation cleanup removes the option.
configure_options false admin '[]' false '[]' '[]' "$MAINTENANCE_ID" true
"${WP[@]}" plugin deactivate acme-coming-soon
run_assertions deactivated ACS_EXPECTED_OPTION_EXISTS=false
"${WP[@]}" plugin activate acme-coming-soon

# Uninstall removes both files and stored plugin options.
configure_options false admin '[]' false '[]' '[]' "$MAINTENANCE_ID" false
"${WP[@]}" plugin deactivate acme-coming-soon
run_assertions deactivated \
	ACS_EXPECTED_OPTION_EXISTS=true \
	ACS_EXPECTED_OPTIONS='{"on":false,"deleteAll":false}'
"${WP[@]}" plugin uninstall acme-coming-soon
run_assertions uninstalled
run_assertions check-debug-log

echo "Acme Coming Soon runtime smoke test passed on WordPress $WP_VERSION / PHP 8.3 / MySQL 8."
