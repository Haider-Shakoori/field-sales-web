# Mobile master-data contract — Batch 5

All mobile master-data routes are under `/api/v1`, require Sanctum authentication plus the registered-device headers enforced by `device.required`, and return the standard envelope:

```json
{
  "success": true,
  "data": [],
  "meta": {}
}
```

## Required device headers

- `X-Device-UUID`
- `X-Installation-UUID`
- `X-App-Version`
- `X-Platform: android`
- `X-OS-Version`

## Master data

- `GET /customers`
- `POST /customers` for offline-created customers
- `GET /territories`
- `GET /routes`
- `GET /routes/{route}/customers`
- `GET /products`
- `GET /price-lists`
- `GET /price-lists/{priceList}/items`

List endpoints accept `page`, `per_page` (1–100), and `updated_since` as an ISO/date-time value. Results include inactive rows so a local cache can reconcile server-side deactivation. Pagination metadata includes `current_page`, `last_page`, `per_page`, `total`, `has_more`, and `next_page`.

Public identifiers in mobile payloads are UUIDs, not database integer IDs.

## Offline-created customer idempotency

The mobile app sends a stable `offline_uuid`. On first successful submission the server creates the customer with that UUID as both its public `uuid` and `offline_uuid`. Repeating the same submission for the same tenant returns the existing customer rather than inserting another row.

The server derives branch and territory from the salesman's current historical assignment where possible. Client input never chooses a branch or territory. A client may send an optional price-list UUID; it is validated inside the active tenant.

## Route-customer rows

`GET /routes/{route}/customers` returns route membership rows with:

- membership `id`
- `route_id`
- `customer_id`
- `sequence_number`
- `planned_visit_minutes`
- `notes`
- `updated_at`

The mobile client should resolve `customer_id` against the locally cached customer table.

## Pricing

Price lists are effective-dated. Each price-list item is a quantity tier. For an assigned active/effective price list, the applicable tier is the highest `min_quantity` not exceeding the order quantity. If no applicable tier exists, the product base price is used. This same rule is implemented server-side by `CatalogPriceResolver` and is intended to be mirrored by the offline client in Batch 6.
