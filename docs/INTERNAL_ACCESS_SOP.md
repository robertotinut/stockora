# Stockora Internal Workspace & Admin Access SOP

This operational guide defines how internal Stockora workspaces (for Stockora staff, demos, or internal operations) are provisioned in production and staging **without** code-level email bypasses or exposing internal plans to paying customers.

---

## 1. Identity Separation Model

Stockora maintains strict separation between two independent identity planes:

| Plane | Auth Guard | Model / Table | Login URL | Purpose |
| --- | --- | --- | --- | --- |
| **Platform Super Admin** | `platform` | `PlatformAdmin` (`platform_admins`) | `/platform/login` | Tenant onboarding, approvals, plan management, manual billing verification, audit logs |
| **Tenant / Customer User** | `web` | `User` (`users` + `company_user`) | `/login` | Company inventory, purchasing, warehousing, reports, team RBAC |

Tenant users (even workspace `owner`s) **never** bypass tenant isolation (`TenantContext` / `TenantScope`), RBAC (`company_user` role & permissions), or `Platform Super Admin` boundaries.

---

## 2. How Unrestricted Internal Workspaces Work (`INTERNAL` Plan)

Instead of hardcoding email exceptions in application code, Stockora uses an explicit **non-public internal subscription plan**:

- **Plan Code:** `INTERNAL` (`is_internal = true`, `is_active = true`, `is_recommended = false`)
- **Tier:** `ENTERPRISE` (`price = 0`, `billing_interval = CUSTOM`)
- **Modules:** All 17 active boolean modules set to enabled (`'1'`)
- **Quotas:** All 6 measurable resource limits set to `null` (**Unlimited**)
- **Public Isolation:** `is_internal = true` plans are excluded from:
  - Public `/plans` comparison matrix
  - Customer `/subscription` upgrade options & payment quote calculator (`SubscriptionPaymentService`)
  - Default public onboarding tier resolution (`SubscriptionPlanService::resolveDefaultPlanByTier()`)

---

## 3. Production Setup Steps (Initial Go-Live)

Run these steps cleanly via CLI + Platform Super Admin UI during initial production setup:

1. **Run migrations and idempotent plan seeding (using the configured migrator connection if least-privilege runtime DB grants apply):**
   ```sh
   php artisan migrate --database=mysql_migrator --force
   php artisan db:seed --class=SubscriptionPlanSeeder --force
   ```
2. **Create Platform Super Admin account (separate from tenant users):**
   ```sh
   php artisan stockora:platform-admin:create --name="Platform Operator" --email="<operator-email>"
   ```
   Do **not** reuse customer passwords or seed default production passwords.
3. **Provision or locate the internal company workspace:**
   - Sign in to `/platform/login` as the Platform Super Admin.
   - Create or open the internal workspace (`Customers` -> select company).
   - Ensure lifecycle status is `ACTIVE` with no forced expiry (`access_expires_at = null` unless time-boxed).
   - Under **Assign Subscription Plan**, select **`Internal Unlimited (INTERNAL)`** (`[Internal]` badge) and save.
   - Verify an immutable audit log entry (`COMPANY_PLAN_ASSIGNED`) is recorded.
4. **Never leave `FREE` assigned to internal staff workspaces:**
   - Any ordinary self-registered customer receives `FREE` (`PENDING_ACTIVATION` -> manually reviewed & activated to `FREE` / `STARTER` / `PROFESSIONAL` / `ENTERPRISE`).
   - Internal workspaces operate exclusively via explicit Platform Super Admin assignment to `INTERNAL`.

---

## 4. Verification Checklist After Assigning `INTERNAL`

1. Public `/plans` does **not** show `INTERNAL` when logged out or logged in as any customer.
2. Ordinary `FREE` and `STARTER` customer tenants continue to enforce their configured feature locks and numerical quotas.
3. Customer billing/quote endpoints reject `INTERNAL` with a domain error if forged.
4. Internal workspace users still respect RBAC roles (`owner`, `admin`, `manager`, `staff`, `viewer`) and remain strictly scoped to their own company data (`TenantScope`).
