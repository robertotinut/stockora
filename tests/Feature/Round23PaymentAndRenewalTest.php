<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalRequest;
use App\Models\User;
use App\Services\PaymentInstructionService;
use App\Services\RenewalReminderService;
use App\Services\SubscriptionPaymentService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Round23PaymentAndRenewalTest extends TestCase
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
            'name' => 'R23 Platform Admin',
            'email' => 'r23-admin-' . uniqid() . '@stockora.app',
            'locale' => $locale,
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => true,
        ]);
    }

    private function createCompanyWithMember(
        string $status = Company::STATUS_ACTIVE,
        string $planCode = Company::ACCESS_STARTER,
        ?int $daysUntilExpiry = 30,
        string $role = 'owner',
        string $locale = 'en'
    ): array {
        $tag = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $plan = SubscriptionPlan::where('code', $planCode)->firstOrFail();

        $user = User::factory()->create([
            'email' => "r23-{$tag}@stockora.test",
            'name' => "User {$tag}",
            'locale' => $locale,
        ]);

        $isOperational = $status === Company::STATUS_ACTIVE;
        $expiresAt = $daysUntilExpiry === null
            ? null
            : ($daysUntilExpiry >= 0
                ? now()->addDays($daysUntilExpiry)->endOfDay()
                : now()->subDays(abs($daysUntilExpiry))->endOfDay());

        $company = Company::create([
            'code' => "R23{$tag}",
            'name' => "PT Payment QA {$tag}",
            'industry' => 'Distributor / Supplier',
            'is_active' => $isOperational,
            'account_status' => $status,
            'access_type' => $plan->tier,
            'subscription_plan_id' => $plan->id,
            'access_starts_at' => $isOperational ? now()->subDays(5) : null,
            'access_expires_at' => $expiresAt,
            'activated_at' => $isOperational ? now()->subDays(5) : null,
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $company, $plan];
    }

    public function test_pending_activation_customer_can_submit_payment_with_private_proof_and_remains_pending(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $company] = $this->createCompanyWithMember(Company::STATUS_PENDING_ACTIVATION, Company::ACCESS_STARTER, null);
        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['status_company_id' => $company->id])
            ->get(route('company.access-status'))
            ->assertOk()
            ->assertSee(route('subscription.index'));

        $this->actingAs($owner)
            ->withSession(['status_company_id' => $company->id])
            ->get(route('subscription.payments.create'))
            ->assertOk()
            ->assertSee($starter->name);

        $proof = UploadedFile::fake()->image('transfer-slip.png', 600, 400);

        $response = $this->actingAs($owner)
            ->withSession(['status_company_id' => $company->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'TRF-R23-0001',
                'payer_name' => 'PT Pemohon QA',
                'customer_note' => 'Please activate Starter workspace.',
                'payment_proof' => $proof,
            ]);

        $payment = SubscriptionPayment::where('company_id', $company->id)->firstOrFail();
        $response->assertRedirect(route('subscription.payments.show', $payment->id));

        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $payment->status);
        $this->assertSame(SubscriptionPayment::TYPE_ACTIVATION, $payment->request_type);
        $this->assertSame(SubscriptionPayment::SOURCE_CUSTOMER, $payment->source);
        $this->assertSame('299000.00', (string) $payment->amount);
        $this->assertSame('local', $payment->payment_proof_disk);
        $this->assertNotNull($payment->payment_proof_path);
        Storage::disk('local')->assertExists($payment->payment_proof_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        // Workspace MUST stay PENDING_ACTIVATION until admin manually verifies & applies
        $company->refresh();
        $this->assertSame(Company::STATUS_PENDING_ACTIVATION, $company->account_status);
        $this->assertFalse((bool) $company->is_active);

        $this->assertDatabaseHas('subscription_renewal_requests', [
            'subscription_payment_id' => $payment->id,
            'company_id' => $company->id,
            'status' => SubscriptionRenewalRequest::STATUS_PENDING_VERIFICATION,
        ]);

        // Authorized owner can download private proof
        $this->actingAs($owner)
            ->withSession(['status_company_id' => $company->id])
            ->get(route('subscription.payments.proof', $payment->id))
            ->assertOk();
    }

    public function test_server_ignores_tampered_amount_and_blocks_free_plan_payment_checkout(): void
    {
        [$owner, $company] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 30);
        $pro = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $free = SubscriptionPlan::where('code', Company::ACCESS_FREE)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        // Attempt yearly checkout with tampered client amount = 1
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $pro->id,
                'billing_interval' => 'YEARLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'amount' => 1,
                'customer_transfer_reference' => 'TAMPER-ATTEMPT',
            ])
            ->assertRedirect();

        $payment = SubscriptionPayment::where('company_id', $company->id)->firstOrFail();
        // 799000 * 12 = 9588000.00 computed strictly server-side
        $this->assertSame('9588000.00', (string) $payment->amount);
        $this->assertSame(365, $payment->duration_days);
        $this->assertSame('YEARLY', $payment->billing_interval);

        // FREE tier checkout rejected cleanly
        $paymentService = app(SubscriptionPaymentService::class);
        $this->expectException(DomainException::class);
        $paymentService->calculateQuote($free, 'MONTHLY');
    }

    public function test_duplicate_pending_submission_handling_and_customer_cancellation(): void
    {
        [$owner, $company] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 20);
        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $pro = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'customer_transfer_reference' => 'FIRST-PENDING',
            ])
            ->assertRedirect();

        $first = SubscriptionPayment::where('company_id', $company->id)->firstOrFail();

        // Duplicate blocked when replace_existing_pending is omitted
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $pro->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, SubscriptionPayment::where('company_id', $company->id)->count());

        // Safe replacement supersedes old pending
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('subscription.payments.store'), [
                'subscription_plan_id' => $pro->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
                'replace_existing_pending' => '1',
                'customer_transfer_reference' => 'SECOND-REPLACING',
            ])
            ->assertRedirect();

        $first->refresh();
        $second = SubscriptionPayment::where('company_id', $company->id)->where('id', '!=', $first->id)->firstOrFail();

        $this->assertSame(SubscriptionPayment::STATUS_CANCELLED, $first->status);
        $this->assertSame($second->id, $first->replaced_by_payment_id);
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $second->status);

        // Customer cancels the second pending payment
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('subscription.payments.cancel', $second->id), [
                'cancellation_reason' => 'Uploaded wrong bank account',
            ])
            ->assertRedirect(route('subscription.payments.show', $second->id));

        $second->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_CANCELLED, $second->status);
    }

    public function test_rejection_and_verification_with_double_verify_and_double_apply_guards(): void
    {
        [$owner, $company] = $this->createCompanyWithMember(Company::STATUS_EXPIRED, Company::ACCESS_STARTER, -2);
        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();
        $admin = $this->createPlatformAdmin();
        $paymentService = app(SubscriptionPaymentService::class);

        $rejectedPay = $paymentService->submitCustomerPayment($company, $owner, [
            'subscription_plan_id' => $starter->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
            'customer_transfer_reference' => 'INVALID-SLIP',
        ]);

        // Empty rejection reason fails validation cleanly via HTTP
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.reject', $rejectedPay), [
                'rejection_reason' => '',
            ])
            ->assertSessionHasErrors('rejection_reason');

        // Valid rejection hides internal admin_note from customer while showing rejection_reason
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.reject', $rejectedPay), [
                'rejection_reason' => 'Transfer nominal did not match invoice.',
                'admin_note' => 'INTERNAL-FRAUD-REVIEW-ONLY',
            ])
            ->assertRedirect(route('platform.payments.show', $rejectedPay));

        $rejectedPay->refresh();
        $company->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_REJECTED, $rejectedPay->status);
        $this->assertSame(Company::STATUS_EXPIRED, $company->account_status);

        $this->actingAs($owner)
            ->withSession(['status_company_id' => $company->id])
            ->get(route('subscription.payments.show', $rejectedPay->id))
            ->assertOk()
            ->assertSee('Transfer nominal did not match invoice.')
            ->assertDontSee('INTERNAL-FRAUD-REVIEW-ONLY');

        // New submission -> verify -> explicit double verify blocked -> apply -> double apply blocked
        $goodPay = $paymentService->submitCustomerPayment($company, $owner, [
            'subscription_plan_id' => $starter->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
            'customer_transfer_reference' => 'VALID-SLIP-123',
        ]);

        // Confirm checkbox required on verify
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.verify', $goodPay), [])
            ->assertSessionHasErrors('confirm_verify');

        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.verify', $goodPay), [
                'confirm_verify' => '1',
                'verification_note' => 'Matched Bank BCA statement.',
                'admin_note' => 'SECRET-ADMIN-LEDGER-REF',
            ])
            ->assertRedirect(route('platform.payments.show', $goodPay));

        $goodPay->refresh();
        $company->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_VERIFIED, $goodPay->status);
        // Verification alone has NOT reactivated expired company yet until applied
        $this->assertSame(Company::STATUS_EXPIRED, $company->account_status);

        // Cannot verify twice or reject after verified
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.verify', $goodPay), ['confirm_verify' => '1'])
            ->assertSessionHas('error');

        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.reject', $goodPay), ['rejection_reason' => 'Too late'])
            ->assertSessionHas('error');

        // Explicit Apply transitions EXPIRED -> ACTIVE
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.apply', $goodPay), [
                'confirm_apply' => '1',
                'expiration_action' => 'AUTO',
                'duration_days' => 30,
            ])
            ->assertRedirect(route('platform.payments.show', $goodPay));

        $goodPay->refresh();
        $company->refresh();
        $this->assertNotNull($goodPay->applied_to_subscription_at);
        $this->assertSame('REACTIVATE_EXPIRED', $goodPay->applied_action);
        $this->assertSame(Company::STATUS_ACTIVE, $company->account_status);
        $this->assertTrue((bool) $company->is_active);
        $this->assertTrue($company->access_expires_at->isFuture());

        // Double apply is blocked cleanly
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.apply', $goodPay), [
                'confirm_apply' => '1',
                'expiration_action' => 'AUTO',
            ])
            ->assertSessionHas('error');
    }

    public function test_pending_activation_to_active_and_active_renewal_extends_without_shortening(): void
    {
        $admin = $this->createPlatformAdmin();
        $paymentService = app(SubscriptionPaymentService::class);
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        // 1. Pending activation -> verify -> apply -> ACTIVE
        [$pendingOwner, $pendingCo] = $this->createCompanyWithMember(Company::STATUS_PENDING_ACTIVATION, Company::ACCESS_FREE, null);
        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();

        $actPay = $paymentService->submitCustomerPayment($pendingCo, $pendingOwner, [
            'subscription_plan_id' => $starter->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
        ]);

        $paymentService->verifyPayment($actPay, $admin, 'Confirmed activation transfer');
        $appliedAct = $paymentService->applyVerifiedPayment($actPay, $admin);

        $pendingCo->refresh();
        $this->assertSame(Company::STATUS_ACTIVE, $pendingCo->account_status);
        $this->assertSame($starter->id, $pendingCo->subscription_plan_id);
        $this->assertSame('ACTIVATE', $appliedAct->applied_action);

        // 2. Active renewal extends cleanly from existing +20d expiration (~ +50d total)
        [$activeOwner, $activeCo] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 20);
        $originalExpiry = $activeCo->access_expires_at->copy();

        $renPay = $paymentService->submitCustomerPayment($activeCo, $activeOwner, [
            'subscription_plan_id' => $starter->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
        ]);
        $paymentService->verifyPayment($renPay, $admin);
        $paymentService->applyVerifiedPayment($renPay, $admin);

        $activeCo->refresh();
        $this->assertSame(Company::STATUS_ACTIVE, $activeCo->account_status);
        $this->assertTrue($activeCo->access_expires_at->gt($originalExpiry));
        $diffDays = (int) round($originalExpiry->diffInDays($activeCo->access_expires_at));
        $this->assertGreaterThanOrEqual(29, $diffDays);
        $this->assertLessThanOrEqual(31, $diffDays);
    }

    public function test_suspended_company_cannot_auto_reactivate_via_payment_verification_or_apply(): void
    {
        [$owner, $suspendedCo] = $this->createCompanyWithMember(Company::STATUS_SUSPENDED, Company::ACCESS_STARTER, 15);
        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $admin = $this->createPlatformAdmin();
        $paymentService = app(SubscriptionPaymentService::class);
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        $pay = $paymentService->submitCustomerPayment($suspendedCo, $owner, [
            'subscription_plan_id' => $starter->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
        ]);

        $paymentService->verifyPayment($pay, $admin);
        $suspendedCo->refresh();
        $this->assertSame(Company::STATUS_SUSPENDED, $suspendedCo->account_status);

        $this->expectException(DomainException::class);
        $paymentService->applyVerifiedPayment($pay, $admin);
    }

    public function test_plan_change_preserves_existing_expiration_by_default(): void
    {
        [$owner, $activeCo] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 45);
        $pro = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $admin = $this->createPlatformAdmin();
        $paymentService = app(SubscriptionPaymentService::class);
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();

        $originalExpiry = $activeCo->access_expires_at->toDateTimeString();

        $upgradePay = $paymentService->submitCustomerPayment($activeCo, $owner, [
            'subscription_plan_id' => $pro->id,
            'billing_interval' => 'MONTHLY',
            'payment_instruction_id' => $instruction->id,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertSame(SubscriptionPayment::TYPE_PLAN_CHANGE, $upgradePay->request_type);
        $paymentService->verifyPayment($upgradePay, $admin);
        $paymentService->applyVerifiedPayment($upgradePay, $admin);

        $activeCo->refresh();
        $this->assertSame($pro->id, $activeCo->subscription_plan_id);
        $this->assertSame(Company::ACCESS_PROFESSIONAL, $activeCo->access_type);
        $this->assertSame($originalExpiry, $activeCo->access_expires_at->toDateTimeString());
    }

    public function test_admin_manual_offline_payment_requires_confirmation_and_never_auto_activates(): void
    {
        [, $pendingCo] = $this->createCompanyWithMember(Company::STATUS_PENDING_ACTIVATION, Company::ACCESS_FREE, null);
        $pro = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $admin = $this->createPlatformAdmin();

        // Missing explicit payment_received_confirmed when status = VERIFIED -> error
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.store'), [
                'company_id' => $pendingCo->id,
                'subscription_plan_id' => $pro->id,
                'request_type' => SubscriptionPayment::TYPE_MANUAL_OFFLINE,
                'billing_interval' => 'MONTHLY',
                'duration_days' => 30,
                'amount' => '799000',
                'payment_method_name' => 'Corporate Invoice Wire',
                'payment_date' => now()->toDateString(),
                'recorded_status' => SubscriptionPayment::STATUS_VERIFIED,
                'payment_received_confirmed' => '0',
            ])
            ->assertSessionHas('error');

        // Confirmed offline payment creates VERIFIED record without silently activating workspace
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payments.store'), [
                'company_id' => $pendingCo->id,
                'subscription_plan_id' => $pro->id,
                'request_type' => SubscriptionPayment::TYPE_MANUAL_OFFLINE,
                'billing_interval' => 'MONTHLY',
                'duration_days' => 30,
                'amount' => '799000',
                'payment_method_name' => 'Corporate Invoice Wire',
                'payment_date' => now()->toDateString(),
                'recorded_status' => SubscriptionPayment::STATUS_VERIFIED,
                'payment_received_confirmed' => '1',
                'admin_note' => 'Offline wire received #INV-881',
            ])
            ->assertRedirect();

        $pendingCo->refresh();
        $this->assertSame(Company::STATUS_PENDING_ACTIVATION, $pendingCo->account_status);
        $offline = SubscriptionPayment::where('company_id', $pendingCo->id)->firstOrFail();
        $this->assertSame(SubscriptionPayment::SOURCE_ADMIN_MANUAL, $offline->source);
        $this->assertSame(SubscriptionPayment::STATUS_VERIFIED, $offline->status);
        $this->assertNull($offline->applied_to_subscription_at);
        $this->assertSame($admin->id, $offline->recorded_by_platform_admin_id);
    }

    public function test_renewal_reminder_stages_dashboard_banner_and_platform_settings_crud(): void
    {
        $reminderService = app(RenewalReminderService::class);

        [, $coUnlimited] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_FREE, null);
        [, $coHealthy] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 60);
        [, $co30] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 25);
        [$owner7, $co7] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 6);
        [, $co3] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 2);
        [, $coExpired] = $this->createCompanyWithMember(Company::STATUS_EXPIRED, Company::ACCESS_STARTER, -1);

        $this->assertSame(RenewalReminderService::STAGE_NONE, $reminderService->evaluateCompany($coUnlimited)['stage']);
        $this->assertSame(RenewalReminderService::STAGE_HEALTHY, $reminderService->evaluateCompany($coHealthy)['stage']);
        $this->assertSame(RenewalReminderService::STAGE_REMINDER_30, $reminderService->evaluateCompany($co30)['stage']);
        $this->assertSame(RenewalReminderService::STAGE_REMINDER_7, $reminderService->evaluateCompany($co7)['stage']);
        $this->assertSame(RenewalReminderService::STAGE_REMINDER_3, $reminderService->evaluateCompany($co3)['stage']);
        $this->assertSame(RenewalReminderService::STAGE_EXPIRED, $reminderService->evaluateCompany($coExpired)['stage']);

        // Dashboard displays renewal reminder banner for owner within 7-day window
        $this->actingAs($owner7)
            ->withSession(['active_company_id' => $co7->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('billingReminderBanner')
            ->assertSee(route('subscription.payments.create'));

        // Platform admin adds payment instruction and updates billing settings
        $admin = $this->createPlatformAdmin();
        $this->actingAs($admin, 'platform')
            ->post(route('platform.payment-instructions.store'), [
                'method_code' => 'BANK_BRI_TEST_' . substr(uniqid(), -4),
                'method_name' => 'Bank BRI Demo Transfer',
                'channel_type' => PaymentInstruction::CHANNEL_BANK_TRANSFER,
                'bank_name' => 'Bank BRI',
                'account_name' => 'PT Stockora QA',
                'account_number' => '000-777-8888 (Demo)',
                'sort_order' => 5,
                'is_active' => '1',
            ])
            ->assertRedirect(route('platform.settings.index'));

        $this->actingAs($admin, 'platform')
            ->post(route('platform.settings.billing.update'), [
                'renewal_reminder_windows' => '30, 14, 7, 3',
                'payment_proof_max_kb' => 4096,
                'allow_customer_payment_submissions' => '1',
            ])
            ->assertRedirect(route('platform.settings.index'));

        $this->assertSame([30, 14, 7, 3], app(PaymentInstructionService::class)->getBillingSettings()['renewal_reminder_windows']);
    }

    public function test_tenant_isolation_rbac_and_en_id_localization(): void
    {
        Storage::fake('local');
        [$ownerA, $companyA] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 10, 'owner', 'id');
        [$ownerB, $companyB] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 10, 'owner', 'en');
        [$staffA] = $this->createCompanyWithMember(Company::STATUS_ACTIVE, Company::ACCESS_STARTER, 10, 'staff', 'en');

        // Attach staffA to companyA as staff (no company_settings perm)
        DB::table('company_user')->insert([
            'company_id' => $companyA->id,
            'user_id' => $staffA->id,
            'role' => 'staff',
            'is_default' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $starter = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $instruction = PaymentInstruction::where('is_active', true)->firstOrFail();
        $paymentA = app(SubscriptionPaymentService::class)->submitCustomerPayment(
            $companyA,
            $ownerA,
            [
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'MONTHLY',
                'payment_instruction_id' => $instruction->id,
                'payment_date' => now()->toDateString(),
            ],
            UploadedFile::fake()->image('tenant-a.png')
        );

        // Tenant B cannot view, download proof, or cancel Tenant A's payment
        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('subscription.payments.show', $paymentA->id))
            ->assertForbidden();

        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('subscription.payments.proof', $paymentA->id))
            ->assertForbidden();

        $this->actingAs($ownerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->post(route('subscription.payments.cancel', $paymentA->id))
            ->assertForbidden();

        // Non-owner Staff in Company A without company_settings permission is blocked
        $this->actingAs($staffA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('subscription.index'))
            ->assertForbidden();

        // Tenant user cannot access platform payment routes
        $this->actingAs($ownerA)
            ->get(route('platform.payments.index'))
            ->assertRedirect(route('platform.login'));

        // Indonesian localization renders cleanly
        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id, 'locale' => 'id'])
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('Langganan & Pembayaran Manual');

        $admin = $this->createPlatformAdmin('id');
        $this->actingAs($admin, 'platform')
            ->withSession(['platform_locale' => 'id'])
            ->get(route('platform.payments.index'))
            ->assertOk()
            ->assertSee('Catatan Pembayaran Manual');

        $this->assertGreaterThan(0, PlatformAuditLog::where('subscription_payment_id', $paymentA->id)->count());
    }
}
