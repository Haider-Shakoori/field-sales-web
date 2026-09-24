# FieldPulse Web

Laravel 13 web/backend for FieldPulse by BusinessOS. It provides multi-tenant admin policy management plus the versioned REST contract consumed by `field-sales-mobile`.

## Highlights

- SaaS multi-tenant admin console with per-organization isolation
- Platform organizations console: provision, edit, suspend/activate companies with default roles, tracking policy and first administrator
- Organization settings page for company admins (profile and timezone)
- Dedicated live map with all salesmen, status filters, search and today's routes
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
npm install
cp .env.example .env
php artisan key:generate
# create a MySQL database called field_sales and update .env if needed
php artisan migrate --seed
npm run build:css
php artisan serve --host=0.0.0.0 --port=8001
```

The admin UI uses a locally built Tailwind stylesheet (`public/css/app.css`). Run `npm run build:css` after changing views, or `npm run watch:css` while developing. Leaflet and Chart.js are served from `public/vendor` instead of public CDNs.

Demo accounts after seeding:

- Admin: `admin@example.com` / `password` (also a platform administrator, so `/admin/organizations` is available)
- Salesman: `salesman@example.com` / `password`

Grant or revoke platform administrator access for any account:

```bash
php artisan field-sales:make-platform-admin admin@example.com
php artisan field-sales:make-platform-admin admin@example.com --revoke
```

Admin UI: `http://127.0.0.1:8001/admin/tracking-settings`

API base: `http://127.0.0.1:8001/api/v1`

## Verification

```bash
php artisan test
vendor/bin/pint --test
git diff --check
```


## Production preflight

Production deployments must start from `.env.production.example`, not from local development defaults. The canonical production target is `https://fieldpulse.businessos.af`, with the mobile API at `https://fieldpulse.businessos.af/api/v1`. Generate a unique application key, use HTTPS, keep `APP_DEBUG=false`, enable secure/encrypted sessions, and configure an asynchronous queue worker.

Before accepting traffic, run:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan field-sales:production-check --services
```

Runtime probes:

- `/up` — process/liveness probe supplied by Laravel.
- `/ready` — FieldPulse readiness probe; returns HTTP 200 only when required runtime dependencies are available.

Stage 2 Batch 17 provides the repeatable server deployment, worker/scheduler, backup, monitoring, restore, and rollback package. See `ops/README.md` for the production operations runbook.


## Release candidate QA

Stage 2 Batch 19 automated acceptance coverage and the manual UAT evidence matrix are documented in [docs/RELEASE_CANDIDATE_QA.md](docs/RELEASE_CANDIDATE_QA.md). Manual scenarios remain unpassed until they are executed on the target staging infrastructure and a physical Android device.


## Ask FieldPulse — LLM integration

Ask FieldPulse supports two modes:

1. **Grounded local mode** — works without any external AI key and answers a limited set of common operational questions.
2. **Tool-calling AI mode** — uses an OpenAI-compatible LLM (Groq, OpenRouter, or another compatible endpoint) to choose safe read-only FieldPulse tools and answer broader natural-language business questions.

### Groq configuration

Recommended production starting point:

```env
AI_INSIGHTS_ENABLED=true
AI_INSIGHTS_PROVIDER=groq
AI_INSIGHTS_API_KEY=your-groq-api-key
AI_INSIGHTS_MODEL=openai/gpt-oss-120b
AI_INSIGHTS_TIMEOUT=20
AI_INSIGHTS_MAX_TOOL_ROUNDS=4
AI_INSIGHTS_ALLOW_CUSTOMER_DATA=false
```

No base URL is required for `AI_INSIGHTS_PROVIDER=groq`; FieldPulse uses `https://api.groq.com/openai/v1`.

To test a multilingual Groq model, change only:

```env
AI_INSIGHTS_MODEL=qwen/qwen3.8-27b
```

### Data access model

The LLM never receives SQL/database credentials and cannot issue arbitrary queries. It can only request FieldPulse-defined read-only tools.

Available tool categories are restricted by the signed-in user's existing permissions. Aggregate reporting is available through report permissions. Customer-specific tools require both:

```env
AI_INSIGHTS_ALLOW_CUSTOMER_DATA=true
```

and the signed-in user to have `customers:view`.

Keep `AI_INSIGHTS_ALLOW_CUSTOMER_DATA=false` until the external provider's data controls are configured to your organization's requirements. The assistant never gets write/create/update/delete tools.

### Generic OpenAI-compatible provider

```env
AI_INSIGHTS_ENABLED=true
AI_INSIGHTS_PROVIDER=openai_compatible
AI_INSIGHTS_BASE_URL=https://provider.example/v1
AI_INSIGHTS_API_KEY=your-api-key
AI_INSIGHTS_MODEL=provider-model-id
```

OpenRouter can be selected with `AI_INSIGHTS_PROVIDER=openrouter`; its base URL is supplied automatically.

After changing production environment settings:

```bash
php artisan optimize:clear
php artisan config:cache
```
