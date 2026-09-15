# UI/UX Admin Panel Plan

Field Sales SaaS Platform — Admin Panel Design & Development Reference

**Status:** Planning Only — No Code
**Stack:** Blade + Tailwind CSS 4 + Alpine.js | Vite | Desktop-first, Responsive | Light + Dark Mode

---

## 1. Design System

### 1.1 Typography

- **Font:** Inter (primary), system font stack fallback
- **Scale:**

| Token | Size / Line Height |
|---|---|
| xs | 0.75rem / 1rem |
| sm | 0.875rem / 1.25rem |
| base | 1rem / 1.5rem |
| lg | 1.125rem / 1.75rem |
| xl | 1.25rem / 1.75rem |
| 2xl | 1.5rem / 2rem |
| 3xl | 1.875rem / 2.25rem |

- **Font weights:** 400 (regular), 500 (medium), 600 (semibold), 700 (bold)
- **Headings:** semibold or bold
- **Body:** regular

### 1.2 Spacing

- Use Tailwind default spacing scale
- **Consistent padding:**
  - `p-4` — cards
  - `p-6` — page content
  - `p-8` — page headers
- **Gap:**
  - `gap-4` — between items
  - `gap-6` — between sections
- **Section spacing:** `space-y-6`

### 1.3 Color System (Semantic Tokens)

**Light Mode:**

```
--bg-primary: white
--bg-secondary: gray-50
--bg-tertiary: gray-100
--text-primary: gray-900
--text-secondary: gray-600
--text-muted: gray-400
--border: gray-200
--border-focus: blue-500
```

**Dark Mode:**

```
--bg-primary: gray-900
--bg-secondary: gray-800
--bg-tertiary: gray-700
--text-primary: gray-50
--text-secondary: gray-300
--text-muted: gray-500
--border: gray-700
--border-focus: blue-400
```

**Status Colors:**

| Status | Strong | Soft (bg) |
|---|---|---|
| Success | green-500 | green-50 |
| Warning | amber-500 | amber-50 |
| Danger | red-500 | red-50 |
| Info | blue-500 | blue-50 |
| Neutral | gray-500 | gray-50 |

### 1.4 Border Radius

| Element | Radius |
|---|---|
| Cards | `rounded-lg` (0.5rem) |
| Buttons | `rounded-lg` (0.5rem) |
| Inputs | `rounded-lg` (0.5rem) |
| Badges | `rounded-full` |
| Avatars | `rounded-full` |
| Modals | `rounded-xl` (0.75rem) |

### 1.5 Shadows

| Element | Shadow |
|---|---|
| Cards | `shadow-sm` (subtle) |
| Dropdowns | `shadow-lg` |
| Modals | `shadow-xl` |
| Hover states | `shadow-md` |

### 1.6 Elevation / Depth Levels

1. **Background** — app canvas (`bg`)
2. **Surface** — cards, panels (`shadow-sm`)
3. **Elevated** — dropdowns, popovers (`shadow-lg`)
4. **Overlay** — modals, drawers (`shadow-xl` + backdrop)
5. **Toast** — notifications (`shadow-xl` + fixed)

---

## 2. Dark Mode Strategy

### Implementation

- Tailwind `dark:` variant
- Toggle in topbar (sun/moon icon)
- Persist preference in `localStorage`
- Respect system preference on first visit
- Apply `dark` class to the `<html>` element

### Color Adjustments

- All semantic colors have light/dark variants
- Charts use adjusted palettes for dark mode
- Map tiles switch to dark variant when available
- Status badges maintain contrast in both modes

---

## 3. Navigation Architecture

### 3.1 Sidebar

- Left side, full height
- Collapsible: expanded (icons + labels) ↔ collapsed (icons only)
- Width: expanded `256px`, collapsed `64px`
- Background: `bg-primary` with subtle border-right
- Company logo at top
- Navigation sections with headings
- Active item highlighted with accent color
- Badge counts for pending items (approvals, alerts)
- Bottom: user info + settings

**Sidebar Navigation Structure:**

```
Dashboard
─────────
Live Map
Salesmen
Customers
─────────
Territories
Routes
─────────
Attendance
Visits
Orders
Collections
─────────
Targets
Expenses
Commissions
─────────
Products
Reports
─────────
Alerts
Devices
─────────
Settings
  ├── Company Settings
  ├── Users & Roles
  └── Subscription
```

