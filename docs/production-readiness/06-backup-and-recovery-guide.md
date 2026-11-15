# 06. Database & Private Storage Backup & Recovery Guide

> **Safety Notice:** Do not execute destructive recovery or database reset commands against `stockora_sandbox` or production. Verify all restores against a dedicated scratch/staging database first.

---

## 1. Backup Scope (Both Database AND Filesystem Are Required)

A valid Stockora recovery point requires **both**:
1. **MySQL 8.0+ Production Database** (all 44+ tables including `companies`, `users`, `company_user`, `products`, append-only `stock_movements`, purchasing/GRN tables, `subscription_payments`, `platform_audit_logs`).
2. **Persistent Storage Files** under `/var/www/stockora/shared/storage/app/`:
   - `storage/app/private/` — uploaded manual payment proof files referenced by `subscription_payments.payment_proof_path`.
   - `storage/app/public/` — tenant company logos referenced by `companies.logo_path`.

---

## 2. Automated Backup Strategy

### Dedicated Read-Only Backup MySQL Account
Create a restricted backup user instead of using application or migrator credentials:
```sql
CREATE USER 'stockora_backup'@'127.0.0.1' IDENTIFIED BY 'STRONG_RANDOM_BACKUP_PASSWORD';
GRANT SELECT, SHOW VIEW, LOCK TABLES, TRIGGER, PROCESS ON stockora_prod.* TO 'stockora_backup'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Daily Encrypted Backup Script Outline (`/usr/local/sbin/stockora-backup.sh`)
Store credentials in a protected `~/.my.cnf` (`chmod 0600`) for the backup runner user — **never** pass `--password=...` cleanly on command-line args visible to `ps`:

```bash
#!/usr/bin/env bash
set -euo pipefail

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORKDIR="/var/backups/stockora/tmp-${STAMP}"
OUTBOX="/var/backups/stockora/archive"
mkdir -p "${WORKDIR}" "${OUTBOX}"
chmod 0700 "${WORKDIR}" "${OUTBOX}"

# 1. Online consistent InnoDB snapshot (no table locks blocking reads/writes)
mysqldump --defaults-extra-file=/root/.stockora-backup.cnf \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 \
  stockora_prod > "${WORKDIR}/db-${STAMP}.sql"

# 2. Bundle storage attachments alongside matching DB point-in-time
tar -C /var/www/stockora/shared/storage/app -cf "${WORKDIR}/storage-${STAMP}.tar" private public

# 3. Compress & encrypt with asymmetric key (recipient public key on host; decryption key kept offline!)
tar -C "${WORKDIR}" -cf - "db-${STAMP}.sql" "storage-${STAMP}.tar" \
  | gzip -9 \
  | gpg --batch --yes --trust-model always --encrypt --recipient "backup-ops@example.com" \
    --output "${OUTBOX}/stockora-backup-${STAMP}.tar.gz.gpg"

sha256sum "${OUTBOX}/stockora-backup-${STAMP}.tar.gz.gpg" > "${OUTBOX}/stockora-backup-${STAMP}.tar.gz.gpg.sha256"
rm -rf "${WORKDIR}"

# 4. Sync offsite (e.g., private S3-compatible object storage with Object Lock / SSE)
# aws s3 cp "${OUTBOX}/" s3://stockora-backups-prod/daily/ --recursive --storage-class STANDARD_IA
```

---

## 3. Retention, Storage & Encryption Recommendations

| Tier | Schedule | Retention | Storage Location |
|---|---|---|---|
| **Daily Automated Snapshot** | Daily `02:00 WIB` (`19:00 UTC`) | `14` days local + `30` days offsite | Encrypted local staging + offsite S3/B2 bucket (private, versioning enabled) |
| **Weekly Consolidated Snapshot** | Sunday `02:30 WIB` | `12` weeks offsite | Offsite encrypted object storage |
| **Monthly Compliance Archive** | 1st of month | `12` months offsite | Offsite immutable / Object-Lock bucket |
| **Pre-Deploy Manual Snapshot** | Immediately before every deployment/migration | `30` days | Offsite + pre-deploy tag |

- **Encryption at Rest & In Transit:** Prefer **asymmetric encryption (`gpg` or `age`)** where only the public key resides on the production server and private decryption keys stay in the team's offline password vault/KMS. Enable bucket-level SSE (`AES-256` or KMS) and block public access completely.
- **Append-Only Log Complement:** Because `stock_movements` and `platform_audit_logs` are business audit trails, pre-deploy snapshots + daily offsite archives protect against accidental deletion or host compromise.

---

## 4. Restoration Procedure (Staging / Isolated Recovery Drill & Emergency Restore)

1. **Provision Clean Target Database First (Never Overwrite Blindly):**
   - For verification drills or forensics, restore into a separate empty database (`stockora_restore_verify`) — never touch live `stockora_prod` or `stockora_sandbox`.
2. **Verify Checksum & Decrypt Archive:**
   ```bash
   sha256sum -c stockora-backup-<STAMP>.tar.gz.gpg.sha256
   gpg --decrypt stockora-backup-<STAMP>.tar.gz.gpg | tar -xz -C /tmp/restore-<STAMP>
   ```
3. **If Restoring Production During Emergency:**
   - Enable `php artisan down --retry=60` on app servers and pause cron/queue workers.
   - Capture an emergency forensic dump of the broken state first (`emergency-pre-restore.sql`).
   - Restore SQL into freshly prepared DB (`mysql stockora_prod < db-<STAMP>.sql`).
   - Sync restored `private/` and `public/` directories back under `/var/www/stockora/shared/storage/app/` preserving `www-data` ownership and `0750`/`0640` permissions.
   - Verify `php artisan migrate:status`, `php artisan optimize:clear`, and config caches before running `php artisan up`.

---

## 5. Monthly Recovery Verification Checklist

Execute monthly (and once cleanly prior to controlled beta launch on a separate staging DB):
- [ ] Latest encrypted backup file downloaded cleanly from offsite storage.
- [ ] SHA-256 checksum matches and decrypts cleanly with recovery private key.
- [ ] SQL dump imports cleanly into temporary verification MySQL 8 instance with zero errors.
- [ ] Row counts verified across core tables (`companies`, `users`, `products`, `stock_movements`, `purchases`, `subscription_payments`, `platform_audit_logs`).
- [ ] Spot-check a restored `subscription_payments.payment_proof_path` exists in the restored `storage/app/private` tarball and matches `payment_proof_hash` (`sha256`).
- [ ] Document measured **RTO** (Time to Restore) and **RPO** (Time Since Last Snapshot) and destroy temporary verification DB after sign-off.
