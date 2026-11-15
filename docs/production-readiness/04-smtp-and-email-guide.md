# 04. SMTP & Email Delivery Configuration Guide

Currently Stockora runs with `MAIL_MAILER=log` in development so no accidental outbound emails occur. Never test production SMTP against real customer email addresses during prep.

---

## 1. Current Email Implementation Inventory (Code-Verified)

| Workflow | Current Code Status | Details / Gap Assessment |
|---|---|---|
| **Direct Admin Customer Onboarding & Password Setup** | **IMPLEMENTED** | `PlatformOnboardingService` creates `Password::broker('users')` token + dispatches `DirectCustomerWelcomeNotification` (`/setup-password/{token}`) **only when `hasRealMailTransport()` is true** (mailer !== `log`). Runs synchronously unless queued. |
| **Team Member Invitations** | **IMPLEMENTED** | `InvitationService` dispatches `CompanyInvitationNotification` (`/invitations/{token}`) **only when `hasRealMailTransport()` is true**. Explicitly skips logging raw tokens to `laravel.log`. |
| **Customer Self-Registration Onboarding Confirmation** | **NOT IMPLEMENTED (In-App Active)** | `RegisterController` & `OnboardingController` rely on immediate in-app redirect (`/company/access-status`). Optional welcome email left out of MVP beta scope unless product policy requests it later. |
| **Self-Service Password Reset (`/forgot-password`)** | **IMPLEMENTED (Round 25)** | Throttled `/forgot-password` & `/reset-password/{token}` using Laravel `users` broker (`60`-min expiry, hashed single-use token, `remember_token` rotation, DB session purge, generic non-enumerating status flash, safe error logging without token leakage, and EN/ID `ResetPasswordNotification`). |
| **Payment Verification / Rejection Emails** | **FUTURE SCOPE (Deferred Safely)** | Currently surfaced immediately in-app via `/subscription/payments/{id}` (`VERIFIED` / `REJECTED` + customer-visible `rejection_reason`). Adding outbound transactional billing emails cleanly requires queued workers (`ShouldQueue`), billing recipient selection (`owner`/`admin`), delivery audit logs, and failure retries rather than blocking admin HTTP actions inline. |
| **Subscription Renewal Reminder Emails** | **FUTURE SCOPE (Deferred Safely)** | Currently evaluated live in-app via `RenewalReminderService::evaluateCompany()` (`30d/7d/3d/Expired` dashboard & billing banners; `logSandboxReminder()` logs in sandbox). Automating scheduled emails requires an idempotent per-cycle reminder dispatch ledger (to prevent duplicate emails from hourly `stockora:expire-companies` cron) and `ShouldQueue` workers. |

---

## 2. Production SMTP Environment Configuration

Set the following in production `.env` (using a dedicated transactional provider such as Postmark, Amazon SES, Resend, Mailgun, or Brevo):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.example-provider.com
MAIL_PORT=465
MAIL_USERNAME=CHANGE_ME_SMTP_USER
MAIL_PASSWORD=CHANGE_ME_SMTP_SECRET
MAIL_FROM_ADDRESS="noreply@mail.example.com"
MAIL_FROM_NAME="Stockora"
MAIL_EHLO_DOMAIN=app.example.com
```
*(Or `MAIL_SCHEME=smtp` on TLS port `587` if required by provider. Remember `InvitationService::hasRealMailTransport()` and `PlatformOnboardingService::hasRealMailTransport()` activate email sending as soon as `MAIL_MAILER` is no longer `log`.)*

---

## 3. Required Domain DNS Authentication Records

Use a dedicated transactional subdomain (e.g., `mail.example.com` or `mg.example.com`) to protect primary corporate domain reputation:

1. **SPF (`TXT` record on sending domain/subdomain):**
   ```text
   v=spf1 include:<provider-spf-domain> ~all
   ```
   *(Ensure strictly one SPF record per DNS hostname.)*
2. **DKIM (`TXT` or `CNAME` records from SMTP provider):**
   - Publish both 2048-bit DKIM selector records (`s1._domainkey.mail.example.com`, `s2._domainkey.mail.example.com`) provided by your mail provider and verify DNS propagation before enabling SMTP.
3. **DMARC (`TXT` record on `_dmarc.example.com`):**
   - Start with monitoring during controlled beta:
     ```text
     v=DMARC1; p=quarantine; pct=25; rua=mailto:dmarc-reports@example.com; adkim=r; aspf=r
     ```
   - Upgrade to `p=reject; pct=100` after confirming zero legitimate bounces/misalignments in DMARC aggregate reports.

---

## 4. Non-Destructive Validation Procedure (Zero Customer Impact)

1. Verify config loading cleanly (after setting staging/production `.env` on test host, NEVER with real customer emails):
   ```bash
   php artisan config:clear
   php artisan tinker --execute="echo config('mail.default') . '|' . config('mail.mailers.smtp.host') . '|' . config('mail.from.address');"
   ```
2. Verify notification rendering without sending outbound SMTP via Laravel feature tests (`Notification::fake()`):
   - Team invitation email subject/body + EN/ID locale preference (`CompanyInvitationNotification`)
   - Direct customer welcome email + password setup URL (`DirectCustomerWelcomeNotification`)
   - Self-service password reset email + EN/ID locale + generic non-enumerating responses + mail failure handling (`ResetPasswordNotification` & `PasswordResetController`)
   *(Remember: Never run `MAIL_MAILER=smtp` with real customer emails until DNS + operator test checks succeed.)*
3. **Controlled Beta SMTP Smoke Test (Operator Mailbox Only):**
   - Only after hosting + SMTP credentials are provisioned and explicitly approved, test `DirectCustomerWelcomeNotification` or `CompanyInvitationNotification` against a single **internal operator test address** (`@example.com` owned by your team), verifying SPF/DKIM pass headers cleanly before inviting external beta testers.
