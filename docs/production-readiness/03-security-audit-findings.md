# 03. Security Audit Findings (Classified by Severity — Round 25 Updated)

Originally audited in Round 24 (`2026-11-15`) and hardened at the code level in **Round 25** (`Stockora` on `stockora_sandbox`). Auth, multi-tenant isolation, RBAC, and separate Platform Super Admin guards were preserved cleanly without architectural rewrites.

---

## Findings Summary

| ID | Severity | Area | Summary | Round 25 Status |
|---|---|---|---|---|
| **SEC-01** | **BLOCKER** | Boot & Inventory Guard (`AppServiceProvider`, `InventoryService`) | Previously hardcoded `stockora_sandbox` boot/runtime abort | **RESOLVED IN CODE (Host Env Gate Required):** Replaced with fail-closed `DatabaseSafetyGuard` + `DB::prohibitDestructiveCommands($this->app->isProduction())` |
| **SEC-02** | **HIGH** | Rate Limiting | Auth, register, setup-password, invitations, and payment routes unthrottled | **RESOLVED IN CODE:** Added `StockoraRateLimiters` across all sensitive endpoints + EN/ID `429` JSON & `errors/429.blade.php` |
| **SEC-03** | **HIGH** | HTTPS / Proxy & Cookies | Missing `TrustProxies` middleware; `SESSION_ENCRYPT=false` & `SESSION_SECURE_COOKIE=NULL` defaults | **RESOLVED IN CODE (Host Proxy IP & TLS Required):** Added `App\Http\Middleware\TrustProxies` via `STOCKORA_TRUSTED_PROXIES` + hardened `config/session.php` production defaults |
| **SEC-04** | **HIGH** | Upload Limit Mismatch | Local Windows PHP `upload_max_filesize=2M` < `5120 KB` proof validation | **APP VALIDATED / HOST INI REQUIRED:** Server-side `mimes`, `mimetypes`, `max:5120`, private `local` disk, and `nosniff` verified by tests; target host must set PHP `upload_max_filesize >= 16M`, `post_max_size >= 16M` (recommended `32M`), Nginx `16M` |
| **SEC-05** | **HIGH** | Password Recovery & Email Auth Gaps | Missing self-service `/forgot-password`; sync notifications | **PASSWORD RESET RESOLVED IN CODE / SMTP & ASYNC BILLING FUTURE SCOPE:** Implemented throttled `/forgot-password` & `/reset-password/{token}` (`ResetPasswordNotification`); live SMTP + SPF/DKIM/DMARC external; payment & scheduled renewal reminder emails documented as future queued scope |
| **SEC-06** | **MEDIUM** | Hardcoded Sandbox Fallbacks | `config/database.php` embedded sandbox DB names & passwords | **RESOLVED IN CODE:** Removed hardcoded sandbox DB name, usernames, and fallback passwords (`env(..., '')`) |
| **SEC-07** | **MEDIUM** | Non-Config `env()` Usage | `CreatePlatformAdminCommand` called `env()` directly outside `config/` | **RESOLVED IN CODE:** Routed `STOCKORA_PLATFORM_ADMIN_*` through `config('stockora.platform_bootstrap.*')` and verified `php artisan config:cache` compatibility |
| **SEC-08** | **MEDIUM** | Removed Membership Access Scope | Verify removed/deactivated member blocking & cross-company preservation | **VERIFIED & REGRESSION TESTED:** Confirmed immediate `403` revocation from removed tenant routes/switch/selector while preserving active multi-company workspaces |
| **SEC-09** | **LOW** | Logging & Error Pages | `LOG_STACK=single`, `LOG_LEVEL=debug`; minimal error views | **PARTIALLY ADDRESSED:** Added branded EN/ID `429.blade.php`; target `.env` must enforce `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=warning` (`404`/`500`/`503` optional) |
| **SEC-10** | **LOW** | External Font CDN & Windows Symlink | Auth/error views load `fonts.googleapis.com`; Windows `public/storage` | **HOST DEPLOYMENT ITEM:** Allow `fonts.googleapis.com` / `fonts.gstatic.com` in CSP and run `php artisan storage:link` on Linux host |
| **SEC-11** | **INFORMATIONAL** | Positive Security Controls | Private `local` proofs + `nosniff`, separate `platform` guard, CSRF intact, `composer audit` (`0` CVEs) | **MAINTAINED & VERIFIED** |

---

## Detailed Status & Technical Verification

