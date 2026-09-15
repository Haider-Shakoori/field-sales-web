# Field Sales SaaS — Architecture Document

> **Status:** Planning  
> **Stack:** Laravel 13 · PHP 8.5 · MySQL 8 · Redis · Flutter · Blade + Tailwind CSS 4 + Alpine.js

---

## 1. System Overview

A multi-tenant SaaS platform for managing field sales teams in Afghanistan. The system handles team management, GPS tracking, customer visits, order collection, payment collection, expense reporting, and commission calculation.

### Components

| Component | Technology | Role |
|---|---|---|
| API Backend | Laravel 13, PHP 8.5 | REST API, business logic, queue processing |
| Admin Panel | Blade + Tailwind CSS 4 + Alpine.js | Web dashboard, reports, configuration |
| Mobile App | Flutter (Android) | Field data capture, GPS, offline sync |
| Database | MySQL 8 | Persistent storage, single shared schema |
| Cache / Queue | Redis | Caching, queues, pub/sub, live locations |
| Push Notifications | Firebase Cloud Messaging | Mobile push delivery |
| File Storage | Local (V1), S3-compatible (future) | Images, receipts, documents |
| Map Provider | Leaflet / OSM (V1) | Geocoding, map tiles, route display |

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          INTERNET                                   │
└──────────┬──────────────────────────┬───────────────────────────────┘
           │                          │
           ▼                          ▼
┌──────────────────┐       ┌──────────────────┐
│   Flutter App    │       │   Admin Panel    │
│   (Android)      │       │   (Blade/Tail/   │
│                  │       │    Alpine.js)    │
│  ┌────────────┐  │       │  ┌────────────┐  │
│  │ SQLite     │  │       │  │ Vite       │  │
│  │ (offline)  │  │       │  │ Assets     │  │
│  └────────────┘  │       │  └────────────┘  │
└────────┬─────────┘       └────────┬─────────┘
         │  REST API (JSON)         │  REST API (JSON)
         │  + Offline Sync          │  + AJAX
         ▼                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Laravel API Backend                              │
│                                                                     │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │ Auth     │ │ Domain   │ │ Queue    │ │ Events   │ │ Scheduler│ │
│  │ Sanctum  │ │ Modules  │ │ Workers  │ │ /Listeners│ │ Console  │ │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘ │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                  Domain Modules (see §3)                     │   │
│  │  Tenancy · Identity · SalesTeam · Customers · Territories   │   │
│  │  Tracking · Attendance · Visits · Orders · Collections      │   │
│  │  Targets · Expenses · Commissions · Notifications · Reports │   │
│  └──────────────────────────────────────────────────────────────┘   │
└──────────┬──────────────┬──────────────┬──────────────┬────────────┘
           │              │              │              │
           ▼              ▼              ▼              ▼
    ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────────┐
    │   MySQL 8  │ │   Redis    │ │  Firebase  │ │  File Storage  │
    │            │ │            │ │   (FCM)    │ │  Local → S3    │
    │ - Schema   │ │ - Cache    │ │            │ │                │
    │ - InnoDB   │ │ - Queues   │ │  Push      │ │  Tenant-scoped │
    │ - JSON     │ │ - Pub/Sub  │ │  Notifs    │ │  paths         │
    └────────────┘ └────────────┘ └────────────┘ └────────────────┘
