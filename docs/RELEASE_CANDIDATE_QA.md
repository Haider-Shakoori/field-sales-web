# Stage 2 Batch 19 — Release Candidate QA & UAT

This document is the canonical release-candidate acceptance record for the independent Field Sales product.

Batch 19 intentionally separates **automated evidence** from **manual UAT evidence**. A test is not marked passed unless it was actually executed.

**Current phase:** automated release-candidate QA is complete on merged `main`; manual physical-device/infrastructure UAT remains pending and is not pre-approved.

## Release-candidate baseline

- Web/admin/API baseline: `field-sales-web/main`
- Mobile baseline: `field-sales-mobile/main`
- Batch 15 BusinessOS integration remains deferred and is not part of this release candidate.
- Production publication remains Batch 20.

## Automated release-candidate gates

### Web/API golden path

`tests/Feature/Stage2Batch19ReleaseCandidateQaTest.php` verifies one continuous production-shaped workflow:

1. Mobile user login and device registration.
2. Device-bound `/auth/me`.
3. Offline UUID attendance start and retry.
4. GPS ingestion during the active work session.
5. Customer visit check-in and idempotent retry.
6. Visit completion.
7. Offline order submission and idempotent retry.
8. Server-authoritative pricing.
9. Admin order approval.
10. Collection against the approved credit receivable.
11. Idempotent collection retry.
12. Admin collection verification.
13. Authoritative outstanding-balance reduction.
14. Offline expense submission and retry.
15. Admin expense approval.
16. Attendance end and idempotent end retry.
17. Database cardinality checks confirm retries did not duplicate business records.

The test deliberately crosses both mobile API and admin-web approval surfaces instead of testing each batch in isolation.

### Mobile offline/restart golden path

`test/release_candidate_offline_flow_test.dart` verifies a field day can be created without network access and survives application/database restart:

1. Cached customer/product/price data is available.
2. Start Day is persisted locally with an attendance sync-queue record.
3. A GPS point is persisted locally as pending.
4. Customer visit check-in and checkout are stored locally.
5. Offline order is server-price-compatible and stored pending.
6. Offline collection stores the cached receivable balance.
7. Offline expense is stored pending.
8. End Day is persisted with a second attendance queue record.
9. The local database is closed and reopened.
10. All business records remain present and pending.
11. Attendance start/end queue order remains priority 10 then 20.
12. Pending attendance is still detected, so the sync coordinator will defer dependent GPS/visit upload until attendance is safe to synchronize.

### Release engineering gates

The normal CI pipelines must remain green:

- Web: full Laravel suite, operations shell validation, changed-PHP Pint gate.
- Mobile: lockfile enforcement, Dart format, Flutter analyze, Flutter tests, debug APK, ephemeral release keystore, signed release AAB, AAB signature verification.

Merged-main evidence:
- Web `main`: `157ec7b32e9f231302fce47f59bd6eb6ab126d71`, CI #833 PASS, 105 tests / 595 assertions.
- Mobile `main`: `18d63ac78b2843016101447d6c87c00a79d16732`, CI #384 PASS, Flutter analyze clean, 27 tests, debug APK built, signed release AAB built and signature-verified.
- Web recovery baseline: `468e5ed924f08078112eded72c4ea833539f201b`, normal CI #848 PASS and recovery-rehearsal #2 PASS.

### Automated recovery rehearsal

`.github/workflows/recovery-rehearsal.yml` exercises the existing production backup/restore tooling against an isolated MySQL 8 service:

1. Builds a production-shaped Laravel/shared-storage layout.
2. Runs migrations and production readiness checks.
3. Creates a database probe and uploaded-media probe.
4. Runs the real `field-sales:backup` command.
5. Verifies database/media files and SHA-256 manifest entries.
6. Deliberately mutates both database and uploaded media.
7. Runs the real destructive `ops/scripts/restore-backup.sh`.
8. Confirms both probes return to the original values.
9. Runs production readiness and `field-sales:ops-check --backup-tooling`.

Merged-main recovery rehearsal #2 passed. This is automated recovery evidence only; it does **not** mark UAT-15 PASS because UAT-15 must still be executed on approved non-production/staging infrastructure.

## Manual UAT prerequisites

Manual UAT must not begin until all of these are available:

