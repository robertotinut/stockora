<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalRequest;
use App\Models\User;
use App\Support\BcMathHelper;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionPaymentService
{
    public const PROOF_DISK = 'local';

    public const ALLOWED_PROOF_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public const ALLOWED_PROOF_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct(
        private TenantContext $tenantContext,
        private PlatformAuditService $audit,
        private CompanyLifecycleService $lifecycle,
        private PaymentInstructionService $instructionService
    ) {}

    /**
     * Authoritative server-side price & duration calculation from configured SubscriptionPlan.
     * Never trust customer-supplied amounts.
     */
    public function calculateQuote(SubscriptionPlan $plan, ?string $requestedInterval = null): array
    {
        if (!$plan->is_active) {
            throw new DomainException(__('payments.errors.plan_inactive'));
        }

        if ($plan->is_internal) {
            throw new DomainException(__('payments.errors.internal_plan_not_billable'));
        }

        if ($plan->isFreePlan()) {
            throw new DomainException(__('payments.errors.free_plan_no_payment'));
        }

        $planInterval = strtoupper((string) $plan->billing_interval);
        $interval = strtoupper(trim((string) ($requestedInterval ?: $planInterval)));
        $basePrice = BcMathHelper::normalize((string) $plan->price, 2);

        if ($planInterval === SubscriptionPlan::INTERVAL_MONTHLY) {
            if ($interval === SubscriptionPlan::INTERVAL_MONTHLY) {
                $amount = $basePrice;
                $durationDays = 30;
            } elseif ($interval === SubscriptionPlan::INTERVAL_YEARLY) {
                $amount = bcmul($basePrice, '12', 2);
                $durationDays = 365;
            } else {
                throw new DomainException(__('payments.errors.invalid_billing_interval'));
            }
        } elseif ($planInterval === SubscriptionPlan::INTERVAL_YEARLY) {
            if ($interval !== SubscriptionPlan::INTERVAL_YEARLY) {
                throw new DomainException(__('payments.errors.yearly_plan_only'));
            }
            $amount = $basePrice;
            $durationDays = 365;
        } elseif ($planInterval === SubscriptionPlan::INTERVAL_CUSTOM) {
            if (!in_array($interval, [SubscriptionPlan::INTERVAL_CUSTOM, SubscriptionPlan::INTERVAL_MONTHLY, SubscriptionPlan::INTERVAL_YEARLY], true)) {
                throw new DomainException(__('payments.errors.invalid_billing_interval'));
            }
            $amount = $basePrice;
            $durationDays = $interval === SubscriptionPlan::INTERVAL_YEARLY ? 365 : 30;
        } else {
            throw new DomainException(__('payments.errors.invalid_billing_interval'));
        }

        BcMathHelper::assertPositive($amount, 'amount', 2);

        return [
            'billing_interval' => $interval,
            'duration_days' => $durationDays,
            'base_plan_price' => $basePrice,
            'amount' => $amount,
            'currency' => 'IDR',
        ];
    }

    public function submitCustomerPayment(
        Company $company,
        User $user,
        array $input,
        ?UploadedFile $proofFile = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        $settings = $this->instructionService->getBillingSettings();
        if (!($settings['allow_customer_payment_submissions'] ?? true)) {
            throw new DomainException(__('payments.errors.submissions_disabled'));
        }

        $storedProof = null;

        try {
            return DB::transaction(function () use ($company, $user, $input, $proofFile, $ipAddress, $settings, &$storedProof): SubscriptionPayment {
                return $this->tenantContext->withoutScope(function () use ($company, $user, $input, $proofFile, $ipAddress, $settings, &$storedProof): SubscriptionPayment {
                    $lockedCompany = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                    $effectiveStatus = $lockedCompany->effectiveStatus();

                    if ($effectiveStatus === Company::STATUS_CANCELLED) {
                        throw new DomainException(__('payments.errors.cancelled_workspace'));
                    }

                    $plan = SubscriptionPlan::where('id', (int) ($input['subscription_plan_id'] ?? 0))->first();
                    if (!$plan) {
                        throw new DomainException(__('payments.errors.plan_not_found'));
                    }

                    $quote = $this->calculateQuote($plan, $input['billing_interval'] ?? null);

                    $instruction = null;
                    if (!empty($input['payment_instruction_id'])) {
                        $instruction = PaymentInstruction::where('id', (int) $input['payment_instruction_id'])
                            ->where('is_active', true)
                            ->first();
                        if (!$instruction) {
                            throw new DomainException(__('payments.errors.invalid_payment_method'));
                        }
                    } else {
                        throw new DomainException(__('payments.errors.invalid_payment_method'));
                    }

                    $existingPending = SubscriptionPayment::where('company_id', $lockedCompany->id)
                        ->where('status', SubscriptionPayment::STATUS_PENDING)
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                    $replaceExisting = (bool) ($input['replace_existing_pending'] ?? false);
                    if ($existingPending && !$replaceExisting) {
                        throw new DomainException(__('payments.errors.duplicate_pending_exists', [
                            'ref' => $existingPending->payment_reference,
                        ]));
                    }

                    $paymentDate = Carbon::parse((string) ($input['payment_date'] ?? now()->toDateString()))->toDateString();
                    $requestType = $this->resolveCustomerRequestType($lockedCompany, $effectiveStatus, $plan);

                    $tempRef = 'PAY-' . now()->format('Ym') . '-' . Str::upper(Str::random(10));

                    if ($proofFile !== null) {
                        $storedProof = $this->storeProofFile(
                            companyId: (int) $lockedCompany->id,
                            referenceHint: $tempRef,
                            file: $proofFile,
                            maxKb: (int) ($settings['payment_proof_max_kb'] ?? PaymentInstructionService::DEFAULT_MAX_PROOF_KB)
                        );
                    }

                    $payment = SubscriptionPayment::create([
                        'payment_reference' => $tempRef,
                        'company_id' => (int) $lockedCompany->id,
                        'subscription_plan_id' => (int) $plan->id,
                        'payment_instruction_id' => (int) $instruction->id,
                        'requested_by_user_id' => (int) $user->id,
                        'recorded_by_platform_admin_id' => null,
                        'source' => SubscriptionPayment::SOURCE_CUSTOMER,
                        'request_type' => $requestType,
                        'status' => SubscriptionPayment::STATUS_PENDING,
                        'plan_code_snapshot' => $plan->code,
                        'plan_name_snapshot' => $plan->name,
                        'plan_tier_snapshot' => $plan->tier,
                        'billing_interval' => $quote['billing_interval'],
                        'duration_days' => $quote['duration_days'],
                        'base_plan_price' => $quote['base_plan_price'],
                        'amount' => $quote['amount'],
                        'currency' => $quote['currency'],
                        'payment_method_code' => $instruction->method_code,
                        'payment_method_name' => $instruction->method_name,
                        'customer_transfer_reference' => $this->cleanNullable($input['customer_transfer_reference'] ?? null),
                        'payer_name' => $this->cleanNullable($input['payer_name'] ?? null),
                        'payment_date' => $paymentDate,
                        'payment_received_confirmed' => false,
                        'customer_note' => $this->cleanNullable($input['customer_note'] ?? null),
                        'admin_note' => null,
                        'payment_proof_disk' => $storedProof['disk'] ?? null,
                        'payment_proof_path' => $storedProof['path'] ?? null,
                        'payment_proof_original_name' => $storedProof['original_name'] ?? null,
                        'payment_proof_mime' => $storedProof['mime'] ?? null,
                        'payment_proof_size' => $storedProof['size'] ?? null,
                        'payment_proof_hash' => $storedProof['hash'] ?? null,
                        'submitted_at' => now(),
                    ]);

                    $finalReference = 'PAY-' . now()->format('Ym') . '-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
                    $payment->update(['payment_reference' => $finalReference]);

                    if ($existingPending && $replaceExisting) {
                        $existingPending->update([
                            'status' => SubscriptionPayment::STATUS_CANCELLED,
                            'cancelled_at' => now(),
                            'cancelled_by_user_id' => (int) $user->id,
                            'cancellation_reason' => "Replaced by newer payment submission {$finalReference}.",
                            'replaced_by_payment_id' => (int) $payment->id,
                        ]);

                        if ($existingPending->renewalRequest) {
                            $existingPending->renewalRequest->update([
                                'status' => SubscriptionRenewalRequest::STATUS_CANCELLED,
                                'processed_at' => now(),
                            ]);
                        }
                    }

                    $renewal = SubscriptionRenewalRequest::create([
                        'request_number' => 'REN-' . now()->format('Ym') . '-' . Str::upper(Str::random(10)),
                        'company_id' => (int) $lockedCompany->id,
                        'current_plan_id' => $lockedCompany->subscription_plan_id,
                        'requested_plan_id' => (int) $plan->id,
                        'subscription_payment_id' => (int) $payment->id,
                        'requested_by_user_id' => (int) $user->id,
                        'request_type' => $requestType,
                        'billing_interval' => $quote['billing_interval'],
                        'duration_days' => $quote['duration_days'],
                        'quoted_amount' => $quote['amount'],
                        'status' => SubscriptionRenewalRequest::STATUS_PENDING_VERIFICATION,
                        'current_status_snapshot' => $effectiveStatus,
                        'current_expires_at_snapshot' => $lockedCompany->access_expires_at,
                        'customer_note' => $payment->customer_note,
                    ]);
                    $renewal->update([
                        'request_number' => 'REN-' . now()->format('Ym') . '-' . str_pad((string) $renewal->id, 5, '0', STR_PAD_LEFT),
                    ]);

                    $this->audit->record(
                        action: 'PAYMENT_SUBMITTED',
                        companyId: (int) $lockedCompany->id,
                        actorUserId: (int) $user->id,
                        previousValues: null,
                        newValues: [
                            'payment_reference' => $finalReference,
                            'plan_code' => $plan->code,
                            'request_type' => $requestType,
                            'billing_interval' => $quote['billing_interval'],
                            'amount' => $quote['amount'],
                            'status' => SubscriptionPayment::STATUS_PENDING,
                            'has_proof' => $storedProof !== null,
                        ],
                        note: "Customer submitted manual payment confirmation {$finalReference} for {$plan->name} ({$quote['billing_interval']}).",
                        ipAddress: $ipAddress,
                        subscriptionPlanId: (int) $plan->id,
                        subscriptionPaymentId: (int) $payment->id
                    );

                    return $payment->fresh(['company', 'subscriptionPlan', 'paymentInstruction', 'renewalRequest']);
                });
            });
        } catch (Throwable $e) {
            if ($storedProof && !empty($storedProof['path'])) {
                Storage::disk(self::PROOF_DISK)->delete($storedProof['path']);
            }
            throw $e;
        }
    }

    public function recordManualOfflinePayment(
        Company $company,
        PlatformAdmin $admin,
        array $input,
        ?UploadedFile $proofFile = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        $settings = $this->instructionService->getBillingSettings();
        $storedProof = null;

        try {
            return DB::transaction(function () use ($company, $admin, $input, $proofFile, $ipAddress, $settings, &$storedProof): SubscriptionPayment {
                return $this->tenantContext->withoutScope(function () use ($company, $admin, $input, $proofFile, $ipAddress, $settings, &$storedProof): SubscriptionPayment {
                    $lockedCompany = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                    $effectiveStatus = $lockedCompany->effectiveStatus();

                    $plan = SubscriptionPlan::where('id', (int) ($input['subscription_plan_id'] ?? 0))->first();
                    if (!$plan) {
                        throw new DomainException(__('payments.errors.plan_not_found'));
                    }
                    if ($plan->isFreePlan()) {
                        throw new DomainException(__('payments.errors.free_plan_no_payment'));
                    }

                    $targetStatus = strtoupper(trim((string) ($input['recorded_status'] ?? SubscriptionPayment::STATUS_PENDING)));
                    if (!in_array($targetStatus, [SubscriptionPayment::STATUS_PENDING, SubscriptionPayment::STATUS_VERIFIED], true)) {
                        throw new DomainException(__('payments.errors.invalid_recorded_status'));
                    }

                    $paymentReceivedConfirmed = (bool) ($input['payment_received_confirmed'] ?? false);
                    if ($targetStatus === SubscriptionPayment::STATUS_VERIFIED && !$paymentReceivedConfirmed) {
                        throw new DomainException(__('payments.errors.confirm_received_required'));
                    }

                    $billingInterval = strtoupper(trim((string) ($input['billing_interval'] ?? $plan->billing_interval)));
                    if (!array_key_exists($billingInterval, SubscriptionPlan::BILLING_INTERVALS)) {
                        $billingInterval = SubscriptionPlan::INTERVAL_MONTHLY;
                    }

                    $durationDays = max(1, min(3650, (int) ($input['duration_days'] ?? ($billingInterval === SubscriptionPlan::INTERVAL_YEARLY ? 365 : 30))));
                    $amountInput = trim((string) ($input['amount'] ?? ''));
                    if ($amountInput === '') {
                        $quote = $this->calculateQuote($plan, $billingInterval);
                        $amount = $quote['amount'];
                    } else {
                        $amount = BcMathHelper::normalize($amountInput, 2);
                        BcMathHelper::assertPositive($amount, 'amount', 2);
                    }

                    $instruction = null;
                    if (!empty($input['payment_instruction_id'])) {
                        $instruction = PaymentInstruction::find((int) $input['payment_instruction_id']);
                    }

                    $methodName = $this->cleanNullable($input['payment_method_name'] ?? null)
                        ?: ($instruction?->method_name ?? 'Manual Offline Transfer');
                    $methodCode = $instruction?->method_code ?? 'ADMIN_MANUAL_OFFLINE';

                    $requestType = strtoupper(trim((string) ($input['request_type'] ?? '')));
                    if (!array_key_exists($requestType, SubscriptionPayment::REQUEST_TYPES)) {
                        $requestType = $this->resolveCustomerRequestType($lockedCompany, $effectiveStatus, $plan);
                    }

                    $paymentDate = Carbon::parse((string) ($input['payment_date'] ?? now()->toDateString()))->toDateString();
                    $tempRef = 'PAY-' . now()->format('Ym') . '-' . Str::upper(Str::random(10));

                    if ($proofFile !== null) {
                        $storedProof = $this->storeProofFile(
                            companyId: (int) $lockedCompany->id,
                            referenceHint: $tempRef,
                            file: $proofFile,
                            maxKb: (int) ($settings['payment_proof_max_kb'] ?? PaymentInstructionService::DEFAULT_MAX_PROOF_KB)
                        );
                    }

                    $isVerifiedNow = $targetStatus === SubscriptionPayment::STATUS_VERIFIED;

                    $payment = SubscriptionPayment::create([
                        'payment_reference' => $tempRef,
                        'company_id' => (int) $lockedCompany->id,
                        'subscription_plan_id' => (int) $plan->id,
                        'payment_instruction_id' => $instruction?->id,
                        'requested_by_user_id' => null,
                        'recorded_by_platform_admin_id' => (int) $admin->id,
                        'source' => SubscriptionPayment::SOURCE_ADMIN_MANUAL,
                        'request_type' => $requestType,
                        'status' => $targetStatus,
                        'plan_code_snapshot' => $plan->code,
                        'plan_name_snapshot' => $plan->name,
                        'plan_tier_snapshot' => $plan->tier,
                        'billing_interval' => $billingInterval,
                        'duration_days' => $durationDays,
                        'base_plan_price' => BcMathHelper::normalize((string) $plan->price, 2),
                        'amount' => $amount,
                        'currency' => 'IDR',
                        'payment_method_code' => $methodCode,
                        'payment_method_name' => $methodName,
                        'customer_transfer_reference' => $this->cleanNullable($input['customer_transfer_reference'] ?? null),
                        'payer_name' => $this->cleanNullable($input['payer_name'] ?? null),
                        'payment_date' => $paymentDate,
                        'payment_received_confirmed' => $paymentReceivedConfirmed,
                        'customer_note' => $this->cleanNullable($input['customer_note'] ?? null),
                        'admin_note' => $this->cleanNullable($input['admin_note'] ?? null),
                        'payment_proof_disk' => $storedProof['disk'] ?? null,
                        'payment_proof_path' => $storedProof['path'] ?? null,
                        'payment_proof_original_name' => $storedProof['original_name'] ?? null,
                        'payment_proof_mime' => $storedProof['mime'] ?? null,
                        'payment_proof_size' => $storedProof['size'] ?? null,
                        'payment_proof_hash' => $storedProof['hash'] ?? null,
                        'submitted_at' => now(),
                        'verified_at' => $isVerifiedNow ? now() : null,
                        'verified_by_platform_admin_id' => $isVerifiedNow ? (int) $admin->id : null,
                        'verification_note' => $isVerifiedNow ? ($this->cleanNullable($input['verification_note'] ?? null) ?? 'Recorded as verified offline payment.') : null,
                    ]);

                    $finalReference = 'PAY-' . now()->format('Ym') . '-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
                    $payment->update(['payment_reference' => $finalReference]);

                    $renewal = SubscriptionRenewalRequest::create([
                        'request_number' => 'REN-' . now()->format('Ym') . '-' . Str::upper(Str::random(10)),
                        'company_id' => (int) $lockedCompany->id,
                        'current_plan_id' => $lockedCompany->subscription_plan_id,
                        'requested_plan_id' => (int) $plan->id,
                        'subscription_payment_id' => (int) $payment->id,
                        'requested_by_user_id' => null,
                        'request_type' => $requestType,
                        'billing_interval' => $billingInterval,
                        'duration_days' => $durationDays,
                        'quoted_amount' => $amount,
                        'status' => $isVerifiedNow
                            ? SubscriptionRenewalRequest::STATUS_APPROVED
                            : SubscriptionRenewalRequest::STATUS_PENDING_VERIFICATION,
                        'current_status_snapshot' => $effectiveStatus,
                        'current_expires_at_snapshot' => $lockedCompany->access_expires_at,
                        'admin_decision_note' => $payment->admin_note,
                        'processed_by_platform_admin_id' => $isVerifiedNow ? (int) $admin->id : null,
                        'processed_at' => $isVerifiedNow ? now() : null,
                    ]);
                    $renewal->update([
                        'request_number' => 'REN-' . now()->format('Ym') . '-' . str_pad((string) $renewal->id, 5, '0', STR_PAD_LEFT),
                    ]);

                    // IMPORTANT: Offline recording NEVER silently activates or extends subscription.
                    // Application happens only through explicit applyVerifiedPayment().
                    $this->audit->record(
                        action: 'MANUAL_PAYMENT_RECORDED',
                        companyId: (int) $lockedCompany->id,
                        platformAdminId: (int) $admin->id,
                        newValues: [
                            'payment_reference' => $finalReference,
                            'plan_code' => $plan->code,
                            'request_type' => $requestType,
                            'billing_interval' => $billingInterval,
                            'duration_days' => $durationDays,
                            'amount' => $amount,
                            'status' => $targetStatus,
                            'payment_received_confirmed' => $paymentReceivedConfirmed,
                        ],
                        note: "Recorded offline payment {$finalReference} ({$targetStatus}) for {$lockedCompany->name}; awaiting explicit subscription application.",
                        ipAddress: $ipAddress,
                        subscriptionPlanId: (int) $plan->id,
                        subscriptionPaymentId: (int) $payment->id
                    );

                    return $payment->fresh(['company', 'subscriptionPlan', 'renewalRequest']);
                });
            });
        } catch (Throwable $e) {
            if ($storedProof && !empty($storedProof['path'])) {
                Storage::disk(self::PROOF_DISK)->delete($storedProof['path']);
            }
            throw $e;
        }
    }

    public function verifyPayment(
        SubscriptionPayment $payment,
        PlatformAdmin $admin,
        ?string $verificationNote = null,
        ?string $adminNote = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        return DB::transaction(function () use ($payment, $admin, $verificationNote, $adminNote, $ipAddress): SubscriptionPayment {
            return $this->tenantContext->withoutScope(function () use ($payment, $admin, $verificationNote, $adminNote, $ipAddress): SubscriptionPayment {
                $locked = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isPending()) {
                    throw new DomainException(__('payments.errors.cannot_verify_non_pending', [
                        'status' => $locked->status,
                    ]));
                }

                $previous = [
                    'status' => $locked->status,
                    'verified_at' => null,
                ];

                $locked->update([
                    'status' => SubscriptionPayment::STATUS_VERIFIED,
                    'verified_at' => now(),
                    'verified_by_platform_admin_id' => (int) $admin->id,
                    'payment_received_confirmed' => true,
                    'verification_note' => $this->cleanNullable($verificationNote),
                    'admin_note' => $adminNote !== null ? $this->cleanNullable($adminNote) : $locked->admin_note,
                ]);

                if ($locked->renewalRequest) {
                    $locked->renewalRequest->update([
                        'status' => SubscriptionRenewalRequest::STATUS_APPROVED,
                        'admin_decision_note' => $locked->admin_note,
                        'processed_by_platform_admin_id' => (int) $admin->id,
                        'processed_at' => now(),
                    ]);
                }

                $this->audit->record(
                    action: 'PAYMENT_VERIFIED',
                    companyId: (int) $locked->company_id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: [
                        'status' => $locked->status,
                        'payment_reference' => $locked->payment_reference,
                        'amount' => (string) $locked->amount,
                        'verified_at' => $locked->verified_at?->toIso8601String(),
                    ],
                    note: $this->cleanNullable($verificationNote)
                        ?: "Verified manual payment {$locked->payment_reference} (Rp " . number_format((float) $locked->amount, 0, ',', '.') . ').',
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $locked->subscription_plan_id,
                    subscriptionPaymentId: (int) $locked->id
                );

                return $locked->fresh(['company', 'subscriptionPlan', 'verifiedByAdmin', 'renewalRequest']);
            });
        });
    }

    public function rejectPayment(
        SubscriptionPayment $payment,
        PlatformAdmin $admin,
        string $rejectionReason,
        ?string $adminNote = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        $reason = trim($rejectionReason);
        if ($reason === '') {
            throw new DomainException(__('payments.errors.rejection_reason_required'));
        }

        return DB::transaction(function () use ($payment, $admin, $reason, $adminNote, $ipAddress): SubscriptionPayment {
            return $this->tenantContext->withoutScope(function () use ($payment, $admin, $reason, $adminNote, $ipAddress): SubscriptionPayment {
                $locked = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isPending()) {
                    throw new DomainException(__('payments.errors.cannot_reject_non_pending', [
                        'status' => $locked->status,
                    ]));
                }

                $previous = ['status' => $locked->status];

                $locked->update([
                    'status' => SubscriptionPayment::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'rejected_by_platform_admin_id' => (int) $admin->id,
                    'rejection_reason' => $reason,
                    'admin_note' => $adminNote !== null ? $this->cleanNullable($adminNote) : $locked->admin_note,
                ]);

                if ($locked->renewalRequest) {
                    $locked->renewalRequest->update([
                        'status' => SubscriptionRenewalRequest::STATUS_REJECTED,
                        'rejection_reason' => $reason,
                        'admin_decision_note' => $locked->admin_note,
                        'processed_by_platform_admin_id' => (int) $admin->id,
                        'processed_at' => now(),
                    ]);
                }

                $this->audit->record(
                    action: 'PAYMENT_REJECTED',
                    companyId: (int) $locked->company_id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: [
                        'status' => $locked->status,
                        'payment_reference' => $locked->payment_reference,
                        'rejection_reason' => $reason,
                    ],
                    note: "Rejected manual payment {$locked->payment_reference}: {$reason}",
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $locked->subscription_plan_id,
                    subscriptionPaymentId: (int) $locked->id
                );

                return $locked->fresh(['company', 'subscriptionPlan', 'rejectedByAdmin', 'renewalRequest']);
            });
        });
    }

    public function cancelByCustomer(
        SubscriptionPayment $payment,
        Company $company,
        User $user,
        ?string $reason = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        return DB::transaction(function () use ($payment, $company, $user, $reason, $ipAddress): SubscriptionPayment {
            return $this->tenantContext->withoutScope(function () use ($payment, $company, $user, $reason, $ipAddress): SubscriptionPayment {
                $locked = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->company_id !== (int) $company->id) {
                    throw new DomainException(__('payments.errors.unauthorized_payment'));
                }

                if (!$locked->canCustomerCancel()) {
                    throw new DomainException(__('payments.errors.cannot_cancel_payment'));
                }

                $cleanReason = $this->cleanNullable($reason) ?? 'Cancelled by customer before verification.';

                $locked->update([
                    'status' => SubscriptionPayment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => (int) $user->id,
                    'cancellation_reason' => $cleanReason,
                ]);

                if ($locked->renewalRequest) {
                    $locked->renewalRequest->update([
                        'status' => SubscriptionRenewalRequest::STATUS_CANCELLED,
                        'processed_at' => now(),
                    ]);
                }

                $this->audit->record(
                    action: 'PAYMENT_CANCELLED',
                    companyId: (int) $locked->company_id,
                    actorUserId: (int) $user->id,
                    previousValues: ['status' => SubscriptionPayment::STATUS_PENDING],
                    newValues: [
                        'status' => SubscriptionPayment::STATUS_CANCELLED,
                        'payment_reference' => $locked->payment_reference,
                        'cancellation_reason' => $cleanReason,
                    ],
                    note: "Customer cancelled pending payment {$locked->payment_reference}.",
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $locked->subscription_plan_id,
                    subscriptionPaymentId: (int) $locked->id
                );

                return $locked->fresh(['company', 'subscriptionPlan', 'renewalRequest']);
            });
        });
    }

    public function cancelByAdmin(
        SubscriptionPayment $payment,
        PlatformAdmin $admin,
        ?string $reason = null,
        ?string $ipAddress = null
    ): SubscriptionPayment {
        return DB::transaction(function () use ($payment, $admin, $reason, $ipAddress): SubscriptionPayment {
            return $this->tenantContext->withoutScope(function () use ($payment, $admin, $reason, $ipAddress): SubscriptionPayment {
                $locked = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isPending()) {
                    throw new DomainException(__('payments.errors.cannot_cancel_non_pending', [
                        'status' => $locked->status,
                    ]));
                }

                $cleanReason = $this->cleanNullable($reason) ?? 'Cancelled by Platform Admin before verification.';

                $locked->update([
                    'status' => SubscriptionPayment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_platform_admin_id' => (int) $admin->id,
                    'cancellation_reason' => $cleanReason,
                ]);

                if ($locked->renewalRequest) {
                    $locked->renewalRequest->update([
                        'status' => SubscriptionRenewalRequest::STATUS_CANCELLED,
                        'processed_by_platform_admin_id' => (int) $admin->id,
                        'processed_at' => now(),
                    ]);
                }

                $this->audit->record(
                    action: 'PAYMENT_CANCELLED',
                    companyId: (int) $locked->company_id,
                    platformAdminId: (int) $admin->id,
                    previousValues: ['status' => SubscriptionPayment::STATUS_PENDING],
                    newValues: [
                        'status' => SubscriptionPayment::STATUS_CANCELLED,
                        'payment_reference' => $locked->payment_reference,
                        'cancellation_reason' => $cleanReason,
                    ],
                    note: "Platform Admin cancelled pending payment {$locked->payment_reference}.",
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $locked->subscription_plan_id,
                    subscriptionPaymentId: (int) $locked->id
                );

                return $locked->fresh(['company', 'subscriptionPlan', 'renewalRequest']);
            });
        });
    }

    /**
     * Preview exact resulting status, plan, and expiration date before applying a verified payment.
     */
    public function previewApplicationResult(
        SubscriptionPayment $payment,
        Company $company,
        ?string $expirationAction = null,
        ?int $durationDays = null,
        ?Carbon $startsAt = null,
        ?Carbon $customExpiresAt = null
    ): array {
        $effectiveStatus = $company->effectiveStatus();
        $days = max(1, min(3650, $durationDays ?? ($payment->duration_days ?: 30)));
        $action = strtoupper(trim((string) ($expirationAction ?: SubscriptionPayment::EXPIRY_ACTION_AUTO)));
        $start = ($startsAt ?? now())->copy();

        if (in_array($effectiveStatus, [Company::STATUS_SUSPENDED, Company::STATUS_CANCELLED], true)) {
            return [
                'can_apply' => false,
                'blocked_reason' => __('payments.errors.cannot_apply_suspended_or_cancelled', ['status' => $effectiveStatus]),
                'from_status' => $effectiveStatus,
                'target_status' => $effectiveStatus,
                'applied_action' => null,
                'period_starts_at' => null,
                'period_ends_at' => $company->access_expires_at,
                'expiration_action' => $action,
                'duration_days' => $days,
            ];
        }

        if ($effectiveStatus === Company::STATUS_PENDING_ACTIVATION) {
            $endsAt = $action === SubscriptionPayment::EXPIRY_ACTION_CUSTOM && $customExpiresAt !== null
                ? $customExpiresAt->copy()->endOfDay()
                : $start->copy()->addDays($days)->endOfDay();

            return [
                'can_apply' => true,
                'blocked_reason' => null,
                'from_status' => $effectiveStatus,
                'target_status' => Company::STATUS_ACTIVE,
                'applied_action' => 'ACTIVATE',
                'period_starts_at' => $start,
                'period_ends_at' => $endsAt,
                'expiration_action' => $action,
                'duration_days' => $days,
            ];
        }

        if ($effectiveStatus === Company::STATUS_EXPIRED) {
            $endsAt = $action === SubscriptionPayment::EXPIRY_ACTION_CUSTOM && $customExpiresAt !== null
                ? $customExpiresAt->copy()->endOfDay()
                : $start->copy()->addDays($days)->endOfDay();

            return [
                'can_apply' => true,
                'blocked_reason' => null,
                'from_status' => $effectiveStatus,
                'target_status' => Company::STATUS_ACTIVE,
                'applied_action' => 'REACTIVATE_EXPIRED',
                'period_starts_at' => $start,
                'period_ends_at' => $endsAt,
                'expiration_action' => $action,
                'duration_days' => $days,
            ];
        }

        // ACTIVE company
        $isPlanChange = (int) $company->subscription_plan_id !== (int) $payment->subscription_plan_id
            || $payment->request_type === SubscriptionPayment::TYPE_PLAN_CHANGE;

        $resolvedAction = $action;
        if ($resolvedAction === SubscriptionPayment::EXPIRY_ACTION_AUTO) {
            $resolvedAction = $payment->request_type === SubscriptionPayment::TYPE_PLAN_CHANGE
                ? SubscriptionPayment::EXPIRY_ACTION_KEEP
                : SubscriptionPayment::EXPIRY_ACTION_EXTEND;
        }

        $currentExpires = $company->access_expires_at?->copy();

        if ($resolvedAction === SubscriptionPayment::EXPIRY_ACTION_KEEP) {
            $periodStarts = $company->access_starts_at?->copy() ?? $start;
            $periodEnds = $currentExpires;
        } elseif ($resolvedAction === SubscriptionPayment::EXPIRY_ACTION_CUSTOM && $customExpiresAt !== null) {
            $periodStarts = $start;
            $periodEnds = $customExpiresAt->copy()->endOfDay();
        } elseif ($resolvedAction === SubscriptionPayment::EXPIRY_ACTION_FROM_START) {
            $periodStarts = $start;
            $periodEnds = $start->copy()->addDays($days)->endOfDay();
        } else {
            // EXTEND_CURRENT: extend from existing future expiration so paid period is never shortened
            $base = ($currentExpires !== null && $currentExpires->isFuture()) ? $currentExpires->copy() : $start->copy();
            $periodStarts = $base->copy();
            $periodEnds = $base->copy()->addDays($days)->endOfDay();
        }

        return [
            'can_apply' => true,
            'blocked_reason' => null,
            'from_status' => $effectiveStatus,
            'target_status' => Company::STATUS_ACTIVE,
            'applied_action' => $isPlanChange ? 'CHANGE_PLAN' : 'RENEW_ACTIVE',
            'period_starts_at' => $periodStarts,
            'period_ends_at' => $periodEnds,
            'expiration_action' => $resolvedAction,
            'duration_days' => $days,
        ];
    }

    /**
     * Explicitly apply a VERIFIED payment to activate, renew, or switch plan on the customer workspace.
     * Protected by DB locks and idempotent applied_to_subscription_at check.
     */
    public function applyVerifiedPayment(
        SubscriptionPayment $payment,
        PlatformAdmin $admin,
        array $options = [],
        ?string $ipAddress = null
    ): SubscriptionPayment {
        return DB::transaction(function () use ($payment, $admin, $options, $ipAddress): SubscriptionPayment {
            return $this->tenantContext->withoutScope(function () use ($payment, $admin, $options, $ipAddress): SubscriptionPayment {
                $lockedPayment = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if (!$lockedPayment->isVerified()) {
                    throw new DomainException(__('payments.errors.must_be_verified_to_apply'));
                }

                if ($lockedPayment->isApplied()) {
                    throw new DomainException(__('payments.errors.already_applied', [
                        'ref' => $lockedPayment->payment_reference,
                    ]));
                }

                $lockedCompany = Company::where('id', $lockedPayment->company_id)->lockForUpdate()->firstOrFail();
                $plan = SubscriptionPlan::where('id', $lockedPayment->subscription_plan_id)->first();
                if (!$plan) {
                    throw new DomainException(__('payments.errors.plan_not_found'));
                }

                $durationDays = isset($options['duration_days']) && (int) $options['duration_days'] > 0
                    ? max(1, min(3650, (int) $options['duration_days']))
                    : (int) ($lockedPayment->duration_days ?: 30);

                $startsAt = !empty($options['starts_at'])
                    ? Carbon::parse((string) $options['starts_at'])
                    : now();
                $customExpiresAt = !empty($options['custom_expires_at'])
                    ? Carbon::parse((string) $options['custom_expires_at'])->endOfDay()
                    : null;
                $expirationAction = (string) ($options['expiration_action'] ?? SubscriptionPayment::EXPIRY_ACTION_AUTO);

                $preview = $this->previewApplicationResult(
                    payment: $lockedPayment,
                    company: $lockedCompany,
                    expirationAction: $expirationAction,
                    durationDays: $durationDays,
                    startsAt: $startsAt,
                    customExpiresAt: $customExpiresAt
                );

                if (!$preview['can_apply']) {
                    throw new DomainException((string) $preview['blocked_reason']);
                }

                $fromStatus = $preview['from_status'];
                $periodStarts = $preview['period_starts_at'];
                $periodEnds = $preview['period_ends_at'];

                if ($periodEnds !== null && in_array($fromStatus, [Company::STATUS_PENDING_ACTIVATION, Company::STATUS_EXPIRED], true) && $periodEnds->isPast()) {
                    throw new DomainException(__('payments.errors.expiration_must_be_future'));
                }

                if (
                    $fromStatus === Company::STATUS_ACTIVE
                    && $lockedCompany->access_expires_at !== null
                    && $periodEnds !== null
                    && $periodEnds->lt($lockedCompany->access_expires_at)
                    && $preview['expiration_action'] !== SubscriptionPayment::EXPIRY_ACTION_CUSTOM
                ) {
                    throw new DomainException(__('payments.errors.cannot_shorten_active_period'));
                }

                if ($preview['expiration_action'] === SubscriptionPayment::EXPIRY_ACTION_CUSTOM && $periodEnds !== null && $periodEnds->isPast()) {
                    throw new DomainException(__('payments.errors.expiration_must_be_future'));
                }

                $previousCompanyState = [
                    'account_status' => $lockedCompany->account_status,
                    'is_active' => (bool) $lockedCompany->is_active,
                    'access_type' => $lockedCompany->access_type,
                    'subscription_plan_id' => $lockedCompany->subscription_plan_id,
                    'access_starts_at' => $lockedCompany->access_starts_at?->toIso8601String(),
                    'access_expires_at' => $lockedCompany->access_expires_at?->toIso8601String(),
                ];

                $previousPlanId = $lockedCompany->subscription_plan_id;
                $previousExpiresAt = $lockedCompany->access_expires_at?->copy();

                $companyUpdates = [
                    'subscription_plan_id' => (int) $plan->id,
                    'access_type' => $plan->tier,
                    'access_expires_at' => $periodEnds,
                ];

                if ($fromStatus === Company::STATUS_PENDING_ACTIVATION) {
                    if (!$this->lifecycle->canTransition($fromStatus, Company::STATUS_ACTIVE)) {
                        throw new DomainException("Cannot activate workspace from {$fromStatus}.");
                    }
                    $companyUpdates['account_status'] = Company::STATUS_ACTIVE;
                    $companyUpdates['is_active'] = true;
                    $companyUpdates['access_starts_at'] = $periodStarts;
                    $companyUpdates['activated_at'] = $lockedCompany->activated_at ?? now();
                    $companyUpdates['activated_by_platform_admin_id'] = $lockedCompany->activated_by_platform_admin_id ?? (int) $admin->id;
                    $companyUpdates['status_reason'] = null;
                } elseif ($fromStatus === Company::STATUS_EXPIRED) {
                    if (!$this->lifecycle->canTransition($fromStatus, Company::STATUS_ACTIVE)) {
                        throw new DomainException("Cannot reactivate expired workspace from {$fromStatus}.");
                    }
                    $companyUpdates['account_status'] = Company::STATUS_ACTIVE;
                    $companyUpdates['is_active'] = true;
                    $companyUpdates['access_starts_at'] = $lockedCompany->access_starts_at ?? $periodStarts;
                    $companyUpdates['activated_at'] = $lockedCompany->activated_at ?? now();
                    $companyUpdates['activated_by_platform_admin_id'] = $lockedCompany->activated_by_platform_admin_id ?? (int) $admin->id;
                    $companyUpdates['status_reason'] = null;
                }

                $lockedCompany->update($companyUpdates);
                $lockedCompany->refresh();

                $adminNote = isset($options['admin_note']) ? $this->cleanNullable($options['admin_note']) : $lockedPayment->admin_note;

                $lockedPayment->update([
                    'applied_to_subscription_at' => now(),
                    'applied_by_platform_admin_id' => (int) $admin->id,
                    'applied_action' => $preview['applied_action'],
                    'period_starts_at' => $periodStarts,
                    'period_ends_at' => $periodEnds,
                    'previous_plan_id' => $previousPlanId,
                    'previous_expires_at' => $previousExpiresAt,
                    'admin_note' => $adminNote,
                ]);

                if ($lockedPayment->renewalRequest) {
                    $lockedPayment->renewalRequest->update([
                        'status' => SubscriptionRenewalRequest::STATUS_APPLIED,
                        'approved_starts_at' => $periodStarts,
                        'approved_ends_at' => $periodEnds,
                        'admin_decision_note' => $adminNote,
                        'processed_by_platform_admin_id' => (int) $admin->id,
                        'processed_at' => now(),
                    ]);
                }

                $newCompanyState = [
                    'account_status' => $lockedCompany->account_status,
                    'is_active' => (bool) $lockedCompany->is_active,
                    'access_type' => $lockedCompany->access_type,
                    'subscription_plan_id' => $lockedCompany->subscription_plan_id,
                    'access_starts_at' => $lockedCompany->access_starts_at?->toIso8601String(),
                    'access_expires_at' => $lockedCompany->access_expires_at?->toIso8601String(),
                    'payment_reference' => $lockedPayment->payment_reference,
                    'applied_action' => $preview['applied_action'],
                ];

                $customNote = $this->cleanNullable($options['note'] ?? null);

                $this->audit->record(
                    action: 'PAYMENT_APPLIED',
                    companyId: (int) $lockedCompany->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previousCompanyState,
                    newValues: $newCompanyState,
                    note: $customNote ?: "Applied verified payment {$lockedPayment->payment_reference} ({$preview['applied_action']}) -> {$plan->name}.",
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $plan->id,
                    subscriptionPaymentId: (int) $lockedPayment->id
                );

                return $lockedPayment->fresh(['company.subscriptionPlan', 'subscriptionPlan', 'appliedByAdmin', 'renewalRequest']);
            });
        });
    }

    private function resolveCustomerRequestType(Company $company, string $effectiveStatus, SubscriptionPlan $plan): string
    {
        if ($effectiveStatus === Company::STATUS_PENDING_ACTIVATION) {
            return SubscriptionPayment::TYPE_ACTIVATION;
        }

        if ($company->subscription_plan_id !== null && (int) $company->subscription_plan_id !== (int) $plan->id) {
            return SubscriptionPayment::TYPE_PLAN_CHANGE;
        }

        return SubscriptionPayment::TYPE_RENEWAL;
    }

    private function storeProofFile(int $companyId, string $referenceHint, UploadedFile $file, int $maxKb): array
    {
        if (!$file->isValid()) {
            throw new DomainException(__('payments.errors.invalid_proof_upload'));
        }

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes <= 0 || $sizeBytes > ($maxKb * 1024)) {
            throw new DomainException(__('payments.errors.proof_too_large', ['max' => (int) ceil($maxKb / 1024)]));
        }

        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        if (!in_array($mime, self::ALLOWED_PROOF_MIMES, true) || !in_array($ext, self::ALLOWED_PROOF_EXTENSIONS, true)) {
            throw new DomainException(__('payments.errors.unsupported_proof_type'));
        }

        $directory = "payment_proofs/company_{$companyId}";
        $filename = $referenceHint . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $storedPath = $file->storeAs($directory, $filename, self::PROOF_DISK);

        if (!$storedPath) {
            throw new DomainException(__('payments.errors.proof_store_failed'));
        }

        $rawOrig = basename((string) $file->getClientOriginalName());
        $safeOrig = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawOrig) ?: ("proof.{$ext}");
        $realPath = $file->getRealPath();
        $hash = $realPath && is_file($realPath) ? hash_file('sha256', $realPath) : null;

        return [
            'disk' => self::PROOF_DISK,
            'path' => $storedPath,
            'original_name' => Str::limit($safeOrig, 240, ''),
            'mime' => $mime,
            'size' => $sizeBytes,
            'hash' => $hash,
        ];
    }

    private function cleanNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