```

---

## 2. Multi-Tenancy Architecture

### Decision: Shared Database with `tenant_id`

**Rationale:**

- **Operational simplicity:** One database to backup, migrate, monitor, and debug.
- **Cost efficiency:** Appropriate for initial user base in the Afghanistan market.
- **Laravel fit:** Global scopes make tenant-scoped queries straightforward.
- **Migration path:** Can evolve to database-per-tenant later using similar scoping patterns.
- **Scale:** Afghanistan market initial scale does not justify per-tenant database isolation.
- **Security:** Row-level security is sufficient when scope enforcement is consistent.

### Implementation Plan

- Every business table includes a `tenant_id` foreign key.
- Global Eloquent scope on all tenant-owned models filters every query by the active tenant.
- Tenant context is **fail-closed** (three states):
  - **Tenant** — a concrete tenant is active; the scope appends `WHERE tenant_id = ?`.
  - **Platform (explicit bypass)** — entered only through guarded APIs (`enterPlatformForUser` for the HTTP super admin, `enterSystemContext` for trusted seeders/console/auth bootstrap); company users are rejected.
  - **Uninitialized** — no context; accessing a tenant-owned model throws `TenantContextMissingException` instead of silently escaping isolation.
- Middleware resolves the current tenant from the authenticated user (guest requests run in the Uninitialized state; platform super admins run in the explicit Platform state).
- All database queries execute within a tenant context or an explicit platform/system context — unscoped queries are impossible outside a deliberate bypass.
- Queued jobs must establish their tenant context explicitly before touching tenant-owned models (a job receiving `tenant_id` resolves and tries the tenant, or opts into an explicit platform/system context); the fail-closed scope refuses to infer one.
- File storage paths are prefixed by `tenant_id`.
- Cache keys include `tenant_id` for isolation.

### Tenant Isolation Rules

| Rule | Description |
|---|---|
| Query scoping | All tenant-owned queries are automatically scoped to the active tenant via global scopes |
| **Fail-closed scope** | Missing context **throws** (`TenantContextMissingException`); it never silently runs unscoped |
| API resolution | Tenant is resolved from the Sanctum auth token on every API request |
| Platform bypass | Only `isSuperAdmin` users can enter the explicit Platform context; company users are rejected |
| Admin access | Admin users may access multiple tenants with explicit tenant selection |
| Cross-tenant | Cross-tenant data access is strictly prohibited |
| Audit logging | All mutations capture tenant context in audit trails |
| File isolation | File storage paths are partitioned by `tenant_id` |
| Cache isolation | Cache keys include `tenant_id` prefix |
| Queue isolation | Jobs carry `tenant_id` and must initialize their tenant/system context before querying |

---

## 3. Application Architecture (Modular Monolith)

### Domain Modules

```
app/
├── Domains/
│   ├── Tenancy/          # Company, Branch, Tenant configuration
│   ├── Identity/         # Users, Roles, Permissions, Auth
│   ├── Devices/          # Device registration, management, heartbeat
│   ├── SalesTeam/        # Salesman profiles, team hierarchy, assignments
│   ├── Customers/        # Customer master data, contacts, locations
│   ├── Territories/      # Territories, Routes, Beat plans
│   ├── Tracking/         # GPS locations, live map, location history
│   ├── Attendance/       # Work sessions, check-in/out, daily attendance
│   ├── Visits/           # Customer visits, check-in/out, visit outcomes
│   ├── Orders/           # Sales orders, order items, order status
│   ├── Collections/      # Payment collections, receipts
│   ├── Targets/          # Sales targets, achievement tracking
│   ├── Expenses/         # Field expenses, receipt capture, approval
│   ├── Commissions/      # Commission rules and calculations
│   ├── Products/         # Product catalog, pricing (lightweight V1)
│   ├── Notifications/    # Push notifications, database notifications, future email
│   ├── Reporting/        # Reports, analytics, exports
│   └── Integrations/     # BusinessOS connector boundary
├── Http/
│   ├── Controllers/      # HTTP concerns only — routing, request/response
│   ├── Requests/         # Form request validation classes
│   └── Resources/        # API resource transformers
├── Models/               # Eloquent models (may also live in domain modules)
├── Jobs/                 # Queued jobs
├── Events/               # Domain events
├── Listeners/            # Event handlers
├── Policies/             # Authorization policies
└── Services/             # Shared service classes
```

### Module Responsibilities

| Module | Responsibility |
|---|---|
| **Tenancy** | Company profile, branch management, tenant configuration, subscription/billing boundaries |
| **Identity** | User CRUD, authentication, role and permission management, password reset |
| **Devices** | Device registration, heartbeat tracking, token binding, device revocation |
| **SalesTeam** | Salesman profiles, hierarchy (manager → supervisor → salesman), territory assignment |
| **Customers** | Customer master data, contact info, GPS coordinates, assignment to salesmen |
| **Territories** | Geographic territory definitions, route planning, beat schedules |
| **Tracking** | GPS point ingestion, location history, live location cache, stale detection |
| **Attendance** | Daily work session start/end, attendance records, work hour calculation |
| **Visits** | Visit check-in/out, visit outcomes, visit photos, visit validation |
| **Orders** | Sales order creation, order items, order status workflow, order history |
| **Collections** | Payment collection recording, receipt generation, collection reconciliation |
| **Targets** | Target definition (per salesman, per team), achievement calculation, progress tracking |
| **Expenses** | Expense submission, receipt photo capture, approval workflow |
| **Commissions** | Commission rule configuration, calculation engine, payout tracking |
| **Products** | Product catalog, pricing tiers, product categories (lightweight for V1) |
| **Notifications** | Push notification delivery via FCM, database notifications, in-app notification center |
| **Reporting** | Report generation, data aggregation, CSV/Excel exports, scheduled reports |
| **Integrations** | BusinessOS connector, external ERP adapters, sync boundary |

### Layer Responsibilities

| Layer | Responsibility |
|---|---|
| **Controllers** | HTTP routing, request deserialization, response serialization, delegation to services |
| **Form Requests** | Input validation, authorization checks, request preprocessing |
| **Services / Actions** | Business logic, orchestration, cross-module coordination |
| **Models** | Eloquent relationships, scopes, accessors, mutators, model state |
| **Jobs** | Async processing, queue-based work, retry logic |
| **Events / Listeners** | Decoupled side effects, cross-module communication, audit logging |
| **Policies** | Permission checks, authorization rules per entity |
| **API Resources** | Response transformation, data shaping, nested resource inclusion |

---

## 4. API Architecture

### REST API Design Principles

- **Versioned:** All endpoints under `/api/v1/...`
- **Authentication:** Laravel Sanctum personal access tokens
- **Content type:** JSON request/response
- **Envelope:** Consistent response wrapper (see below)
- **Pagination:** Page-number pagination with configurable per-page
- **Rate limiting:** Per-device and per-user limits
- **Idempotency:** Idempotency key support for offline sync endpoints

### API Endpoint Groups

| Group | Endpoints | Description |
|---|---|---|
| **Auth** | `POST /login`, `POST /logout`, `POST /refresh`, `POST /password/reset` | Authentication flow |
| **Profile** | `GET /profile`, `PUT /profile` | Current user profile |
| **Devices** | `POST /devices`, `GET /devices`, `DELETE /devices/{id}` | Device registration and management |
| **Sync** | `POST /sync/pull`, `POST /sync/push`, `POST /sync/gps` | Offline data synchronization |
| **Attendance** | `POST /attendance/start`, `POST /attendance/end`, `GET /attendance/today` | Work session management |
| **GPS** | `POST /gps/locations` | Bulk GPS location upload |
| **Customers** | `GET /customers`, `POST /customers`, `GET /customers/{id}`, `PUT /customers/{id}` | Customer CRUD |
| **Routes** | `GET /routes`, `GET /routes/{id}` | Assigned routes and beat plans |
| **Visits** | `POST /visits`, `GET /visits`, `PUT /visits/{id}` | Visit check-in/out and listing |
| **Orders** | `POST /orders`, `GET /orders`, `GET /orders/{id}` | Sales order management |
| **Collections** | `POST /collections`, `GET /collections` | Payment collection recording |
| **Targets** | `GET /targets`, `GET /targets/{id}/achievement` | Target listing and progress |
| **Expenses** | `POST /expenses`, `GET /expenses`, `PUT /expenses/{id}` | Expense submission and tracking |
| **Notifications** | `GET /notifications`, `PUT /notifications/{id}/read` | Notification center |

### Response Envelope

All successful responses follow a consistent envelope:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 100
  },
  "message": "optional human-readable message"
}
```

- `data` contains the primary response payload (object, array, or null).
- `meta` is included only on list/paginated responses.
- `message` is optional and used for success confirmations.

### Error Response Format