- A staging or production-like HTTPS deployment using the Batch 17 deployment model.
- `/up`, `/ready`, queue workers, scheduler and operations monitor healthy.
- A dedicated UAT tenant with non-production test data.
- A real Android upload/release signing key configured outside Git.
- `PRODUCTION_API_BASE_URL` pointing to the staging/UAT HTTPS API.
- At least one physical Android device.
- Approved Field Sales application icon and splash/branding assets.

## Manual UAT scenarios

| ID | Scenario | Expected result | Status |
|---|---|---|---|
| UAT-01 | Install signed RC APK on a clean physical Android device | App installs, launches, shows approved branding, no debug endpoint/configuration is visible | Not Executed |
| UAT-02 | Login and device registration | Correct tenant/user/salesman loads; one active device is enforced | Not Executed |
| UAT-03 | Location/privacy/notification permissions | Disclosure is understandable; required permissions can be granted without crashes | Not Executed |
| UAT-04 | Start Day online | Attendance becomes active and GPS foreground-service behavior begins as designed | Not Executed |
| UAT-05 | Enter airplane mode and perform field work | Visit, order, collection, expense, notes/photo metadata and GPS remain usable/persisted locally | Not Executed |
| UAT-06 | Force-close/relaunch while still offline | Pending records remain visible and are not duplicated or lost | Not Executed |
| UAT-07 | Restore connectivity and run sync | Dependencies synchronize in safe order; retries do not duplicate server records | Not Executed |
| UAT-08 | Admin reviews synced order/collection/expense | Approval/verification changes are correct, audited and visible back on mobile after refresh | Not Executed |
| UAT-09 | Background GPS during active session | Location updates continue according to Android permission/foreground-service rules and appear on admin live map | Not Executed |
| UAT-10 | End Day then attempt later background tracking | Work session completes and tracking stops according to product policy | Not Executed |
| UAT-11 | Revoke the device from admin | Existing device token loses operational access with the documented machine-readable error | Not Executed |
| UAT-12 | Notifications/preferences | In-app notifications and preference changes are correct; external push only if a provider is actually configured | Not Executed |
| UAT-13 | Reports/CSV | Sales, visits, GPS/performance filters match source records and money remains separated by currency | Not Executed |
| UAT-14 | Queue/monitoring observation | No abnormal failed-job growth or stale queue reservations during the UAT run | Not Executed |
| UAT-15 | Backup/restore drill on non-production environment | Backup checksum verifies and a restore recreates DB + uploaded visit media successfully | Not Executed |

The mobile repository contains the device-oriented execution script in `UAT.md` and the auditable result record in `UAT_RESULTS_TEMPLATE.md`. The template defaults every scenario to `NOT EXECUTED`; it must be copied and completed for the actual release candidate.

## Evidence to capture during manual UAT

For every manual scenario record:

- date/time in UTC
- web commit SHA
- mobile commit SHA
- app version + Android version/device model
- test tenant
- scenario result: PASS / FAIL / BLOCKED
- screenshot or screen recording where it materially proves UX behavior
- relevant server log/queue/monitor evidence for failures
- defect link/identifier if failed

Do not include passwords, tokens, private signing data or customer production data in UAT evidence.

## Current known blockers

### Launch blockers

1. **Approved mobile branding is missing.** The current Android manifest still references the platform default application icon. Batch 20 promotion must not ship that default icon.
2. **Physical-device UAT is not yet executed.** CI cannot prove Android permission prompts, background-service behavior, OEM battery-management effects, camera/photo capture UX or actual restart/reconnect behavior on a phone.
3. **Production-like deployment validation is not yet executed.** The automated MySQL/database + uploaded-media recovery rehearsal passes, but public TLS, external monitoring, off-site backup replication and the UAT-15 restore drill still require the target infrastructure.
4. **Real production signing/release secrets are intentionally not present in Git.** They must be configured in the release environment before a distributable RC can be generated.

### Conditional blocker

- External push delivery remains conditional on a configured push provider. In-app notification behavior can be accepted independently; push must not be marked passed unless a real provider is configured and exercised.

## Batch 19 acceptance rule

Batch 19 can be marked **Complete** only when:

- both repository automated RC gates are green on merged `main`
- a signed release-candidate build has been installed on a physical Android device
- UAT-01 through UAT-14 are executed with no unresolved release-blocking defects
- UAT-15 restore drill is completed on non-production infrastructure
- approved application icon/splash branding is present
- any failed scenario is either fixed and rerun or explicitly accepted as a non-release-blocking limitation with documented rationale

Until then, Batch 19 status is **In Progress — Automated QA / Manual UAT Pending**.
