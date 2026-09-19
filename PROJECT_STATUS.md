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
- Batch 17 provides deployment/operations artifacts and CI validation, but no live production-host deployment or disaster-restore drill is claimed yet.
- Off-site backup replication remains infrastructure-provider specific; the application creates local verified backup sets that must be copied to an independent encrypted target before launch.
- Approved FieldPulse production launcher and splash branding is now present in the mobile repository and the platform default icon has been replaced. Physical-device launcher-mask/splash verification remains part of Batch 19 UAT.
- A real production keystore, GitHub release secrets, production API repository variable, store submission, and real-device release installation are not falsely claimed by Batch 18.

## Stage 2 — Production Readiness & Release

Batch 15 — BusinessOS Integration is deferred and is not a release blocker.

### Stage 2 Status

| Batch | Name | Status |
|---|---|---|
| 16 | Web Production Security & Runtime Readiness | Complete |
| 17 | Deployment, Workers, Backups & Monitoring | Complete |
| 18 | Android Production Release Engineering | Complete |
| 19 | Release Candidate QA & UAT | In Progress — Manual UAT Pending |
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

### Batch 17 Verification

Verified scope:
- Release-based Linux deployment layout with immutable releases plus shared environment/storage.
- Failure-safe atomic deployment and code rollback scripts.
- Pre-deploy database snapshots before migrations.
- MySQL database backups with compressed dumps, SHA-256 manifest verification, retention, and optional uploaded-media archives.
- Destructive restore workflow requires explicit confirmation, protected restore credentials, checksum verification, maintenance mode, post-restore migrations, and readiness checks.
- Nginx HTTP bootstrap and HTTPS production templates, including ACME handling and explicit HTTPS propagation to PHP-FPM.
- PHP 8.5 production runtime override template.
- systemd queue worker template with a 60-second worker timeout below the explicit 90-second database retry window.
- systemd Laravel scheduler, daily backup, and five-minute monitoring timers.
- Operational monitoring covers runtime readiness, queue backlog, failed jobs, stale reserved jobs, backup directory/tooling, and optional public HTTPS readiness.
- Scheduled failed-job pruning after seven days.
- CI validates all operations shell scripts with `bash -n`.
- Full production operations runbook at `ops/README.md`.

Latest verification gate:
- GitHub Actions `web-ci`: PASS.
- Laravel tests: 102 passed, 531 assertions.
- Stage 2 Batch 17 focused operations regression suite: PASS.
- Operations shell syntax gate: PASS.
- Changed-PHP Pint gate: PASS.

A real server installation, TLS issuance, external/off-site backup replication, and destructive restore drill still require the target production/staging infrastructure and are not falsely claimed by this batch.

### Batch 18 Verification

Verified scope:
- Android release configuration fails closed unless a production API base URL and application version are supplied.
- Release API configuration requires HTTPS and the versioned `/api/v1` endpoint.
- Production Android cleartext traffic is disabled; a debug-only manifest override preserves emulator/LAN development.
- Android OS application backup is disabled so offline customer/transaction/location data is not silently copied into device cloud backup.
- Release signing is externalized through ignored local signing properties or GitHub Actions secrets; no keystore/password is committed.
- `pubspec.lock` is committed and both CI/release workflows enforce it for reproducible dependency resolution.
- Normal mobile CI builds the debug APK and a signed release AAB using a one-day ephemeral CI key.
- Production release workflow builds signed AAB + APK from external secrets, enforces version/tag consistency, verifies signatures, and emits SHA-256 checksums.
- Android production release runbook is documented in `RELEASE.md`.
- Mobile PR #9 merged into `main` at `3a3df3be34e8cd14174f0dd64f38c12adfe36e42`.
- Post-merge mobile CI run #367 passed.
- Flutter analyze: PASS.
- Flutter tests: 25 passed.
- Debug APK build: PASS.
- Signed release AAB build: PASS.
- AAB signature verification: PASS.

Batch 19 branding carry-forward status:
- Resolved in mobile PR #12: approved FieldPulse launcher and splash branding replaced the Android platform default icon and added branding regression coverage. Physical-device visual verification remains required before launch.

### Batch 19 Release-Candidate QA

Automated release-candidate evidence completed so far:
- Web PR #24 merged to `main` at `157ec7b32e9f231302fce47f59bd6eb6ab126d71`.
- Web post-merge CI run #833 passed.
- Laravel suite: 105 tests passed / 595 assertions.
- Continuous web/API golden path crosses login/device binding, attendance, GPS, visit, server-priced order, admin order approval, collection verification/balance reduction, expense approval, attendance end, and retry/idempotency cardinality checks.
- Web release-candidate acceptance matrix is documented in `docs/RELEASE_CANDIDATE_QA.md`.
- Mobile PR #10 merged to `main` at `5447e409914befc87b9607293de9a8fdb7342b73`.
- Mobile PR gate passed with Flutter analyze clean, 26 tests, debug APK, signed release AAB, and AAB signature verification.
- Mobile post-merge `main` CI run #376 passed at `5447e409914befc87b9607293de9a8fdb7342b73` with Flutter analyze clean, 26 tests, debug APK, signed release AAB, AAB signature verification, and SHA-256 output.
- Mobile PR #11 added the auditable `UAT_RESULTS_TEMPLATE.md` and merged to `main` at `18d63ac78b2843016101447d6c87c00a79d16732`; its PR CI run #383 passed.
- Mobile post-merge `main` CI run #384 passed at `18d63ac78b2843016101447d6c87c00a79d16732`: Dart format PASS, Flutter analyze PASS, 27 tests PASS, debug APK PASS, ephemeral release signing PASS, signed release AAB PASS, and release-artifact verification PASS.
- Web PR #26 reconciled merged-main RC evidence and merged at `852274c1b91ea018a549e40b171e46c76623ffd9`; PR #27 then added the isolated backup/restore recovery rehearsal and advanced web `main` to `468e5ed924f08078112eded72c4ea833539f201b`. PR #27 web-ci #847 and recovery-rehearsal #1 passed; post-merge web-ci #848/#849 and recovery-rehearsal #2 passed. The CI rehearsal does not complete UAT-15.
- Mobile offline golden path proves attendance/GPS/visit/order/collection/expense/End Day records survive SQLite close/reopen and remain retry-safe/pending in dependency-safe state.
- Physical-device execution script is documented in mobile `UAT.md`.
- CI concurrency now cancels obsolete mobile branch builds.
- Mobile PR #12 merged approved FieldPulse production branding to `main` at `ec30a1d44d234d04a4994795780428d3fe4d4c41`; the Android app label, launcher icon, native splash, Flutter startup branding, and branding regression coverage now use FieldPulse.

Batch 19 is **not complete** yet. Repository-side production branding is resolved; the remaining gates require real execution evidence rather than simulation:
- signed RC APK using the real external upload/release key
- production-like/staging HTTPS deployment with healthy workers/readiness
- physical Android install and UAT-01 through UAT-14
- background GPS and Android permission/OEM behavior on a real device
- reconnect/idempotent sync and admin round-trip
- non-production backup/restore drill (UAT-15)
- off-site backup/monitoring validation as applicable

Manual UAT results must remain PASS / FAIL / BLOCKED with evidence. CI success must not be substituted for physical-device or infrastructure acceptance.

## Next Gate

Complete the documented Batch 19 manual UAT and resolve all release-blocking defects before Stage 2 Batch 20 — Production Launch & Handover.
