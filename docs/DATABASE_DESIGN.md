# Database Design — Field Sales SaaS Platform

> **Status:** Planning Document — No migrations created yet
> **Database:** MySQL 8
> **Framework:** Laravel 13
> **Multi-tenancy:** Shared database with `tenant_id` column
> **Mobile strategy:** Offline-first with UUID-based entity creation

---

## 1. Design Principles

| Principle | Rationale |
|-----------|-----------|
| `tenant_id` on every business table | Row-level data isolation in shared database; enables single-query tenant scoping |
| UUIDs for offline-created entities | Mobile devices must create records without a server-assigned `id`; UUID stored in `uuid` column alongside bigint `id` |
| UTC timestamps everywhere | Avoids timezone ambiguity across branches, regions, and devices |
| Soft deletes on master data, hard delete on transactional history | Preserve referential integrity for lookup entities; keep transactional tables lean |
| Strategic indexing | Composite indexes start with `tenant_id` to leverage partition pruning; avoid over-indexing high-write tables |
| Foreign keys for data integrity | Prevent orphaned rows; enforce cascading behavior at the database level |
| No JSON columns where normalization is better | JSON columns break query performance and prevent indexing; prefer junction tables |
| String values instead of MySQL ENUM | MySQL ENUM requires ALTER TABLE to add values; strings allow app-level validation and future flexibility |

---

## 2. Conventions

### Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Table names | snake_case, plural | `customer_visits`, `order_items` |
| Column names | snake_case | `check_in_time`, `is_active` |
| Primary key | `id` — bigint unsigned auto-increment | `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` |
| UUID column | `uuid` — char(36) | `uuid CHAR(36) NOT NULL` |
| Tenant column | `tenant_id` — foreign key to `tenants.id` | `tenant_id BIGINT UNSIGNED NOT NULL` |

### Timestamps

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `created_at` | datetime | NOT NULL | UTC, set by Laravel |
| `updated_at` | datetime | NULL | UTC, set by Laravel |
| `deleted_at` | datetime | NULL | Soft delete marker |

### Audit Columns

| Column | Purpose |
|--------|---------|
| `created_by` | User ID who created the record |
| `updated_by` | User ID who last modified the record |

### Status Columns

All status columns use `VARCHAR(50)` with application-validated string values — never MySQL ENUM.

### Offline UUIDs

Records created offline store a client-generated UUID in the `uuid` column. On sync, the server assigns a bigint `id` and stores the original UUID in `offline_uuid` or `uuid` for deduplication.

---

## 3. Table Definitions

### 3.1 Tenancy & Identity

#### `tenants`

Multi-tenant root table. Each tenant represents a company.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated identifier |
| `name` | varchar(255) | NO | — | Company display name |
| `slug` | varchar(100) | NO | — | URL-safe identifier |
| `domain` | varchar(255) | YES | NULL | Custom domain if applicable |
| `logo_url` | varchar(500) | YES | NULL | Company logo |
| `timezone` | varchar(50) | NO | 'UTC' | Default timezone for reports |
| `default_currency` | char(3) | NO | 'USD' | ISO 4217 currency code |
| `locale` | varchar(10) | NO | 'en' | Default locale |
| `settings` | json | YES | NULL | Flexible key-value settings |
| `subscription_status` | varchar(50) | NO | 'active' | active, trial, suspended, cancelled |
| `trial_ends_at` | datetime | YES | NULL | Trial expiration |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `tenants_uuid_unique` | `uuid` | UNIQUE |
| `tenants_slug_unique` | `slug` | UNIQUE |
| `tenants_domain_unique` | `domain` | UNIQUE |

**Foreign Keys:** None (root table)

---

#### `branches`

Physical locations / offices for a tenant.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated identifier |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `name` | varchar(255) | NO | — | Branch name |
| `code` | varchar(50) | NO | — | Short unique code per tenant |
| `address` | varchar(500) | YES | NULL | Street address |
| `city` | varchar(100) | YES | NULL | |
| `province` | varchar(100) | YES | NULL | |
| `phone` | varchar(50) | YES | NULL | |
| `latitude` | decimal(10,7) | YES | NULL | |
| `longitude` | decimal(10,7) | YES | NULL | |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `branches_tenant_id` | `tenant_id` | INDEX |
| `branches_tenant_code_unique` | `tenant_id`, `code` | UNIQUE |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

---

#### `users`

System users — salesmen, supervisors, admins, etc.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated identifier |
| `tenant_id` | bigint unsigned | YES | NULL | NULL for super admin |
| `name` | varchar(255) | NO | — | Full name |
| `email` | varchar(255) | NO | — | Login credential |
| `email_verified_at` | datetime | YES | NULL | |
| `password` | varchar(255) | NO | — | Hashed |
| `phone` | varchar(50) | YES | NULL | |
| `role` | varchar(50) | NO | — | super_admin, admin, manager, salesman, supervisor |
| `is_active` | tinyint(1) | NO | 1 | |
| `last_login_at` | datetime | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `users_email_unique` | `email` | UNIQUE |
| `users_tenant_id` | `tenant_id` | INDEX |
| `users_role` | `role` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE SET NULL |

---

#### `model_has_roles`

