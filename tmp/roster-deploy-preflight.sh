#!/usr/bin/env bash
set -euo pipefail
cd /var/www/mahadum
echo '=== SOURCE ==='
if [ -d .git ]; then
  git -c safe.directory=/var/www/mahadum rev-parse HEAD
  git -c safe.directory=/var/www/mahadum status --short
  git -c safe.directory=/var/www/mahadum branch --show-current
else
  echo 'No git checkout; immutable snapshot deployment'
fi
find . -maxdepth 1 -type f -iname '*release*' -printf '%f\n'
echo '=== RUNTIME ==='
stat -c '%U:%G %a %n' . .env storage bootstrap/cache
php --version | head -n 1
node --version
composer --version 2>/dev/null | head -n 1
command -v mysqldump || command -v mariadb-dump
command -v rsync
df -h /var/www/mahadum
systemctl is-active apache2 php8.3-fpm mysql mahadum-queue
echo '=== HEALTH ==='
curl --fail --silent --show-error --max-time 15 https://mahadum360.com/up
echo
echo '=== MIGRATIONS ==='
sudo -u www-data php artisan migrate:status --no-ansi | tail -n 12
echo '=== SOURCE HASHES ==='
sha256sum app/Http/Controllers/School/RosterController.php app/Http/Controllers/School/SchoolClassController.php app/Services/School/RosterIdentityService.php composer.lock web/package-lock.json
