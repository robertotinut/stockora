# 01. Stockora Production Readiness Report (Round 25 Updated)

**Prepared On:** 2026-11-15  
**Target Release:** First Controlled Real-World Beta Launch  
**Workspace Audited:** `g:\Project\stockora` (bound to `stockora_sandbox`)  
**Production Database Status:** Untouched (`PRODUCTION_DB_DIFFS=0` verified)

---

## Executive Determination

**Code Security & Application Determination:** **CODE READY FOR CONTROLLED BETA STAGING/DEPLOYMENT**  
**Overall Deployment Gate:** **`HOLD / NO-GO FOR LIVE CUTOVER` UNTIL EXTERNAL HOST, RUNTIME, CREDENTIAL & DNS PREREQUISITES ARE PROVISIONED**

Round 25 resolved all code-level BLOCKER, HIGH, and MEDIUM engineering findings (`SEC-01`, `SEC-02`, `SEC-03`, `SEC-05` password reset, `SEC-06`, `SEC-07`, `SEC-08`). All **91 automated PHPUnit tests (1,426 assertions)**, **2,514 EN / 2,514 ID translation checks (`2,801` static refs)**, **`config:cache` / `route:cache` checks**, and **`0` Composer security advisories** pass on `stockora_sandbox`.

> **Important Distinction:** Application source code readiness does **not** mean production infrastructure is ready. Hosting, TLS termination, production PHP-FPM (`>= 16M` uploads), production MySQL credentials + approval env flags, SMTP/DNS records, Linux symlinks, OS cron, and encrypted offsite backups remain external operational dependencies outside this sandbox workspace.

---

## Verified Application & Security Hardening Baseline vs External Host Dependencies

### Code & Sandbox Runtime Verified (Rounds 1–25)
| Component | Verified Value / Behavior | Verification Evidence |
|---|---|---|
| PHP & Extensions | `PHP 8.5.10` (`^8.3`), OPcache enabled, all `19` required extensions present | `php -v`, `php -m` |
| Framework & Caching | `Laravel 13.42.0`, `/up` health endpoint, clean `config:cache` & `route:cache` without direct `env()` failures | `php artisan config:cache`, `route:cache` |
| Database Safety Gate (`SEC-01`) | `DatabaseSafetyGuard` locks non-production to `stockora_sandbox`; production fails closed without explicit `STOCKORA_ALLOW_PRODUCTION_DB=true`, matching `STOCKORA_APPROVED_PRODUCTION_DATABASE`, and valid `STOCKORA_PRODUCTION_DB_APPROVAL`; destructive Artisan commands prohibited in prod | `Round25SecurityHardeningTest`, [DatabaseSafetyGuard.php](file:///g:/Project/stockora/app/Support/DatabaseSafetyGuard.php) |
| Rate Limiting (`SEC-02`) | Account/IP & actor-scoped `StockoraRateLimiters` across `/login`, `/platform/login`, `/register`, `/forgot-password`, `/reset-password`, `/setup-password*`, `/invitations/*`, `/subscription/payments*`, `/platform/payments*` with EN/ID `429` JSON & `errors/429.blade.php` | `Round25SecurityHardeningTest`, [StockoraRateLimiters.php](file:///g:/Project/stockora/app/Support/StockoraRateLimiters.php) |
| Proxy Trust & Sessions (`SEC-03`) | `App\Http\Middleware\TrustProxies` ignores untrusted `X-Forwarded-*` unless `STOCKORA_TRUSTED_PROXIES` matches; `config/session.php` defaults `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` in `production` while keeping local HTTP working | `Round25SecurityHardeningTest`, [TrustProxies.php](file:///g:/Project/stockora/app/Http/Middleware/TrustProxies.php) |
| Password Reset (`SEC-05`) | Throttled `/forgot-password` & `/reset-password/{token}`, 60-min single-use hashed token, generic non-enumerating responses, token-free error logging on mail failure, EN/ID `ResetPasswordNotification` | `Round25SecurityHardeningTest`, [PasswordResetController.php](file:///g:/Project/stockora/app/Http/Controllers/Auth/PasswordResetController.php) |
| Credentials & Membership (`SEC-06`–`08`) | Zero hardcoded sandbox DB credentials in `config/database.php`; `CreatePlatformAdminCommand` config-cache compatible; removed/deactivated member access strictly `403` while multi-company active memberships preserved | `Round25SecurityHardeningTest` |
| Security Dependencies | `composer audit --no-interaction` = `0` vulnerability advisories | `composer audit` |
| Localization & Full Test Suite | `2,514` EN / `2,514` ID keys matched (`17` files, `2,801` static refs); **91 tests passed (`1,426` assertions)** across Rounds 1–25 | `stockora:check-translations`, `php artisan test` |

### Remaining External Infrastructure, Runtime & Credential Dependencies
| Area | Sandbox Observed State | Required Production Action (External Owner) |
|---|---|---|
| Production DB & Approval Env (`SEC-01`) | Local `.env` safely stays `APP_ENV=local`, `DB_DATABASE=stockora_sandbox`; legacy `stockora` untouched | Provision isolated production MySQL 8.0+ DB + least-privilege users + set `.env` `STOCKORA_ALLOW_PRODUCTION_DB`, `STOCKORA_APPROVED_PRODUCTION_DATABASE`, `STOCKORA_PRODUCTION_DB_APPROVAL` during approved cutover |
| Domain, TLS & Trusted Proxy (`SEC-03`) | HTTP local `http://127.0.0.1:8000`; `STOCKORA_TRUSTED_PROXIES` unset | Provision domain DNS, TLS 1.2/1.3 cert, Nginx/LB upstream, and exact `STOCKORA_TRUSTED_PROXIES` IP/CIDR |
| PHP-FPM & Nginx Upload Limits (`SEC-04`) | Local Windows `php.ini` reports `upload_max_filesize=2M`, `post_max_size=8M` (< app's `5120 KB` cap) | Set target PHP-FPM `upload_max_filesize=16M`, `post_max_size=32M`, `max_execution_time=60`, `memory_limit=256M`, Nginx `client_max_body_size 16M` |
| Live SMTP & Email DNS (`SEC-05`) | Sandbox `MAIL_MAILER=log` / PHPUnit `array` | Configure transactional SMTP credentials (`smtp`), publish SPF/DKIM/DMARC records, smoke-test single internal operator mailbox |
| Linux Storage Symlink & Permissions | Windows `public/storage` junction (`is_link=NO`) | Run `php artisan storage:link` on Linux host; enforce `0750`/`0640` permissions (`www-data`) |
| OS Cron, Backups & Monitoring (`SEC-09`) | No cron daemon / offsite bucket in sandbox | Configure `* * * * * php artisan schedule:run`, encrypted daily `mysqldump` + `storage/app/{private,public}` backups + restore drill, `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=warning` |
| Future Async Email Enhancements | Welcome, Invitation & Password Reset active synchronously; Payment/Renewal emails in-app only | Separate future release: enable `Supervisor` queue workers (`ShouldQueue`) + payment confirmation/rejection emails + idempotent renewal reminder email ledger |

---

## Non-Secret `.env.production.example` Template

Updated for Round 25 at [`.env.production.example`](file:///g:/Project/stockora/.env.production.example) including `STOCKORA_ALLOW_PRODUCTION_DB`, `STOCKORA_APPROVED_PRODUCTION_DATABASE`, `STOCKORA_PRODUCTION_DB_APPROVAL`, and `STOCKORA_TRUSTED_PROXIES`. Active workspace `.env` remains strictly bound to `stockora_sandbox`.