### 3.2 Top Bar

- Sticky top, full width (minus sidebar)
- Height: `64px`
- **Left:** Page title / breadcrumb
- **Center:** Search input (command palette placeholder)
- **Right:** Notifications bell, dark mode toggle, user avatar + dropdown
- **Responsive:** sidebar hidden on mobile, hamburger toggle

### 3.3 Mobile Navigation

- Sidebar becomes slide-over drawer on mobile
- Triggered by hamburger icon in topbar
- Backdrop overlay
- Auto-close on navigation

---

## 4. Responsive Behavior

### Breakpoints

| Range | Behavior |
|---|---|
| Mobile `< 768px` | Single column, stacked layout |
| Tablet `768px – 1024px` | Collapsed sidebar, two-column where appropriate |
| Desktop `> 1024px` | Full sidebar, multi-column layouts |
| Large `> 1280px` | Wider content area |

### Responsive Patterns

- **Tables:** horizontal scroll on mobile, full on desktop
- **Dashboard cards:** 1 column mobile, 2 tablet, 3–4 desktop
- **Forms:** single column mobile, two column desktop
- **Sidebar:** drawer on mobile, collapsed on tablet, expanded on desktop

---

## 5. Reusable Blade Components

### 5.1 Layout Components

```blade
<x-layout.app-shell>
  <x-layout.sidebar />
  <x-layout.topbar />
  <x-layout.main-content>
    {{ $slot }}
  </x-layout.main-content>
</x-layout.app-shell>
```

### 5.2 UI Components

**StatCard** — KPI display with icon, value, label, trend

```blade
<x-ui.stat-card
  label="Today's Sales"
  value="AFN 45,200"
  trend="+12%"
  trend-direction="up"
  icon="currency-dollar"
/>
```

**DataTable** — Modern table with sorting, pagination, row actions

```blade
<x-ui.data-table :columns="$columns" :rows="$rows" :sortable="true">
  <x-slot:actions>
    <x-ui.button label="Export" />
  </x-slot:actions>
</x-ui.data-table>
```

**FilterBar** — Horizontal filter row with dropdowns, date pickers, search

```blade
<x-ui.filter-bar>
  <x-ui.select name="branch" :options="$branches" />
  <x-ui.date-range-picker name="date_range" />
  <x-ui.search-input name="search" placeholder="Search..." />
</x-ui.filter-bar>
```

**StatusBadge** — Colored badge for statuses

```blade
<x-ui.status-badge status="approved" />
<x-ui.status-badge status="pending" />
<x-ui.status-badge status="rejected" />
```

**Modal** — Dialog overlay with header, body, footer

```blade
<x-ui.modal name="confirm-action" title="Confirm Action">
  <p>Are you sure?</p>
  <x-slot:footer>
    <x-ui.button label="Cancel" variant="ghost" />
    <x-ui.button label="Confirm" variant="primary" />
  </x-slot:footer>
</x-ui.modal>
```

**Drawer** — Slide-over panel from right

```blade
<x-ui.drawer name="salesman-details" title="Salesman Profile">
  <!-- content -->
</x-ui.drawer>
```

**Toast** — Notification popup (top-right)

```blade
<x-ui.toast type="success" message="Changes saved successfully" />
```

**EmptyState** — Shown when no data available

```blade
<x-ui.empty-state
  icon="users"
  title="No customers yet"
  description="Add your first customer to get started"
  action-label="Add Customer"
  action-url="/customers/create"
/>
```

**LoadingState** — Skeleton loading placeholder

```blade
<x-ui.loading-state type="table" rows="5" />
<x-ui.loading-state type="cards" count="4" />
```

**ConfirmationDialog** — Destructive action confirmation

```blade
<x-ui.confirmation-dialog
  title="Delete Customer"
  message="This action cannot be undone."
  confirm-label="Delete"
  confirm-variant="danger"
/>
```

**MapCard** — Map display container

```blade
<x-ui.map-card id="live-map" :markers="$salesmen" height="500px" />
```

**ChartCard** — Chart container with title and controls

```blade
<x-ui.chart-card title="Sales Trend" type="line" :data="$chartData" />
```

**Tabs** — Tabbed content navigation

```blade
<x-ui.tabs :tabs="['Overview', 'Visits', 'Orders']">
  <x-slot:overview>...</x-slot:overview>
  <x-slot:visits>...</x-slot:visits>
  <x-slot:orders>...</x-slot:orders>
</x-ui.tabs>
```

