# Offline-First Sync Design

> **Status:** PLANNING ONLY
> **Last Updated:** 2025-01-15
> **Target Platform:** Flutter Android (SQLite local DB, REST API backend)
> **Mobile contract:** Offline-first applies **from the initial Android release**, not a later phase. The Offline-First Foundation ships with release one: local SQLite database, local-first write strategy, client UUIDs, basic sync queue, connectivity state, and per-record `pending` / `synced` / `failed` sync states. The advanced capabilities described in this design (conflict resolution, retry/backoff, bulk sync, tombstones, duplicate handling, recovery, sync hardening) are delivered by the later Sync Engine phase on top of that foundation.

---

## Table of Contents

1. [Design Philosophy](#1-design-philosophy)
2. [Local SQLite Database Schema](#2-local-sqlite-database-schema)
3. [UUID & Idempotency Strategy](#3-uuid--idempotency-strategy)
4. [Sync Protocol](#4-sync-protocol)
5. [Conflict Detection & Resolution](#5-conflict-detection--resolution)
6. [Sync State Machine](#6-sync-state-machine)
7. [Connectivity Handling](#7-connectivity-handling)
8. [Retry Strategy](#8-retry-strategy)
9. [Photo Handling](#9-photo-handling)
10. [Data Freshness Strategy](#10-data-freshness-strategy)
11. [Sync UI in Mobile App](#11-sync-ui-in-mobile-app)
12. [Data Storage Limits](#12-data-storage-limits)
13. [Edge Cases](#13-edge-cases)

---

## 1. Design Philosophy

The field sales mobile application operates in a market (Afghanistan) where internet connectivity is unreliable, intermittent, or entirely unavailable for extended periods. The entire system is designed around one core principle:

> **The mobile app must be fully functional without internet connectivity. All user actions are recorded locally first. Sync happens transparently in the background when connectivity returns.**

### Core Principles

```
┌─────────────────────────────────────────────────────────────────┐
│                     OFFLINE-FIRST TENETS                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. LOCAL-FIRST WRITES                                         │
│     Every user action (visit, order, collection, expense,      │
│     GPS) is recorded in local SQLite BEFORE anything else.     │
│     The app never waits on the network for a write.            │
│                                                                 │
│  2. TRANSPARENT SYNC                                           │
│     Background sync runs continuously. The user never          │
│     manually triggers sync — it just happens. Manual sync      │
│     is available but not required.                             │
│                                                                 │
│  3. SERVER IS AUTHORITATIVE (for master data)                  │
│     Customer details, product catalogs, price lists, and       │
│     routes always reflect the server's version. Server         │
│     wins on conflicts for shared records.                      │
│                                                                 │
│  4. CLIENT IS AUTHORITATIVE (for offline-created records)      │
│     Records created offline (visits, orders, collections)      │
│     belong to the creating device until synced. The server     │
│     accepts them idempotently.                                 │
│                                                                 │
│  5. PROGRESSIVE ENHANCEMENT                                    │
│     Core functionality works offline. Enhanced features        │
│     (real-time inventory, manager approvals) degrade           │
│     gracefully when connectivity is unavailable.               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Functional Parity Matrix

| Feature | Online | Offline | Notes |
|---------|--------|---------|-------|
| Check-in/out at customer | Full | Full | GPS recorded locally |
| Create orders | Full | Full | Synced when online |
| Record payments | Full | Full | Synced when online |
| Log expenses | Full | Full | Synced when online |
| View product catalog | Full | Full (cached) | Last sync version |
| View customer details | Full | Full (cached) | Last sync version |
| View price lists | Full | Full (cached) | Last sync version |
| Execute route plan | Full | Full | Route cached locally |
| Submit for approval | Full | Full | Queued, sent when online |
| View real-time inventory | Full | Degraded | Shows last known |
| Manager approvals | Full | None | Requires connectivity |
| Push notifications | Full | None | Requires connectivity |
| GPS tracking | Full | Full | Bulk upload when online |

### Foundation vs Advanced Sync

The Offline-First Foundation (ship with the initial Android release) establishes: local SQLite database, local-first write strategy, client UUIDs, basic sync queue, connectivity state, and per-record `pending` / `synced` / `failed` sync states. The full scope of this design document — conflict resolution (§5), retry/backoff (§8), tombstones, cursor/checkpoint management, bulk synchronization, duplicate handling, recovery, and sync hardening — is delivered by the later Sync Engine phase and intentionally deferred beyond the first release.

---

## 2. Local SQLite Database Schema

The local database is split into three logical groups: **server-mirrored** (master data synced from server), **transactional** (created locally, synced to server), and **sync infrastructure** (metadata for the sync engine).

### 2.1 Tables Mirrored from Server

```sql
-- ============================================================
-- AUTH & CONFIG
-- ============================================================

CREATE TABLE local_user (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    name            TEXT NOT NULL,
    email           TEXT NOT NULL,
    role            TEXT NOT NULL,           -- 'salesman', 'supervisor', 'admin'
    phone           TEXT,
    avatar_url      TEXT,
    territory_id    INTEGER,
    is_active       INTEGER DEFAULT 1,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_tenant (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    name            TEXT NOT NULL,
    slug            TEXT NOT NULL,
    config          TEXT,                    -- JSON blob for tenant settings
    subscription    TEXT,                    -- JSON: plan, limits, features
    is_active       INTEGER DEFAULT 1,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_config (
    id              INTEGER PRIMARY KEY,
    key             TEXT UNIQUE NOT NULL,
    value           TEXT NOT NULL,           -- JSON or plain text
    category        TEXT NOT NULL,           -- 'sync', 'ui', 'gps', 'general'
    updated_at      TEXT NOT NULL
);

-- ============================================================
-- MASTER DATA (synced from server)
-- ============================================================

CREATE TABLE local_products (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    sku             TEXT NOT NULL,
    name            TEXT NOT NULL,
    description     TEXT,
    category        TEXT,
    unit            TEXT NOT NULL,           -- 'piece', 'box', 'carton', 'kg'
    base_price      REAL NOT NULL,
    is_active       INTEGER DEFAULT 1,
    image_url       TEXT,
    metadata        TEXT,                    -- JSON: weight, dimensions, etc.
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_price_lists (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    name            TEXT NOT NULL,
    currency        TEXT DEFAULT 'AFN',
    effective_from  TEXT,
    effective_to    TEXT,
    is_default      INTEGER DEFAULT 0,
    is_active       INTEGER DEFAULT 1,
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_price_list_items (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    price_list_id   INTEGER NOT NULL,
    product_id      INTEGER NOT NULL,
    unit_price      REAL NOT NULL,
    min_quantity    INTEGER DEFAULT 1,
    discount_pct    REAL DEFAULT 0,
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL,
    FOREIGN KEY (price_list_id) REFERENCES local_price_lists(id),
    FOREIGN KEY (product_id) REFERENCES local_products(id)
);

CREATE TABLE local_customers (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    territory_id    INTEGER,
    name            TEXT NOT NULL,
    business_name   TEXT,
    contact_person  TEXT,
    phone           TEXT,
    secondary_phone TEXT,
    address         TEXT,
    latitude        REAL,
    longitude       REAL,
    customer_type   TEXT DEFAULT 'retailer', -- 'retailer', 'wholesaler', 'distributor'
    credit_limit    REAL DEFAULT 0,
    current_balance REAL DEFAULT 0,
    price_list_id   INTEGER,
    route_id        INTEGER,
    visit_day       TEXT,                    -- 'monday', 'tuesday', etc.
    is_active       INTEGER DEFAULT 1,
    metadata        TEXT,                    -- JSON: custom fields
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL,
    FOREIGN KEY (price_list_id) REFERENCES local_price_lists(id),
    FOREIGN KEY (route_id) REFERENCES local_routes(id)
);

CREATE TABLE local_routes (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    name            TEXT NOT NULL,
    territory_id    INTEGER,
    salesman_id     INTEGER,
    day_of_week     TEXT,                    -- 'monday', 'tuesday', etc.
    is_active       INTEGER DEFAULT 1,
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_route_customers (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    route_id        INTEGER NOT NULL,
    customer_id     INTEGER NOT NULL,
    sequence        INTEGER NOT NULL,       -- visit order in route
    planned_arrival TEXT,                    -- HH:MM suggested time
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL,
    FOREIGN KEY (route_id) REFERENCES local_routes(id),
    FOREIGN KEY (customer_id) REFERENCES local_customers(id)
);

CREATE TABLE local_territories (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    name            TEXT NOT NULL,
    parent_id       INTEGER,
    boundary        TEXT,                    -- GeoJSON polygon
    is_active       INTEGER DEFAULT 1,
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_visit_plans (
    id              INTEGER PRIMARY KEY,
    server_id       INTEGER UNIQUE,
    offline_uuid    TEXT UNIQUE,
    tenant_id       INTEGER NOT NULL,
    route_id        INTEGER,
    customer_id     INTEGER NOT NULL,
    salesman_id     INTEGER NOT NULL,
    planned_date    TEXT NOT NULL,           -- YYYY-MM-DD
    planned_time    TEXT,                    -- HH:MM
    status          TEXT DEFAULT 'planned',  -- 'planned', 'completed', 'skipped'
    notes           TEXT,
    deleted_at      TEXT,
    last_synced_at  TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL,
    FOREIGN KEY (route_id) REFERENCES local_routes(id),
    FOREIGN KEY (customer_id) REFERENCES local_customers(id)
);
```

### 2.2 Transactional Tables (Created Locally, Synced to Server)

```sql
CREATE TABLE local_visits (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,
    customer_id         INTEGER NOT NULL,
    visit_plan_id       INTEGER,
    route_id            INTEGER,
    salesman_id         INTEGER NOT NULL,

    -- Check-in
    check_in_time       TEXT NOT NULL,
    check_in_latitude   REAL,
    check_in_longitude  REAL,
    check_in_accuracy   REAL,
    check_in_address    TEXT,

    -- Check-out
    check_out_time      TEXT,
    check_out_latitude  REAL,
    check_out_longitude REAL,
    check_out_accuracy  REAL,

    -- Visit data
    visit_outcome       TEXT,                -- 'successful', 'no_answer', 'closed', 'cancelled'
    purpose             TEXT,                -- 'order', 'collection', 'follow_up', 'survey'
    notes               TEXT,
    competitor_activity TEXT,

    -- Relationships
    orders_created      INTEGER DEFAULT 0,
    collections_made    INTEGER DEFAULT 0,

    -- Sync state
    status              TEXT DEFAULT 'pending', -- 'pending', 'syncing', 'synced', 'failed'
    is_split_visit      INTEGER DEFAULT 0,   -- visit interrupted by connectivity loss
    split_from_uuid     TEXT,
    deleted_at          TEXT,
    last_synced_at      TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL,

    FOREIGN KEY (customer_id) REFERENCES local_customers(id)
);

CREATE TABLE local_orders (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,
    customer_id         INTEGER NOT NULL,
    visit_uuid          TEXT,                -- links to local_visits.offline_uuid
    salesman_id         INTEGER NOT NULL,
    route_id            INTEGER,

    -- Order data
    order_number        TEXT,                -- client-generated: ORD-{timestamp}-{random}
    order_date          TEXT NOT NULL,       -- YYYY-MM-DD
    delivery_date       TEXT,
    payment_type        TEXT NOT NULL,       -- 'cash', 'credit', 'partial'
    currency            TEXT DEFAULT 'AFN',
    subtotal            REAL NOT NULL,
    discount_amount     REAL DEFAULT 0,
    tax_amount          REAL DEFAULT 0,
    total_amount        REAL NOT NULL,
    amount_paid         REAL DEFAULT 0,
    notes               TEXT,

    -- Status
    status              TEXT DEFAULT 'draft', -- 'draft', 'submitted', 'approved', 'rejected', 'delivered'
    submitted_at        TEXT,
    approved_at         TEXT,
    approved_by         INTEGER,
    rejection_reason    TEXT,

    -- Sync state
    sync_status         TEXT DEFAULT 'pending',
    deleted_at          TEXT,
    last_synced_at      TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL,

    FOREIGN KEY (customer_id) REFERENCES local_customers(id)
);

CREATE TABLE local_order_items (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    order_id            INTEGER NOT NULL,
    product_id          INTEGER NOT NULL,
    product_name        TEXT,                -- denormalized for offline display
    quantity            REAL NOT NULL,
    unit_price          REAL NOT NULL,
    discount_pct        REAL DEFAULT 0,
    discount_amount     REAL DEFAULT 0,
    tax_pct             REAL DEFAULT 0,
    tax_amount          REAL DEFAULT 0,
    line_total          REAL NOT NULL,
    unit                TEXT,
    notes               TEXT,
    deleted_at          TEXT,
    last_synced_at      TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL,

    FOREIGN KEY (order_id) REFERENCES local_orders(id),
    FOREIGN KEY (product_id) REFERENCES local_products(id)
);

CREATE TABLE local_collections (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,
    customer_id         INTEGER NOT NULL,
    visit_uuid          TEXT,
    salesman_id         INTEGER NOT NULL,

    -- Collection data
    collection_number   TEXT,
    collection_date     TEXT NOT NULL,       -- YYYY-MM-DD
    amount              REAL NOT NULL,
    payment_method      TEXT NOT NULL,       -- 'cash', 'bank_transfer', 'check', 'mobile_money'
    reference_number    TEXT,                -- check number, transfer ref
    currency            TEXT DEFAULT 'AFN',
    notes               TEXT,

    -- Linked order (if partial payment)
    order_uuid          TEXT,                -- links to local_orders.offline_uuid
    applied_to_order    INTEGER DEFAULT 0,

    -- Status
    status              TEXT DEFAULT 'pending', -- 'pending', 'confirmed', 'rejected'
    confirmed_by        INTEGER,
    confirmed_at        TEXT,

    -- Sync state
    sync_status         TEXT DEFAULT 'pending',
    deleted_at          TEXT,
    last_synced_at      TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL,

    FOREIGN KEY (customer_id) REFERENCES local_customers(id)
);

CREATE TABLE local_expenses (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,
    salesman_id         INTEGER NOT NULL,

    -- Expense data
    expense_date        TEXT NOT NULL,       -- YYYY-MM-DD
    category            TEXT NOT NULL,       -- 'transport', 'food', 'accommodation', 'communication', 'other'
    amount              REAL NOT NULL,
    currency            TEXT DEFAULT 'AFN',
    description         TEXT NOT NULL,
    receipt_photo_path  TEXT,                -- local file path
    receipt_photo_url   TEXT,                -- server URL after upload

    -- Approval
    status              TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    approved_by         INTEGER,
    approved_at         TEXT,
    rejection_reason    TEXT,

    -- Sync state
    sync_status         TEXT DEFAULT 'pending',
    deleted_at          TEXT,
    last_synced_at      TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL
);

CREATE TABLE local_gps_points (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,
    salesman_id         INTEGER NOT NULL,

    -- Location data
    latitude            REAL NOT NULL,
    longitude           REAL NOT NULL,
    altitude            REAL,
    accuracy            REAL,
    speed               REAL,
    heading             REAL,

    -- Device context
    battery_level       INTEGER,
    is_charging         INTEGER DEFAULT 0,
    network_status      TEXT,                -- 'wifi', 'mobile', 'offline'
    is_mock_location    INTEGER DEFAULT 0,
    provider            TEXT,                -- 'gps', 'network', 'fused'

    -- Timing
    recorded_at         TEXT NOT NULL,       -- when GPS fix was taken
    sequence_number     INTEGER,             -- ordering within batch

    -- Sync state
    batch_id            TEXT,                -- groups points for batch upload
    sync_status         TEXT DEFAULT 'pending',
    uploaded_at         TEXT,
    created_at          TEXT NOT NULL
);

CREATE TABLE local_photos (
    id                  INTEGER PRIMARY KEY,
    server_id           INTEGER UNIQUE,
    offline_uuid        TEXT UNIQUE NOT NULL,
    tenant_id           INTEGER NOT NULL,

    -- Photo metadata
    entity_type         TEXT NOT NULL,       -- 'visit', 'order', 'collection', 'expense', 'customer'
    entity_id           INTEGER,
    entity_uuid         TEXT,                -- offline_uuid of parent entity
    filename            TEXT NOT NULL,
    local_path          TEXT NOT NULL,       -- device file path
    server_url          TEXT,                -- URL after upload
    mime_type           TEXT DEFAULT 'image/jpeg',
    file_size           INTEGER,             -- bytes

    -- Capture context
    taken_at            TEXT NOT NULL,
    latitude            REAL,
    longitude           REAL,

    -- Sync state
    sync_status         TEXT DEFAULT 'pending', -- 'pending', 'uploading', 'uploaded', 'failed'
    uploaded_at         TEXT,
    deleted_at          TEXT,
    created_at          TEXT NOT NULL
);
```

### 2.3 Sync Infrastructure Tables

```sql
CREATE TABLE local_sync_queue (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type     TEXT NOT NULL,           -- 'visit', 'order', 'collection', 'expense', 'gps_point', 'photo'
    entity_id       INTEGER NOT NULL,        -- local SQLite row ID
    entity_uuid     TEXT NOT NULL,           -- offline_uuid of the entity
    action          TEXT NOT NULL,           -- 'create', 'update', 'delete'
    payload         TEXT NOT NULL,           -- JSON: full entity data to sync
    priority        INTEGER DEFAULT 5,       -- 1=highest (GPS), 5=normal, 10=lowest
    attempts        INTEGER DEFAULT 0,
    max_attempts    INTEGER DEFAULT 5,
    status          TEXT DEFAULT 'pending',  -- 'pending', 'syncing', 'synced', 'failed', 'cancelled'
    error_message   TEXT,
    next_retry_at   TEXT,                    -- ISO timestamp for next retry attempt
    server_id       INTEGER,                 -- filled after successful sync
    server_uuid     TEXT,                    -- filled after successful sync
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE INDEX idx_sync_queue_status ON local_sync_queue(status, priority, created_at);
CREATE INDEX idx_sync_queue_entity ON local_sync_queue(entity_type, entity_uuid);

CREATE TABLE local_sync_cursor (
    id              INTEGER PRIMARY KEY,
    entity_type     TEXT UNIQUE NOT NULL,    -- 'products', 'customers', 'routes', etc.
    last_pull_at    TEXT,                    -- ISO timestamp of last successful pull
    last_push_at    TEXT,                    -- ISO timestamp of last successful push
    last_full_sync  TEXT,                    -- ISO timestamp of last full (non-incremental) sync
    record_count    INTEGER DEFAULT 0,       -- total records of this type locally
    metadata        TEXT,                    -- JSON: additional cursor info
    updated_at      TEXT NOT NULL
);

CREATE TABLE local_sync_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    sync_type       TEXT NOT NULL,           -- 'pull', 'push', 'gps_upload', 'photo_upload'
    direction       TEXT NOT NULL,           -- 'inbound', 'outbound'
    status          TEXT NOT NULL,           -- 'started', 'completed', 'failed', 'partial'
    entities_sent   INTEGER DEFAULT 0,
    entities_received INTEGER DEFAULT 0,
    bytes_sent      INTEGER DEFAULT 0,
    bytes_received  INTEGER DEFAULT 0,
    duration_ms     INTEGER,
    error_message   TEXT,
    error_code      TEXT,
    network_type    TEXT,                    -- 'wifi', 'mobile', 'offline'
    started_at      TEXT NOT NULL,
    completed_at    TEXT,
    created_at      TEXT NOT NULL
);

CREATE INDEX idx_sync_log_type ON local_sync_log(sync_type, created_at);
```

---

## 3. UUID & Idempotency Strategy

### 3.1 Client-Side UUID Generation

Every entity created offline receives a UUID v4 generated by the device. This UUID serves as the idempotency key for the entire lifecycle of the record.

```
┌─────────────────────────────────────────────────────────────────┐
│                    UUID GENERATION FLOW                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  User creates order offline                                    │
│         │                                                       │
│         ▼                                                       │
│  ┌──────────────────┐                                          │
│  │ Generate UUID v4 │  "550e8400-e29b-41d4-a716-446655440000" │
│  └────────┬─────────┘                                          │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────┐                  │
│  │ Save to local SQLite                     │                  │
│  │   id: 42 (auto-increment)               │                  │
│  │   offline_uuid: "550e8400-..."          │                  │
│  │   server_id: NULL (not yet synced)       │                  │
│  └────────┬─────────────────────────────────┘                  │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────┐                  │
│  │ Add to local_sync_queue                  │                  │
│  │   entity_type: 'order'                   │                  │
│  │   entity_id: 42                          │                  │
│  │   entity_uuid: "550e8400-..."            │                  │
│  │   action: 'create'                       │                  │
│  │   payload: { full JSON of order }        │                  │
│  └────────┬─────────────────────────────────┘                  │
│           │                                                     │
│           ▼  (when online)                                      │
│  ┌──────────────────────────────────────────┐                  │
│  │ POST /api/v1/sync/push                   │                  │
│  │   offline_uuid: "550e8400-..."           │                  │
│  │   ... order data ...                     │                  │
│  └────────┬─────────────────────────────────┘                  │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────┐                  │
│  │ Server response                          │                  │
│  │   server_id: 789                         │                  │
│  │   server_uuid: "server-uuid-abc"         │                  │
│  │   status: 'created'                      │                  │
│  └────────┬─────────────────────────────────┘                  │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────┐                  │
│  │ Update local record                      │                  │
│  │   server_id: 789                         │                  │
│  │   status: 'synced'                       │                  │
│  └──────────────────────────────────────────┘                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Why UUIDs over auto-increment IDs?**

| Approach | Problem |
|----------|---------|
| Auto-increment ID | Device A creates order #1, Device B creates order #1 — collision on sync |
| Timestamp-based | Clock skew between devices creates duplicates |
| Server-assigned ID | Requires network to create any record — breaks offline-first |
| **UUID v4** | **Globally unique, no coordination needed, works offline** |

### 3.2 Idempotent Server Operations

The server implements idempotency checks for all entity creation endpoints:

```
POST /api/v1/sync/push arrives with offline_uuid = "550e8400-..."

Server logic:
┌─────────────────────────────────────────────────────┐
│                                                     │
│  1. Check: does order with this offline_uuid exist? │
│     │                                               │
│     ├── NO  → Create new order                     │
│     │        Assign server_id                       │
│     │        Return: status = "created"             │
│     │                                               │
│     └── YES → Check: is payload identical?          │
│               │                                     │
│               ├── YES → Return existing order       │
│               │        Return: status = "exists"    │
│               │        (no duplicate created)       │
│               │                                     │
│               └── NO  → Return conflict (409)       │
│                        Return: status = "conflict"  │
│                        Include both versions        │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 3.3 Database Indexes for Idempotency

```sql
-- Server-side indexes (Laravel migration)
$table->unique('offline_uuid');
$table->index(['offline_uuid', 'deleted_at']);
```

---

## 4. Sync Protocol

### 4.1 Pull (Server → Client)

Pull brings master data and server-side changes to the client. It is read-only from the client's perspective.

**Triggers:**
- App startup (automatic)
- Periodic interval: every 15 minutes when online
- Manual pull-to-refresh in UI
- After successful push (to get any server-side derivations)

**Pull Flow:**

```
┌──────────┐                                          ┌──────────┐
│  CLIENT  │                                          │  SERVER  │
└────┬─────┘                                          └────┬─────┘
     │                                                      │
     │  1. Read local_sync_cursor for each entity type      │
     │     last_pull_at = "2025-01-14T00:00:00Z"           │
     │                                                      │
     │  2. GET /api/v1/sync/pull                            │
     │     ?since=2025-01-14T00:00:00Z                     │
     │     &types[]=customers                               │
     │     &types[]=products                                │
     │     &types[]=routes                                  │
     │     &types[]=price_lists                             │
     │     &types[]=territories                             │
     │     &types[]=visit_plans                             │
     │  ─────────────────────────────────────────────────>  │
     │                                                      │
     │                    3. Query each table for changes    │
     │                       WHERE updated_at > since       │
     │                       OR deleted_at > since          │
     │                                                      │
     │  4. Receive response                                 │
     │  <─────────────────────────────────────────────────  │
     │                                                      │
     │  5. For each entity in response:                     │
     │     - If action == "create" or "update": UPSERT      │
     │     - If action == "delete": soft delete locally     │
     │                                                      │
     │  6. Update local_sync_cursor.last_pull_at            │
     │     = response.server_time                           │
     │                                                      │
     │  7. Log to local_sync_log                            │
     │                                                      │
```

**Pull Request:**

```
GET /api/v1/sync/pull?since=2025-01-14T00:00:00Z&types[]=customers&types[]=products&types[]=routes&types[]=price_lists&types[]=territories&types[]=visit_plans
```

**Pull Response:**

```json
{
  "changes": [
    {
      "type": "customers",
      "items": [
        {
          "id": 123,
          "uuid": "server-uuid-abc",
          "offline_uuid": null,
          "action": "update",
          "data": {
            "name": "Kabul Grocery Store",
            "phone": "+93-700-123456",
            "latitude": 34.5553,
            "longitude": 69.2075,
            "credit_limit": 50000,
            "current_balance": 12500,
            "updated_at": "2025-01-15T08:30:00Z"
          }
        },
        {
          "id": 456,
          "uuid": "server-uuid-def",
          "offline_uuid": null,
          "action": "create",
          "data": {
            "name": "New Shop Main Street",
            "phone": "+93-700-789012",
            "latitude": 34.5600,
            "longitude": 69.2100,
            "credit_limit": 25000,
            "current_balance": 0,
            "updated_at": "2025-01-15T09:00:00Z"
          }
        }
      ],
      "count": 2
    },
    {
      "type": "products",
      "items": [
        {
          "id": 789,
          "uuid": "server-uuid-ghi",
          "offline_uuid": null,
          "action": "delete",
          "data": {
            "deleted_at": "2025-01-15T07:00:00Z"
          }
        }
      ],
      "count": 1
    }
  ],
  "server_time": "2025-01-15T10:30:00Z",
  "has_more": false,
  "next_cursor": null
}
```

**Handling Tombstones (Deletes):**

```
Server marks records with deleted_at timestamp.
Pull returns deleted records with action = "delete".
Client soft-deletes in local SQLite (sets deleted_at).
After configurable retention (30 days), tombstones purged.

Lifecycle:
  Server deletes record → sets deleted_at
       ↓
  Pull returns { action: "delete", data: { deleted_at: "..." } }
       ↓
  Client marks local record: UPDATE ... SET deleted_at = "..."
       ↓
  30 days later: DELETE FROM local_customers WHERE deleted_at IS NOT NULL
                 AND deleted_at < datetime('now', '-30 days')
```

**Pagination:**
- If `has_more: true`, client reads `next_cursor` and makes another pull request
- Client continues until `has_more: false`
- Each paginated response is processed atomically (upsert all, then update cursor)

### 4.2 Push (Client → Server)

Push sends locally-created and locally-modified records to the server. This is the core of the offline write sync.

**Triggers:**
- Connectivity restored (immediate)
- Periodic attempt: every 5 minutes when online
- Manual sync button in settings
- After completing a visit check-out

**Push Flow:**

```
┌──────────┐                                          ┌──────────┐
│  CLIENT  │                                          │  SERVER  │
└────┬─────┘                                          └────┬─────┘
     │                                                      │
     │  1. Read local_sync_queue WHERE status = 'pending'   │
     │     ORDER BY priority, created_at                    │
     │     LIMIT 50                                         │
     │                                                      │
     │  2. Group by entity_type for batch processing        │
     │                                                      │
     │  3. POST /api/v1/sync/push                           │
     │  { entities: [...] }                                 │
     │  ─────────────────────────────────────────────────>  │
     │                                                      │
     │                    4. For each entity:                │
     │                       a. Validate data               │
     │                       b. Check offline_uuid          │
     │                       c. Create or update            │
     │                       d. Return result               │
     │                                                      │
     │  5. Receive response with results[]                  │
     │  <─────────────────────────────────────────────────  │
     │                                                      │
     │  6. For each result:                                 │
     │     - If status == "created" or "updated":           │
     │       Update local record with server_id             │
     │       Mark sync_queue item as "synced"               │
     │     - If status == "exists":                         │
     │       Mark sync_queue item as "synced"               │
     │       (idempotent duplicate — no action needed)      │
     │     - If status == "conflict":                       │
     │       Mark as "conflict", surface to user            │
     │     - If status == "error":                          │
     │       Increment attempts, schedule retry             │
     │                                                      │
     │  7. Update local_sync_cursor.last_push_at            │
     │                                                      │
     │  8. Log to local_sync_log                            │
     │                                                      │
```

**Push Request:**

```
POST /api/v1/sync/push
Content-Type: application/json
```

```json
{
  "last_sync_at": "2025-01-14T00:00:00Z",
  "client_info": {
    "device_id": "device-uuid-xyz",
    "app_version": "1.2.3",
    "os_version": "Android 13",
    "battery_level": 72,
    "network_type": "wifi"
  },
  "entities": [
    {
      "type": "customer_visit",
      "action": "create",
      "offline_uuid": "550e8400-e29b-41d4-a716-446655440000",
      "data": {
        "customer_id": 45,
        "check_in_time": "2025-01-15T09:30:00Z",
        "check_in_latitude": 34.5553,
        "check_in_longitude": 69.2075,
        "check_in_accuracy": 10.5,
        "visit_outcome": "successful",
        "purpose": "order",
        "notes": "Met with shop owner, discussed new product line"
      }
    },
    {
      "type": "order",
      "action": "create",
      "offline_uuid": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
      "data": {
        "customer_id": 45,
        "visit_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "order_number": "ORD-1705312200-A1B2",
        "order_date": "2025-01-15",
        "payment_type": "credit",
        "currency": "AFN",
        "subtotal": 750,
        "discount_amount": 0,
        "tax_amount": 0,
        "total_amount": 750,
        "items": [
          {
            "product_id": 10,
            "product_name": "Green Tea 250g",
            "quantity": 5,
            "unit_price": 150,
            "unit": "piece",
            "line_total": 750
          }
        ]
      }
    },
    {
      "type": "collection",
      "action": "create",
      "offline_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "data": {
        "customer_id": 45,
        "collection_date": "2025-01-15",
        "amount": 2000,
        "payment_method": "cash",
        "currency": "AFN",
        "order_uuid": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
        "applied_to_order": 1,
        "notes": "Partial payment for today's order"
      }
    }
  ]
}
```

**Push Response:**

```json
{
  "results": [
    {
      "type": "customer_visit",
      "offline_uuid": "550e8400-e29b-41d4-a716-446655440000",
      "status": "created",
      "server_id": 789,
      "server_uuid": "server-uuid-visit-001",
      "server_time": "2025-01-15T10:30:05Z"
    },
    {
      "type": "order",
      "offline_uuid": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
      "status": "created",
      "server_id": 456,
      "server_uuid": "server-uuid-order-001",
      "server_time": "2025-01-15T10:30:06Z"
    },
    {
      "type": "collection",
      "offline_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "status": "created",
      "server_id": 321,
      "server_uuid": "server-uuid-col-001",
      "server_time": "2025-01-15T10:30:07Z"
    }
  ],
  "server_time": "2025-01-15T10:30:07Z",
  "conflicts": [],
  "rejected": []
}
```

### 4.3 Bulk GPS Upload

GPS data is high-volume (a point every 5-15 seconds) and uses a dedicated endpoint optimized for throughput.

**Endpoint:** `POST /api/v1/gps/locations`

```
┌──────────────────────────────────────────────────────────────┐
│                    GPS SYNC LIFECYCLE                         │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  GPS Fix Received (every 5-15 seconds)                      │
│         │                                                    │
│         ▼                                                    │
│  ┌────────────────────┐                                     │
│  │ Save to local_gps  │                                     │
│  │ _points table      │                                     │
│  └────────┬───────────┘                                     │
│           │                                                  │
│           ▼                                                  │
│  ┌────────────────────┐    ┌─────────────────────┐          │
│  │ Batch accumulator  │───>│ When batch reaches  │          │
│  │ (in-memory buffer) │    │ 50-100 points OR    │          │
│  └────────────────────┘    │ 5 min elapsed:      │          │
│                            │ flush to queue       │          │
│                            └──────────┬──────────┘          │
│                                       │                      │
│                                       ▼                      │
│                            ┌─────────────────────┐          │
│                            │ Add batch to        │          │
│                            │ local_sync_queue    │          │
│                            │ priority = 1        │          │
│                            └──────────┬──────────┘          │
│                                       │                      │
│                                       ▼                      │
│                            ┌─────────────────────┐          │
│                            │ POST /api/v1/gps/   │          │
│                            │ locations            │          │
│                            └──────────┬──────────┘          │
│                                       │                      │
│                                       ▼                      │
│                            ┌─────────────────────┐          │
│                            │ Server deduplicates │          │
│                            │ by client_uuid      │          │
│                            │ Returns accepted/   │          │
│                            │ rejected/duplicates │          │
│                            └──────────┬──────────┘          │
│                                       │                      │
│                                       ▼                      │
│                            ┌─────────────────────┐          │
│                            │ Delete uploaded     │          │
│                            │ points older than   │          │
│                            │ 7 days              │          │
│                            └─────────────────────┘          │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**GPS Upload Request:**

```json
{
  "batch_uuid": "gps-batch-550e8400",
  "device_id": "device-uuid-xyz",
  "locations": [
    {
      "client_uuid": "gps-point-001",
      "latitude": 34.5553,
      "longitude": 69.2075,
      "altitude": 1850.5,
      "accuracy": 10.5,
      "speed": 0,
      "heading": 180,
      "battery_level": 85,
      "is_charging": false,
      "network_status": "wifi",
      "is_mock_location": false,
      "provider": "gps",
      "recorded_at": "2025-01-15T10:30:00Z",
      "sequence_number": 1
    },
    {
      "client_uuid": "gps-point-002",
      "latitude": 34.5555,
      "longitude": 69.2077,
      "altitude": 1851.0,
      "accuracy": 8.2,
      "speed": 3.5,
      "heading": 185,
      "battery_level": 84,
      "is_charging": false,
      "network_status": "wifi",
      "is_mock_location": false,
      "provider": "gps",
      "recorded_at": "2025-01-15T10:30:05Z",
      "sequence_number": 2
    }
  ]
}
```

**GPS Upload Response:**

```json
{
  "accepted": 50,
  "rejected": 0,
  "duplicates": 3,
  "batch_id": 12345,
  "server_time": "2025-01-15T10:30:10Z"
}
```

**GPS Configuration (in `local_config`):**

| Config Key | Default | Description |
|------------|---------|-------------|
| `gps.interval_seconds` | 10 | GPS fix interval when moving |
| `gps.stationary_interval_seconds` | 60 | GPS fix interval when stationary |
| `gps.batch_size` | 50 | Points per upload batch |
| `gps.batch_timeout_seconds` | 300 | Flush batch after this timeout |
| `gps.local_retention_days` | 7 | Days to keep uploaded points locally |
| `gps.min_accuracy_meters` | 50 | Discard fixes with worse accuracy |
| `gps.mock_location_check` | true | Reject mock GPS locations |

### 4.4 Sync Order of Operations

When connectivity is restored, sync follows a strict order to maintain data integrity:

```
┌──────────────────────────────────────────────────────────────┐
│              RECONNECTION SYNC SEQUENCE                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Step 1: GPS BULK UPLOAD (immediate, highest priority)      │
│          - Flush all pending GPS batches                     │
│          - Non-blocking, can continue in background         │
│                                                              │
│  Step 2: PUSH TRANSACTIONAL DATA                             │
│          a. Visits (creates)                                 │
│          b. Orders + Order Items (creates)                   │
│          c. Collections (creates)                            │
│          d. Expenses (creates)                               │
│          e. Photos (uploads)                                 │
│          - Process in priority order                         │
│          - Wait for each batch response before next         │
│                                                              │
│  Step 3: PULL MASTER DATA                                    │
│          - Refresh customers, products, routes, prices       │
│          - Get any server-side changes (approvals, etc.)     │
│          - Update local records with server IDs             │
│                                                              │
│  Step 4: RESOLVE LINKAGES                                    │
│          - Update local records with server_id references   │
│          - e.g., order.server_id references                 │
│            customer.server_id (not customer.local_id)       │
│                                                              │
│  Step 5: RESUME NORMAL SCHEDULE                              │
│          - GPS: continuous background                        │
│          - Push: every 5 minutes                             │
│          - Pull: every 15 minutes                            │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## 5. Conflict Detection & Resolution

### 5.1 Conflict Resolution Strategy

The system uses a **hybrid resolution strategy**:

| Data Type | Authority | Resolution |
|-----------|-----------|------------|
| Master data (customers, products, routes) | **Server** | Server wins, client overwrites local |
| Transactions (visits, orders, collections) | **Client** (creator) | Idempotent, no conflict on create |
| Status fields (approval, delivery) | **Server** | Server wins, client accepts |
| Item details (order items, quantities) | **Client** (until submitted) | Client wins until server locks |
| GPS points | **Neither** | Deduplicated by UUID |

### 5.2 Conflict Scenarios

#### Scenario 1: Customer Updated While Offline

```
Timeline:
  T1: Office updates customer credit limit to 100,000 AFN
  T2: Salesman is offline, still sees old limit (50,000 AFN)
  T3: Salesman creates order for 75,000 AFN (within old limit)
  T4: Salesman comes online, push order
  T5: Pull customer data — discover new limit is 100,000 AFN

Resolution:
  - Server's customer data wins (server-authoritative)
  - Order is valid (75,000 < 100,000 new limit)
  - Client updates local customer data with server version
  - UI shows: "Customer credit limit was updated by office"
  - No conflict — order is accepted
```

#### Scenario 2: Duplicate Order Upload

```
Timeline:
  T1: Client sends order push
  T2: Network timeout (client doesn't receive response)
  T3: Client marks order as "pending" (retry)
  T4: Client retries — sends same order again

Resolution:
  - Server checks offline_uuid on both requests
  - First request: creates order, returns server_id
  - Second request: finds existing offline_uuid, returns existing
  - Client receives success for both — no duplicate created
  - Response status: "exists" (not "created")
```

#### Scenario 3: Visit Synced Twice (App Crash)

```
Timeline:
  T1: Visit synced successfully
  T2: App crashes before local record is updated
  T3: App restarts, visit still marked as "pending"
  T4: Sync retries visit

Resolution:
  - Server checks offline_uuid
  - Finds existing visit with same UUID
  - Returns existing visit data
  - Client updates local with server data
  - No duplicate visit created
```

#### Scenario 4: Order Modified Offline While Approved on Server

```
Timeline:
  T1: Salesman creates order, submits for approval
  T2: Salesman goes offline
  T3: Manager approves order on web dashboard
  T4: Salesman modifies order items offline
  T5: Salesman comes online, pushes modified order

Resolution:
  - Server returns conflict with both versions
  - Conflict payload includes:
    - Server version (approved, with approval metadata)
    - Client version (modified items)
  - Resolution rules:
    - "status", "approved_by", "approved_at": server wins
    - "items", "quantities", "notes": client's changes flagged for review
  - Manager notified of item changes post-approval
  - Order status set to "modified_post_approval"
```

#### Scenario 5: Collection Recorded Twice

```
Timeline:
  T1: Collection recorded, push initiated
  T2: Network error, retry scheduled
  T3: Collection pushed again before first response received

Resolution:
  - Same as duplicate order — idempotency by offline_uuid
  - Server returns "exists" for duplicate
  - No double-payment recorded
```

#### Scenario 6: GPS Points Uploaded Twice

```
Timeline:
  T1: GPS batch of 50 points uploaded
  T2: Server responds, but response lost
  T3: Client retries same batch

Resolution:
  - Each GPS point has unique client_uuid
  - Server checks for existing client_uuid
  - Duplicates silently ignored
  - Response: { accepted: 50, duplicates: 50, rejected: 0 }
```

### 5.3 Conflict Response Format

```json
{
  "results": [],
  "conflicts": [
    {
      "type": "order",
      "offline_uuid": "550e8400-e29b-41d4-a716-446655440000",
      "conflict_type": "field_level",
      "server_version": {
        "server_id": 456,
        "status": "approved",
        "approved_by": 5,
        "approved_at": "2025-01-15T09:00:00Z",
        "total_amount": 750
      },
      "client_version": {
        "status": "submitted",
        "total_amount": 900,
        "items": [
          { "product_id": 10, "quantity": 6, "line_total": 900 }
        ]
      },
      "conflicting_fields": ["status", "items", "total_amount"],
      "resolution": "server_wins_for_status, client_changes_flagged"
    }
  ]
}
```

---

## 6. Sync State Machine

### 6.1 Entity Lifecycle States

```
                    ┌─────────────┐
                    │  CREATED    │
                    │  LOCALLY    │
                    └──────┬──────┘
                           │
                           │ sync push initiated
                           ▼
                    ┌─────────────┐
              ┌─────│  SYNCING    │─────┐
              │     └──────┬──────┘     │
              │            │            │
     success  │     conflict     error  │
              │            │            │
              ▼            ▼            ▼
       ┌──────────┐ ┌──────────┐ ┌──────────┐
       │ SYNCED   │ │ CONFLICT │ │  FAILED  │
       └──────────┘ └────┬─────┘ └────┬─────┘
                         │            │
                    resolved     retry limit
                         │        exceeded
                         ▼            │
                   ┌──────────┐       │
                   │ RESOLVED │       │
                   └────┬─────┘       │
                        │             │
                        ▼             ▼
                   ┌──────────────────────┐
                   │     SYNCED           │
                   │  (or PERMANENTLY     │
                   │      FAILED)         │
                   └──────────────────────┘
```

### 6.2 Status Values

| Status | Description | Next State |
|--------|-------------|------------|
| `pending` | Created/modified locally, awaiting sync | `syncing` |
| `syncing` | Currently being uploaded to server | `synced`, `conflict`, or `failed` |
| `synced` | Successfully synced to server | Terminal (until next local edit) |
| `conflict` | Sync conflict detected, needs resolution | `resolved` or `synced` |
| `failed` | Sync failed after max retries | Manual intervention or `pending` (reset) |

> **Foundation scope:** The initial Android release ships a simplified three-state version: `pending`, `synced`, and `failed`. The `syncing`, `conflict`, and `resolved` transitions are part of the advanced Sync Engine phase.

### 6.3 State Transitions in Code

```
// Flutter pseudocode
enum SyncStatus {
  pending,
  syncing,
  synced,
  conflict,
  failed,
}

// Transition rules
SyncStatus transition(SyncStatus current, SyncEvent event) {
  switch (current) {
    case SyncStatus.pending:
      if (event == SyncEvent.pushStarted) return SyncStatus.syncing;

    case SyncStatus.syncing:
      if (event == SyncEvent.pushSuccess) return SyncStatus.synced;
      if (event == SyncEvent.conflict) return SyncStatus.conflict;
      if (event == SyncEvent.pushFailed) return SyncStatus.failed;

    case SyncStatus.conflict:
      if (event == SyncEvent.resolved) return SyncStatus.synced;
      if (event == SyncEvent.acceptedServer) return SyncStatus.synced;

    case SyncStatus.failed:
      if (event == SyncEvent.retry) return SyncStatus.pending;
      if (event == SyncEvent.manualReset) return SyncStatus.pending;

    case SyncStatus.synced:
      // Only transitions back on local edit
      if (event == SyncEvent.localEdit) return SyncStatus.pending;
  }
  return current; // no valid transition
}
```

---

## 7. Connectivity Handling

### 7.1 Network States

```dart
// Flutter connectivity states
enum NetworkState {
  online,      // Connected with internet access
  offline,     // No network connection
  limited,     // Connected but no internet (e.g., captive portal, local network only)
}
```

### 7.2 Behavior Matrix

| State | GPS Collection | Local Writes | Sync Push | Sync Pull | Photo Upload |
|-------|---------------|--------------|-----------|-----------|--------------|
| **Online** | Continuous (5-15s) | Immediate | Every 5 min | Every 15 min | Immediate after capture |
| **Limited** | Continuous (5-15s) | Immediate | Attempt, handle timeout | Attempt, handle timeout | Queue for later |
| **Offline** | Continuous (5-15s) | Immediate | Queue only | Queue only | Queue for later |

### 7.3 Connectivity Detection

```
┌──────────────────────────────────────────────────────────────┐
│                CONNECTIVITY DETECTION                         │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Flutter Connectivity Plugin                                 │
│  ┌────────────────────────────────────┐                     │
│  │ Monitor: connection.status         │                     │
│  │ Check interval: 30 seconds         │                     │
│  └──────────────┬─────────────────────┘                     │
│                 │                                            │
│                 ▼                                            │
│  ┌────────────────────────────────────┐                     │
│  │ On state change:                   │                     │
│  │   offline → online                 │                     │
│  │     → trigger reconnection sync    │                     │
│  │                                    │                     │
│  │   online → offline                 │                     │
│  │     → pause sync, queue locally    │                     │
│  │     → show offline indicator       │                     │
│  │                                    │                     │
│  │   online → limited                 │                     │
│  │     → attempt sync with timeout    │                     │
│  │     → show degraded indicator      │                     │
│  └────────────────────────────────────┘                     │
│                                                              │
│  Additional check:                                           │
│  ┌────────────────────────────────────┐                     │
│  │ Ping: GET /api/v1/health           │                     │
│  │ Timeout: 5 seconds                 │                     │
│  │ Purpose: confirm actual internet   │                     │
│  │   (WiFi connected ≠ internet)      │                     │
│  └────────────────────────────────────┘                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 7.4 Reconnection Recovery Sequence

```
Connectivity restored
        │
        ▼
┌───────────────────┐
│ 1. Health check   │
│    GET /health    │
└────────┬──────────┘
         │ success
         ▼
┌───────────────────┐
│ 2. GPS bulk flush │  (background, non-blocking)
│    Upload all     │
│    queued points  │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ 3. Sync push      │  (foreground, shows progress)
│    Upload pending │
│    transactions   │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ 4. Sync pull      │  (foreground, shows progress)
│    Refresh master │
│    data           │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ 5. Resume normal  │
│    sync schedule  │
└───────────────────┘
```

---

## 8. Retry Strategy

### 8.1 Exponential Backoff

```
┌──────────────────────────────────────────────────────────────┐
│                 RETRY BACKOFF SCHEDULE                        │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Attempt 1:  Immediate (0 seconds)                          │
│  Attempt 2:  5 seconds                                      │
│  Attempt 3:  30 seconds                                     │
│  Attempt 4:  2 minutes                                      │
│  Attempt 5:  10 minutes                                     │
│  Attempt 6:  30 minutes (GPS only)                          │
│  Attempt 7:  1 hour (GPS only)                              │
│  Attempt 8:  2 hours (GPS only)                             │
│  Attempt 9:  4 hours (GPS only)                             │
│  Attempt 10: 8 hours (GPS only)                             │
│                                                              │
│  Formula: delay = min(base * 2^attempt, max_delay)          │
│  + jitter: random(0, delay * 0.1)                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 8.2 Max Retries by Entity Type

| Entity Type | Max Retries | Rationale |
|-------------|-------------|-----------|
| GPS points | 10 | Low priority, data can be approximated from other points |
| Photos | 5 | Medium priority, can be re-captured |
| Visits | 5 | High priority, core business data |
| Orders | 5 | Critical, drives revenue |
| Collections | 5 | Critical, financial data |
| Expenses | 3 | Lower priority, can be submitted later |
| Master data pull | 3 | Will retry on next interval anyway |

### 8.3 Retry Conditions

```
┌──────────────────────────────────────────────────────────────┐
│                   RETRY DECISION MATRIX                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Error Type              │ Retry?  │ Action                  │
│  ────────────────────────│─────────│────────────────────────│
│  Network error           │ Yes     │ Retry with backoff     │
│  Timeout                 │ Yes     │ Retry with backoff     │
│  Server 5xx              │ Yes     │ Retry with backoff     │
│  Server 429 (rate limit) │ Yes     │ Retry after Retry-After│
│  Server 400 (validation) │ No      │ Mark failed, log error │
│  Server 401 (auth)       │ No      │ Mark failed, re-auth   │
│  Server 403 (forbidden)  │ No      │ Mark failed, notify    │
│  Server 404 (not found)  │ No      │ Mark failed, flag data │
│  Server 409 (conflict)   │ No      │ Surface conflict       │
│  Server 422 (unprocessable)│ No    │ Mark failed, log error │
│  Server 500 (internal)   │ Yes     │ Retry with backoff     │
│  Server 502 (bad gateway)│ Yes     │ Retry with backoff     │
│  Server 503 (unavailable)│ Yes     │ Retry with backoff     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 8.4 Retry Implementation

```sql
-- Find items ready for retry
SELECT * FROM local_sync_queue
WHERE status = 'pending'
  AND attempts < max_attempts
  AND (next_retry_at IS NULL OR next_retry_at <= datetime('now'))
ORDER BY priority, created_at
LIMIT 50;
```

```dart
// Flutter retry logic pseudocode
Future<void> processRetry(SyncQueueItem item) async {
  final delay = calculateBackoff(item.attempts);
  
  if (item.attempts >= item.maxAttempts) {
    await markPermanentlyFailed(item);
    return;
  }
  
  if (item.nextRetryAt != null && item.nextRetryAt!.isAfter(DateTime.now())) {
    return; // not ready for retry yet
  }
  
  await updateStatus(item, SyncStatus.syncing);
  
  try {
    final result = await syncService.pushEntity(item);
    await handleSyncResult(item, result);
  } on NetworkException catch (e) {
    await scheduleRetry(item, delay);
  } on ServerException catch (e) {
    if (e.statusCode >= 500) {
      await scheduleRetry(item, delay);
    } else {
      await markFailed(item, e.message);
    }
  }
}

Duration calculateBackoff(int attempt) {
  const base = Duration(seconds: 5);
  const maxDelay = Duration(hours: 8);
  final delay = base * pow(2, attempt);
  final jitter = Random().nextDouble() * (delay.inMilliseconds * 0.1);
  return Duration(milliseconds: delay.inMilliseconds + jitter.toInt());
}
```

---

## 9. Photo Handling

### 9.1 Photo Capture Flow

```
┌──────────────────────────────────────────────────────────────┐
│                   PHOTO CAPTURE FLOW                          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  User taps "Take Photo"                                     │
│         │                                                    │
│         ▼                                                    │
│  ┌────────────────────┐                                     │
│  │ Camera opens       │                                     │
│  │ (works offline)    │                                     │
│  └────────┬───────────┘                                     │
│           │                                                  │
│           ▼                                                  │
│  ┌────────────────────┐                                     │
│  │ Photo captured     │                                     │
│  │ Saved to local     │                                     │
│  │ device storage     │                                     │
│  └────────┬───────────┘                                     │
│           │                                                  │
│           ▼                                                  │
│  ┌────────────────────────────────────────┐                 │
│  │ Save metadata to local_photos table:   │                 │
│  │   entity_type: 'visit'                 │                 │
│  │   entity_uuid: 'visit-uuid-123'        │                 │
│  │   filename: 'IMG_20250115_103000.jpg'  │                 │
│  │   local_path: '/storage/.../IMG_...'   │                 │
│  │   mime_type: 'image/jpeg'              │                 │
│  │   file_size: 2456789                   │                 │
│  │   taken_at: '2025-01-15T10:30:00Z'    │                 │
│  │   latitude: 34.5553                    │                 │
│  │   longitude: 69.2075                   │                 │
│  │   sync_status: 'pending'               │                 │
│  └────────┬───────────────────────────────┘                 │
│           │                                                  │
│           ▼                                                  │
│  ┌────────────────────┐                                     │
│  │ Photo queued for   │                                     │
│  │ upload with entity │                                     │
│  └────────────────────┘                                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 9.2 Photo Upload

```
POST /api/v1/photos/upload
Content-Type: multipart/form-data

Fields:
  entity_type: "visit"
  entity_id: 789                    // server ID (after entity sync)
  offline_uuid: "photo-uuid-123"    // for idempotency
  taken_at: "2025-01-15T10:30:00Z"
  latitude: 34.5553
  longitude: 69.2075
  photo: <binary data>

Response:
{
  "id": 456,
  "url": "https://cdn.example.com/photos/tenant/2025/01/15/abc123.jpg",
  "thumbnail_url": "https://cdn.example.com/photos/tenant/2025/01/15/abc123_thumb.jpg",
  "file_size": 2456789
}
```

### 9.3 Photo Upload Strategy

```
Photos are uploaded AFTER their parent entity is synced:

1. Visit syncs → server returns server_id = 789
2. Photo upload uses entity_id = 789
3. If photo upload fails:
   - Photo stays in local_photos with sync_status = 'failed'
   - Retried on next sync cycle
   - Parent entity is not affected

Photo compression:
  - Before upload, compress to max 1920px width
  - JPEG quality: 80%
  - Target file size: < 500KB
  - Original kept locally until upload confirmed

Photo retention:
  - After successful upload: delete local file after 24 hours
  - Configurable: keep_local_after_upload = true/false
  - Manual cleanup: Settings > Storage > Clear Uploaded Photos
```

### 9.4 Photo Storage Monitoring

```sql
-- Calculate total photo storage usage
SELECT 
  COUNT(*) as total_photos,
  SUM(file_size) as total_bytes,
  SUM(CASE WHEN sync_status = 'uploaded' THEN file_size ELSE 0 END) as uploaded_bytes,
  SUM(CASE WHEN sync_status = 'pending' THEN file_size ELSE 0 END) as pending_bytes
FROM local_photos
WHERE deleted_at IS NULL;
```

---

## 10. Data Freshness Strategy

### 10.1 Refresh Intervals

| Data Type | Refresh Interval | Trigger | Priority |
|-----------|-----------------|---------|----------|
| Products | Daily + app startup | Pull | High |
| Price Lists | Daily + app startup | Pull | High |
| Customers | Hourly + app startup | Pull | High |
| Routes | Daily + on assignment | Pull | Medium |
| Visit Plans | Daily + app startup | Pull | Medium |
| Territories | Weekly | Pull | Low |
| User Profile | On login | Pull | High |
| App Config | On login + daily | Pull | Medium |

### 10.2 Freshness Indicators

```
┌──────────────────────────────────────────────────────────────┐
│                 DATA FRESHNESS UI                             │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Status Bar (always visible):                               │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ● Online | Last sync: 3 min ago | 2 pending       │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  Data Freshness Badge (per section):                        │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Products (synced 2 hours ago)          [Refresh]   │     │
│  │ Customers (synced 15 min ago)          [Refresh]   │     │
│  │ Routes (synced 1 day ago)              [Refresh]   │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  Stale Data Warning:                                        │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ⚠ Product catalog may be outdated                  │     │
│  │   Last updated: 3 days ago                         │     │
│  │   [Sync Now]                                       │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  Offline Mode Banner:                                       │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ⚠ Offline Mode — Data may be outdated             │     │
│  │   5 visits pending sync | 2 orders pending sync   │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 10.3 Freshness Thresholds (Configurable)

```json
{
  "freshness_thresholds": {
    "products_hours": 24,
    "customers_hours": 2,
    "price_lists_hours": 24,
    "routes_hours": 24,
    "visit_plans_hours": 12,
    "territories_hours": 168,
    "warning_after_stale": true,
    "force_refresh_option": true
  }
}
```

---

## 11. Sync UI in Mobile App

### 11.1 Sync Status Bar

The sync status bar is a persistent UI element shown at the top or bottom of the app.

```
┌──────────────────────────────────────────────────────────────┐
│                   SYNC STATUS STATES                          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  STATE: ALL SYNCED                                          │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ● All data synced                                  │     │
│  │   Last sync: Jan 15, 10:30 AM                      │     │
│  └────────────────────────────────────────────────────┘     │
│  Color: Green                                               │
│                                                              │
│  STATE: SYNCING                                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ◐ Syncing... 3 of 7 items                          │     │
│  │   ████████████░░░░░░░░░░░░ 43%                     │     │
│  └────────────────────────────────────────────────────┘     │
│  Color: Blue, animated progress bar                         │
│                                                              │
│  STATE: PENDING                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ○ 5 items pending sync                             │     │
│  │   3 visits | 1 order | 1 collection               │     │
│  └────────────────────────────────────────────────────┘     │
│  Color: Orange                                              │
│                                                              │
│  STATE: OFFLINE                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ○ Offline — 8 items queued for sync                │     │
│  │   All features available offline                   │     │
│  └────────────────────────────────────────────────────┘     │
│  Color: Gray                                                │
│                                                              │
│  STATE: SYNC ERROR                                           │
│  ┌────────────────────────────────────────────────────┐     │
│  │ ✕ 2 items failed to sync                           │     │
│  │   Tap for details                          [Retry] │     │
│  └────────────────────────────────────────────────────┘     │
│  Color: Red                                                 │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 11.2 Pull-to-Refresh

Every list view supports pull-to-refresh which triggers a sync pull for that data type:

```
Pull down on Customers list
    → Triggers: GET /api/v1/sync/pull?types[]=customers
    → Shows loading spinner
    → Updates local database
    → Refreshes list view
    → Shows "Updated" toast
```

### 11.3 Manual Sync Screen

Accessible from Settings > Sync:

```
┌──────────────────────────────────────────────────────────────┐
│                    SYNC SETTINGS                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Sync Status                                        │     │
│  │ ● Online | Last sync: 3 min ago                    │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Pending Items                                      │     │
│  │ Visits:        3 pending                           │     │
│  │ Orders:        1 pending                           │     │
│  │ Collections:   1 pending                           │     │
│  │ GPS Points:    1,247 queued (12 batches)           │     │
│  │ Photos:        5 pending upload                    │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Data Freshness                                     │     │
│  │ Products:      ✓ 2 hours ago                       │     │
│  │ Customers:     ✓ 15 min ago                        │     │
│  │ Price Lists:   ✓ 2 hours ago                       │     │
│  │ Routes:        ⚠ 2 days ago                        │     │
│  │ Visit Plans:   ✓ 1 hour ago                        │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  [ Sync Now ]          [ Force Full Refresh ]               │
│                                                              │
│  ───────────────────────────────────────────────────────    │
│                                                              │
│  Sync Log (last 5):                                         │
│  • 10:30 AM — Push: 3 items synced successfully             │
│  • 10:30 AM — Pull: 15 customers updated                    │
│  • 10:25 AM — GPS: 50 points uploaded                       │
│  • 10:15 AM — Push: 1 item failed (network error)           │
│  • 10:00 AM — Pull: 8 products updated                      │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 11.4 Per-Record Sync Indicator

Each transactional record shows its sync status:

```
┌──────────────────────────────────────────┐
│  Visit: Kabul Grocery Store              │
│  Jan 15, 9:30 AM — Successful           │
│  ● Synced                                │  ← Green dot
└──────────────────────────────────────────┘

┌──────────────────────────────────────────┐
│  Order: ORD-1705312200-A1B2             │
│  Jan 15, 9:45 AM — 750 AFN             │
│  ○ Pending sync                          │  ← Orange dot
└──────────────────────────────────────────┘

┌──────────────────────────────────────────┐
│  Collection: 2,000 AFN                   │
│  Jan 15, 10:00 AM — Cash                 │
│  ✕ Sync failed — tap to retry            │  ← Red dot
└──────────────────────────────────────────┘
```

---

## 12. Data Storage Limits

### 12.1 Local Retention Policies

| Data Type | Retention | Cleanup Action |
|-----------|-----------|----------------|
| GPS points (uploaded) | 7 days | DELETE from local_gps_points |
| GPS points (pending) | Indefinite | Keep until uploaded |
| Sync queue (synced items) | 7 days | DELETE from local_sync_queue |
| Sync queue (failed items) | 30 days | DELETE, log to sync_log |
| Sync log | 30 days | DELETE old entries |
| Photos (uploaded) | 24 hours | DELETE local file, keep metadata |
| Photos (pending) | Indefinite | Keep until uploaded |
| Master data | Indefinite | Full refresh on fresh install |
| Transactional data | Indefinite | Keep locally forever (audit trail) |

### 12.2 Storage Monitoring

```sql
-- Monitor total database size
-- SQLite: PRAGMA page_count * PRAGMA page_size

-- Track entity counts
SELECT 
  'gps_points' as entity,
  COUNT(*) as total,
  SUM(CASE WHEN sync_status = 'uploaded' THEN 1 ELSE 0 END) as synced,
  SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending
FROM local_gps_points
UNION ALL
SELECT 
  'photos',
  COUNT(*),
  SUM(CASE WHEN sync_status = 'uploaded' THEN 1 ELSE 0 END),
  SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END)
FROM local_photos
UNION ALL
SELECT 
  'sync_queue',
  COUNT(*),
  SUM(CASE WHEN status = 'synced' THEN 1 ELSE 0 END),
  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)
FROM local_sync_queue;
```

### 12.3 Low Storage Handling

```
Storage monitoring runs on app startup and periodically:

1. Check available device storage
2. If < 100MB free:
   - Show warning: "Low storage — some features may be limited"
   - Aggressively clean uploaded photos
   - Clean old GPS points (keep 3 days instead of 7)
   - Clean old sync queue items

3. If < 50MB free:
   - Show critical warning
   - Clean all uploaded photos immediately
   - Clean all synced GPS points
   - Reduce GPS recording interval
   - Prompt user to free space

4. If < 10MB free:
   - Emergency mode
   - Stop GPS recording
   - Stop photo capture
   - Only allow text-based data entry
   - Show: "Storage critically low — sync to free space"
```

### 12.4 Database Size Estimates

| Data Type | Record Size | 1000 Records | Notes |
|-----------|-------------|---------------|-------|
| GPS point | ~200 bytes | ~200 KB | With all fields |
| Visit | ~500 bytes | ~500 KB | With notes |
| Order + items | ~1 KB | ~1 MB | Depends on item count |
| Collection | ~300 bytes | ~300 KB | |
| Photo metadata | ~200 bytes | ~200 KB | Not including binary |
| Sync queue entry | ~1 KB | ~1 MB | Includes JSON payload |

**Estimated daily usage (active salesman):**
- GPS points: ~5,000 points × 200 bytes = ~1 MB/day
- Visits: ~15 visits × 500 bytes = ~7.5 KB/day
- Orders: ~10 orders × 1 KB = ~10 KB/day
- Collections: ~5 collections × 300 bytes = ~1.5 KB/day
- **Total: ~1 MB/day transactional data**

---

## 13. Edge Cases

### 13.1 Device Reset

```
Scenario: User factory resets phone or reinstalls app

Impact:
  ✗ All local data lost
  ✗ GPS history lost (server has it)
  ✗ Unsynced transactions lost

Recovery:
  1. User logs in fresh
  2. Full master data sync (all products, customers, routes, etc.)
  3. No local transactional data (already synced or lost)
  4. GPS history preserved on server
  5. Server shows any pending approvals for lost orders

Prevention:
  - Regular background sync minimizes data at risk
  - Most data synced within 5 minutes of creation (when online)
  - Offline-only data: limited to current day's work typically
```

### 13.2 Multiple Devices

```
Scenario: Salesman uses phone + tablet

Behavior:
  - Each device syncs independently
  - Same offline_uuid prevented by server idempotency
  - Different devices create different records (different UUIDs)
  - Master data is same on both (synced from same server)

Conflict potential:
  - Device A creates order for Customer X
  - Device B creates different order for Customer X
  - Both sync successfully (different UUIDs, different records)
  - No conflict — two separate orders

Edge case:
  - Device A and B both create visit for same customer at same time
  - Both sync successfully
  - Manager sees two visits — flagged as "duplicate visit" on server
  - Manager reviews and merges if needed
```

### 13.3 Clock Skew

```
Scenario: Device clock is wrong (manual time set, timezone error)

Impact:
  - GPS timestamps may be in future or past
  - Visit check-in/out times may be incorrect
  - Sync timestamps may confuse cursor-based pull

Server-side handling:
  - Server validates recorded_at timestamps
  - Flags suspicious anomalies:
    * recorded_at > received_at + 5 minutes (future timestamp)
    * recorded_at < received_at - 24 hours (very old timestamp)
    * visit duration > 24 hours (impossible)
  - Uses received_at (server time) as fallback
  - Logs anomaly for admin review

Client-side prevention:
  - App checks device time on startup
  - Warns if device time differs from server time by > 5 minutes
  - Uses NTP-synced server time for sync cursors
  - Stores both device_time and server_time for each record
```

### 13.4 Battery Dead During Sync

```
Scenario: Device battery dies mid-sync

Impact:
  - Partial sync may have completed
  - Some entities synced, others not

Recovery:
  - Sync queue preserves pending items
  - Already-synced items marked as synced (server confirmed)
  - Partially-synced items: server either accepted or rejected
  - On next sync: picks up where left off
  - No data loss (items in queue until explicitly synced)

Prevention:
  - Sync is designed to be atomic per batch
  - Each entity sync is independent
  - No partial states that corrupt data
```

### 13.5 Storage Full During Photo Capture

```
Scenario: Device storage full when trying to take photo

Behavior:
  1. Camera app may fail to save
  2. Flutter catches the error
  3. Shows: "Storage full — cannot save photo"
  4. Suggests: "Sync data to free space" or "Delete old photos"
  5. Other data entry (text, GPS) continues working
```

### 13.6 Server Migration / Schema Change

```
Scenario: Server API changes during app update

Behavior:
  - API versioning (v1, v2) prevents breaking changes
  - App checks server API version on startup
  - If major version mismatch: prompt app update
  - If minor version change: backward-compatible, graceful degradation
  - Sync protocol includes client app version in requests
  - Server can adapt response format based on client version
```

### 13.7 Tenant Data Isolation

```
Scenario: Multi-tenant — ensure no data leaks between tenants

Behavior:
  - Every local record has tenant_id
  - Every API request includes tenant context (auth token)
  - Server filters all queries by tenant_id
  - Pull only returns data for authenticated tenant
  - Push validates tenant_id matches auth token
  - SQLite queries always include WHERE tenant_id = ?
```

---

## Appendix A: API Endpoint Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/v1/sync/pull` | Pull master data changes |
| `POST` | `/api/v1/sync/push` | Push transactional data |
| `POST` | `/api/v1/gps/locations` | Bulk GPS upload |
| `POST` | `/api/v1/photos/upload` | Photo upload |
| `GET` | `/api/v1/health` | Connectivity check |
| `POST` | `/api/v1/auth/refresh` | Token refresh |
| `GET` | `/api/v1/sync/status` | Server sync status |

## Appendix B: Configuration Defaults

```json
{
  "sync": {
    "pull_interval_minutes": 15,
    "push_interval_minutes": 5,
    "gps_batch_size": 50,
    "gps_batch_timeout_seconds": 300,
    "max_retry_attempts": 5,
    "retry_base_delay_seconds": 5,
    "retry_max_delay_seconds": 3600,
    "tombstone_retention_days": 30,
    "gps_retention_days": 7,
    "photo_retention_after_upload_hours": 24,
    "sync_queue_retention_days": 7,
    "freshness_warning_hours": {
      "products": 24,
      "customers": 2,
      "price_lists": 24,
      "routes": 24
    }
  },
  "gps": {
    "interval_seconds": 10,
    "stationary_interval_seconds": 60,
    "min_accuracy_meters": 50,
    "mock_location_check": true,
    "battery_saver_interval_seconds": 30
  },
  "storage": {
    "low_storage_warning_mb": 100,
    "critical_storage_mb": 50,
    "emergency_storage_mb": 10
  }
}
```

## Appendix C: Glossary

| Term | Definition |
|------|------------|
| **offline_uuid** | UUID v4 generated by the client device, used as idempotency key |
| **server_id** | Auto-increment integer ID assigned by the server |
| **server_uuid** | UUID assigned by the server (may differ from offline_uuid) |
| **sync cursor** | Timestamp tracking the last successful sync per entity type |
| **tombstone** | A deleted record marker with `deleted_at` timestamp |
| **idempotency** | Property ensuring duplicate requests produce the same result |
| **pull** | Server-to-client data transfer (master data refresh) |
| **push** | Client-to-server data transfer (transactional data upload) |
| **batch** | Group of records uploaded together (especially GPS points) |
| **backoff** | Increasing delay between retry attempts |
