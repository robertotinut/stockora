# 02. Domain, HTTPS, Web Server & Deployment Checklist

All domain names, upstream hostnames, and filesystem paths below use placeholders (`app.example.com`, `/var/www/stockora`) until confirmed with the hosting operator.

---

## 1. Domain, DNS & HTTPS/TLS Requirements

- **Primary Application Domain:** Dedicated subdomain (e.g., `app.example.com`) with `A` / `AAAA` (or `CNAME`) pointing to the production load balancer / web server IP.
- **Control Plane Isolation (Recommended Defense-in-Depth):** While Stockora isolates Platform Super Admin routes at `/platform/*` behind `guard('platform')`, restrict `/platform/login` and `/platform/*` by WAF / IP allowlist or Zero-Trust Access rule during controlled beta whenever feasible.
- **SSL / TLS Policy:**
  - TLS `1.2` and `1.3` only (disable TLS 1.0/1.1 and weak ciphers).
  - HTTP (`:80`) permanently redirected (`301`) to HTTPS (`:443`), except ACME challenge paths if using Certbot HTTP-01.
  - Enable `Strict-Transport-Security: max-age=31536000; includeSubDomains` once HTTPS stability is verified.
  - Upstream TLS termination is handled securely via `App\Http\Middleware\TrustProxies` (wired in `bootstrap/app.php`), which ignores `X-Forwarded-*` headers by default (`STOCKORA_TRUSTED_PROXIES=`). Set `STOCKORA_TRUSTED_PROXIES` strictly to your Nginx/ALB/load-balancer IP or CIDR range (e.g., `127.0.0.1` for single-host Nginx or private VPC subnet) so HTTPS `APP_URL`, signed redirects, real client IPs for rate limiters, and `SESSION_SECURE_COOKIE=true` operate accurately without spoofing.
  - `config/session.php` automatically defaults `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` whenever `APP_ENV=production` (keeping local HTTP development functional without setting `Secure` cookies in `.env`), paired with `SESSION_HTTP_ONLY=true` and `SESSION_SAME_SITE=lax`.

---

## 2. Recommended Nginx + PHP-FPM Server Configuration

Web server document root **MUST** point strictly to `<release>/public` (never the repository root). Block hidden files, `.env`, `storage/`, and uploaded scripts cleanly.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name app.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name app.example.com;

    root /var/www/stockora/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    # Exceeds Stockora payment_proof_max_kb=5120 (5 MB) + multipart form overhead
    client_max_body_size 16M;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Block direct access to public/storage script execution just in case
    location ~* ^/storage/.*\.(php|phtml|phar|sh)$ {
        deny all;
    }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Required PHP-FPM Production INI Overrides (`php.ini` / `pool.d/stockora.conf`)
```ini
expose_php = Off
display_errors = Off
log_errors = On
upload_max_filesize = 16M
post_max_size = 32M
memory_limit = 256M
max_execution_time = 60
opcache.enable = 1
opcache.validate_timestamps = 0
```
*(If using Apache instead of Nginx, verify `mod_rewrite` is enabled with `AllowOverride All` on `public/` and keep `public/.htaccess` intact.)*

---

## 3. Directory Structure & Filesystem Permissions (Linux)

```text
/var/www/stockora/
├── shared/
│   ├── .env                          # chmod 0640, owned deploy:www-data
│   └── storage/
│       ├── app/
│       │   ├── private/              # Private manual payment proofs (chmod 0750)
│       │   └── public/               # Company logos exposed via public/storage symlink
│       ├── framework/{cache,sessions,views}
│       └── logs/                     # Daily log files (chmod 0750)
├── releases/<release-tag>/
└── current -> releases/<release-tag>
```

Set directory/file permissions on deploy:
- Code directories: `0755` directories, `0644` files (no `0777` anywhere).
- Persistent `shared/storage` and `bootstrap/cache`: owned by web/PHP user (`www-data`), writable (`0775` / `0750`), files `0640`.
- `.env`: `0640` (readable only by deploy/app runtime user, never world-readable).

---

## 4. Secure Zero/Low-Downtime Deployment Procedure

