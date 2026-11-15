# 08. Go-Live Readiness Scorecard & Release Gate (Round 25)

**Assessment Date:** 2026-11-15  
**Environment Audited:** `g:\Project\stockora` on `stockora_sandbox` (Production `stockora` untouched — `PRODUCTION_DB_DIFFS=0`)

---

## Domain Readiness Matrix

| Domain | Verification Basis | Round 25 Status | Summary & Remaining Dependencies |
|---|---|---|---|
| **1. Application Core, Multi-Tenancy, RBAC & Billing** | Code + PHPUnit (`91` tests / `1,426` assertions) + Browser QA (Rounds 16–23) | **CODE READY** | Core inventory, purchasing/GRN, RBAC, lifecycle, quotas, and two-phase manual payment confirmation pass cleanly. |
| **2. Database Guard & Migration Safety (`SEC-01`, `SEC-06`)** | `DatabaseSafetyGuard` + `DB::prohibitDestructiveCommands` + `config/database.php` + Round 25 tests | **CODE READY (Host DB & Env Gate Pending)** | Local/testing locked strictly to `stockora_sandbox`; production fails closed until intentional approval flags match; sandbox fallback creds removed; destructive commands blocked in prod. Needs target MySQL host, credentials, & release sign-off. |
| **3. Security Hardening & Rate Limiting (`SEC-02`, `SEC-03`, `SEC-07`, `SEC-08`)** | `StockoraRateLimiters`, `TrustProxies`, `config/session.php`, `CreatePlatformAdminCommand`, `config:cache`, Round 25 tests | **CODE READY (Host Proxy IP & TLS Pending)** | Throttling active across tenant/platform logins, signup, password setup/reset, invitations, & payment endpoints with EN/ID `429` views. Proxy trust defaults to none (`STOCKORA_TRUSTED_PROXIES` needed on host); prod secure/encrypted session cookies defaulted. |
| **4. Upload Compatibility & Private Proofs (`SEC-04`)** | `CustomerSubscriptionController`, `SubscriptionPaymentService`, Round 25 upload tests | **APP VALIDATED (Host PHP/Nginx INI Required)** | App validates `PNG/JPG/WEBP/PDF` <= `5120 KB`, rejects spoofed MIME/extensions, stores privately on `local` disk with `nosniff`. Target PHP-FPM (`>= 16M`) & Nginx (`16M`) INI overrides still required on server. |
| **5. Password Recovery & Customer Email (`SEC-05`)** | `PasswordResetController`, `ResetPasswordNotification`, `04-smtp-and-email-guide.md`, Round 25 tests | **PASSWORD RESET CODE READY (SMTP Infra + Async Billing Deferred)** | Welcome, invitation, & throttled EN/ID `/forgot-password` ready without enumeration or token leakage. External SMTP creds + SPF/DKIM/DMARC still needed; payment decision & scheduled renewal reminder emails documented cleanly as future queued-worker milestone. |
| **6. Scheduler, Cron, Logging & Storage ACLs (`SEC-09`, `SEC-10`)** | `routes/console.php`, `schedule:list`, `.env.production.example` | **HOST SETUP REQUIRED** | Hourly `stockora:expire-companies` verified; production host still needs OS crontab, `php artisan storage:link`, `0750`/`0640` permissions, `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=warning`. |
| **7. Backup & Disaster Recovery Strategy** | `06-backup-and-recovery-guide.md` | **PLAN READY (Needs Target Host & Restore Drill)** | Asymmetric encrypted `mysqldump` + `storage/app/{private,public}` offsite backup & recovery procedure documented; requires host cron + S3/B2 bucket + verified staging restore drill. |

---

## Round 25 Release Gate Decision

### **Decision: Application Code `APPROVED` — Production Cutover `HELD PENDING EXTERNAL INFRASTRUCTURE & CREDENTIALS`**

Code-level security hardening (`SEC-01` through `SEC-08`) is complete and verified across **91 tests (1,426 assertions)** on `stockora_sandbox`. Do **not** deploy live until every external prerequisite below is executed on the production target:

1. **Infrastructure & TLS (`SEC-03`):** Provision dedicated Linux host + domain DNS + TLS `1.2/1.3` certificate, point Nginx root strictly to `/public`, configure `STOCKORA_TRUSTED_PROXIES` to the real upstream proxy IP/CIDR, and apply HSTS + Nginx edge rate limits.
2. **PHP & Nginx Upload Sizing (`SEC-04`):** Configure PHP-FPM `upload_max_filesize >= 16M`, `post_max_size >= 16M` (recommended `32M`), `max_execution_time >= 60`, `memory_limit >= 256M`, and Nginx `client_max_body_size 16M`.
3. **Production Database & Intentional Approval Gate (`SEC-01`, `SEC-06`):** Create dedicated `stockora_prod` schema + least-privilege `stockora_app_prod` / `stockora_migrator_prod` / `stockora_backup` accounts, verify pre-cutover snapshot, and populate `.env` from [`.env.production.example`](file:///g:/Project/stockora/.env.production.example) setting `STOCKORA_ALLOW_PRODUCTION_DB=true`, `STOCKORA_APPROVED_PRODUCTION_DATABASE`, and signed `STOCKORA_PRODUCTION_DB_APPROVAL` (`>= 12` chars).
4. **SMTP, DNS & Closed-Beta Scope (`SEC-05`):** Provision transactional SMTP credentials + SPF/DKIM/DMARC DNS records, run one internal operator-only smoke email test (`/forgot-password` / Welcome / Invitation), and note that automated payment/renewal reminder emails remain an explicit post-queue future enhancement.
5. **Cron, Filesystem, Caches & Bootstrap:** Run `php artisan storage:link`, apply `0750`/`0640` permissions, run `php artisan config:cache && route:cache && view:cache`, install `* * * * * php artisan schedule:run`, execute backup + scratch restore drill, and bootstrap initial Platform Super Admin via `php artisan stockora:platform-admin:create`.