All error responses follow a consistent structure:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "field_name": ["Error message for this field."]
    }
  }
}
```

- `code` is a machine-readable error identifier (e.g., `VALIDATION_ERROR`, `UNAUTHORIZED`, `NOT_FOUND`).
- `message` is a human-readable description.
- `details` is included for validation errors with per-field messages.

### HTTP Status Codes

| Code | Usage |
|---|---|
| `200` | Successful read/update |
| `201` | Successful creation |
| `204` | Successful deletion (no content) |
| `400` | Bad request / malformed input |
| `401` | Unauthenticated / invalid token |
| `403` | Unauthorized / insufficient permissions |
| `404` | Resource not found |
| `422` | Validation error |
| `429` | Rate limit exceeded |
| `500` | Server error |

---

## 5. Web Admin Panel Architecture

### Technology Stack

- **Templates:** Blade (server-rendered)
- **Styling:** Tailwind CSS 4
- **Interactivity:** Alpine.js (no React, Vue, or Livewire)
- **Build:** Vite
- **Dynamic updates:** AJAX / fetch API for partial page updates

### Component Architecture

```
resources/views/
├── components/
│   ├── layout/
│   │   ├── app-shell.blade.php       # Main layout wrapper
│   │   ├── sidebar.blade.php         # Collapsible sidebar navigation
│   │   ├── topbar.blade.php          # Top bar with search, notifications, user menu
│   │   └── responsive-nav.blade.php  # Mobile hamburger menu
│   ├── ui/
│   │   ├── stat-card.blade.php       # KPI metric card
│   │   ├── data-table.blade.php      # Sortable, filterable table
│   │   ├── filter-bar.blade.php      # Search and filter controls
│   │   ├── status-badge.blade.php    # Colored status indicators
│   │   ├── modal.blade.php           # Dialog modal
│   │   ├── drawer.blade.php          # Slide-out panel
│   │   ├── toast.blade.php           # Flash notification toast
│   │   ├── empty-state.blade.php     # Empty state placeholder
│   │   ├── loading-state.blade.php   # Loading spinner/skeleton
│   │   ├── pagination.blade.php      # Page navigation
│   │   ├── dropdown.blade.php        # Dropdown menu
│   │   └── confirm-dialog.blade.php  # Confirmation modal
│   └── partials/
│       └── ... (page-specific partials)
├── pages/
│   ├── dashboard/
│   │   ├── index.blade.php           # Main dashboard
│   │   └── components/
│   │       ├── kpi-cards.blade.php
│   │       ├── live-map.blade.php
│   │       └── recent-activity.blade.php
│   ├── customers/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   ├── salesmen/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   ├── attendance/
│   ├── visits/
│   ├── orders/
│   ├── collections/
│   ├── targets/
│   ├── expenses/
│   ├── reports/
│   ├── territories/
│   ├── products/
│   ├── notifications/
│   └── settings/
└── layouts/
    └── app.blade.php                 # Base HTML layout
```

### Navigation Structure

```
┌──────────────────────────────────────────────────────┐
│ ☰  Search...                    🔔  👤 Admin ▾     │  ← Top bar
├────────┬─────────────────────────────────────────────┤
│ 🏠 Dash │                                             │
│ 👥 Team │          Page Content Area                  │
│ 📍 GPS  │                                             │
│ 🏪 Cust │                                             │
│ 🗺 Terr │                                             │
│ 📋 Visit│                                             │
│ 📦 Order│                                             │
│ 💰 Col  │                                             │
│ 🎯 Targ │                                             │
│ 💸 Exp  │                                             │
│ 📊 Rpt  │                                             │
│ ⚙ Set  │                                             │
└────────┴─────────────────────────────────────────────┘
  ↑ Sidebar (collapsible: full → icons → hidden)
```

- **Sidebar:** Collapsible with icon + label. On tablet: icons only. On mobile: hidden behind hamburger.
- **Top bar:** Global search, notification bell with count badge, user avatar with dropdown menu.
- **Role-based:** Menu items filtered by user role and permissions.

### Dashboard Role Variants

| Role | Dashboard View |
|---|---|
| **Owner** | Company-wide KPIs, revenue trends, active alerts, live map, team performance comparison |
| **Sales Manager** | Team metrics, target achievement, order/collection summaries, pending approvals |
| **Supervisor** | Assigned salesmen status, today's visits, route adherence, team attendance |
| **Read-only** | View-only version of role-appropriate data, no action buttons |

---

## 6. Mobile App Architecture (Flutter)

### Offline-First Design

The Flutter app is **offline-first from its initial implementation**. It is designed for unreliable connectivity in Afghanistan: all critical data operations work offline with background synchronization.

**Core principles (ship with the initial Android release — the Offline-First Foundation):**
- Local SQLite database mirrors relevant server data
- Local-first write strategy — the device is the source of truth while offline (with local SQLite)
- Client-generated UUIDs for every offline-created entity (no server round-trip needed)
- Basic sync queue (outbox) with a simple connectivity check
- Per-record sync states: `pending` / `synced` / `failed`

**Deferred to the later Sync Engine phase (advanced sync):**
- Advanced conflict resolution (server-authoritative merge policies, tombstones, cursor/checkpoint management)
- Retry/backoff, bulk synchronization, bulk GPS upload optimization, duplicate handling and recovery
- Sync hardening (logging, monitoring, dead-letter handling)

### Local SQLite Entities

| Entity | Purpose |
|---|---|
| Auth session | Current user token, refresh token, expiry |
| User / tenant info | Current user profile and company context |
| Assigned customers | Customer list with GPS coordinates for offline visit |
| Products and prices | Product catalog for offline order creation |
| Routes and visit plans | Today's assigned routes and beat plans |
| Visit records | Visit records created offline (with local UUID) |
| Orders and order items | Orders created offline (with local UUID) |
| Collections | Payment collections recorded offline |
| Expenses | Expense submissions with receipt photos |
| GPS points | Location points queued for upload |
| Photos metadata | Photo references pending server upload |
| Sync queue and cursor | Outbox for pending mutations, last sync timestamp |

### Sync Protocol

```
┌──────────────┐                    ┌──────────────┐
│  Flutter App │                    │   Laravel    │
│  (SQLite)    │                    │   API        │
└──────┬───────┘                    └──────┬───────┘
       │                                    │
       │  1. POST /api/v1/sync/pull         │
       │  { last_sync: "2026-01-15T10:00Z" }│
       │  ─────────────────────────────────► │
       │                                    │
       │  2. Server returns changes since   │
       │  ◄────────────────────────────────  │
       │  { customers: [...], orders: [...] }│
       │                                    │
       │  3. POST /api/v1/sync/push         │
       │  {                                 │
       │    visits: [{uuid, ...}],          │
       │    orders: [{uuid, ...}],          │
       │    collections: [{uuid, ...}],     │
       │    expenses: [{uuid, ...}]         │
       │  }                                 │
       │  ─────────────────────────────────► │
       │                                    │
       │  4. Server processes, returns      │
       │     per-record success/failure     │
       │  ◄────────────────────────────────  │
       │                                    │
       │  5. POST /api/v1/sync/gps          │
       │  { points: [{uuid, lat, lng, ts}] }│
       │  ─────────────────────────────────► │
       │                                    │
