# Field Sales API Contract

> **Version:** 1.0.0  
> **Status:** Planning  
> **Base URL:** `https://api.{domain}/api/v1`  
> **Content Type:** `application/json`

---

## Table of Contents

1. [API Design Principles](#1-api-design-principles)
2. [Base URL & Versioning](#2-base-url--versioning)
3. [Authentication](#3-authentication)
4. [Response Envelope](#4-response-envelope)
5. [Pagination](#5-pagination)
6. [Filtering & Sorting](#6-filtering--sorting)
7. [Rate Limiting](#7-rate-limiting)
8. [Endpoint Groups](#8-endpoint-groups)
9. [Idempotency](#9-idempotency)
10. [GPS Upload Contract](#10-gps-upload-contract)
11. [Sync Protocol](#11-sync-protocol)
12. [Validation Error Format](#12-validation-error-format)
13. [Web Dashboard API](#13-web-dashboard-api)
14. [Webhook Support](#14-webhook-support-future)

---

## 1. API Design Principles

- **RESTful resource naming** — nouns for resources, HTTP verbs for actions
- **Versioned** — all endpoints under `/api/v1/...`
- **JSON:API-inspired envelope** — consistent `success`, `data`, `meta`, `error` keys
- **Consistent error handling** — uniform error codes and messages across all endpoints
- **Offline-first from initial release** — create endpoints accept client-generated `offline_uuid` for idempotent sync; the API contract is defined assuming the Android app runs offline-first from day one (local SQLite, local-first writes, client UUIDs, basic sync queue, `pending`/`synced`/`failed` record states). Advanced sync hardening (conflict resolution, retry/backoff, tombstones, bulk sync, recovery) is a later enhancement
- **Idempotent operations** — safe retries for offline sync using idempotency keys
- **Rate limiting** — per-device and per-IP limits with clear headers
- **Device identification** — client sends device headers on every request

---

## 2. Base URL & Versioning

```
Base: https://api.{domain}/api/v1
```

- Version bump for **breaking changes** (field removal, type changes, semantics changes)
- **Additive changes** (new fields, new endpoints) do not require a version bump
- Deprecated endpoints return a `Deprecation` header with a sunset date

---

## 3. Authentication

### Token Authentication

```
Authorization: Bearer {token}
```

All requests (except login/forgot-password/reset-password) must include a valid Sanctum bearer token.

### Device Headers (sent on every request)

```
X-Device-UUID: {device_uuid}
X-Installation-UUID: {installation_uuid}
X-App-Version: {app_version}
X-Platform: android
X-OS-Version: {android_version}
```

### Authentication Endpoints

| Method | Endpoint                          | Description              |
|--------|-----------------------------------|--------------------------|
| POST   | `/api/v1/auth/login`              | Authenticate user        |
| POST   | `/api/v1/auth/logout`             | Revoke current token     |
| POST   | `/api/v1/auth/refresh`            | Refresh token            |
| POST   | `/api/v1/auth/forgot-password`    | Request password reset   |
| POST   | `/api/v1/auth/reset-password`     | Reset password           |

### Login

```
POST /api/v1/auth/login
```

**Request:**

```json
{
  "email": "user@example.com",
  "password": "secret",
  "device_uuid": "abc-123",
  "device_model": "Samsung S21",
  "manufacturer": "Samsung",
  "android_version": "14",
  "app_version": "1.0.0",
  "push_token": "fcm_token_here"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "token": "sanctum_token",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "salesman",
      "branch_id": 1,
      "avatar_url": "https://..."
    },
    "tenant": {
      "id": 1,
      "name": "Acme Corp",
      "slug": "acme",
      "subscription_status": "active"
    },
    "permissions": [
      "customers.view",
      "orders.create",
      "orders.view"
    ],
    "device": {
      "id": 10,
      "uuid": "abc-123",
      "status": "active",
      "registered_at": "2025-01-15T10:30:00Z"
    }
  }
}
```

**Error Responses:**

| Code | Meaning                        |
|------|--------------------------------|
| 422  | Validation failed              |
| 401  | Invalid credentials            |
| 403  | Account deactivated            |
| 429  | Too many login attempts        |
| 440  | Device revoked                 |

### Logout

```
POST /api/v1/auth/logout
Headers: Authorization: Bearer {token}
```

**Response 200:**

```json
{
  "success": true
}
```

### Refresh Token

```
POST /api/v1/auth/refresh
Headers: Authorization: Bearer {token}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "token": "new_sanctum_token"
  }
}
```

### Forgot Password

```
POST /api/v1/auth/forgot-password
```

**Request:**

```json
{
  "email": "user@example.com"
}
```

**Response 200:**

```json
{
  "success": true,
  "message": "If the email exists, a reset link has been sent."
}
```

### Reset Password

```
POST /api/v1/auth/reset-password
```

**Request:**

```json
{
  "token": "reset_token_from_email",
  "email": "user@example.com",
  "password": "new_secret",
  "password_confirmation": "new_secret"
}
```

**Response 200:**

```json
{
  "success": true,
  "message": "Password has been reset."
}
```

---

## 4. Response Envelope

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6
  }
}
```

- `data` — single object or array
- `meta` — present on list endpoints (pagination info)

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."]
    }
  }
}
```

### Common Error Codes

| Code                   | HTTP Status | Meaning                       |
|------------------------|-------------|-------------------------------|
| `VALIDATION_ERROR`     | 422         | Request validation failed     |
| `UNAUTHORIZED`         | 401         | Missing or invalid token      |
| `FORBIDDEN`            | 403         | Insufficient permissions      |
| `NOT_FOUND`            | 404         | Resource not found            |
| `CONFLICT`             | 409         | Resource conflict / duplicate |
| `RATE_LIMITED`         | 429         | Too many requests             |
| `SERVER_ERROR`         | 500         | Internal server error         |
| `DEVICE_REVOKED`       | 440         | Device has been revoked       |
| `TENANT_INACTIVE`      | 441         | Tenant subscription inactive  |
| `SYNC_CONFLICT`        | 409         | Sync data conflict            |
| `APP_VERSION_OUTDATED` | 442         | App version must be updated   |

---

## 5. Pagination

### Page-based Pagination

```
GET /api/v1/customers?page=1&per_page=25
```

| Parameter | Default | Max  | Description          |
|-----------|---------|------|----------------------|
| `page`    | 1       | —    | Page number          |
| `per_page`| 25      | 100  | Items per page       |

### Cursor-based Pagination

For large datasets (GPS locations, logs):

```
GET /api/v1/gps/locations?cursor=eyJpZCI6MTAwfQ==&limit=100
```

| Parameter | Default | Max  | Description          |
|-----------|---------|------|----------------------|
| `cursor`  | —       | —    | Opaque cursor token  |
| `limit`   | 100     | 500  | Items per page       |

### Response Meta

```json
{
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6,
    "has_more": true
  }
}
```

---

## 6. Filtering & Sorting

### Query Parameters

```
GET /api/v1/salesmen?filter[branch_id]=1&filter[is_active]=true&sort=-created_at
GET /api/v1/visits?filter[date_from]=2025-01-01&filter[date_to]=2025-01-31&filter[salesman_id]=5
```

### Filter Syntax

| Syntax                      | Operator         | Example                              |
|-----------------------------|------------------|--------------------------------------|
| `filter[field]=value`       | Exact match      | `filter[branch_id]=1`               |
| `filter[field][gte]=value`  | Greater or equal | `filter[date_from]=2025-01-01`      |
| `filter[field][lte]=value`  | Less or equal    | `filter[date_to]=2025-01-31`        |
| `filter[field][like]=value` | Partial match    | `filter[name][like]=ahmad`          |
| `filter[field][in]=v1,v2`   | In list          | `filter[status][in]=active,inactive`|
| `sort=field`                | Ascending        | `sort=name`                          |
| `sort=-field`               | Descending       | `sort=-created_at`                   |

---

## 7. Rate Limiting

### Default Limits

| Endpoint Group | Limit                | Window  |
|----------------|----------------------|---------|
| Auth endpoints | 10 requests          | per minute per IP |
| API endpoints  | 60 requests          | per minute per device |
| GPS upload     | 10 requests          | per minute per device |
| Sync           | 30 requests          | per minute per device |

### Headers

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1704067200
```

### Rate Limit Exceeded Response

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many requests. Please try again later.",
    "retry_after": 30
  }
}
```

---

## 8. Endpoint Groups

### 8.1 Profile

| Method | Endpoint                     | Description              |
|--------|------------------------------|--------------------------|
| GET    | `/api/v1/profile`            | Get current user profile |
| PUT    | `/api/v1/profile`            | Update profile           |
| PUT    | `/api/v1/profile/password`   | Change password          |

**GET /api/v1/profile — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "phone": "+93700123456",
    "role": "salesman",
    "branch_id": 1,
    "branch": {
      "id": 1,
      "name": "Kabul Branch"
    },
    "avatar_url": "https://...",
    "is_active": true,
    "last_login_at": "2025-01-15T08:00:00Z",
    "created_at": "2024-06-01T00:00:00Z"
  }
}
```

**PUT /api/v1/profile — Request:**

```json
{
  "name": "John Doe Updated",
  "phone": "+93700987654",
  "avatar": "base64_encoded_image_or_null"
}
```

**PUT /api/v1/profile/password — Request:**

```json
{
  "current_password": "old_secret",
  "password": "new_secret",
  "password_confirmation": "new_secret"
}
```

---

### 8.2 Device Management

| Method | Endpoint                              | Description            |
|--------|---------------------------------------|------------------------|
| POST   | `/api/v1/devices/register`            | Register device        |
| PUT    | `/api/v1/devices/{device_id}/heartbeat` | Send heartbeat       |
| DELETE | `/api/v1/devices/{device_id}`         | Revoke device          |
| GET    | `/api/v1/devices`                     | List devices (admin)   |

**POST /api/v1/devices/register — Request:**

```json
{
  "device_uuid": "abc-123",
  "device_model": "Samsung S21",
  "manufacturer": "Samsung",
  "android_version": "14",
  "app_version": "1.0.0",
  "push_token": "fcm_token_here",
  "screen_resolution": "1080x2400",
  "storage_total_gb": 128,
  "storage_available_gb": 85
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 10,
    "uuid": "abc-123",
    "status": "active",
    "registered_at": "2025-01-15T10:30:00Z",
    "last_heartbeat_at": "2025-01-15T10:30:00Z"
  }
}
```

**PUT /api/v1/devices/{device_id}/heartbeat — Request:**

```json
{
  "app_version": "1.0.1",
  "battery_level": 72,
  "is_charging": false,
  "storage_available_gb": 82,
  "push_token": "updated_fcm_token"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 10,
    "last_heartbeat_at": "2025-01-15T12:00:00Z",
    "app_update_available": true,
    "app_update_version": "1.1.0",
    "app_update_url": "https://..."
  }
}
```

---

### 8.3 App Configuration

| Method | Endpoint                      | Description                |
|--------|-------------------------------|----------------------------|
| GET    | `/api/v1/app/config`          | Get app configuration      |
| GET    | `/api/v1/app/sync-status`     | Get last sync info         |

**GET /api/v1/app/config — Response 200:**

```json
{
  "success": true,
  "data": {
    "gps_interval_seconds": 30,
    "gps_accuracy_threshold": 50,
    "sync_interval_minutes": 15,
    "max_photo_size_mb": 5,
    "max_photos_per_visit": 10,
    "offline_data_retention_days": 30,
    "features": {
      "gps_tracking": true,
      "photo_capture": true,
      "route_optimization": false,
      "expense_approval": true
    },
    "min_app_version": "1.0.0",
    "maintenance_mode": false
  }
}
```

**GET /api/v1/app/sync-status — Response 200:**

```json
{
  "success": true,
  "data": {
    "last_sync_at": "2025-01-15T10:30:00Z",
    "pending_push_count": 3,
    "server_time": "2025-01-15T12:00:00Z",
    "types": {
      "customers": {
        "last_sync_at": "2025-01-15T10:30:00Z",
        "count": 150
      },
      "products": {
        "last_sync_at": "2025-01-15T10:30:00Z",
        "count": 45
      },
      "routes": {
        "last_sync_at": "2025-01-15T10:30:00Z",
        "count": 12
      }
    }
  }
}
```

---

### 8.4 Sync

| Method | Endpoint                          | Description                    |
|--------|-----------------------------------|--------------------------------|
| POST   | `/api/v1/sync/push`               | Push local changes to server   |
| GET    | `/api/v1/sync/pull`               | Pull server changes to client  |
| POST   | `/api/v1/sync/batch`              | Batch push + pull in one call  |

**POST /api/v1/sync/push — Request:**

```json
{
  "last_sync_at": "2025-01-14T00:00:00Z",
  "entities": [
    {
      "type": "customer_visit",
      "action": "create",
      "offline_uuid": "client-uuid-001",
      "data": {
        "customer_id": 5,
        "check_in_time": "2025-01-15T09:00:00Z",
        "check_out_time": "2025-01-15T09:30:00Z",
        "notes": "Met with purchasing manager"
      }
    },
    {
      "type": "order",
      "action": "create",
      "offline_uuid": "client-uuid-002",
      "data": {
        "customer_id": 5,
        "items": [
          {
            "product_id": 10,
            "quantity": 50,
            "unit_price": 25.00
          }
        ],
        "total": 1250.00,
        "notes": "Urgent delivery needed"
      }
    }
  ]
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "accepted": 2,
    "rejected": 0,
    "results": [
      {
        "offline_uuid": "client-uuid-001",
        "server_id": 456,
        "status": "created"
      },
      {
        "offline_uuid": "client-uuid-002",
        "server_id": 789,
        "status": "created"
      }
    ],
    "server_time": "2025-01-15T10:30:00Z"
  }
}
```

**GET /api/v1/sync/pull — Request:**

```
GET /api/v1/sync/pull?since=2025-01-14T00:00:00Z&types[]=customers&types[]=products&types[]=routes
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "changes": [
      {
        "type": "customers",
        "items": [
          {
            "id": 150,
            "name": "New Customer",
            "action": "created",
            "updated_at": "2025-01-15T08:00:00Z"
          },
          {
            "id": 42,
            "name": "Updated Customer",
            "action": "updated",
            "updated_at": "2025-01-15T09:00:00Z"
          }
        ],
        "count": 2
      },
      {
        "type": "products",
        "items": [
          {
            "id": 10,
            "name": "Product A",
            "action": "updated",
            "updated_at": "2025-01-15T07:00:00Z"
          }
        ],
        "count": 1
      }
    ],
    "server_time": "2025-01-15T10:30:00Z",
    "has_more": false
  }
}
```

**POST /api/v1/sync/batch — Request:**

```json
{
  "push": {
    "last_sync_at": "2025-01-14T00:00:00Z",
    "entities": [
      {
        "type": "order",
        "action": "create",
        "offline_uuid": "client-uuid-003",
        "data": { ... }
      }
    ]
  },
  "pull_since": "2025-01-14T00:00:00Z",
  "pull_types": ["customers", "products"]
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "push_results": {
      "accepted": 1,
      "rejected": 0,
      "results": [
        {
          "offline_uuid": "client-uuid-003",
          "server_id": 790,
          "status": "created"
        }
      ]
    },
    "pull_results": {
      "changes": [
        {
          "type": "customers",
          "items": [],
          "count": 0
        }
      ],
      "has_more": false
    },
    "server_time": "2025-01-15T10:30:00Z"
  }
}
```

---

### 8.5 Attendance / Work Sessions

| Method | Endpoint                              | Description                   |
|--------|---------------------------------------|-------------------------------|
| POST   | `/api/v1/attendance/start`            | Start work session            |
| POST   | `/api/v1/attendance/end`              | End work session              |
| GET    | `/api/v1/attendance/today`            | Get today's attendance        |
| GET    | `/api/v1/attendance/history`          | Get attendance history        |
| PUT    | `/api/v1/attendance/{id}`             | Request attendance correction |

**POST /api/v1/attendance/start — Request:**

```json
{
  "latitude": 34.5553,
  "longitude": 69.2075,
  "accuracy": 10.5,
  "offline_uuid": "client-uuid-att-001"
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 100,
    "user_id": 5,
    "status": "active",
    "started_at": "2025-01-15T08:00:00Z",
    "start_location": {
      "latitude": 34.5553,
      "longitude": 69.2075
    },
    "offline_uuid": "client-uuid-att-001"
  }
}
```

**POST /api/v1/attendance/end — Request:**

```json
{
  "latitude": 34.5600,
  "longitude": 69.2100,
  "accuracy": 8.2
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 100,
    "status": "completed",
    "started_at": "2025-01-15T08:00:00Z",
    "ended_at": "2025-01-15T17:00:00Z",
    "duration_hours": 9.0,
    "end_location": {
      "latitude": 34.5600,
      "longitude": 69.2100
    }
  }
}
```

**GET /api/v1/attendance/today — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 100,
    "status": "active",
    "started_at": "2025-01-15T08:00:00Z",
    "ended_at": null,
    "duration_hours": null,
    "start_location": {
      "latitude": 34.5553,
      "longitude": 69.2075
    }
  }
}
```

**GET /api/v1/attendance/history — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "status": "completed",
      "started_at": "2025-01-15T08:00:00Z",
      "ended_at": "2025-01-15T17:00:00Z",
      "duration_hours": 9.0
    },
    {
      "id": 99,
      "status": "completed",
      "started_at": "2025-01-14T08:15:00Z",
      "ended_at": "2025-01-14T16:45:00Z",
      "duration_hours": 8.5
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 30,
    "last_page": 2
  }
}
```

**PUT /api/v1/attendance/{id} — Request (correction):**

```json
{
  "reason": "App crashed at start, actual start time was 07:55",
  "requested_started_at": "2025-01-15T07:55:00Z",
  "requested_ended_at": null
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 100,
    "correction_status": "pending",
    "correction_requested_at": "2025-01-15T17:30:00Z"
  }
}
```

---

### 8.6 GPS Locations

| Method | Endpoint                       | Description              |
|--------|--------------------------------|--------------------------|
| POST   | `/api/v1/gps/locations`        | Bulk upload GPS points   |
| GET    | `/api/v1/gps/current`          | Get current location     |
| GET    | `/api/v1/gps/history`          | Get location history     |

**POST /api/v1/gps/locations — Request:**

```json
{
  "locations": [
    {
      "client_uuid": "gps-uuid-001",
      "latitude": 34.5553,
      "longitude": 69.2075,
      "accuracy": 10.5,
      "altitude": 1800,
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
      "client_uuid": "gps-uuid-002",
      "latitude": 34.5560,
      "longitude": 69.2080,
      "accuracy": 12.0,
      "altitude": 1805,
      "speed": 5.2,
      "heading": 210,
      "battery_level": 84,
      "is_charging": false,
      "network_status": "wifi",
      "is_mock_location": false,
      "provider": "gps",
      "recorded_at": "2025-01-15T10:30:30Z",
      "sequence_number": 2
    }
  ],
  "batch_uuid": "batch-uuid-001"
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "accepted": 2,
    "rejected": 0,
    "duplicates": 0,
    "batch_id": 12345
  }
}
```

**GET /api/v1/gps/current — Response 200:**

```json
{
  "success": true,
  "data": {
    "user_id": 5,
    "latitude": 34.5553,
    "longitude": 69.2075,
    "accuracy": 10.5,
    "recorded_at": "2025-01-15T10:30:00Z",
    "battery_level": 85,
    "is_charging": false,
    "network_status": "wifi"
  }
}
```

**GET /api/v1/gps/history — Request:**

```
GET /api/v1/gps/history?date=2025-01-15&user_id=5
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "locations": [
      {
        "id": 1,
        "latitude": 34.5553,
        "longitude": 69.2075,
        "accuracy": 10.5,
        "recorded_at": "2025-01-15T08:00:00Z",
        "battery_level": 100,
        "is_mock_location": false,
        "provider": "gps"
      }
    ],
    "summary": {
      "total_points": 1200,
      "distance_km": 45.2,
      "first_point_at": "2025-01-15T08:00:00Z",
      "last_point_at": "2025-01-15T17:00:00Z"
    }
  }
}
```

---

### 8.7 Customers

| Method | Endpoint                              | Description                    |
|--------|---------------------------------------|--------------------------------|
| GET    | `/api/v1/customers`                   | List customers                 |
| GET    | `/api/v1/customers/{id}`              | Get customer details           |
| POST   | `/api/v1/customers`                   | Create customer                |
| PUT    | `/api/v1/customers/{id}`              | Update customer                |
| GET    | `/api/v1/customers/{id}/visits`       | Get customer visit history     |
| GET    | `/api/v1/customers/{id}/orders`       | Get customer orders            |
| GET    | `/api/v1/customers/{id}/balance`      | Get customer balance           |

**POST /api/v1/customers — Request:**

```json
{
  "offline_uuid": "client-customer-001",
  "name": "Kabul Electronics Shop",
  "contact_person": "Ahmad Shah",
  "phone": "+93700123456",
  "email": "ahmad@kabulelectronics.af",
  "address": "Street 12, District 5, Kabul",
  "latitude": 34.5553,
  "longitude": 69.2075,
  "route_id": 3,
  "price_list_id": 1,
  "credit_limit": 5000.00,
  "notes": "Main electronics distributor",
  "custom_fields": {
    "tax_id": "AF-12345",
    "registration_number": "REG-67890"
  }
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 200,
    "offline_uuid": "client-customer-001",
    "name": "Kabul Electronics Shop",
    "contact_person": "Ahmad Shah",
    "phone": "+93700123456",
    "email": "ahmad@kabulelectronics.af",
    "address": "Street 12, District 5, Kabul",
    "latitude": 34.5553,
    "longitude": 69.2075,
    "route_id": 3,
    "route": {
      "id": 3,
      "name": "Kabul City Route"
    },
    "price_list_id": 1,
    "credit_limit": 5000.00,
    "current_balance": 0.00,
    "is_active": true,
    "created_by": 5,
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```

**GET /api/v1/customers — Query Parameters:**

```
GET /api/v1/customers?filter[route_id]=3&filter[is_active]=true&sort=-created_at&page=1&per_page=25
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 200,
      "name": "Kabul Electronics Shop",
      "contact_person": "Ahmad Shah",
      "phone": "+93700123456",
      "route_id": 3,
      "is_active": true,
      "current_balance": 1250.00,
      "last_visit_at": "2025-01-14T09:00:00Z",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6
  }
}
```

**GET /api/v1/customers/{id} — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 200,
    "name": "Kabul Electronics Shop",
    "contact_person": "Ahmad Shah",
    "phone": "+93700123456",
    "email": "ahmad@kabulelectronics.af",
    "address": "Street 12, District 5, Kabul",
    "latitude": 34.5553,
    "longitude": 69.2075,
    "route_id": 3,
    "route": {
      "id": 3,
      "name": "Kabul City Route"
    },
    "price_list_id": 1,
    "price_list": {
      "id": 1,
      "name": "Standard Price List"
    },
    "credit_limit": 5000.00,
    "current_balance": 1250.00,
    "is_active": true,
    "custom_fields": {
      "tax_id": "AF-12345",
      "registration_number": "REG-67890"
    },
    "stats": {
      "total_orders": 25,
      "total_order_value": 32500.00,
      "total_collections": 31250.00,
      "last_order_at": "2025-01-14T10:00:00Z",
      "last_visit_at": "2025-01-14T09:00:00Z"
    },
    "created_by": 5,
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-14T10:00:00Z"
  }
}
```

