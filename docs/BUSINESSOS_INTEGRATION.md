# BusinessOS Integration — Field Sales SaaS

> **Status:** Planning Document — Not Implemented  
> **Version:** 1.0  
> **Last Updated:** September 15, 2026

---

## Table of Contents

1. [Integration Philosophy](#1-integration-philosophy)
2. [Integration Architecture](#2-integration-architecture)
3. [Data Domains](#3-data-domains)
4. [Sync Mechanism](#4-sync-mechanism)
5. [Conflict Resolution](#5-conflict-resolution)
6. [API/Protocol for BusinessOS](#6-apiprotocol-for-businessos)
7. [Data Mapping](#7-data-mapping)
8. [Error Handling](#8-error-handling)
9. [Admin UI for Integration](#9-admin-ui-for-integration)
10. [Future Integration Support](#10-future-integration-support)
11. [Security Considerations](#11-security-considerations)
12. [V1 Scope (Not Implemented)](#12-v1-scope-not-implemented)

---

## 1. Integration Philosophy

### Standalone First

- Field Sales operates independently without any ERP.
- All data can be created and managed within Field Sales.
- BusinessOS integration is an optional enhancement.
- No hard dependencies on external systems.

### Pluggable Architecture

- Integration layer uses the **adapter pattern**.
- Each external system gets its own adapter.
- Common interface for all adapters.
- Easy to add new integrations.
- Easy to disable or remove integrations.

---

## 2. Integration Architecture

### High-Level Design

```
Field Sales SaaS
    │
    ├── Integration Service (internal)
    │   ├── Adapter Interface
    │   ├── BusinessOS Adapter (V2)
    │   ├── Generic ERP Adapter (future)
    │   └── Custom Adapter (future)
    │
    └── Event System
        ├── OrderCreated → Sync to ERP
        ├── CustomerCreated → Sync to ERP
        ├── CollectionRecorded → Sync to ERP
        ├── ProductUpdated ← Sync from ERP
        └── PriceListUpdated ← Sync from ERP
```

### Adapter Interface

```php
interface IntegrationAdapter
{
    public function isConnected(): bool;
    public function testConnection(): bool;

    // Inbound (ERP → Field Sales)
    public function pullCustomers(): Collection;
    public function pullProducts(): Collection;
    public function pullPriceLists(): Collection;
    public function pullCustomerBalances(): Collection;

    // Outbound (Field Sales → ERP)
    public function pushOrder(Order $order): IntegrationResult;
    public function pushCollection(Collection $collection): IntegrationResult;
    public function pushCustomer(Customer $customer): IntegrationResult;

    // Sync status
    public function getSyncStatus(): array;
    public function getLastSyncAt(): ?Carbon;
}
```

### Integration Service

```php
class IntegrationService
{
    protected ?IntegrationAdapter $adapter = null;

    public function setAdapter(IntegrationAdapter $adapter): void;
    public function getAdapter(): ?IntegrationAdapter;
    public function syncInbound(string $entityType): SyncResult;
    public function syncOutbound(string $entityType, Model $model): IntegrationResult;
}
```

---

## 3. Data Domains

### 3.1 BusinessOS → Field Sales (Inbound)

**Products**

- BusinessOS is source of truth for products.
- Field Sales receives: `id`, `name`, `sku`, `unit`, `category`, `base_price`.
- Sync frequency: daily or on-demand.
- Conflict: Field Sales cannot modify product data.

**Units of Measurement**

- BusinessOS defines units (`pcs`, `kg`, `box`, etc.).
- Field Sales uses these for order quantities.
- Synced with product data.

**Price Lists**

- BusinessOS manages pricing tiers.
- Field Sales receives: `price_list_id`, `product_id`, `price`.
- Sync frequency: daily or on-demand.
- Multiple price lists per company supported.

**Customer Balances**

- BusinessOS tracks financial balances.
- Field Sales receives: `customer_id`, `outstanding_balance`, `credit_limit`.
- Sync frequency: daily.
- Read-only in Field Sales.

**Inventory Availability (Optional)**

- BusinessOS provides stock levels.
- Field Sales displays availability (informational).
- Not used for order validation in V1.
- Future: real-time stock check.

**Customers (Initial Load)**

- BusinessOS provides initial customer master data.
- Field Sales may add new customers in the field.
- New customers synced back to BusinessOS.

### 3.2 Field Sales → BusinessOS (Outbound)

**Orders**

- Field Sales creates orders in the field.
- Synced to BusinessOS for fulfillment.
- Order data: customer, products, quantities, prices, totals.
- Status updates flow: BusinessOS → Field Sales.

**Collections**

- Payment collections recorded in the field.
- Synced to BusinessOS for accounting.
- Data: customer, amount, payment_method, date.

**New Customers**

- New customers created by salesmen.
- Synced to BusinessOS for master data.
- BusinessOS may assign its own customer code.
- Code syncs back to Field Sales.

**Returns (Future)**

- Product returns captured in the field.
- Synced to BusinessOS for inventory/credit.

### 3.3 Bidirectional Sync

**Customer Updates**

- Office updates customer in BusinessOS → changes sync to Field Sales.
- Salesman updates customer in the field → changes sync to BusinessOS.
- Conflict resolution: timestamp-based (most recent wins).
- Audit trail preserved on both sides.

**Order Status Updates**

- Field Sales: `draft` → `submitted`.
- BusinessOS: `approved` → `processing` → `dispatched` → `delivered`.
- Status syncs from BusinessOS to Field Sales.
- Salesman sees current status on mobile.

---

## 4. Sync Mechanism

### 4.1 Full Sync (Initial Setup)

- Pull all master data from BusinessOS.
- Products, units, price lists, customers, balances.
- May take time for large datasets.
- Background job with progress tracking.

### 4.2 Incremental Sync (Ongoing)

- Pull changes since last sync timestamp.
- Efficient for daily operations.
- Uses `updated_at` comparison.
- Handles creates, updates, soft deletes.

### 4.3 Event-Driven Sync (Near Real-Time)

- Field Sales events trigger outbound sync.
- `OrderCreated` → queue push to BusinessOS.
- `CollectionRecorded` → queue push to BusinessOS.
- `CustomerCreated` → queue push to BusinessOS.
- Queued jobs handle retries.

### 4.4 Manual Sync

- Admin triggers sync from Settings page.
- On-demand pull/push for specific entities.
- Useful for troubleshooting.

### 4.5 Sync Schedule

```php
// Laravel Scheduler
$schedule->job(new SyncInboundProducts())->dailyAt('02:00');
$schedule->job(new SyncInboundCustomers())->dailyAt('02:30');
$schedule->job(new SyncInboundPriceLists())->dailyAt('03:00');
$schedule->job(new SyncInboundBalances())->dailyAt('03:30');
```

---

## 5. Conflict Resolution

### General Rules

1. BusinessOS is authoritative for: products, units, price lists, balances.
2. Field Sales is authoritative for: orders (until submitted), collections, new customers.
3. Customer data: most recent update wins (timestamp comparison).
4. Order status: BusinessOS is authoritative after submission.

### Conflict Scenarios

**Customer Updated Both Sides**

- Compare `updated_at` timestamps.
- Most recent update wins.
- Loser's changes logged in audit.
- Admin notified of conflict resolution.

**Order Status Conflict**

- BusinessOS status always wins.
- Field Sales status changes only until "submitted".
- After submission, only BusinessOS can change status.

**Product Price Discrepancy**

- BusinessOS price lists are source of truth.
- Field Sales uses synced price lists.
- No price modification in Field Sales (V1).

**Duplicate Customer**

- Match by phone number or business name.
- BusinessOS customer code preferred.
- Field Sales UUID stored as reference.

### Conflict Resolution Table

| Entity         | Authoritative Source    | Conflict Strategy                 |
| -------------- | ----------------------- | --------------------------------- |
| Products       | BusinessOS              | BusinessOS wins                   |
| Prices         | BusinessOS              | BusinessOS wins                   |
| Customers      | Both                    | Timestamp wins                    |
| Orders         | Both (phased)           | Submitted → BusinessOS            |
| Collections    | Field Sales             | Field Sales wins                  |
| Balances       | BusinessOS              | BusinessOS wins                   |
| New Customers  | Field Sales             | Push to BusinessOS                |

---

## 6. API/Protocol for BusinessOS

### Assumptions

- BusinessOS exposes a REST API.
- Authentication via API key or OAuth2.
- JSON request/response.
- Standard CRUD endpoints.

### Adapter Implementation

```php
class BusinessOSAdapter implements IntegrationAdapter
{
    protected string $baseUrl;
    protected string $apiKey;
    protected HttpClient $http;

    // Implements all interface methods
    // Maps Field Sales entities to BusinessOS API format
    // Handles errors, retries, logging
}
```

### Configuration

```php
// config/integrations.php
return [
    'businessos' => [
        'enabled' => false,
        'base_url' => env('BUSINESSOS_API_URL'),
        'api_key' => env('BUSINESSOS_API_KEY'),
        'sync_interval' => 'daily',
        'timeout' => 30,
    ],
];
```

### Company-Level Enable

- Integration enabled per company (not globally).
- Admin configures in Settings → Integrations.
- Can be disabled without disabling Field Sales.
- Each company has its own BusinessOS credentials.

---

## 7. Data Mapping

### Entity Mapping

| Field Sales          | BusinessOS             |
| -------------------- | ---------------------- |
| Customer             | Customer               |
| Product              | Product/Item           |
| Unit                 | UOM                    |
| PriceList            | PriceLevel             |
| Order                | SalesOrder             |
| OrderItem            | SalesOrderLine         |
| Collection           | Payment/Receipt        |
| Expense              | Expense (if supported) |

### Field Mapping Example: Customer

| Field Sales Field    | BusinessOS Field       |
| -------------------- | ---------------------- |
| `uuid`               | `external_id`          |
| `code`               | `customer_code`        |
| `business_name`      | `name`                 |
| `contact_person`     | `contact_name`         |
| `phone`              | `phone`                |
| `address`            | `address_line_1`       |
| `city`               | `city`                 |
| `province`           | `state`                |
| `latitude`           | `latitude`             |
| `longitude`          | `longitude`            |
| `credit_limit`       | `credit_limit`         |

### Field Mapping Configuration

- Mappings stored in config or database.
- Configurable per company (future).
- Default mappings provided.
- Override capability for custom BusinessOS setups.

---

## 8. Error Handling

### Sync Failures

- Log error details.
- Retry with exponential backoff.
- Alert admin after N failures.
- Don't block Field Sales operations.

### Partial Failures

- Sync one entity at a time.
- Failed entity doesn't block others.
- Track per-entity sync status.
- Retry failed entities independently.

### Error Notification

- Dashboard alert for sync failures.
- Email notification for critical failures (configurable).
- Log entry with full error details.

### Recovery

- Manual retry from admin panel.
- Bulk retry for batch failures.
- Reset sync state for specific entities.
- Force full re-sync option.

---

## 9. Admin UI for Integration

### Settings Page

```
Integrations
├── BusinessOS
│   ├── Status: Connected / Disconnected
│   ├── Last Sync: 2025-01-15 03:00
│   ├── Configure Connection
│   │   ├── API URL
│   │   ├── API Key
│   │   └── Test Connection button
│   ├── Sync Settings
│   │   ├── Enable/Disable
│   │   ├── Sync Interval
│   │   └── Entity Selection
│   ├── Sync History
│   │   └── Table of sync runs with status
│   └── Manual Sync
│       └── Button per entity type
```

### Sync Status Dashboard

- Last sync time per entity.
- Sync health indicator (green/yellow/red).
- Error count.
- Pending items count.

---

## 10. Future Integration Support

### Generic ERP Adapter Interface

```php
interface ERPAdapter extends IntegrationAdapter
{
    public function configure(array $config): void;
    public function testConnection(): bool;
    public function getSupportedEntities(): array;
}
```

### Potential Future Integrations

- SAP Business One
- Oracle NetSuite
- QuickBooks
- Odoo
- Custom in-house ERPs

### Integration Marketplace (Future)

- Community-contributed adapters.
- Adapter marketplace/store.
- Standard certification process.

---

## 11. Security Considerations

### Credential Storage

- API keys encrypted in database (Laravel encryption).
- Never exposed in API responses.
- Never logged.
- Rotation capability.

### API Communication

- HTTPS only for all integration calls.
- Request signing where supported.
- Timeout handling for slow responses.
- Rate limiting respect for partner APIs.

### Data Access

- Integration only accesses configured entities.
- No access to internal Field Sales data beyond agreed scope.
- Audit log for all integration activities.

---

## 12. V1 Scope (Not Implemented)

### Explicitly Out of Scope for V1

- BusinessOS adapter implementation.
- Any integration code.
- Integration UI.
- Sync jobs.
- Integration configuration.

### What V1 Prepares

- **Clean entity boundaries** — easy to map later.
- **Event system** — ready for sync triggers.
- **UUID strategy** — ready for cross-system references.
- **Config structure** — ready for integration settings.
- **No hard dependencies** on BusinessOS.

### V2 Integration Batch (Future)

After core Field Sales features are stable:

1. Implement adapter interface.
2. Build BusinessOS adapter.
3. Create sync jobs.
4. Build integration settings UI.
5. Add sync monitoring.
6. Test with BusinessOS sandbox.
