# Field Sales Production Operations Runbook

This runbook describes the production deployment model delivered by Stage 2 Batch 17.

The templates assume a Linux host with Nginx, PHP 8.5 FPM/CLI, Composer, MySQL client tools, systemd, Git, tar, gzip, and curl. Package installation commands are intentionally not hard-coded because repository/package names differ by distribution. The server must provide the PHP extensions required by Laravel and MySQL.

## 1. Deployment layout

The production layout is release-based:

```text
/var/www/field-sales/
├── current -> releases/<active-release>
├── previous -> releases/<previous-release>
├── releases/
└── shared/
    ├── .env
    └── storage/
```

Application code is immutable per release. The production `.env` and Laravel `storage` directory live in `shared` and are symlinked into every release.

Default backup path:

```text
/var/backups/field-sales/
└── <timestamp>-<label>-<id>/
    ├── database.sql.gz
    ├── public-storage.tar.gz
    └── manifest.json
```

The manifest contains SHA-256 hashes for restore verification.

## 2. Server prerequisites

Required commands:

- `php` (PHP 8.5 CLI)
- PHP-FPM 8.5
- `composer`
- `git`
- `mysqldump` and `mysql`
- `tar`
- `gzip`
- `curl`
- `nginx`
- `systemctl`

The deployment user needs read access to the private Git repository, normally through a GitHub deploy key or another non-interactive SSH credential. Never commit a private key or access token to this repository.

Create the shared directories using your deployment account and the web-server group. One conventional setup is:

```bash
sudo mkdir -p /var/www/field-sales/{releases,shared/storage}
sudo mkdir -p /var/backups/field-sales
sudo mkdir -p /etc/field-sales

sudo chown -R deploy:www-data /var/www/field-sales
sudo chown deploy:www-data /var/backups/field-sales

sudo chmod 2775 /var/www/field-sales
sudo chmod 2775 /var/www/field-sales/releases
sudo chmod 2775 /var/www/field-sales/shared
sudo chmod 2775 /var/www/field-sales/shared/storage
sudo chmod -R g+rwX /var/www/field-sales/shared/storage
sudo chmod 2770 /var/backups/field-sales
```

Replace `deploy` if your deployment account has another name. Ensure the deployment account is a member of the web-server group and that `www-data` can write the shared Laravel storage and backup directories.

## 3. Production environment

Start from `.env.production.example`:

```bash
cp .env.production.example /var/www/field-sales/shared/.env
chmod 0640 /var/www/field-sales/shared/.env
```

Configure at minimum:

- a unique `APP_KEY`
- the production HTTPS `APP_URL`
- MySQL credentials
- `APP_DEBUG=false`
- encrypted and secure sessions
- backup path and retention
- push provider settings if push delivery is enabled

Do not copy a development `.env` into production.

## 4. First deployment

Export the private repository URL for the deployment shell:

```bash
export FIELD_SALES_REPO_URL=git@github.com:Haider-Shakoori/field-sales-web.git
```

Run the deploy script from a checked-out copy of the repository:

```bash
bash ops/scripts/deploy.sh main
```

The deployment script performs these gates in order. On an existing deployment, Laravel maintenance mode prevents idle workers from taking new jobs while migrations and the release switch are in progress:

1. Fetches the requested Git ref into a new immutable release.
2. Links shared `.env` and Laravel storage.
3. Installs production Composer dependencies.
4. Builds Laravel configuration, route, and view caches.
5. Runs the configuration-only production readiness check.
6. Creates a **pre-deploy database snapshot**. Uploaded media is not re-archived on every code deployment because migrations do not mutate visit-photo files.
7. Places the currently active release in maintenance mode when applicable.
8. Runs migrations with `--force`.
9. Verifies runtime/service readiness.
10. Atomically switches the `current` symlink.
11. Restarts queue workers through Laravel's queue restart signal.
12. Brings the application out of maintenance mode.
13. Retains the newest releases while preserving the current and previous targets.

The default retained release count is five. Override it with `FIELD_SALES_KEEP_RELEASES`.

## 5. Nginx and TLS

For the first certificate issuance, copy `ops/nginx/field-sales-bootstrap.conf`, replace `FIELD_SALES_DOMAIN`, and enable it as an Nginx site. It serves the application over HTTP temporarily and exposes the ACME challenge path.

After obtaining the TLS certificate, replace the bootstrap configuration with `ops/nginx/field-sales.conf`, again replacing `FIELD_SALES_DOMAIN`, then validate and reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

The final configuration redirects HTTP to HTTPS, limits request bodies to 8 MB, denies hidden/sensitive files, serves Laravel from the active release symlink, and uses the PHP 8.5 FPM socket.

If your distribution uses a different FPM socket path, update `fastcgi_pass` before enabling the site.

## 6. PHP runtime

`ops/php/99-field-sales.ini` contains conservative production overrides for PHP-FPM and CLI. Install an equivalent override into the configuration paths used by both SAPIs, then restart PHP-FPM.

The file enables OPcache, hides PHP exposure/errors from users, keeps server-side logging on, and sizes upload/post limits for the current 5 MB visit-photo rule.

## 7. Queue workers

Install the worker template:

```bash
sudo cp ops/systemd/field-sales-queue@.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now field-sales-queue@1.service
sudo systemctl enable --now field-sales-queue@2.service
```

Two workers are a reasonable starting point, not a permanent sizing rule. Increase or reduce worker count based on observed queue depth, CPU, and database capacity.

Check workers:

```bash
systemctl status field-sales-queue@1.service
journalctl -u field-sales-queue@1.service
```

## 8. Laravel scheduler

Install and enable the systemd scheduler timer:

