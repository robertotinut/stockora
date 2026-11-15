<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\CompanyInvitationNotification;
use App\Notifications\DirectCustomerWelcomeNotification;
use App\Services\InvitationService;
use App\Services\TeamManagementService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Round22LocalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app()->setLocale('en');
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createPlatformAdmin(string $locale = 'en'): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'R22 Locale Admin',
            'email' => 'r22-loc-' . uniqid() . '@stockora.app',
            'locale' => $locale,
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => true,
        ]);
    }

    private function createEnterpriseTenant(?string $locale = 'en'): array
    {
        $tag = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $enterprisePlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        $user = User::factory()->create([
            'email' => "loc-{$tag}@stockora.test",
            'name' => "Owner {$tag}",
            'locale' => $locale,
        ]);

        $company = Company::create([
            'code' => "LOC{$tag}",
            'name' => "PT Lokalisasi {$tag}",
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => $enterprisePlan->tier,
            'subscription_plan_id' => $enterprisePlan->id,
            'access_starts_at' => now()->subDays(2),
            'access_expires_at' => now()->addDays(60),
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $whMain = app(TenantContext::class)->withoutScope(fn () => Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-MAIN',
            'name' => 'Gudang Utama',
            'is_default' => true,
            'is_active' => true,
        ]));

        return [$user, $company, $whMain];
    }

    public function test_artisan_check_translations_passes_with_full_en_id_parity(): void
    {
        $exitCode = Artisan::call('stockora:check-translations');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('100% EN/ID translation parity verified', Artisan::output());
    }

    public function test_guard_isolated_locale_switchers_persist_and_reject_unsafe_inputs(): void
    {
        // 1. Guest locale switch persists only in guest_locale and stays isolated from /platform/login
        $this->post(route('locale.update'), [
            'locale' => 'id',
            'redirect_to' => '/login',
        ])->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee(__('auth.login_card_title', [], 'id'));
        $this->get('/platform/login')->assertOk()->assertSee('Control Plane Sign In');

        // 2. Authenticated tenant user persists locale to users.locale without affecting another user
        [$userA] = $this->createEnterpriseTenant('en');
        [$userB] = $this->createEnterpriseTenant('en');

        $this->actingAs($userA)
            ->post(route('locale.update'), [
                'locale' => 'id',
                'redirect_to' => '/dashboard',
            ])->assertRedirect('/dashboard');

        $this->assertSame('id', $userA->fresh()->locale);
        $this->assertSame('en', $userB->fresh()->locale);

        $this->actingAs($userB)
            ->get('/company/select')
            ->assertOk()
            ->assertSee('Sign Out');

        // 3. Authenticated Platform Admin locale persists to platform_admins.locale independently
        $admin = $this->createPlatformAdmin('en');
        $this->actingAs($admin, 'platform')
            ->post(route('platform.locale.update'), [
                'locale' => 'id',
                'redirect_to' => '/platform/dashboard',
            ])->assertRedirect('/platform/dashboard');

        $this->assertSame('id', $admin->fresh()->locale);
        $this->assertSame('en', $userB->fresh()->locale);

        // 4. Unsupported locale rejected and unsafe external redirect falls back safely
        $this->actingAs($userA)
            ->from('/dashboard')
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('locale');

        $this->actingAs($userA)
            ->post(route('locale.update'), [
                'locale' => 'en',
                'redirect_to' => 'https://evil.example/phish',
            ])->assertRedirect('/');
    }

    public function test_public_auth_onboarding_tenant_and_platform_views_render_in_en_and_id(): void
    {
        [$owner, $company, $whMain] = $this->createEnterpriseTenant('en');
        $session = ['active_company_id' => $company->id];
        $admin = $this->createPlatformAdmin('en');

        $enterprisePlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        // Default English assertions
        $this->get('/plans')->assertOk()->assertSee('Enterprise')->assertSee($enterprisePlan->formattedPrice());
        $this->get('/login')->assertOk()->assertSee('Sign in to your workspace');
        $this->get('/register')->assertOk()->assertSee('Create your Stockora account');

        $this->actingAs($owner)->withSession($session)->get('/dashboard')->assertOk()
            ->assertSee('Executive Overview')
            ->assertSee('OWNER');
        $this->actingAs($owner)->withSession($session)->get('/products')->assertOk()->assertSee(__('products.page_title', [], 'en'));
        $this->actingAs($owner)->withSession($session)->get('/warehouses')->assertOk()->assertSee(__('warehouses.page_title', [], 'en'));
        $this->actingAs($owner)->withSession($session)->get('/purchases')->assertOk()->assertSee(__('purchases.page_title', [], 'en'));
        $this->actingAs($owner)->withSession($session)->get('/reports')->assertOk()->assertSee('Inventory Valuation Summary by Warehouse');
        $this->actingAs($owner)->withSession($session)->get('/team')->assertOk()->assertSee('OWNER');

        $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertSee(__('platform.dashboard_title', [], 'en'));
        $this->actingAs($admin, 'platform')->get('/platform/customers')->assertOk()->assertSee(__('platform.customers_title', [], 'en'));
        $this->actingAs($admin, 'platform')->get('/platform/plans')->assertOk()->assertSee('Enterprise');

        // Switch both user and admin to Bahasa Indonesia
        $owner->forceFill(['locale' => 'id'])->save();
        $admin->forceFill(['locale' => 'id'])->save();

        $idPlans = $this->actingAs($owner)->get('/plans')->assertOk();
        $idPlans->assertSee(__('subscription.public_plans.heading', [], 'id'));

        $this->actingAs($owner)->withSession($session)->get('/dashboard')->assertOk()
            ->assertSee('Ringkasan Eksekutif')
            ->assertSee('OWNER');
        $this->actingAs($owner)->withSession($session)->get('/products')->assertOk()->assertSee(__('products.page_title', [], 'id'));
        $this->actingAs($owner)->withSession($session)->get('/warehouses')->assertOk()->assertSee(__('warehouses.page_title', [], 'id'));
        $this->actingAs($owner)->withSession($session)->get('/purchases')->assertOk()->assertSee(__('purchases.page_title', [], 'id'));
        $this->actingAs($owner)->withSession($session)->get('/reports')->assertOk()->assertSee(__('reports.warehouse_summary_heading', [], 'id'));
        $this->actingAs($owner)->withSession($session)->get('/team')->assertOk()->assertSee(__('team.page_title', [], 'id'));

        $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertSee(__('platform.dashboard_title', [], 'id'));
        $this->actingAs($admin, 'platform')->get('/platform/customers')->assertOk()->assertSee(__('platform.customers_title', [], 'id'));
        $this->actingAs($admin, 'platform')->get('/platform/plans')->assertOk()->assertSee(__('platform.plans_title', [], 'id'));

        // Self-service onboarding + pending access-status in Bahasa Indonesia
        auth('web')->logout();
        auth('platform')->logout();
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $pendingTag = strtoupper(substr(uniqid(), -5));
        $this->post(route('locale.update'), ['locale' => 'id', 'redirect_to' => '/register'])->assertRedirect('/register');

        $this->post('/register', [
            'name' => 'Pendiri Baru',
            'email' => "pendiri-{$pendingTag}@stockora.test",
            'password' => 'StrongPass2026!',
            'password_confirmation' => 'StrongPass2026!',
            'agree_terms' => '1',
        ])->assertRedirect(route('onboarding.company.create'));

        $this->get('/onboarding/company')->assertOk()->assertSee('Atur Workspace Perusahaan Anda');

        $this->post('/onboarding/company', [
            'name' => "PT Uji Bahasa {$pendingTag}",
            'code' => "ID{$pendingTag}",
            'industry' => 'Distributor / Supplier',
            'phone' => '+62 21 7788 9900',
            'address' => 'Jl. Jenderal Sudirman Kav 52, Jakarta',
        ])->assertRedirect(route('onboarding.ready'));

        $this->get('/company/access-status')->assertOk()->assertSee(__('auth.pending_activation_heading', [], 'id'));
    }

    public function test_validation_notifications_domain_errors_and_csv_exports_localize(): void
    {
        [$owner, $company, $whMain] = $this->createEnterpriseTenant('id');
        $session = ['active_company_id' => $company->id];

        // 1. Validation error rendered in Bahasa Indonesia
        $valRes = $this->actingAs($owner)->withSession($session)
            ->postJson('/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        $this->assertStringContainsString('wajib diisi', strtolower((string) $valRes->json('errors.code.0')));

        // 2. Notifications honor HasLocalePreference ('id') when dispatched
        Notification::fake();
        $recipientId = User::factory()->create(['name' => 'Sari', 'locale' => 'id']);
        $this->assertSame('id', $recipientId->preferredLocale());
        $recipientId->notify(new DirectCustomerWelcomeNotification('PT Nusantara', 'PTNUS', 'https://example.test/setup'));

        Notification::assertSentTo($recipientId, DirectCustomerWelcomeNotification::class, function ($notification, $channels, $notifiable): bool {
            $prev = app()->getLocale();
            app()->setLocale($notifiable->preferredLocale());
            try {
                $mail = $notification->toMail($notifiable);

                return str_contains($mail->subject, __('notifications.welcome.subject', ['company' => 'PT Nusantara'], 'id'))
                    && $mail->actionText === __('notifications.welcome.action', [], 'id');
            } finally {
                app()->setLocale($prev);
            }
        });

        $inviteNotif = new CompanyInvitationNotification('PT Nusantara', 'Budi', 'staff', 'https://example.test/inv', '15 Nov 2026');
        $prevLocale = app()->getLocale();
        app()->setLocale('id');
        try {
            $inviteMailId = $inviteNotif->toMail($recipientId);
        } finally {
            app()->setLocale($prevLocale);
        }
        $this->assertSame(__('notifications.invitation.action', [], 'id'), $inviteMailId->actionText);

        // 3. Domain errors in TeamManagementService / InvitationService localize under app locale
        $ownerMembershipId = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $owner->id)
            ->value('id');

        app()->setLocale('id');
        try {
            app(TeamManagementService::class)->removeMember($company->id, (int) $ownerMembershipId, $owner);
            $this->fail('Expected DomainException when removing owner');
        } catch (DomainException $e) {
            $this->assertSame(__('team.errors.cannot_remove_self', [], 'id'), $e->getMessage());
        }

        // 4. CSV report export headers localize between EN and ID
        app(TenantContext::class)->withoutScope(function () use ($company, $whMain): void {
            $cat = Category::create(['company_id' => $company->id, 'code' => 'C-CSV', 'name' => 'Cat CSV']);
            $unit = Unit::create(['company_id' => $company->id, 'name' => 'Unit CSV', 'symbol' => 'pcs']);
            $prod = Product::create([
                'company_id' => $company->id,
                'code' => 'PRD-CSV-1',
                'name' => 'CSV Paper',
                'category_id' => $cat->id,
                'unit_id' => $unit->id,
                'purchase_price' => '100.000000',
                'selling_price' => '150.000000',
                'stock' => '5.0000',
                'min_stock' => '1.0000',
                'legacy_reconciliation_status' => 'CHAIN_CONSISTENT',
            ]);
            ProductWarehouse::create([
                'company_id' => $company->id,
                'product_id' => $prod->id,
                'warehouse_id' => $whMain->id,
                'stock' => '5.0000',
                'moving_average_cost' => '100.000000',
                'inventory_value' => '500.0000',
                'valuation_basis' => 'VERIFIED_ACQUISITION',
            ]);
        });

        $idCsvRes = $this->actingAs($owner)->withSession($session)->get(route('reports.export', ['type' => 'valuation']));
        $idCsvRes->assertOk();
        $idCsv = $idCsvRes->streamedContent();
        $idHeaderFirst = __('reports.csv.valuation_headers', [], 'id')[0];
        $enHeaderFirst = __('reports.csv.valuation_headers', [], 'en')[0];
        $this->assertNotSame($enHeaderFirst, $idHeaderFirst);
        $this->assertStringContainsString($idHeaderFirst, $idCsv);

        $owner->forceFill(['locale' => 'en'])->save();
        $enCsvRes = $this->actingAs($owner)->withSession($session)->get(route('reports.export', ['type' => 'valuation']));
        $enCsvRes->assertOk();
        $this->assertStringContainsString($enHeaderFirst, $enCsvRes->streamedContent());
    }
}
