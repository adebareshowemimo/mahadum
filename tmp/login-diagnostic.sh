#!/bin/sh
cd /var/www/mahadum || exit 1
printf 'SERVICES\n'
systemctl is-active apache2 php8.3-fpm mysql
printf 'RECENT ERROR SUMMARIES\n'
find storage/logs -maxdepth 1 -name '*.log' -type f -exec grep -h 'production.ERROR' {} + | tail -10 | cut -c1-1600
printf 'PERMISSIONS\n'
stat -c '%a %U:%G %n' storage storage/logs bootstrap/cache