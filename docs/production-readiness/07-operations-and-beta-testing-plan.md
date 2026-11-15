# 07. Operational Readiness SOPs & Controlled Beta Testing Plan

All procedures below correspond directly to implemented routes, Artisan commands, and UI workflows verified in Stockora (`g:\Project\stockora`). Never create test users or activate live customers in production prior to approved go-live.

---

## Part A — Operational Readiness Standard Procedures

### 1. Creating the First Production Platform Super Admin
Run interactively on the production host via Artisan CLI (never commit admin credentials):
```bash
cd /var/www/stockora/current
php artisan stockora:platform-admin:create
```
Or supply flags explicitly via CLI (`CreatePlatformAdminCommand` reads `config('stockora.platform_bootstrap.*')`, so `config:cache` compatibility is verified cleanly):
```bash
php artisan stockora:platform-admin:create \
  --name="Primary Platform Operator" \
  --email="operator@example.com" \
  --password="STRONG_MIN_10_CHAR_ALPHANUM_PASSWORD"
```
Verify login exclusively at `https://<domain>/platform/login`.

### 2. Customer Onboarding (Hybrid Model)
- **Self-Service Website Onboarding:** Customer registers at `/register` → creates workspace at `/onboarding/company` → workspace starts in `PENDING_ACTIVATION` (`is_active = 0`) and lands on `/company/access-status`.
- **Direct Platform Onboarding:** Platform Super Admin opens `/platform/customers/create` → selects plan & initial dates → creates company (`ACTIVE`) + Owner. If SMTP is enabled, Stockora sends `DirectCustomerWelcomeNotification` (`/setup-password/{token}`); if SMTP is not yet live or for operator-assisted onboarding, copy the single-use setup URL shown cleanly once (not stored in audit logs) and deliver securely.

### 3. Manual Payment Verification & Subscription Activation / Renewal / Plan Change
Stockora intentionally enforces a **Two-Phase Manual Control**:
1. **Review (`/platform/payments` → `/platform/payments/{id}`):**
   - Inspect transfer date, channel, customer reference, payer name, quoted IDR amount, and private proof attachment (`/platform/payments/{id}/proof`).
   - **Action A — Verify (`VERIFIED`):** Confirm bank/QRIS mutation matches quoted amount, add verification/internal note (`admin_note` is hidden from customer UI), check verification confirmation, and click **Verify Payment**. *(Does NOT activate or extend access yet.)*
   - **Action B — Apply (`APPLIED`):** Review the **Subscription Application Preview** panel showing exact `From Status → Target Status` and resulting `access_expires_at`:
     - `PENDING_ACTIVATION` → transitions to `ACTIVE` (`ACTIVATE`).
     - `EXPIRED` → reactivates cleanly to `ACTIVE` (`REACTIVATE_EXPIRED`).
     - `ACTIVE` renewal → extends from existing future `access_expires_at` (`EXTEND_CURRENT`) without shortening paid time.
     - `ACTIVE` plan change/upgrade → switches `subscription_plan_id` / `access_type` and defaults to preserving existing expiration (`KEEP_EXISTING`) unless admin selects custom extension.
     - Confirm checkbox and click **Apply Verified Payment to Subscription**.
2. **Offline Admin Payment (`/platform/payments/create`):**
   - Record cash/direct transfers received outside the portal; if marked confirmed received, creates as `VERIFIED` and still requires explicit Step-2 Apply.

### 4. Customer Suspension, Reactivation & Expired Workspaces
- **Suspend (`/platform/customers/{company}` → Suspend):** Immediately blocks tenant operations (`account_status = SUSPENDED`, `is_active = 0`). **Note:** Verified payments CANNOT auto-reactivate a `SUSPENDED` or `CANCELLED` workspace; admin must explicitly Reactivate on `/platform/customers/{company}` first before applying a payment.
- **Expired Workspaces:** Hourly cron `stockora:expire-companies` marks overdue workspaces `EXPIRED`. Customers retain access to `/company/access-status` and `/subscription*` (for `owner`/`admin` billing roles) to submit renewal confirmation; once verified & applied by admin, access restores immediately.

### 5. Handling Failed Payments, Rejected Proofs & Support Requests
- **Unreadable / Unmatched Proof:** Reject on `/platform/payments/{id}` with a clear customer-facing `rejection_reason` (visible on `/subscription/payments/{id}`). Workspace access remains unchanged. Guide customer to submit a replacement confirmation with valid proof.
- **Mistaken Customer Submission:** Customer (`owner`/`admin` billing role) or Platform Admin can cancel a `PENDING` payment cleanly before verification.
- **Forgotten Beta Passwords:** Once production SMTP is live, guide users to **Forgot password?** on `/login` (`/forgot-password` → throttled, 60-minute single-use link via `ResetPasswordNotification` without leaking email existence). Until SMTP DNS is live, support staff verifies requester identity and issues a manual `/setup-password/{token}` link under audit review.

---

## Part B — Controlled Beta Testing Plan & Checklist

Target Scope: `3–5` invited beta customer companies across Distributor, Wholesale, Printing/Small Mfg, and Multi-Warehouse Retail cohorts.

