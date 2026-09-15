# Security & Privacy Document

**Platform:** Field Sales SaaS
**Stack:** Laravel 13, Sanctum, MySQL, Flutter (Android), Blade + Tailwind Admin
**Market:** Afghanistan (initial), International (future)
**Classification:** Internal — Do Not Distribute
**Last Updated:** 2026-09-15

---

## Table of Contents

1. [Security Architecture Overview](#1-security-architecture-overview)
2. [Authentication](#2-authentication)
3. [Authorization](#3-authorization)
4. [Tenant Isolation](#4-tenant-isolation)
5. [API Security](#5-api-security)
6. [Device Security](#6-device-security)
7. [Data Protection](#7-data-protection)
8. [GPS Privacy](#8-gps-privacy)
9. [Audit Logging](#9-audit-logging)
10. [Secure File Uploads](#10-secure-file-uploads)
11. [Fraud Detection](#11-fraud-detection)
12. [Infrastructure Security](#12-infrastructure-security)
13. [Web Admin Panel Security](#13-web-admin-panel-security)
14. [Mobile App Security](#14-mobile-app-security)
15. [Incident Response](#15-incident-response)
16. [Compliance Considerations](#16-compliance-considerations)
17. [Security Checklist for Launch](#17-security-checklist-for-launch)

---

## 1. Security Architecture Overview

### Defense in Depth

Security is implemented in layered defenses so that no single point of failure compromises the entire system.

| Layer | Description |
|---|---|
| **Application Layer** | Input validation, output encoding, business logic guards, exception handling that leaks no internals |
| **API Layer** | Sanctum authentication, rate limiting, request signing (future), CORS policy, request validation via Form Requests |
| **Database Layer** | Tenant isolation via global scopes, parameterized queries, least-privilege DB user, encrypted connections |
| **Transport Layer** | TLS 1.2+ enforced, HSTS headers, certificate management, no plaintext HTTP in production |
| **Device Layer** | Device registration and binding, app version enforcement, local encryption (SQLCipher), secure key storage |
| **Organizational Layer** | RBAC, audit logging, security training, incident response procedures, privacy policies |

---

## 2. Authentication

### Sanctum Token Authentication

| Context | Mechanism | Lifetime |
|---|---|---|
| **Mobile API (Flutter)** | Personal access tokens via Sanctum | Configurable — default 30 days |
| **Web Admin Panel** | Session-based (Sanctum SPA authentication) | Configurable — default 24 hours |

- Token abilities are scoped to device type (`mobile`, `web`, `admin`).
- Tokens are created on login and returned to the client once.
- The server stores only the hashed token (SHA-256).

### Token Management

| Rule | Detail |
|---|---|
| One token per device | Device-bound via device UUID stored in token name |
| Revocation on password change | All tokens for the user revoked automatically |
| Revocation on account deactivation | All tokens revoked immediately |
| Bulk revocation | Admin action: revoke all tokens for a user or company |
| Token refresh | Mobile app silently refreshes token before expiry (future) |

### Password Policy

| Requirement | Value |
|---|---|
| Minimum length | 8 characters |
| Complexity | At least one letter and one number |
| Hashing | bcrypt (Laravel default, 12 rounds) |
| Reset flow | Email-based (web) or admin-initiated reset |
| Rate limiting | 5 login attempts per minute per IP/account |
| History | Last 5 passwords cannot be reused (future) |

### Session Management

| Context | Behavior |
|---|---|
| Web | Laravel session with `secure`, `httponly`, `samesite=strict` cookies |
| Mobile | Token-based — no server-side session |
| Concurrent sessions | Configurable per company (default: unlimited for mobile, 1 for web) |
| Idle timeout | Web: configurable (default 8 hours); Mobile: token expiry |

---

## 3. Authorization

### Role-Based Access Control (RBAC)

#### Platform-Level

| Role | Access |
|---|---|
| **Super Admin** | Full platform access across all tenants. Can switch tenants (audit logged). |

#### Company-Level

| Role | Permissions |
|---|---|
| **Owner** | Full company access. Billing/subscription management. User management. All CRUD operations. |
| **Company Admin** | Full company access except billing. User management. Configuration. |
| **Sales Manager** | View all salesmen, customers (company-wide), visits, orders, collections. Manage targets. Approve expenses. View reports. View live map. |
| **Supervisor** | View assigned salesmen only. View assigned territory customers and routes. View assigned salesmen's visits, orders. Approve expenses for assigned salesmen. View territory reports. |
| **Salesman** | Own profile. Assigned customers only. Own routes, visits, orders, collections, expenses. Own GPS status. |
| **Accountant** | View all collections and expenses. View financial reports. Export financial data. |
| **Warehouse User** | View orders. Update order status (dispatched, delivered). View inventory (future). |
| **Auditor / Read-only** | Read-only access to all data. Export reports. No create/update/delete. |

### Permission Format

Permissions follow the `resource:action` convention:

```
customers:view
customers:create
customers:update
customers:delete
orders:view
orders:create
orders:approve
expenses:view
expenses:approve
reports:view
reports:export
users:manage
settings:manage
```

### Branch-Scoped Access

| Role | Branch Scope |
|---|---|
| Owner / Company Admin | All branches |
| Sales Manager | All branches (configurable to restrict) |
| Supervisor | Assigned branch(es) only |
| Salesman | Own branch only |

Branch assignment is historical — the audit trail is preserved when a user's branch changes.

### Policy Implementation

- One Laravel Policy per domain entity (`Customer`, `Order`, `Visit`, `Expense`, etc.).
- `before()` method grants Super Admin unrestricted access.
- Tenant scope is applied via an Eloquent global scope and **cannot be bypassed outside an explicit platform/system context** (fail-closed).
- **Role storage (source of truth):** `model_has_roles` (tenant-scoped pivot). The `users.role` column is a **denormalized mirror** maintained automatically for display/convenience — authorization never reads it.
- Branch scope is applied within policies where relevant.

---

## 4. Tenant Isolation

### Database-Level Isolation

- Every business table carries a `tenant_id` foreign key.
- A global Eloquent scope applies `WHERE tenant_id = ?` to all queries automatically.
- The scope is **fail-closed** (three states):
  - **Tenant** — a concrete tenant is active; the scope appends `WHERE tenant_id = ?`.
  - **Platform (explicit bypass)** — entered only through guarded APIs (`enterPlatformForUser` for the HTTP super admin, `enterSystemContext` for trusted seeders/console/auth bootstrap); company users are rejected.
  - **Uninitialized** — no context; accessing a tenant-owned model throws `TenantContextMissingException` instead of silently escaping isolation.
- Cross-tenant queries are impossible through Eloquent or the Query Builder unless an explicit platform/system context is deliberately activated.

### API-Level Isolation

- Middleware resolves the tenant from the authenticated user's `company_id`.
- The tenant context is set in the request and inherited by all service and repository layers.
- The client never sends `tenant_id` — it is derived server-side.

### File Storage Isolation

- Files are stored in `{tenant_id}/` directories on disk or S3.
- Signed URLs include tenant verification.
- No cross-tenant file access is possible.

### Job/Queue Isolation

- Every job carries `tenant_id` in its payload.
- Job middleware must explicitly initialize the tenant context (or an explicit platform/system context) before executing any tenant-owned model queries — the fail-closed scope refuses to infer context.
- No cross-tenant job processing occurs.

### Cache Isolation

- All cache keys are prefixed with the tenant ID.
- No cross-tenant cache access is possible.

### Cross-Tenant Access Prevention

| Rule | Detail |
|---|---|
| Client cannot set tenant | `tenant_id` is never accepted from API input |
| Super Admin switching | Allowed, but every switch is audit-logged |
| Single-company users (V1) | Each user belongs to exactly one company |
| Multi-company (future) | Users may belong to multiple companies with explicit tenant switching |

---

## 5. API Security

### Rate Limiting

| Endpoint Category | Limit |
|---|---|
| Authentication endpoints | 10 requests/minute per IP |
| General API | 60 requests/minute per device |
| GPS upload | 10 requests/minute per device |
| Sync endpoints | 30 requests/minute per device |
| Password reset | 3 requests/minute per email |

### Request Validation

- Form Request classes for every API endpoint — no inline validation.
- Strict type validation (no PHP type juggling).
- Array and nested data validated with explicit rules.
- File uploads validated for type, MIME, and size.
- Date formats validated and normalized.

### Mass Assignment Protection

- `$fillable` declared on every model.
- No model uses `$guarded = []`.
- API Resources transform output — raw Eloquent models are never returned to clients.

### SQL Injection Prevention

- Eloquent ORM and Query Builder used for all queries.
- Parameterized bindings enforced for any raw SQL.
- No string interpolation in SQL constructs.

### XSS Prevention

- Blade `{{ }}` auto-escapes all output.
- `{!! !!}` is never used with user-controlled input.
- Content-Security-Policy headers are set on all responses.
- Alpine.js template literals are reviewed for injection vectors.

### CSRF Protection

- Laravel CSRF token is verified on all web forms.
- API endpoints are exempt (authenticated via tokens).

### CORS Configuration

| Setting | Value |
|---|---|
| Allowed origins | Explicitly allowlisted (no `*`) |
| Allowed headers | Minimal set required by the app |
| Allowed methods | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` |
| Credentials | Supported for web admin panel |

### Request Signing (Future)

- HMAC signing for high-security endpoints.
- Timestamp-based replay protection with a configurable window.

---

## 6. Device Security

### Device Registration

- Each device is registered with a unique UUID.
- Device is bound to a user account at registration.
- Device metadata is captured: model, OS version, app version, screen resolution.

### Device Policies

| Policy | Detail |
|---|---|
| One device per salesman | Default; configurable to allow multiple |
| Max devices per user | Configurable per company |
| Device revocation | Admin can revoke any device |
| Forced logout | Admin can revoke all device tokens for a user |

### Device Validation

- API requests must include device identification headers.
- Device UUID is validated against the registered devices table.
- Revoked devices receive `403 Forbidden` on every request.
- New device registration requires an authenticated session.

### App Version Enforcement

- Company-configurable minimum app version.
- API returns `426 Upgrade Required` for outdated app versions.
- Response body includes `upgrade_url` for forced updates.

### Session Invalidation

| Trigger | Action |
|---|---|
| Password change | Revoke all tokens for the user |
| Account deactivation | Revoke all tokens immediately |
| Admin force logout | Revoke all tokens |
| Suspicious activity | Revoke specific device token |

---

## 7. Data Protection

### Sensitive Data Handling

| Data Type | Protection |
|---|---|
| Passwords | bcrypt hashed; never stored in plain text |
| API tokens | SHA-256 hashed; only the hash is stored |
| Personal data | Encrypted at rest (future: Laravel encryption) |
| GPS data | Access-controlled; retention-limited |
| Financial data | Access-controlled; audit-logged on access |

### Data Classification

| Level | Examples |
|---|---|
| **Public** | Product names, company name (for display) |
| **Internal** | User names, employee codes, branch names |
| **Confidential** | GPS locations, financial data, visit records |
| **Restricted** | Passwords, tokens, API keys, encryption keys |

### Encryption

| Context | Method |
|---|---|
| Transport | TLS 1.2+ enforced on all connections (HTTPS) |
| At rest (database) | Server-level disk encryption |
| At rest (files) | Future: Laravel file encryption for sensitive uploads |
| Application secrets | Laravel app key used for signing and encryption |

### Backup & Recovery

| Item | Detail |
|---|---|
| Frequency | Daily database backups |
| Storage | Encrypted backup storage (off-server) |
| Retention | 30 days |
| Recovery | Documented and tested recovery procedure |
| Integrity | Checksums verified on backup completion |

---

## 8. GPS Privacy

### Transparency Requirements

- Salesmen must be explicitly informed of GPS tracking before it begins.
- Tracking policy is displayed in the mobile app at first launch.
- A clear, persistent indicator shows when tracking is active.
- Salesman's acceptance of the tracking policy is recorded in the audit log.

### Tracking Rules

| Mode | Behavior |
|---|---|
| **Standard** | Track throughout the configured work day |
| **Strict** | Track only during active visits |
| Configurable intervals | Company sets GPS upload frequency (e.g., every 1, 5, 15 minutes) |
| Work hours only | No tracking outside configured work hours (configurable) |

### Data Minimization

- GPS data is retained for a configurable period (default: 90 days).
- Old data is archived or deleted automatically.
- Salesmen can view their own GPS history.
- GPS data is never sold or shared with third parties.

### Access Control

| Rule | Detail |
|---|---|
| Role-based access | Only authorized roles (Manager, Supervisor, Admin) view GPS data |
| Territory scoping | GPS data is scoped to the viewer's territory or branch |
| Export restrictions | GPS export requires elevated permission |
| Audit logged | Every access to GPS data is recorded in the audit log |

### Privacy Policy

- Every company must maintain a written GPS tracking policy.
- Salesmen acknowledge the policy before their first GPS-tracked action.
- The policy is stored in company settings with version tracking.
- Policy changes trigger re-acknowledgment (future).

### Legal Compliance

- Comply with Afghan labor laws where applicable.
- Data processing consent is documented and stored.
- Salesmen have the right to access their own GPS data.
- Right to deletion is configurable per company policy.

---

## 9. Audit Logging

### What Gets Logged

| Category | Events |
|---|---|
| Authentication | Login, logout, failed login, password change, password reset |
| Authorization | Permission denied, role change, tenant switch |
| CRUD | Create, update, delete on sensitive entities (customers, orders, expenses, users) |
| Configuration | Company settings changes, role/permission changes |
| Devices | Device registration, revocation, forced logout |
| GPS | Tracking policy changes, GPS data access, anomaly flags |
| Exports | Data export events (who, what, when) |
| Account | Account creation, deactivation, reactivation |

### Audit Log Schema

```json
{
  "tenant_id": "integer — company ID",
  "user_id": "integer — actor ID",
  "event": "string — e.g. 'order.created', 'user.login'",
  "auditable_type": "string — e.g. 'App\\Models\\Order'",
  "auditable_id": "integer — ID of the affected record",
  "old_values": "JSON — previous state (updates/deletes)",
  "new_values": "JSON — new state (creates/updates)",
  "url": "string — request URL",
  "ip_address": "string — client IP",
  "user_agent": "string — client user agent",
  "created_at": "timestamp"
}
```

### Audit Log Access

| Rule | Detail |
|---|---|
| Viewable by | Super Admin, Company Admin, Auditor |
| Exportable | CSV format |
| Retention | 1 year (configurable) |
| Integrity | Cannot be modified or deleted by application users |
| Storage | Dedicated `audit_logs` table; not mixed with application logs |

---

## 10. Secure File Uploads

### Validation Rules

| Rule | Value |
|---|---|
| Allowed types | jpg, jpeg, png, pdf |
| Max file size | 5 MB (configurable per company) |
| Image dimensions | Max 4000 x 4000 pixels |
| MIME validation | Server-side MIME type check (not just file extension) |
| Content scanning | Future: virus/malware scanning on upload |

### Storage Security

- Files are stored outside the public web directory.
- Access is granted via signed URLs with a configurable expiration (default: 1 hour).
- Signed URLs include tenant-scoped verification.
- Directory listing is disabled.

### Upload Flow

1. Client requests a pre-signed upload URL (or uploads directly to the API).
2. Server validates file type, MIME, size, and image dimensions.
3. Server generates a unique filename (UUID-based).
4. File is stored in a tenant-scoped directory (`{tenant_id}/{year}/{month}/`).
5. A `File` record is created in the database.
6. Client receives a signed URL for accessing the file.
7. When a file is replaced, the old file is deleted.

---

## 11. Fraud Detection

### Indicators (Not Automatic Guilt)

| Indicator | Severity |
|---|---|
| Mock GPS detected | High |
| Impossible travel speed between visits | High |
| Same GPS coordinates for multiple visits | Medium |
| Visit recorded far outside geofence | Medium |
| Extremely short visit (< 30 seconds) | Medium |
| Large GPS gap during work hours | Medium |
| Tracking disabled during work hours | High |
| Unexpected device change | Medium |

### Severity Levels

| Level | Meaning | Action |
|---|---|---|
| **Low** | Informational; no action required | Logged for reference |
| **Medium** | Anomaly detected; needs review | Flagged for supervisor review |
| **High** | Likely fraud indicator; requires investigation | Escalated to manager/admin |

### Response Flow

1. GPS and visit data is analyzed server-side on ingestion.
2. A `SuspiciousFlag` record is created when an indicator is triggered.
3. Notification is sent to the assigned supervisor or manager.
4. Manager reviews the flag in the Alerts page.
5. Manager marks the flag as: **Resolved**, **False Positive**, or **Confirmed**.
6. The full audit trail is preserved regardless of outcome.

---

## 12. Infrastructure Security

### Server Security

| Measure | Detail |
|---|---|
| OS | Ubuntu/Debian with automatic security updates |
| Firewall | UFW — only ports 80, 443, and 22 open |
| SSH | Key-based authentication only; password auth disabled |
| Brute force | Fail2Ban monitoring SSH and web login |
| Patching | Regular security patch schedule (at minimum: monthly) |

### Database Security

| Measure | Detail |
|---|---|
| Password | Strong, randomly generated database password |
| Remote access | Restricted to application server (or VPN) |
| Backups | Daily, encrypted, off-server |
| Connections | Encrypted (TLS) where supported by hosting |

### Redis Security

| Measure | Detail |
|---|---|
| Authentication | Password protected |
| Network | Not exposed to public internet |
| Purpose | Caching, sessions, queue broker |

### Laravel Security

| Setting | Value |
|---|---|
| `APP_DEBUG` | `false` in production |
| `APP_ENV` | `production` |
| `APP_KEY` | Strong, randomly generated 32-byte key |
| Dependencies | Regular `composer update` with security advisories |
| Monitoring | Laravel Vapor/Forge security alerts or Dependabot |

---

## 13. Web Admin Panel Security

### Session Security

| Setting | Value |
|---|---|
| Cookie secure | `true` (HTTPS only) |
| Cookie httponly | `true` (no JavaScript access) |
| Cookie SameSite | `Strict` |
| Session timeout | Configurable (default: 8 hours) |

### CSRF Protection

- CSRF token is included in every form via `@csrf` directive.
- Verified on all state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`).
- Token is rotated on each request.

### Content Security

| Header | Value |
|---|---|
| `Content-Security-Policy` | Restrictive policy; only allow trusted sources |
| `X-Frame-Options` | `DENY` (no iframe embedding) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-XSS-Protection` | `0` (rely on CSP instead) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` |

### Input Sanitization

- Server-side validation on every input using Form Requests.
- Client-side validation is for UX only — never trusted for security.
- HTML is stripped from all plain text inputs.
- Rich text editing is not supported in V1.

---

## 14. Mobile App Security

### Local Storage

| Measure | Detail |
|---|---|
| Database | SQLite encrypted with SQLCipher |
| Sensitive data | Never stored in plain text files |
| Key storage | Android Keystore for encryption keys |

### Network Security

| Measure | Detail |
|---|---|
| Certificate pinning | Optional, configurable per deployment |
| Protocol | HTTPS only; no HTTP fallback |
| TLS version | Minimum TLS 1.2 |

### Code Security

| Measure | Detail |
|---|---|
| Obfuscation | Enabled for release builds (ProGuard/R8) |
| API keys | Never hardcoded; fetched from secure config |
| Logging | No sensitive data in release logs |

### Device Security

| Feature | Status |
|---|---|
| Jailbreak/root detection | Optional |
| Screen capture prevention | Optional |
| Biometric authentication | Future |

---

## 15. Incident Response

### Security Incident Process

| Step | Action |
|---|---|
| 1. Detection | Monitoring alerts, user reports, anomaly detection |
| 2. Assessment | Classify severity (Low / Medium / High / Critical) |
| 3. Containment | Revoke compromised tokens, block malicious IPs, isolate affected systems |
| 4. Investigation | Review audit logs, analyze attack vector, identify scope |
| 5. Recovery | Restore from backup if data integrity is compromised |
| 6. Communication | Notify affected users and stakeholders |
| 7. Documentation | Write incident report with timeline, impact, and remediation |
| 8. Prevention | Update security measures, patch vulnerabilities, improve monitoring |

### Monitoring

| Signal | Response |
|---|---|
| Failed login attempts | Alert after threshold; block after limit |
| Unusual API usage patterns | Rate limit escalation; flag for review |
| GPS anomalies | Fraud detection pipeline (see Section 11) |
| File access anomalies | Alert on unusual download patterns |
| Database query anomalies | Monitor for slow queries, unexpected full-table scans |

---

## 16. Compliance Considerations

### Data Protection Principles

| Principle | Implementation |
|---|---|
| Consent | User consent collected before data processing |
| Purpose limitation | GPS data used only for work tracking |
| Data minimization | Only necessary data is collected and retained |
| Storage limitation | Retention policies enforced; old data archived or deleted |
| Access control | Role-based; least-privilege principle |

### Afghan Market

- Comply with local data protection regulations where they exist.
- Support local data residency if required by law.
- Bilingual privacy documentation (English and Dari).
- Engage local legal counsel for regulatory compliance.

### Future Compliance Readiness

| Standard | Timeline |
|---|---|
| GDPR readiness | When expanding to international markets |
| SOC 2 | Enterprise sales enablement |
| ISO 27001 | Long-term security posture goal |

---

## 17. Security Checklist for Launch

### Infrastructure

- [ ] HTTPS configured and enforced (no HTTP in production)
- [ ] `APP_DEBUG` set to `false`
- [ ] Strong `APP_KEY` generated and secured
- [ ] Database credentials stored securely (not in version control)
- [ ] Redis password set and not exposed publicly
- [ ] Server firewall configured (UFW: 80, 443, 22 only)
- [ ] SSH key-based authentication; password auth disabled
- [ ] Fail2Ban active and monitoring

### Application

- [ ] Rate limiting configured on all endpoint categories
- [ ] CSRF protection enabled on web admin panel
- [ ] Input validation via Form Requests on every API endpoint
- [ ] Mass assignment protection (`$fillable`) on all models
- [ ] File upload validation in place (type, size, MIME)
- [ ] Blade auto-escaping verified; no unescaped user output
- [ ] Security headers configured (CSP, HSTS, X-Frame-Options, etc.)

### Data & Logging

- [ ] Audit logging functional and tested
- [ ] Tenant isolation verified (no cross-tenant data leaks)
- [ ] GPS privacy policy documented and displayed in-app
- [ ] Backup system configured and verified with test restore
- [ ] Error monitoring configured (e.g., Sentry, Laravel Telescope)

### Dependencies

- [ ] All Composer dependencies up to date
- [ ] No known security vulnerabilities (`composer audit`)
- [ ] npm dependencies reviewed (`npm audit`)
- [ ] Security advisory monitoring enabled

### Access Control

- [ ] RBAC roles and permissions defined and tested
- [ ] Super Admin account secured with strong credentials
- [ ] Default user roles have least-privilege access
- [ ] Device registration and revocation flow tested
- [ ] Password reset flow tested (email and admin-initiated)
