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
| 13 | Admin Dashboard & Live Map | Complete / final merge pending | KPI dashboard, analytics, live map and field status |
| 14 | Notifications, Alerts & Reporting | Next | Alerts, fraud indicators, reports and exports |
| 15 | BusinessOS Integration | Optional | ERP integration adapter and synchronization |

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

### Planned Scope

- Database notification model and delivery pipeline.
- Notification preferences.
- Push notification foundation for mobile.
- Suspicious visit/GPS alert surfacing.
- Admin alerts page.
- Sales, visits, GPS and performance reports.
- Date/branch/salesman/territory filters where applicable.
- CSV export.
- PDF export where operationally justified.
- Basic commission-rule foundation only if it does not destabilize reporting.

### Verification

- Notifications are created and permission-scoped.
- Fraud/suspicious indicators are surfaced from existing evidence, not invented heuristics.
- Report totals match source transactions.
- Filters are tenant-safe.
- CSV exports match filtered report data.
- CI and formatting gates remain green.

## Batch 15 — BusinessOS Integration (Optional)

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
