# Field Sales — Project Status

_Last updated: 2026-09-19_

## Repository State

- Web/admin/API repository: `Haider-Shakoori/field-sales-web`
- Mobile repository: `Haider-Shakoori/field-sales-mobile`
- Default branch: `main`
- Production workflow: feature batch branch → focused regression coverage → full CI → PR → merge.
- GitHub is the source of truth. Chat history is not a substitute for repository state.

## Current Delivery Status

Batches 1–12 are complete and merged. Batch 13 — Admin Dashboard & Live Map — was initially merged in PR #18, then repository review found missing roadmap scope and an untested activity timestamp rendering defect. The Batch 13 hardening branch completes the missing work and is CI-green.

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
| 13 | Admin Dashboard & Live Map | Complete on hardening branch; merge pending |

## Batch 13 Verification

Hardening branch: `batch13-admin-dashboard-live-map-hardening`

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
- Historical route playback, territory overlays, fraud alerting and full reports belong to later roadmap work.

## Next Batch

Batch 14 — Notifications, Alerts & Reporting.