```

**Sync rules:**
- **Pull:** Client sends last sync timestamp; server returns all changes since.
- **Push:** Client sends batched creates/updates with client-generated UUIDs.
- **GPS:** Dedicated bulk upload endpoint for location points.
- **Idempotent:** Same UUID submitted twice produces no duplicate.
- **Retry:** Exponential backoff on failure; partial failure reported per record.
- **Conflict:** Server values win for shared fields; client values win for device-specific fields (GPS, timestamps).

---

## 7. GPS Tracking Architecture

### Storage Design

| Table | Purpose | Characteristics |
|---|---|---|
| `current_locations` | One row per salesman | Updated on each GPS point, indexed by `user_id` |
| `location_history` | Append-only log | All GPS points, indexed on `(tenant_id, user_id, recorded_at)` |

### Collection Flow

```
Flutter App                API Server               Storage
───────────                ──────────               ───────
GPS sensor fires
  │
  ▼
Store in local SQLite
  │
  ▼ (batch every 50-100)
POST /api/v1/gps/locations
  │
  ▼
Validate & transform
  │
  ├──► INSERT location_history (batch)
  │
  ├──► UPSERT current_locations
  │
  └──► SET Redis: fs:{tenant}:location:{user}
         with TTL 5 minutes
```

1. Flutter app collects GPS at configured intervals (e.g., every 30–60 seconds) during active work sessions.
2. Points are stored in local SQLite with upload-pending status.
3. On connectivity, points are batched (50–100 per request) and uploaded to `POST /api/v1/gps/locations`.
4. Server validates coordinates, timestamps, and tenant context.
5. Points are bulk-inserted into `location_history`.
6. `current_locations` is upserted with the latest point per salesman.
7. Redis cache is updated with the latest location for live map queries.

### Map Provider Abstraction

The domain layer references a `MapProvider` interface — never a specific implementation.

```
App\Domains\Tracking\Contracts\MapProvider
    ├── getCoordinates(string $address): array
    ├── getRoute(array $points): array
    └── getDistance(array $from, array $to): float

App\Domains\Tracking\Providers\OpenStreetMapProvider (V1)
App\Domains\Tracking\Providers\GoogleMapsProvider (future)
App\Domains\Tracking\Providers\MapboxProvider (future)
```

### Scale Considerations

- `location_history` can be partitioned by month when data volume grows.
- Old locations are archived or purged after a configurable retention period (e.g., 90 days).
- Composite index on `(tenant_id, user_id, recorded_at)` for efficient queries.
- Batch inserts with `insert(...)` for high-throughput writes.

---

## 8. Realtime / Live Map Architecture

### Strategy

**V1: Polling** — Admin frontend polls the server every N seconds for latest locations.

**Future: WebSockets** — Laravel Reverb or Soketi for true push updates.

### Data Flow

```
┌─────────────┐     AJAX poll (10-30s)     ┌─────────────┐
│  Admin      │  ──────────────────────►   │  Laravel    │
│  Live Map   │                            │  API        │
│  (Alpine.js)│  ◄──────────────────────   │             │
│             │     [{ user, lat, lng }]   │             │
└─────────────┘                            └──────┬──────┘
                                                  │
                                           ┌──────▼──────┐
                                           │    Redis     │
                                           │  (cache hit) │
                                           └──────┬──────┘
                                                  │ fallback
                                           ┌──────▼──────┐
                                           │   current_   │
                                           │  locations   │
                                           │  (DB)        │
                                           └─────────────┘
```

1. Admin opens live map page.
2. Alpine.js component initiates polling every N seconds (configurable, default 15s).
3. `GET /api/v1/tracking/live` controller reads from Redis `fs:{tenant}:location:*` keys.
4. If cache miss, falls back to `current_locations` table.
5. Returns array of salesman locations with metadata and status.

### Status Indicators

| Status | Condition | Visual |
|---|---|---|
| **Online** | GPS received within last 5 minutes | 🟢 Green dot |
| **Idle** | GPS received within last 15 minutes | 🟡 Yellow dot |
| **Offline** | No GPS for > 15 minutes | 🔴 Red dot |
| **Stale** | Location older than configured threshold | ⚫ Gray dot |

### Live Map Page Structure

```html
<div x-data="liveMap({ refreshInterval: 15000 })">
  <!-- Map container (Leaflet/OpenStreetMap) -->
  <div id="map"></div>

  <!-- Sidebar: salesman list with status -->
  <div class="salesman-list">
    <!-- Online / Idle / Offline sections -->
    <!-- Click to center map on salesman -->
  </div>

  <!-- Controls: refresh interval, filter by team -->
  <div class="map-controls"></div>
</div>
```

---

## 9. File Storage Architecture

### Strategy

- Laravel storage disk abstraction (`storage/app`).
- **V1:** Local filesystem.
- **Future:** S3-compatible object storage (AWS S3, MinIO, DigitalOcean Spaces).
- Files are never stored as BLOBs in MySQL.

### Storage Paths

```
storage/app/private/
├── {tenant_id}/
│   ├── customers/
│   │   └── {uuid}.jpg
│   ├── expenses/
│   │   └── {receipt_uuid}.jpg
│   ├── visits/
│   │   └── {visit_uuid}/
│   │       ├── {photo_uuid}.jpg
│   │       └── {photo_uuid_2}.jpg
│   ├── companies/
│   │   └── logo.{ext}
│   └── salesmen/
│       └── {uuid}.jpg
```

### Disk Configuration

```php
// config/filesystems.php
'disks' => [
    'private' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'visibility' => 'private',
    ],
    // Future: S3
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],
```

### Security

- **Access control:** Signed URLs with expiration for file access.
- **Validation:** Image MIME type and size validation on upload.
- **Size limits:** Configurable per entity type (e.g., 5MB for receipts, 10MB for photos).
- **Tenant isolation:** File access is validated against the requesting user's tenant.
- **No direct access:** Files served through a controller, not directly from disk.

---

## 10. Queue & Job Architecture

### Queue Configuration

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

'connections' => [
    'gps' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'gps',
        'retry_after' => 120,
    ],
],

'connections' => [
    'notifications' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'notifications',
        'retry_after' => 60,
    ],
],
```

