# GPS Tracking Design — Field Sales Platform

> **Status:** Planning Only
> **Market:** Afghanistan (multi-tenant SaaS)
> **Stack:** Flutter Android · MySQL · Redis · Laravel API

---

## Table of Contents

1. [GPS Tracking Overview](#1-gps-tracking-overview)
2. [GPS Data Model](#2-gps-data-model)
3. [Storage Architecture](#3-storage-architecture)
4. [GPS Collection Flow](#4-gps-collection-flow)
5. [GPS Quality & Filtering](#5-gps-quality--filtering)
6. [Current Location Strategy](#6-current-location-strategy)
7. [Route Reconstruction](#7-route-reconstruction)
8. [Live Map Architecture](#8-live-map-architecture)
9. [Geofencing](#9-geofencing)
10. [Suspicious GPS Indicators](#10-suspicious-gps-indicators)
11. [Retention & Archival](#11-retention--archival)
12. [Indexing Strategy](#12-indexing-strategy)
13. [Scale Projections](#13-scale-projections)
14. [Configuration Options](#14-configuration-options-per-company)

---

## 1. GPS Tracking Overview

### Purpose

- **Verify salesman locations** during work hours — confirm salesmen are where they claim to be.
- **Live map for managers** — real-time visibility into field team positions.
- **Visit GPS verification** — validate that customer check-ins occur at actual customer locations.
- **Route reconstruction** — replay a salesman's daily path for performance analysis and accountability.
- **Fraud detection** — identify mock locations, impossible travel speeds, and suspicious patterns.

### Design Principles

| Principle | Detail |
|---|---|
| **Track only during permitted work periods** | GPS collection is gated by work session state and company policy. No tracking outside allowed windows. |
| **Transparent to salesmen** | The app clearly indicates when tracking is active. Salesmen can view their own location history. |
| **Company-configurable rules** | Each tenant controls intervals, retention, enforcement strictness, and geofence radii. |
| **Privacy-aware retention** | Location data is deleted after the configurable retention period. No indefinite storage. |
| **Efficient storage for scale** | Batch inserts, UPSERT for current state, Redis caching, and future partitioning keep costs predictable. |

---

## 2. GPS Data Model

### Location Point Fields

```
Field                 Type                     Notes
─────────────────────────────────────────────────────────────────────
tenant_id             bigint (FK)              Tenant isolation
user_id               bigint (FK)              Who generated this point
salesman_id           bigint (FK, nullable)    Salesman reference (null for non-salesman users)
device_id             varchar(64)              Device identifier
latitude              decimal(10,7)            ~1 cm precision
longitude             decimal(10,7)            ~1 cm precision
horizontal_accuracy   float                    Meters
altitude              float (nullable)         Meters above sea level
speed                 float (nullable)         Meters/second
heading               float (nullable)         Degrees 0–360
battery_level         tinyint unsigned (null)  0–100
is_charging           boolean                  Charging state
network_status        enum                     wifi | cellular | offline
is_mock_location      boolean                  Android mock location detection
provider              enum                     gps | network | fused
recorded_at           datetime (UTC)           When device recorded this point
received_at           datetime (UTC)           When server received this point
sync_batch_id         bigint (FK, nullable)    Sync batch reference
sequence_number       integer                  Ordering within batch
metadata              json (nullable)          Extensible payload
```

### Data Type Rationale

| Field | Type | Reasoning |
|---|---|---|
| `latitude` / `longitude` | `DECIMAL(10,7)` | ~1 cm precision — sufficient for pedestrian and vehicle tracking. Avoids floating-point rounding. |
| `speed` | `FLOAT` | Meters/second. Sufficient precision; rarely exceeds 50 m/s for salesmen. |
| `horizontal_accuracy` | `FLOAT` | Meters. Used for filtering and confidence scoring. |
| `battery_level` | `TINYINT UNSIGNED` | 0–100 range fits in 1 byte. |
| `heading` | `FLOAT` | Degrees 0–360. Used for route direction display. |

---

## 3. Storage Architecture

The system uses a three-tier storage strategy: a small **current state** table, a high-volume **history** table, and a **Redis cache** for the live map.

### 3.1 Current Locations Table

**Purpose:** One row per user. Always reflects the most recent known position. Queried for live map display.

```sql
CREATE TABLE current_locations (
    id                  bigint unsigned AUTO_INCREMENT PRIMARY KEY,
    tenant_id           bigint unsigned NOT NULL,
    user_id             bigint unsigned NOT NULL,
    salesman_id         bigint unsigned NULL,
    device_id           varchar(64) NOT NULL,
    latitude            decimal(10,7) NOT NULL,
    longitude           decimal(10,7) NOT NULL,
    horizontal_accuracy float NULL,
    altitude            float NULL,
    speed               float NULL,
    heading             float NULL,
    battery_level       tinyint unsigned NULL,
    is_charging         boolean NOT NULL DEFAULT false,
    network_status      enum('wifi','cellular','offline') NOT NULL DEFAULT 'offline',
    is_mock_location    boolean NOT NULL DEFAULT false,
    provider            enum('gps','network','fused') NOT NULL DEFAULT 'gps',
    recorded_at         datetime NOT NULL,
    received_at         datetime NOT NULL,
    updated_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_current_locations_tenant_user (tenant_id, user_id),
    INDEX idx_current_locations_tenant (tenant_id)
) ENGINE=InnoDB;
```

**Update Strategy:** UPSERT on each GPS point.

```sql
INSERT INTO current_locations
    (tenant_id, user_id, salesman_id, device_id, latitude, longitude, ...)
VALUES (?, ?, ?, ?, ?, ?, ...)
ON DUPLICATE KEY UPDATE
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    recorded_at = VALUES(recorded_at),
    received_at = VALUES(received_at);
```

### 3.2 Location History Table (High Volume)

**Purpose:** Complete historical log of all GPS points. Append-only.

```sql
CREATE TABLE location_history (
    id                  bigint unsigned AUTO_INCREMENT PRIMARY KEY,
    tenant_id           bigint unsigned NOT NULL,
    user_id             bigint unsigned NOT NULL,
    salesman_id         bigint unsigned NULL,
    device_id           varchar(64) NOT NULL,
    latitude            decimal(10,7) NOT NULL,
    longitude           decimal(10,7) NOT NULL,
    horizontal_accuracy float NULL,
    altitude            float NULL,
    speed               float NULL,
    heading             float NULL,
    battery_level       tinyint unsigned NULL,
    is_charging         boolean NOT NULL DEFAULT false,
    network_status      enum('wifi','cellular','offline') NOT NULL DEFAULT 'offline',
    is_mock_location    boolean NOT NULL DEFAULT false,
    provider            enum('gps','network','fused') NOT NULL DEFAULT 'gps',
    recorded_at         datetime NOT NULL,
    received_at         datetime NOT NULL,
    sync_batch_id       bigint unsigned NULL,
    sequence_number     int NOT NULL DEFAULT 0,
    metadata            json NULL,
    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_location_history_tenant_user_time (tenant_id, user_id, recorded_at),
    INDEX idx_location_history_recorded_at (recorded_at),
    INDEX idx_location_history_sync_batch (sync_batch_id)
) ENGINE=InnoDB;
```

**Write Pattern:** Batch inserts — 50–100 points per query.

**Read Patterns:**
- Route reconstruction: `WHERE tenant_id = X AND user_id = Y AND recorded_at BETWEEN start AND end`
- Daily summary: `WHERE tenant_id = X AND recorded_at BETWEEN day_start AND day_end`
- Analytics: Aggregated by `tenant_id` and date.

### 3.3 Location Sync Batches

**Purpose:** Track GPS upload batches for debugging and reconciliation.

```sql
CREATE TABLE location_sync_batches (
    id              bigint unsigned AUTO_INCREMENT PRIMARY KEY,
    tenant_id       bigint unsigned NOT NULL,
    device_id       varchar(64) NOT NULL,
    user_id         bigint unsigned NOT NULL,
    batch_uuid      char(36) NOT NULL,
    point_count     int unsigned NOT NULL,
    received_at     datetime NOT NULL,
    processed_at    datetime NULL,
    created_at      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_sync_batches_uuid (batch_uuid),
    INDEX idx_sync_batches_tenant_time (tenant_id, received_at)
) ENGINE=InnoDB;
```

### 3.4 Redis Latest Locations Cache

**Purpose:** Fast access for live map without hitting MySQL on every poll.

**Key Pattern:**

```
fs:{tenant_id}:locations:latest
```

**Data Structure:** Redis Hash — one field per user.

| Field | Value (JSON) |
|---|---|
| `user_id` | `{ "user_id": 5, "salesman_id": 12, "name": "Ahmad", "latitude": 34.5553, "longitude": 69.2075, "accuracy": 10.5, "speed": 0, "heading": 180, "battery_level": 85, "is_charging": false, "is_mock_location": false, "recorded_at": "2025-01-15T10:30:00Z", "status": "online", "current_customer_id": null, "device_model": "Samsung S21" }` |

**TTL:** None. Stale entries are handled by the `status` logic in Section 8. If a user has not reported in for > 15 minutes, the status field flips to `offline`.

**Update:** On each GPS point received, the server runs `HSET fs:{tenant_id}:locations:latest {user_id} {json}`.

---

## 4. GPS Collection Flow

### 4.1 Device-Side Collection

```
┌─────────────────────────────────────────────────┐
│              FLUTTER APP (ANDROID)               │
├─────────────────────────────────────────────────┤
│                                                 │
│  1. Is work session active?                     │
│     NO  → Collect at minimal rate (or not at all)│
│     YES → Continue                              │
│                                                 │
│  2. Is tracking policy enabled?                 │
│     NO  → Don't collect GPS                     │
│     YES → Continue                              │
│                                                 │
│  3. Determine collection interval:              │
│     • Moving  (speed > 1 m/s):  every 15s       │
│     • Stationary (speed < 1 m/s): every 60s     │
│     (Intervals configurable per company)        │
│                                                 │
│  4. Read device sensors:                        │
│     lat, lng, accuracy, altitude, speed,        │
│     heading, battery_level, is_charging,        │
│     network_status, is_mock_location            │
│                                                 │
│  5. Store point in local SQLite:                │
│     • local_gps_points table                    │
│     • Assign client_uuid + sequence_number      │
│                                                 │
│  6. Upload decision:                            │
│     • Online  → batch upload (50 pts or 5 min)  │
│     • Offline → store locally, retry later      │
│                                                 │
└─────────────────────────────────────────────────┘
```

### 4.2 Server-Side Processing

```
┌─────────────────────────────────────────────────┐
│         POST /api/v1/gps/locations              │
│         (Batch of up to 100 points)             │
├─────────────────────────────────────────────────┤
│                                                 │
│  1. Validate request:                           │
│     • Auth token valid                          │
│     • Device not revoked                        │
│     • Work session active (if enforcement on)   │
│     • Batch size ≤ 100                          │
│                                                 │
│  2. For each GPS point:                         │
│     a. Check client_uuid for duplicates         │
│     b. Validate coordinates in bounds           │
│     c. Validate timestamp (±5 min tolerance)    │
│     d. Check is_mock_location flag              │
│                                                 │
│  3. Batch INSERT INTO location_history           │
│                                                 │
│  4. UPSERT current_locations (per user)         │
│                                                 │
│  5. UPDATE Redis latest-locations hash          │
│                                                 │
│  6. Fraud checks:                               │
│     • Mock location → flag                      │
│     • Accuracy > 100m → flag                    │
│     • Timestamp anomaly → flag                  │
│     • Speed anomaly → flag                      │
│     → Queue FraudIndicator job if flagged       │
│                                                 │
│  7. Record sync batch                           │
│                                                 │
│  8. Return response:                            │
│     { accepted: N, rejected: M, duplicates: K } │
│                                                 │
└─────────────────────────────────────────────────┘
```

### 4.3 Background Processing

**`ProcessLocationBatch` Job:**

| Step | Action |
|---|---|
| 1 | Receive batch of GPS points from controller |
| 2 | Validate and deduplicate |
| 3 | Bulk INSERT into `location_history` |
| 4 | UPSERT `current_locations` per distinct user |
| 5 | UPDATE Redis cache per distinct user |
| 6 | Trigger fraud indicator checks |
| 7 | Create `location_sync_batch` record |

---

## 5. GPS Quality & Filtering

### 5.1 Noise Filtering

**Server-Side Rejection Rules:**

| Rule | Threshold | Rationale |
|---|---|---|
| Low accuracy | `horizontal_accuracy > 200m` | Worthless for tracking |
| Future timestamp | `recorded_at > now() + 5 min` | Clock drift or spoofing |
| Invalid coordinates | Lat outside ±90, Lng outside ±180 | Data corruption |
| Impossible speed | Speed > 200 km/h (55 m/s) | No salesman drives that fast |
| Empty coordinates | Lat = 0 and Lng = 0 | GPS not acquired |

**Client-Side Filtering (Flutter):**

- Use Android's **fused location provider** (combines GPS + network + WiFi for best accuracy).
- Apply minimum accuracy threshold before storing in SQLite.
- Debounce: skip storing a point if it is within 5m and 5 seconds of the previous point.

### 5.2 Duplicate Detection

| Aspect | Detail |
|---|---|
| Mechanism | Each GPS point carries a `client_uuid` (UUIDv4, generated client-side) |
| Server check | `SELECT id FROM location_history WHERE client_uuid = ?` before insert (or batch-check with `WHERE client_uuid IN (...)`) |
| Handling | Duplicate counted in response `duplicates` field; not inserted; no error returned |

### 5.3 Gap Detection

A gap is identified when:

```
time_between_points > 2 × expected_collection_interval
```

For the standard 15-second interval, a gap is any pause > 30 seconds. Possible causes:

- Device screen off / app killed by OS
- GPS signal loss (tunnel, dense urban)
- Network failure (offline buffer empty)
- Salesman deliberately disabled GPS

Gaps are logged and surfaced in the dashboard as "GPS gaps" during the work day.

### 5.4 Stationary vs Moving Detection

| State | Threshold | Collection Interval |
|---|---|---|
| Stationary | `speed < 0.5 m/s` | 60 seconds |
| Moving | `speed ≥ 0.5 m/s` | 15 seconds |

This distinction is also used for:
- Visit duration calculation (stationary period at a customer location).
- Battery optimization (reduce collection when not moving).

---

## 6. Current Location Strategy

### Why Two Tables?

| Table | Rows | Purpose |
|---|---|---|
| `current_locations` | Exactly N (N = active salesmen) | Fast lookup for live map. Always current. |
| `location_history` | Append-only, millions | Analytics, route reconstruction, auditing. |

`current_locations` is essentially a materialized "latest state" — it avoids scanning millions of history rows to find the most recent point per user.

### Update Flow

```
GPS Point Received
  │
  ├──► INSERT INTO location_history (batch)
  │
  ├──► INSERT INTO current_locations ... ON DUPLICATE KEY UPDATE (UPSERT)
  │
  └──► HSET fs:{tenant_id}:locations:latest {user_id} {json}
```

### Query for Live Map

```
Primary path:    Redis   →  HGETALL fs:{tenant_id}:locations:latest
Fallback:        MySQL   →  SELECT * FROM current_locations WHERE tenant_id = ?
```

---

## 7. Route Reconstruction

### Purpose

- Show a salesman's path on a map for a given day.
- Compare planned route vs. actual route.
- Calculate total distance traveled.
- Verify visit locations match customer addresses.

### Query

```sql
SELECT latitude, longitude, recorded_at, speed
FROM location_history
WHERE tenant_id = ?
  AND user_id = ?
  AND recorded_at BETWEEN ? AND ?
ORDER BY recorded_at ASC;
```

### Route Polyline

- Return an array of `[lat, lng]` points to the client.
- Client renders as a polyline on the map (Leaflet / MapLibre).
- For display performance, apply **Douglas-Peucker simplification** if the point count exceeds 1,000 for a single day.

### Distance Calculation

```php
function calculateRouteDistance(array $points): float
{
    $totalDistance = 0.0;

    for ($i = 1; $i < count($points); $i++) {
        // Skip low-accuracy points
        if ($points[$i]['accuracy'] > 50 || $points[$i - 1]['accuracy'] > 50) {
            continue;
        }

        $totalDistance += haversine(
            $points[$i - 1]['latitude'], $points[$i - 1]['longitude'],
            $points[$i]['latitude'],     $points[$i]['longitude']
        );
    }

    return $totalDistance; // meters
}
```

---

## 8. Live Map Architecture

### Data Flow

```
Manager opens Live Map
  │
  ├─► Browser loads map (Leaflet / MapLibre GL)
  │
  ├─► JavaScript polls GET /api/v1/tracking/live  (every 15s)
  │
  ├─► Controller reads Redis: HGETALL fs:{tenant_id}:locations:latest
  │
  ├─► Enriches with current visit, route, device info
  │
  └─► Returns JSON → JavaScript updates markers on map
```

### Live Map Response

```json
{
  "success": true,
  "data": {
    "salesmen": [
      {
        "user_id": 5,
        "salesman_id": 12,
        "name": "Ahmad Shah",
        "employee_code": "S001",
        "latitude": 34.5553,
        "longitude": 69.2075,
        "accuracy": 10.5,
        "speed": 0,
        "heading": 180,
        "battery_level": 85,
        "is_charging": false,
        "is_mock_location": false,
        "recorded_at": "2025-01-15T10:30:00Z",
        "status": "online",
        "current_visit": {
          "customer_id": 45,
          "customer_name": "Kabul Dry Goods",
          "check_in_time": "2025-01-15T10:25:00Z"
        },
        "route": {
          "route_id": 8,
          "route_name": "Kabul City Route 1"
        },
        "device": {
          "model": "Samsung S21",
          "app_version": "1.0.0"
        }
      }
    ],
    "updated_at": "2025-01-15T10:30:15Z"
  }
}
```

### Status Logic

```
if (last_gps within 5 minutes AND work_session is active):
    status = "online"
elif (last_gps within 15 minutes):
    status = "idle"
else:
    status = "offline"
```

### Polling Strategy

| Setting | Value |
|---|---|
| Default interval | 15 seconds |
| Configurable | Yes, per user/session |
| Visibility detection | Pause polling when browser tab is hidden (Page Visibility API) |
| Future upgrade | WebSocket via Laravel Reverb for true push-based realtime |

---

## 9. Geofencing

### Customer Geofencing

Each customer record includes:

| Field | Default | Description |
|---|---|---|
| `latitude` | — | Customer location |
| `longitude` | — | Customer location |
| `geofence_radius` | 100 | Meters. Tolerance around customer point. |

When a salesman checks in, the system calculates the distance from the check-in point to the customer's stored location.

### Distance Calculation (Haversine)

```
d = 2R × arcsin(√(sin²(Δφ/2) + cos(φ₁)cos(φ₂)sin²(Δλ/2)))

Where:
  R  = 6,371,000 m (Earth's radius)
  φ₁ = visit latitude  (radians)
  φ₂ = customer latitude (radians)
  Δφ = φ₂ − φ₁
  Δλ = λ₂ − λ₁
```

### Geofence Radii Presets

| Preset | Radius | Use Case |
|---|---|---|
| Tight | 50m | Urban, single-building customers |
| Default | 100m | Standard |
| Moderate | 200m | Markets, shopping centers |
| Loose | 500m | Rural, large compounds |

### Visit Verification

```
check_in_distance = haversine(
    visit.check_in_latitude,  visit.check_in_longitude,
    customer.latitude,        customer.longitude
)

if (check_in_distance > customer.geofence_radius):
    flag_visit_as_suspicious("Check-in too far from customer location")
    // Store: visit_suspicious_flags record with severity = 'medium'
```

---

## 10. Suspicious GPS Indicators

All flags are stored in `visit_suspicious_flags` with severity levels (`low`, `medium`, `high`) and are **reviewable by managers** — never used for automatic punishment.

### Detection Rules

| Indicator | Rule | Severity | Notes |
|---|---|---|---|
| **Mock location detected** | `is_mock_location = true` | High | Android `isFromMockProvider` API. Rooted devices can spoof this. |
| **Impossible travel speed** | Speed between consecutive points > 200 km/h | High | Account for accuracy; skip transitions between low-accuracy points. |
| **Same coords for multiple visits** | Two check-ins within 10m of each other | Medium | Suggests GPS spoofing or sitting in one place. |
| **Check-in far outside geofence** | Distance > 2× geofence_radius | Medium | Customer may have moved, or salesman is elsewhere. |
| **No GPS during work hours** | Zero GPS points during an active work session | Low | Could be legitimate (phone died, building). |
| **Large GPS gap** | Gap > 30 minutes during work session | Low | May be legitimate — tunnel, underground, etc. |

### Storage

```sql
CREATE TABLE visit_suspicious_flags (
    id              bigint unsigned AUTO_INCREMENT PRIMARY KEY,
    tenant_id       bigint unsigned NOT NULL,
    visit_id        bigint unsigned NOT NULL,
    user_id         bigint unsigned NOT NULL,
    flag_type       varchar(64) NOT NULL,
    severity        enum('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    description     text NULL,
    metadata        json NULL,
    reviewed_by     bigint unsigned NULL,
    reviewed_at     datetime NULL,
    created_at      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_suspicious_flags_tenant (tenant_id, created_at),
    INDEX idx_suspicious_flags_visit (visit_id)
) ENGINE=InnoDB;
```

---

## 11. Retention & Archival

### Active Data Retention

| Data | Default Retention | Configurable |
|---|---|---|
| `location_history` | 90 days | Yes, per company |
| `current_locations` | Always current | No |
| `location_sync_batches` | 30 days | No |
| `visit_suspicious_flags` | 1 year | No |

### Archival Process (Monthly Scheduler Job)

```
┌─────────────────────────────────────────────────┐
│         ArchiveOldLocationData (Scheduled)       │
├─────────────────────────────────────────────────┤
│                                                 │
│  1. Query: SELECT MIN(recorded_at) cutoff        │
│     FROM location_history                       │
│     WHERE recorded_at < NOW() - INTERVAL X DAY  │
│                                                 │
│  2. Optional: Export to S3 (cold storage)        │
│     • Parquet format for efficient querying      │
│     • Partitioned by tenant_id/date              │
│                                                 │
│  3. DELETE FROM location_history                 │
│     WHERE recorded_at < cutoff                   │
│     (Batched: 10,000 rows per DELETE)            │
│                                                 │
│  4. DELETE FROM location_sync_batches            │
│     WHERE received_at < NOW() - INTERVAL 30 DAY │
│                                                 │
│  5. Log: archival count, duration, freed space   │
│                                                 │
└─────────────────────────────────────────────────┘
```

### Partitioning (Future Scale)

When `location_history` exceeds ~10M rows:

```sql
ALTER TABLE location_history
PARTITION BY RANGE COLUMNS(recorded_at) (
    PARTITION p2025_01 VALUES LESS THAN ('2025-02-01'),
    PARTITION p2025_02 VALUES LESS THAN ('2025-03-01'),
    PARTITION p2025_03 VALUES LESS THAN ('2025-04-01'),
    -- ...
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

Drop old partitions instead of `DELETE` for instant, space-freeing cleanup:

```sql
ALTER TABLE location_history DROP PARTITION p2025_01;
```

---

## 12. Indexing Strategy

### location_history

```sql
-- Primary query: "show user X's route on date Y"
INDEX idx_lh_tenant_user_time (tenant_id, user_id, recorded_at)

-- Archival job: delete by date range
INDEX idx_lh_recorded_at (recorded_at)

-- Debugging batch issues
INDEX idx_lh_sync_batch (sync_batch_id)
```

### current_locations

```sql
-- Single row lookup per user per tenant
UNIQUE INDEX idx_cl_tenant_user (tenant_id, user_id)

-- Live map: all users in a tenant
INDEX idx_cl_tenant (tenant_id)
```

### Indexing Rationale

- A **composite index** on `(tenant_id, user_id, recorded_at)` covers the dominant query pattern without needing three separate single-column indexes.
- Individual column indexes are avoided on `location_history` because it is a high-write table — every extra index slows inserts.
- `sync_batch_id` index is narrow and only used for debugging.

---

## 13. Scale Projections

### V1 — Launch

| Metric | Value |
|---|---|
| Companies | 10–50 |
| Salesmen | 50–200 |
| Points per salesman per day | ~200 |
| Total points per day | ~40,000 |
| Total points per month | ~1.2M |
| Infrastructure | Single MySQL server, single Redis |

### V2 — Growth

| Metric | Value |
|---|---|
| Companies | 100–500 |
| Salesmen | 500–2,000 |
| Points per salesman per day | ~200 |
| Total points per day | ~400,000 |
| Total points per month | ~12M |
| Infrastructure | MySQL read replica, Redis optimization |

### V3 — Scale

| Metric | Value |
|---|---|
| Companies | 1,000+ |
| Salesmen | 10,000+ |
| Points per salesman per day | ~200 |
| Total points per day | ~2M |
| Total points per month | ~60M |
| Infrastructure | Table partitioning, cold archival to S3, dedicated GPS database |

### Storage Estimates

| Metric | Estimate |
|---|---|
| Per GPS point (with indexes) | ~200 bytes |
| V1 monthly storage | ~240 MB |
| V2 monthly storage | ~2.4 GB |
| V3 monthly storage | ~12 GB |
| 1 year active (with 90-day retention) | ~3× monthly = ~7.2 GB (V2) |

---

## 14. Configuration Options (Per Company)

All settings are stored in a `tenant_tracking_settings` table and editable from the admin panel.

```sql
CREATE TABLE tenant_tracking_settings (
    tenant_id                       bigint unsigned PRIMARY KEY,
    tracking_enabled                boolean NOT NULL DEFAULT true,
    tracking_during_work_hours_only boolean NOT NULL DEFAULT true,
    gps_collection_interval_moving    int unsigned NOT NULL DEFAULT 15,
    gps_collection_interval_stationary int unsigned NOT NULL DEFAULT 60,
    geofence_default_radius         int unsigned NOT NULL DEFAULT 100,
    gps_retention_days              int unsigned NOT NULL DEFAULT 90,
    require_mock_location_check     boolean NOT NULL DEFAULT true,
    min_accuracy_threshold          int unsigned NOT NULL DEFAULT 100,
    max_acceptable_speed            int unsigned NOT NULL DEFAULT 200,
    created_at                      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

### Setting Descriptions

| Setting | Default | Description |
|---|---|---|
| `tracking_enabled` | `true` | Master switch. Disables all GPS collection. |
| `tracking_during_work_hours_only` | `true` | Only collect GPS during active work sessions. |
| `gps_collection_interval_moving` | `15` | Seconds between GPS points when salesman is moving. |
| `gps_collection_interval_stationary` | `60` | Seconds between GPS points when salesman is stationary. |
| `geofence_default_radius` | `100` | Default geofence radius in meters for new customers. |
| `gps_retention_days` | `90` | Days to keep location_history before archival. |
| `require_mock_location_check` | `true` | Reject points flagged as mock locations. |
| `min_accuracy_threshold` | `100` | Reject GPS points with accuracy worse than this (meters). |
| `max_acceptable_speed` | `200` | Maximum speed in km/h before flagging as impossible travel. |

---

## Appendix A: API Endpoints Summary

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/v1/gps/locations` | Receive batch of GPS points from device |
| `GET` | `/api/v1/tracking/live` | Live map data (all salesmen in tenant) |
| `GET` | `/api/v1/tracking/{userId}/route` | Route reconstruction for a user on a date |
| `GET` | `/api/v1/tracking/{userId}/history` | Paginated location history |
| `GET` | `/api/v1/tracking/gaps` | GPS gaps for a user/date |
| `GET` | `/api/v1/tracking/suspicious` | Suspicious flags for a tenant |

## Appendix B: Key Files (Planned)

| File | Purpose |
|---|---|
| `app/Models/LocationHistory.php` | Eloquent model for location_history |
| `app/Models/CurrentLocation.php` | Eloquent model for current_locations |
| `app/Models/LocationSyncBatch.php` | Eloquent model for sync batches |
| `app/Models/TenantTrackingSetting.php` | Per-company tracking config |
| `app/Models/VisitSuspiciousFlag.php` | Fraud detection flags |
| `app/Http/Controllers/Api/GpsController.php` | GPS receive endpoint |
| `app/Http/Controllers/Api/TrackingController.php` | Live map + route queries |
| `app/Jobs/ProcessLocationBatch.php` | Background GPS processing |
| `app/Jobs/ArchiveOldLocationData.php` | Monthly archival |
| `app/Jobs/CheckFraudIndicators.php` | Suspicious GPS analysis |
| `app/Services/GpsService.php` | Core GPS logic (haversine, filtering, validation) |
| `app/Services/LiveMapService.php` | Redis cache + live map enrichment |
| `database/migrations/xxxx_create_gps_tables.php` | All GPS-related migrations |