### 1. [SEC-01 — RESOLVED IN CODE] Fail-Closed Production Database Guard
- **Implementation:** Created [DatabaseSafetyGuard.php](file:///g:/Project/stockora/app/Support/DatabaseSafetyGuard.php), wired into [AppServiceProvider.php](file:///g:/Project/stockora/app/Providers/AppServiceProvider.php) and [InventoryService.php](file:///g:/Project/stockora/app/Services/InventoryService.php), plus `DB::prohibitDestructiveCommands($this->app->isProduction())`.
- **Fail-Closed Guarantee:**
  - Non-production (`local`, `testing`, `staging`) strictly refuses booting or querying anything except `stockora_sandbox` (even if production opt-in env flags are present).
  - Production (`APP_ENV=production`) fails closed unless `STOCKORA_ALLOW_PRODUCTION_DB=true`, `STOCKORA_APPROVED_PRODUCTION_DATABASE` matches `DB_DATABASE` (and `mysql_migrator`) and differs from `stockora_sandbox`, and `STOCKORA_PRODUCTION_DB_APPROVAL` contains a valid non-placeholder release token (`>= 12` chars).
  - Destructive Artisan commands (`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `db:seed`) are blocked when `app()->isProduction()`.
- **Remaining Operational Dependency:** Production host `.env` must supply the explicit opt-in variables only during an approved release window (`stockora` legacy schema remains untouched).

### 2. [SEC-02 — RESOLVED IN CODE] Authentication & Sensitive Route Rate Limiting
- **Implementation:** Created [StockoraRateLimiters.php](file:///g:/Project/stockora/app/Support/StockoraRateLimiters.php) and wired named limiters in [routes/web.php](file:///g:/Project/stockora/routes/web.php):
  - `tenant-login` (`5/min` per email+IP, `25/min` per IP) on `POST /login`
  - `platform-login` (`5/min` per email+IP, `15/min` per IP) on `POST /platform/login`
  - `register` (`5/min` per IP) on `POST /register`
  - `password-setup` (`5/min` per email+IP, `15/min` per IP) & `password-setup-view` (`20/min` per IP)
  - `password-reset-request` (`3/min` per email+IP, `10/min` per IP) & `password-reset-submit` (`5/min` per email+IP, `15/min` per IP)
  - `invitation-view` (`20/min` per IP) & `invitation-accept` (`10/min` per user+IP)
  - `subscription-payment-submit` (`8/min` per user+IP) on `POST /subscription/payments`
  - `customer-payment-action` (`10/min` per user+IP) on `POST /subscription/payments/{paymentId}/cancel`
  - `platform-payment-actions` (`20/min` per admin+IP) on `/platform/payments` store/verify/reject/cancel/apply
- **Responses:** Returns localized EN/ID HTTP `429` JSON (for API/JSON callers) or Cassie-styled [429.blade.php](file:///g:/Project/stockora/resources/views/errors/429.blade.php) with `Retry-After` headers.

### 3. [SEC-03 — RESOLVED IN CODE] Reverse Proxy & Session Hardening
- **Implementation:**
  - Replaced framework default `TrustProxies` in [bootstrap/app.php](file:///g:/Project/stockora/bootstrap/app.php) with [TrustProxies.php](file:///g:/Project/stockora/app/Http/Middleware/TrustProxies.php) reading `config('stockora.trusted_proxies')` (`STOCKORA_TRUSTED_PROXIES`, defaulting to `[]` so arbitrary client `X-Forwarded-*` headers are ignored).
  - Updated [config/session.php](file:///g:/Project/stockora/config/session.php) so `SESSION_ENCRYPT` and `SESSION_SECURE_COOKIE` default to `true` when `APP_ENV=production` while keeping local HTTP development (`APP_ENV=local`) functional.

### 4. [SEC-04 — APP VALIDATED / HOST RUNTIME INI PENDING] Upload Compatibility
- **Application Verified:** `CustomerSubscriptionController::storePayment` + `SubscriptionPaymentService::storeProofFile()` authoritatively enforce `mimes:jpg,jpeg,png,webp,pdf`, `mimetypes:...`, and `max:5120` (5 MB) onto private `local` storage with `X-Content-Type-Options: nosniff` authorized downloads.
- **Host Requirement:** Configure PHP-FPM `upload_max_filesize >= 16M`, `post_max_size >= 16M` (recommended `32M`), `max_execution_time >= 60`, `memory_limit >= 256M`, and Nginx `client_max_body_size 16M`.

### 5. [SEC-05 — SELF-SERVICE RESET COMPLETE / SMTP INFRA & FUTURE EMAIL SCOPE]
- **Implementation:** Added [PasswordResetController.php](file:///g:/Project/stockora/app/Http/Controllers/Auth/PasswordResetController.php), [ResetPasswordNotification.php](file:///g:/Project/stockora/app/Notifications/ResetPasswordNotification.php), [forgot-password.blade.php](file:///g:/Project/stockora/resources/views/auth/forgot-password.blade.php), [reset-password.blade.php](file:///g:/Project/stockora/resources/views/auth/reset-password.blade.php), and a subtle link in [login.blade.php](file:///g:/Project/stockora/resources/views/auth/login.blade.php).
- **Security Controls:** Generic non-enumerating confirmation message (`auth.password_reset_link_sent_generic`), 60-minute HMAC-hashed single-use token via `users` broker, graceful `Throwable` catch & token-free logging on SMTP errors, EN/ID locale support via `User::preferredLocale()`, `remember_token` rotation, and DB session invalidation.
- **Documented Future Scope:** Payment verification/rejection emails and automated scheduled renewal reminder emails require queue worker deployment (`ShouldQueue`) and an idempotent reminder dispatch ledger and are therefore excluded from quick synchronous implementation.

### 6. [SEC-06, SEC-07, SEC-08 — RESOLVED & TESTED]
- Removed hardcoded sandbox credentials from [database.php](file:///g:/Project/stockora/config/database.php).
- Switched [CreatePlatformAdminCommand.php](file:///g:/Project/stockora/app/Console/Commands/CreatePlatformAdminCommand.php) to `config('stockora.platform_bootstrap.*')` inside [stockora.php](file:///g:/Project/stockora/config/stockora.php), verified with `php artisan config:cache`.
- Verified removed/deactivated member revocation (`403`, selector omission, switch denial) while keeping valid multi-company workspaces accessible in `Round25SecurityHardeningTest`.