**GET /api/v1/customers/{id}/balance — Response 200:**

```json
{
  "success": true,
  "data": {
    "customer_id": 200,
    "credit_limit": 5000.00,
    "current_balance": 1250.00,
    "available_credit": 3750.00,
    "currency": "AFN",
    "last_payment_at": "2025-01-13T14:00:00Z",
    "last_payment_amount": 500.00,
    "aging": {
      "current": 750.00,
      "30_days": 500.00,
      "60_days": 0.00,
      "90_days_plus": 0.00
    }
  }
}
```

---

### 8.8 Routes

| Method | Endpoint                              | Description                    |
|--------|---------------------------------------|--------------------------------|
| GET    | `/api/v1/routes`                      | List routes                    |
| GET    | `/api/v1/routes/{id}`                 | Get route details              |
| GET    | `/api/v1/routes/{id}/customers`       | Get route customers            |
| GET    | `/api/v1/routes/{id}/visits`          | Get route visits               |

**GET /api/v1/routes — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Kabul City Route",
      "description": "Main Kabul city customers",
      "branch_id": 1,
      "salesman_id": 5,
      "customer_count": 35,
      "is_active": true,
      "created_at": "2024-06-01T00:00:00Z"
    },
    {
      "id": 2,
      "name": "Kabul outskirts",
      "description": "Outer districts of Kabul",
      "branch_id": 1,
      "salesman_id": 5,
      "customer_count": 20,
      "is_active": true,
      "created_at": "2024-06-01T00:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 5,
    "last_page": 1
  }
}
```

**GET /api/v1/routes/{id} — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Kabul City Route",
    "description": "Main Kabul city customers",
    "branch_id": 1,
    "branch": {
      "id": 1,
      "name": "Kabul Branch"
    },
    "salesman_id": 5,
    "salesman": {
      "id": 5,
      "name": "John Doe"
    },
    "customer_count": 35,
    "is_active": true,
    "created_at": "2024-06-01T00:00:00Z",
    "updated_at": "2025-01-10T00:00:00Z"
  }
}
```

