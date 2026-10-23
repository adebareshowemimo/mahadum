#!/usr/bin/env bash
# Sent over SSH by download-production-db.ps1; stdout is gzip data ONLY.
set -euo pipefail
umask 077

APP_DIR="${1:-/var/www/mahadum}"
cd "$APP_DIR"
command -v php >/dev/null || { echo 'PHP is unavailable.' >&2; exit 1; }
command -v gzip >/dev/null || { echo 'gzip is unavailable.' >&2; exit 1; }
if command -v mariadb-dump >/dev/null; then
    DUMP_BIN="$(command -v mariadb-dump)"
elif command -v mysqldump >/dev/null; then
    DUMP_BIN="$(command -v mysqldump)"
else
    echo 'Install the MariaDB/MySQL client tools on the server first.' >&2
    exit 1
fi

BACKUP_TEMP="$(mktemp -d /tmp/mahadum-db-backup.XXXXXXXX)"
trap 'rm -f -- "$BACKUP_TEMP/client.cnf" "$BACKUP_TEMP/database"; rmdir -- "$BACKUP_TEMP"' EXIT
export MAHADUM_BACKUP_TEMP="$BACKUP_TEMP"

php -d display_errors=stderr -d log_errors=0 <<'PHP'
<?php
try {
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Laravel resolves cached configuration and DB_URL without exporting .env.
    $config = $app['db']->connection()->getConfig();
    if (!in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
        throw new RuntimeException('Unsupported database driver.');
    }
    $database = $config['database'] ?? '';
    if (!is_string($database) || !preg_match('/^[A-Za-z0-9_$-]+$/D', $database)) {
        throw new RuntimeException('Unsupported database name.');
    }
    $quote = static fn ($value) => '"'.str_replace(
        ["\\", '"', "\n", "\r", "\t", "\0"],
        ["\\\\", '\\"', '\\n', '\\r', '\\t', '\\0'],
        (string) $value
    ).'"';
    $options = ['host' => $config['host'] ?? '127.0.0.1', 'port' => $config['port'] ?? 3306,
        'user' => $config['username'] ?? '', 'password' => $config['password'] ?? ''];
    if (!empty($config['unix_socket'])) {
        $options['socket'] = $config['unix_socket'];
    }
    foreach (['MYSQL_ATTR_SSL_CA' => 'ssl-ca', 'MYSQL_ATTR_SSL_CERT' => 'ssl-cert', 'MYSQL_ATTR_SSL_KEY' => 'ssl-key'] as $constant => $option) {
        if (defined('PDO::'.$constant) && !empty($config['options'][constant('PDO::'.$constant)])) {
            $options[$option] = $config['options'][constant('PDO::'.$constant)];
        }
    }
    $contents = "[client]\n";
    foreach ($options as $key => $value) {
        $contents .= $key.'='.$quote($value)."\n";
    }
    $directory = getenv('MAHADUM_BACKUP_TEMP');
    if (file_put_contents($directory.'/client.cnf', $contents) === false
        || file_put_contents($directory.'/database', $database) === false) {
        throw new RuntimeException('Could not prepare private backup configuration.');
    }
} catch (Throwable $error) {
    // Do not print a configuration object, credentials or exception trace.
    fwrite(STDERR, "Could not read the application's MySQL configuration. Check the app path and server file permissions.\n");
    exit(1);
}
PHP

DATABASE="$(cat "$BACKUP_TEMP/database")"
EXTRA_OPTIONS=()
case "$("$DUMP_BIN" --version)" in
    *MariaDB*) ;;
    *) EXTRA_OPTIONS+=(--set-gtid-purged=OFF) ;;
esac

# The application remains online. Avoid schema changes during this snapshot.
# A failed dump/compression returns nonzero so the downloader rejects the file.
"$DUMP_BIN" --defaults-extra-file="$BACKUP_TEMP/client.cnf" \
    --single-transaction --quick --skip-lock-tables --no-tablespaces \
    --routines --events --triggers --hex-blob --default-character-set=utf8mb4 \
    "${EXTRA_OPTIONS[@]}" --databases "$DATABASE" | gzip -c
