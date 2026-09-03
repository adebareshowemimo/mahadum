#!/usr/bin/env bash
# Deploy/update mahadum on the staging box. Run from the app directory
# (or set APP_DIR) as a user with sudo for the *first* run (permissions,
# artisan storage:link); subsequent runs can be the deploy user only.
#
# Usage: ./deploy/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mahadum}"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
LOCK_FILE="${LOCK_FILE:-/tmp/mahadum-deploy.lock}"
SKIP_GIT_PULL="${SKIP_GIT_PULL:-0}"

cd "$APP_DIR"

# ---- Prevent two deploys from stepping on each other ----
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another deploy is already running (lock: $LOCK_FILE). Aborting." >&2
    exit 1
fi

PREVIOUS_COMMIT=""
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    PREVIOUS_COMMIT="$(git rev-parse HEAD)"
fi
MAINTENANCE_ON=0

rollback() {
    local exit_code=$?
    echo "==> Deploy failed (exit $exit_code). Rolling back to $PREVIOUS_COMMIT" >&2
    if [ -n "$PREVIOUS_COMMIT" ]; then
        git checkout "$PREVIOUS_COMMIT" --quiet || true
    fi
    composer install --no-dev --optimize-autoloader --no-interaction --quiet || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    if [ "$MAINTENANCE_ON" = "1" ]; then
        php artisan up || true
    fi
    echo "==> Rolled back to $PREVIOUS_COMMIT. Investigate before retrying." >&2
    exit "$exit_code"
}
trap rollback ERR

if [ "$SKIP_GIT_PULL" = "1" ]; then
    echo "==> Using the supplied release snapshot"
else
    echo "==> Pulling $BRANCH"
    git fetch origin
    git checkout "$BRANCH"
    git pull origin "$BRANCH"
fi

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Building the SPA"
(cd web && npm ci && npm run build)

echo "==> Publishing SPA build into public/ and resources/spa/"
mkdir -p resources/spa
# Vite emits bundled code under assets/ and copies every directory from
# web/public (including images/) to the dist root. Publish both kinds of
# directories; copying assets alone leaves the landing-page artwork behind.
find web/dist -mindepth 1 -maxdepth 1 -type d -print0 |
    while IFS= read -r -d '' source_dir; do
        target_dir="public/$(basename "$source_dir")"
        rm -rf "$target_dir"
        cp -r "$source_dir" "$target_dir"
    done
cp web/dist/index.html resources/spa/index.html
find web/dist -maxdepth 1 -type f ! -name 'index.html' -exec cp {} public/ \;

echo "==> Entering maintenance mode"
php artisan down --retry=15 || true
MAINTENANCE_ON=1

echo "==> Running migrations"
php artisan migrate --force

echo "==> Syncing RBAC roles & permissions"
# Idempotent (findOrCreate/syncPermissions) — safe on every deploy. Keeps the
# live permission matrix in sync whenever a commit adds/renames a permission
# (e.g. the emails.* group), without a manual step.
php artisan db:seed --class="Database\Seeders\RolesAndPermissionsSeeder" --force

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

echo "==> Fixing storage/cache permissions"
install -d -m 0775 -o "$WEB_USER" -g "$WEB_USER" storage/app/public/media
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Leaving maintenance mode"
php artisan up
MAINTENANCE_ON=0

echo "==> Restarting queue worker (graceful — finishes in-flight jobs first)"
php artisan queue:restart

echo "==> Health check"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/up}"
if ! curl --fail --silent --show-error --max-time 10 "$HEALTH_URL" > /dev/null; then
    echo "==> Health check against $HEALTH_URL failed" >&2
    false # triggers the ERR trap → rollback
fi

trap - ERR
if [ -n "$PREVIOUS_COMMIT" ]; then
    echo "Done. Deployed $(git rev-parse --short HEAD) (was $(git rev-parse --short "$PREVIOUS_COMMIT"))."
else
    echo "Done. Deployed supplied release snapshot."
fi