**GET /api/v1/routes/{id}/customers — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 200,
      "name": "Kabul Electronics Shop",
      "contact_person": "Ahmad Shah",
      "phone": "+93700123456",
      "address": "Street 12, District 5, Kabul",
      "latitude": 34.5553,
      "longitude": 69.2075,
      "is_active": true,
      "visit_order": 1
    },
    {
      "id": 201,
      "name": "Kabul Mobile Center",
      "contact_person": "Omar Farooq",
      "phone": "+93700987654",
      "address": "Main Road, District 3, Kabul",
      "latitude": 34.5570,
      "longitude": 69.2090,
      "is_active": true,
      "visit_order": 2
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 35,
    "last_page": 1
  }
}
```

---

### 8.9 Visits

| Method | Endpoint                         | Description                 |
|--------|----------------------------------|-----------------------------|
| POST   | `/api/v1/visits/{id}/check-in`   | Check in to visit           |
| POST   | `/api/v1/visits/{id}/check-out`  | Check out from visit        |
| GET    | `/api/v1/visits`                 | List visits                 |
| GET    | `/api/v1/visits/{id}`            | Get visit details           |
| POST   | `/api/v1/visits/{id}/notes`      | Add visit notes             |
| POST   | `/api/v1/visits/{id}/photos`     | Upload visit photos         |

**POST /api/v1/visits/{id}/check-in — Request:**

```json
{
  "latitude": 34.5553,
  "longitude": 69.2075,
  "accuracy": 8.5,
  "offline_uuid": "client-visit-checkin-001"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 500,
    "customer_id": 200,
    "customer": {
      "id": 200,
      "name": "Kabul Electronics Shop"
    },
    "salesman_id": 5,
    "route_id": 3,
    "status": "checked_in",
    "check_in_time": "2025-01-15T09:00:00Z",
    "check_in_location": {
      "latitude": 34.5553,
      "longitude": 69.2075,
      "accuracy": 8.5
    },
    "check_out_time": null,
    "check_out_location": null,
    "offline_uuid": "client-visit-checkin-001"
  }
}
```

**POST /api/v1/visits/{id}/check-out — Request:**

```json
{
  "latitude": 34.5555,
  "longitude": 69.2078,
  "accuracy": 7.2,
  "notes": "Met with purchasing manager, discussed Q2 orders",
  "outcome": "successful"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 500,
    "status": "completed",
    "check_in_time": "2025-01-15T09:00:00Z",
    "check_out_time": "2025-01-15T09:30:00Z",
    "duration_minutes": 30,
    "check_out_location": {
      "latitude": 34.5555,
      "longitude": 69.2078
    },
    "notes": "Met with purchasing manager, discussed Q2 orders",
    "outcome": "successful"
  }
}
```

**GET /api/v1/visits — Query Parameters:**

```
GET /api/v1/visits?filter[date]=2025-01-15&filter[customer_id]=200&filter[status]=completed&sort=-check_in_time
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 500,
      "customer_id": 200,
      "customer": {
        "id": 200,
        "name": "Kabul Electronics Shop"
      },
      "salesman_id": 5,
      "route_id": 3,
      "status": "completed",
      "check_in_time": "2025-01-15T09:00:00Z",
      "check_out_time": "2025-01-15T09:30:00Z",
      "duration_minutes": 30,
      "outcome": "successful"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 8,
    "last_page": 1
  }
}
```

**POST /api/v1/visits/{id}/notes — Request:**

```json
{
  "notes": "Met with purchasing manager, discussed Q2 orders. They are interested in 20% more volume."
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 500,
    "notes": "Met with purchasing manager, discussed Q2 orders. They are interested in 20% more volume.",
    "updated_at": "2025-01-15T09:25:00Z"
  }
}
```

**POST /api/v1/visits/{id}/photos — Request:**

```
POST /api/v1/visits/{id}/photos
Content-Type: multipart/form-data

