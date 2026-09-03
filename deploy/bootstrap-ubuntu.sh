#!/usr/bin/env bash
# Bootstrap the fresh Ubuntu 24.04 Azure VM from a release snapshot.
# Run with sudo and provide APP_SOURCE as the extracted snapshot directory.
set -euo pipefail

APP_SOURCE="${APP_SOURCE:-/tmp/mahadum-release}"
APP_DIR="${APP_DIR:-/var/www/mahadum}"
ADMIN_USER="${ADMIN_USER:-adebareshowemimo}"
APP_URL="${APP_URL:-http://20.151.177.171}"
DB_NAME="${DB_NAME:-mahadum}"
DB_USER="${DB_USER:-mahadum_app}"
WEB_USER="www-data"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this installer with sudo." >&2
    exit 1
fi

if [ ! -f "$APP_SOURCE/artisan" ] || [ ! -f "$APP_SOURCE/web/package-lock.json" ]; then
    echo "APP_SOURCE does not contain a MAHADUM.360 release snapshot: $APP_SOURCE" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Updating Ubuntu and installing the application stack"
apt-get update
apt-get upgrade -y
apt-get install -y \
    apache2 ca-certificates composer curl git mysql-server nodejs npm openssl rsync unzip ufw xz-utils \
    php8.3-bcmath php8.3-cli php8.3-curl php8.3-fpm php8.3-gd php8.3-intl \
    php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip

node_major="$(node --version | sed -E 's/^v([0-9]+).*/\1/')"
if [ "$node_major" -lt 20 ]; then
    echo "==> Installing Node.js 22 LTS from the official verified binary"
    node_version="22.23.2"
    node_archive="node-v${node_version}-linux-x64.tar.xz"
    node_download="https://nodejs.org/dist/v${node_version}"
    node_tmp="$(mktemp -d)"
    curl --fail --location --silent --show-error \
        --output "$node_tmp/$node_archive" "$node_download/$node_archive"
    curl --fail --location --silent --show-error \
        --output "$node_tmp/SHASUMS256.txt" "$node_download/SHASUMS256.txt"
    (
        cd "$node_tmp"
        grep "  $node_archive\$" SHASUMS256.txt | sha256sum --check --strict -
    )
    install -d -m 0755 /usr/local/lib/nodejs
    tar -xJf "$node_tmp/$node_archive" -C /usr/local/lib/nodejs
    ln -sfn "/usr/local/lib/nodejs/node-v${node_version}-linux-x64/bin/node" /usr/local/bin/node
    ln -sfn "/usr/local/lib/nodejs/node-v${node_version}-linux-x64/bin/npm" /usr/local/bin/npm
    ln -sfn "/usr/local/lib/nodejs/node-v${node_version}-linux-x64/bin/npx" /usr/local/bin/npx
    rm -rf "$node_tmp"
fi

echo "Using $(node --version) and npm $(npm --version)"

echo "==> Installing the release snapshot"
install -d -m 0755 -o "$ADMIN_USER" -g "$ADMIN_USER" "$APP_DIR"
rsync -a --delete \
    --exclude='.env' \
    --exclude='/.git/' \
    --exclude='/vendor/' \
    --exclude='/web/node_modules/' \
    --exclude='/web/dist/' \
    --exclude='/public/assets/' \
    --exclude='/public/storage' \
    --exclude='/resources/spa/' \
    --exclude='/storage/logs/*' \
    "$APP_SOURCE/" "$APP_DIR/"
chown -R "$ADMIN_USER":"$ADMIN_USER" "$APP_DIR"

echo "==> Creating the local MySQL database"
db_password="$(openssl rand -hex 24)"
mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$db_password';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$db_password';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Creating the application environment"
cd "$APP_DIR"
cp .env.example .env

set_env() {
    local key="$1"
    local value="$2"
    if grep -qE "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

set_env APP_NAME '"MAHADUM.360"'
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env LOG_LEVEL info
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$db_password"
set_env SANCTUM_STATEFUL_DOMAINS 20.151.177.171
set_env FRONTEND_URLS "$APP_URL"
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database
set_env SESSION_DRIVER database
set_env MAIL_MAILER log
set_env PAYMENT_GATEWAY_LIVE false
set_env TELCO_SDP_LIVE false
set_env MESSAGING_LIVE false

composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force

echo "==> Configuring Apache and PHP-FPM"
cp deploy/apache/mahadum-ip.conf /etc/apache2/sites-available/mahadum.conf
cp deploy/php/99-mahadum-upload.ini /etc/php/8.3/fpm/conf.d/99-mahadum-upload.ini
cp deploy/php/99-mahadum-upload.ini /etc/php/8.3/cli/conf.d/99-mahadum-upload.ini
a2enmod headers rewrite proxy_fcgi setenvif
a2enconf php8.3-fpm
a2dissite 000-default >/dev/null || true
a2ensite mahadum >/dev/null
apache2ctl configtest
systemctl enable --now php8.3-fpm apache2 mysql
systemctl reload apache2

echo "==> Deploying the application"
SKIP_GIT_PULL=1 APP_DIR="$APP_DIR" WEB_USER="$WEB_USER" bash ./deploy/deploy.sh

echo "==> Loading review content with randomized passwords"
if ! php artisan tinker --execute="exit(\\App\\Models\\User::query()->exists() ? 0 : 1);"; then
    php artisan db:seed --class="Database\\Seeders\\DevSeeder" --force
fi

review_password="$(openssl rand -base64 24 | tr -d '\n')"
MAHADUM_REVIEW_PASSWORD="$review_password" php artisan tinker --execute="\\App\\Models\\User::query()->each(fn (\\App\\Models\\User \$user) => \$user->forceFill(['password' => \\Illuminate\\Support\\Facades\\Hash::make(getenv('MAHADUM_REVIEW_PASSWORD'))])->save());"

credential_file="/home/$ADMIN_USER/mahadum-review-credentials.txt"
install -m 0600 -o "$ADMIN_USER" -g "$ADMIN_USER" /dev/null "$credential_file"
{
    echo 'MAHADUM.360 review login'
    echo 'URL: http://20.151.177.171'
    echo 'Email: super@dev.mahadum360'
    echo "Password: $review_password"
} > "$credential_file"
chown "$ADMIN_USER":"$ADMIN_USER" "$credential_file"
chmod 0600 "$credential_file"

echo "==> Installing queue worker and scheduler"
cp deploy/systemd/mahadum-queue.service /etc/systemd/system/mahadum-queue.service
cat > /etc/cron.d/mahadum-scheduler <<CRON
* * * * * $WEB_USER cd $APP_DIR && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/mahadum-scheduler
systemctl daemon-reload
systemctl enable --now mahadum-queue

echo "==> Applying the host firewall"
ufw allow OpenSSH
ufw allow 80/tcp
ufw --force enable

echo "==> Hardening SSH authentication"
install -m 0644 deploy/ssh/00-mahadum-hardening.conf \
    /etc/ssh/sshd_config.d/00-mahadum-hardening.conf
sshd -t
systemctl reload ssh

echo "==> Final verification"
systemctl is-active --quiet apache2 php8.3-fpm mysql mahadum-queue
curl --fail --silent --show-error --max-time 15 http://127.0.0.1/up >/dev/null
php artisan about --only=environment,cache,drivers

echo "Fresh deployment completed at $APP_URL"
echo "Review credentials are stored at $credential_file"