**DateRangePicker** — Date range selection

```blade
<x-ui.date-range-picker name="range" :start="$start" :end="$end" />
```

**Pagination** — Page navigation

```blade
<x-ui.pagination :paginator="$visits" />
```

**Dropdown** — Menu dropdown

```blade
<x-ui.dropdown>
  <x-slot:trigger>
    <x-ui.button label="Actions" icon="chevron-down" />
  </x-slot:trigger>
  <x-ui.dropdown-item label="Edit" icon="pencil" />
  <x-ui.dropdown-item label="Delete" icon="trash" variant="danger" />
</x-ui.dropdown>
```

**UserAvatar** — User profile image/initials

```blade
<x-ui.user-avatar :user="$user" size="md" />
```

**SearchInput** — Search with icon and debounce

```blade
<x-ui.search-input name="q" placeholder="Search customers..." />
```

**Breadcrumb** — Page breadcrumb trail

```blade
<x-ui.breadcrumb :items="[
  ['label' => 'Dashboard', 'url' => '/dashboard'],
  ['label' => 'Customers'],
]" />
```

**PageHeader** — Page title area with actions

```blade
<x-ui.page-header title="Customers" subtitle="Manage your customer database">
  <x-slot:actions>
    <x-ui.button label="Add Customer" icon="plus" />
  </x-slot:actions>
</x-ui.page-header>
```

---

## 6. Page Layouts

### 6.1 Login Page

- Centered card on neutral background
- Company logo
- Email + password fields
- Remember me checkbox
- Login button
- Footer with copyright

### 6.2 Dashboard

```
┌─────────────────────────────────────────────┐
│ Page Header: Dashboard + Date Range Picker  │
├─────────────────────────────────────────────┤
│ KPI Cards Row (4-6 cards)                   │
│ [Today's Sales] [Collections] [Active] [Visits] │
├─────────────────────────────────────────────┤
│ Charts Row                                   │
│ [Sales Trend Chart]  [Visit Completion]      │
├─────────────────────────────────────────────┤
│ Map + Performance Row                        │
│ [Live Map (60%)]  [Top Performers (40%)]     │
├─────────────────────────────────────────────┤
│ Recent Activity / Alerts Table               │
└─────────────────────────────────────────────┘
```