photo: (binary image data)
caption: "Store front"
latitude: 34.5553
longitude: 69.2075
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 1000,
    "visit_id": 500,
    "url": "https://storage.example.com/visits/500/photo_1000.jpg",
    "thumbnail_url": "https://storage.example.com/visits/500/photo_1000_thumb.jpg",
    "caption": "Store front",
    "latitude": 34.5553,
    "longitude": 69.2075,
    "size_bytes": 245000,
    "created_at": "2025-01-15T09:25:00Z"
  }
}
```

---

### 8.10 Orders

| Method | Endpoint                          | Description              |
|--------|-----------------------------------|--------------------------|
| POST   | `/api/v1/orders`                  | Create order             |
| GET    | `/api/v1/orders`                  | List orders              |
| GET    | `/api/v1/orders/{id}`             | Get order details        |
| PUT    | `/api/v1/orders/{id}`             | Update order             |
| POST   | `/api/v1/orders/{id}/submit`      | Submit order for approval|
| PUT    | `/api/v1/orders/{id}/status`      | Update order status      |

**POST /api/v1/orders — Request:**

```json
{
  "offline_uuid": "client-order-001",
  "customer_id": 200,
  "visit_id": 500,
  "route_id": 3,
  "items": [
    {
      "product_id": 10,
      "product_name": "Product A",
      "quantity": 50,
      "unit_price": 25.00,
      "discount_percent": 5,
      "discount_amount": 62.50,
      "line_total": 1187.50,
      "notes": "Urgent delivery"
    },
    {
      "product_id": 15,
      "product_name": "Product B",
      "quantity": 100,
      "unit_price": 12.00,
      "discount_percent": 0,
      "discount_amount": 0,
      "line_total": 1200.00,
      "notes": null
    }
  ],
  "subtotal": 2437.50,
  "tax_percent": 0,
  "tax_amount": 0,
  "discount_total": 62.50,
  "total": 2375.00,
  "currency": "AFN",
  "delivery_date": "2025-01-20",
  "payment_terms": "net_30",
  "notes": "Urgent delivery needed for Product A"
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 1000,
    "offline_uuid": "client-order-001",
    "order_number": "ORD-2025-001000",
    "customer_id": 200,
    "customer": {
      "id": 200,
      "name": "Kabul Electronics Shop"
    },
    "visit_id": 500,
    "route_id": 3,
    "salesman_id": 5,
    "status": "draft",
    "items": [
      {
        "id": 1,
        "product_id": 10,
        "product_name": "Product A",
        "quantity": 50,
        "unit_price": 25.00,
        "discount_percent": 5,
        "discount_amount": 62.50,
        "line_total": 1187.50,
        "notes": "Urgent delivery"
      },
      {
        "id": 2,
        "product_id": 15,
        "product_name": "Product B",
        "quantity": 100,
        "unit_price": 12.00,
        "discount_percent": 0,
        "discount_amount": 0,
        "line_total": 1200.00,
        "notes": null
      }
    ],
    "subtotal": 2437.50,
    "tax_percent": 0,
    "tax_amount": 0,
    "discount_total": 62.50,
    "total": 2375.00,
    "currency": "AFN",
    "delivery_date": "2025-01-20",
    "payment_terms": "net_30",
    "notes": "Urgent delivery needed for Product A",
    "submitted_at": null,
    "approved_at": null,
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-15T10:00:00Z"
  }
}
```

**GET /api/v1/orders — Query Parameters:**

```
GET /api/v1/orders?filter[status]=submitted&filter[date_from]=2025-01-01&filter[date_to]=2025-01-31&sort=-created_at
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1000,
      "order_number": "ORD-2025-001000",
      "customer_id": 200,
      "customer": {
        "id": 200,
        "name": "Kabul Electronics Shop"
      },
      "status": "submitted",
      "total": 2375.00,
      "currency": "AFN",
      "item_count": 2,
      "delivery_date": "2025-01-20",
      "created_at": "2025-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 45,
    "last_page": 2
  }
}
```

**POST /api/v1/orders/{id}/submit — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 1000,
    "status": "submitted",
    "submitted_at": "2025-01-15T10:15:00Z"
  }
}
```

