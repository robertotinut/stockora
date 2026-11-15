<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Services\PaymentInstructionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlatformPaymentInstructionController extends Controller
{
    public function store(Request $request, PaymentInstructionService $service)
    {
        $request->merge([
            'method_code' => strtoupper(trim((string) $request->input('method_code', ''))),
        ]);

        $validated = $request->validate([
            'method_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]{3,50}$/', Rule::unique('payment_instructions', 'method_code')],
            'method_name' => ['required', 'string', 'max:120'],
            'channel_type' => ['required', 'string', Rule::in(array_keys(PaymentInstruction::CHANNEL_TYPES))],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'instructions_id' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $service->createInstruction(
                admin: $this->platformAdmin(),
                attributes: array_merge($validated, [
                    'is_active' => $request->boolean('is_active', true),
                    'is_default' => $request->boolean('is_default'),
                ]),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.settings.index')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.settings.index')
            ->with('success', __('payments.flash.instruction_created', ['name' => $validated['method_name']]));
    }

    public function update(Request $request, PaymentInstruction $instruction, PaymentInstructionService $service)
    {
        $validated = $request->validate([
            'method_name' => ['required', 'string', 'max:120'],
            'channel_type' => ['required', 'string', Rule::in(array_keys(PaymentInstruction::CHANNEL_TYPES))],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'instructions_id' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $service->updateInstruction(
                instruction: $instruction,
                admin: $this->platformAdmin(),
                attributes: array_merge($validated, [
                    'is_active' => $request->boolean('is_active'),
                    'is_default' => $request->boolean('is_default'),
                ]),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.settings.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.settings.index')
            ->with('success', __('payments.flash.instruction_updated', ['code' => $instruction->method_code]));
    }

    public function toggle(Request $request, PaymentInstruction $instruction, PaymentInstructionService $service)
    {
        try {
            $updated = $service->toggleInstruction($instruction, $this->platformAdmin(), $request->ip());
        } catch (DomainException $e) {
            return redirect()->route('platform.settings.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.settings.index')
            ->with('success', __('payments.flash.instruction_toggled', [
                'code' => $updated->method_code,
                'state' => $updated->is_active ? __('app.active') : __('app.inactive'),
            ]));
    }

    public function updateBillingSettings(Request $request, PaymentInstructionService $service)
    {
        $validated = $request->validate([
            'renewal_reminder_windows' => ['required', 'string', 'max:50'],
            'payment_proof_max_kb' => ['required', 'integer', 'min:512', 'max:5120'],
            'allow_customer_payment_submissions' => ['nullable', 'boolean'],
            'verification_sla_note' => ['nullable', 'string', 'max:500'],
            'verification_sla_note_id' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->updateBillingSettings(
                admin: $this->platformAdmin(),
                attributes: array_merge($validated, [
                    'allow_customer_payment_submissions' => $request->boolean('allow_customer_payment_submissions'),
                ]),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.settings.index')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.settings.index')
            ->with('success', __('payments.flash.billing_settings_updated'));
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();
        if (!$admin instanceof PlatformAdmin) {
            abort(403, 'Platform administrator identity required.');
        }

        return $admin;
    }
}
