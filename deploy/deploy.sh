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

cd "$APP_DIR"

echo "==> Pulling $BRANCH"
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Building the SPA"
(cd web && npm ci && npm run build)

echo "==> Publishing SPA build into public/ and resources/spa/"
mkdir -p resources/spa public/assets
rm -rf public/assets/*
cp -r web/dist/assets/. public/assets/
cp web/dist/index.html resources/spa/index.html
find web/dist -maxdepth 1 -type f ! -name 'index.html' -exec cp {} public/ \;

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
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Restarting queue worker (graceful — finishes in-flight jobs first)"
php artisan queue:restart

echo "Done."