**PUT /api/v1/orders/{id}/status — Request:**

```json
{
  "status": "approved",
  "notes": "Approved by manager"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 1000,
    "status": "approved",
    "approved_at": "2025-01-15T14:00:00Z",
    "approved_by": 10,
    "notes": "Approved by manager"
  }
}
```

---

### 8.11 Collections

| Method | Endpoint                         | Description             |
|--------|----------------------------------|-------------------------|
| POST   | `/api/v1/collections`            | Record collection       |
| GET    | `/api/v1/collections`            | List collections        |
| GET    | `/api/v1/collections/{id}`       | Get collection details  |

**POST /api/v1/collections — Request:**

```json
{
  "offline_uuid": "client-collection-001",
  "customer_id": 200,
  "order_id": 1000,
  "amount": 2375.00,
  "currency": "AFN",
  "payment_method": "cash",
  "reference_number": "REC-001",
  "received_from": "Ahmad Shah",
  "notes": "Full payment for order ORD-2025-001000",
  "received_at": "2025-01-15T10:30:00Z"
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 500,
    "offline_uuid": "client-collection-001",
    "collection_number": "COL-2025-000500",
    "customer_id": 200,
    "customer": {
      "id": 200,
      "name": "Kabul Electronics Shop"
    },
    "order_id": 1000,
    "order": {
      "id": 1000,
      "order_number": "ORD-2025-001000"
    },
    "salesman_id": 5,
    "amount": 2375.00,
    "currency": "AFN",
    "payment_method": "cash",
    "reference_number": "REC-001",
    "received_from": "Ahmad Shah",
    "notes": "Full payment for order ORD-2025-001000",
    "received_at": "2025-01-15T10:30:00Z",
    "status": "recorded",
    "created_at": "2025-01-15T10:30:00Z"
  }
}
```

**GET /api/v1/collections — Query Parameters:**

```
GET /api/v1/collections?filter[date_from]=2025-01-01&filter[date_to]=2025-01-31&filter[payment_method]=cash&sort=-received_at
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 500,
      "collection_number": "COL-2025-000500",
      "customer_id": 200,
      "customer": {
        "id": 200,
        "name": "Kabul Electronics Shop"
      },
      "amount": 2375.00,
      "currency": "AFN",
      "payment_method": "cash",
      "received_at": "2025-01-15T10:30:00Z",
      "status": "recorded"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 120,
    "last_page": 5
  }
}
```

---

### 8.12 Targets

| Method | Endpoint                       | Description              |
|--------|--------------------------------|--------------------------|
| GET    | `/api/v1/targets`              | List targets             |
| GET    | `/api/v1/targets/achievement`  | Get achievement summary  |
| GET    | `/api/v1/targets/{id}`         | Get target details       |

