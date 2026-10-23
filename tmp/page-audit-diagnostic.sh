#!/bin/sh
cd /var/www/mahadum || exit 1
printf 'PUBLIC DIRECTORY PERMISSIONS\n'
stat -c '%a %U:%G %n' public public/assets public/images resources/spa/index.html
printf 'ASSET COUNTS\n'
find public/assets -type f | wc -l
printf 'UNREADABLE ASSETS FOR WEB SERVER\n'
sudo -u www-data find public/assets -type f ! -readable 2>&1 | head -15
printf 'OWNER-ONLY ASSET COUNT\n'
find public/assets -type f ! -perm -004 | wc -l
printf 'RECENT STATIC ERRORS\n'
tail -5 /var/log/apache2/mahadum-error.log | cut -c1-450