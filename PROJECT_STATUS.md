# Field Sales — Project Status

_Last updated: 2026-09-19_

## Repository State

- Web/admin/API repository: `Haider-Shakoori/field-sales-web`
- Mobile repository: `Haider-Shakoori/field-sales-mobile`
- Default branch: `main`
- Production workflow: feature batch branch → focused regression coverage → full CI → PR → merge.
- GitHub is the source of truth. Chat history is not a substitute for repository state.

## Current Delivery Status

Batches 1–14 are complete. Batch 14 — Notifications, Alerts & Reporting — adds tenant-scoped notification delivery, evidence-based operational alerts, and exportable operational reports without weakening the existing RBAC, tenancy, or offline-first guarantees.

### Batch Status

| Batch | Name | Status |
|---|---|---|
| 1 | Foundation & Tenancy | Complete |
| 2 | Roles, Permissions & User Management | Complete |
| 3 | Sales Team & Device Management | Complete |
| 4 | Customers, Territories & Routes | Complete |
| 5 | Products, Price Lists & Mobile Master Data | Complete |
| 6 | Mobile API UUID bindings / compatibility | Complete |
| 7 | GPS Tracking Core | Complete |
| 8 | Customer Visits & Call Activities | Complete |
| 9 | Orders | Complete |
| 10 | Collections | Complete |
| 11 | Expenses & Targets | Complete |
| 12 | Offline Sync / Idempotency Hardening | Complete |
| 13 | Admin Dashboard & Live Map | Complete |
| 14 | Notifications, Alerts & Reporting | Complete |

## Batch 13 Verification

Verified scope:
- Tenant-scoped KPI dashboard.
- 7-day real-data sales trend chart, separated by currency.
- 7-day visit started/completed chart.
- Live salesman map using Leaflet/OpenStreetMap.
- 30-second no-store polling.
- Online / idle / offline classification.
- Salesman status table with latest update, coordinates and battery.
- Role-aware dashboard titles and permission-aware sections.
- Supervisor data restricted to current salesman assignments.
- Recent activity rendering regression fixed and covered.
- Live location endpoint remains tenant-scoped and tracking-permission protected.

Latest verification gate:
- GitHub Actions `web-ci`: PASS.
- Laravel tests: 84 passed, 421 assertions.
- Changed-PHP Pint gate: PASS.

## Batch 14 Verification

Verified scope:
- Tenant-owned operational notifications with per-user in-app and push preferences.
- Order, collection, and expense review decisions notify the responsible salesman.
- Suspicious visit evidence automatically notifies company managers and the currently assigned supervisor.
- Provider-neutral queued push outbox with explicit disabled/missing-provider handling and environment configuration.
- Authenticated web notification inbox plus device-bound mobile notification/preference APIs.
- Alerts page surfaces existing suspicious-visit flags and mock-location GPS evidence only; no synthetic fraud score is generated.
- Suspicious visit review is audited.
- Sales, visits, GPS, and performance reports use authoritative source records.
- Date, salesman, branch, and territory filters are tenant-safe and supervisor-scoped.
- Sales and visit branch/territory filters use transaction/customer classification; GPS/performance assignment filters use the assignment effective on the report end date.
- Monetary report values remain separated by currency.
- CSV export is generated from the same filtered report dataset shown in the admin view.

Latest verification gate:
- GitHub Actions `web-ci`: PASS.
- Laravel tests: 89 passed, 465 assertions.
- Batch 14 focused regression suite: PASS.
- Changed-PHP Pint gate: PASS.

Batch 14 intentionally does not add a PDF library or commission engine. CSV is the required verified operational export for this release; PDF can be introduced when a concrete formatted-report requirement exists. Commission rules are deferred because introducing compensation/accounting semantics without a dedicated specification would destabilize otherwise authoritative reporting.

## Architecture Decisions in Force

- Laravel 13 / PHP 8.5 / MySQL 8.
- Admin UI: Blade + Tailwind CSS + Alpine-compatible server-rendered views.
- Mobile: separate Flutter Android repository.
- REST API under the existing versioned API structure with Sanctum.
- Shared-database multi-tenancy with strict tenant scoping.
- Offline-first mobile writes use UUID/idempotency protections.
- Operational timestamps are stored in UTC and presented using tenant timezone.
- GPS history and current-location state are separate; dashboard live map uses current-location state only.
- Polling is the current live-map transport. WebSockets/Reverb are deferred.
- Money analytics never combine different currencies into one total series.

## Known Limitations / Deferred Work

- No browser automation or screenshot-based visual QA evidence has been claimed for Batch 13.
- Map and chart assets currently load from pinned public CDNs; asset bundling/self-hosting can be addressed in a later frontend hardening pass.
- Historical route playback and territory overlays remain deferred.
- Push delivery requires a configured provider endpoint/token; the server-side outbox and retry-safe queue foundation are present.
- No browser automation or screenshot-based visual QA evidence is claimed for Batch 14.

## Stage 2 — Production Readiness & Release

Batch 15 — BusinessOS Integration is deferred and is not a release blocker.

### Stage 2 Status

| Batch | Name | Status |
|---|---|---|
| 16 | Web Production Security & Runtime Readiness | Complete |
| 17 | Deployment, Workers, Backups & Monitoring | Next |
| 18 | Android Production Release Engineering | Planned |
| 19 | Release Candidate QA & UAT | Planned |
| 20 | Production Launch & Handover | Planned |

### Batch 16 Verification

Verified scope:
- Web and mobile login endpoints are protected by named rate limiters.
- Baseline security headers are applied globally.
- HSTS is emitted only for secure production requests.
- Public `/ready` checks database connectivity, infrastructure tables, and writable runtime directories without exposing internal failure details.
- `field-sales:production-check` fails closed when deployment configuration is unsafe and can include service checks.
- `.env.production.example` provides explicit production-safe defaults.
- Session encryption, secure-cookie, and SameSite settings are environment-configurable.
- Production preflight guidance is documented in the repository README.
- Strict Content-Security-Policy remains deferred until Leaflet/Chart.js assets are self-hosted.

Latest verification gate:
- GitHub Actions `web-ci`: PASS.
- Laravel tests: 94 passed, 503 assertions.
- Stage 2 Batch 16 focused regression suite: PASS.
- Changed-PHP Pint gate: PASS.

## Next Batch

Stage 2 Batch 17 — Deployment, Workers, Backups & Monitoring.