```bash
sudo cp ops/systemd/field-sales-scheduler.service /etc/systemd/system/
sudo cp ops/systemd/field-sales-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now field-sales-scheduler.timer
```

Verify:

```bash
systemctl list-timers field-sales-scheduler.timer
php /var/www/field-sales/current/artisan schedule:list
```

Batch 17 schedules failed queue-job pruning after seven days. Business workflows are not scheduled here.

## 9. Backups

Install and enable the backup timer:

```bash
sudo cp ops/systemd/field-sales-backup.service /etc/systemd/system/
sudo cp ops/systemd/field-sales-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now field-sales-backup.timer
```

The timer runs daily at 02:15 UTC with a randomized delay of up to 15 minutes.

Run an on-demand backup:

```bash
cd /var/www/field-sales/current
php artisan field-sales:backup --label=manual
```

A normal scheduled/manual backup contains:

- a compressed MySQL dump including database creation/drop statements for deterministic disaster recovery
- uploaded visit media from `storage/app/public`
- a manifest with SHA-256 checksums

Pre-deploy snapshots use `--database-only`; scheduled/manual backups include both database and media. Retention defaults to 14 days.

The production `.env`, TLS private keys, Git deploy key, MySQL restore credentials, and other server secrets are intentionally **not** included in application backups. Protect and recover those through your server/secret-management process. **Local server backups alone are not sufficient disaster recovery.** Replicate `/var/backups/field-sales` to an independent encrypted/off-site target using your infrastructure provider or backup system. Credentials for that external target must remain outside this repository.

## 10. Restore test and disaster restore

A backup is not considered operationally trustworthy until a restore has been tested on a non-production environment.

The destructive restore script requires:

- the backup directory
- the explicit `--confirm-destructive-restore` flag
- `FIELD_SALES_MYSQL_DEFAULTS_FILE`, pointing to a mode-600 MySQL client option file whose account may drop/create/restore the application database

Example protected MySQL defaults file:

```ini
[client]
host=127.0.0.1
port=3306
user=field_sales_restore
password=REPLACE_WITH_SECRET
```

Protect it:

```bash
chmod 0600 /secure/path/field-sales-restore.cnf
```

Restore:

```bash
export FIELD_SALES_MYSQL_DEFAULTS_FILE=/secure/path/field-sales-restore.cnf
bash /var/www/field-sales/current/ops/scripts/restore-backup.sh \
    /var/backups/field-sales/<backup-directory> \
    --confirm-destructive-restore
```

The restore flow verifies manifest checksums and tooling first, enters maintenance mode, takes a stable pre-restore backup, restores the database and public media, migrates forward to the current release if needed, runs readiness checks, restarts queues, and only then re-enables traffic.

If any destructive restore step fails, the application intentionally remains in maintenance mode for operator intervention.

## 11. Monitoring

The local monitoring command checks:

- database connectivity
- required queue/infrastructure tables
- writable Laravel runtime directories
- queue backlog threshold
- failed-job threshold
- oldest queued-job age

Run the complete production check manually:

```bash
php /var/www/field-sales/current/artisan field-sales:ops-check --backup-tooling
```

Without `--backup-tooling`, the command checks application runtime and queue health only.

Install the five-minute timer:

```bash
sudo cp ops/systemd/field-sales-monitor.service /etc/systemd/system/
sudo cp ops/systemd/field-sales-monitor.timer /etc/systemd/system/
sudo cp ops/monitor.env.example /etc/field-sales/monitor.env
sudo systemctl daemon-reload
sudo systemctl enable --now field-sales-monitor.timer
```

Set `FIELD_SALES_HEALTH_URL` in `/etc/field-sales/monitor.env` to the public HTTPS `/ready` endpoint. When configured, the monitor checks both Laravel internals and the externally routed HTTP endpoint.

Inspect failures:

```bash
systemctl status field-sales-monitor.service
journalctl -u field-sales-monitor.service
```

The systemd timer produces local failure evidence; production alert delivery should be connected at the infrastructure level (for example, your host monitoring platform) rather than embedding infrastructure credentials in Laravel.

## 12. Health endpoints

- `/up`: Laravel process/liveness
- `/ready`: database/infrastructure/filesystem readiness
- `php artisan field-sales:ops-check`: deeper queue + runtime operational health

Use `/ready`, not `/up`, as the primary service-readiness monitor.

## 13. Code rollback

To switch back to the previous code release:

```bash
bash /var/www/field-sales/current/ops/scripts/rollback.sh
```

Or specify a release directory explicitly:

```bash
bash /var/www/field-sales/current/ops/scripts/rollback.sh \
    /var/www/field-sales/releases/<release>
```

Rollback first verifies that the target code can pass service readiness against the **current database schema**.

Database migrations are deliberately **not** reversed automatically. Automatic `migrate:rollback` can destroy data and is not safe as a generic deployment rollback strategy. If a release introduced an incompatible/destructive schema change, keep the site in maintenance mode and restore the matching pre-deploy backup using the documented disaster-restore process.

## 14. Release verification

After every production deployment:

```bash
curl --fail https://FIELD_SALES_DOMAIN/up
curl --fail https://FIELD_SALES_DOMAIN/ready
php /var/www/field-sales/current/artisan field-sales:production-check --services
php /var/www/field-sales/current/artisan field-sales:ops-check
systemctl --no-pager --full status field-sales-queue@1.service
systemctl --no-pager --full status field-sales-scheduler.timer
systemctl --no-pager --full status field-sales-backup.timer
systemctl --no-pager --full status field-sales-monitor.timer
```

Batch 19 will perform the application-level release-candidate/UAT golden paths. This runbook covers infrastructure and operational readiness only.
