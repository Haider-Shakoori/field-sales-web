# Field Sales SaaS — Development Roadmap

> **Status:** Planning only — no implementation has started.
> **Stack:** Laravel 13 · PHP 8.5 · MySQL 8 · Sanctum · Blade + Tailwind + Alpine admin panel · Flutter Android app (separate project)

---

## Table of Contents

1. [Batch 1 — Foundation & Tenancy](#batch-1--foundation--tenancy)
2. [Batch 2 — Roles, Permissions & User Management](#batch-2--roles-permissions--user-management)
3. [Batch 3 — Sales Team & Device Management](#batch-3--sales-team--device-management)
4. [Batch 4 — Customers & Territories](#batch-4--customers--territories)
5. [Batch 5 — Products & Price Lists](#batch-5--products--price-lists)
6. [Batch 6 — Attendance & Work Sessions](#batch-6--attendance--work-sessions)
7. [Batch 7 — GPS Tracking Core](#batch-7--gps-tracking-core)
8. [Batch 8 — Customer Visits](#batch-8--customer-visits)
9. [Batch 9 — Orders](#batch-9--orders)
10. [Batch 10 — Collections](#batch-10--collections)
11. [Batch 11 — Expenses & Targets](#batch-11--expenses--targets)
12. [Batch 12 — Sync Engine & Offline Support](#batch-12--sync-engine--offline-support)
13. [Batch 13 — Admin Dashboard & Live Map](#batch-13--admin-dashboard--live-map)
14. [Batch 14 — Notifications, Alerts & Reporting](#batch-14--notifications-alerts--reporting)
15. [Batch 15 (Optional) — BusinessOS Integration](#batch-15-optional--businessos-integration)
16. [Batch Summary Table](#batch-summary-table)
17. [Batch Dependency Graph](#batch-dependency-graph)
18. [Risk Assessment](#risk-assessment)
19. [Unresolved Decisions](#unresolved-decisions)

---

## Batch 1 — Foundation & Tenancy

**Goal:** Establish the core Laravel project structure, multi-tenant foundation, and authentication.

### Scope

- Tenant model and migration
- Company/tenant configuration
- Branch model and migration
- User model enhancement (role, `tenant_id`, `is_active`)
- Sanctum configuration
- Auth: login, logout, password reset (web)
- Tenant resolution middleware
- Global Eloquent scope for tenant isolation
- Basic `artisan make` commands setup
- Database seeders for initial data (roles, default tenant)

### Dependencies

- Fresh Laravel 13 project

### Files / Modules

- `app/Domains/Tenancy/` — Models, Migrations
- `app/Domains/Identity/` — User model, Roles
- `app/Http/Middleware/` — `TenantMiddleware`
- `config/tenancy.php`
- `database/seeders/`

### Verification

- Can create tenant, user, and log in via web
- Tenant scope filters queries correctly
- API auth endpoints functional

### Completion Criteria

Multi-tenant foundation working with authentication end-to-end.

---

## Batch 2 — Roles, Permissions & User Management

**Goal:** Implement role-based access control, permission system, and user management CRUD.

### Scope

- Role and Permission models
- Role-permission relationships
- Permission middleware for API and web
- Policy base classes
- User CRUD (admin web)
- User assignment to roles
- Branch assignment for users
- Audit logging foundation

### Dependencies

- Batch 1

### Files / Modules

- `app/Domains/Identity/` — Roles, Permissions, Policies
- `app/Http/Requests/` — User requests
- `app/Http/Controllers/` — `UserController`
- `database/migrations/` — Roles, permissions tables

### Verification

- Can create roles and assign permissions
- Users can only access permitted resources
- Audit logs created for user changes

### Completion Criteria

RBAC functional with user management.

---

## Batch 3 — Sales Team & Device Management

**Goal:** Build salesman/supervisor profiles and mobile device registration.

### Scope

- Salesman model and migration
- Supervisor model and migration
- Salesman assignment (historical)
- Supervisor assignment (historical)
- Device registration API
- Device management (list, revoke, heartbeat)
- Employee code generation
- Team hierarchy (Manager → Supervisor → Salesman)

### Dependencies

- Batch 2

### Files / Modules

- `app/Domains/SalesTeam/` — Salesman, Supervisor, Assignments
- `app/Domains/Devices/` — Device model, registration
- `app/Http/Controllers/Api/DeviceController`
- `app/Http/Requests/` — Device requests

### Verification

- Salesmen and supervisors created with assignments
- Devices registered via API
- Device revocation works
- Team hierarchy visible in admin

### Completion Criteria

Sales team and device management complete.

---

## Batch 4 — Customers & Territories

**Goal:** Build customer master data and territory/route structure.

### Scope

- Customer model and migration
- Customer categories
- Territory model and migration
- Route model and migration
- Route-customer junction with ordering
- Customer CRUD (admin web)
- Customer assignment to salesmen
- Customer assignment to territories/routes
- Geofence radius per customer
- Customer location history

### Dependencies

- Batch 3

### Files / Modules

- `app/Domains/Customers/` — Customer, Category, LocationHistory
- `app/Domains/Territories/` — Territory, Route, RouteCustomer
- `app/Http/Controllers/` — CustomerController, TerritoryController, RouteController
- `app/Http/Resources/` — CustomerResource

### Verification

- Customers created with geolocation
- Territories and routes structured
- Route-customer ordering works
- Customer assignment to salesmen functional

### Completion Criteria

Customer and territory foundation complete.

---

## Batch 5 — Products & Price Lists

**Goal:** Build lightweight product catalog and pricing.

### Scope

- Product model and migration
- Price list model and migration
- Price list items
- Product CRUD (admin web)
- Price list management
- Default price list per company
- Product categories

### Dependencies

- Batch 4

### Files / Modules

- `app/Domains/Products/` — Product, PriceList, PriceListItem
- `app/Http/Controllers/` — ProductController, PriceListController

### Verification

- Products created with SKU and pricing
- Price lists managed
- Products available for order creation

### Completion Criteria

Product catalog ready.

---

## Batch 6 — Attendance & Work Sessions

**Goal:** Build work session tracking with GPS verification.

### Scope

- Work session model and migration
- Start day API (with GPS)
- End day API (with GPS)
- Today's session status
- Session duration calculation
- Late start / early finish detection
- Break tracking
- Manual correction workflow
- Supervisor approval for corrections
- Attendance list view (admin web)

### Dependencies

- Batch 3, Batch 4

### Files / Modules

- `app/Domains/Attendance/` — WorkSession
- `app/Http/Controllers/Api/AttendanceController`
- `app/Http/Controllers/` — AttendanceController (web)

### Verification

- Salesman can start/end day via API
- GPS recorded at start/end
- Duration calculated
- Corrections require approval
- Attendance visible in admin

### Completion Criteria

Attendance tracking functional.

---

## Batch 7 — GPS Tracking Core

**Goal:** Implement GPS storage, current location, and basic GPS infrastructure.

### Scope

- Location history model and migration
- Current location model and migration
- Location sync batch model
- GPS bulk upload API endpoint
- Current location UPSERT logic
- Redis latest-location cache
- GPS data validation
- Mock location detection flag
- GPS quality filtering
- GPS indexing strategy

### Dependencies

- Batch 1, Batch 3

### Files / Modules

- `app/Domains/Tracking/` — LocationHistory, CurrentLocation, SyncBatch
- `app/Http/Controllers/Api/GpsController`
- `app/Jobs/ProcessLocationBatch`
- `app/Services/GpsService`

### Verification

- GPS points uploaded via API in batches
- Current location updated correctly
- Redis cache reflects latest positions
- Invalid GPS rejected

### Completion Criteria

GPS infrastructure operational.

---

## Batch 8 — Customer Visits

**Goal:** Build visit workflow with check-in/out and GPS verification.

### Scope

- Visit model and migration
- Visit photo model
- Visit suspicious flag model
- Check-in API (with GPS)
- Check-out API
- Visit distance calculation from customer
- Geofence verification
- Visit duration calculation
- Planned vs unplanned visits
- Visit outcome recording
- Visit list view (admin web)
- Visit detail view (admin web)

### Dependencies

- Batch 4, Batch 6, Batch 7

### Files / Modules

- `app/Domains/Visits/` — CustomerVisit, VisitPhoto, SuspiciousFlag
- `app/Http/Controllers/Api/VisitController`
- `app/Http/Controllers/` — VisitController (web)
- `app/Services/GeofenceService`

### Verification

- Salesman checks in at customer location
- GPS distance calculated
- Geofence verified
- Visit duration tracked
- Visits visible in admin

### Completion Criteria

Visit workflow functional.

---

## Batch 9 — Orders

**Goal:** Build field order capture with offline UUID support.

### Scope

- Order model and migration
- Order item model
- Order number generation
- Order creation API (with offline UUID)
- Order status workflow
- Order items with product, quantity, price
- Discount and total calculation
- Cash/credit payment type
- Order list view (admin web)
- Order detail view (admin web)
- Order approval workflow (admin)

### Dependencies

- Batch 4, Batch 5, Batch 8

### Files / Modules

- `app/Domains/Orders/` — Order, OrderItem
- `app/Http/Controllers/Api/OrderController`
- `app/Http/Controllers/` — OrderController (web)
- `app/Services/OrderService`

### Verification

- Orders created via API with offline UUID
- Order items calculate correctly
- Status workflow progresses
- Idempotent order creation
- Orders visible in admin

### Completion Criteria

Order capture functional.

---

## Batch 10 — Collections

**Goal:** Build payment collection recording.

### Scope

- Collection model and migration
- Collection creation API (with offline UUID)
- Multiple payment methods
- GPS and receipt photo
- Collection list view (admin web)
- Collection detail view (admin web)
- Idempotent collection creation

### Dependencies

- Batch 4, Batch 8

### Files / Modules

- `app/Domains/Collections/` — Collection
- `app/Http/Controllers/Api/CollectionController`
- `app/Http/Controllers/` — CollectionController (web)

### Verification

- Collections recorded via API
- Multiple payment methods work
- GPS captured
- Idempotent creation
- Collections visible in admin

### Completion Criteria

Collection recording functional.

---

## Batch 11 — Expenses & Targets

**Goal:** Build expense tracking and sales target management.

### Scope

- Expense model and migration
- Expense submission API
- Expense categories
- Receipt photo upload
- Expense approval workflow
- Supervisor approval
- Target model and migration
- Target creation (admin)
- Target achievement calculation
- Target list view (admin web)
- Expense list view (admin web)

### Dependencies

- Batch 3, Batch 6

### Files / Modules

- `app/Domains/Expenses/` — Expense
- `app/Domains/Targets/` — Target
- `app/Http/Controllers/Api/ExpenseController`
- `app/Http/Controllers/` — ExpenseController, TargetController (web)
- `app/Jobs/CalculateTargetAchievement`

### Verification

- Expenses submitted and approved
- Targets created and tracked
- Achievement calculated
- Both visible in admin

### Completion Criteria

Expenses and targets functional.

---

## Batch 12 — Sync Engine & Offline Support

**Goal:** Build the complete sync protocol for offline-first mobile.

### Scope

- Sync push endpoint (client → server)
- Sync pull endpoint (server → client)
- Idempotency handling for all entity types
- Conflict detection and resolution
- Sync queue processing
- Sync cursor/checkpoint management
- Tombstone records for deletes
- Bulk GPS upload optimization
- Sync logging

### Dependencies

- Batch 7, Batch 8, Batch 9, Batch 10, Batch 11

### Files / Modules

- `app/Http/Controllers/Api/SyncController`
- `app/Services/SyncService`
- `app/Domains/Sync/` — SyncLog
- `app/Jobs/ProcessSyncPush`

### Verification

- Pull returns changed entities since timestamp
- Push processes creates and updates
- Idempotency prevents duplicates
- Conflict detection works
- Sync logs recorded

### Completion Criteria

Sync engine operational.

---

## Batch 13 — Admin Dashboard & Live Map

**Goal:** Build the admin dashboard with KPIs and live map.

### Scope

- Dashboard controller and view
- KPI cards (today's sales, collections, active salesmen, etc.)
- Sales trend chart
- Visit completion chart
- Live map page
- Map component (Leaflet/MapLibre)
- Salesman markers on map
- Auto-refresh polling
- Status indicators (online/idle/offline)
- Salesman status table
- Role-based dashboard variants

### Dependencies

- Batch 7, Batch 8, Batch 9, Batch 10

### Files / Modules

- `app/Http/Controllers/` — DashboardController
- `app/Http/Controllers/Api/TrackingController` — live locations
- `resources/views/pages/dashboard/`
- `resources/views/pages/tracking/`
- `resources/views/components/ui/map-card.blade.php`
- `resources/js/map.js`

### Verification

- Dashboard loads with KPI data
- Charts render with real data
- Live map shows salesman locations
- Map auto-refreshes
- Role-based views work

### Completion Criteria

Dashboard and live map functional.

---

## Batch 14 — Notifications, Alerts & Reporting

**Goal:** Build notification system, fraud alerts, and report generation.

### Scope

- Notification model and migration
- Database notifications
- Push notification setup (FCM)
- Notification preferences
- Suspicious activity alerts
- Fraud indicator detection jobs
- Alerts page (admin web)
- Report generation (sales, visits, GPS, performance)
- Report filters
- Export to CSV/PDF
- Commission rules (basic)

### Dependencies

- Batch 8, Batch 9, Batch 10, Batch 11, Batch 13

### Files / Modules

- `app/Domains/Notifications/` — Notification, NotificationTemplate
- `app/Domains/Reporting/` — ReportService
- `app/Domains/Commissions/` — CommissionRule, Commission
- `app/Jobs/SendPushNotification`
- `app/Jobs/DetectFraudIndicators`
- `app/Http/Controllers/` — AlertController, ReportController
- `resources/views/pages/alerts/`
- `resources/views/pages/reports/`

### Verification

- Notifications created and displayed
- Fraud indicators detected
- Alerts page shows suspicious activity
- Reports generate with correct data
- CSV export works

### Completion Criteria

Notifications, alerts, and reporting functional.

---

## Batch 15 (Optional) — BusinessOS Integration

**Goal:** Build the integration layer for BusinessOS ERP.

### Scope

- Integration adapter interface
- BusinessOS adapter implementation
- Inbound sync (products, customers, prices)
- Outbound sync (orders, collections, new customers)
- Sync scheduler jobs
- Integration settings UI
- Sync monitoring dashboard
- Error handling and retry
- Manual sync trigger

### Dependencies

- Batch 12 (all entity sync infrastructure)

### Files / Modules

- `app/Domains/Integrations/` — IntegrationAdapter, BusinessOSAdapter
- `app/Jobs/` — SyncInbound\*, SyncOutbound\*
- `app/Http/Controllers/` — IntegrationController
- `resources/views/pages/settings/integrations.blade.php`
- `config/integrations.php`

### Verification

- BusinessOS connection testable
- Products pulled from BusinessOS
- Orders pushed to BusinessOS
- Sync status visible in admin
- Error handling works

### Completion Criteria

BusinessOS integration operational.

---

## Batch Summary Table

| Batch | Name | Goal | Dependencies | Est. Complexity |
|-------|------|------|--------------|-----------------|
| 1 | Foundation & Tenancy | Core project structure, multi-tenant foundation, and authentication | Fresh Laravel 13 | Medium |
| 2 | Roles, Permissions & User Mgmt | Role-based access control, permission system, and user CRUD | Batch 1 | Medium |
| 3 | Sales Team & Device Mgmt | Salesman/supervisor profiles and mobile device registration | Batch 2 | Medium |
| 4 | Customers & Territories | Customer master data and territory/route structure | Batch 3 | Medium |
| 5 | Products & Price Lists | Lightweight product catalog and pricing | Batch 4 | Low |
| 6 | Attendance & Work Sessions | Work session tracking with GPS verification | Batch 3, 4 | Medium |
| 7 | GPS Tracking Core | GPS storage, current location, and basic GPS infrastructure | Batch 1, 3 | High |
| 8 | Customer Visits | Visit workflow with check-in/out and GPS verification | Batch 4, 6, 7 | High |
| 9 | Orders | Field order capture with offline UUID support | Batch 4, 5, 8 | High |
| 10 | Collections | Payment collection recording | Batch 4, 8 | Medium |
| 11 | Expenses & Targets | Expense tracking and sales target management | Batch 3, 6 | Medium |
| 12 | Sync Engine & Offline Support | Complete sync protocol for offline-first mobile | Batch 7–11 | High |
| 13 | Admin Dashboard & Live Map | Admin dashboard with KPIs and live map | Batch 7–10 | High |
| 14 | Notifications, Alerts & Reporting | Notification system, fraud alerts, and report generation | Batch 8–11, 13 | High |
| 15 | BusinessOS Integration *(optional)* | Integration layer for BusinessOS ERP | Batch 12 | Medium |

---

## Batch Dependency Graph

```
Batch 1  ──► Batch 2  ──► Batch 3 ──┬──► Batch 4 ──┬──► Batch 5
                                      │              │
                                      │              ├──► Batch 6
                                      │              │         │
                                      │              │         ▼
                                      │              ├──► Batch 7
                                      │              │         │
                                      │              │         ▼
                                      │              ├──► Batch 8 ◄── Batch 6, Batch 7
                                      │              │         │
                                      │              │         ├──► Batch 9  ◄── Batch 5
                                      │              │         │
                                      │              │         ├──► Batch 10
                                      │              │         │
                                      │              │         ▼
                                      │              ├──► Batch 11 ◄── Batch 6
                                      │              │
                                      │              ▼
                                      └──► Batch 12 ◄── Batch 7, 8, 9, 10, 11
                                                     │
                                                     ▼
                                    Batch 13 ◄── Batch 7, 8, 9, 10
                                        │
                                        ▼
                                    Batch 14 ◄── Batch 8, 9, 10, 11, 13
                                        │
                                        ▼
                                    Batch 15 (Optional) ◄── Batch 12
```

**Read the graph left-to-right:** A batch can only start once every batch pointing to it has been completed.

---

## Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| **GPS volume scalability** — High-frequency GPS uploads from many devices can overwhelm storage and queries. | High | Batch GPS uploads, Redis caching for latest positions, aggressive MySQL indexing, consider TimescaleDB for time-series if volume exceeds expectations. |
| **Offline sync reliability** — Conflict resolution and idempotency are notoriously hard to get right. | High | Thorough testing with simulated offline/online cycles, tombstone records, sync cursors, and explicit conflict resolution policies. |
| **Multi-tenant data isolation bugs** — Cross-tenant data leaks are critical security issues. | High | Global Eloquent scopes enforced at every layer, comprehensive tenant-scoped tests, middleware on every API route, and row-level database checks. |
| **Flutter app development timeline (external)** — The mobile app is built separately and may not align with API milestones. | Medium | API-first design with well-documented contracts, mockable endpoints, and independent deployability. |
| **BusinessOS API availability** — External ERP API may be unreliable, rate-limited, or poorly documented. | Medium | Adapter pattern for easy swap, retry logic with exponential backoff, manual sync fallback, and sync monitoring. |
| **Map tile provider reliability in Afghanistan** — Map data quality and tile availability may be inconsistent. | Medium | Evaluate multiple providers (OSM, Mapbox), cache tiles locally where possible, and ensure graceful degradation. |

---

## Unresolved Decisions

| Decision | Context | Status |
|----------|---------|--------|
| **Livewire vs pure Blade** | Admin panel interactivity requirements not yet clear. | Deferred until UI needs clarity |
| **Specific chart library** | Chart.js is a candidate but alternatives (ApexCharts, Chartist) not evaluated. | Pending benchmark |
| **Map tile provider for Afghanistan** | OpenStreetMap vs Mapbox — data quality and availability in Afghanistan unknown. | Pending provider evaluation |
| **Redis driver choice** | Predis (PHP) vs PhpRedis (C extension) — performance and deployment trade-offs. | Pending load testing |
| **Push notification package** | Laravel Notification Channels ecosystem has multiple FCM packages. | Pending evaluation |