### 6.3 Live Map Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Live Map + Refresh Interval     │
├─────────────────────────────────────────────┤
│ Filter Bar: Branch, Territory, Status        │
├─────────────────────────────────────────────┤
│ Map (full width, ~600px height)              │
│ - Markers for each salesman                  │
│ - Click marker → info popup                  │
│ - Legend for status colors                   │
├─────────────────────────────────────────────┤
│ Salesman Status Table (below map)            │
│ Name | Status | Last Update | Location | Battery │
└─────────────────────────────────────────────┘
```

### 6.4 Salesmen List Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Salesmen + Add Salesman button  │
├─────────────────────────────────────────────┤
│ Filter Bar: Branch, Territory, Status, Search │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Code | Name | Territory | Status | Actions   │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.5 Salesman Profile Page

```
┌─────────────────────────────────────────────┐
│ Breadcrumb > Salesmen > Ahmad Shah           │
├──────────────┬──────────────────────────────┤
│ Salesman Info │ Tabs                         │
│ [Avatar]      │ [Overview] [Visits] [Orders] │
│ Code: S001   │ [Collections] [Expenses]      │
│ Territory: X  │ [GPS History] [Performance]   │
│ Status: Active│                              │
│              │ Tab Content                   │
│ Map showing  │ (varies by tab)               │
│ recent route │                              │
└──────────────┴──────────────────────────────┘
```

### 6.6 Customers List Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Customers + Add Customer button │
├─────────────────────────────────────────────┤
│ Filter Bar: Territory, Route, Salesman, Status│
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Code | Name | Contact | Salesman | Status    │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.7 Customer Profile Page

```
┌─────────────────────────────────────────────┐
│ Breadcrumb > Customers > Kabul Dry Goods     │
├──────────────┬──────────────────────────────┤
│ Customer Info │ Tabs                         │
│ [Photo]       │ [Overview] [Visits] [Orders] │
│ Code: C001   │ [Collections] [Balance]       │
│ Contact: ...  │                              │
│ Address: ...  │ Tab Content                  │
│              │ (varies by tab)                │
│ Map showing  │                              │
│ location     │                              │
└──────────────┴──────────────────────────────┘
```

### 6.8 Attendance Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Attendance                      │
├─────────────────────────────────────────────┤
│ Filter Bar: Date, Branch, Salesman, Status   │
├─────────────────────────────────────────────┤
│ Today Summary Cards                          │
│ [On Time] [Late] [Absent] [Total Active]     │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Salesman | Check In | Check Out | Duration   │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.9 Visits Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Visits                          │
├─────────────────────────────────────────────┤
│ Filter Bar: Date Range, Salesman, Customer,  │
│             Outcome, Verification Status     │
├─────────────────────────────────────────────┤
│ Summary Cards                                │
│ [Total] [Completed] [Planned] [Unplanned]    │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Salesman | Customer | Time | Duration | Type │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.10 Orders Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Orders                          │
├─────────────────────────────────────────────┤
│ Filter Bar: Date Range, Salesman, Customer,  │
│             Status, Payment Type             │
├─────────────────────────────────────────────┤
│ Summary Cards                                │
│ [Total] [Pending] [Approved] [Delivered]     │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ # | Salesman | Customer | Amount | Status    │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.11 Collections Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Collections                     │
├─────────────────────────────────────────────┤
│ Filter Bar: Date Range, Salesman, Customer,  │
│             Payment Method                   │
├─────────────────────────────────────────────┤
│ Summary Cards                                │
│ [Total Collected] [Cash] [Bank] [Mobile]     │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Salesman | Customer | Amount | Method | Date │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.12 Targets Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Targets                         │
├─────────────────────────────────────────────┤
│ Filter Bar: Period, Metric, Branch, Territory│
├─────────────────────────────────────────────┤
│ Target Overview Cards                        │
│ [Sales Achievement] [Collection] [Visits]    │
├─────────────────────────────────────────────┤
│ Data Table with Progress Bars                │
│ Salesman | Target | Achieved | Progress %    │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.13 Expenses Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Expenses                        │
├─────────────────────────────────────────────┤
│ Filter Bar: Date Range, Salesman, Category,  │
│             Status                           │
├─────────────────────────────────────────────┤
│ Summary Cards                                │
│ [Total] [Pending] [Approved] [Rejected]      │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Salesman | Category | Amount | Status | Date │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.14 Devices Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Devices                         │
├─────────────────────────────────────────────┤
│ Filter Bar: Status, User, App Version        │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ User | Device | App Version | Last Seen |    │
│       Status | Actions (Revoke)              │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.15 Alerts Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Alerts & Suspicious Activity    │
├─────────────────────────────────────────────┤
│ Filter Bar: Date Range, Type, Severity,      │
│             Salesman, Resolved               │
├─────────────────────────────────────────────┤
│ Summary Cards                                │
│ [Total] [High] [Medium] [Unresolved]         │
├─────────────────────────────────────────────┤
│ Data Table                                   │
│ Type | Salesman | Details | Severity | Date  │
├─────────────────────────────────────────────┤
│ Pagination                                   │
└─────────────────────────────────────────────┘
```

### 6.16 Reports Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Reports                         │
├─────────────────────────────────────────────┤
│ Report Type Selector (tabs or cards)         │
│ [Sales] [Visits] [GPS] [Performance] [Custom]│
├─────────────────────────────────────────────┤
│ Report Filters                               │
│ Date Range, Branch, Territory, Salesman      │
├─────────────────────────────────────────────┤
│ Report Content                               │
│ Charts + Tables + Summary                    │
├─────────────────────────────────────────────┤
│ Export Buttons                               │
│ [Export PDF] [Export CSV]                     │
└─────────────────────────────────────────────┘
```

### 6.17 Company Settings Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Company Settings                │
├─────────────────────────────────────────────┤
│ Settings Sections (card-based)               │
│ [General] [Branches] [Tracking] [Billing]    │
├─────────────────────────────────────────────┤
│ Section Content                              │
│ Form fields per section                      │
│ Save button per section                      │
└─────────────────────────────────────────────┘
```