**GET /api/v1/targets — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "January 2025 Sales Target",
      "type": "sales",
      "period": "monthly",
      "start_date": "2025-01-01",
      "end_date": "2025-01-31",
      "target_amount": 50000.00,
      "currency": "AFN",
      "salesman_id": 5,
      "branch_id": 1
    },
    {
      "id": 2,
      "name": "January 2025 Visit Target",
      "type": "visits",
      "period": "monthly",
      "start_date": "2025-01-01",
      "end_date": "2025-01-31",
      "target_count": 100,
      "salesman_id": 5,
      "branch_id": 1
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 4,
    "last_page": 1
  }
}
```

**GET /api/v1/targets/achievement — Response 200:**

```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31",
      "days_elapsed": 15,
      "days_total": 31
    },
    "sales": {
      "target": 50000.00,
      "achieved": 28500.00,
      "percentage": 57.0,
      "on_track": true,
      "projected_total": 59000.00
    },
    "visits": {
      "target": 100,
      "achieved": 52,
      "percentage": 52.0,
      "on_track": true,
      "projected_total": 108
    },
    "collections": {
      "target": 45000.00,
      "achieved": 25000.00,
      "percentage": 55.6,
      "on_track": true,
      "projected_total": 53000.00
    },
    "new_customers": {
      "target": 10,
      "achieved": 4,
      "percentage": 40.0,
      "on_track": false,
      "projected_total": 8
    }
  }
}
```

---

### 8.13 Expenses

| Method | Endpoint                         | Description               |
|--------|----------------------------------|---------------------------|
| POST   | `/api/v1/expenses`               | Submit expense            |
| GET    | `/api/v1/expenses`               | List expenses             |
| GET    | `/api/v1/expenses/{id}`          | Get expense details       |
| PUT    | `/api/v1/expenses/{id}/approve`  | Approve expense (admin)   |
| PUT    | `/api/v1/expenses/{id}/reject`   | Reject expense (admin)    |

**POST /api/v1/expenses — Request:**

```json
{
  "offline_uuid": "client-expense-001",
  "category": "transport",
  "description": "Taxi fare to customer meeting",
  "amount": 150.00,
  "currency": "AFN",
  "receipt_photo": "base64_encoded_image",
  "date": "2025-01-15",
  "latitude": 34.5553,
  "longitude": 69.2075,
  "notes": "Round trip to District 5 for customer meeting"
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 300,
    "offline_uuid": "client-expense-001",
    "user_id": 5,
    "category": "transport",
    "description": "Taxi fare to customer meeting",
    "amount": 150.00,
    "currency": "AFN",
    "receipt_url": "https://storage.example.com/expenses/300_receipt.jpg",
    "date": "2025-01-15",
    "latitude": 34.5553,
    "longitude": 69.2075,
    "notes": "Round trip to District 5 for customer meeting",
    "status": "pending",
    "submitted_at": "2025-01-15T17:00:00Z",
    "created_at": "2025-01-15T17:00:00Z"
  }
}
```

**GET /api/v1/expenses — Query Parameters:**

```
GET /api/v1/expenses?filter[status]=pending&filter[date_from]=2025-01-01&filter[date_to]=2025-01-31&sort=-date
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 300,
      "user_id": 5,
      "user": {
        "id": 5,
        "name": "John Doe"
      },
      "category": "transport",
      "description": "Taxi fare to customer meeting",
      "amount": 150.00,
      "currency": "AFN",
      "date": "2025-01-15",
      "status": "pending",
      "submitted_at": "2025-01-15T17:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 30,
    "last_page": 2
  }
}
```

**PUT /api/v1/expenses/{id}/approve — Request:**

```json
{
  "notes": "Approved - valid transport expense"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 300,
    "status": "approved",
    "approved_by": 10,
    "approved_at": "2025-01-16T09:00:00Z",
    "notes": "Approved - valid transport expense"
  }
}
```

**PUT /api/v1/expenses/{id}/reject — Request:**

```json
{
  "reason": "Receipt required for expenses over 100 AFN"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 300,
    "status": "rejected",
    "rejected_by": 10,
    "rejected_at": "2025-01-16T09:00:00Z",
    "rejection_reason": "Receipt required for expenses over 100 AFN"
  }
}
```

---

### 8.14 Products

| Method | Endpoint                       | Description              |
|--------|--------------------------------|--------------------------|
| GET    | `/api/v1/products`             | List products            |
| GET    | `/api/v1/products/{id}`        | Get product details      |
| GET    | `/api/v1/price-lists`          | List price lists         |

**GET /api/v1/products — Query Parameters:**

```
GET /api/v1/products?filter[category]=electronics&filter[is_active]=true&sort=name
```

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "sku": "ELEC-001",
      "name": "Product A",
      "description": "High-quality electronic component",
      "category": "electronics",
      "unit": "piece",
      "price": 25.00,
      "currency": "AFN",
      "stock_available": 500,
      "is_active": true,
      "image_url": "https://storage.example.com/products/10.jpg",
      "updated_at": "2025-01-10T00:00:00Z"
    },
    {
      "id": 15,
      "sku": "ELEC-002",
      "name": "Product B",
      "description": "Standard electronic component",
      "category": "electronics",
      "unit": "piece",
      "price": 12.00,
      "currency": "AFN",
      "stock_available": 1000,
      "is_active": true,
      "image_url": "https://storage.example.com/products/15.jpg",
      "updated_at": "2025-01-10T00:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 45,
    "last_page": 2
  }
}
```

**GET /api/v1/products/{id} — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 10,
    "sku": "ELEC-001",
    "name": "Product A",
    "description": "High-quality electronic component",
    "category": "electronics",
    "unit": "piece",
    "price": 25.00,
    "currency": "AFN",
    "stock_available": 500,
    "stock_reserved": 20,
    "is_active": true,
    "image_url": "https://storage.example.com/products/10.jpg",
    "images": [
      "https://storage.example.com/products/10.jpg",
      "https://storage.example.com/products/10_2.jpg"
    ],
    "price_lists": [
      {
        "id": 1,
        "name": "Standard Price List",
        "price": 25.00
      },
      {
        "id": 2,
        "name": "Wholesale Price List",
        "price": 22.00
      }
    ],
    "created_at": "2024-06-01T00:00:00Z",
    "updated_at": "2025-01-10T00:00:00Z"
  }
}
```

**GET /api/v1/price-lists — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Standard Price List",
      "description": "Default pricing for all customers",
      "currency": "AFN",
      "is_default": true,
      "customer_count": 120,
      "product_count": 45,
      "created_at": "2024-06-01T00:00:00Z"
    },
    {
      "id": 2,
      "name": "Wholesale Price List",
      "description": "Discounted pricing for wholesale customers",
      "currency": "AFN",
      "is_default": false,
      "customer_count": 30,
      "product_count": 45,
      "created_at": "2024-06-01T00:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 3,
    "last_page": 1
  }
}
```

---

### 8.15 Notifications

| Method | Endpoint                              | Description              |
|--------|---------------------------------------|--------------------------|
| GET    | `/api/v1/notifications`               | List notifications       |
| PUT    | `/api/v1/notifications/{id}/read`     | Mark as read             |
| PUT    | `/api/v1/notifications/read-all`      | Mark all as read         |

**GET /api/v1/notifications — Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "type": "order_approved",
      "title": "Order Approved",
      "message": "Order ORD-2025-001000 has been approved",
      "data": {
        "order_id": 1000,
        "order_number": "ORD-2025-001000"
      },
      "is_read": false,
      "created_at": "2025-01-15T14:00:00Z"
    },
    {
      "id": 99,
      "type": "target_achievement",
      "title": "Target Update",
      "message": "You have achieved 50% of your January sales target",
      "data": {
        "target_type": "sales",
        "percentage": 50
      },
      "is_read": true,
      "created_at": "2025-01-15T12:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 15,
    "last_page": 1,
    "unread_count": 5
  }
}
```

**PUT /api/v1/notifications/{id}/read — Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 100,
    "is_read": true,
    "read_at": "2025-01-15T15:00:00Z"
  }
}
```

**PUT /api/v1/notifications/read-all — Response 200:**

```json
{
  "success": true,
  "data": {
    "marked_count": 5
  }
}
```

---

### 8.16 Dashboard (Admin Web)

| Method | Endpoint                                  | Description                    |
|--------|-------------------------------------------|--------------------------------|
| GET    | `/api/v1/dashboard/summary`               | Dashboard summary              |
| GET    | `/api/v1/dashboard/sales`                 | Sales analytics                |
| GET    | `/api/v1/dashboard/visits`                | Visit analytics                |
| GET    | `/api/v1/dashboard/map`                   | Live map data                  |
| GET    | `/api/v1/dashboard/charts/sales-trend`    | Sales trend chart data         |
| GET    | `/api/v1/dashboard/charts/visit-completion`| Visit completion chart data    |
| GET    | `/api/v1/dashboard/map/locations`         | All current locations for map  |
| GET    | `/api/v1/reports/{type}`                  | Generate report                |
| POST   | `/api/v1/reports/{type}/export`           | Export report as file          |