Spatie-style polymorphic role assignment.

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | NO | Primary key |
| `user_id` | bigint unsigned | NO | FK → `users.id` |
| `role_id` | bigint unsigned | NO | FK → `roles.id` |
| `tenant_id` | bigint unsigned | NO | FK → `tenants.id` |
| `model_type` | varchar(255) | NO | Polymorphic type |
| `model_id` | bigint unsigned | NO | Polymorphic ID |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `mhr_user_model` | `user_id`, `model_type`, `model_id` | UNIQUE |
| `mhr_tenant_id` | `tenant_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `user_id` | `users.id` ON DELETE CASCADE |
| `role_id` | `roles.id` ON DELETE CASCADE |
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

---

#### `roles`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | NO | Primary key |
| `name` | varchar(125) | NO | e.g. admin, salesman |
| `guard_name` | varchar(125) | NO | web, api |
| `created_at` | datetime | YES | |

**Foreign Keys:** None (shared across tenants via `model_has_roles.tenant_id`)

---

#### `permissions`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | NO | Primary key |
| `name` | varchar(125) | NO | e.g. orders.create |
| `guard_name` | varchar(125) | NO | |
| `created_at` | datetime | YES | |

---

#### `role_has_permissions`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `role_id` | bigint unsigned | NO | FK → `roles.id` |
| `permission_id` | bigint unsigned | NO | FK → `permissions.id` |

**Indexes:** Primary key on (`role_id`, `permission_id`)

---

#### `audit_logs`

Immutable audit trail for all significant actions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | YES | NULL | FK → `users.id` |
| `event` | varchar(50) | NO | — | created, updated, deleted, login, etc. |
| `auditable_type` | varchar(255) | NO | — | Polymorphic type |
| `auditable_id` | bigint unsigned | NO | — | Polymorphic ID |
| `old_values` | json | YES | NULL | Previous attribute values |
| `new_values` | json | YES | NULL | Updated attribute values |
| `url` | varchar(500) | YES | NULL | Request URL |
| `ip_address` | varchar(45) | YES | NULL | IPv4 or IPv6 |
| `user_agent` | varchar(500) | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `audit_logs_tenant_created` | `tenant_id`, `created_at` | INDEX |
| `audit_logs_auditable` | `auditable_type`, `auditable_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE SET NULL |

**Notes:** Hard delete with retention policy (see §8). No `updated_at` — append-only.

---

### 3.2 Devices

#### `devices`

Registered mobile devices for push notifications and sync tracking.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Server-assigned UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `salesman_id` | bigint unsigned | YES | NULL | FK → `salesmen.id` |
| `device_uuid` | varchar(255) | NO | — | Hardware-level device ID |
| `installation_uuid` | varchar(255) | NO | — | App installation identifier |
| `device_model` | varchar(100) | YES | NULL | e.g. "Samsung Galaxy S24" |
| `manufacturer` | varchar(100) | YES | NULL | |
| `android_version` | varchar(20) | YES | NULL | |
| `app_version` | varchar(20) | YES | NULL | |
| `push_token` | varchar(500) | YES | NULL | FCM registration token |
| `fcm_token` | varchar(500) | YES | NULL | Firebase Cloud Messaging token |
| `is_active` | tinyint(1) | NO | 1 | |
| `registered_at` | datetime | YES | NULL | First registration time |
| `last_seen_at` | datetime | YES | NULL | Last activity timestamp |
| `revoked_at` | datetime | YES | NULL | When access was revoked |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `devices_tenant_user` | `tenant_id`, `user_id` | INDEX |
| `devices_device_uuid_unique` | `device_uuid` | UNIQUE |
| `devices_installation_uuid_unique` | `installation_uuid` | UNIQUE |
| `devices_push_token` | `push_token`(191) | INDEX | Partial index on first 191 chars |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE SET NULL |

---

### 3.3 Sales Team

#### `salesmen`

Field sales representatives.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | YES | NULL | FK → `users.id` (nullable if no system login) |
| `employee_code` | varchar(50) | NO | — | Unique per tenant |
| `first_name` | varchar(100) | NO | — | |
| `last_name` | varchar(100) | YES | NULL | |
| `phone` | varchar(50) | YES | NULL | |
| `email` | varchar(255) | YES | NULL | |
| `hire_date` | date | YES | NULL | |
| `designation` | varchar(100) | YES | NULL | Job title |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `salesmen_tenant_code_unique` | `tenant_id`, `employee_code` | UNIQUE |
| `salesmen_user_id` | `user_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE SET NULL |

---

#### `supervisors`

Field supervisors who manage salesmen.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `employee_code` | varchar(50) | NO | — | Unique per tenant |
| `first_name` | varchar(100) | NO | — | |
| `last_name` | varchar(100) | YES | NULL | |
| `phone` | varchar(50) | YES | NULL | |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `supervisors_tenant_code_unique` | `tenant_id`, `employee_code` | UNIQUE |
| `supervisors_user_id` | `user_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |

---

#### `salesman_assignments`

Historical record of salesman territory/route assignments.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `branch_id` | bigint unsigned | YES | NULL | FK → `branches.id` |
| `territory_id` | bigint unsigned | NO | — | FK → `territories.id` |
| `route_id` | bigint unsigned | YES | NULL | FK → `routes.id` |
| `supervisor_id` | bigint unsigned | YES | NULL | FK → `supervisors.id` |
| `effective_from` | date | NO | — | Assignment start date |
| `effective_to` | date | YES | NULL | NULL = currently active |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `created_by` | bigint unsigned | YES | NULL | FK → `users.id` |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `sa_tenant_salesman` | `tenant_id`, `salesman_id` | INDEX |
| `sa_effective_from` | `effective_from` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `branch_id` | `branches.id` ON DELETE SET NULL |
| `territory_id` | `territories.id` ON DELETE CASCADE |
| `route_id` | `routes.id` ON DELETE SET NULL |
| `supervisor_id` | `supervisors.id` ON DELETE SET NULL |

---

#### `supervisor_assignments`

