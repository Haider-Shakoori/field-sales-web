# Field Sales SaaS — Development Roadmap

_Last updated: 2026-09-19_

## Delivery Sequence

| Batch | Name | Status | Primary Outcome |
|---|---|---|---|
| 1 | Foundation & Tenancy | Complete | Multi-tenant Laravel foundation and authentication |
| 2 | Roles, Permissions & User Management | Complete | RBAC and tenant user management |
| 3 | Sales Team & Device Management | Complete | Salesmen, supervisors, assignments and device controls |
| 4 | Customers & Territories | Complete | Customer, territory and route operations |
| 5 | Products & Price Lists | Complete | Product catalog, price lists and mobile master data |
| 6 | Mobile API Contract / UUID Bindings | Complete | Stable UUID-based mobile contracts |
| 7 | GPS Tracking Core | Complete | Work sessions, GPS history/current location and tracking settings |
| 8 | Customer Visits | Complete | Visit check-in/out, geofencing and call activities |
| 9 | Orders | Complete | Offline-first field orders and approval workflow |
| 10 | Collections | Complete | Customer collections, geofence evidence and balances |
| 11 | Expenses & Targets | Complete | Expense workflow and target management |
| 12 | Offline Sync Hardening | Complete | Idempotent retries and offline-safe synchronization |
| 13 | Admin Dashboard & Live Map | Complete | KPI dashboard, analytics, live map and field status |
| 14 | Notifications, Alerts & Reporting | Complete | Notifications, evidence-based alerts, reports and CSV exports |
| 15 | BusinessOS Integration | Deferred | Optional ERP integration adapter and synchronization |

## Batch 13 — Admin Dashboard & Live Map

### Goal

Provide managers and authorized operational users with a tenant-scoped view of current field execution.

### Delivered Scope

- Dashboard controller/service/view.
- KPI cards for salesmen, visits, approved orders, verified collections, expenses and pending approvals.
- Sales trend chart using real approved-order data.
- Visit completion chart using real visit data.
- Live map using Leaflet/OpenStreetMap.
- Salesman markers with popup details.
- 30-second auto-refresh polling.
- Online / idle / offline status indicators.
- Salesman status table.
- Role-aware dashboard variants.
- Supervisor assignment scoping.
- Permission-specific visibility for tracking, orders, visits, collections and expenses.
- Regression tests for tenancy, role access, live-map freshness, analytics and recent-activity rendering.

### Completion Gate

- Dashboard loads with real KPI data.
- Charts render from real backend data.
- Live map returns tenant-scoped current locations.
- Polling endpoint is protected by `tracking:view`.
- Supervisor sees only current assignments.
- Full CI passes.
- Changed PHP files pass Pint.

## Batch 14 — Notifications, Alerts & Reporting

### Goal

Build operational notifications, suspicious-activity alerting and exportable reporting.

### Delivered Scope

- Database notification model and delivery pipeline.
- Notification preferences.
- Provider-neutral queued push notification foundation for mobile.
- Suspicious visit/GPS evidence surfacing and manager/supervisor notification.
- Admin alerts page.
- Sales, visits, GPS and performance reports.
- Date/branch/salesman/territory filters where applicable.
- CSV export.
- PDF export deferred until a concrete formatted-report requirement justifies an additional rendering dependency.
- Commission-rule foundation deferred to a dedicated specification because it introduces compensation/accounting semantics.

### Completion Gate

- Notifications are created, user-scoped, preference-aware, and accessible through authenticated web/mobile surfaces.
- Fraud/suspicious indicators are surfaced from existing evidence, not invented heuristics.
- Report totals match source transactions.
- Filters are tenant-safe.
- CSV exports match filtered report data.
- Full CI passes: 89 tests / 465 assertions.
- Changed PHP files pass Pint.

## Stage 2 — Production Readiness & Release

Core product development is complete through Batch 14. Batch 15 is intentionally deferred and is not a release blocker. Stage 2 prepares the independent Field Sales product for production operation.

| Batch | Name | Status | Primary Outcome |
|---|---|---|---|
| 16 | Web Production Security & Runtime Readiness | Complete | Secure runtime defaults, auth throttling, readiness checks and production configuration validation |
| 17 | Deployment, Workers, Backups & Monitoring | Planned | Repeatable server deployment, queue/scheduler operation, backups, monitoring and rollback |
| 18 | Android Production Release Engineering | Planned | Signed production Android build, production API configuration and mobile release pipeline |
| 19 | Release Candidate QA & UAT | Planned | End-to-end golden paths, offline recovery, security/regression QA and UAT evidence |
| 20 | Production Launch & Handover | Planned | Release candidate promotion, launch checklist, operational handover and post-launch validation |

### Stage 2 Release Rules

- Batch 15 remains deferred until BusinessOS integration is explicitly resumed.
- No new business modules are introduced during production-readiness batches unless they fix a release blocker.
- Web and mobile production configuration must be explicit; local/debug defaults are never treated as deployment configuration.
- Deployment must include queue workers, scheduler, database migrations, backups and rollback procedures.
- Android release artifacts must be signed through secrets outside the repository; signing keys must never be committed.
- Final release status requires evidence from both repositories and does not rely only on unit/feature tests.

## Batch 16 — Web Production Security & Runtime Readiness

### Goal

Harden the Laravel web/API runtime for safe production deployment without changing field-sales business workflows.

### Scope

- Authentication rate limiting for web and mobile login.
- Global baseline security response headers.
- Public runtime readiness endpoint with fail-closed dependency checks.
- Production configuration validation command.
- Secure production environment template.
- Configurable session encryption, secure-cookie and SameSite settings.
- Focused regression coverage for the release-hardening controls.
- Content-Security-Policy is deferred until dashboard CDN assets are self-hosted so security hardening does not break current functionality.

### Completion Gate

- Web and API login bursts are throttled.
- Security headers are present on application responses.
- Readiness returns 200 only when required runtime dependencies are available.
- Production configuration checker fails closed on unsafe configuration.
- Full CI passes: 94 tests / 503 assertions.
- Changed-PHP Pint gate passes.

## Batch 15 — BusinessOS Integration (Deferred)

### Goal

Add a stable integration boundary without coupling core field-sales workflows to BusinessOS.

### Planned Scope

- Integration adapter interface.
- BusinessOS adapter.
- Inbound sync for products, customers and prices.
- Outbound sync for orders, collections and field-created customers.
- Retry-safe jobs and sync monitoring.
- Manual sync trigger.
- Error visibility and auditability.

## Engineering Gates for Every Future Batch

1. Inspect `main`, current branches, recent commits and CI before editing.
2. Work on a dedicated batch branch.
3. Preserve tenant isolation, RBAC and offline/idempotency guarantees.
4. Add focused regression tests for the batch.
5. Run the full repository CI suite, not only targeted tests.
6. Run Pint on changed PHP files.
7. Do not claim browser/E2E verification unless it was actually performed.
8. Merge only after the branch is green and the diff matches roadmap scope.
9. Update `PROJECT_STATUS.md` and this roadmap during batch closeout.
