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
| 17 | Deployment, Workers, Backups & Monitoring | Complete | Repeatable server deployment, queue/scheduler operation, backups, monitoring and rollback |
| 18 | Android Production Release Engineering | Complete | Signed production Android build, production API configuration and mobile release pipeline |
| 19 | Release Candidate QA & UAT | In Progress — Manual UAT Pending | End-to-end golden paths, offline recovery, security/regression QA and UAT evidence |
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

## Batch 17 — Deployment, Workers, Backups & Monitoring

### Goal

Provide a repeatable, recoverable production operations layer for the Laravel web/API without coupling deployment to a specific hosting vendor.

### Delivered Scope

- Release-based deployment with shared environment/storage and atomic `current`/ `previous` symlinks.
- Failure-safe deployment and code rollback scripts.
- Pre-migration database snapshots.
- MySQL database + uploaded-media backup command with compression, checksums, manifests and retention.
- Explicit destructive restore script with protected MySQL restore credentials and checksum verification.
- Nginx first-TLS bootstrap and final HTTPS/PHP-FPM configuration.
- PHP production runtime overrides.
- systemd queue workers, Laravel scheduler timer, daily backup timer and five-minute operations monitor.
- Queue monitoring for backlog, failed jobs, oldest waiting jobs and stale reserved jobs.
- Backup tooling/directory readiness monitoring.
- Seven-day failed-job pruning schedule.
- Production operations runbook.
- CI shell-syntax validation for operational scripts.
- No live-host deployment or restore-drill claim; those require the actual target infrastructure.

### Completion Gate

- Full CI passes: 102 tests / 531 assertions.
- Stage 2 Batch 17 focused regression suite passes.
- Queue worker timeout remains lower than database `retry_after`.
- HTTPS state is explicitly propagated from Nginx to Laravel.
- Deployment/rollback/restore/monitor shell scripts pass `bash -n`.
- Changed PHP files pass Pint.
- Diff contains production operations work only; no field-sales business workflow semantics are changed.

## Batch 18 — Android Production Release Engineering

### Goal

Make the Flutter Android application release-capable with deterministic dependencies, production-only HTTPS configuration, externalized signing, and a reproducible signed artifact pipeline.

### Delivered Scope

- Explicit production API/version build defines with fail-closed runtime validation.
- HTTPS-only production API requirement ending in `/api/v1`.
- Debug-only cleartext traffic override; production cleartext disabled.
- Android OS backup disabled for sensitive offline field data.
- External Android signing through ignored local properties or CI secrets.
- Git-ignored keystore/signing files and a local signing-properties example.
- Committed `pubspec.lock` and `--enforce-lockfile` in CI/release workflows.
- Normal CI builds debug APK plus signed release AAB with an ephemeral CI keystore.
- Production GitHub workflow builds signed AAB + APK using external secrets and the configured production API URL.
- Version/tag consistency gate.
- AAB signature and APK signature verification.
- SHA-256 release-artifact checksum generation.
- Android production release runbook in `RELEASE.md`.
- Regression tests for production configuration, manifest security, external signing, release workflow, and deterministic dependencies.

### Completion Gate

- Mobile PR #9 merged to `main`.
- Mobile main SHA: `3a3df3be34e8cd14174f0dd64f38c12adfe36e42`.
- Post-merge mobile CI run #367 passes.
- Flutter analyze passes with no issues.
- Flutter test passes: 25 tests.
- Debug APK builds successfully.
- Signed release AAB builds successfully.
- AAB signature verification passes.
- Real production signing secrets remain outside the repository.

### Carry-forward to Batch 19

- Approved production icon/splash branding must replace the Android platform default icon before launch.
- Configure the real upload keystore/secrets and `PRODUCTION_API_BASE_URL` only against the target production environment.
- Install the signed release candidate on a real Android device and execute offline/GPS/background-sync UAT.
- Store/publishing submission is outside Batch 18 and must not be treated as complete until Batch 20 launch.

## Batch 19 — Release Candidate QA & UAT

### Goal

Validate the merged web/API and Android release candidate across continuous business golden paths, offline persistence/retry behavior, release engineering, physical-device behavior, and production-like operational recovery before promotion.

### Automated QA Delivered

- Continuous server-side mobile-to-admin golden path across authentication/device binding, attendance, GPS, visit, order, collection, expense, approvals and End Day.
- Explicit duplicate/idempotency assertions for offline UUID retry paths.
- Release CI changed-file fallback regression coverage.
- Canonical RC acceptance matrix in `docs/RELEASE_CANDIDATE_QA.md`.
- Mobile offline workday golden path using the real local repositories and SQLite schema.
- Offline workday survives DB/application restart with attendance start/end ordering and all field records retained pending.
- Real dependency guard proves pending attendance blocks dependent GPS/visit synchronization.
- Physical Android execution script in mobile `UAT.md`.
- Mobile CI concurrency prevents obsolete branch builds consuming release-QA capacity.
- Web merged-main gate: 105 tests / 595 assertions, operations scripts and Pint PASS.
- Mobile PR gate: 26 tests, analyze PASS, debug APK PASS, signed release AAB PASS, signature verification PASS.
- Mobile merged-main gate: CI #376 PASS at `5447e409914befc87b9607293de9a8fdb7342b73`, including analyze, 26 tests, debug APK, signed release AAB and signature verification.
- Auditable manual-UAT record: mobile PR #11 merged at `18d63ac78b2843016101447d6c87c00a79d16732`; PR CI #383 PASS; `UAT_RESULTS_TEMPLATE.md` preserves UAT-01 through UAT-15 as NOT EXECUTED until real evidence exists.
- Recovered web evidence reconciliation: PR #26 merged at `852274c1b91ea018a549e40b171e46c76623ffd9`; post-merge web CI #844 and #845 PASS.
- Latest mobile post-merge gate: CI #384 PASS at `18d63ac78b2843016101447d6c87c00a79d16732`, including format, analyze, 27 tests, debug APK, ephemeral signing, signed release AAB and release-artifact verification. Batch 19 still remains manual-UAT pending.

### Manual UAT Still Required

Batch 19 remains **In Progress — Automated QA Complete / Manual UAT Pending** until actual evidence exists for:

- approved app icon/splash branding
- real signed RC APK against production-like HTTPS API
- clean physical-device install/login/device binding
- permission/privacy flows
- online Start Day/GPS
- full airplane-mode field workflow
- process restart while offline
- reconnect and idempotent synchronization
- admin approval/verification round-trip
- background GPS / screen-lock / OEM battery behavior
- End Day tracking stop
- device revocation
- notifications/preferences
- reports/CSV reconciliation
- operational health observation
- non-production backup/restore drill

### Completion Gate

Batch 19 becomes Complete only after:
- web and mobile automated gates are green on merged `main`
- UAT-01 through UAT-14 are executed on a physical Android device with no unresolved release-blocking defects
- UAT-15 restore drill passes on non-production infrastructure
- approved production branding is present
- any failed scenario is fixed/retested or explicitly accepted as non-release-blocking with documented rationale

Stage 2 Batch 20 must not begin as a production-promotion step before these gates are satisfied.

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