| # | Area | Concrete Beta Scenarios | Expected Result | Pass/Fail Criteria |
|---|---|---|---|---|
| **1** | **Registration & Onboarding** | Self-register at `/register`, create company at `/onboarding/company`; also test admin direct create `/platform/customers/create` + `/setup-password/{token}`. | Self-registered company starts `PENDING_ACTIVATION` blocked at `/company/access-status` (with Billing CTA); direct onboarded owner sets password via single-use link cleanly. | **PASS** if no pending company accesses `/dashboard` or inventory routes; single-use token invalidates after use. |
| **2** | **Workspace Isolation** | Create Company A & Company B; test cross-tenant IDs in URLs (`/products/{id}`, `/purchases/{id}`, `/subscription/payments/{id}/proof`) and `X-Company-Id` header tampering. | All cross-tenant queries and proof downloads abort with `403`/`404` and zero data leakage; multi-company user switches cleanly via `/company/select`. | **PASS** if zero cross-company rows or proof files load across sessions. |
| **3** | **RBAC & Team Invitations** | Owner invites Admin, Manager, Staff, Viewer via `/team/invitations`, customizes permissions on `/team/permissions`, toggles member active status. | Role defaults enforced on backend (e.g., Staff/Viewer blocked `403` from `/subscription` & team deletes); invitation link works once and respects `max_users` quota. | **PASS** if backend denies unauthorized `POST`/`PUT`/`DELETE`/`GET` calls cleanly. |
| **4** | **Inventory & Stock Movements** | Create Warehouses, Units + Conversions, Categories, Products; perform Transfer (`/transfers`), Issue (`/issues`), Draft/Post Adjustment (`/adjustments`) + Reversals. | Moving average cost & `product_warehouses` stock update atomically; `stock_movements` remains append-only; reversals emit offsetting entries cleanly. | **PASS** if ledger sum equals warehouse stock (`product_warehouses.stock`) with zero negative drift. |
| **5** | **Purchasing & Web GRN** | Create Supplier + PO (`/purchases`) → Confirm → Receive partial & full GRN (`/grns/create`) → Record PO Payment status → Test GRN Reversal. | Weighted moving average cost updates on GRN receipt; cannot over-receive beyond ordered quantity; reversed GRN restores remaining receivable qty cleanly. | **PASS** if PO status (`PARTIAL`/`RECEIVED`), MAC valuation, and ledger stay 100% reconciled. |
| **6** | **Subscription Limits & Feature Locks** | Assign Starter plan (`max_users=5`, `max_warehouses=2`, `max_products=500`, `max_suppliers=50`, `max_monthly_purchases=150`, `max_monthly_stock_movements=2000`) and test hitting a quota/feature lock (e.g., Free plan `max_warehouses=1`, `max_users=2`). | Exceeding numeric quota returns user-friendly limit message with upgrade link; locked features render `feature-locked` (`403`) cleanly on backend routes & sidebar. | **PASS** if all `6` measurable limits in `SubscriptionCatalog::LIMIT_KEYS` block creation at exact ceiling. |
| **7** | **Manual Payments & Renewal** | Customer submits `STARTER` / `PROFESSIONAL` proof (`PNG`/`JPG`/`PDF` up to 5 MB) → Admin verifies (`VERIFIED`) → Previews expiry → Applies (`APPLIED`) → Test Reject & Cancel → Verify `<7d` expiring banner on `/dashboard` & hourly expiration command. | Private proof downloads `200` for owner/admin and `403` for non-members/Staff; verify alone never activates; `EXTEND_CURRENT` preserves remaining days; suspended company cannot be auto-reactivated via payment. | **PASS** if zero payment creates false access and all transitions appear in `platform_audit_logs`. |
| **8** | **EN / ID Localization** | Switch header language `EN` ↔ `ID` across Customer Workspace, Billing Checkout, Access Status, and Platform Control Plane. | All labels, validation errors, emails/notifications (if active), and currency/date formats adapt without raw `payments.*`/`platform.*`/`subscription.*` keys. | **PASS** if 0 untranslated keys observed (`stockora:check-translations` = OK). |
| **9** | **Desktop & Mobile Usability** | Exercise primary workflows at Desktop `1440x900` and Mobile `390x844` (sidebar drawer, forms, filter bars, modal confirmations, responsive tables). | Zero horizontal page overflow (`scrollWidth <= innerWidth`), clickable controls unclipped, clean alerts & badges. | **PASS** if zero layout overlap, horizontal page overflow, or console JS errors occur. |
| **10** | **Security Controls & Abuse Defense** | Trigger >5 invalid `/login` attempts, test `/forgot-password` EN/ID flow, upload >5 MB or spoofed `.php` proof, test removed member access across single/multi-company memberships. | `429` throttle blocks flood with EN/ID retry message & clears after cooldown; reset tokens expire & work once; invalid proofs rejected cleanly; removed member blocked `403` from former tenant while keeping active other tenant. | **PASS** if Round 25 automated security regression suite passes cleanly. |
