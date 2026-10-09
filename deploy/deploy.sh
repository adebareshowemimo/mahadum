#!/usr/bin/env bash
# Deploy/update mahadum on the staging box. Run from the app directory
# (or set APP_DIR) as the deploy user. The bootstrap script configures storage
# and bootstrap/cache for shared deploy-user / web-server access.
#
# Usage: ./deploy/deploy.sh
set -euo pipefail
# Public build artifacts must remain readable by Apache even when the deploy
# user's shell has a restrictive umask (for example after a database backup).
umask 022

APP_DIR="${APP_DIR:-/var/www/mahadum}"
BRANCH="${BRANCH:-main}"
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
PUBLISH_BACKUP=""
PUBLISHED_PATHS=()

cleanup() {
    if [ -n "$PUBLISH_BACKUP" ]; then
        rm -rf -- "$PUBLISH_BACKUP"
    fi
}
trap cleanup EXIT

rollback() {
    local exit_code=$?
    echo "==> Deploy failed (exit $exit_code). Rolling back to $PREVIOUS_COMMIT" >&2
    if [ -n "$PREVIOUS_COMMIT" ]; then
        git checkout "$PREVIOUS_COMMIT" --quiet || true
    fi
    # Build artifacts are untracked. Restore them along with PHP so a failed
    # deploy cannot leave a new editor talking to an older API.
    if [ -n "$PUBLISH_BACKUP" ]; then
        for target in "${PUBLISHED_PATHS[@]}"; do
            rm -rf -- "$target"
            if [ -e "$PUBLISH_BACKUP/$target" ]; then
                cp -a "$PUBLISH_BACKUP/$target" "$target" || true
            fi
        done
    fi
    composer install --no-dev --optimize-autoloader --no-interaction --quiet || true
    php artisan config:cache || true
    # Run this in its own Artisan process. If the process starts with an
    # existing route cache, Laravel's route:cache command can otherwise reuse
    # the already-loaded CompiledRouteCollection when booting its fresh app.
    php artisan route:clear || true
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

# Existing Git/Composer files can retain owner-only modes from an earlier
# deployment. PHP-FPM needs to traverse and read the application code too.
# Leave .env, runtime caches and uploaded files under their existing policy.
for code_dir in app bootstrap config database routes resources vendor; do
    find "$code_dir" -path bootstrap/cache -prune -o -type d -exec chmod 0755 {} +
    find "$code_dir" -path bootstrap/cache -prune -o -type f -exec chmod a+r {} +
done

echo "==> Building the SPA"
(cd web && npm ci && npm run build)

echo "==> Publishing SPA build into public/ and resources/spa/"
PUBLISH_BACKUP="$(mktemp -d /tmp/mahadum-spa-backup-XXXXXX)"
while IFS= read -r -d '' source_path; do
    target="public/$(basename "$source_path")"
    mkdir -p "$PUBLISH_BACKUP/public"
    if [ -e "$target" ]; then
        cp -a "$target" "$PUBLISH_BACKUP/$target"
    fi
    PUBLISHED_PATHS+=("$target")
done < <(find web/dist -mindepth 1 -maxdepth 1 ! -name 'index.html' -print0)
mkdir -p "$PUBLISH_BACKUP/resources/spa"
if [ -f resources/spa/index.html ]; then
    cp -a resources/spa/index.html "$PUBLISH_BACKUP/resources/spa/index.html"
fi
PUBLISHED_PATHS+=("resources/spa/index.html")
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
# cp preserves restrictive source modes. Normalize only the published SPA
# artifacts, never private application files or uploaded media.
for target in "${PUBLISHED_PATHS[@]}"; do
    if [ -d "$target" ]; then
        find "$target" -type d -exec chmod 0755 {} +
        find "$target" -type f -exec chmod 0644 {} +
    else
        chmod 0644 "$target"
    fi
done

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

echo "==> Syncing achievement badge definitions"
# Completion can award First Steps only when its canonical definition exists.
php artisan db:seed --class="Database\Seeders\BadgeSeeder" --force

echo "==> Caching config/routes/views"
php artisan config:cache
# route:cache clears the cache internally, but that is too late when this
# Artisan process booted with compiled routes. Clearing in a separate process
# guarantees route:cache builds from a fresh RouteCollection.
php artisan route:clear
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

echo "==> Preserving storage/cache permissions"
mkdir -p storage/app/public/media
# A shared writable group permits file access, but only the owner (or root)
# can chmod a file. Uploaded media and invoice PDFs belong to www-data;
# trying to chmod them as the deploy user aborts an otherwise healthy deploy.
find storage bootstrap/cache -user "$(id -un)" \( -type f -o -type d \) -exec chmod ug+rwX {} +
# Preserve the configured shared group on files created by either Artisan or
# the web server. Ownership is provisioned once by bootstrap-ubuntu.sh.
find storage bootstrap/cache -user "$(id -un)" -type d -exec chmod g+s {} +

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
# A healthy API does not prove that Apache can serve the SPA. Check every
# bundled script/stylesheet against the same origin before accepting release.
SPA_ORIGIN="${SPA_ORIGIN:-${HEALTH_URL%/up}}"
while IFS= read -r asset_path; do
    if ! curl --fail --silent --show-error --max-time 10 "$SPA_ORIGIN$asset_path" > /dev/null; then
        echo "==> SPA asset check failed: $asset_path" >&2
        false # triggers rollback
    fi
done < <(php -r 'preg_match_all("~(?:src|href)=\"(/assets/[^\"]+\\.(?:js|css))\"~", file_get_contents("resources/spa/index.html"), $matches); echo implode(PHP_EOL, array_unique($matches[1])).PHP_EOL;')

trap - ERR
if [ -n "$PREVIOUS_COMMIT" ]; then
    echo "Done. Deployed $(git rev-parse --short HEAD) (was $(git rev-parse --short "$PREVIOUS_COMMIT"))."
else
    echo "Done. Deployed supplied release snapshot."
fi
