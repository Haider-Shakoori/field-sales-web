# FieldPulse on cPanel / Shared Hosting

This profile is the production runbook for the current BusinessOS deployment at `fieldpulse.businessos.af`. It complements the systemd/Nginx runbook in `ops/README.md`; use this file when the application is hosted directly inside a cPanel account rather than on a VPS with root access.

## Application path

The current production checkout is expected at:

```text
/home/businessos/fieldpulse.businessos.af
```

The repository may contain cPanel-specific untracked files such as root/public `.htaccess`, `.user.ini`, `php.ini`, and provider error logs. The deployment script allows untracked files but refuses to proceed if any **tracked** file is modified.

## Required production environment

Use the production values from `.env.production.example`, including:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fieldpulse.businessos.af
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database

FIELD_SALES_REQUIRE_SCHEDULER_HEARTBEAT=true
FIELD_SALES_SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS=180
FIELD_SALES_SCHEDULE_BACKUPS=true
```

Keep `AI_INSIGHTS_ALLOW_CUSTOMER_DATA=false` unless customer-level data has explicitly been approved for the configured external AI provider.

## cPanel cron jobs

Use the PHP CLI binary associated with the deployed PHP version. On many cPanel systems this can be `/opt/cpanel/ea-php84/root/usr/bin/php`; confirm the actual path in cPanel before saving jobs.

### Laravel scheduler — every minute

```cron
* * * * * cd /home/businessos/fieldpulse.businessos.af && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The scheduler writes the `field-sales:scheduler-heartbeat` cache key every minute. With `FIELD_SALES_REQUIRE_SCHEDULER_HEARTBEAT=true`, `/ready` fails closed when that heartbeat is stale.

The scheduler also runs appointment reminders, AI-history retention, failed-job pruning, and—when `FIELD_SALES_SCHEDULE_BACKUPS=true`—the daily FieldPulse backup.

### Queue drain — every minute

If cPanel does not provide a persistent process manager, drain the database queue once per minute:

```cron
* * * * * cd /home/businessos/fieldpulse.businessos.af && /opt/cpanel/ea-php84/root/usr/bin/php artisan queue:work database --queue=default --stop-when-empty --tries=3 --timeout=60 >> /dev/null 2>&1
```

Do not configure a worker timeout greater than the database queue `retry_after` value.

### Operational check — every five minutes

For cPanel accounts configured to email cron output, omit the redirect so failures are delivered to the account mailbox:

```cron
*/5 * * * * cd /home/businessos/fieldpulse.businessos.af && /opt/cpanel/ea-php84/root/usr/bin/php artisan field-sales:ops-check
```

## Safe deployment

From the application directory:

```bash
bash ops/scripts/deploy-cpanel.sh main
```

The script:

1. refuses deployment when tracked production files are modified;
2. saves `.env`, compiled CSS, and cPanel-specific configuration files into a timestamped deployment backup;
3. verifies production configuration;
4. creates a pre-deploy database backup;
5. fetches the requested Git ref and exits without changes when already current;
6. enables Laravel maintenance mode;
7. performs a fast-forward-only pull;
8. installs production Composer dependencies;
9. runs migrations, rebuilds Laravel caches, and runs production/service + queue health checks;
10. restarts Laravel queue workers and only then takes the application out of maintenance mode.

If anything fails after maintenance mode is enabled, the script intentionally leaves the application in maintenance mode. This prevents a partially migrated or unverified release from serving traffic. Review the failure, use the timestamped config backup and pre-deploy database snapshot as needed, then explicitly run `php artisan up` only after the release is known safe.

## Release verification

After deployment:

```bash
curl --fail https://fieldpulse.businessos.af/up
curl --fail https://fieldpulse.businessos.af/ready
php artisan field-sales:production-check --services
php artisan field-sales:ops-check --backup-tooling
git status --short --branch
```

Expected Git state: the deployed branch/commit is current with **zero tracked changes**. Provider-managed untracked files may remain.

## Backups

The scheduled FieldPulse backup depends on `mysqldump`, `tar`, and the configured writable backup directory. Verify once manually:

```bash
php artisan field-sales:backup --label=cpanel-verification
php artisan field-sales:ops-check --backup-tooling
```

A backup stored only in the same cPanel account is not sufficient disaster recovery. Replicate backup archives to an independent encrypted destination managed outside the account.