Historical record of supervisor branch/territory assignments.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `supervisor_id` | bigint unsigned | NO | — | FK → `supervisors.id` |
| `branch_id` | bigint unsigned | YES | NULL | FK → `branches.id` |
| `territory_id` | bigint unsigned | YES | NULL | FK → `territories.id` |
| `effective_from` | date | NO | — | |
| `effective_to` | date | YES | NULL | NULL = currently active |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `sua_tenant_supervisor` | `tenant_id`, `supervisor_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `supervisor_id` | `supervisors.id` ON DELETE CASCADE |
| `branch_id` | `branches.id` ON DELETE SET NULL |
| `territory_id` | `territories.id` ON DELETE SET NULL |

---

### 3.4 Customers

#### `customers`

Retail / business customers visited by salesmen.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `branch_id` | bigint unsigned | NO | — | FK → `branches.id` |
| `code` | varchar(50) | NO | — | Unique per tenant |
| `business_name` | varchar(255) | NO | — | |
| `contact_person` | varchar(255) | YES | NULL | |
| `phone` | varchar(50) | YES | NULL | |
| `whatsapp` | varchar(50) | YES | NULL | |
| `category_id` | bigint unsigned | YES | NULL | FK → `customer_categories.id` |
| `province` | varchar(100) | YES | NULL | |
| `district` | varchar(100) | YES | NULL | |
| `address` | varchar(500) | YES | NULL | |
| `latitude` | decimal(10,7) | YES | NULL | |
| `longitude` | decimal(10,7) | YES | NULL | |
| `geofence_radius` | int unsigned | NO | 100 | Meters; geofence for check-in validation |
| `photo_url` | varchar(500) | YES | NULL | |
| `assigned_salesman_id` | bigint unsigned | YES | NULL | FK → `salesmen.id` |
| `territory_id` | bigint unsigned | NO | — | FK → `territories.id` |
| `route_id` | bigint unsigned | YES | NULL | FK → `routes.id` |
| `credit_limit` | decimal(12,2) | NO | 0.00 | |
| `outstanding_balance` | decimal(12,2) | NO | 0.00 | Running balance |
| `price_list_id` | bigint unsigned | YES | NULL | FK → `price_lists.id` |
| `visit_frequency` | varchar(50) | YES | NULL | daily, weekly, biweekly, monthly |
| `is_active` | tinyint(1) | NO | 1 | |
| `notes` | text | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `customers_tenant_code_unique` | `tenant_id`, `code` | UNIQUE |
| `customers_assigned_salesman` | `assigned_salesman_id` | INDEX |
| `customers_territory_id` | `territory_id` | INDEX |
| `customers_route_id` | `route_id` | INDEX |
| `customers_lat_lng` | `latitude`, `longitude` | INDEX | For proximity queries |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `branch_id` | `branches.id` ON DELETE CASCADE |
| `category_id` | `customer_categories.id` ON DELETE SET NULL |
| `assigned_salesman_id` | `salesmen.id` ON DELETE SET NULL |
| `territory_id` | `territories.id` ON DELETE CASCADE |
| `route_id` | `routes.id` ON DELETE SET NULL |
| `price_list_id` | `price_lists.id` ON DELETE SET NULL |

---

#### `customer_categories`

Categorization for customers (e.g., wholesale, retail, key account).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `name` | varchar(100) | NO | — | |
| `description` | varchar(500) | YES | NULL | |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `cc_tenant_id` | `tenant_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

**Notes:** No soft delete — hard delete if no customers reference it.

---

#### `customer_location_history`

Tracks when a customer's registered location is changed.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `customer_id` | bigint unsigned | NO | — | FK → `customers.id` |
| `latitude` | decimal(10,7) | NO | — | |
| `longitude` | decimal(10,7) | NO | — | |
| `address` | varchar(500) | YES | NULL | |
| `changed_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `changed_at` | datetime | NO | CURRENT_TIMESTAMP | When the change was made |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `clh_customer_changed` | `customer_id`, `changed_at` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `customer_id` | `customers.id` ON DELETE CASCADE |
| `changed_by` | `users.id` ON DELETE SET NULL |

---

### 3.5 Territories & Routes

#### `territories`

Geographic regions that group routes and customers.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `branch_id` | bigint unsigned | NO | — | FK → `branches.id` |
| `name` | varchar(255) | NO | — | |
| `code` | varchar(50) | NO | — | Unique per tenant |
| `description` | varchar(500) | YES | NULL | |
| `latitude` | decimal(10,7) | YES | NULL | Center point |
| `longitude` | decimal(10,7) | YES | NULL | Center point |
| `radius_km` | decimal(6,2) | YES | NULL | Approximate radius |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `territories_tenant_code_unique` | `tenant_id`, `code` | UNIQUE |
| `territories_branch_id` | `branch_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `branch_id` | `branches.id` ON DELETE CASCADE |

---

#### `routes`

Ordered sequences of customers to visit within a territory.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `territory_id` | bigint unsigned | NO | — | FK → `territories.id` |
| `name` | varchar(255) | NO | — | |
| `code` | varchar(50) | NO | — | |
| `description` | varchar(500) | YES | NULL | |
| `weekday` | tinyint unsigned | YES | NULL | 0=Sun..6=Sat; NULL = any day |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `routes_tenant_territory` | `tenant_id`, `territory_id` | INDEX |
| `routes_weekday` | `weekday` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `territory_id` | `territories.id` ON DELETE CASCADE |

---

#### `route_customers`

