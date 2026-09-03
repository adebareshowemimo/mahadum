#!/usr/bin/env bash
# Smoke-test the freshly deployed review environment without printing secrets.
set -euo pipefail

APP_URL="${APP_URL:-http://127.0.0.1}"
CREDENTIAL_FILE="${CREDENTIAL_FILE:-$HOME/mahadum-review-credentials.txt}"
APP_DIR="${APP_DIR:-/var/www/mahadum}"

review_password="$(sed -n 's/^Password: //p' "$CREDENTIAL_FILE")"
if [ -z "$review_password" ]; then
    echo "Review password is missing from $CREDENTIAL_FILE" >&2
    exit 1
fi

response_file="$(mktemp)"
spa_file="$(mktemp)"
bundle_file="$(mktemp)"
trap 'rm -f "$response_file" "$spa_file" "$bundle_file"' EXIT

status="$(curl --silent --show-error --output "$response_file" --write-out '%{http_code}' \
    --request POST \
    --data-urlencode 'login=super@dev.mahadum360' \
    --data-urlencode "password=$review_password" \
    --data-urlencode 'device_name=deployment-check' \
    "$APP_URL/api/v1/auth/login")"

test "$status" = '200'
grep -q '"token"' "$response_file"

# A server-side 200 is not enough for a client-side deep link: Apache may serve
# a stale SPA whose router falls through to NotFound. Verify the deployed entry
# bundle actually contains the public referral route.
curl --fail --silent --show-error "$APP_URL/r/DEPLOYCHK" --output "$spa_file"
grep -q '<div id="root"></div>' "$spa_file"
bundle_path="$(sed -n 's#.*src="\(/assets/index-[^"]*\.js\)".*#\1#p' "$spa_file" | head -n 1)"
test -n "$bundle_path"
curl --fail --silent --show-error "$APP_URL$bundle_path" --output "$bundle_file"
grep -Fq '"/r/:code"' "$bundle_file"

cd "$APP_DIR"
counts="$(php artisan tinker --execute='$users = \App\Models\User::query()->count(); $courses = \App\Models\Course::query()->count(); $english = \App\Models\Language::query()->where("name", "English")->count(); $pidgin = \App\Models\Language::query()->where("name", "like", "%Pidgin%")->count(); echo "Users: {$users}; courses: {$courses}; English: {$english}; Pidgin: {$pidgin}\n";')"
echo "$counts"
grep -Eq 'Users: [1-9][0-9]*; courses: [1-9][0-9]*; English: [1-9][0-9]*; Pidgin: 0' <<< "$counts"

echo "Authentication, referral deep links, users, and courses verified (HTTP $status)."
