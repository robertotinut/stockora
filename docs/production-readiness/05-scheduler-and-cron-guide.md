# 05. Scheduler, Queue & Background Jobs Guide

---

## 1. Audited Scheduler State (`routes/console.php`)

Observed directly via [routes/console.php](file:///g:/Project/stockora/routes/console.php) and `php artisan schedule:list`:

| Scheduled Task | Command / Signature | Current Frequency | Status & Behavior |
|---|---|---|---|
| **Subscription Expiration Scheduler** | `stockora:expire-companies` ([ExpireCompanyAccessCommand](file:///g:/Project/stockora/app/Console/Commands/ExpireCompanyAccessCommand.php)) | Hourly (`0 * * * *`) | **VERIFIED.** Calls `CompanyLifecycleService::syncExpiredCompanies()` to transition overdue active companies (`access_expires_at` passed) to `EXPIRED`. |
| **Renewal Reminder Email Scheduler** | *(None registered)* | N/A | **NOT IMPLEMENTED FOR EMAIL.** Reminder banners (`30d` / `7d` / `3d` / `Expired`) are evaluated synchronously in web requests by `RenewalReminderService::evaluateCompany()`. `logSandboxReminder()` writes to logs only. |

> **Important:** Do **not** add duplicate cron entries calling `php artisan stockora:expire-companies` directly alongside `schedule:run`. Laravel's single `schedule:run` cron invokes the hourly task automatically.

---

## 2. Production Cron Configuration (Linux Host)

Run Laravel's scheduler under the **application runtime user** (e.g., `www-data` or `deploy` — never mixed users owning conflicting log files):

```cron
# /etc/cron.d/stockora-scheduler
* * * * * www-data cd /var/www/stockora/current && /usr/bin/php artisan schedule:run >> /var/www/stockora/shared/storage/logs/scheduler.log 2>&1
```

### Verification Commands on Production Host
```bash
php artisan schedule:list
php artisan schedule:test   # dry-select expire-companies on staging/sandbox only
```

---

## 3. Multi-Server & Overlap Safeguard (When Scaling Beyond 1 Node)

Currently, `Schedule::command('stockora:expire-companies')->hourly()` runs without `withoutOverlapping()` or `onOneServer()`.
- **Single-Server Beta:** Safe as configured (atomic single cron daemon).
- **Multi-Server Later:** Update [routes/console.php](file:///g:/Project/stockora/routes/console.php#L11) to add `->withoutOverlapping()->onOneServer()` and verify `CACHE_STORE` uses a shared store (MySQL `database` cache or Redis).

---

## 4. Queue Requirements & Logging / Failure Handling

### Current Queue Architecture
- `config/queue.php` defaults to `QUEUE_CONNECTION=database` (`jobs`, `job_batches`, and `failed_jobs` tables exist in migration Batch 1).
- Currently, **no Notification or Job class implements `ShouldQueue`** (`CompanyInvitationNotification` and `DirectCustomerWelcomeNotification` include `Queueable`, but execute synchronously during the HTTP request).
- **Operational Impact During Beta:** If SMTP hangs or rejects a connection, customer invitation or onboarding requests experience request latency/error until queued.
- **Recommended Supervisor Worker Config (Once `ShouldQueue` Is Enabled or for Future Queued Jobs):**

```ini
; /etc/supervisor/conf.d/stockora-worker.conf
[program:stockora-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/stockora/current/artisan queue:work database --sleep=3 --tries=3 --backoff=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/stockora/shared/storage/logs/worker.log
stopwaitsecs=60
```

### Logging & Failure Handling
- Configure `LOG_CHANNEL=stack`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=30`, `LOG_LEVEL=warning` in production `.env`.
- Monitor:
  - `/var/www/stockora/shared/storage/logs/scheduler.log` (verify hourly `Expired N overdue customer company workspace(s).` execution).
  - `php artisan queue:failed` (inspect `failed_jobs` table if queued jobs are enabled).
  - `/platform/logs` in Stockora Control Plane (append-only `platform_audit_logs`).