Junction table linking routes to customers with visit ordering.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `route_id` | bigint unsigned | NO | — | FK → `routes.id` |
| `customer_id` | bigint unsigned | NO | — | FK → `customers.id` |
| `visit_order` | int unsigned | NO | — | Sequence within the route |
| `effective_from` | date | NO | CURRENT_DATE | When this ordering starts |
| `effective_to` | date | YES | NULL | NULL = currently active |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `rc_route_order` | `route_id`, `visit_order` | INDEX |
| `rc_customer_id` | `customer_id` | INDEX |
| `rc_tenant_id` | `tenant_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `route_id` | `routes.id` ON DELETE CASCADE |
| `customer_id` | `customers.id` ON DELETE CASCADE |

---

### 3.6 GPS Tracking

#### `current_locations`

Latest known position for each user. Overwritten on each sync.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `latitude` | decimal(10,7) | NO | — | |
| `longitude` | decimal(10,7) | NO | — | |
| `horizontal_accuracy` | decimal(8,2) | YES | NULL | Meters |
| `altitude` | decimal(8,2) | YES | NULL | Meters above sea level |
| `speed` | decimal(6,2) | YES | NULL | m/s |
| `heading` | decimal(5,2) | YES | NULL | Degrees 0-360 |
| `battery_level` | tinyint unsigned | YES | NULL | 0-100 |
| `is_charging` | tinyint(1) | NO | 0 | |
| `network_status` | varchar(20) | YES | NULL | wifi, cellular, offline |
| `is_mock_location` | tinyint(1) | NO | 0 | GPS spoofing detection |
| `provider` | varchar(50) | YES | NULL | gps, network, fused |
| `recorded_at` | datetime | NO | — | When GPS fix was taken |
| `received_at` | datetime | NO | CURRENT_TIMESTAMP | When server received it |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `cl_tenant_user_unique` | `tenant_id`, `user_id` | UNIQUE | One row per user |
| `cl_recorded_at` | `recorded_at` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `device_id` | `devices.id` ON DELETE CASCADE |

---

#### `location_history`

High-volume append-only GPS log. Expected to grow to millions of rows.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `salesman_id` | bigint unsigned | YES | NULL | FK → `salesmen.id` |
| `device_id` | bigint unsigned | YES | NULL | FK → `devices.id` |
| `latitude` | decimal(10,7) | NO | — | |
| `longitude` | decimal(10,7) | NO | — | |
| `horizontal_accuracy` | decimal(8,2) | YES | NULL | Meters |
| `altitude` | decimal(8,2) | YES | NULL | |
| `speed` | decimal(6,2) | YES | NULL | m/s |
| `heading` | decimal(5,2) | YES | NULL | Degrees |
| `battery_level` | tinyint unsigned | YES | NULL | 0-100 |
| `is_charging` | tinyint(1) | NO | 0 | |
| `network_status` | varchar(20) | YES | NULL | |
| `is_mock_location` | tinyint(1) | NO | 0 | |
| `provider` | varchar(50) | YES | NULL | |
| `recorded_at` | datetime | NO | — | When GPS fix was taken |
| `received_at` | datetime | NO | CURRENT_TIMESTAMP | When server received it |
| `sync_batch_id` | bigint unsigned | YES | NULL | FK → `location_sync_batches.id` |
| `sequence_number` | int unsigned | YES | NULL | Order within batch |
| `metadata` | json | YES | NULL | Extra device-reported data |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `lh_tenant_user_recorded` | `tenant_id`, `user_id`, `recorded_at` | INDEX |
| `lh_recorded_at` | `recorded_at` | INDEX | For archival batch jobs |
| `lh_sync_batch_id` | `sync_batch_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE SET NULL |
| `device_id` | `devices.id` ON DELETE SET NULL |
| `sync_batch_id` | `location_sync_batches.id` ON DELETE SET NULL |

**Notes:**
- Append-only table. Never updated.
- Partition by month on `recorded_at` when table exceeds ~10M rows (see §7).
- Retention policy: configurable, default 90 days active, then archive (see §8).

---

#### `location_sync_batches`

Groups of GPS points received from a device in a single sync operation.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `batch_uuid` | char(36) | NO | — | Client-generated batch ID |
| `point_count` | int unsigned | NO | — | Number of points in batch |
| `received_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `processed_at` | datetime | YES | NULL | When all points were persisted |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `lsb_tenant_device` | `tenant_id`, `device_id` | INDEX |
| `lsb_batch_uuid_unique` | `batch_uuid` | UNIQUE |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `device_id` | `devices.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |

---

### 3.7 Attendance & Work Sessions

#### `work_sessions`

Daily clock-in/clock-out tracking for salesmen.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `salesman_id` | bigint unsigned | YES | NULL | FK → `salesmen.id` |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `date` | date | NO | — | Work date |
| `start_time` | datetime | NO | — | Clock-in timestamp (UTC) |
| `end_time` | datetime | YES | NULL | Clock-out timestamp (UTC) |
| `start_latitude` | decimal(10,7) | NO | — | |
| `start_longitude` | decimal(10,7) | NO | — | |
| `end_latitude` | decimal(10,7) | YES | NULL | |
| `end_longitude` | decimal(10,7) | YES | NULL | |
| `status` | varchar(50) | NO | 'active' | active, completed, corrected, approved |
| `duration_minutes` | int unsigned | YES | NULL | Calculated on clock-out |
| `is_late_start` | tinyint(1) | NO | 0 | |
| `is_early_finish` | tinyint(1) | NO | 0 | |
| `notes` | text | YES | NULL | |
| `corrected_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `corrected_at` | datetime | YES | NULL | |
| `correction_reason` | varchar(500) | YES | NULL | |
| `approved_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `approved_at` | datetime | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `ws_tenant_user_date_unique` | `tenant_id`, `user_id`, `date` | UNIQUE | One session per user per day |
| `ws_status` | `status` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE SET NULL |
| `device_id` | `devices.id` ON DELETE CASCADE |
| `corrected_by` | `users.id` ON DELETE SET NULL |
| `approved_by` | `users.id` ON DELETE SET NULL |

---

### 3.8 Customer Visits

#### `customer_visits`

