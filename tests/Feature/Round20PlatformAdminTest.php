<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyLifecycleService;
use App\Services\TenantContext;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class Round20PlatformAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createPlatformAdmin(bool $active = true): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Control Plane Operator',
            'email' => 'operator-' . uniqid() . '@stockora.app',
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => $active,
        ]);
    }

    private function createSelfRegisteredCustomer(string $suffix = ''): array
    {
        $tag = $suffix ?: uniqid();
        $email = "owner-{$tag}@customer.test";
        $code = 'SELF-' . strtoupper(substr(md5((string) $tag), 0, 6));

        $this->post('/register', [
            'name' => "Founder {$tag}",
            'email' => $email,
            'password' => 'CustomerPass2026!',
            'password_confirmation' => 'CustomerPass2026!',
            'agree_terms' => '1',
        ])->assertRedirect(route('onboarding.company.create'));

        $this->post('/onboarding/company', [
            'name' => "PT Self Registered {$tag}",
            'code' => $code,
            'industry' => 'Distributor / Supplier',
            'phone' => '+62 21 7788 9900',
            'address' => 'Jl. Jenderal Sudirman Kav 52, Jakarta',
        ])->assertRedirect(route('onboarding.ready'));

        $user = User::where('email', $email)->firstOrFail();
        $company = Company::where('code', $code)->firstOrFail();

        return [$user, $company];
    }

    public function test_platform_admin_login_works_and_normal_users_cannot_access_platform_routes(): void
    {
        $admin = $this->createPlatformAdmin(true);
        $inactiveAdmin = $this->createPlatformAdmin(false);

        $this->get('/platform/login')->assertOk()->assertSee('Control Plane Sign In');

        // Guest blocked
        $this->get('/platform/dashboard')->assertRedirect(route('platform.login'));
        $this->getJson('/platform/customers')->assertStatus(401);

        // Inactive platform admin rejected
        $this->from('/platform/login')->post('/platform/login', [
            'email' => $inactiveAdmin->email,
            'password' => 'PlatformStrong2026!',
        ])->assertRedirect('/platform/login')->assertSessionHasErrors('email');
        $this->assertGuest('platform');

        // Tenant OWNER cannot elevate into /platform/*
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $tenantOwner = User::factory()->create();
        DB::table('company_user')->insert([
            'company_id' => $defaultCompany->id,
            'user_id' => $tenantOwner->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tenantOwner, 'web')
            ->withSession(['active_company_id' => $defaultCompany->id])
            ->get('/platform/dashboard')
            ->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->get('/platform/customers')->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->get('/platform/logs')->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->getJson('/platform/dashboard')->assertStatus(401);

        // Sign out tenant user first, then verify registration payload forgery cannot create platform admin
        $this->actingAs($tenantOwner, 'web')->post('/logout');
        $forgedEmail = 'forged-' . uniqid() . '@customer.test';
        $this->post('/register', [
            'name' => 'Forged Privilege',
            'email' => $forgedEmail,
            'password' => 'CustomerPass2026!',
            'password_confirmation' => 'CustomerPass2026!',
            'agree_terms' => '1',
            'is_platform_admin' => 1,
            'role' => 'platform_admin',
        ])->assertRedirect(route('onboarding.company.create'));
        $this->assertNull(PlatformAdmin::where('email', $forgedEmail)->first());

        // Real Platform Admin authentication succeeds
        $this->post('/platform/login', [
            'email' => $admin->email,
            'password' => 'PlatformStrong2026!',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($admin, 'platform');
        $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk();

        // Logout clears platform session
        $this->actingAs($admin, 'platform')->post('/platform/logout')->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_existing_seeded_companies_remain_active(): void
    {
        foreach (['DEFAULT', 'DEMOQA', 'PT-NUSANTARA-LOGIS'] as $code) {
            $company = Company::where('code', $code)->first();
            if ($company) {
                $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
                $this->assertTrue($company->is_active);
                $this->assertTrue($company->isOperationallyActive());
            }
        }
    }

    public function test_self_registered_customer_starts_pending_and_is_blocked_from_operations_until_activated(): void
    {
        $admin = $this->createPlatformAdmin();
        [$user, $company] = $this->createSelfRegisteredCustomer();

        $this->assertSame(Company::STATUS_PENDING_ACTIVATION, $company->account_status);
        $this->assertFalse($company->is_active);
        $this->assertFalse($company->isOperationallyActive());

        $regLog = PlatformAuditLog::where('company_id', $company->id)
            ->where('action', 'CUSTOMER_REGISTERED')
            ->first();
        $this->assertNotNull($regLog);
        $this->assertSame((int) $user->id, (int) $regLog->actor_user_id);

        // Ready page renders pending status clearly
        $this->actingAs($user)
            ->get('/onboarding/ready')
            ->assertOk()
            ->assertSee('Pending Platform Activation')
            ->assertSee($company->code);

        // Blocked from operational endpoints & exports
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('company.access-status'));
        $this->actingAs($user)->get('/products')->assertRedirect(route('company.access-status'));
        $this->actingAs($user)->get('/purchases')->assertRedirect(route('company.access-status'));
        $this->actingAs($user)->get('/warehouses')->assertRedirect(route('company.access-status'));
        $this->actingAs($user)->get('/reports/export')->assertRedirect(route('company.access-status'));
        $this->actingAs($user)->getJson('/dashboard')->assertStatus(403)->assertJsonPath('reason', 'pending_activation');
        $this->actingAs($user)->postJson('/inventory/grn', [])->assertStatus(403);

        // Cannot bypass pending state by posting second onboarding form
        $this->actingAs($user)->get('/onboarding/company')->assertRedirect(route('onboarding.ready'));
        $this->actingAs($user)->post('/onboarding/company', [
            'name' => 'Bypass Corp',
            'industry' => 'Retail',
            'phone' => '021-88997766',
            'address' => 'Jakarta',
        ])->assertRedirect(route('onboarding.ready'));
        $this->assertNull(Company::where('name', 'Bypass Corp')->first());

        // Dedicated Pending Activation screen shows support contact & logout
        $this->actingAs($user)
            ->get('/company/access-status')
            ->assertOk()
            ->assertSee('Workspace Awaiting Manual Activation')
            ->assertSee(config('stockora.support.email'))
            ->assertSee('Sign Out');

        // Pending Activation queue lists this company
        $this->actingAs($admin, 'platform')
            ->get('/platform/pending')
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($user->email);

        // Super Admin activates customer manually
        $expiresAt = now()->addMonths(3)->toDateString();
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.activate', $company), [
                'access_type' => Company::ACCESS_PROFESSIONAL,
                'access_starts_at' => now()->toDateString(),
                'access_expires_at' => $expiresAt,
                'note' => 'Manual verification passed.',
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
        $this->assertTrue($company->is_active);
        $this->assertSame(Company::ACCESS_PROFESSIONAL, $company->access_type);
        $this->assertNotNull($company->activated_at);
        $this->assertSame((int) $admin->id, (int) $company->activated_by_platform_admin_id);

        $actLog = PlatformAuditLog::where('company_id', $company->id)
            ->where('action', 'ACCOUNT_ACTIVATED')
            ->first();
        $this->assertNotNull($actLog);
        $this->assertSame((int) $admin->id, (int) $actLog->platform_admin_id);

        // Customer access-status check seamlessly forwards newly activated customer to /dashboard!
        $this->actingAs($user)
            ->get('/company/access-status')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($company->code);
    }

    public function test_direct_customer_onboarding_creates_company_warehouse_owner_and_single_use_setup_without_password_leak(): void
    {
        Notification::fake();
        $admin = $this->createPlatformAdmin();

        $code = 'DIR-' . strtoupper(substr(uniqid(), -6));
        $ownerEmail = 'direct-owner-' . uniqid() . '@enterprise.test';

        $response = $this->actingAs($admin, 'platform')
            ->post('/platform/customers', [
                'company_name' => 'PT Nusantara Direct Mandiri',
                'company_code' => $code,
                'industry' => 'Manufacturing',
                'phone' => '+62 21 5000 1234',
                'address' => 'Kawasan Industri Cikarang Blok E1',
                'owner_name' => 'Budi Santoso',
                'owner_email' => $ownerEmail,
                'access_type' => Company::ACCESS_ENTERPRISE,
                'initial_status' => Company::STATUS_ACTIVE,
                'access_starts_at' => now()->toDateString(),
                'access_expires_at' => now()->addYear()->toDateString(),
                'admin_notes' => 'Enterprise contract #ENT-2026-01',
            ]);

        $company = Company::where('code', $code)->firstOrFail();
        $owner = User::where('email', $ownerEmail)->firstOrFail();

        $response->assertRedirect(route('platform.customers.show', $company));
        $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
        $this->assertSame(Company::ACCESS_ENTERPRISE, $company->access_type);
        $this->assertSame(1, DB::table('warehouses')->where('company_id', $company->id)->where('code', 'WH-MAIN')->count());

        $pivot = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $owner->id)->first();
        $this->assertSame('owner', $pivot->role);
        $this->assertSame(1, (int) $pivot->is_active);

        $audit = PlatformAuditLog::where('company_id', $company->id)->where('action', 'CUSTOMER_CREATED_DIRECTLY')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('password', $audit->new_values ?? []);
        $this->assertArrayNotHasKey('setup_url', $audit->new_values ?? []);

        // Duplicate existing owner email without explicit confirmation must reject safely
        $this->actingAs($admin, 'platform')
            ->from('/platform/customers/create')
            ->post('/platform/customers', [
                'company_name' => 'PT Second Unconfirmed',
                'industry' => 'Retail',
                'phone' => '021-88990011',
                'address' => 'Jakarta',
                'owner_name' => 'Budi Duplicate',
                'owner_email' => $ownerEmail,
                'access_type' => Company::ACCESS_STARTER,
                'initial_status' => Company::STATUS_ACTIVE,
            ])
            ->assertRedirect('/platform/customers/create')
            ->assertSessionHasErrors('owner_email');
        $this->assertNull(Company::where('name', 'PT Second Unconfirmed')->first());
        $this->assertSame(1, User::where('email', $ownerEmail)->count());

        // With explicit confirmation checkbox, existing user attaches safely without password overwrite or duplicate row
        $originalPasswordHash = $owner->password;
        $code2 = 'DIR2-' . strtoupper(substr(uniqid(), -5));
        $this->actingAs($admin, 'platform')
            ->post('/platform/customers', [
                'company_name' => 'PT Confirmed Multi Co',
                'company_code' => $code2,
                'industry' => 'Wholesale / Trading',
                'phone' => '021-77665544',
                'address' => 'Surabaya',
                'owner_name' => 'Ignored Overwrite',
                'owner_email' => $ownerEmail,
                'confirm_existing_owner' => '1',
                'access_type' => Company::ACCESS_PROFESSIONAL,
                'initial_status' => Company::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame($originalPasswordHash, $owner->password);
        $this->assertSame(1, User::where('email', $ownerEmail)->count());
        $co2 = Company::where('code', $code2)->firstOrFail();
        $this->assertNotNull(DB::table('company_user')->where('company_id', $co2->id)->where('user_id', $owner->id)->first());
    }

    public function test_suspend_reactivate_expiration_and_data_preservation(): void
    {
        $admin = $this->createPlatformAdmin();
        [$user, $company] = $this->createSelfRegisteredCustomer();
        $lifecycle = app(CompanyLifecycleService::class);
        $tenantCtx = app(TenantContext::class);

        $lifecycle->activate($company, $admin, Company::ACCESS_STARTER, now(), now()->addDays(30), 'Initial go-live');

        // Populate operational tenant data that MUST remain intact across suspend/expire
        $tenantCtx->withoutScope(function () use ($company): void {
            $wh = Warehouse::where('company_id', $company->id)->firstOrFail();
            $catId = DB::table('categories')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Lifecycle Cat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $unitId = DB::table('units')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Piece',
                'symbol' => 'pcs',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Product::create([
                'company_id' => $company->id,
                'code' => 'PRESERVE-001',
                'name' => 'Preserved Inventory SKU',
                'category_id' => $catId,
                'unit_id' => $unitId,
                'price' => 50000,
                'stock' => 25,
                'min_stock' => 5,
            ]);
            $this->assertNotNull($wh);
        });

        // Customer can access dashboard now
        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get('/dashboard')
            ->assertOk();

        // Suspension requires non-empty reason
        $this->actingAs($admin, 'platform')
            ->from(route('platform.customers.show', $company))
            ->post(route('platform.customers.suspend', $company), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // Suspend customer
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.suspend', $company), [
                'reason' => 'Compliance review requested',
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame(Company::STATUS_SUSPENDED, $company->account_status);
        $this->assertFalse($company->is_active);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'ACCOUNT_SUSPENDED')->first());

        // Customer access blocked immediately, AND tenant profile update cannot re-enable suspended company
        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get('/dashboard')
            ->assertRedirect(route('company.access-status'));
        $this->actingAs($user)
            ->withHeaders(['X-Company-Id' => (string) $company->id])
            ->getJson('/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'suspended');

        // Tenant data preserved cleanly
        $tenantCtx->withoutScope(function () use ($company): void {
            $this->assertSame(1, Product::where('company_id', $company->id)->where('code', 'PRESERVE-001')->count());
        });

        // Reactivate customer
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.reactivate', $company), [
                'access_type' => Company::ACCESS_PROFESSIONAL,
                'access_expires_at' => now()->addDays(45)->toDateString(),
                'reason' => 'Compliance cleared',
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
        $this->assertTrue($company->is_active);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'ACCOUNT_REACTIVATED')->first());

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($company->name);

        // Simulate passed expiration date & confirm live enforcement + Artisan expiry command
        $company->update(['access_expires_at' => Carbon::now()->subDays(2)]);

        $this->actingAs($user)
            ->withHeaders(['X-Company-Id' => (string) $company->id])
            ->getJson('/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'expired');

        $company->refresh();
        $this->assertSame(Company::STATUS_EXPIRED, $company->account_status);
        $this->assertFalse($company->is_active);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'ACCOUNT_EXPIRED')->first());

        // Extend expiration restores access cleanly
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.extend', $company), [
                'access_expires_at' => now()->addDays(60)->toDateString(),
                'note' => '60-day renewal processed',
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
        $this->assertTrue($company->is_active);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'EXPIRATION_EXTENDED')->first());

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_access_type_profile_notes_filters_and_invalid_transitions(): void
    {
        $admin = $this->createPlatformAdmin();
        [$owner, $company] = $this->createSelfRegisteredCustomer('FLT');
        $lifecycle = app(CompanyLifecycleService::class);

        // Invalid transition: PENDING_ACTIVATION cannot be reactivated via reactivate()
        try {
            $lifecycle->reactivate($company, $admin);
            $this->fail('Expected DomainException for invalid reactivation from PENDING_ACTIVATION.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Pending workspaces must be activated', $e->getMessage());
        }

        // Invalid past expiration during activation
        try {
            $lifecycle->activate($company, $admin, Company::ACCESS_STARTER, now(), now()->subDay());
            $this->fail('Expected DomainException for past expiration.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('later than', strtolower($e->getMessage()));
        }

        $lifecycle->activate($company, $admin, Company::ACCESS_STARTER, now(), now()->addDays(10));

        // Change access type
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.access-type', $company), [
                'access_type' => Company::ACCESS_ENTERPRISE,
                'note' => 'Upgraded manually',
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame(Company::ACCESS_ENTERPRISE, $company->access_type);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'ACCESS_TYPE_CHANGED')->first());

        // Update profile & internal admin notes (must NOT leak admin_notes into tenant view)
        $secretNote = 'INTERNAL-PLATFORM-ONLY-SECRET-NOTE-' . uniqid();
        $this->actingAs($admin, 'platform')
            ->put(route('platform.customers.update', $company), [
                'code' => $company->code,
                'name' => $company->name . ' Updated',
                'industry' => 'Wholesale / Trading',
                'phone' => '+62 21 555 8899',
                'address' => 'Updated Platform Address',
                'admin_notes' => $secretNote,
            ])
            ->assertRedirect(route('platform.customers.show', $company));

        $company->refresh();
        $this->assertSame($secretNote, $company->admin_notes);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'PROFILE_UPDATED')->first());
        $this->assertNotNull(PlatformAuditLog::where('company_id', $company->id)->where('action', 'ADMIN_NOTE_UPDATED')->first());

        // Tenant company edit page & dashboard must not expose internal admin_notes
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('companies.edit', $company))
            ->assertOk()
            ->assertDontSee($secretNote);

        // Directory filters & search work without adding admin into company_user
        $this->actingAs($admin, 'platform')
            ->get('/platform/customers?status=ACTIVE&access_type=ENTERPRISE&search=' . urlencode($company->code))
            ->assertOk()
            ->assertSee($company->name);

        // Customer detail loads metrics without adding platform admin to company_user
        $this->actingAs($admin, 'platform')
            ->get(route('platform.customers.show', $company))
            ->assertOk()
            ->assertSee($secretNote)
            ->assertSee($owner->email);
        $this->assertNull(DB::table('company_user')->where('company_id', $company->id)->where('user_id', $admin->id)->first());
    }

    public function test_multi_company_user_cannot_switch_to_blocked_workspace_and_audit_log_is_append_only(): void
    {
        $admin = $this->createPlatformAdmin();
        $activeCo = Company::where('code', 'DEFAULT')->firstOrFail();
        $blockedCo = Company::create([
            'code' => 'BLK-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'PT Blocked Multi Tenant',
            'is_active' => false,
            'account_status' => Company::STATUS_SUSPENDED,
            'access_type' => Company::ACCESS_FREE,
            'status_reason' => 'Suspended for test',
        ]);

        $user = User::factory()->create();
        DB::table('company_user')->insert([
            [
                'company_id' => $activeCo->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'is_default' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $blockedCo->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Unset active_company_id falls back cleanly to operationally active DEFAULT company
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($activeCo->code);

        // Cannot switch into or spoof blockedCo via X-Company-Id
        $this->actingAs($user)
            ->post('/company/switch', ['company_id' => $blockedCo->id])
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->withHeaders(['X-Company-Id' => (string) $blockedCo->id])
            ->getJson('/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'suspended');

        // Dashboard metrics accurately match DB totals & logs are append-only
        $expectedPending = Company::where('account_status', Company::STATUS_PENDING_ACTIVATION)->count();
        $expectedActive = Company::where('account_status', Company::STATUS_ACTIVE)->count();
        $this->actingAs($admin, 'platform')
            ->get('/platform/dashboard')
            ->assertOk()
            ->assertSee((string) $expectedPending)
            ->assertSee((string) $expectedActive);

        $log = PlatformAuditLog::create([
            'company_id' => $blockedCo->id,
            'platform_admin_id' => $admin->id,
            'action' => 'IMMUTABLE_CHECK',
            'note' => 'Initial note',
            'created_at' => now(),
        ]);

        try {
            $log->update(['note' => 'Tampered']);
            $this->fail('Expected LogicException when modifying PlatformAuditLog.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Expected LogicException when deleting PlatformAuditLog.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_platform_settings_and_cli_commands_work_cleanly(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin, 'platform')
            ->get('/platform/settings')
            ->assertOk()
            ->assertSee('stockora_sandbox')
            ->assertSee(config('stockora.support.email'));

        $this->actingAs($admin, 'platform')
            ->get('/platform/logs')
            ->assertOk()
            ->assertSee('Platform Audit');

        // CLI platform admin creation command works and rejects weak passwords
        $weakExit = Artisan::call('stockora:platform-admin:create', [
            '--name' => 'Weak',
            '--email' => 'weak@stockora.app',
            '--password' => '123',
        ]);
        $this->assertSame(1, $weakExit);
        $this->assertNull(PlatformAdmin::where('email', 'weak@stockora.app')->first());

        $cliEmail = 'cli-admin-' . uniqid() . '@stockora.app';
        $okExit = Artisan::call('stockora:platform-admin:create', [
            '--name' => 'CLI Provisioned Admin',
            '--email' => $cliEmail,
            '--password' => 'CliAdminPass2026!',
        ]);
        $this->assertSame(0, $okExit);
        $createdAdmin = PlatformAdmin::where('email', $cliEmail)->first();
        $this->assertNotNull($createdAdmin);
        $this->assertTrue(Hash::check('CliAdminPass2026!', $createdAdmin->password));

        // Expiration CLI command runs cleanly
        $expCo = Company::create([
            'code' => 'CMDEXP-' . strtoupper(substr(uniqid(), -5)),
            'name' => 'PT CLI Expire Check',
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => Company::ACCESS_FREE,
            'access_expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(0, Artisan::call('stockora:expire-companies'));
        $expCo->refresh();
        $this->assertSame(Company::STATUS_EXPIRED, $expCo->account_status);
        $this->assertFalse($expCo->is_active);
    }

    public function test_direct_onboarding_password_setup_link_sets_owner_password_and_logs_in(): void
    {
        $admin = $this->createPlatformAdmin();
        $code = 'STP-' . strtoupper(substr(uniqid(), -6));
        $ownerEmail = 'setup-' . uniqid() . '@enterprise.test';

        $result = app(\App\Services\PlatformOnboardingService::class)->createCustomerDirect(
            admin: $admin,
            companyAttributes: [
                'name' => 'PT Setup Verified',
                'code' => $code,
                'industry' => 'Retail',
                'phone' => '+62 21 555 1122',
                'address' => 'Jakarta Selatan',
            ],
            ownerAttributes: [
                'name' => 'Setup Owner',
                'email' => $ownerEmail,
            ],
            accessType: Company::ACCESS_PROFESSIONAL,
            initialStatus: Company::STATUS_ACTIVE
        );

        $this->assertNotNull($result['setup_url']);
        $parsedUrl = parse_url($result['setup_url']);
        $path = $parsedUrl['path'] . '?' . ($parsedUrl['query'] ?? '');
        $token = basename($parsedUrl['path']);

        // Setup page renders validly
        $this->get($path)
            ->assertOk()
            ->assertSee('Set Up Your Owner Password')
            ->assertSee($ownerEmail);

        // Owner sets password, enters active dashboard, and cannot reuse single-use token
        $this->post('/setup-password', [
            'token' => $token,
            'email' => $ownerEmail,
            'password' => 'MyChosenPass2026!',
            'password_confirmation' => 'MyChosenPass2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('web');
        $this->get('/dashboard')->assertOk()->assertSee('PT Setup Verified');

        // Reusing token fails
        $this->get($path)->assertSee('invalid or has expired');
    }
}