> **Prerequisite Gate (`SEC-01` Production Database Release Mechanism):**  
> Non-production environments (`local`, `testing`, `staging`) remain strictly locked to `stockora_sandbox` by `App\Support\DatabaseSafetyGuard`. Before booting `APP_ENV=production`, the authorized release operator **must intentionally configure all four fail-closed `.env` settings**:
> 1. `STOCKORA_ALLOW_PRODUCTION_DB=true`
> 2. `STOCKORA_APPROVED_PRODUCTION_DATABASE=<exact_production_db_name>` (must differ from `stockora_sandbox`)
> 3. `DB_DATABASE=<exact_production_db_name>` matching `STOCKORA_APPROVED_PRODUCTION_DATABASE` (on both `mysql` and `mysql_migrator` connections)
> 4. `STOCKORA_PRODUCTION_DB_APPROVAL=<signed-release-approval-token>` (minimum `12` characters, non-placeholder, e.g. `APPROVED-BETA-RELEASE-YYYYMMDD`)
> 
> Missing or mismatched configuration fails closed (`RuntimeException`). `AppServiceProvider` also invokes `DB::prohibitDestructiveCommands($this->app->isProduction())`, automatically blocking `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, and `db:seed` in production. **Note:** Historical Round 13–23 migrations (`database/migrations/2026_11_15_100001..700001`) retain sandbox reconciliation guards (`stockora_sandbox`); preserve existing production database schema/data or deploy an approved schema snapshot cleanly without altering production history blindly.

1. **Take Pre-Deploy Database Snapshot:** Run backup script and verify dump integrity before touching migrations.
2. **Pull Release & Install Production Dependencies:**
   ```bash
   git fetch --tags && git checkout <approved-release-tag>
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   ```
   *(If building Tailwind/Vite landing assets: `npm ci && npm run build`.)*
3. **Link Persistent `.env` and `storage/`:**
   ```bash
   ln -sfn /var/www/stockora/shared/.env .env
   rm -rf storage && ln -sfn /var/www/stockora/shared/storage storage
   php artisan storage:link
   ```
4. **Maintenance Mode & Pending Migrations (`mysql_migrator`):**
   ```bash
   php artisan down --render="errors::503" --retry=60
   php artisan migrate --force
   ```
   - Verify `php artisan migrate:status` shows all `24`+ migrations as `Ran`.
   - Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe`.
5. **Production Cache Optimization & Asset Check:**
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   php artisan stockora:check-translations
   ```
6. **Reload PHP-FPM & Queue Workers, Bring App Up:**
   ```bash
   ln -sfn /var/www/stockora/releases/<release-tag> /var/www/stockora/current
   sudo systemctl reload php8.5-fpm
   php artisan queue:restart   # if queue worker active
   php artisan up
   ```
7. **Post-Deploy Smoke Verification:**
   - `curl -fsS https://app.example.com/up` returns HTTP `200`.
   - Confirm `/login` and `/platform/login` return `200` over HTTPS with Secure/HttpOnly cookies and no debug output.
   - Verify static `/assets/css/stockora.css` and `/storage/...` resolve `200`.
   - Confirm `php artisan schedule:list` runs without exception.

---

## 5. Deployment Rollback Procedure

1. **Enable Maintenance Mode:** `php artisan down --retry=60`
2. **Database Rollback Rule:** Because Stockora's migrations (`Batches 2–8`) are additive and production data must never be lost:
   - If no incompatible schema change ran, skip DB rollback.
   - If a failed deploy included a new migration that breaks the prior release, restore the pre-deploy verified MySQL snapshot into staging/target per Section 6 Backup Recovery Guide rather than running destructive drops on live customer data.
3. **Restore Previous Symlink Release:**
   ```bash
   ln -sfn /var/www/stockora/releases/<previous-good-tag> /var/www/stockora/current
   cd /var/www/stockora/current
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   sudo systemctl reload php8.5-fpm
   php artisan queue:restart
   php artisan up
   ```
4. **Verify Health:** Re-test `/up`, `/login`, and `/platform/login`, and inspect `storage/logs/laravel-*.log`.