Core transactional table — every check-in/check-out at a customer location.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `customer_id` | bigint unsigned | NO | — | FK → `customers.id` |
| `route_id` | bigint unsigned | YES | NULL | FK → `routes.id` |
| `work_session_id` | bigint unsigned | YES | NULL | FK → `work_sessions.id` |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `visit_type` | varchar(50) | NO | — | planned, unplanned |
| `check_in_time` | datetime | NO | — | UTC |
| `check_out_time` | datetime | YES | NULL | UTC |
| `check_in_latitude` | decimal(10,7) | NO | — | |
| `check_in_longitude` | decimal(10,7) | NO | — | |
| `check_out_latitude` | decimal(10,7) | YES | NULL | |
| `check_out_longitude` | decimal(10,7) | YES | NULL | |
| `distance_from_customer_meters` | decimal(8,2) | YES | NULL | Distance at check-in vs registered location |
| `accuracy_meters` | decimal(8,2) | YES | NULL | GPS accuracy at check-in |
| `duration_minutes` | int unsigned | YES | NULL | Calculated on check-out |
| `visit_outcome` | varchar(50) | YES | NULL | successful, no_order, customer_closed, other |
| `verification_status` | varchar(50) | NO | 'unverified' | verified, unverified, suspicious |
| `notes` | text | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `cv_tenant_salesman_checkin` | `tenant_id`, `salesman_id`, `check_in_time` | INDEX |
| `cv_customer_checkin` | `customer_id`, `check_in_time` | INDEX |
| `cv_route_id` | `route_id` | INDEX |
| `cv_work_session_id` | `work_session_id` | INDEX |
| `cv_verification_status` | `verification_status` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `customer_id` | `customers.id` | ON DELETE CASCADE |
| `route_id` | `routes.id` ON DELETE SET NULL |
| `work_session_id` | `work_sessions.id` ON DELETE SET NULL |
| `device_id` | `devices.id` ON DELETE CASCADE |

---

#### `visit_photos`

Photos taken during a visit (proof of visit, shelf photos, etc.).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `visit_id` | bigint unsigned | NO | — | FK → `customer_visits.id` |
| `photo_url` | varchar(500) | NO | — | Storage path |
| `caption` | varchar(255) | YES | NULL | |
| `latitude` | decimal(10,7) | YES | NULL | Where photo was taken |
| `longitude` | decimal(10,7) | YES | NULL | |
| `taken_at` | datetime | NO | — | When photo was captured |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `vp_visit_id` | `visit_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `visit_id` | `customer_visits.id` ON DELETE CASCADE |

---

#### `visit_suspicious_flags`

Flags raised when visit verification detects anomalies.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `visit_id` | bigint unsigned | NO | — | FK → `customer_visits.id` |
| `flag_type` | varchar(50) | NO | — | distance_mismatch, gps_spoofing, too_fast, duplicate_checkin |
| `description` | varchar(500) | YES | NULL | |
| `severity` | varchar(20) | NO | — | low, medium, high |
| `resolved_at` | datetime | YES | NULL | |
| `resolved_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `vsf_tenant_flag_type` | `tenant_id`, `flag_type` | INDEX |
| `vsf_visit_id` | `visit_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `visit_id` | `customer_visits.id` ON DELETE CASCADE |
| `resolved_by` | `users.id` ON DELETE SET NULL |

---

### 3.9 Products (Lightweight V1)

#### `products`

Products available for ordering. Lightweight for V1 — no inventory tracking.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `name` | varchar(255) | NO | — | |
| `sku` | varchar(100) | NO | — | Unique per tenant |
| `unit` | varchar(50) | NO | 'piece' | piece, box, case, kg, litre |
| `price` | decimal(12,2) | NO | — | Default price |
| `is_active` | tinyint(1) | NO | 1 | |
| `category` | varchar(100) | YES | NULL | Free-text category for V1 |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `products_tenant_sku_unique` | `tenant_id`, `sku` | UNIQUE |
| `products_tenant_name` | `tenant_id`, `name` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

---

#### `price_lists`

Named price lists for different customer segments or regions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `name` | varchar(255) | NO | — | |
| `is_default` | tinyint(1) | NO | 0 | |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `pl_tenant_id` | `tenant_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

---

#### `price_list_items`