**GET /api/v1/dashboard/summary — Query Parameters:**

```
GET /api/v1/dashboard/summary?date_from=2025-01-01&date_to=2025-01-31&branch_id=1
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31"
    },
    "sales": {
      "total": 125000.00,
      "currency": "AFN",
      "orders_count": 150,
      "average_order_value": 833.33,
      "comparison_previous_period": {
        "amount": 110000.00,
        "change_percent": 13.6,
        "trend": "up"
      }
    },
    "collections": {
      "total": 100000.00,
      "currency": "AFN",
      "collection_count": 120,
      "outstanding_balance": 25000.00
    },
    "visits": {
      "total": 450,
      "completed": 420,
      "completion_rate": 93.3,
      "average_duration_minutes": 35
    },
    "customers": {
      "total": 150,
      "active": 140,
      "new_this_period": 8
    },
    "salesmen": {
      "total": 10,
      "active": 9,
      "attendance_rate": 95.0
    },
    "expenses": {
      "total": 15000.00,
      "pending_approval": 3,
      "approved": 12
    }
  }
}
```

**GET /api/v1/dashboard/sales — Query Parameters:**

```
GET /api/v1/dashboard/sales?date_from=2025-01-01&date_to=2025-01-31&group_by=day&branch_id=1
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "summary": {
      "total": 125000.00,
      "currency": "AFN",
      "orders_count": 150
    },
    "series": [
      {
        "date": "2025-01-01",
        "amount": 4200.00,
        "orders_count": 5
      },
      {
        "date": "2025-01-02",
        "amount": 3800.00,
        "orders_count": 4
      }
    ],
    "by_salesman": [
      {
        "salesman_id": 5,
        "salesman_name": "John Doe",
        "amount": 28500.00,
        "orders_count": 35,
        "target_achievement": 57.0
      }
    ],
    "by_category": [
      {
        "category": "electronics",
        "amount": 75000.00,
        "percentage": 60.0
      },
      {
        "category": "accessories",
        "amount": 50000.00,
        "percentage": 40.0
      }
    ]
  }
}
```

**GET /api/v1/dashboard/visits — Query Parameters:**

```
GET /api/v1/dashboard/visits?date_from=2025-01-01&date_to=2025-01-31&group_by=day
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "summary": {
      "total_planned": 450,
      "total_completed": 420,
      "completion_rate": 93.3,
      "average_duration_minutes": 35
    },
    "series": [
      {
        "date": "2025-01-01",
        "planned": 15,
        "completed": 14,
        "completion_rate": 93.3
      }
    ],
    "by_salesman": [
      {
        "salesman_id": 5,
        "salesman_name": "John Doe",
        "planned": 50,
        "completed": 48,
        "completion_rate": 96.0
      }
    ],
    "outcomes": [
      {
        "outcome": "successful",
        "count": 300,
        "percentage": 71.4
      },
      {
        "outcome": "customer_unavailable",
        "count": 80,
        "percentage": 19.0
      },
      {
        "outcome": "no_order",
        "count": 40,
        "percentage": 9.6
      }
    ]
  }
}
```

**GET /api/v1/dashboard/map — Response 200:**

```json
{
  "success": true,
  "data": {
    "salesmen": [
      {
        "id": 5,
        "name": "John Doe",
        "latitude": 34.5553,
        "longitude": 69.2075,
        "last_update_at": "2025-01-15T10:30:00Z",
        "status": "active",
        "battery_level": 85,
        "current_visit": {
          "id": 500,
          "customer_name": "Kabul Electronics Shop"
        }
      }
    ],
    "customers": [
      {
        "id": 200,
        "name": "Kabul Electronics Shop",
        "latitude": 34.5553,
        "longitude": 69.2075,
        "last_visit_at": "2025-01-14T09:00:00Z",
        "visit_status": "visited"
      }
    ]
  }
}
```

**GET /api/v1/dashboard/charts/sales-trend — Query Parameters:**

```
GET /api/v1/dashboard/charts/sales-trend?period=monthly&months=6
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "labels": ["Aug 2024", "Sep 2024", "Oct 2024", "Nov 2024", "Dec 2024", "Jan 2025"],
    "series": [
      {
        "name": "Sales",
        "data": [95000, 102000, 110000, 98000, 115000, 125000]
      },
      {
        "name": "Collections",
        "data": [90000, 98000, 105000, 95000, 110000, 100000]
      }
    ],
    "currency": "AFN"
  }
}
```

**GET /api/v1/dashboard/charts/visit-completion — Query Parameters:**

```
GET /api/v1/dashboard/charts/visit-completion?period=weekly&weeks=4
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "labels": ["Week 1", "Week 2", "Week 3", "Week 4"],
    "series": [
      {
        "name": "Completed",
        "data": [95, 88, 92, 93]
      },
      {
        "name": "Missed",
        "data": [5, 12, 8, 7]
      }
    ]
  }
}
```

**GET /api/v1/reports/{type} — Query Parameters:**

```
GET /api/v1/reports/sales-summary?date_from=2025-01-01&date_to=2025-01-31&format=json
```

**Report Types:**

| Type               | Description                  |
|--------------------|------------------------------|
| `sales-summary`    | Sales summary report         |
| `visit-report`     | Visit activity report        |
| `collection-report`| Collections report           |
| `expense-report`   | Expense report               |
| `customer-balance` | Customer balance report      |
| `target-achievement`| Target achievement report   |
| `gps-trail`        | GPS trail report             |

**POST /api/v1/reports/{type}/export — Request:**

```json
{
  "date_from": "2025-01-01",
  "date_to": "2025-01-31",
  "format": "xlsx",
  "filters": {
    "branch_id": 1,
    "salesman_id": 5
  }
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "download_url": "https://storage.example.com/reports/sales_summary_2025_01.xlsx",
    "expires_at": "2025-01-16T10:00:00Z",
    "file_size_bytes": 245000
  }
}
```

---

## 9. Idempotency

### Idempotency Key

For operations that create resources (especially from offline clients):

```
X-Idempotency-Key: {uuid}
```

### Behavior

- Server stores idempotency key with response for **24 hours**
- Same key + same request body = same response (no duplicate creation)
- Used for: orders, collections, expenses, visit check-ins, GPS uploads
- `offline_uuid` and `X-Idempotency-Key` are part of the initial API contract (the Offline-First Foundation ships with release one), not a later phase. Advanced conflict detection and resolution are deferred to the Sync Engine phase.

### Conflict Detection

For offline-created entities:

```
POST /api/v1/orders
```

```json
{
  "offline_uuid": "client-generated-uuid",
  "customer_id": 5,
  "items": [...]
}
```

- Server checks if `offline_uuid` already exists
- If exists with **same data**: returns existing record (no duplicate)
- If exists with **different data**: returns 409 with both versions

### Conflict Response

```json
{
  "success": false,
  "error": {
    "code": "SYNC_CONFLICT",
    "message": "Resource already exists with different data.",
    "details": {
      "offline_uuid": "client-generated-uuid",
      "server_version": {
        "id": 1000,
        "status": "submitted",
        "total": 2375.00,
        "updated_at": "2025-01-15T10:00:00Z"
      },
      "client_version": {
        "status": "draft",
        "total": 2500.00
      }
    }
  }
}
```

---

## 10. GPS Upload Contract

### Bulk Upload

```
POST /api/v1/gps/locations
Content-Type: application/json
```

