# Field Sales SaaS — Development Roadmap

> **Status:** Batch 1 (Foundation & Tenancy), **Batch 2 (Roles, Permissions & User Management)** and **Batch 4 (Customers, Territories & Routes)** **COMPLETE**. Batches 3 and 5+ planning only.
> **Stack:** Laravel 13 · PHP 8.5 · MySQL 8 · Sanctum · Blade + Tailwind + Alpine admin panel · Flutter Android app (`field-sales-mobile`, created in **Batch 6**)

> **Dual-repository scope:** From Batch 6 onward, relevant implementation batches may modify BOTH separate repositories:
> - **Backend:** `field-sales-api`
> - **Mobile:** `field-sales-mobile`
>
> They remain separate applications and separate Git repositories, but both are part of the same development roadmap. No batch may be considered complete until the mobile-side work it defines is done alongside the Laravel-side work.

> **Mobile Offline-First Contract:** Offline-first begins in **Batch 6 — Flutter Android Foundation & Offline-First Core**, where the separate Flutter project is created and establishes local SQLite, local-first write architecture, client UUID generation, a basic sync queue, connectivity detection, and `pending` / `syncing` / `synced` / `failed` record states. **Batch 12 — Offline Sync Engine Hardening** does **not** start offline capability; it hardens the Batch 6 foundation with advanced push/pull synchronization, cursors/checkpoints, retry/backoff, conflicts, duplicate prevention, tombstones, partial failures, recovery, and sync diagnostics.

---

## Table of Contents

