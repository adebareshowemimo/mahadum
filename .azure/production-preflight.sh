#!/usr/bin/env bash
set -euo pipefail

cd /var/www/mahadum
id -un
git -c safe.directory=/var/www/mahadum rev-parse --short HEAD
git -c safe.directory=/var/www/mahadum status --short --branch
systemctl is-active apache2 php8.3-fpm mysql mahadum-queue
test -f .env
grep '^APP_ENV=' .env
grep '^APP_URL=' .env
crontab -u adebareshowemimo -l 2>/dev/null | grep 'artisan schedule:run' || true
