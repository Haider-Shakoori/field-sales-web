# FieldPulse Final UAT

This is the final application-level acceptance checklist to run **after** the latest web \`main\` is deployed to \`fieldpulse.businessos.af\` and the latest mobile \`main\` is installed on a physical Android device.

Do not run UAT against production business data unless a dedicated test tenant exists. Prefer a clearly named UAT tenant and test customers/products. Clean up test data where the workflow permits it.

## Preconditions

- Web deployment is on the intended GitHub \`main\` commit with zero tracked server drift.
- \`https://fieldpulse.businessos.af/up\` returns healthy.
- \`https://fieldpulse.businessos.af/ready\` returns ready.
- \`php artisan field-sales:production-check --services\` passes.
- \`php artisan field-sales:ops-check --backup-tooling\` passes.
- cPanel scheduler and queue cron jobs are active.
- Android app uses \`https://fieldpulse.businessos.af/api/v1\` as its production API base URL.
- A UAT tenant has an admin/supervisor, salesman, customers with coordinates, products, price lists, route/territory assignments and opening stock where needed.
- Keep \`AI_INSIGHTS_ALLOW_CUSTOMER_DATA=false\` during the default UAT unless customer-data AI transmission is explicitly approved for the test tenant.

Record for each case: **PASS / FAIL**, tester, timestamp, device/app build, evidence, and issue reference when failed.

## UAT-01 — Authentication and device identity

1. Sign in on the Android app with a salesman account.
2. Confirm device registration succeeds and the same device remains recognized after app restart.
3. Confirm revoked/invalid credentials return the user to sign-in.
4. Verify admin login works on the web.
5. Verify tenant isolation by confirming the UAT user cannot see another tenant's records.

**Pass:** authentication/device identity is stable and tenant data is isolated.

## UAT-02 — Start Day / attendance / shift opening

1. Start the work day from mobile.
2. Enter vehicle/plate and starting odometer when required.
3. Confirm GPS permission flow is not duplicated.
4. Confirm the active shift appears in web attendance.
5. End the day with ending odometer.
6. Verify ending odometer cannot be below starting odometer and total hours/mileage are correct.

**Pass:** attendance and odometer lifecycle is consistent on mobile and web.

## UAT-03 — GPS tracking and privacy

1. Accept the tracking/privacy acknowledgement.
2. Start the work day and move with the physical device.
3. Confirm current location appears on the web live map.
4. Confirm poor/mock/implausible GPS samples are rejected or flagged according to policy.
5. End the work day and confirm background tracking stops.

**Pass:** location tracking is accurate, bounded by work state and privacy acknowledgement, and visible to authorized web users.

## UAT-04 — Offline master data and sync recovery

1. Sync customers, routes, territories, products and price data while online.
2. Disable connectivity.
3. Confirm cached master data remains usable.
4. Create at least one offline-safe record.
5. Restore connectivity and run sync.
6. Confirm no duplicate records are created and the sync issue screen is clear.

**Pass:** offline cache and retry/idempotency behavior work without data loss or duplication.

## UAT-05 — Customer visit, forms, photo and voice note

1. Check in to a planned customer inside its geofence.
2. Confirm planned/unplanned state and geofence result.
3. Complete any required configurable visit forms.
4. Capture a visit photo.
5. Record a voice note and stop it normally.
6. Temporarily disable connectivity before sync and confirm the photo/voice note stay queued locally.
7. Restore connectivity and sync.
8. Check out with an outcome and written notes.
9. Open the visit on web and verify form answers, photo, protected voice playback and transcription status.
10. With the default customer-data AI policy disabled, confirm the voice note is stored but external transcription is marked policy-blocked rather than transmitted.

**Pass:** the complete visit evidence flow is offline-first, idempotent, private and reviewable.

## UAT-06 — Smart Route 2.0 / nearby opportunities

1. Open the daily route on mobile.
2. Confirm assigned/planned customers are prioritized.
3. Confirm nearby opportunities are shown using current/cached context.
4. Verify a suggested nearby customer can be opened and visited.
5. Compare the same day's route context on web.

**Pass:** route guidance is useful, location-aware and consistent across web/mobile.

## UAT-07 — Orders and reorder recommendations

1. Create an order from a visit.
2. Verify product pricing, discount rules, totals and stock checks.
3. Create an order offline and sync it after reconnecting.
4. Confirm the server creates one order only.
5. For a customer with repeat approved purchase history, open reorder recommendations.
6. Verify cadence, suggested quantity, due/overdue timing and stock cap are explainable.
7. Build a normal order from the recommendation and review it before submission.

**Pass:** ordinary and recommended orders are accurate, reviewable and idempotent.

## UAT-08 — Collections, customer statement and credit

1. Open a customer balance/statement.
2. Record a collection with GPS evidence.
3. Verify overpayment protection/flagging.
4. Confirm verified collection status updates the statement/balance correctly.
5. Validate receipt/history visibility on web and mobile.

**Pass:** receivables and collections remain consistent and auditable.

## UAT-09 — Expenses, mileage and fuel

1. Record a normal expense.
2. Record a fuel expense with liters, unit price and odometer.
3. End the work day with odometer data.
4. Verify GPS distance versus odometer reconciliation.
5. Verify fuel totals, fuel cost and km/L in web mileage reporting.
6. Confirm suspicious outlier expenses can appear in anomaly detection when test data meets the rule threshold.

**Pass:** expense and mileage calculations reconcile across mobile and web.

## UAT-10 — Leads / Sales Pipeline

1. Create a lead on mobile while online.
2. Create/update a lead while offline, then sync.
3. Change stage and add an activity/follow-up.
4. Assign/verify ownership according to role.
5. Convert the lead to a customer.
6. Confirm conversion is idempotent and the original lead retains its audit history.

**Pass:** pipeline lifecycle and offline conversion behavior are reliable.

## UAT-11 — Calendar and appointments

1. Create an appointment on web.
2. Sync it to mobile.
3. Create/update appointment status from mobile.
4. Verify reminder scheduling and timezone behavior.
5. Confirm completed/cancelled states match across both clients.

**Pass:** appointment lifecycle and reminders are synchronized correctly.

## UAT-12 — Territory heat maps, anomalies and commissions

1. Open Territory Heat Maps for coverage, visits, sales and collections.
2. Change date range and currency; confirm monetary currencies are never combined.
3. Verify supervisor visibility is limited to assigned territories.
4. Generate known anomaly fixtures (for example geofence mismatch or controlled amount outlier) and verify evidence-based alerts.
5. Create/enable commission rules for sales/collections/product/target scenarios.
6. Generate a draft commission run and verify each line's evidence and currency.
7. Approve the run and confirm the approved period becomes immutable/non-overlapping.

**Pass:** management analytics are scoped, explainable and deterministic.

## UAT-13 — Ask FieldPulse

1. Confirm provider-health status is connected.
2. Ask questions covering attendance, sales, collections, visits, mileage, route context, reorder recommendations, territory performance and management priorities.
3. Verify answers use real tool data and preserve currency separation.
4. Verify conversation history/follow-up context.
5. Confirm customer-level AI tools remain unavailable when customer-data AI sharing is disabled.
6. Verify external-provider failure falls back gracefully without breaking the page.

**Pass:** Ask FieldPulse is grounded, policy-aware and operationally resilient.

## UAT-14 — Sync failures, retries and recovery

1. Create several offline records across visits/orders/collections/leads.
2. Interrupt connectivity during sync.
3. Confirm retryable failures remain queued and non-retryable validation failures become visible/blocked.
4. Correct one blocked item where supported and retry.
5. Verify a later sync clears all resolvable issues without duplicates.
6. Reopen the app and confirm queued state survives restart.

**Pass:** failure recovery is transparent, persistent and idempotent.

## UAT-15 — Release/backup recovery rehearsal

Run this on **non-production infrastructure** or a disposable clone of the production database/storage.

1. Create a fresh FieldPulse backup and verify its manifest/checksums.
2. Restore it using the documented destructive restore procedure.
3. Run migrations/readiness/ops checks after restore.
4. Verify representative customers, visits, orders, collections and uploaded media/voice notes.
5. Verify the restored application can authenticate and serve the core workflows.
6. Document restore duration and any manual intervention.

**Pass:** a verified backup can be restored into a working FieldPulse environment.

## Release decision

The release is accepted only when:

- UAT-01 through UAT-14 pass on the deployed release and physical Android device.
- UAT-15 passes on non-production infrastructure.
- No open blocker/critical defect remains.
- Web and mobile GitHub CI are green at the deployed/released commits.
- Production readiness and operational checks pass after the final deployment.
