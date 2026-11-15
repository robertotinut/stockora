<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CompanyLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformDashboardController extends Controller
{
    public function index(CompanyLifecycleService $lifecycle)
    {
        $lifecycle->syncExpiredCompanies();

        $statusCounts = Company::selectRaw('account_status, COUNT(*) AS total')
            ->groupBy('account_status')
            ->pluck('total', 'account_status');

        $accessTypeCounts = Company::selectRaw('access_type, COUNT(*) AS total')
            ->groupBy('access_type')
            ->pluck('total', 'access_type');

        $totalCompanies = (int) $statusCounts->sum();
        $pendingCount = (int) ($statusCounts[Company::STATUS_PENDING_ACTIVATION] ?? 0);
        $activeCount = (int) ($statusCounts[Company::STATUS_ACTIVE] ?? 0);
        $suspendedCount = (int) ($statusCounts[Company::STATUS_SUSPENDED] ?? 0);
        $expiredCount = (int) ($statusCounts[Company::STATUS_EXPIRED] ?? 0);
        $cancelledCount = (int) ($statusCounts[Company::STATUS_CANCELLED] ?? 0);
        $totalUsers = User::count();

        $pendingQueue = Company::where('account_status', Company::STATUS_PENDING_ACTIVATION)
            ->with(['users' => fn ($q) => $q->wherePivot('role', 'owner')->wherePivot('is_active', 1)])
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        $expiringSoon = Company::where('account_status', Company::STATUS_ACTIVE)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', now()->addDays(14))
            ->orderBy('access_expires_at')
            ->limit(6)
            ->get();

        $recentRegistrations = Company::with(['users' => fn ($q) => $q->wherePivot('role', 'owner')])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $recentActivations = Company::whereNotNull('activated_at')
            ->with('activatedByAdmin')
            ->orderByDesc('activated_at')
            ->limit(6)
            ->get();

        $recentSuspensions = Company::where('account_status', Company::STATUS_SUSPENDED)
            ->with('suspendedByAdmin')
            ->orderByDesc('suspended_at')
            ->limit(5)
            ->get();

        $recentLogs = PlatformAuditLog::with(['company', 'subscriptionPlan', 'subscriptionFeature', 'platformAdmin', 'actorUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $activePlansCount = SubscriptionPlan::where('is_active', 1)->count();
        $activeFeaturesCount = SubscriptionFeature::where('is_active', 1)->count();
        $pendingPaymentsCount = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_PENDING)->count();
        $verifiedUnappliedPaymentsCount = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_VERIFIED)
            ->whereNull('applied_to_subscription_at')
            ->count();
        $recentPendingPayments = SubscriptionPayment::whereIn('status', [SubscriptionPayment::STATUS_PENDING, SubscriptionPayment::STATUS_VERIFIED])
            ->whereNull('applied_to_subscription_at')
            ->with(['company', 'subscriptionPlan'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        // 14-day company onboarding trend directly from DB timestamps
        $startDay = Carbon::today()->subDays(13);
        $rawTrend = DB::table('companies')
            ->selectRaw('DATE(created_at) AS reg_date, COUNT(*) AS total')
            ->where('created_at', '>=', $startDay->copy()->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'reg_date');

        $registrationTrend = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $startDay->copy()->addDays($i);
            $key = $day->toDateString();
            $registrationTrend[] = [
                'label' => $day->format('d M'),
                'count' => (int) ($rawTrend[$key] ?? 0),
            ];
        }
        $trendMax = max(1, (int) collect($registrationTrend)->max('count'));

        return view('platform.dashboard.index', compact(
            'totalCompanies',
            'pendingCount',
            'activeCount',
            'suspendedCount',
            'expiredCount',
            'cancelledCount',
            'totalUsers',
            'statusCounts',
            'accessTypeCounts',
            'pendingQueue',
            'expiringSoon',
            'recentRegistrations',
            'recentActivations',
            'recentSuspensions',
            'recentLogs',
            'registrationTrend',
            'trendMax',
            'activePlansCount',
            'activeFeaturesCount',
            'pendingPaymentsCount',
            'verifiedUnappliedPaymentsCount',
            'recentPendingPayments'
        ));
    }
}
