# Field Sales Web

Laravel 13 web/backend for the Field Sales SaaS. It provides multi-tenant admin policy management plus the versioned REST contract consumed by `field-sales-mobile`.

## Highlights

- Tenant-scoped users, salesmen, devices and company policy
- Sanctum mobile authentication with one-active-device enforcement
- Offline-safe attendance idempotency using `offline_uuid`
- Client-reported `started_at` / `ended_at` support for accurate offline work sessions
- Per-point GPS ingestion verdicts, duplicate handling and out-of-order protection
- Active overnight sessions may continue to upload points after local midnight
- Privacy acknowledgement and stable `DEVICE_REVOKED` error code
- Admin login + tracking policy UI
- API errors always use the documented envelope

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# create a MySQL database called field_sales and update .env if needed
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8001
```

Demo accounts after seeding:

- Admin: `admin@example.com` / `password`
- Salesman: `salesman@example.com` / `password`

Admin UI: `http://127.0.0.1:8001/admin/tracking-settings`

API base: `http://127.0.0.1:8001/api/v1`

## Verification

```bash
php artisan test
vendor/bin/pint --test
git diff --check
```
