# FieldPulse Production Deployment Profile

This file records the canonical Stage 2 production target for FieldPulse by BusinessOS.

## Canonical endpoints

- Web/admin/backend: `https://fieldpulse.businessos.af`
- Mobile API base URL: `https://fieldpulse.businessos.af/api/v1`
- Liveness: `https://fieldpulse.businessos.af/up`
- Readiness: `https://fieldpulse.businessos.af/ready`

The Android repository must use this exact API base URL for `PRODUCTION_API_BASE_URL`.

## DNS requirement

Create a DNS record for `fieldpulse.businessos.af` pointing to the production host. Use an A record for an IPv4 host and, when the host has IPv6 configured, an AAAA record as well. Do not enable a proxy/CDN mode until the origin works correctly over HTTPS and Laravel sees secure requests correctly.

## Server-side values

The shared production environment at `/var/www/field-sales/shared/.env` must be based on `.env.production.example` and retain:

```dotenv
APP_NAME="FieldPulse"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fieldpulse.businessos.af
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

Generate `APP_KEY` on the server and provide production MySQL credentials outside Git.

## Nginx and TLS

For the first deployment:

1. Copy `ops/nginx/field-sales-bootstrap.conf`.
2. Replace every `FIELD_SALES_DOMAIN` with `fieldpulse.businessos.af`.
3. Enable the site and validate with `nginx -t`.
4. Point DNS at the host.
5. Obtain a TLS certificate for `fieldpulse.businessos.af`.
6. Switch to `ops/nginx/field-sales.conf`, again replacing `FIELD_SALES_DOMAIN`.
7. Reload Nginx only after `nginx -t` passes.

The PHP-FPM socket in the template is `/run/php/php8.5-fpm.sock`; change it only when the target distribution uses another path.

## Application deployment

From an operator checkout on the server:

```bash
export FIELD_SALES_REPO_URL=git@github.com:Haider-Shakoori/field-sales-web.git
bash ops/scripts/deploy.sh main
```

The deployment script must not be bypassed: it performs the production configuration check, pre-migration backup, maintenance transition, migration, service readiness check, atomic release switch and queue restart.

## Required services

Enable the two initial queue workers, scheduler, backup and monitoring units documented in `ops/README.md`. Configure:

```dotenv
FIELD_SALES_HEALTH_URL=https://fieldpulse.businessos.af/ready
```

## Release verification

After deployment, all of these must succeed before a real Android RC is built:

```bash
curl --fail https://fieldpulse.businessos.af/up
curl --fail https://fieldpulse.businessos.af/ready
php /var/www/field-sales/current/artisan field-sales:production-check --services
php /var/www/field-sales/current/artisan field-sales:ops-check --backup-tooling
systemctl --no-pager --full status field-sales-queue@1.service
systemctl --no-pager --full status field-sales-scheduler.timer
systemctl --no-pager --full status field-sales-backup.timer
systemctl --no-pager --full status field-sales-monitor.timer
```

## Android release inputs

In `Haider-Shakoori/field-sales-mobile`, configure the GitHub Actions repository variable:

```text
PRODUCTION_API_BASE_URL=https://fieldpulse.businessos.af/api/v1
```

and the external signing secrets:

```text
ANDROID_KEYSTORE_BASE64
ANDROID_KEYSTORE_PASSWORD
ANDROID_KEY_ALIAS
ANDROID_KEY_PASSWORD
```

These values must never be committed.

## Batch 19 boundary

Repository configuration for the canonical domain is complete when this profile is merged. Batch 19 is not complete until the hostname is actually routed to a production-like host, HTTPS/readiness and workers are healthy, a real externally signed RC APK is produced, UAT-01 through UAT-14 are executed on a physical Android device, and UAT-15 passes on non-production infrastructure.
