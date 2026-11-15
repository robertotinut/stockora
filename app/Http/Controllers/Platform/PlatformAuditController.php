<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Services\PaymentInstructionService;
use Illuminate\Http\Request;

class PlatformAuditController extends Controller
{
    public function index(Request $request)
    {
        $action = strtoupper(trim((string) $request->query('action', '')));
        $companyId = (int) $request->query('company_id', 0);
        $search = trim((string) $request->query('search', ''));

        $logs = PlatformAuditLog::with(['company', 'subscriptionPlan', 'subscriptionFeature', 'platformAdmin', 'actorUser'])
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('note', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $availableActions = PlatformAuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $companies = Company::orderBy('name')->get(['id', 'code', 'name']);

        return view('platform.logs.index', compact(
            'logs',
            'action',
            'companyId',
            'search',
            'availableActions',
            'companies'
        ));
    }

    public function settings(PaymentInstructionService $instructionService)
    {
        $platformAdmins = PlatformAdmin::orderBy('id')->get(['id', 'name', 'email', 'is_active', 'last_login_at', 'created_at']);
        $support = config('stockora.support', []);
        $mailTransport = config('mail.default');
        $databaseName = config('database.connections.' . config('database.default') . '.database');
        $paymentInstructions = $instructionService->getAllInstructions();
        $channelTypes = PaymentInstruction::CHANNEL_TYPES;
        $billingSettings = $instructionService->getBillingSettings();

        return view('platform.settings.index', compact(
            'platformAdmins',
            'support',
            'mailTransport',
            'databaseName',
            'paymentInstructions',
            'channelTypes',
            'billingSettings'
        ));
    }
}