Product prices within a specific price list.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `price_list_id` | bigint unsigned | NO | — | FK → `price_lists.id` |
| `product_id` | bigint unsigned | NO | — | FK → `products.id` |
| `price` | decimal(12,2) | NO | — | Price for this product in this list |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `pli_price_list_id` | `price_list_id` | INDEX |
| `pli_product_id` | `product_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `price_list_id` | `price_lists.id` ON DELETE CASCADE |
| `product_id` | `products.id` ON DELETE CASCADE |

---

### 3.10 Orders

#### `orders`

Sales orders created by salesmen in the field.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `customer_id` | bigint unsigned | NO | — | FK → `customers.id` |
| `visit_id` | bigint unsigned | YES | NULL | FK → `customer_visits.id` |
| `order_number` | varchar(50) | NO | — | Human-readable, generated server-side |
| `status` | varchar(50) | NO | 'draft' | draft, submitted, approved, processing, dispatched, delivered, cancelled |
| `order_date` | date | NO | — | |
| `total_amount` | decimal(12,2) | NO | 0.00 | Sum of line items |
| `discount_amount` | decimal(12,2) | NO | 0.00 | |
| `tax_amount` | decimal(12,2) | NO | 0.00 | |
| `net_amount` | decimal(12,2) | NO | 0.00 | total - discount + tax |
| `payment_type` | varchar(50) | NO | — | cash, credit, partial |
| `amount_paid` | decimal(12,2) | NO | 0.00 | |
| `notes` | text | YES | NULL | |
| `offline_uuid` | char(36) | YES | NULL | UUID assigned on device when created offline |
| `sync_status` | varchar(50) | NO | 'synced' | synced, pending, conflict |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `orders_tenant_salesman_date` | `tenant_id`, `salesman_id`, `order_date` | INDEX |
| `orders_customer_id` | `customer_id` | INDEX |
| `orders_status` | `status` | INDEX |
| `orders_offline_uuid_unique` | `offline_uuid` | UNIQUE (conditional: WHERE offline_uuid IS NOT NULL) |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `customer_id` | `customers.id` ON DELETE CASCADE |
| `visit_id` | `customer_visits.id` ON DELETE SET NULL |

---

#### `order_items`

Individual line items within an order.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `order_id` | bigint unsigned | NO | — | FK → `orders.id` |
| `product_id` | bigint unsigned | NO | — | FK → `products.id` |
| `quantity` | decimal(10,2) | NO | — | Supports fractional units (e.g. 2.5 kg) |
| `unit_price` | decimal(12,2) | NO | — | Price at time of order |
| `discount` | decimal(12,2) | NO | 0.00 | Line-level discount |
| `total_price` | decimal(12,2) | NO | — | (quantity × unit_price) - discount |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `oi_order_id` | `order_id` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `order_id` | `orders.id` ON DELETE CASCADE |
| `product_id` | `products.id` ON DELETE CASCADE |

---

### 3.11 Collections

#### `collections`

Payment collections recorded by salesmen from customers.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `customer_id` | bigint unsigned | NO | — | FK → `customers.id` |
| `visit_id` | bigint unsigned | YES | NULL | FK → `customer_visits.id` |
| `amount` | decimal(12,2) | NO | — | |
| `currency` | char(3) | NO | 'USD' | ISO 4217 |
| `payment_method` | varchar(50) | NO | — | cash, bank_transfer, mobile_money, other |
| `receipt_number` | varchar(100) | YES | NULL | External receipt reference |
| `latitude` | decimal(10,7) | YES | NULL | |
| `longitude` | decimal(10,7) | YES | NULL | |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `collected_at` | datetime | NO | — | When collection was made |
| `notes` | text | YES | NULL | |
| `receipt_photo_url` | varchar(500) | YES | NULL | Photo of receipt |
| `offline_uuid` | char(36) | YES | NULL | UUID assigned on device |
| `sync_status` | varchar(50) | NO | 'synced' | synced, pending, conflict |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `coll_tenant_salesman_collected` | `tenant_id`, `salesman_id`, `collected_at` | INDEX |
| `coll_customer_id` | `customer_id` | INDEX |
| `coll_offline_uuid_unique` | `offline_uuid` | UNIQUE (conditional: WHERE offline_uuid IS NOT NULL) |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `customer_id` | `customers.id` ON DELETE CASCADE |
| `visit_id` | `customer_visits.id` ON DELETE SET NULL |
| `device_id` | `devices.id` ON DELETE CASCADE |

---

### 3.12 Targets

#### `targets`

Sales targets set for salesmen, teams, routes, territories, or branches.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `targetable_type` | varchar(50) | NO | — | salesman, team, route, territory, branch |
| `targetable_id` | bigint unsigned | NO | — | Polymorphic ID |
| `metric` | varchar(50) | NO | — | sales_value, collections, orders, visits, new_customers, product_quantity |
| `product_id` | bigint unsigned | YES | NULL | FK → `products.id` (for product-specific targets) |
| `period_type` | varchar(50) | NO | — | daily, weekly, monthly, custom |
| `period_start` | date | NO | — | |
| `period_end` | date | NO | — | |
| `target_value` | decimal(12,2) | NO | — | Goal amount |
| `achieved_value` | decimal(12,2) | NO | 0.00 | Current progress |
| `branch_id` | bigint unsigned | YES | NULL | FK → `branches.id` |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `targets_tenant_targetable_period` | `tenant_id`, `targetable_type`, `targetable_id`, `period_start` | INDEX |
| `targets_period_range` | `period_start`, `period_end` | INDEX |
| `targets_metric` | `metric` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `product_id` | `products.id` ON DELETE SET NULL |
| `branch_id` | `branches.id` ON DELETE SET NULL |

---

### 3.13 Expenses

#### `expenses`

Field expenses submitted by salesmen for approval.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `category` | varchar(50) | NO | — | fuel, food, parking, transport, accommodation, other |
| `amount` | decimal(12,2) | NO | — | |
| `currency` | char(3) | NO | 'USD' | ISO 4217 |
| `description` | varchar(500) | YES | NULL | |
| `receipt_photo_url` | varchar(500) | YES | NULL | |
| `latitude` | decimal(10,7) | YES | NULL | Where expense was incurred |
| `longitude` | decimal(10,7) | YES | NULL | |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `expense_date` | date | NO | — | |
| `status` | varchar(50) | NO | 'pending' | pending, approved, rejected |
| `approved_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `approved_at` | datetime | YES | NULL | |
| `rejection_reason` | varchar(500) | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `exp_tenant_salesman_date` | `tenant_id`, `salesman_id`, `expense_date` | INDEX |
| `exp_status` | `status` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |
| `device_id` | `devices.id` ON DELETE CASCADE |
| `approved_by` | `users.id` ON DELETE SET NULL |

---

### 3.14 Commissions

#### `commission_rules`

Configurable rules for calculating salesman commissions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `name` | varchar(255) | NO | — | |
| `type` | varchar(50) | NO | — | percentage_sales, percentage_collections, fixed_per_product, tiered, bonus |
| `rate` | decimal(8,4) | YES | NULL | Percentage or fixed rate |
| `product_id` | bigint unsigned | YES | NULL | FK → `products.id` (NULL = applies to all) |
| `min_target` | decimal(12,2) | YES | NULL | Minimum threshold to qualify |
| `max_target` | decimal(12,2) | YES | NULL | Cap for tiered rules |
| `bonus_amount` | decimal(12,2) | YES | NULL | Fixed bonus when target met |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |
| `deleted_at` | datetime | YES | NULL | Soft delete |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `cr_tenant_type` | `tenant_id`, `type` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `product_id` | `products.id` ON DELETE SET NULL |

---

#### `commissions`

Calculated commission amounts per salesman per period.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `salesman_id` | bigint unsigned | NO | — | FK → `salesmen.id` |
| `commission_rule_id` | bigint unsigned | YES | NULL | FK → `commission_rules.id` |
| `period_start` | date | NO | — | |
| `period_end` | date | NO | — | |
| `base_value` | decimal(12,2) | NO | — | Value used for calculation |
| `commission_value` | decimal(12,2) | NO | — | Calculated commission |
| `status` | varchar(50) | NO | 'calculated' | calculated, approved, paid |
| `approved_by` | bigint unsigned | YES | NULL | FK → `users.id` |
| `paid_at` | datetime | YES | NULL | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `comm_tenant_salesman_period` | `tenant_id`, `salesman_id`, `period_start` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `salesman_id` | `salesmen.id` ON DELETE CASCADE |
| `commission_rule_id` | `commission_rules.id` ON DELETE SET NULL |
| `approved_by` | `users.id` ON DELETE SET NULL |

