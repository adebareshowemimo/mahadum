# Staging deploy — mahadum.agunfoninteractivity.com

Single-subdomain setup: Apache serves the built React SPA as static files and
routes `/api`, `/sanctum`, `/storage`, `/up` to Laravel (PHP); everything else
falls back to the SPA's `index.html` via the route in `routes/web.php`. Same
origin for both, so no CORS/cross-domain cookie config is needed.

Assumes Apache2, PHP 8.3, Composer, and Node are already installed.

## One-time setup

1. **DNS** — point `mahadum.agunfoninteractivity.com` (A record) at the
   server's IP.

2. **Database**
   ```bash
   # edit the password first
   mysql -u root -p < deploy/mysql-setup.sql
   ```

3. **Clone the app**
   ```bash
   sudo mkdir -p /var/www/mahadum
   sudo chown "$USER" /var/www/mahadum
   git clone https://github.com/adebareshowemimo/mahadum.git /var/www/mahadum
   cd /var/www/mahadum
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   # apply the overrides in deploy/env.staging.example (fill in CHANGE_ME values)
   php artisan key:generate
   ```

5. **First deploy** — same script used for every subsequent deploy:
   ```bash
   APP_DIR=/var/www/mahadum ./deploy/deploy.sh
   ```

6. **Apache vhost**
   ```bash
   sudo cp deploy/apache/mahadum-staging.conf /etc/apache2/sites-available/
   sudo a2enmod rewrite headers
   sudo a2ensite mahadum-staging
   sudo systemctl reload apache2
   sudo certbot --apache -d mahadum.agunfoninteractivity.com
   ```
   If PHP runs via php-fpm rather than mod_php, confirm
   `sudo a2enconf php8.3-fpm` is enabled (Ubuntu's default php-fpm Apache
   integration) — the vhost itself doesn't need FPM-specific directives.

7. **Queue worker**
   ```bash
   sudo cp deploy/systemd/mahadum-queue.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now mahadum-queue
   ```

8. **Scheduler cron** — the app relies on `routes/console.php` scheduled
   commands (billing, escrow clearing, referral velocity checks, teacher
   compensation accrual, subscription reminders, webhook pruning, scheduled
   emails). Add to the deploy user's crontab (`crontab -e`):
   ```
   * * * * * cd /var/www/mahadum && php artisan schedule:run >> /dev/null 2>&1
   ```

## Subsequent deploys

```bash
cd /var/www/mahadum && ./deploy/deploy.sh
```

When updating the deploy script itself, fetch, check out your deployment
branch, and pull it before starting the script. A running script keeps using
the version it started with, even after its own Git pull updates the file:

```bash
git fetch origin
git checkout codex/beta-feedback-20260903
git pull --ff-only origin codex/beta-feedback-20260903
BRANCH=codex/beta-feedback-20260903 ./deploy/deploy.sh
```

Pulls `main`, reinstalls dependencies, rebuilds the SPA, migrates, **re-syncs
RBAC roles/permissions** (idempotent — picks up any new permission a commit
added, e.g. `emails.*`), re-caches config, and gracefully restarts the queue
worker.

The script is safe to re-run and re-trigger:
- **Concurrency lock** (`flock` on `/tmp/mahadum-deploy.lock`) — a second
  deploy started while one is in flight exits immediately instead of racing it.
- **Maintenance mode** wraps only the migrate/cache window, not the
  build (`php artisan down` → migrate → cache → `php artisan up`).
- **Auto-rollback on failure** — any failed step (including a failed
  `/up` health check after restart) checks the code back out to the
  commit that was live before the deploy started, re-caches, and takes
  the app back out of maintenance mode. SPA files overwritten by the release
  are backed up before publishing and restored with the previous code, so
  the interface and API stay on the same release. Completed database
  migrations are retained; rollback does not reverse migrations.
- **Shared runtime files** — permission updates apply only to files owned
  by the user running the deploy. Uploads and generated PDFs owned by
  `www-data` keep their existing permissions. The shared group and directory
  ownership are provisioned by `bootstrap-ubuntu.sh`.
- Override `HEALTH_URL` (defaults to `http://127.0.0.1/up`) if the app
  isn't reachable on localhost, or `LOCK_FILE` if running multiple
  apps' deploys need distinct locks on the same box.
