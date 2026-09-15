# Field Sales SaaS Platform — Product Requirements Document

**Version:** 1.0  
**Status:** Draft  
**Date:** September 2026  

---

## Table of Contents

1. [Product Vision](#1-product-vision)
2. [Core Feature Domains](#2-core-feature-domains)
   - 2.1 [Multi-Company / Multi-Tenant](#21-multi-company--multi-tenant)
   - 2.2 [Identity & Access](#22-identity--access)
   - 2.3 [Sales Team Management](#23-sales-team-management)
   - 2.4 [Device Management](#24-device-management)
   - 2.5 [GPS Tracking](#25-gps-tracking)
   - 2.6 [Attendance & Work Sessions](#26-attendance--work-sessions)
   - 2.7 [Territories & Routes](#27-territories--routes)
   - 2.8 [Customers / Shops](#28-customers--shops)
   - 2.9 [Customer Visits](#29-customer-visits)
   - 2.10 [Sales Orders](#210-sales-orders)
   - 2.11 [Collections](#211-collections)
   - 2.12 [Targets](#212-targets)
   - 2.13 [Expenses](#213-expenses)
   - 2.14 [Commissions](#214-commissions)
   - 2.15 [Reporting](#215-reporting)
   - 2.16 [Notifications](#216-notifications)
   - 2.17 [Fraud / Anti-Fraud](#217-fraud--anti-fraud)
   - 2.18 [Admin Dashboard](#218-admin-dashboard)
3. [User Personas](#3-user-personas)
4. [Key Constraints](#4-key-constraints)
5. [Out of Scope for V1](#5-out-of-scope-for-v1)

---

## 1. Product Vision

### 1.1 What This Product Is

A modern, cloud-based Field Sales Management and Salesman GPS Tracking platform delivered as Software-as-a-Service (SaaS). The platform gives distribution companies complete visibility over their field sales operations — from real-time salesman locations to sales performance analytics.

### 1.2 Target Market

**Primary (V1):** Distribution companies in Afghanistan — FMCG, pharmaceutical, beverage, construction materials, and general merchandise distributors who employ roaming salesmen visiting retail shops across cities and provinces.

**Secondary (V2+):** International markets — starting with neighboring regions (Central Asia, South Asia, Middle East) and expanding globally. The architecture is international from day one.

### 1.3 Value Proposition

| Role | Core Value |
|------|-----------|
| **Company Owner** | Full visibility into field operations. Know if your sales team is working, where they went, what they sold, and what they collected — all in one dashboard. Reduce revenue leakage and increase accountability. |
| **Sales Manager** | Manage targets, track team performance in real-time, identify underperformers and top sellers, generate reports for leadership, and make data-driven decisions. |
| **Supervisor** | Oversee assigned salesmen, manage routes, verify visits, approve expenses, and ensure daily coverage of all planned territories. |
| **Salesman (Mobile)** | Clear daily work plan, efficient route navigation, easy order and collection entry (including offline), transparent target tracking, and fair commission calculation. |
| **Accountant** | Accurate collection records, expense tracking, receipt verification, and clean data for accounting integration. |
| **Platform Admin** | Manage the SaaS platform, onboard new companies, monitor system health, and ensure tenant isolation. |

### 1.4 Core Promise

> "Know where your sales team is, which customers they visited, what they sold, what they collected, what they missed, and how they performed."

### 1.5 Technology Context

| Component | Technology |
|-----------|-----------|
| Backend API | Laravel 13 / PHP 8.5 / MySQL 8 |
| Authentication | Laravel Sanctum (API tokens + SPA sessions) |
| API Style | RESTful JSON API |
| Mobile App | Flutter (Android-first). **Offline-first from the initial implementation** — local SQLite, local-first writes, client UUIDs, basic sync queue, connectivity state, and `pending`/`synced`/`failed` record states ship with the first Android release. |
| Admin Panel | Blade + Tailwind CSS + Alpine.js |
| Hosting | Laravel Cloud |

---

## 2. Core Feature Domains

### 2.1 Multi-Company / Multi-Tenant

The platform operates as a multi-tenant SaaS where each company is a fully isolated tenant.

- **Unlimited companies** can be onboarded to the platform, each with its own data, users, and configuration.
- **Branches per company** model the physical distribution branches (e.g., Kabul Main, Mazar-i-Sharif, Herat). Each branch has its own territory, team, and operational scope.
- **Tenant isolation** ensures that one company's data, users, orders, and customers are completely invisible and inaccessible to another company — enforced at the database level.
- **Company-level configuration** allows each tenant to customize business rules: GPS tracking frequency, geofence radius, working hours, target periods, commission structures, expense categories, and visit requirements.
- **Platform-level management** provides the Super Admin with tools to create, suspend, and manage company subscriptions and overall platform health.

### 2.2 Identity & Access

Role-based access control (RBAC) with granular permissions, scoped to company and branch.

**Company Roles:**

| Role | Scope | Description |
|------|-------|-------------|
| Owner | Company-wide | Full control. Company settings, billing, all data. |
| Company Admin | Company-wide | Operational admin. Manages users, config, reports. |
| Sales Manager | Company-wide or Branch | Manages sales team, targets, performance reviews. |
| Supervisor | Assigned Branch/Territory | Oversees assigned salesmen, verifies visits, manages routes. |
| Salesman | Own data only | Mobile user. Records orders, collections, visits. |
| Accountant | Financial data | Views collections, expenses, receipts, financial reports. |
| Warehouse User | Warehouse/Branch | Manages dispatch, stock visibility. |
| Auditor | Read-only all | Views all data without modification. Audit and compliance. |

**Platform Role:**

| Role | Scope | Description |
|------|-------|-------------|
| Super Admin | Platform-wide | Manages tenants, system config, platform health. |

- **Granular permissions** go beyond roles — individual permissions can be toggled per user where role defaults need adjustment.
- **Branch-scoped access** ensures that Supervisors and Sales Managers can be restricted to their assigned branches only, preventing cross-branch data leakage within the same company.

### 2.3 Sales Team Management

Managing the people who do the field work.

- **User profiles** with full personal details, role assignment, contact information, and status (active/inactive/suspended).
- **Salesman profiles** include employee codes, joining date, assigned vehicle/transport, salary information, and commission eligibility — separate from the general user profile for clarity.
- **Branch assignment** ties each team member to one or more branches, determining their operational scope and data visibility.
- **Team hierarchy** follows the chain: Company → Branch → Sales Manager → Supervisor → Salesman. This hierarchy drives reporting lines, approval workflows, route assignments, and dashboard visibility.
- **Bulk operations** for onboarding large sales teams — CSV import, batch role assignment, and template-based setup.

### 2.4 Device Management

Controlling which mobile devices can access the platform.

- **Mobile device registration** — each salesman must register their device before using the app. Registration captures device model, OS version, app version, and unique identifiers.
- **Device UUID tracking** — a unique hardware identifier is stored to prevent unauthorized device sharing or spoofing attempts.
- **Push token management** — FCM tokens are registered and maintained for reliable push notification delivery, with automatic cleanup of stale tokens.
- **One-device or multi-device policy** per company — companies can enforce single-device binding (most secure) or allow multiple devices per user (for shared tablets or device upgrades).
- **Device revocation** — administrators can remotely revoke a device, requiring re-registration and preventing unauthorized access immediately.
- **App version monitoring** — dashboard visibility into which app versions are deployed across the fleet, enabling managed rollout of updates.

### 2.5 GPS Tracking

The core location intelligence engine.

- **Background location during work hours** — the mobile app collects GPS coordinates while a salesman is in an active work session, even when the app is backgrounded.
- **Current location storage** — the last known location for each active salesman is stored and accessible for live map views.
- **Historical location logs** — every GPS ping is recorded with timestamp, coordinates, accuracy, speed, and battery level, forming a complete audit trail.
- **Route reconstruction** — historical pings are stitched together to reconstruct the path a salesman took during a work session, viewable on the map.
- **Configurable tracking frequency** — companies can set ping intervals (e.g., every 1, 5, 10, or 15 minutes) based on their needs and battery/data budget.
- **Battery and connectivity awareness** — the app adjusts tracking behavior based on battery level and network connectivity, batching and syncing when connection is restored.
- **Map visualization** — admin panel shows real-time and historical locations on an interactive map with filtering by date, branch, salesman, and territory.

### 2.6 Attendance & Work Sessions

Tracking when and where salesmen work.

- **Start/end day check-in with GPS** — salesmen must check in at the start of their day (with location capture) and check out at the end, creating a defined work session.
- **Work session tracking** — each check-in/check-out pair forms a work session with duration, distance traveled, and location summary.
- **Break tracking** — salesmen can log breaks (lunch, rest) within a work session, which are excluded from active work duration calculations.
- **Duration calculation** — automatic computation of total work time, active field time, break time, and overtime based on company-defined working hours.
- **Manual correction workflow** — salesmen can request attendance corrections (e.g., forgot to check in) which route to the supervisor for approval with an audit trail.
- **Schedule-aware tracking** — integration with work schedules so that check-ins outside expected hours are flagged and reporting accounts for shift patterns.

### 2.7 Territories & Routes

Defining the geographic structure of sales operations.

- **Territory hierarchy** follows: Branch → Territory → Route (also called "Beat"). This three-level structure mirrors how distribution companies organize their coverage areas.
- **Recurring weekday routes** — each route defines which weekdays it is active, with a planned sequence of customers to visit on that day.
- **Customer visit sequences** — routes define the order in which customers should be visited, enabling optimized path planning and consistent coverage.
- **Salesman and supervisor assignment** — routes are assigned to specific salesmen with a supervisor overseeing multiple routes. Assignment can change by day or period.
- **Route change management** — when a salesman deviates from the planned route or visits are skipped/reordered, the system tracks planned vs actual for analysis.
- **Planned vs actual analysis** — dashboards and reports compare the planned route execution against actual visits, highlighting coverage gaps, skipped stops, and extra unplanned visits.

### 2.8 Customers / Shops

The master data for all points of sale.

- **Customer master data** — name, shop name, owner name, phone numbers, address, contact person, and any custom fields the company needs.
- **Geographic location with geofence** — each customer has GPS coordinates and a configurable geofence radius (default 100m, adjustable 50m–500m) for visit verification.
- **Category classification** — customers are classified by type (retailer, wholesaler, kiosk, supermarket, hospital, pharmacy, etc.) and tier (A, B, C) based on sales volume or strategic importance.
- **Assigned salesman and territory** — each customer is assigned to a specific salesman within a territory, defining ownership and visit responsibility.
- **Credit and pricing** — customer-specific credit limits, payment terms, and pricing tier assignments for order processing.
- **Visit frequency configuration** — how often each customer should be visited (daily, every other day, weekly, etc.), driving route planning and visit compliance tracking.
- **Photo documentation** — salesmen can capture shop front photos during visits for documentation and geo-verification.

### 2.9 Customer Visits

Verifying that salesmen actually visit the customers they claim to.

- **Check-in/check-out workflow** — salesman taps "Check In" when arriving at a customer, which captures GPS location and timestamp, and "Check Out" when leaving, recording visit duration.
- **GPS distance verification** — the system calculates the distance between the salesman's GPS location and the customer's registered coordinates at the time of check-in.
- **Geofence validation** — configurable radius (50m–500m) determines whether a check-in is considered "at location." Check-ins outside the geofence are flagged.
- **Duration tracking** — visit duration is calculated from check-in to check-out and compared against expected visit times for anomaly detection.
- **Visit outcome recording** — salesmen record what happened: order placed, collection made, complaint received, no stock needed, shop closed, customer unavailable, etc.
- **Notes and photos** — free-text notes, photo attachments, and voice notes for richer visit documentation.
- **Planned vs unplanned tracking** — visits are classified as planned (on the day's route) or unplanned (extra visit), affecting coverage analysis and commission calculations.
- **Suspicious activity flags** — visits that raise red flags are tagged for review: too short duration, location mismatch, multiple simultaneous check-ins, repeated patterns. Flags are reviewable, not automatically punitive.

### 2.10 Sales Orders

Capturing what the salesman sells at each stop.

- **Order lifecycle:** Draft → Submitted → Approved → Processing → Dispatched → Delivered → Cancelled. Each status transition is timestamped and attributed.
- **Product/quantity/price capture** — salesmen select products from a catalog, enter quantities, and see company-standard pricing. Product catalog is synced to the device for offline access.
- **Discounts and promotions support** — line-level and order-level discounts with reason codes. Promotional pricing rules can be configured per company.
- **Cash and credit split** — orders can specify how much is paid in cash at time of order vs placed on credit, supporting the common partial-payment pattern.
- **Offline UUID generation** — orders created offline are assigned a client-generated UUID so they can be synced later without duplicates, even if multiple orders were created while disconnected.
- **ERP integration boundary** — the platform defines clean API boundaries for ERP integration (order push, status sync) but does not couple to any specific ERP system in V1.

### 2.11 Collections

Tracking money collected from customers.

- **Payment collection from customers** — salesmen record payments received against customer accounts, supporting both specific invoice payment and general account payment.
- **Multiple payment methods** — cash, bank transfer, mobile money (e.g., M-Paisa, Roshan Money), cheque, and other local payment methods. Each method has its own tracking and reconciliation needs.
- **Receipt tracking** — system-generated receipt numbers with optional manual receipt references for paper receipt cross-referencing.
- **GPS and photo proof** — collection transactions capture GPS location and optional receipt photo for verification and audit.
- **Offline capability** — collections recorded offline are stored locally with UUID and synced when connectivity is restored, ensuring no collection data is lost.
- **Accounting integration boundary** — clean API endpoints for pushing collection data to accounting systems (e.g., QuickBooks, SAP Business One, custom systems). No direct coupling in V1.

### 2.12 Targets

Setting and tracking performance goals.

- **Target dimensions:** sales value, collections amount, number of orders, number of visits, new customers acquired, and product-specific quantities.
- **Target periods:** daily, weekly, monthly, quarterly, and custom period definitions.
- **Target scope:** targets can be set per individual salesman, team (supervisor's group), route, territory, or branch — with company-wide targets as the aggregate.
- **Achievement tracking** — real-time dashboards showing target vs actual for each dimension, with percentage completion and trend indicators.
- **Weighted scoring** — companies can assign weights to different target dimensions (e.g., 40% sales value, 30% collections, 20% visits, 10% new customers) for composite performance scores.

### 2.13 Expenses

Managing field expense claims.

- **Expense categories:** fuel, food, parking, transport, accommodation, communication, and custom categories per company.
- **Receipt photo** — mandatory photo capture of physical receipt for expense claims above configurable thresholds.
- **GPS location** — location capture at time of expense entry for verification.
- **Approval workflow** — expenses route to the salesman's supervisor for review. Supervisor can approve or reject with a mandatory reason. Rejected expenses can be corrected and resubmitted.
- **Budget controls** — optional daily/weekly/monthly expense limits per salesman or category, with warnings when approaching limits and blocks when exceeded.

### 2.14 Commissions

Automated incentive calculation.

- **Percentage-based commissions** — configurable percentage of sales value or collections amount, adjustable per product, category, or customer type.
- **Fixed amount per product** — flat commission per unit sold, useful for promotions or new product launches.
- **Tiered target achievement** — commission rates increase as the salesman hits higher achievement tiers (e.g., 0–50% target: 1%, 50–80%: 2%, 80–100%: 3%, 100%+: 5%).
- **Bonus structures** — lump-sum bonuses for hitting specific milestones (e.g., 100% monthly target, new customer acquisition goals, zero-skipped-days streak).
- **Configurable engine** — the commission calculation engine is designed to be flexible enough to handle the diverse incentive structures used by different distribution companies, without requiring code changes.

### 2.15 Reporting

Turning operational data into business intelligence.

- **Report types:**
  - Salesman performance (individual and comparative)
  - Sales and collections summaries
  - Visit completion rates and coverage analysis
  - Route and territory analysis
  - Target achievement and trends
  - GPS coverage and tracking quality
  - Suspicious activity summaries
  - Expense summaries and breakdowns
  - Commission calculations
- **Filtering:** all reports support filtering by date range, branch, territory, route, salesman, customer category, and product category.
- **Export capability** — PDF for sharing and printing, CSV/Excel for data analysis. Scheduled report delivery (daily/weekly) via push notification or email (future).
- **Drill-down** — summary reports allow drilling down from aggregate numbers to individual records (e.g., from total sales to individual orders).

### 2.16 Notifications

Keeping all stakeholders informed.

- **Database notifications** — in-app notification center for all users, storing notification history with read/unread status and action links.
- **Push notifications (FCM)** — real-time push to Android devices for urgent alerts: new order approvals, expense rejections, target alerts, attendance reminders, route assignments.
- **Email notifications** — (Future scope) daily summary emails, report delivery, and critical alerts via email.
- **WhatsApp/SMS integration** — (Future scope) critical notifications via WhatsApp Business API or SMS gateway for markets where these are primary communication channels.

**Notification categories:**

| Category | Examples |
|----------|---------|
| Operational | New route assignment, schedule change, target updated |
| Financial | Order approved/rejected, collection recorded, commission calculated |
| Compliance | Suspicious visit flag, attendance anomaly, tracking gap |
| System | Device registration pending, app update available |

### 2.17 Fraud / Anti-Fraud

Detecting and flagging suspicious activity for human review.

- **Mock GPS detection** — detection of developer mode, mock location apps, and GPS spoofing indicators on the device.
- **Impossible travel speed** — flag when a salesman's GPS shows movement between two points at a speed physically impossible for their mode of transport.
- **Visit location anomalies** — flag check-ins where the GPS coordinates don't match the customer's registered location beyond acceptable tolerance.
- **Route deviation** — flag when a salesman's actual path deviates significantly from the planned route without explanation.
- **Tracking disabled indicators** — detect and flag periods when GPS tracking was disabled, app was force-closed, or location permissions were revoked.
- **All flags are reviewable, not automatic guilt** — flagged activities are presented to supervisors and managers as "requires review" items with context, evidence, and the ability to mark as explained or confirmed suspicious. No automated punishment.

### 2.18 Admin Dashboard

The command center for company administrators.

- **KPI widgets** — real-time tiles showing today's key metrics: active salesmen, orders today, collections today, visits completed, target achievement %, pending approvals.
- **Charts and analytics** — trend lines, bar charts, pie charts, and heat maps for visual analysis of sales, visits, coverage, and performance patterns.
- **Live map** — interactive map showing all active salesmen's current locations with click-to-details, breadcrumb trails, and territory overlays.
- **Role-based dashboard views** — each role sees a tailored dashboard. Owners see company-wide KPIs. Supervisors see their team. Salesmen see their personal targets and progress.
- **Dark/light mode** — theme toggle for user preference and accessibility.
- **Responsive design** — fully functional on desktop, tablet, and mobile browsers, enabling managers to check dashboards from any device.

---

## 3. User Personas

### 3.1 Company Owner — "Ahmad"

- **Role:** Owns a distribution company with 3 branches and 45 salesmen.
- **Needs:** Revenue visibility, team accountability, fraud prevention, business growth insights.
- **Pain points:** Doesn't know if salesmen are actually visiting shops, loses money to fake expense claims, can't compare branch performance objectively.
- **Success metric:** Revenue per salesman increases, expense fraud drops, can make expansion decisions based on territory data.

### 3.2 Sales Manager — "Fatima"

- **Role:** Manages all sales operations across branches.
- **Needs:** Team performance comparison, target tracking, report generation for owner, data-driven coaching.
- **Pain points:** Spends hours compiling Excel reports, can't identify underperformers until monthly review, doesn't know which territories are under-served.
- **Success metric:** Weekly performance reviews take 15 minutes instead of 2 hours, can identify and address issues in real-time.

### 3.3 Supervisor — "Omar"

- **Role:** Oversees 8 salesmen across 2 routes.
- **Needs:** Daily coverage verification, visit quality assurance, expense approval, salesman support.
- **Pain points:** Can't verify if salesmen actually visited all shops, doesn't know when salesmen skip stops, expense approval is paper-based.
- **Success metric:** 95%+ route coverage, zero unapproved expenses, can resolve issues before they become patterns.

### 3.4 Salesman — "Karim"

- **Role:** Visits 25–35 shops daily across assigned route.
- **Needs:** Clear daily plan, easy order entry (even offline), fair target tracking, commission transparency.
- **Pain points:** Paper-based orders get lost, doesn't know target progress until month-end, feels micromanaged without proof of work.
- **Success metric:** Knows daily target progress, orders are never lost, commission is calculated fairly and transparently.

### 3.5 Accountant — "Nadia"

- **Role:** Manages collections, expenses, and financial reconciliation.
- **Needs:** Accurate collection records, receipt verification, expense audit trail, clean data for accounting software.
- **Pain points:** Paper receipts don't match records, collection data is incomplete, expense claims lack documentation.
- **Success metric:** 99% collection data accuracy, zero undocumented expenses, seamless monthly close.

### 3.6 Platform Admin — "System"

- **Role:** Manages the SaaS platform infrastructure.
- **Needs:** Tenant health monitoring, onboarding efficiency, system performance, security.
- **Pain points:** N/A (system persona — represents automated and administrative platform operations).

---

## 4. Key Constraints

| Constraint | Impact | Mitigation |
|-----------|--------|-----------|
| **Offline-first mobile** | Salesmen operate in areas with unreliable or no internet. Data must be captured offline and synced seamlessly. Offline capability is core to the **initial** Android app, not a later phase. | Initial release: local SQLite storage on device, local-first writes with client-generated UUIDs, basic sync queue, connectivity state, and per-record `pending`/`synced`/`failed` states. Advanced sync (conflict resolution, retry/backoff, tombstones, bulk sync, recovery) is delivered by the later Sync Engine batch. |
| **Afghanistan initial market** | Connectivity challenges, varying GPS reliability in urban and rural areas, limited smartphone specs, diverse languages. | Lightweight app design, configurable GPS accuracy thresholds, battery-efficient tracking, English primary with Dari/Pashto UI planned. |
| **Flutter Android app** | Mobile app is developed and deployed separately from the backend API. | Clean, well-documented REST API contract. API-first design with versioning. |
| **No inventory coupling in V1** | The platform focuses on field operations, not warehouse or inventory management. | Orders capture product/quantity/price but do not validate against real-time inventory. Inventory integration is a future concern. |
| **BusinessOS integration is future** | Integration with existing business systems (ERP, accounting) is planned but not V1 scope. | API boundaries are designed with integration in mind, but actual connectors are deferred. |
| **Multi-language** | English is the primary language for V1. Dari and Pashto are required for the Afghan market. | All user-facing strings are externalized. Translation infrastructure is in place but full i18n is V1.x scope. |
| **Security & data sensitivity** | GPS tracking, sales data, and financial records are sensitive. Data must be protected at rest and in transit. | HTTPS everywhere, encrypted storage, Sanctum token auth, tenant isolation, audit logging. |

---

## 5. Out of Scope for V1

The following features are explicitly excluded from the initial release to maintain focus and accelerate time-to-market:

| Item | Reason | Planned For |
|------|--------|-------------|
| **iOS app** | Market is Android-dominant in target region. Flutter architecture allows future iOS build. | V2 |
| **Inventory management** | Out of core promise. Complex domain requiring its own lifecycle. | V2+ |
| **Invoicing** | ERP responsibility. Platform captures orders, not formal invoices. | V2+ |
| **Warehouse management** | Separate domain. Platform may surface dispatch status but won't manage warehouse ops. | V2+ |
| **Full multi-language (i18n)** | English primary for V1. Dari/Pashto UI translation is planned but not blocking. | V1.x |
| **WhatsApp/SMS integration** | Valuable for notifications but requires third-party partnerships and setup complexity. | V2 |
| **Advanced analytics / BI** | V1 covers standard reports. Advanced BI, predictive analytics, and custom dashboards are future enhancements. | V2+ |
| **Offline-first conflict resolution UI** | Offline-first capture (local writes + sync) is included in the initial Android app. Only the sophisticated conflict-resolution UX for simultaneous edits is deferred. | V1.x |
| **Custom report builder** | Standard report suite covers V1 needs. Drag-and-drop custom report builder is a significant feature. | V2+ |
| **Multi-currency** | Single currency (Afghan Afghani) in V1. Multi-currency support for international markets. | V2 |

---

*This document is a living artifact. It will be updated as product decisions are made, scope is refined, and user feedback is incorporated.*
