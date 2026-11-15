<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ResetPasswordNotification;
use App\Services\InventoryService;
use App\Services\InvitationService;
use App\Services\RbacService;
use App\Services\TenantContext;
use App\Support\DatabaseSafetyGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class Round25SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConfig = [
            'app.env' => config('app.env'),
            'database.connections.mysql.database' => config('database.connections.mysql.database'),
            'database.connections.mysql_migrator.database' => config('database.connections.mysql_migrator.database'),
            'stockora.database_guard' => config('stockora.database_guard'),
            'stockora.trusted_proxies' => config('stockora.trusted_proxies'),
            'stockora.platform_bootstrap' => config('stockora.platform_bootstrap'),
            'mail.default' => config('mail.default'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            Config::set($key, $value);
        }

        TrustProxies::flushState();
        Carbon::setTestNow();
        app()->setLocale('en');
        app(TenantContext::class)->clear();
        app(RbacService::class)->clearCache();
        parent::tearDown();
    }

    private function createActiveCompanyWithOwner(string $tagSuffix = '', string $locale = 'en'): array
    {
        $tag = strtoupper(substr(md5(uniqid($tagSuffix, true)), 0, 6));
        $plan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        $owner = User::factory()->create([
            'email' => "r25-owner-{$tag}@stockoratest.com",
            'name' => "R25 Owner {$tag}",
            'locale' => $locale,
            'password' => Hash::make('StrongPass123'),
        ]);

        $company = Company::create([
            'code' => "R25{$tag}",
            'name' => "PT Hardening {$tag}",
            'industry' => 'Distributor / Supplier',
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => $plan->tier,
            'subscription_plan_id' => $plan->id,
            'access_starts_at' => now()->subDays(5),
            'access_expires_at' => now()->addDays(30),
            'activated_at' => now()->subDays(5),
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouse = app(TenantContext::class)->withoutScope(function () use ($company) {
            return Warehouse::create([
                'company_id' => $company->id,
                'code' => 'WH-MAIN',
                'name' => 'Main Warehouse',
                'is_default' => true,
                'is_active' => true,
            ]);
        });

        return [$company, $owner, $warehouse];
    }

    private function createPlatformAdmin(bool $active = true): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'R25 Platform Operator',
            'email' => 'r25-platform-' . uniqid() . '@stockora.app',
            'locale' => 'en',
            'password' => Hash::make('PlatformPass123!'),
            'is_active' => $active,
        ]);
    }

    public function test_tenant_login_throttling_enforces_limit_with_en_id_messages_and_recovers_after_cooldown(): void
    {
        [, $owner] = $this->createActiveCompanyWithOwner('TLOGIN');
        [, $otherUser] = $this->createActiveCompanyWithOwner('TOTHER');

        $server = ['REMOTE_ADDR' => '198.51.100.11'];

        // 5 invalid attempts succeed at HTTP 302 validation redirect level without prematurely blocking
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables($server)
                ->post('/login', [
                    'email' => $owner->email,
                    'password' => 'WrongPassword1',
                ])
                ->assertStatus(302)
                ->assertSessionHasErrors('email');
        }

        // 6th attempt hits 429 Too Many Requests with localized English body + Retry-After header
        $throttled = $this->withServerVariables($server)
            ->post('/login', [
                'email' => $owner->email,
                'password' => 'StrongPass123',
            ]);

        $throttled->assertStatus(429);
        $this->assertNotNull($throttled->headers->get('Retry-After'));
        $throttled->assertSee('Too many attempts detected.');

        // Indonesian guest locale renders localized 429 JSON error
        $this->withServerVariables($server)
            ->withSession(['guest_locale' => 'id'])
            ->postJson('/login', [
                'email' => $owner->email,
                'password' => 'StrongPass123',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'Terlalu banyak percobaan terdeteksi.'));

        // Legitimate user on another account sharing same IP is NOT blocked by owner's per-account limit
        $this->withServerVariables($server)
            ->post('/login', [
                'email' => $otherUser->email,
                'password' => 'StrongPass123',
            ])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout');

        // After cooldown expires, original owner recovers and logs in cleanly
        $this->travel(65)->seconds();

        $this->withServerVariables($server)
            ->post('/login', [
                'email' => $owner->email,
                'password' => 'StrongPass123',
            ])
            ->assertRedirect(route('dashboard'));
    }

    public function test_platform_login_and_registration_throttling_and_recovery(): void
    {
        $admin = $this->createPlatformAdmin(true);
        $platformServer = ['REMOTE_ADDR' => '198.51.100.12'];

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables($platformServer)
                ->post(route('platform.login.submit'), [
                    'email' => $admin->email,
                    'password' => 'WrongAdminPass1',
                ])
                ->assertStatus(302)
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables($platformServer)
            ->withSession(['platform_guest_locale' => 'id'])
            ->post(route('platform.login.submit'), [
                'email' => $admin->email,
                'password' => 'PlatformPass123!',
            ])
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan terdeteksi.');

        $this->travel(65)->seconds();

        $this->withServerVariables($platformServer)
            ->post(route('platform.login.submit'), [
                'email' => $admin->email,
                'password' => 'PlatformPass123!',
            ])
            ->assertRedirect(route('platform.dashboard'));

        // Registration throttling
        $regServer = ['REMOTE_ADDR' => '198.51.100.13'];
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables($regServer)
                ->post('/register', [
                    'name' => "Spam User {$i}",
                    'email' => "spam-reg-{$i}-" . uniqid() . '@stockoratest.com',
                    'password' => 'short',
                    'password_confirmation' => 'short',
                ])
                ->assertStatus(302);
        }

        $this->withServerVariables($regServer)
            ->post('/register', [
                'name' => 'Blocked Reg',
                'email' => 'blocked-' . uniqid() . '@stockoratest.com',
                'password' => 'StrongPass123',
                    'password_confirmation' => 'StrongPass123',
                    'agree_terms' => '1',
                ])
            ->assertStatus(429);

        $this->travel(65)->seconds();

        $this->withServerVariables($regServer)
            ->post('/register', [
                'name' => 'Recovered Reg User',
                'email' => 'recovered-' . uniqid() . '@stockoratest.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
                'agree_terms' => '1',
            ])
            ->assertRedirect(route('onboarding.company.create'));
    }

    public function test_reverse_proxy_trust_ignores_spoofed_headers_unless_ip_is_configured_trusted_proxy(): void
    {
        // Unconfigured trusted proxies -> arbitrary X-Forwarded-* headers MUST be ignored
        Config::set('stockora.trusted_proxies', null);

        $untrustedReq = Request::create('http://stockora.test/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.55',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        ]);

        $middleware = new TrustProxies();
        $middleware->handle($untrustedReq, function (Request $req) {
            $this->assertFalse($req->isSecure());
            $this->assertSame('198.51.100.55', $req->ip());

            return response('ok');
        });

        // Configured reverse proxy -> ONLY requests arriving from 10.0.10.2 trust forwarded headers
        Config::set('stockora.trusted_proxies', '10.0.10.2, 10.0.10.3');

        $trustedProxyReq = Request::create('http://stockora.test/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.10.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        ]);

        $middleware->handle($trustedProxyReq, function (Request $req) {
            $this->assertTrue($req->isSecure());
            $this->assertSame('203.0.113.77', $req->ip());

            return response('ok');
        });

        $spoofedClientReq = Request::create('http://stockora.test/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        ]);

        $middleware->handle($spoofedClientReq, function (Request $req) {
            $this->assertFalse($req->isSecure());
            $this->assertSame('198.51.100.99', $req->ip());

            return response('ok');
        });
    }

    public function test_session_cookie_defaults_harden_for_production_without_breaking_local_http(): void
    {
        $sessionConfig = file_get_contents(config_path('session.php'));
        $this->assertNotFalse($sessionConfig);
        $this->assertStringContainsString("env('SESSION_ENCRYPT', env('APP_ENV', 'production') === 'production')", $sessionConfig);
        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production' ? true : null)", $sessionConfig);
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        // Local/testing environment does not force secure=true on HTTP
        $this->assertNull(config('session.secure'));
    }

    public function test_password_reset_security_non_enumeration_en_id_localization_expiry_and_failure_handling(): void
    {
        Notification::fake();

        [, $userEn] = $this->createActiveCompanyWithOwner('PREN', 'en');
        [, $userId] = $this->createActiveCompanyWithOwner('PRID', 'id');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__('auth.send_reset_link_btn'));

        $ipServer = ['REMOTE_ADDR' => '198.51.100.21'];

        // Non-existent vs existing email produce identical generic flash status
        $missingResp = $this->withServerVariables($ipServer)
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'does-not-exist@stockoratest.com']);
        $missingResp->assertRedirect(route('password.request'));
        $missingResp->assertSessionHas('status', __('auth.password_reset_link_sent_generic'));
        Notification::assertCount(0);

        $existingResp = $this->withServerVariables($ipServer)
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => $userEn->email]);
        $existingResp->assertRedirect(route('password.request'));
        $existingResp->assertSessionHas('status', __('auth.password_reset_link_sent_generic'));

        $capturedResetUrl = null;
        Notification::assertSentTo($userEn, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($userEn, &$capturedResetUrl) {
            $mail = $notification->toMail($userEn);
            $capturedResetUrl = $notification->resetUrl($userEn);
            $this->assertSame('Reset Your Stockora Password', $mail->subject);
            return str_contains($capturedResetUrl, '/reset-password/');
        });

        // Indonesian preferred locale email content
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->post(route('password.email'), ['email' => $userId->email])
            ->assertSessionHas('status', __('auth.password_reset_link_sent_generic'));

        Notification::assertSentTo($userId, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($userId) {
            app()->setLocale('id');
            $mail = $notification->toMail($userId);
            $this->assertSame('Atur Ulang Kata Sandi Stockora Anda', $mail->subject);
            app()->setLocale('en');
            return true;
        });

        // Rate limit enforced on repeated password reset requests (3/min)
        $spamServer = ['REMOTE_ADDR' => '198.51.100.23'];
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables($spamServer)
                ->post(route('password.email'), ['email' => 'ratecheck@stockoratest.com'])
                ->assertStatus(302);
        }
        $this->withServerVariables($spamServer)
            ->post(route('password.email'), ['email' => 'ratecheck@stockoratest.com'])
            ->assertStatus(429);

        // Extract issued token via fresh broker token for single-use + expiry assertions
        $rawToken = Password::broker('users')->createToken($userEn);
        $oldRememberToken = $userEn->remember_token;

        $this->get(route('password.reset', ['token' => $rawToken, 'email' => $userEn->email]))
            ->assertOk()
            ->assertSee(strtolower($userEn->email));

        $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => $userEn->email,
            'password' => 'NewStrongPass99',
            'password_confirmation' => 'NewStrongPass99',
        ])->assertRedirect(route('login'));

        $userEn->refresh();
        $this->assertTrue(Hash::check('NewStrongPass99', $userEn->password));
        $this->assertFalse(Hash::check('StrongPass123', $userEn->password));
        $this->assertNotSame($oldRememberToken, $userEn->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $userEn->email]);

        // Reusing already-consumed token fails cleanly
        $this->from(route('password.reset', ['token' => $rawToken, 'email' => $userEn->email]))
            ->post(route('password.update'), [
                'token' => $rawToken,
                'email' => $userEn->email,
                'password' => 'AnotherPass99',
                'password_confirmation' => 'AnotherPass99',
            ])
            ->assertRedirect(route('password.reset', ['token' => $rawToken, 'email' => $userEn->email]))
            ->assertSessionHasErrors('email');

        // Expired token (65 minutes old) is rejected cleanly on GET and POST
        $expiredToken = Password::broker('users')->createToken($userEn);
        DB::table('password_reset_tokens')
            ->where('email', $userEn->email)
            ->update(['created_at' => now()->subMinutes(65)]);

        $this->get(route('password.reset', ['token' => $expiredToken, 'email' => $userEn->email]))
            ->assertOk()
            ->assertSee(__('auth.reset_password_invalid_token'));

        $this->post(route('password.update'), [
            'token' => $expiredToken,
            'email' => $userEn->email,
            'password' => 'ExpiredPass99',
            'password_confirmation' => 'ExpiredPass99',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_reset_handles_mail_delivery_failure_gracefully_without_token_leakage(): void
    {
        Log::spy();

        [, $user] = $this->createActiveCompanyWithOwner('PRFAIL');

        Mail::extend('broken_r25', static function () {
            return new class implements TransportInterface {
                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    throw new RuntimeException('Simulated SMTP connection refused');
                }

                public function __toString(): string
                {
                    return 'broken_r25';
                }
            };
        });

        Config::set('mail.mailers.broken_r25', ['transport' => 'broken_r25']);
        Config::set('mail.default', 'broken_r25');

        try {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.24'])
                ->from(route('password.request'))
                ->post(route('password.email'), ['email' => $user->email]);

            $response->assertRedirect(route('password.request'));
            $response->assertSessionHas('status', __('auth.password_reset_link_sent_generic'));

            Log::shouldHaveReceived('error')
                ->once()
                ->withArgs(function (string $msg, array $context) use ($user): bool {
                    return $msg === 'Password reset email dispatch failed.'
                        && ($context['user_id'] ?? null) === $user->id
                        && !isset($context['token']);
                });
        } finally {
            Config::set('mail.default', 'array');
            Mail::purge('broken_r25');
        }
    }

    public function test_invitation_token_security_blocks_wrong_user_expired_and_flood_attempts(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createActiveCompanyWithOwner('INVSEC');
        $invitedEmail = 'invited-r25-' . uniqid() . '@stockoratest.com';

        $created = app(InvitationService::class)->createInvitation(
            $company->id,
            $owner,
            $invitedEmail,
            'staff'
        );

        /** @var CompanyInvitation $invitation */
        $invitation = $created['invitation'];
        $rawToken = basename(parse_url($created['accept_url'], PHP_URL_PATH));

        $this->assertNotSame($rawToken, $invitation->token_hash);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);

        // Wrong logged-in user cannot accept invitation belonging to another email
        $wrongUser = User::factory()->create(['email' => 'wrong-' . uniqid() . '@stockoratest.com']);
        $this->actingAs($wrongUser)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertForbidden();

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id' => $wrongUser->id,
        ]);

        // Expired invitation cannot be accepted even by matching email user
        $matchingUser = User::factory()->create(['email' => $invitedEmail]);
        app(TenantContext::class)->withoutScope(function () use ($invitation): void {
            $invitation->update(['expires_at' => now()->subMinute()]);
        });

        $this->actingAs($matchingUser)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertRedirect(route('invitations.show', ['token' => $rawToken]))
            ->assertSessionHas('error');

        // Invitation flood throttling
        $inviteFloodServer = ['REMOTE_ADDR' => '198.51.100.31'];
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables($inviteFloodServer)
                ->get(route('invitations.show', ['token' => 'nonexistent-token-' . $i]))
                ->assertStatus(404);
        }
        $this->withServerVariables($inviteFloodServer)
            ->get(route('invitations.show', ['token' => 'nonexistent-token-21']))
            ->assertStatus(429);
    }

    public function test_tenant_isolation_and_removed_membership_revocation_preserve_active_other_company(): void
    {
        [$companyA, $ownerA, $warehouseA] = $this->createActiveCompanyWithOwner('COMPA');
        [$companyB, $ownerB, $warehouseB] = $this->createActiveCompanyWithOwner('COMPB');

        $sharedUser = User::factory()->create([
            'email' => 'shared-' . uniqid() . '@stockoratest.com',
            'password' => Hash::make('SharedPass123'),
        ]);

        DB::table('company_user')->insert([
            [
                'company_id' => $companyA->id,
                'user_id' => $sharedUser->id,
                'role' => 'manager',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $companyB->id,
                'user_id' => $sharedUser->id,
                'role' => 'admin',
                'is_default' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Cross-tenant isolation: Owner B cannot access Company A warehouse or inject X-Company-Id
        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('warehouses.edit', $warehouseA->id))
            ->assertNotFound();

        $this->actingAs($ownerB)
            ->get(route('dashboard'), ['X-Company-Id' => (string) $companyA->id])
            ->assertForbidden();

        // Remove sharedUser from Company A
        DB::table('company_user')
            ->where('company_id', $companyA->id)
            ->where('user_id', $sharedUser->id)
            ->delete();

        // Stale session pointing at removed Company A immediately fails 403
        $this->actingAs($sharedUser)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('dashboard'))
            ->assertForbidden();

        // Attempt to switch to removed Company A fails
        $this->actingAs($sharedUser)
            ->from(route('company.select'))
            ->post(route('company.switch'), ['company_id' => $companyA->id])
            ->assertRedirect(route('company.select'))
            ->assertSessionHas('error');

        // Removed Company A disappears from workspace selector while Company B remains accessible
        $this->actingAs($sharedUser)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('company.select'))
            ->assertOk()
            ->assertDontSee($companyA->name)
            ->assertSee($companyB->name);

        // User retains legitimate access to switch to and operate Company B
        $this->actingAs($sharedUser)
            ->post(route('company.switch'), ['company_id' => $companyB->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_company_id', $companyB->id);

        $this->actingAs($sharedUser)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('warehouses.edit', $warehouseB->id))
            ->assertOk();

        // If ALL memberships are deactivated/removed, user cannot access any workspace routes
        DB::table('company_user')
            ->where('company_id', $companyB->id)
            ->where('user_id', $sharedUser->id)
            ->update(['is_active' => 0]);

        $this->actingAs($sharedUser)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_payment_proof_upload_validation_private_storage_and_authorization(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$companyA, $ownerA] = $this->createActiveCompanyWithOwner('PAYA');
        [$companyB, $ownerB] = $this->createActiveCompanyWithOwner('PAYB');
        $admin = $this->createPlatformAdmin(true);

        $staffA = User::factory()->create(['email' => 'staff-a-' . uniqid() . '@stockoratest.com']);
        DB::table('company_user')->insert([
            'company_id' => $companyA->id,
            'user_id' => $staffA->id,
            'role' => 'staff',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        // 1. Oversized (> 5120 KB) rejected cleanly
        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'TRF-HUGE',
                'payer_name' => 'Huge Upload',
                'payment_proof' => UploadedFile::fake()->create('huge.pdf', 5200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('payment_proof');

        // 2. Disallowed executable / mismatched extension & MIME rejected cleanly
        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'TRF-PHP',
                'payer_name' => 'Bad Upload',
                'payment_proof' => UploadedFile::fake()->create('exploit.php', 12, 'application/x-php'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'TRF-TXT',
                'payer_name' => 'Spoofed Upload',
                'payment_proof' => UploadedFile::fake()->create('fake.png', 12, 'text/plain'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, SubscriptionPayment::where('company_id', $companyA->id)->count());

        // 3. Valid PNG upload succeeds to private `local` disk and never touches `public`
        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'TRF-VALID-01',
                'payer_name' => 'Valid Transfer',
                'payment_proof' => UploadedFile::fake()->image('proof.png', 400, 400),
            ])
            ->assertStatus(302);

        $payment = SubscriptionPayment::where('company_id', $companyA->id)->firstOrFail();
        $this->assertSame('local', $payment->payment_proof_disk);
        Storage::disk('local')->assertExists($payment->payment_proof_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        // 4. Download authorization
        auth('web')->logout();
        $this->get(route('subscription.payments.proof', $payment->id))->assertRedirect(route('login'));

        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('subscription.payments.proof', $payment->id))
            ->assertForbidden();

        $this->actingAs($staffA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('subscription.payments.proof', $payment->id))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('subscription.payments.proof', $payment->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($admin, 'platform')
            ->get(route('platform.payments.proof', $payment->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // 5. Payment submission rate limiting (8/min per user+IP)
        $payServer = ['REMOTE_ADDR' => '198.51.100.41'];
        for ($i = 1; $i <= 8; $i++) {
            $this->actingAs($ownerB)
                ->withSession(['active_company_id' => $companyB->id])
                ->withServerVariables($payServer)
                ->post(route('subscription.payments.store'), [
                    'subscription_plan_id' => $starter->id,
                    'billing_interval' => 'INVALID',
                ])
                ->assertStatus(302);
        }

        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->withServerVariables($payServer)
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
            ])
            ->assertStatus(429);
    }

    public function test_production_database_safety_guard_fails_closed_and_protects_local_testing(): void
    {
        // Local & testing strictly refuse non-sandbox DB even if production opt-in flags are set
        Config::set('database.connections.mysql.database', 'stockora_prod');
        Config::set('database.connections.mysql_migrator.database', 'stockora_prod');
        Config::set('stockora.database_guard', [
            'allow_production_db' => true,
            'approved_production_database' => 'stockora_prod',
            'production_approval_token' => 'APPROVED-PROD-RELEASE-20261115',
        ]);

        foreach (['local', 'testing', 'staging'] as $nonProdEnv) {
            try {
                DatabaseSafetyGuard::enforce($nonProdEnv);
                $this->fail("Expected RuntimeException when booting '{$nonProdEnv}' on stockora_prod.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('SANDBOX SAFETY VIOLATION', $e->getMessage());
            }
        }

        // Production fails closed when approval opt-in is false
        Config::set('stockora.database_guard.allow_production_db', false);
        $this->assertThrows(fn () => DatabaseSafetyGuard::enforce('production'), RuntimeException::class);

        // Production fails closed when placeholder approval token used
        Config::set('stockora.database_guard', [
            'allow_production_db' => true,
            'approved_production_database' => 'stockora_prod',
            'production_approval_token' => 'changeme_placeholder',
        ]);
        $this->assertThrows(fn () => DatabaseSafetyGuard::enforce('production'), RuntimeException::class);

        // Production fails closed when approved DB mismatches active DB_DATABASE
        Config::set('stockora.database_guard', [
            'allow_production_db' => true,
            'approved_production_database' => 'different_prod_db',
            'production_approval_token' => 'APPROVED-PROD-RELEASE-20261115',
        ]);
        $this->assertThrows(fn () => DatabaseSafetyGuard::enforce('production'), RuntimeException::class);

        // Production fails closed when targeting stockora_sandbox in production env
        Config::set('database.connections.mysql.database', 'stockora_sandbox');
        Config::set('stockora.database_guard.approved_production_database', 'stockora_sandbox');
        $this->assertThrows(fn () => DatabaseSafetyGuard::enforce('production'), RuntimeException::class);

        // Production succeeds ONLY when all intentional approval conditions match cleanly
        Config::set('database.connections.mysql.database', 'stockora_prod');
        Config::set('database.connections.mysql_migrator.database', 'stockora_prod');
        Config::set('stockora.database_guard', [
            'allow_production_db' => true,
            'approved_production_database' => 'stockora_prod',
            'production_approval_token' => 'APPROVED-PROD-RELEASE-20261115',
        ]);

        DatabaseSafetyGuard::enforce('production');
        DatabaseSafetyGuard::assertConnectedDatabase('stockora_prod', 'Round25Test', 'production');
        $this->assertThrows(
            fn () => DatabaseSafetyGuard::assertConnectedDatabase('stockora', 'Round25Test', 'production'),
            RuntimeException::class
        );

        // InventoryService connected database helper refuses mismatched DB cleanly
        $this->assertThrows(
            fn () => DatabaseSafetyGuard::assertConnectedDatabase('stockora', InventoryService::class, 'testing'),
            RuntimeException::class
        );
    }

    public function test_config_database_has_no_hardcoded_sandbox_credentials_and_platform_command_uses_config(): void
    {
        $dbConfigRaw = file_get_contents(config_path('database.php'));
        $this->assertNotFalse($dbConfigRaw);
        $this->assertStringNotContainsString('AppSandbox!2026', $dbConfigRaw);
        $this->assertStringNotContainsString('MigSandbox!2026', $dbConfigRaw);
        $this->assertStringNotContainsString('stockora_app_sandbox', $dbConfigRaw);
        $this->assertStringNotContainsString('stockora_migrator_sandbox', $dbConfigRaw);

        // CreatePlatformAdminCommand uses config('stockora.platform_bootstrap.*') instead of env(), ensuring config:cache compatibility
        $cmdSource = file_get_contents(app_path('Console/Commands/CreatePlatformAdminCommand.php'));
        $this->assertNotFalse($cmdSource);
        $this->assertStringNotContainsString('env(', $cmdSource);
        $this->assertStringContainsString("config('stockora.platform_bootstrap.email')", $cmdSource);
        $this->assertStringContainsString("config('stockora.platform_bootstrap.password'", $cmdSource);

        $bootstrapEmail = 'r25-cached-admin-' . uniqid() . '@stockora.app';
        Config::set('stockora.platform_bootstrap.email', $bootstrapEmail);
        Config::set('stockora.platform_bootstrap.password', 'CachedAdminPass2026!');

        $exit = Artisan::call('stockora:platform-admin:create', [
            '--name' => 'Cached Config Admin',
        ]);

        $this->assertSame(0, $exit);
        $this->assertNotNull(PlatformAdmin::where('email', $bootstrapEmail)->first());
    }

    public function test_sensitive_setup_invitation_and_payment_action_throttling_and_destructive_cmd_lock(): void
    {
        // Password setup throttling (5/min per email+IP)
        $setupServer = ['REMOTE_ADDR' => '198.51.100.61'];
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables($setupServer)
                ->post(route('password.setup.store'), [
                    'token' => 'bad-token-' . $i,
                    'email' => 'setup-throttle@stockoratest.com',
                    'password' => 'StrongPass123',
                    'password_confirmation' => 'StrongPass123',
                ])
                ->assertStatus(302);
        }
        $this->withServerVariables($setupServer)
            ->post(route('password.setup.store'), [
                'token' => 'bad-token-6',
                'email' => 'setup-throttle@stockoratest.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
            ])
            ->assertStatus(429);

        // Invitation accept throttling (10/min) + customer cancel action throttling (10/min) + platform payment action throttling (20/min)
        [$company, $owner] = $this->createActiveCompanyWithOwner('ACTTHR');
        $admin = $this->createPlatformAdmin(true);

        $inviteAcceptServer = ['REMOTE_ADDR' => '198.51.100.62'];
        for ($i = 1; $i <= 10; $i++) {
            $resp = $this->actingAs($owner)
                ->withServerVariables($inviteAcceptServer)
                ->post(route('invitations.accept', ['token' => 'missing-invite-' . $i]));
            $this->assertTrue(in_array($resp->getStatusCode(), [302, 404], true));
        }
        $this->actingAs($owner)
            ->withServerVariables($inviteAcceptServer)
            ->post(route('invitations.accept', ['token' => 'missing-invite-11']))
            ->assertStatus(429);

        $cancelServer = ['REMOTE_ADDR' => '198.51.100.63'];
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($owner)
                ->withSession(['active_company_id' => $company->id])
                ->withServerVariables($cancelServer)
                ->post(route('subscription.payments.cancel', 999999))
                ->assertStatus(403);
        }
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->withServerVariables($cancelServer)
            ->post(route('subscription.payments.cancel', 999999))
            ->assertStatus(429);

        $platPayServer = ['REMOTE_ADDR' => '198.51.100.64'];
        for ($i = 1; $i <= 20; $i++) {
            $this->actingAs($admin, 'platform')
                ->withServerVariables($platPayServer)
                ->post(route('platform.payments.store'), [])
                ->assertStatus(302);
        }
        $this->actingAs($admin, 'platform')
            ->withServerVariables($platPayServer)
            ->post(route('platform.payments.store'), [])
            ->assertStatus(429);

        // Production destructive command lock (without executing any DB command)
        DB::prohibitDestructiveCommands(true);
        try {
            $this->assertSame(1, Artisan::call('migrate:fresh'));
            $this->assertSame(1, Artisan::call('migrate:refresh'));
            $this->assertSame(1, Artisan::call('db:wipe'));
        } finally {
            DB::prohibitDestructiveCommands(false);
        }
    }
}