---

### 3.15 Notifications

#### `notifications`

In-app notifications for users.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `uuid` | char(36) | NO | — | Client-generated UUID |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `type` | varchar(100) | NO | — | Notification class/type identifier |
| `title` | varchar(255) | NO | — | |
| `body` | text | YES | NULL | |
| `data` | json | YES | NULL | Additional payload |
| `read_at` | datetime | YES | NULL | NULL = unread |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `notif_tenant_user_read` | `tenant_id`, `user_id`, `read_at` | INDEX |
| `notif_type` | `type` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |

---

#### `notification_templates`

Reusable notification templates.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `type` | varchar(100) | NO | — | Matches notification.type |
| `title_template` | varchar(255) | NO | — | May contain : placeholders |
| `body_template` | text | NO | — | May contain : placeholders |
| `channel` | varchar(50) | NO | — | database, push, email |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `nt_type` | `type` | UNIQUE |

---

### 3.16 Sync & Offline

#### `sync_logs`

Audit trail for data synchronization between mobile devices and server.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `device_id` | bigint unsigned | NO | — | FK → `devices.id` |
| `user_id` | bigint unsigned | NO | — | FK → `users.id` |
| `direction` | varchar(10) | NO | — | push, pull |
| `entity_type` | varchar(100) | NO | — | e.g. orders, customer_visits |
| `entity_id` | bigint unsigned | YES | NULL | Server-assigned ID |
| `uuid` | char(36) | YES | NULL | Client-generated UUID |
| `status` | varchar(50) | NO | — | success, conflict, error |
| `conflict_details` | json | YES | NULL | Resolution info for conflicts |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `sl_tenant_device_created` | `tenant_id`, `device_id`, `created_at` | INDEX |
| `sl_entity_type_uuid` | `entity_type`, `uuid` | INDEX |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |
| `device_id` | `devices.id` ON DELETE CASCADE |
| `user_id` | `users.id` ON DELETE CASCADE |

**Notes:** Hard delete with 30-day retention (see §8).

---

### 3.17 Configuration

#### `company_settings`

Key-value store for per-tenant configuration.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `tenant_id` | bigint unsigned | NO | — | FK → `tenants.id` |
| `key` | varchar(100) | NO | — | e.g. gps_interval_seconds |
| `value` | json | NO | — | Flexible value storage |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | YES | NULL | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `cs_tenant_key_unique` | `tenant_id`, `key` | UNIQUE |

**Foreign Keys:**

| Column | References |
|--------|-----------|
| `tenant_id` | `tenants.id` ON DELETE CASCADE |

---

### 3.18 Currencies

#### `currencies`

Supported currencies for multi-currency operations.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | NO | auto_increment | Primary key |
| `code` | char(3) | NO | — | ISO 4217 (USD, UGX, KES) |
| `name` | varchar(100) | NO | — | |
| `symbol` | varchar(10) | NO | — | $, USh, KSh |
| `decimal_places` | tinyint unsigned | NO | 2 | |
| `is_active` | tinyint(1) | NO | 1 | |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `currencies_code_unique` | `code` | UNIQUE |

---

## 4. Entity Relationship Summary

```
tenants
├── branches
├── users
│   ├── model_has_roles → roles
│   ├── devices
│   └── audit_logs
├── salesmen
│   ├── salesman_assignments → territories, routes, supervisors
│   ├── customer_visits → visit_photos, visit_suspicious_flags
│   ├── orders → order_items → products
│   ├── collections
│   ├── expenses
│   └── commissions → commission_rules
├── supervisors
│   └── supervisor_assignments → branches, territories
├── customers
│   ├── customer_categories
│   ├── customer_location_history
│   └── route_customers → routes
├── territories
│   └── routes
│       └── route_customers → customers
├── products
│   ├── price_list_items → price_lists
│   └── commission_rules
├── targets (polymorphic: salesman, team, route, territory, branch)
├── notifications → notification_templates
├── sync_logs
├── company_settings
└── current_locations → users, salesmen, devices
    location_history → users, salesmen, devices, sync_batches
```

### Key Relationships

| Parent | Child | Relationship | Cascade |
|--------|-------|-------------|---------|
| Tenant | Branch | One-to-many | CASCADE |
| Tenant | User | One-to-many | SET NULL |
| Tenant | Salesman | One-to-many | CASCADE |
| Tenant | Customer | One-to-many | CASCADE |
| Tenant | Territory | One-to-many | CASCADE |
| Salesman | Customer (assigned) | One-to-many | SET NULL |
| Territory | Route | One-to-many | CASCADE |
| Route | Customer (junction) | Many-to-many via `route_customers` | CASCADE |
| Salesman | Visit | One-to-many | CASCADE |
| Customer | Visit | One-to-many | CASCADE |
| Visit | Photo | One-to-many | CASCADE |
| Visit | Order | One-to-many | SET NULL |
| Visit | Collection | One-to-many | SET NULL |
| Order | Order Item | One-to-many | CASCADE |
| Order Item | Product | Many-to-one | CASCADE |
| Salesman | Work Session | One-to-many | SET NULL |
| Work Session | Visit | One-to-many | SET NULL |
| User | Device | One-to-many | CASCADE |
| User | Current Location | One-to-one | CASCADE |
| Device | Location History | One-to-many | SET NULL |
| Salesman | Expense | One-to-many | CASCADE |
| Salesman | Commission | One-to-many | CASCADE |

---

## 5. Indexing Strategy

### Principles

1. **Tenant-scoped composite indexes**: Every query is tenant-scoped. Composite indexes always place `tenant_id` first to enable partition pruning and reduce index scan size.