### 6.18 Users & Roles Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Users & Roles                   │
├─────────────────────────────────────────────┤
│ Tabs: [Users] [Roles] [Permissions]          │
├─────────────────────────────────────────────┤
│ Users Tab: Data Table with Add User button   │
│ Roles Tab: Role list with permission matrix  │
│ Permissions Tab: Permission groups            │
└─────────────────────────────────────────────┘
```

### 6.19 Territories Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Territories                     │
├─────────────────────────────────────────────┤
│ Split View                                   │
│ [Territory List (40%)] [Map View (60%)]      │
│ Select territory → highlight on map          │
│ Show routes and customers within territory   │
└─────────────────────────────────────────────┘
```

### 6.20 Routes / Route Planner Page

```
┌─────────────────────────────────────────────┐
│ Page Header: Route Planner                   │
├─────────────────────────────────────────────┤
│ Route Selector Dropdown                      │
├─────────────────────────────────────────────┤
│ Split View                                   │
│ [Customer Sequence (40%)] [Map Route (60%)]  │
│ Drag-and-drop customer ordering              │
│ Show route polyline on map                   │
│ Day-of-week assignment                       │
└─────────────────────────────────────────────┘
```

### 6.21 Subscription / Billing Page (Future)

```
┌─────────────────────────────────────────────┐
│ Page Header: Subscription                    │
├─────────────────────────────────────────────┤
│ Current Plan Card                            │
│ Usage Metrics                                │
│ Upgrade/Downgrade Buttons                    │
│ Billing History Table                        │
└─────────────────────────────────────────────┘
```

---

## 7. Chart Types & Usage

### Dashboard Charts

- **Sales Trend:** Line chart, daily/weekly/monthly
- **Visit Completion:** Donut/pie chart (completed vs planned vs missed)
- **Collection Methods:** Bar chart (cash vs bank vs mobile)
- **Target Achievement:** Progress bars or bar chart
- **Salesman Ranking:** Horizontal bar chart (top 10)
- **Territory Performance:** Bar chart comparison

### Chart Library

- Use Chart.js via CDN/npm (lightweight, no framework dependency)
- Alpine.js for chart interactivity (date range, filters)
- Responsive chart containers

---

## 8. Map Integration

### Map Provider

- **V1:** OpenStreetMap / Leaflet (free, no API key)
- **Future:** MapLibre GL JS for vector tiles
- Abstract map component (provider-agnostic)

### Map Features

- Marker clustering (when many salesmen)
- Marker popups with salesman info
- Route polylines
- Geofence circles
- Territory boundaries (future)
- Heatmap for visit density (future)

### Map Interactions

- Click marker → drawer with salesman details
- Click territory → filter to that territory
- Zoom to fit all markers
- Fullscreen toggle

---

## 9. Form Patterns

### Form Layout

- Single column on mobile
- Two columns on desktop for complex forms
- Fieldsets with clear labels
- Inline validation with error messages
- Required field indicators (`*`)

### Form Components

- Text inputs with labels and helper text
- Select dropdowns with search
- Multi-select with checkboxes
- Date pickers (single and range)
- File upload with preview
- Toggle switches
- Radio buttons for single choice
- Textarea for notes

### Form Actions

- Save button (primary)
- Cancel button (ghost/secondary)
- Delete button (danger, with confirmation)
- Sticky bottom bar on mobile forms

---

## 10. Table Patterns

### Standard Table

- Header row with sortable column indicators
- Alternating row colors (subtle)
- Hover state
- Row click action (navigate or open drawer)
- Checkbox for bulk selection
- Row actions dropdown (edit, delete, view)
- Empty state when no data
- Loading skeleton when fetching

### Table Features

- Column sorting (click header)
- Column visibility toggle (optional)
- Bulk actions toolbar (appears when items selected)
- Export button
- Pagination (bottom)
- Row count display

---

## 11. Loading & Empty States

### Loading States

- Skeleton screens (gray animated placeholders)
- Table skeleton: 5–8 rows of gray bars
- Card skeleton: gray rectangles matching card layout
- Map skeleton: gray rectangle with loading spinner
- Button loading: spinner replacing text

### Empty States

- Centered icon + title + description + action button
- Contextual illustration (SVG)
- Helpful message explaining why empty
- Primary action to create first item

### Error States

- Error icon
- Error message (human-readable)
- Retry button
- Contact support link if critical

---

## 12. Notification Patterns

### Toast Notifications

- Position: top-right
- Types: success, error, warning, info
- Auto-dismiss: 5 seconds (success), manual dismiss (error)
- Stack vertically
- Slide-in animation

### In-App Notifications