1. [Batch 1 — Foundation & Tenancy](#batch-1--foundation--tenancy)
2. [Batch 2 — Roles, Permissions & User Management](#batch-2--roles-permissions--user-management)
3. [Batch 3 — Sales Team & Device Management](#batch-3--sales-team--device-management)
4. [Batch 4 — Customers, Territories & Routes](#batch-4--customers-territories--routes)
5. [Batch 5 — Products, Price Lists & Mobile API Preparation](#batch-5--products-price-lists--mobile-api-preparation)
6. [Batch 6 — Flutter Android Foundation & Offline-First Core](#batch-6--flutter-android-foundation--offline-first-core)
7. [Batch 7 — Attendance & GPS Tracking Core](#batch-7--attendance--gps-tracking-core)
8. [Batch 8 — Customer Visits & Geofencing](#batch-8--customer-visits--geofencing)
9. [Batch 9 — Orders](#batch-9--orders)
10. [Batch 10 — Collections](#batch-10--collections)
11. [Batch 11 — Expenses & Targets](#batch-11--expenses--targets)
12. [Batch 12 — Offline Sync Engine Hardening](#batch-12--offline-sync-engine-hardening)
13. [Batch 13 — Admin Dashboard, Live Map & Mobile Refinement](#batch-13--admin-dashboard-live-map--mobile-refinement)
14. [Batch 14 — Notifications, Alerts, Reporting & Final End-to-End QA](#batch-14--notifications-alerts-reporting--final-end-to-end-qa)
15. [Batch 15 (Optional) — BusinessOS Integration](#batch-15-optional--businessos-integration)
16. [Batch Summary Table](#batch-summary-table)
17. [Batch Dependency Graph](#batch-dependency-graph)
18. [Risk Assessment](#risk-assessment)
19. [Unresolved Decisions](#unresolved-decisions)

---

## Batch 1 — Foundation & Tenancy

**Goal:** Establish the core Laravel project structure, multi-tenant foundation, and authentication.

> **Status: ✅ COMPLETE** — see `docs/DATABASE_DESIGN.md` for the schema and the delivery report in the Batch 1 closeout message for details.

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

- Fresh Laravel project

### Files / Modules

- `app/Models/` — Tenant, Branch, User, Currency, Role, Permission, ModelHasRole, CompanySetting, AuditLog, BelongsToTenant concern, TenantScope
- `app/Support/Tenancy/` — TenantContext (singleton backed by `InitializeTenancy` middleware)
- `app/Providers/TenancyServiceProvider` — singleton, policy mapping, Gate::before RBAC
- `config/tenancy.php`
- `database/seeders/` — RbacSeeder, CurrencySeeder, DemoTenantSeeder
- `app/Http/Controllers/Auth/` — LoginController, PasswordResetLinkController, NewPasswordController

### Verification

- Can create tenant, user, and log in via web ✅
- Tenant scope filters queries correctly ✅
- API auth endpoints functional ✅ (Sanctum token + `GET /api/v1/me` envelope verified)

### Completion Criteria

Multi-tenant foundation working with authentication end-to-end. ✅

---

## Batch 2 — Roles, Permissions & User Management

**Goal:** Implement role-based access control, permission system, and user management CRUD.

> **Status: ✅ COMPLETE** — Role management CRUD + permission matrix UI, user CRUD with tenant-safe role/branch assignment, audit logging + UI, and targeted security tests have all been delivered. See the Batch 2 delivery report for full details.

### Architectural deviations (documented)

Two schema additions were required beyond Batch 1's design and are documented here as intentional deviations:

1. **`roles.tenant_id`** (`database/migrations/2026_09_15_164625_add_tenant_id_to_roles_table.php`): nullable FK to `tenants.id`. `NULL` = protected *system* roles shared across all tenants (`super_admin`, `owner`, `sales_manager`, `salesman`, `accountant`); a non-null value = a tenant's *custom* role, visible/assignable only inside that tenant. Company admins can create/edit/delete only their own tenant's custom roles; system roles are view-only (marked **Protected** in the UI).
2. **`users.branch_id`** (`database/migrations/2026_09_15_164626_add_branch_id_to_users_table.php`): nullable FK to `branches.id`, matching the existing API `user` object contract (`branch_id`). Branch/role inputs are validated tenant-safe (closure + `Rule::exists` with `tenant_id` scoping).

Additional behavior introduced:

- `config('tenancy.sensitive_permissions')` = `['users:manage', 'roles:manage', 'settings:manage']` require an Alpine confirmation modal when granted to a custom role.
- `config('tenancy.platform_permissions')` = `['tenants:manage']` can never be granted to a company custom role (super admin only).
- Company custom-role names are lowercase underscores (`/^[a-z][a-z0-9_]*$/`), must not shadow a system role, and are unique per tenant.
- Inactive users are rejected at login; a user cannot change their own role; the last active `owner` of a tenant cannot be deactivated or demoted.
- `RoleController::destroy` refuses to delete any role that still has users (`Role::userCount()`).
- Audit events are recorded for role and permission changes (`role.created`, `role.updated`, `role.permissions.changed`, `role.deleted`) and user changes (`user.created`, `user.updated`, `user.role.changed`, `user.branch.changed`, `user.deactivated`, `user.role.deactivated`) and surfaced via `GET /audit`.

### Scope

- Role and Permission models
- Role-permission relationships
- Permission middleware for API and web
- Policy base classes
- User CRUD (admin web)
- User assignment to roles
- Branch assignment for users
- Audit logging foundation

> **Delivered:** RBAC models (Role, Permission, ModelHasRole) from Batch 1 reused as planned — no duplicate RBAC was introduced. Batch 2 focused on role management CRUD + permission matrix, tenant-safe user CRUD, and the audit foundation.

### Dependencies

- Batch 1

### Files / Modules

- `app/Http/Requests/` — `StoreUserRequest`, `UpdateUserRequest`, `StoreRoleRequest`, `UpdateRoleRequest`
- `app/Http/Controllers/` — `UserController`, `RoleController`, `AuditLogController`
- `app/Policies/` — `RolePolicy`, `UserPolicy`
- `database/migrations/` — `add_tenant_id_to_roles_table`, `add_branch_id_to_users_table` (deviations)
- `resources/views/` — `pages/users/*`, `pages/settings/roles/*`, `components/ui/permission-matrix`, `pages/audit/index`

### Verification

- Can create roles and assign permissions ✅
- Users can only access permitted resources ✅
- Audit logs created for user changes ✅
- 47 feature tests passing (`UserManagementTest`, `RoleManagementTest`, `PageRenderTest` + existing Batch 1 suites) ✅

### Completion Criteria

RBAC functional with user management. ✅

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

## Batch 4 — Customers, Territories & Routes

**Goal:** Build customer master data and territory/route structure.

> **Status: ✅ COMPLETE** — customer master data, territories, routes/route-customers, historical salesman & supervisor assignment windows, customer location history, and the customer/route/territory REST API foundation are all delivered. Verification: **118 tests / 332 assertions passing**, Pint clean, `git diff --check` clean. Batch 4 work remains uncommitted pending this documentation pass.

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

## Batch 5 — Products, Price Lists & Mobile API Preparation

**Goal:** Build a lightweight product catalog and pricing, and freeze the mobile-facing API contract that the Flutter app will consume from Batch 6 onward.

### Scope

- Product model and migration
- Price list model and migration
- Price list items
- Product CRUD (admin web)
- Price list management
- Default price list per company
- Product categories

#### Mobile API Preparation

- Versioned mobile-facing API freeze for catalog, pricing, and reference data
- Consistent envelope (`success`/`data`/`meta`/`error`) across all mobile-facing endpoints
- Resource serialization for mobile consumption (catalog, price lists, reference data)
- Documented endpoint list and field reference for the Batch 6 API client

### Dependencies

- Batch 4

### Files / Modules

- `app/Domains/Products/` — Product, PriceList, PriceListItem
- `app/Http/Controllers/` — ProductController, PriceListController
- `app/Http/Resources/` — mobile-facing catalog resources
- `docs/API_CONTRACT.md` — versioned mobile contract section

### Verification

- Products created with SKU and pricing
- Price lists managed
- Products available for order creation
- Mobile-facing catalog endpoints return the frozen envelope contract

### Completion Criteria

Product catalog ready and mobile API contract frozen for Batch 6 consumption.

---

## Batch 6 — Flutter Android Foundation & Offline-First Core

**Goal:** Create the separate Flutter project and establish the offline-first mobile foundation. This is where the Android app comes into existence — **not** an external project — and where offline capability **begins**.

> **Repository: `field-sales-mobile`** — a separate application and Git repository, created here and carried through every later batch.

### Scope

#### Project & Architecture

- Flutter project creation (`field-sales-mobile`)
- Android-first architecture
- Secure token storage (encrypted storage)
- Localization foundation — English / Dari / Pashto readiness
- Light/dark theme
- App navigation shell

#### Backend Integration

- API client layer (versioned, `api/v1`)
- Sanctum authentication (login + token handling)
- Device registration flow

#### Offline-First Core (offline capability begins here)

- SQLite / local database
- Local-first write architecture
- Client UUID generation
- Sync queue foundation
- Connectivity detection
- Record sync states: `pending` / `syncing` / `synced` / `failed`

#### Mobile UI Shell

- Login screen
- Mobile dashboard / home
- Route / customer shell screens
- Profile
- Visible offline / sync status indicator

### Dependencies

- Batch 2, Batch 3, Batch 5

### Files / Modules

- `field-sales-mobile/` — new Flutter project
- `lib/core/api/` — API client, Sanctum token handling
- `lib/core/storage/` — secure token storage, SQLite/local DB layer
- `lib/core/sync/` — sync queue, connectivity detection, sync state model
- `lib/features/auth/` — login screen
- `lib/features/home/` — dashboard/home, route/customer shells, profile
- `lib/l10n/` — English / Dari / Pashto localization foundation
- `lib/theme/` — light/dark theming

### Verification

- Flutter app boots on Android and reaches the login screen
- Login against the real API with a Sanctum token works
- Token persisted in encrypted storage across restarts
- Records created offline persist locally with client UUIDs and `pending` state
- Sync status indicator reflects connectivity and per-record state

### Completion Criteria

Android app exists, authenticates against the API, and captures data offline-first (local SQLite, client UUIDs, `pending`/`syncing`/`synced`/`failed` states).

---

## Batch 7 — Attendance & GPS Tracking Core

**Goal:** Build work-session tracking and GPS infrastructure end-to-end across **both** repositories.

### Scope

#### Laravel (`field-sales-api`)

- Attendance / work session model and migrations
- Start-day and end-day APIs (with GPS)
- Today's session status, duration calculation
- GPS storage: location history, current location, location sync batch
- GPS bulk upload API endpoint
- Current location UPSERT + Redis latest-location cache
- GPS validation, mock-location detection, quality filtering, indexing
- GPS batching backend

#### Flutter (`field-sales-mobile`)

- Start / end day UI and interaction
- Location permission flow
- Foreground service
- Background GPS collection
- Screen-off tracking
- Local GPS persistence
- Batched GPS sync
- Battery / network metadata capture
- Configurable tracking intervals
- Visible tracking indicator
- Offline GPS capture (records locally, syncs when online)

### Dependencies

- Batch 4, Batch 6

### Files / Modules

#### Backend

- `app/Domains/Attendance/` — WorkSession
- `app/Domains/Tracking/` — LocationHistory, CurrentLocation, SyncBatch
- `app/Http/Controllers/Api/` — AttendanceController, GpsController
- `app/Jobs/ProcessLocationBatch`
- `app/Services/GpsService`

#### Mobile

- `lib/features/attendance/` — start/end day, session status
- `lib/features/tracking/` — GPS service, foreground service, batched sync
- `lib/core/permissions/` — location permission flow

### Verification

- Salesman starts/ends day via mobile; GPS recorded at both events
- GPS points uploaded in batches; invalid GPS rejected
- Redis cache reflects latest positions
- Background/screen-off tracking persists and syncs offline queues

### Completion Criteria

Attendance and GPS tracking operational end-to-end across API and Android app.

---

## Batch 8 — Customer Visits & Geofencing

**Goal:** Build the visit workflow with geofencing — fully wired into the mobile app.

### Scope

#### Laravel (`field-sales-api`)

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
- Visit list/detail views (admin web)

#### Flutter (`field-sales-mobile`)

- Planned visits list (from routes)
- Customer detail screen
- Check-in / check-out UX
- Geofence proximity feedback
- Visit notes entry
- Visit photo capture
- Offline visit entry
- Visit synchronization (queued locally, synced when online)

### Dependencies

- Batch 4, Batch 6, Batch 7

### Files / Modules

#### Backend

- `app/Domains/Visits/` — CustomerVisit, VisitPhoto, SuspiciousFlag
- `app/Http/Controllers/Api/VisitController`
- `app/Http/Controllers/` — VisitController (web)
- `app/Services/GeofenceService`

#### Mobile

- `lib/features/visits/` — planned visits, check-in/out, notes, photos
- `lib/features/customers/` — customer detail
- Sync queue entry for offline visits

### Verification

- Salesman checks in at customer location via the app (geofence verified)
- Offline visit stored locally with UUID and synced when connectivity returns
- GPS distance and duration calculated
- Visits visible in admin

### Completion Criteria

Visit workflow functional across backend and mobile.

---

## Batch 9 — Orders

**Goal:** Build field order capture with offline-first semantics.

### Scope

#### Laravel (`field-sales-api`)

- Order model and migration
- Order item model
- Order number generation
- Order creation API (with offline UUID)
- Order status workflow
- Order items with product, quantity, price
- Discount and total calculation
- Cash/credit payment type
- Idempotent server handling (safe retries on `offline_uuid`)
- Order list/detail views (admin web)
- Order approval workflow (admin)

#### Flutter (`field-sales-mobile`)

- Product catalog browsing
- Pricing display (from synced price lists)
- Cart / order-entry UI
- Offline order creation
- Local UUID generation
- Pending sync behavior (queued, visible state)
- Order history screen

### Dependencies

- Batch 4, Batch 5, Batch 6, Batch 8

### Files / Modules

#### Backend

- `app/Domains/Orders/` — Order, OrderItem
- `app/Http/Controllers/Api/OrderController`
- `app/Http/Controllers/` — OrderController (web)
- `app/Services/OrderService`

#### Mobile

- `lib/features/orders/` — catalog, cart, order entry, order history
- Sync queue entry for offline orders

### Verification

- Orders created via API with offline UUID, idempotently
- Orders created offline in the app sync without duplicates
- Order items calculate correctly
- Status workflow progresses
- Orders visible in admin

### Completion Criteria

Order capture functional across backend and mobile.

---

## Batch 10 — Collections

**Goal:** Build payment collection recording with offline support.

### Scope

#### Laravel (`field-sales-api`)

- Collection model and migration
- Collection creation API (with offline UUID)
- Multiple payment methods
- GPS and receipt photo
- Idempotent collection creation
- Customer balance calculation
- Collection list/detail views (admin web)

#### Flutter (`field-sales-mobile`)

- Customer balance display
- Collection entry UI
- Payment method selection
- GPS capture at collection time
- Receipt / proof capture
- Offline collection recording
- Collection synchronization

### Dependencies

- Batch 4, Batch 6, Batch 8, Batch 9

### Files / Modules

#### Backend

- `app/Domains/Collections/` — Collection
- `app/Http/Controllers/Api/CollectionController`
- `app/Http/Controllers/` — CollectionController (web)

#### Mobile

- `lib/features/collections/` — balance, entry, payment methods
- Sync queue entry for offline collections

### Verification

- Collections recorded via mobile API with GPS and receipt
- Multiple payment methods work
- Offline collections sync without duplicates (idempotent)
- Collections visible in admin

### Completion Criteria

Collection recording functional across backend and mobile.

---

## Batch 11 — Expenses & Targets

**Goal:** Build expense tracking and sales target management.

### Scope

#### Laravel (`field-sales-api`)

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

#### Flutter (`field-sales-mobile`)

- Expense entry UI
- Receipt photo capture
- Offline expense creation
- Target display and progress
- Expense/target synchronization
- Expense history screen

### Dependencies

- Batch 3, Batch 6, Batch 7

### Files / Modules

#### Backend

- `app/Domains/Expenses/` — Expense
- `app/Domains/Targets/` — Target
- `app/Http/Controllers/Api/ExpenseController`
- `app/Http/Controllers/` — ExpenseController, TargetController (web)
- `app/Jobs/CalculateTargetAchievement`

#### Mobile

- `lib/features/expenses/` — entry, receipts, approval status
- `lib/features/targets/` — progress display
- Sync queue entry for offline expenses

### Verification

- Expenses submitted and approved
- Offline expenses sync correctly
- Targets created and tracked; achievement calculated
- Both visible/progress shown in admin and app

### Completion Criteria

Expenses and targets functional across backend and mobile.

---

## Batch 12 — Offline Sync Engine Hardening

**Goal:** Harden the offline capability that **already began in Batch 6**. This batch does **not** introduce offline support — it makes the Batch 6 foundation production-grade.

### Scope

- Push / pull synchronization (client → server, server → client)
- Sync cursors / checkpoints
- Retry / backoff for failed payloads
- Conflict detection and resolution
- Duplicate prevention and reconciliation
- Tombstone records for deletes
- Partial-failure handling (per-entity results)
- Image upload recovery
- GPS bulk upload optimization
- Application-restart recovery (resume interrupted sync)
- Long-offline scenarios (bounded queues, storage limits)
- Sync diagnostics and logging (dead-letter, monitoring)

### Dependencies

- Batch 7, Batch 8, Batch 9, Batch 10, Batch 11

### Files / Modules

#### Backend

- `app/Http/Controllers/Api/SyncController`
- `app/Services/SyncService`
- `app/Domains/Sync/` — SyncLog
- `app/Jobs/ProcessSyncPush`

#### Mobile

- `lib/core/sync/` — hardened sync engine (push/pull, retry, conflict, tombstone, recovery)
- `lib/core/sync/diagnostics.dart` — sync diagnostics & status UI

### Verification

- Pull returns changed entities since cursor; push processes creates and updates
- Retries resume after app restart; partial failures don't block other entities
- Idempotency prevents duplicates; conflicts resolved per policy
- Sync logs/telemetry recorded and viewable

### Completion Criteria

Sync engine production-hardened across both repositories.

---

## Batch 13 — Admin Dashboard, Live Map & Mobile Refinement

**Goal:** Build the admin dashboard, live map, and polish the mobile UX.

### Scope

#### Laravel / Web (`field-sales-api`)

- Dashboard controller and views
- KPI cards (today's sales, collections, active salesmen, etc.)
- Sales trend and visit completion charts
- Live salesmen map (Leaflet/MapLibre, auto-refresh polling)
- Status indicators (online/idle/offline), salesman status table
- Role-based dashboard variants

#### Flutter (`field-sales-mobile`)

- Mobile dashboard / home refinement
- Day summary
- Route progress
- Sync UX polish (status, manual trigger, feedback)
- Loading / empty / error states across screens
- General usability improvements

### Dependencies

- Batch 6, Batch 7, Batch 8, Batch 9, Batch 10

### Files / Modules

#### Backend

- `app/Http/Controllers/` — DashboardController
- `app/Http/Controllers/Api/TrackingController` — live locations
- `resources/views/pages/dashboard/`
- `resources/views/pages/tracking/`
- `resources/views/components/ui/map-card.blade.php`
- `resources/js/map.js`

#### Mobile

- `lib/features/home/` — dashboard refinement, day summary, route progress
- `lib/core/sync/ui.dart` — sync status UX

### Verification

- Dashboard loads with KPI data; charts render; live map shows salesman locations
- Map auto-refreshes; role-based views work
- Mobile shows day summary and route progress; sync UX polished
- Loading/empty/error states consistent in the app

### Completion Criteria

Dashboard and live map functional; mobile UX polished.

---

## Batch 14 — Notifications, Alerts, Reporting & Final End-to-End QA

**Goal:** Build notifications, fraud alerts, reporting, and run final end-to-end QA across both repositories.

### Scope

#### Laravel + Flutter (co-built)

- FCM push notifications (backend sends, app receives/renders)
- In-app notifications & preferences
- Suspicious / fraud indicator detection jobs and alerts
- Report generation (sales, visits, GPS, performance), filters, CSV/PDF export
- Complete mobile/backend integration pass

#### Final End-to-End QA

- GPS reliability tests
- Offline / online transition tests
- Background tracking validation
- Sync reliability tests
- Android permission behavior
- Battery / data usage behavior
- Tenant / security regression
- API regression
- Production readiness checklist

### Dependencies

- Batch 8, Batch 9, Batch 10, Batch 11, Batch 12, Batch 13

### Files / Modules

#### Backend

- `app/Domains/Notifications/` — Notification, NotificationTemplate
- `app/Domains/Reporting/` — ReportService
- `app/Domains/Commissions/` — CommissionRule, Commission
- `app/Jobs/SendPushNotification`
- `app/Jobs/DetectFraudIndicators`
- `app/Http/Controllers/` — AlertController, ReportController
- `resources/views/pages/alerts/`
- `resources/views/pages/reports/`

#### Mobile

- `lib/features/notifications/` — FCM handling, notification center
- `lib/features/reports/` — mobile-facing report views (as applicable)

### Verification

- Notifications created/displayed; FCM pushes reach the app
- Fraud indicators detected; alerts page populated
- Reports generate with correct data; CSV export works
- All end-to-end QA scenarios pass (offline/online, GPS, permissions, battery/data)

### Completion Criteria

Notifications, alerts, reporting functional; production readiness confirmed across both repositories.

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
| 4 | Customers, Territories & Routes | Customer master data and territory/route structure | Batch 3 | Medium |
| 5 | Products, Price Lists & Mobile API Prep | Lightweight product/pricing catalog + frozen mobile API contract | Batch 4 | Low |
| 6 | **Flutter Android Foundation & Offline-First Core** | Create `field-sales-mobile`; offline-first mobile foundation (SQLite, local-first writes, UUIDs, sync queue, connectivity, `pending`/`syncing`/`synced`/`failed`) | Batch 2, 3, 5 | **High** |
| 7 | Attendance & GPS Tracking Core | Work sessions + GPS infrastructure, Laravel **and** Flutter | Batch 4, 6 | **High** |
| 8 | Customer Visits & Geofencing | Visit workflow with geofencing, Laravel **and** Flutter | Batch 4, 6, 7 | **High** |
| 9 | Orders | Field order capture (offline-first), Laravel **and** Flutter | Batch 4, 5, 6, 8 | **High** |
| 10 | Collections | Payment collection recording (offline), Laravel **and** Flutter | Batch 4, 6, 8, 9 | Medium |
| 11 | Expenses & Targets | Expense tracking and sales targets, Laravel **and** Flutter | Batch 3, 6, 7 | Medium |
| 12 | Offline Sync Engine Hardening | Harden the Batch 6 offline foundation (push/pull, retries, conflicts, tombstones, recovery) | Batch 7–11 | **High** |
| 13 | Admin Dashboard, Live Map & Mobile Refinement | KPI dashboard, live map, mobile UX polish | Batch 6–10 | **High** |
| 14 | Notifications, Alerts, Reporting & End-to-End QA | FCM push, fraud alerts, reporting, production readiness | Batch 8–13 | **High** |
| 15 | BusinessOS Integration *(optional)* | Integration layer for BusinessOS ERP | Batch 12 | Medium |

---

## Batch Dependency Graph

```
Batch 1  ──► Batch 2  ──► Batch 3 ──┬──► Batch 4 ──► Batch 5 ─────────────────────────────┐
                                     │                                                    │
                                     │                                                    ▼
                                     │                                  ┌──► Batch 6 (Flutter Foundation)
                                     │                                  │        │        │
                                     │                                  │        │        ▼
                                     │                                  │        ├──► Batch 7 (Attendance + GPS)
                                     │                                  │        │        │
                                     │                                  │        │        ▼
                                     │                                  │        ├──► Batch 8 (Visits + Geofence)
                                     │                                  │        │        │
                                     │                                  │        │        ├──► Batch 9 (Orders) ◄── Batch 5
                                     │                                  │        │        │         │
                                     │                                  │        │        │         ▼
                                     │                                  │        │        ├──► Batch 10 (Collections)
                                     │                                  │        │        │         │
                                     │                                  │        │        │         ▼
                                     │                                  │        │        └──► Batch 11 (Expenses + Targets)
                                     │                                  │        │
                                     │                                  │        ▼
                                     │                                  └──► Batch 12 (Sync Hardening) ◄── Batches 7–11
                                     │                                                     │
                                     │                                                     ▼
                                     │                                        Batch 13 (Dashboard + Live Map) ◄── Batches 6–10
                                     │                                                     │
                                     │                                                     ▼
                                     │                                        Batch 14 (Notifications + QA) ◄── Batches 8–13
                                     │                                                     │
                                     │                                                     ▼
                                     │                                        Batch 15 (Optional) ◄── Batch 12
                                     ▼
                           (Flutter app shell only exists from Batch 6)
```

**Read the graph left-to-right:** A batch can only start once every batch pointing to it has been completed. From Batch 6 onward, batch completion requires both `field-sales-api` (backend) and `field-sales-mobile` (Flutter) work where the batch defines mobile scope.

---

## Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| **GPS volume scalability** — High-frequency GPS uploads from many devices can overwhelm storage and queries. | High | Batch GPS uploads, Redis caching for latest positions, aggressive MySQL indexing, consider TimescaleDB for time-series if volume exceeds expectations. |
| **Cross-project Laravel/Flutter coordination** — Backend API contracts and mobile implementation must remain synchronized; a drift between the API and the app blocks mobile batches. | High | Contract-first development (frozen versioned contract in Batch 5 / `docs/API_CONTRACT.md`), co-defined batch scope with explicit backend + mobile sections, end-to-end integration checks per batch, and shared acceptance criteria. |
| **Offline sync reliability** — Conflict resolution and idempotency are notoriously hard to get right. | High | Offline foundation established early (Batch 6) and hardened incrementally; thorough testing with simulated offline/online cycles, tombstone records, sync cursors, and explicit conflict resolution policies in Batch 12. |
| **Multi-tenant data isolation bugs** — Cross-tenant data leaks are critical security issues. | High | Global Eloquent scopes enforced at every layer with fail-closed behavior, comprehensive tenant-scoped tests, middleware on every API route, and row-level database checks. |
| **BusinessOS API availability** — External ERP API may be unreliable, rate-limited, or poorly documented. | Medium | Adapter pattern for easy swap, retry logic with exponential backoff, manual sync fallback, and sync monitoring. |
| **Map tile provider reliability in Afghanistan** — Map data quality and tile availability may be inconsistent. | Medium | Evaluate multiple providers (OSM, Mapbox), cache tiles locally where possible, and ensure graceful degradation. |
| **Android background execution constraints** — Aggressive Android battery optimizations (Doze, background limits) can break background GPS and sync. | High | Foreground service with persistent notification, configurable intervals, battery/network metadata, and explicit testing of Doze/App Standby in Batch 7 and Batch 14. |

---

## Unresolved Decisions

| Decision | Context | Status |
|----------|---------|--------|
| **Livewire vs pure Blade** | Admin panel interactivity requirements not yet clear. | **Resolved** — pure Blade + Tailwind 4 + Alpine (batch 1 ships this) |
| **Specific chart library** | Chart.js is a candidate but alternatives (ApexCharts, Chartist) not evaluated. | Pending benchmark |
| **Map tile provider for Afghanistan** | OpenStreetMap vs Mapbox — data quality and availability in Afghanistan unknown. | Pending provider evaluation |
| **Redis driver choice** | Predis (PHP) vs PhpRedis (C extension) — performance and deployment trade-offs. | Pending load testing |
| **Push notification package** | Laravel Notification Channels ecosystem has multiple FCM packages. | Pending evaluation |
| **Flutter local DB layer** | SQLite via drift vs sqflite vs floor for the Batch 6 foundation. | Pending evaluation in Batch 6 |