2. **Date/time columns for range queries**: Tables queried by time periods (visits, orders, collections, expenses, GPS) include `check_in_time`, `order_date`, `collected_at`, `expense_date`, `recorded_at` in composite indexes.

3. **Foreign keys for joins**: All foreign key columns are indexed to prevent full table scans on joins.

4. **Unique constraints for business codes**: `employee_code`, `code`, `sku`, `slug`, `order_number` — all enforced unique per tenant.

5. **Unique constraints for offline UUIDs**: `offline_uuid` on orders and collections use conditional unique indexes (`WHERE offline_uuid IS NOT NULL`).

6. **Avoid over-indexing on high-write tables**: `location_history` and `audit_logs` have minimal indexes. Only the columns used by archival jobs and common queries are indexed.

### Index Count Summary

| Table | Indexes | Notes |
|-------|---------|-------|
| tenants | 3 | uuid, slug, domain (all unique) |
| branches | 2 | tenant_id, tenant+code (unique) |
| users | 3 | email (unique), tenant_id, role |
| salesmen | 2 | tenant+code (unique), user_id |
| customers | 5 | tenant+code (unique), salesman, territory, route, lat+lng |
| customer_visits | 5 | tenant+salesman+time, customer+time, route, session, verification |
| location_history | 3 | tenant+user+time, time (for archival), batch_id |
| orders | 4 | tenant+salesman+date, customer, status, offline_uuid |
| audit_logs | 2 | tenant+time, auditable |

---

## 6. Soft Delete Strategy

### Soft-Deleted Tables

These tables use `deleted_at` column and Laravel's `SoftDeletes` trait:

| Table | Rationale |
|-------|-----------|
| `tenants` | Preserve billing history and data recovery |
| `branches` | Reference from other tables; recoverable |
| `users` | Audit trail; referenced by many tables |
| `salesmen` | Historical assignment and order data |
| `supervisors` | Historical assignment data |
| `customers` | Visit history, orders, collections reference them |
| `territories` | Route and assignment history |
| `routes` | Customer visit history |
| `products` | Order items reference them |
| `orders` | Financial records; audit requirements |
| `expenses` | Financial records |
| `targets` | Historical comparison |
| `commission_rules` | Referenced by commission calculations |
| `commissions` | Financial records |
| `customer_visits` | Visit history; referenced by orders/collections |
| `collections` | Financial records |

### Hard-Delete Tables (with Retention)

These tables are permanently deleted after their retention period:

| Table | Retention | Rationale |
|-------|-----------|-----------|
| `location_history` | 90 days (configurable) | High volume; no business reference needed |
| `audit_logs` | 1 year (configurable) | Compliance; archive before delete |
| `sync_logs` | 30 days | Debugging only |
| `order_items` | Follows parent `orders` cascade | No standalone value |
| `visit_photos` | Follows parent `customer_visits` cascade | No standalone value |
| `visit_suspicious_flags` | Follows parent cascade | No standalone value |

### Cascade Behavior

| Operation | Behavior |
|-----------|----------|
| Soft delete parent | Children remain; queried with tenant scope |
| Hard delete parent | CASCADE children if they have no independent business value |
| Hard delete parent | SET NULL on FK if child has independent value |

---

## 7. Partitioning Strategy (Future)

### Tables to Partition

| Table | Partition Key | Strategy | Trigger |
|-------|--------------|----------|---------|
| `location_history` | `recorded_at` | RANGE by month | > 10M rows |
| `audit_logs` | `created_at` | RANGE by month | > 10M rows |
| `sync_logs` | `created_at` | RANGE by month | > 5M rows |

### Implementation Notes

- Use MySQL native partitioning (`PARTITION BY RANGE (TO_DAYS(...))`)
- Create partitions 3 months ahead, drop old partitions (faster than DELETE)
- Laravel does not natively support partitioned table management — use raw SQL or a custom Artisan command
- Test partition pruning with `EXPLAIN` to confirm queries use partition elimination
- Partition boundary: `PARTITION BY RANGE (TO_DAYS(recorded_at))` with monthly intervals

### Example DDL (location_history)

```sql
ALTER TABLE location_history
PARTITION BY RANGE (TO_DAYS(recorded_at)) (
    PARTITION p2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')),
    PARTITION p2026_08 VALUES LESS THAN (TO_DAYS('2026-09-01')),
    PARTITION p2026_09 VALUES LESS THAN (TO_DAYS('2026-10-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

---

## 8. Data Retention Policy

### Default Retention Periods

| Data Type | Active Period | Action After | Configurable |
|-----------|--------------|-------------|-------------|
| GPS history (`location_history`) | 90 days | Archive to cold storage, then drop partition | Yes, per tenant |
| Audit logs (`audit_logs`) | 1 year | Archive to cold storage, then drop partition | Yes, per tenant |
| Sync logs (`sync_logs`) | 30 days | Hard delete | Yes, per tenant |
| Visit photos (`visit_photos`) | Follows visit retention | Delete from storage | Yes |
| Notifications (`notifications`) | 90 days | Hard delete read notifications | Yes |

### Implementation

- **Archive before delete**: Export to S3/GCS or a separate analytics database before dropping
- **Configurable per tenant**: Store retention settings in `company_settings`:
  - `gps_retention_days` (default: 90)
  - `audit_retention_days` (default: 365)
  - `sync_log_retention_days` (default: 30)
- **Scheduled Artisan commands**:
  - `tenants:archive-gps` — Export and drop old `location_history` partitions
  - `tenants:archive-audit` — Export and drop old `audit_logs` partitions
  - `tenants:prune-sync-logs` — Delete old `sync_logs`
  - `tenants:prune-notifications` — Delete old read notifications
- **Run weekly** via Laravel Scheduler
- **Never hard delete** soft-deletable tables without explicit admin action

### Compliance Notes

- Retention periods should comply with local labor and tax regulations
- GPS data may have stricter privacy requirements in some jurisdictions
- Audit logs may need longer retention for financial compliance
- All retention changes should be logged in `audit_logs`