Three named queues prevent starvation:
- `default` — General business logic jobs.
- `gps` — High-volume GPS processing (isolated so it doesn't block other work).
- `notifications` — Push notification delivery.

### Key Jobs

| Job | Queue | Description |
|---|---|---|
| `ProcessLocationBatch` | `gps` | Bulk insert GPS points, update current locations |
| `SyncPushData` | `default` | Process mobile sync uploads |
| `CalculateTargetAchievement` | `default` | Update target progress for a salesman/team |
| `GenerateReport` | `default` | Async report generation with CSV/Excel export |
| `SendPushNotification` | `notifications` | Deliver FCM push notification to device |
| `CheckStaleDevices` | `default` | Detect devices that haven't sent heartbeat |
| `ArchiveOldLocations` | `gps` | Monthly cleanup of old location history |
| `ProcessVisitPhotos` | `default` | Resize/optimize uploaded visit photos |

### Scheduler Tasks

| Schedule | Command/Job | Description |
|---|---|---|
| Every 5 minutes | `CheckStaleDevices` | Detect offline/lost devices |
| Daily at 00:30 | `CalculateTargetAchievement` | Recalculate all target achievements |
| Monthly (1st) | `ArchiveOldLocations` | Archive/purge location history beyond retention |
| Daily at 06:00 | `GenerateReport` (daily summary) | Generate and cache daily summary reports |
| Every 15 minutes | `CheckAttendanceAlerts` | Alert if salesmen haven't started day |

---

## 11. Event Architecture

### Key Domain Events

| Event | Triggered By | Listeners |
|---|---|---|
| `UserLoggedIn` | Auth controller | `UpdateUserLastActivity`, `LogAuditEntry` |
| `UserLoggedOut` | Auth controller | `LogAuditEntry` |
| `DeviceRegistered` | Device controller | `SendPushNotification`, `LogAuditEntry` |
| `DeviceRevoked` | Device controller | `LogAuditEntry` |
| `AttendanceStarted` | Attendance service | `LogAuditEntry`, `NotifySupervisor` |
| `AttendanceEnded` | Attendance service | `LogAuditEntry`, `CalculateWorkHours` |
| `VisitCheckedIn` | Visit service | `LogAuditEntry`, `NotifySupervisor` |
| `VisitCheckedOut` | Visit service | `UpdateVisitStats`, `LogAuditEntry` |
| `OrderSubmitted` | Order service | `SendPushNotification`, `LogAuditEntry` |
| `OrderApproved` | Order service | `NotifySalesman`, `UpdateDashboardStats` |
| `OrderDelivered` | Order service | `UpdateDashboardStats`, `LogAuditEntry` |
| `CollectionRecorded` | Collection service | `UpdateDashboardStats`, `LogAuditEntry` |
| `ExpenseSubmitted` | Expense service | `SendPushNotification`, `NotifyApprover` |
| `ExpenseApproved` | Expense service | `NotifySalesman`, `LogAuditEntry` |
| `ExpenseRejected` | Expense service | `NotifySalesman`, `LogAuditEntry` |
| `TargetAchieved` | Target service | `SendPushNotification`, `LogAuditEntry` |
| `SuspiciousActivityDetected` | Fraud service | `SendPushNotification`, `LogAuditEntry` |
| `SyncCompleted` | Sync service | `LogAuditEntry` |

### Event-Driven Patterns

- **Cross-module communication:** Events decouple domain modules (e.g., Visits module doesn't directly call Notifications module).
- **Side effects:** Audit logging, notifications, and dashboard stat updates are event listeners — not inline code.
- **Async listeners:** Heavy listeners (report generation, push notification) dispatch jobs instead of running synchronously.
- **Failed listener handling:** Listener failures are logged and retried via the queue; they don't roll back the triggering mutation.

---

## 12. Caching Strategy

### Redis Usage

| Cache Target | Key Pattern | TTL | Invalidation |
|---|---|---|---|
| Latest salesman location | `fs:{tenant_id}:location:{user_id}` | 5 minutes | On each GPS update |
| Dashboard KPIs | `fs:{tenant_id}:dashboard:{period}` | 5 minutes | On relevant data change or TTL |
| User permissions | `fs:{tenant_id}:permissions:{user_id}` | 15 minutes | On role/permission change |
| Tenant configuration | `fs:config:{tenant_id}` | 1 hour | On config update |
| Rate limiting | `fs:rate:{identifier}` | 1 minute | Automatic sliding window |
| Session storage | Laravel session driver | Per session | On logout / expiry |

### Cache Key Convention

All cache keys follow the pattern: `fs:{tenant_id}:{category}:{identifier}`

```
fs:1:location:42              → Tenant 1, Salesman 42's latest location
fs:1:dashboard:daily          → Tenant 1, daily dashboard KPIs
fs:1:permissions:42           → Tenant 1, Salesman 42's permission set
fs:config:1                   → Tenant 1, configuration
fs:rate:device:abc123         → Rate limit for device token abc123
```

### Cache Invalidation Strategy

- **Write-through:** Dashboard KPI cache is invalidated when relevant mutations occur (order placed, collection recorded, etc.).
- **TTL-based:** All caches have maximum TTL as a safety net.
- **Event-driven:** Permission cache is invalidated when roles/permissions change.
- **Location cache:** Naturally expires via short TTL (5 minutes); overwritten on each GPS update.

---

## 13. Security Architecture

### Authentication

| Aspect | Implementation |
|---|---|
| **Token type** | Laravel Sanctum personal access tokens |
| **Token binding** | One token per device, stored with device metadata |
| **Token abilities** | Scoped to device role (e.g., `salesman`, `supervisor`) |
| **Token expiry** | Configurable TTL; refresh token flow for mobile |
| **Forced logout** | Token revocation on password change, device revocation, or admin action |
| **Device fingerprint** | Device hardware ID + app version for token binding |

### Authorization

| Aspect | Implementation |
|---|---|
| **Model** | Role-Based Access Control (RBAC) |
| **Permission format** | `resource:action` strings (e.g., `customers:view`, `orders:create`) |
| **Roles** | Owner, Sales Manager, Supervisor, Salesman, Read-only |
| **Role storage (source of truth)** | `model_has_roles` (tenant-scoped pivot). `users.role` is a **denormalized mirror** maintained automatically for display/convenience — authorization never reads it |
| **Policies** | One policy per domain entity, registered in `AuthServiceProvider` |
| **Branch scoping** | Managers and supervisors are scoped to their branch's data |
| **Tenant scoping** | All roles are scoped to a single tenant |

### Role Permission Matrix

| Resource | Owner | Manager | Supervisor | Salesman | Read-only |
|---|---|---|---|---|---|
| Dashboard | Full | Team | Branch | Self | View |
| Customers | Full | Team | Branch | Assigned | View |
| Orders | Full | Team | Branch | Create | View |
| Collections | Full | Team | Branch | Create | View |
| Attendance | Full | Team | Branch | Self | View |
| Expenses | Full | Approve | Approve | Submit | View |
| Targets | Full | Team | Branch | View | View |
| Reports | Full | Team | Branch | None | View |
| Settings | Full | Limited | None | None | View |
| Users | Full | Limited | None | None | View |

### API Security

- **Rate limiting:** 60 requests/minute per device (configurable per endpoint).
- **Input validation:** Every endpoint uses Form Request validation.
- **Mass assignment protection:** `$fillable` or `$guarded` on all models.
- **SQL injection:** Prevented via Eloquent query builder and parameterized queries.
- **XSS prevention:** JSON API responses (no HTML rendering in API).
- **CSRF:** Not applicable to API (token-authenticated); admin panel uses standard Laravel CSRF.
- **HTTPS:** Enforced in production via middleware.

---

## 14. Internationalization Architecture

### V1 Approach

- **Primary language:** English.
- **Database translations:** Key entity names (products, categories) stored with translation columns or a translations table.
- **UI strings:** English with Laravel lang file structure ready for future translation.
- **Lang files:** `lang/en/`, `lang/fa/` (Dari), `lang/ps/` (Pashto) directories prepared.

### Translation File Structure

```
lang/
├── en/
│   ├── auth.php
│   ├── dashboard.php
│   ├── customers.php
│   ├── orders.php
│   └── ...
├── fa/
│   ├── auth.php
│   ├── dashboard.php
│   └── ...
└── ps/
    ├── auth.php
    └── ...
```

### Future Enhancements

| Feature | Approach |
|---|---|
| User locale preference | `locale` column on `users` table |
| Company default locale | `default_locale` on company config |
| RTL support | Tailwind `rtl:` variants for layout direction |
| Number formatting | PHP `NumberFormatter` per locale |
| Date formatting | Carbon `isoFormat()` per locale |
| Currency display | Locale-aware currency formatting |

---

## 15. Currency & Timezone Architecture

### Currencies

| Aspect | Implementation |
|---|---|
| **Configuration** | Per-company `currency` setting (e.g., `AFN`, `USD`, `PKR`) |
| **Storage** | Currency code string + integer/decimal amount |
| **V1 scope** | No money library; simple arithmetic with rounding |
| **Display** | Format based on currency code (e.g., ` AFN 1,250` or `$45.00`) |
| **Multi-currency** | Not supported in V1; single currency per company |

### Supported Currencies (V1)

| Code | Name | Symbol |
|---|---|---|
| `AFN` | Afghan Afghani | ؋ |
| `USD` | US Dollar | $ |
| `PKR` | Pakistani Rupee | ₨ |

### Timezones

| Aspect | Implementation |
|---|---|
| **Database** | All timestamps stored in UTC |
| **Company config** | `timezone` column (e.g., `Asia/Kabul`, UTC+4:30) |
| **API responses** | Always UTC timestamps (ISO 8601 format) |
| **Client display** | Mobile app and admin panel convert UTC to local timezone |
| **Offline records** | Mobile records include local time + timezone offset |
| **Scheduler** | Runs in UTC; company-specific scheduling uses timezone conversion |

### Timestamp Convention

```json
{
  "created_at": "2026-01-15T10:30:00Z",
  "updated_at": "2026-01-15T10:30:00Z"
}
```

Always ISO 8601 with `Z` suffix (UTC). Client responsible for timezone conversion.

---

## 16. Deployment Architecture

### V1 Deployment

```
┌─────────────────────────────────────────┐
│           Single Server                 │
│                                         │
│  ┌─────────────┐  ┌─────────────────┐  │
│  │   Nginx     │  │   PHP-FPM       │  │
│  │   (reverse  │──│   (Laravel 13)  │  │
│  │    proxy)   │  │                 │  │
│  └──────┬──────┘  └────────┬────────┘  │
│         │                  │            │
│  ┌──────▼──────────────────▼────────┐  │
│  │           Filesystem              │  │
│  │  storage/app/private/             │  │
│  │  public/build/ (Vite assets)     │  │
│  └──────────────────────────────────┘  │
│                                         │
│  ┌─────────────┐  ┌─────────────────┐  │
│  │  MySQL 8    │  │    Redis        │  │
│  │  (local)    │  │    (local)      │  │
│  └─────────────┘  └─────────────────┘  │
└─────────────────────────────────────────┘
         ▲
         │  Deploy via:
         │  - Laravel Forge
         │  - Laravel Cloud
         │  - or manual git pull + artisan
         │
┌────────▼────────────────────────────────┐
│          Git Repository                  │
│  main branch → auto-deploy              │
│  develop branch → staging               │
└─────────────────────────────────────────┘
```

**V1 stack:**
- Laravel Forge or Laravel Cloud for deployment management.
- Single server monolith (no horizontal scaling needed initially).
- MySQL on same server or managed (e.g., PlanetScale, RDS).
- Redis on same server or managed (e.g., Upstash, ElastiCache).
- Nginx as reverse proxy to PHP-FPM.
- Vite build output served directly by Nginx.

### Future Scale

| Component | V1 | Future |
|---|---|---|
| App server | Single server | Horizontal scaling behind load balancer |
| Database | Local MySQL | Managed MySQL (RDS, PlanetScale) |
| Cache/Queue | Local Redis | Managed Redis (ElastiCache, Upstash) |
| Static assets | Nginx direct | CDN (Cloudflare, CloudFront) |
| File storage | Local filesystem | S3-compatible object storage |
| Search | Database queries | Elasticsearch / Meilisearch |

---

## 17. Integration Architecture

### BusinessOS Connector

The integrations module provides a boundary for external system connections. It is not required for V1 operation.

```
┌──────────────────┐     ┌──────────────────┐
│  Field Sales     │     │  BusinessOS      │
│  Domain Modules  │────►│  Connector       │
│                  │     │                  │
│  Events:         │     │  ┌────────────┐  │
│  - OrderCreated  │     │  │ Adapter    │  │
│  - SyncRequested │     │  │ Interface  │  │
│                  │     │  └─────┬──────┘  │
└──────────────────┘     │        │         │
                         │  ┌─────▼──────┐  │
                         │  │ BusinessOS │  │
                         │  │ Adapter    │  │
                         │  └────────────┘  │
                         └──────────────────┘
```

### Integration Patterns

| Pattern | Description |
|---|---|
| **Repository/Adapter** | Each external system gets its own adapter implementing a shared interface |
| **Event-driven sync** | Domain events trigger sync jobs; integrations subscribe to relevant events |
| **Configurable per company** | Each company can enable/configure which integrations are active |
| **Non-blocking** | Integration failures never block core platform operations |
| **Retry with dead-letter** | Failed sync attempts are retried, then moved to dead-letter queue for manual review |

### External ERP Support

- Pluggable adapter interface for each ERP system.
- Each ERP adapter handles authentication, data transformation, and API communication.
- Sync jobs handle bidirectional data flow.
- ERP-specific field mapping stored in company configuration.

---

## 18. Code Conventions

### PHP Conventions

- **Constructor promotion:** PHP 8 constructor property promotion: `public function __construct(public GitHub $github) {}`
- **Return types:** Explicit return type declarations on all methods.
- **Enums:** TitleCase enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- **Documentation:** PHPDoc blocks for complex logic; no inline comments for obvious code.
- **Type hints:** Explicit type hints for all method parameters.
- **Naming:** Descriptive names — `isRegisteredForDiscounts`, not `discount()`.

### Laravel Conventions

- **File creation:** Always use `php artisan make:` commands (make:model, make:controller, make:test, etc.).
- **Validation:** Form Request classes for all non-trivial validation.
- **Responses:** API Resources for all API response transformations.
- **Authorization:** Policies for each domain entity.
- **Logic placement:** Services/Actions for business logic; controllers stay thin.
- **Async work:** Jobs for any work that can be deferred or is time-consuming.
- **Events:** Domain events for cross-module communication and side effects.
- **Testing:** Pest test framework with feature tests as the primary test type.

### Database Conventions

| Convention | Example |
|---|---|
| Table names | `snake_case`, plural: `sales_orders`, `customers` |
| Column names | `snake_case`: `first_name`, `created_at` |
| Primary key | `id` (bigint auto-increment for server-generated) |
| UUID column | `uuid` column for client-generated identifiers |
| Tenant column | `tenant_id` on all business tables (foreign key) |
| Timestamps | `created_at`, `updated_at` (auto-managed by Eloquent) |
| Soft deletes | `deleted_at` where appropriate (users, customers, orders) |
| Foreign keys | `foreign_id()->constrained()` in migrations |
| Indexes | Strategic indexes on foreign keys, frequently queried columns, and composite queries |

### Migration Naming

```
create_users_table
add_tenant_id_to_customers_table
create_location_history_table
add_phone_number_to_salesmen_table
```

### Model Conventions

```php
class Customer extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
}
```

- Use `HasUuids` trait for UUID generation.
- Use `BelongsToTenant` trait for automatic tenant scoping.
- Define `$fillable` explicitly (never `$guarded = []`).
- Cast attributes to appropriate types.
- Define relationships with explicit return types.

---

# Architecture Decision Record — Batch 0

This section records the explicit architectural decisions made during Batch 0 planning. Each decision states the context, the chosen option, and the rationale.

## ADR-001: Multi-Tenancy Model

**Decision:** Shared database with a `tenant_id` column on every business table.

**Context:** The platform must support multiple companies while keeping operational complexity low. Database-per-tenant was considered.

**Rationale:**
- One database means simpler backups, migrations, and monitoring
- Lower infrastructure cost for the initial Afghanistan market
- Laravel global scopes make tenant filtering straightforward
- Row-level isolation is sufficient with rigorous scope enforcement
- Database-per-tenant can be added later if a large client requires it

**Consequences:** Every business table carries `tenant_id`. All queries, jobs, and file paths must be tenant-scoped. Any leak of the global scope from a model would expose cross-tenant data — mitigated with a test suite and `BelongsToTenant` trait.

## ADR-002: Laravel Modular Monolith

**Decision:** Build a modular monolith (not microservices) with domain modules under `app/Domains/`.

**Context:** Features span many domains (GPS, visits, orders, collections, reporting). Microservices would add network complexity without benefit at this stage.

**Rationale:**
- Single deployable unit keeps operations simple
- Team ships features faster with clear in-process boundaries
- No premature distributed systems complexity
- Domains can be extracted into services later if they grow hot (e.g., GPS ingestion)

**Consequences:** All modules share the same process, database, and queue. Boundary discipline is enforced by convention, not infrastructure.

## ADR-003: Admin Panel = Blade + Tailwind CSS + Alpine.js

**Decision:** Server-rendered Blade templates styled with Tailwind CSS (v4) and enhanced with Alpine.js for interactivity.

**Context:** The admin dashboard must feel like a modern commercial SaaS product. React/Vue/Livewire were considered.

**Rationale:**
- Server-rendered Blade is fast and maps directly to Eloquent/data
- Tailwind v4 provides the modern, consistent design system needed
- Alpine.js adds enough interactivity (drawers, modals, toggles, polling) without a JS framework
- Keeps the frontend simple and maintainable for a small team
- No build-time framework overhead

**Consequences:** Complex realtime UI (drag-drop kanban, heavy client state) is harder. Livewire may be revisited if interactive complexity grows beyond Alpine's comfort zone.

## ADR-004: Flutter Android App

**Decision:** The mobile app is a separate Flutter project targeting Android first, and is **offline-first from its initial implementation** (not a later phase).

**Context:** Field salesmen need an offline-first Android application. iOS was considered but excluded from V1.

**Rationale:**
- Flutter provides a single codebase with a path to iOS later
- Offline-first local storage (SQLite via drift/floor) fits the domain
- The API contract (`APIs/CONTRACT.md`) is the boundary — backend is fully decoupled from the app

**Consequences:** The mobile app is developed separately. The initial Android release ships the Offline-First Foundation (local SQLite, local-first writes, client UUIDs, basic sync queue, connectivity state, `pending`/`synced`/`failed` record states). The API contract must be stable before significant mobile work begins. Backend work in this repo does not build Flutter code.

## ADR-005: REST API with Laravel Sanctum

**Decision:** Versioned REST API (`/api/v1`) secured with Sanctum personal access tokens.

**Context:** Mobile clients need clean, token-authenticated HTTP endpoints. Alternatives like GraphQL were considered.

**Rationale:**
- REST is well-understood by clients and tooling
- Sanctum is built into Laravel, no extra package required
- Simple token model fits the device-bound session design
- Idempotency keys + offline UUIDs solve the offline semantics without a transport protocol change

**Consequences:** Mobile sync relies on HTTPS + REST. No realtime transport in V1 (polling for live map; Reverb/WebSockets deferred).

## ADR-006: UTC Timestamps

**Decision:** Store all operational timestamps in UTC in the database.

**Context:** Companies in Afghanistan (Asia/Kabul) and possibly Pakistan (Asia/Karachi) have different offsets.

**Rationale:**
- Unambiguous worldwide ordering
- Conversion is done at the presentation layer (timezone per company/user)
- Offline mobile records keep UTC + client offset for correct display

**Consequences:** All `created_at`/`updated_at` and business-time columns are UTC. UI and API responses convert using the company timezone.

## ADR-007: Offline-First Strategy

**Decision:** The mobile app is offline-first: all critical operations (visits, orders, collections, expenses, GPS) work without connectivity and sync later. Offline-first applies **from the initial app implementation** — the Offline-First Foundation (local SQLite database, local-first write strategy, client UUIDs, basic sync queue, connectivity state, per-record `pending`/`synced`/`failed` states) ships with the first Android release, not a later phase.

**Context:** Afghan field connectivity is intermittent. Losing a sale or visit because of poor signal is unacceptable.

**Rationale:**
- App remains fully functional offline
- Sync is transparent and backgrounded
- Company requirements (offline-first is a hard requirement)

**Consequences:** Every write entity has an `offline_uuid` on release one. Advanced sync (conflict resolution, retry/backoff, bulk sync, tombstones, duplicate handling, recovery, sync hardening) is a later batch that extends — not introduces — the offline-first foundation. See `OFFLINE_SYNC_DESIGN.md`.

## ADR-008: UUID + Idempotency Strategy

**Decision:** Client-generated UUID v4 for every offline-created entity; server enforces idempotency on `offline_uuid`; idempotency keys on all create endpoints.

**Context:** Duplicates must be impossible even when a device retries or a batch is replayed.

**Rationale:**
- UUIDs prevent collisions across devices
- `offline_uuid` unique checks make retries idempotent
- Batch GPS dedupe via `client_uuid` per point

**Consequences:** Every writable business table has an `offline_uuid` (nullable until synced, unique when set). Server never trusts client-supplied `id` for routing.

## ADR-009: Redis Usage

**Decision:** Redis is used for cache, queues, rate limiting, latest-location cache, and session/personal-access-token storage.

**Context:** Queues and live-location caching were flagged as Redis workloads.

**Rationale:**
- Redis is fast, established, and supported by Laravel's cache/queue drivers
- Live-map "latest location" cache avoids hitting `location_history` per refresh
- Queue (Redis driver) isolates GPS processing from other workloads

**Consequences:** Redis becomes an operational dependency in production. A grace period exists: the app must still function (with reduced live-map freshness) if Redis is unavailable.

## ADR-010: GPS Storage Strategy

**Decision:** Two-tier storage: `current_locations` (one row per user, UPSERT) + `location_history` (append-only, high-volume) + Redis latest-location cache.

**Context:** Millions of GPS points must be written cheaply and read quickly for maps/analytics.

**Rationale:**
- Append-only history is the efficient high-write path
- Current location is always a single UPSERT row, not a scan
- Redis serves the live map without touching the hot table
- Indexes: `(tenant_id, user_id, recorded_at)` — no over-indexing

**Consequences:** Route reconstruction queries the history table on demand. Partitioning and archival are planned for scale (V3). See `GPS_TRACKING_DESIGN.md`.

## ADR-011: Latest-Location Strategy

**Decision:** Redis hash `fs:{tenant_id}:locations:latest` holds every salesman's latest position; `current_locations` is the durable fallback; the live map polls it.

**Context:** The live map must stay fast without constantly querying millions of GPS rows.

**Rationale:**
- Redis read is O(1) for the whole team
- `current_locations` provides durability and a fallback if Redis is flushed
- Polling (every 15s) is sufficient for V1; WebSockets deferred

**Consequences:** Map freshness depends on Redis; staleness handled via `recorded_at` status logic rather than cache TTL.

## ADR-012: Map Provider Abstraction

**Decision:** All map rendering and geocoding behind a provider-agnostic interface; V1 renders with Leaflet/OpenStreetMap (no API key, works in Afghanistan).

**Context:** Google Maps/Mapbox were considered but cost and Afghanistan availability differ.

**Rationale:**
- No vendor lock-in in domain logic
- OSM/Leaflet is free and usable without keys
- Swapping providers later is a config change, not a rewrite

**Consequences:** An abstraction layer (`MapProvider` interface) is in the plan. Tile choice and reverse-geocoding go through it.

## ADR-013: File Storage Strategy

**Decision:** Laravel Storage (Flysystem) with local disk in V1 (`storage/app/private/{tenant_id}/...`), S3-compatible swap later. No binary data in MySQL.

**Context:** Photos (customers, visits, receipts, salesmen), logos, and reports need durable storage.

**Rationale:**
- Flysystem abstracts local vs S3
- Tenant-scoped directory layout isolates tenants
- Signed URLs control read access

**Consequences:** All binary files live on disk/object storage, referenced by URL/relative path in DB. Tenant boundary enforced in the storage path and signed-URL middleware.

## ADR-014: Notification Strategy

**Decision:** Database (in-app) notifications serve the admin panel; Firebase Cloud Messaging (FCM) pushes to Android; email/WhatsApp/SMS deferred.

**Context:** Salesmen need push updates (route changes, approvals); managers need in-app alerts.

**Rationale:**
- Laravel's native notifications + a FCM channel cover V1
- `notification_templates` keep copy configurable
- Email/SMS/WhatsApp added later without redesign

**Consequences:** FCM requires device tokens captured at registration and refreshed on rotation. See `API_CONTRACT.md` device registration.

## ADR-015: BusinessOS Integration Boundary

**Decision:** Integration lives behind an adapter interface (`IntegrationAdapter`) on the `Integrations` domain. BusinessOS is optional and never a dependency of core operations.

**Context:** Future ERP (BusinessOS) sync is planned but must not gate V1.

**Rationale:**
- Field Sales is standalone-first; all data created in-app
- Adapter pattern allows BusinessOS now, other ERPs later
- Event-driven sync (queues) decouples outbound pushes from request lifecycle

**Consequences:** No BusinessOS code ships in V1 beyond the interface contract. See `BUSINESSOS_INTEGRATION.md`.