- Bell icon with unread count badge
- Dropdown panel with notification list
- Click notification → navigate to relevant page
- Mark as read / mark all as read

### Confirmation Dialogs

- For destructive actions (delete, revoke, reject)
- Title explaining the action
- Description of consequences
- Confirm button (red for destructive)
- Cancel button

---

## 13. Accessibility

### Requirements

- Keyboard navigation for all interactive elements
- Focus visible indicators
- ARIA labels on icons and interactive elements
- Sufficient color contrast (WCAG AA)
- Alt text for images
- Screen reader friendly form labels
- Skip to main content link
- Reduced motion support (`prefers-reduced-motion`)

### Implementation

- Semantic HTML (`nav`, `main`, `aside`, `header`, `footer`)
- ARIA roles where appropriate
- Focus management for modals and drawers
- Tab order follows visual order

---

## 14. RTL Readiness

### Tailwind RTL Support

- Use Tailwind `rtl:` variant for directional styles
- Layout mirrors for RTL languages
- Text alignment adjusts
- Icons with direction (arrows, chevrons) flip
- Padding/margin adjustments

### Planning

- All components designed with RTL in mind
- No hard-coded left/right (use `start`/`end` where possible)
- Test with Arabic/Dari/Pashto content

---

## 15. Animation & Transitions

### Guidelines

- Subtle, purposeful animations only
- Duration: `150ms`–`300ms`
- Easing: `ease-in-out`
- Avoid animation on large elements (performance)

### Specific Animations

| Element | Duration |
|---|---|
| Sidebar collapse/expand | 200ms |
| Modal/drawer open | 200ms slide + fade |
| Toast slide-in | 300ms |
| Dropdown open | 150ms scale + fade |
| Skeleton pulse | 2s infinite |
| Page transitions | none (server-rendered, instant) |

---

## 16. Component File Structure

```
resources/views/
├── components/
│   ├── layout/
│   │   ├── app-shell.blade.php
│   │   ├── sidebar.blade.php
│   │   ├── topbar.blade.php
│   │   └── main-content.blade.php
│   ├── ui/
│   │   ├── stat-card.blade.php
│   │   ├── data-table.blade.php
│   │   ├── filter-bar.blade.php
│   │   ├── search-input.blade.php
│   │   ├── date-range-picker.blade.php
│   │   ├── status-badge.blade.php
│   │   ├── user-avatar.blade.php
│   │   ├── modal.blade.php
│   │   ├── drawer.blade.php
│   │   ├── dropdown.blade.php
│   │   ├── tabs.blade.php
│   │   ├── toast.blade.php
│   │   ├── empty-state.blade.php
│   │   ├── loading-state.blade.php
│   │   ├── pagination.blade.php
│   │   ├── confirmation-dialog.blade.php
│   │   ├── map-card.blade.php
│   │   ├── chart-card.blade.php
│   │   ├── breadcrumb.blade.php
│   │   ├── page-header.blade.php
│   │   ├── button.blade.php
│   │   ├── input.blade.php
│   │   ├── select.blade.php
│   │   ├── textarea.blade.php
│   │   ├── toggle.blade.php
│   │   └── card.blade.php
│   └── partials/
│       ├── dashboard/
│       ├── salesmen/
│       ├── customers/
│       └── ...
├── layouts/
│   ├── app.blade.php
│   └── auth.blade.php
├── pages/
│   ├── dashboard/
│   │   └── index.blade.php
│   ├── salesmen/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── customers/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── tracking/
│   │   └── live.blade.php
│   ├── attendance/
│   │   └── index.blade.php
│   ├── visits/
│   │   └── index.blade.php
│   ├── orders/
│   │   └── index.blade.php
│   ├── collections/
│   │   └── index.blade.php
│   ├── targets/
│   │   └── index.blade.php
│   ├── expenses/
│   │   └── index.blade.php
│   ├── devices/
│   │   └── index.blade.php
│   ├── alerts/
│   │   └── index.blade.php
│   ├── reports/
│   │   └── index.blade.php
│   ├── territories/
│   │   └── index.blade.php
│   ├── routes/
│   │   └── index.blade.php
│   ├── settings/
│   │   ├── company.blade.php
│   │   └── users.blade.php
│   └── auth/
│       ├── login.blade.php
│       └── forgot-password.blade.php
├── emails/
│   └── ... (future)
└── vendor/
    └── ... (blade component overrides)
```