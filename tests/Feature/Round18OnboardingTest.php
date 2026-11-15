<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class Round18OnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_guest_can_open_registration_page(): void
    {
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSee('Create your Stockora account')
            ->assertSee('Account')
            ->assertSee('Company')
            ->assertSee('Ready')
            ->assertSee(route('login'), false);
    }

    public function test_guest_can_successfully_register_and_proceed_to_company_setup(): void
    {
        $email = 'founder-' . uniqid() . '@stockoratest.com';

        $response = $this->post('/register', [
            'name' => 'Rina Kusuma',
            'email' => $email,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
            'agree_terms' => '1',
        ]);

        $response->assertRedirect(route('onboarding.company.create'));
        $this->assertAuthenticated();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('Rina Kusuma', $user->name);
        $this->assertTrue(Hash::check('Stockora2026!', $user->password));

        $this->actingAs($user)
            ->get('/onboarding/company')
            ->assertOk()
            ->assertSee('Configure Your Company Workspace');
    }

    public function test_duplicate_email_is_rejected_including_case_variant(): void
    {
        $existing = User::factory()->create([
            'email' => 'dupowner-' . uniqid() . '@stockoratest.com',
        ]);

        $countBefore = User::count();

        $response = $this->from('/register')->post('/register', [
            'name' => 'Duplicate User',
            'email' => strtoupper($existing->email),
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
            'agree_terms' => '1',
        ]);

        $response->assertRedirect('/register')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame($countBefore, User::count());
    }

    public function test_invalid_password_and_missing_terms_are_rejected(): void
    {
        $email = 'weak-' . uniqid() . '@stockoratest.com';

        // Too short & lacks letters/numbers
        $this->from('/register')->post('/register', [
            'name' => 'Weak Pass',
            'email' => $email,
            'password' => '12345',
            'password_confirmation' => '12345',
            'agree_terms' => '1',
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        // Mismatched confirmation
        $this->from('/register')->post('/register', [
            'name' => 'Mismatch Pass',
            'email' => $email,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Different2026!',
            'agree_terms' => '1',
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        // Missing Terms acceptance
        $this->from('/register')->post('/register', [
            'name' => 'No Terms',
            'email' => $email,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
        ])->assertRedirect('/register')->assertSessionHasErrors('agree_terms');

        $this->assertNull(User::where('email', $email)->first());
    }

    public function test_invalid_company_information_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/onboarding/company')
            ->post('/onboarding/company', [
                'name' => '',
                'code' => 'BAD CODE WITH SPACES!',
                'industry' => 'InvalidIndustryNotAllowed',
                'phone' => 'invalid-phone',
                'address' => '',
                'logo' => UploadedFile::fake()->create('script.php', 20, 'application/x-php'),
            ]);

        $response->assertRedirect('/onboarding/company')
            ->assertSessionHasErrors(['name', 'code', 'industry', 'phone', 'address', 'logo']);

        $this->assertFalse(app(CompanyProvisioningService::class)->hasActiveCompany($user));
    }

    public function test_successful_company_creation_assigns_owner_and_auto_generates_code_with_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $uniqueName = 'PT Cahaya Abadi ' . uniqid();
        $logo = UploadedFile::fake()->image('brand.png', 200, 200);

        $response = $this->actingAs($user)->post('/onboarding/company', [
            'name' => $uniqueName,
            'code' => '',
            'industry' => 'Distributor / Supplier',
            'phone' => '+62 21 555 1234',
            'address' => 'Jl. Sudirman Kav. 10, Jakarta Selatan',
            'logo' => $logo,
        ]);

        $response->assertRedirect(route('onboarding.ready'));

        $company = Company::where('name', $uniqueName)->firstOrFail();
        $this->assertNotEmpty($company->code);
        $this->assertSame('Distributor / Supplier', $company->industry);
        $this->assertSame('+62 21 555 1234', $company->phone);
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);

        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertSame(1, (int) $membership->is_default);
        $this->assertSame(1, (int) $membership->is_active);
    }

    public function test_company_and_membership_and_warehouse_created_together_and_duplicate_blocked(): void
    {
        $user = User::factory()->create();
        $code = 'ONB-' . strtoupper(substr(uniqid(), -6));

        $this->actingAs($user)->post('/onboarding/company', [
            'name' => 'PT Mandiri Raya',
            'code' => $code,
            'industry' => 'Manufacturing',
            'phone' => '021-77889900',
            'address' => 'Kawasan Industri Jababeka Blok C2',
        ])->assertRedirect(route('onboarding.ready'));

        $company = Company::where('code', $code)->firstOrFail();
        $this->assertSame(1, DB::table('company_user')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('warehouses')->where('company_id', $company->id)->where('code', 'WH-MAIN')->count());

        // Second submission during onboarding must NOT create a duplicate workspace
        $secondAttempt = $this->actingAs($user)->post('/onboarding/company', [
            'name' => 'PT Duplikat Kedua',
            'code' => 'DUP-' . strtoupper(substr(uniqid(), -6)),
            'industry' => 'Retail',
            'phone' => '021-99988877',
            'address' => 'Jl. Ahmad Yani No. 5',
        ]);

        $secondAttempt->assertRedirect(route('onboarding.ready'));
        $this->assertSame(1, DB::table('company_user')->where('user_id', $user->id)->count());
        $this->assertNull(Company::where('name', 'PT Duplikat Kedua')->first());
    }

    public function test_failed_company_creation_rolls_back_with_no_orphans_or_leftover_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $companyName = 'PT Rollback Failure ' . uniqid();
        $companyCode = 'RB-' . strtoupper(substr(uniqid(), -6));

        $companiesBefore = Company::count();
        $membershipsBefore = DB::table('company_user')->count();
        $warehousesBefore = DB::table('warehouses')->count();

        Event::listen('eloquent.created: ' . Company::class, function (Company $created) use ($companyCode): void {
            if ($created->code === $companyCode) {
                throw new RuntimeException('Simulated mid-transaction failure');
            }
        });

        try {
            app(CompanyProvisioningService::class)->createWorkspaceForUser(
                $user,
                [
                    'name' => $companyName,
                    'code' => $companyCode,
                    'industry' => 'Retail',
                    'phone' => '021-44556677',
                    'address' => 'Jl. Merdeka No. 99',
                ],
                true,
                UploadedFile::fake()->image('fail.png', 100, 100)
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated mid-transaction failure', $e->getMessage());
        } finally {
            Event::forget('eloquent.created: ' . Company::class);
        }

        $this->assertSame($companiesBefore, Company::count());
        $this->assertSame($membershipsBefore, DB::table('company_user')->count());
        $this->assertSame($warehousesBefore, DB::table('warehouses')->count());
        $this->assertNull(Company::where('code', $companyCode)->first());
        $this->assertEmpty(Storage::disk('public')->allFiles('company-logos'));
    }

    public function test_newly_registered_user_reaches_ready_state_and_isolated_dashboard(): void
    {
        $email = 'journey-' . uniqid() . '@stockoratest.com';
        $companyCode = 'JRN-' . strtoupper(substr(uniqid(), -6));

        $this->post('/register', [
            'name' => 'Andi Wijaya',
            'email' => $email,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
            'agree_terms' => '1',
        ])->assertRedirect(route('onboarding.company.create'));

        // Incomplete account cannot enter tenant business features yet
        $this->get('/dashboard')->assertRedirect(route('onboarding.company.create'));
        $this->get('/products')->assertRedirect(route('onboarding.company.create'));
        $this->getJson('/dashboard')->assertStatus(403);

        $this->post('/onboarding/company', [
            'name' => 'PT Mandiri Terisolasi',
            'code' => $companyCode,
            'industry' => 'Wholesale / Trading',
            'phone' => '081299988877',
            'address' => 'Jl. Gatot Subroto No. 12',
        ])->assertRedirect(route('onboarding.ready'));

        $this->get('/onboarding/ready')
            ->assertOk()
            ->assertSee('Pending Platform Activation')
            ->assertSee('PT Mandiri Terisolasi')
            ->assertSee($companyCode);

        // Before platform activation, self-registered workspace is blocked from operational dashboard
        $this->get('/dashboard')->assertRedirect(route('company.access-status'));

        // Platform Super Admin manually activates the workspace
        $created = Company::where('code', $companyCode)->firstOrFail();
        $platformAdmin = \App\Models\PlatformAdmin::create([
            'name' => 'Test Activator',
            'email' => 'activator-' . uniqid() . '@stockora.app',
            'password' => Hash::make('StrongAdmin2026!'),
            'is_active' => true,
        ]);
        app(\App\Services\CompanyLifecycleService::class)->activate(
            $created,
            $platformAdmin,
            Company::ACCESS_STARTER
        );

        // Isolated workspace dashboard renders created tenant cleanly with no foreign tenant SKUs
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('PT Mandiri Terisolasi')
            ->assertSee($companyCode)
            ->assertDontSee('PRD-PLP-GEL')
            ->assertDontSee('SKU-QA-001');
    }

    public function test_existing_login_remains_functional(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = User::factory()->create([
            'password' => Hash::make('ExistingPass2026!'),
        ]);
        DB::table('company_user')->insert([
            'company_id' => $defaultCompany->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'ExistingPass2026!',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_multi_company_users_can_switch_workspaces_and_create_company_manually(): void
    {
        $co1 = Company::where('code', 'DEFAULT')->firstOrFail();
        $co2 = Company::where('code', 'DEMOQA')->first()
            ?? Company::create(['code' => 'QA2-' . uniqid(), 'name' => 'QA Co Two', 'is_active' => 1]);

        $user = User::factory()->create(['password' => Hash::make('MultiCo2026!')]);
        DB::table('company_user')->insert([
            [
                'company_id' => $co1->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $co2->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'is_default' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'MultiCo2026!'])
            ->assertRedirect(route('company.select'));

        $this->actingAs($user)
            ->post('/company/switch', ['company_id' => $co2->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_company_id', (int) $co2->id);

        // Existing admin manual creation route stays intact and compatible
        $manualCode = 'MAN-' . strtoupper(substr(uniqid(), -6));
        $this->actingAs($user)
            ->withSession(['active_company_id' => $co1->id])
            ->post('/companies', [
                'code' => $manualCode,
                'name' => 'PT Manual Admin Corp',
                'industry' => 'Building Materials',
                'phone' => '021-88776655',
                'address' => 'Jl. Raya Bogor KM 25',
            ])->assertRedirect(route('companies.index'));

        $manualCompany = Company::where('code', $manualCode)->firstOrFail();
        $this->assertSame('Building Materials', $manualCompany->industry);
        $this->assertSame('owner', DB::table('company_user')->where('company_id', $manualCompany->id)->where('user_id', $user->id)->value('role'));
    }

    public function test_guest_cannot_access_protected_dashboard_or_onboarding_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/products')->assertRedirect('/login');
        $this->get('/onboarding/company')->assertRedirect('/login');
        $this->post('/onboarding/company', [])->assertRedirect('/login');
        $this->get('/onboarding/ready')->assertRedirect('/login');
        $this->get('/company/select')->assertRedirect('/login');
    }

    public function test_authenticated_users_cannot_access_register_or_create_duplicate_accounts(): void
    {
        $incompleteUser = User::factory()->create();
        $usersBefore = User::count();

        $this->actingAs($incompleteUser)
            ->get('/register')
            ->assertRedirect(route('onboarding.company.create'));

        $this->actingAs($incompleteUser)
            ->post('/register', [
                'name' => 'Duplicate Attempt',
                'email' => 'second-' . uniqid() . '@stockoratest.com',
                'password' => 'Stockora2026!',
                'password_confirmation' => 'Stockora2026!',
                'agree_terms' => '1',
            ])->assertRedirect(route('onboarding.company.create'));

        $this->assertSame($usersBefore, User::count());

        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $onboardedUser = User::factory()->create();
        DB::table('company_user')->insert([
            'company_id' => $defaultCompany->id,
            'user_id' => $onboardedUser->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($onboardedUser)
            ->withSession(['active_company_id' => $defaultCompany->id])
            ->get('/register')
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_cannot_manipulate_company_id_or_role_through_request_parameters(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $email = 'sec-' . uniqid() . '@stockoratest.com';
        $code = 'SEC-' . strtoupper(substr(uniqid(), -6));

        $this->post('/register', [
            'name' => 'Security Tester',
            'email' => $email,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
            'agree_terms' => '1',
            'company_id' => $defaultCompany->id,
            'role' => 'superadmin',
            'is_default' => 1,
        ])->assertRedirect(route('onboarding.company.create'));

        $user = User::where('email', $email)->firstOrFail();

        // Must NOT have joined DEFAULT via registration parameters
        $this->assertNull(
            DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $defaultCompany->id)
                ->first()
        );

        // Submit forged role / company_id / is_active / is_default on onboarding
        $this->actingAs($user)->post('/onboarding/company', [
            'name' => 'PT Security Verified',
            'code' => $code,
            'industry' => 'Printing',
            'phone' => '021-33445566',
            'address' => 'Jl. Kemanggisan Raya No. 7',
            'company_id' => $defaultCompany->id,
            'user_id' => 1,
            'role' => 'viewer',
            'is_default' => 0,
            'is_active' => 0,
        ])->assertRedirect(route('onboarding.ready'));

        $created = Company::where('code', $code)->firstOrFail();
        $this->assertNotEquals($defaultCompany->id, $created->id);

        $pivot = DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $created->id)
            ->firstOrFail();

        $this->assertSame('owner', $pivot->role);
        $this->assertSame(1, (int) $pivot->is_active);
        $this->assertSame(1, (int) $pivot->is_default);

        // Cross-tenant attempt into DEFAULT still returns 403
        $this->actingAs($user)
            ->withHeaders(['X-Company-Id' => (string) $defaultCompany->id])
            ->getJson('/dashboard')
            ->assertStatus(403);
    }
}
