# Field Sales SaaS — Planning Documents

> **Batch 0: Architecture & Product Planning**
> Created: September 2026
> Status: Planning Complete — Ready for Batch 1 Implementation

---

## Project Overview

A modern multi-tenant Field Sales / Salesman GPS Tracking SaaS platform, initially targeted at distributors in Afghanistan, architected for international expansion.

**Core promise:** Know where your sales team is, which customers they visited, what they sold, what they collected, what they missed, and how they performed.

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.5, MySQL 8 |
| Auth | Laravel Sanctum |
| Admin Panel | Blade + Tailwind CSS 4 + Alpine.js |
| Mobile | Flutter (Android, separate project) |
| Cache/Queue | Redis |
| Build | Vite |
| Testing | Pest |

---

## Document Index

| # | Document | Description |
|---|----------|-------------|
| 1 | [PRODUCT_REQUIREMENTS.md](./PRODUCT_REQUIREMENTS.md) | Complete product requirements, feature domains, user personas, and scope |
| 2 | [ARCHITECTURE.md](./ARCHITECTURE.md) | System architecture, multi-tenancy, modular monolith, code structure, ADRs |
| 3 | [DATABASE_DESIGN.md](./DATABASE_DESIGN.md) | Full database schema — 40+ tables with columns, indexes, and relationships |
| 4 | [API_CONTRACT.md](./API_CONTRACT.md) | REST API specification — endpoints, request/response formats, sync protocol |
| 5 | [OFFLINE_SYNC_DESIGN.md](./OFFLINE_SYNC_DESIGN.md) | Offline-first sync engine — SQLite schema, conflict resolution, retry strategy |
| 6 | [GPS_TRACKING_DESIGN.md](./GPS_TRACKING_DESIGN.md) | GPS architecture — storage, collection flow, live map, geofencing, privacy |
| 7 | [UI_UX_ADMIN_PLAN.md](./UI_UX_ADMIN_PLAN.md) | Admin panel design system, all 21 page layouts, Blade components |
| 8 | [SECURITY_PRIVACY.md](./SECURITY_PRIVACY.md) | Security architecture, RBAC, GPS privacy, audit logging, compliance |
| 9 | [BUSINESSOS_INTEGRATION.md](./BUSINESSOS_INTEGRATION.md) | ERP integration architecture — adapter pattern, sync domains, conflict resolution |
| 10 | [DEVELOPMENT_ROADMAP.md](./DEVELOPMENT_ROADMAP.md) | 15 implementation batches with dependencies, verification, and completion criteria |

---

## Key Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Tenancy | Shared database + `tenant_id` | Simple, cost-effective, scalable for V1 |
| Architecture | Modular monolith | Clean domain boundaries without microservice overhead |
| Admin UI | Blade + Tailwind + Alpine | No React/Vue complexity, server-rendered, fast |
| Mobile | Flutter Android first | Single codebase, offline-first, cross-platform ready |
| API | REST + Sanctum | Industry standard, well-supported, mobile-friendly |
| Timestamps | UTC storage | International-ready, consistent |
| Offline | UUID + idempotent sync | Reliable offline-first with conflict prevention |
| GPS Storage | Two-table + Redis cache | Efficient writes (history) + fast reads (current) |
| Map Provider | Interface abstraction | Avoid vendor lock-in, swap providers easily |
| Files | Local → S3 migration path | Start simple, scale when needed |
| BusinessOS | Adapter pattern, future scope | Standalone now, pluggable integration later |

---

## Implementation Batches Overview

```
Batch  1: Foundation & Tenancy          ████░░░░░░░░░░░░░░░░ Core
Batch  2: Roles, Permissions & Users    ████░░░░░░░░░░░░░░░░ Core
Batch  3: Sales Team & Devices          ████░░░░░░░░░░░░░░░░ Core
Batch  4: Customers & Territories       ████░░░░░░░░░░░░░░░░ Core
Batch  5: Products & Price Lists        ████░░░░░░░░░░░░░░░░ Core
Batch  6: Attendance & Work Sessions    ████░░░░░░░░░░░░░░░░ Core
Batch  7: GPS Tracking Core             ████░░░░░░░░░░░░░░░░ Critical
Batch  8: Customer Visits               ████░░░░░░░░░░░░░░░░ Critical
Batch  9: Orders                        ████░░░░░░░░░░░░░░░░ Core
Batch 10: Collections                   ████░░░░░░░░░░░░░░░░ Core
Batch 11: Expenses & Targets            ████░░░░░░░░░░░░░░░░ Core
Batch 12: Sync Engine & Offline         ████░░░░░░░░░░░░░░░░ Critical
Batch 13: Admin Dashboard & Live Map    ████░░░░░░░░░░░░░░░░ UI
Batch 14: Notifications & Reporting     ████░░░░░░░░░░░░░░░░ Features
Batch 15: BusinessOS Integration        ░░░░░░░░░░░░░░░░░░░░ Optional
```

---

## Major Risks

1. **GPS Volume** — Millions of location points require careful indexing, partitioning, and archival planning
2. **Offline Sync Reliability** — Conflict resolution edge cases need thorough testing with real-world connectivity patterns
3. **Flutter Timeline** — Mobile app developed externally; API contract must be stable before mobile development begins
4. **Afghanistan Connectivity** — Intermittent internet requires robust offline-first design and retry mechanisms
5. **Multi-Tenant Isolation** — Single data leak across tenants is catastrophic; requires rigorous policy enforcement

---

## Unresolved Decisions

| Topic | Status | Notes |
|-------|--------|-------|
| Livewire usage | Deferred | Pure Blade + Alpine sufficient for V1; revisit if AJAX complexity grows |
| Chart library | TBD | Chart.js leading candidate; evaluate during Batch 13 |
| Map tiles | TBD | OpenStreetMap/Leaflet for V1; Mapbox consideration for better Afghanistan coverage |
| Redis driver | TBD | PhpRedis (native) recommended for performance |
| Push notifications | TBD | Laravel Notification Channels + FCM; evaluate package options during Batch 14 |

---

## How to Use These Documents

1. **Start with** `PRODUCT_REQUIREMENTS.md` to understand what we're building
2. **Read** `ARCHITECTURE.md` for the technical structure and key decisions
3. **Reference** `DATABASE_DESIGN.md` when building models and migrations
4. **Follow** `API_CONTRACT.md` when building API endpoints
5. **Consult** `OFFLINE_SYNC_DESIGN.md` and `GPS_TRACKING_DESIGN.md` for critical subsystems
6. **Use** `UI_UX_ADMIN_PLAN.md` as the reference for all admin panel work
7. **Check** `SECURITY_PRIVACY.md` for security requirements in every batch
8. **Plan** future work with `DEVELOPMENT_ROADMAP.md`
9. **Defer** `BUSINESSOS_INTEGRATION.md` until Batch 15

---

*This is a Batch 0 planning output. No application code, migrations, or tests have been modified.*
