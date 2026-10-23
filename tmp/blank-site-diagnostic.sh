#!/bin/sh
cd /var/www/mahadum || exit 1
git rev-parse --short HEAD
namei -l public/assets/index-DDR3r9xc.js
stat -c '%a %U:%G %n' public public/assets resources/spa/index.html
find public/assets -maxdepth 1 -type f -printf '%m %u:%g %f\n' | head -8
sudo -u www-data test -r public/assets/index-DDR3r9xc.js && echo readable || echo unreadable
tail -12 /var/log/apache2/mahadum-error.log