```json
{
  "locations": [
    {
      "client_uuid": "uuid-generated-by-device",
      "latitude": 34.5553,
      "longitude": 69.2075,
      "accuracy": 10.5,
      "altitude": 1800,
      "speed": 0,
      "heading": 180,
      "battery_level": 85,
      "is_charging": false,
      "network_status": "wifi",
      "is_mock_location": false,
      "provider": "gps",
      "recorded_at": "2025-01-15T10:30:00Z",
      "sequence_number": 1
    }
  ],
  "batch_uuid": "batch-uuid-here"
}
```

### Field Reference

| Field               | Type    | Required | Description                          |
|---------------------|---------|----------|--------------------------------------|
| `client_uuid`       | string  | Yes      | Client-generated UUID                |
| `latitude`          | number  | Yes      | Latitude (-90 to 90)                 |
| `longitude`         | number  | Yes      | Longitude (-180 to 180)              |
| `accuracy`          | number  | No       | Accuracy in meters                   |
| `altitude`          | number  | No       | Altitude in meters                   |
| `speed`             | number  | No       | Speed in m/s                         |
| `heading`           | number  | No       | Heading in degrees (0-360)           |
| `battery_level`     | integer | No       | Battery percentage (0-100)           |
| `is_charging`       | boolean | No       | Whether device is charging           |
| `network_status`    | string  | No       | `wifi`, `cellular`, `offline`        |
| `is_mock_location`  | boolean | No       | Whether location is mocked           |
| `provider`          | string  | No       | `gps`, `network`, `fused`            |
| `recorded_at`       | string  | Yes      | ISO 8601 timestamp                   |
| `sequence_number`   | integer | No       | Order within batch                   |

### Response

```json
{
  "success": true,
  "data": {
    "accepted": 50,
    "rejected": 0,
    "duplicates": 0,
    "batch_id": 12345
  }
}
```

### Validation Rules

- `latitude`: required, numeric, between -90 and 90
- `longitude`: required, numeric, between -180 and 180
- `accuracy`: numeric, min 0
- `battery_level`: integer, between 0 and 100
- `recorded_at`: required, valid ISO 8601 date, cannot be in the future
- `client_uuid`: required, unique within tenant (duplicate = rejected, not error)
- Maximum 100 locations per request

---

## 11. Sync Protocol

The sync endpoints consume the basic sync queue from the Offline-First Foundation (local-first writes, `pending`/`synced`/`failed` states, client UUIDs). Advanced sync processing (conflict resolution, retry/backoff, tombstones, bulk sync, recovery, sync hardening) is deferred to a later phase. The endpoints themselves are part of the initial API contract.

### Push (Client → Server)

```
POST /api/v1/sync/push
```

```json
{
  "last_sync_at": "2025-01-14T00:00:00Z",
  "entities": [
    {
      "type": "customer_visit",
      "action": "create",
      "offline_uuid": "client-uuid",
      "data": { ... }
    },
    {
      "type": "order",
      "action": "create",
      "offline_uuid": "client-uuid",
      "data": { ... }
    },
    {
      "type": "customer",
      "action": "update",
      "offline_uuid": "client-uuid",
      "server_id": 200,
      "data": { ... }
    }
  ]
}
```

### Entity Types

| Type               | Actions           | Offline UUID | Server ID |
|--------------------|-------------------|--------------|-----------|
| `customer`         | create, update    | Yes          | On update |
| `customer_visit`   | create            | Yes          | No        |
| `order`            | create, update    | Yes          | On update |
| `collection`       | create            | Yes          | No        |
| `expense`          | create            | Yes          | No        |
| `visit_note`       | create            | Yes          | No        |
| `visit_photo`      | create            | Yes          | No        |
| `attendance`       | create, update    | Yes          | On update |

### Pull (Server → Client)

```
GET /api/v1/sync/pull?since=2025-01-14T00:00:00Z&types[]=customers&types[]=products&types[]=routes
```

### Response

```json
{
  "success": true,
  "data": {
    "changes": [
      {
        "type": "customers",
        "items": [
          {
            "id": 150,
            "name": "New Customer",
            "action": "created",
            "updated_at": "2025-01-15T08:00:00Z",
            "data": { ... }
          },
          {
            "id": 42,
            "name": "Updated Customer",
            "action": "updated",
            "updated_at": "2025-01-15T09:00:00Z",
            "data": { ... }
          }
        ],
        "count": 2
      },
      {
        "type": "products",
        "items": [
          {
            "id": 10,
            "name": "Product A",
            "action": "updated",
            "updated_at": "2025-01-15T07:00:00Z",
            "data": { ... }
          }
        ],
        "count": 1
      }
    ],
    "server_time": "2025-01-15T10:30:00Z",
    "has_more": false
  }
}
```

### Sync Types Available for Pull

| Type        | Description                   |
|-------------|-------------------------------|
| `customers` | Customer master data          |
| `products`  | Product catalog               |
| `routes`    | Route definitions             |
| `price_lists` | Price list data            |
| `targets`   | Target assignments            |
| `config`    | App configuration changes     |

---

## 12. Validation Error Format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email has already been taken."],
      "amount": ["The amount must be greater than 0."],
      "items": {
        "0": {
          "product_id": ["The product id field is required."],
          "quantity": ["The quantity must be at least 1."]
        }
      }
    }
  }
}
```

### Error Detail Formats

**Simple field errors:**

```json
{
  "details": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

**Nested/array errors:**

```json
{
  "details": {
    "items": {
      "0": {
        "product_id": ["The product id field is required."],
        "quantity": ["The quantity must be at least 1."]
      }
    }
  }
}
```

---

## 13. Web Dashboard API

### Same Base API

The web dashboard uses the same `/api/v1/` endpoints with session-based auth (CSRF + cookie) OR Sanctum tokens.

### Additional Endpoints

| Method | Endpoint                                      | Description                    |
|--------|-----------------------------------------------|--------------------------------|
| GET    | `/api/v1/dashboard/charts/sales-trend`        | Sales trend chart data         |
| GET    | `/api/v1/dashboard/charts/visit-completion`   | Visit completion chart data    |
| GET    | `/api/v1/dashboard/map/locations`             | All current locations for map  |
| GET    | `/api/v1/reports/{type}`                      | Generate report                |
| POST   | `/api/v1/reports/{type}/export`               | Export report as file          |

### Authentication for Web

**Session-based (Inertia.js):**

```
POST /login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=secret&_token={csrf_token}
```

**Token-based (API):**

```
Authorization: Bearer {sanctum_token}
```

---

## 14. Webhook Support (Future)

| Method | Endpoint                       | Description              |
|--------|--------------------------------|--------------------------|
| POST   | `/api/v1/webhooks`             | Create webhook           |
| GET    | `/api/v1/webhooks`             | List webhooks            |
| DELETE | `/api/v1/webhooks/{id}`        | Delete webhook           |

### Webhook Events (Future)

| Event                    | Description                    |
|--------------------------|--------------------------------|
| `order.created`          | New order created              |
| `order.status_changed`   | Order status updated           |
| `collection.created`     | New collection recorded        |
| `expense.submitted`      | New expense submitted          |
| `expense.approved`       | Expense approved               |
| `expense.rejected`       | Expense rejected               |
| `customer.created`       | New customer added             |
| `visit.completed`        | Visit completed                |
| `target.achieved`        | Target achieved                |

### Webhook Payload

```json
{
  "id": "evt_12345",
  "event": "order.created",
  "created_at": "2025-01-15T10:00:00Z",
  "data": {
    "id": 1000,
    "order_number": "ORD-2025-001000",
    "customer_id": 200,
    "total": 2375.00,
    "currency": "AFN",
    "status": "draft"
  }
}
```

---

*End of API Contract Document*
