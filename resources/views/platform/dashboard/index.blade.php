@extends('layouts.platform')

@section('title', __('platform.dashboard_title'))
@section('page_title', __('platform.dashboard.page_title'))
@section('page_subtitle', __('platform.dashboard.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.dashboard.breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.payments.index', ['status' => 'PENDING']) }}" class="btn btn-white">
    <i data-feather="credit-card" class="svg-14"></i> {{ __('navigation.platform_payments') }} ({{ $pendingPaymentsCount }})
  </a>
  <a href="{{ route('platform.plans.index') }}" class="btn btn-white">
    <i data-feather="layers" class="svg-14"></i> {{ __('platform.dashboard.plans_btn', ['count' => $activePlansCount]) }}
  </a>
  <a href="{{ route('platform.pending.index') }}" class="btn btn-white">
    <i data-feather="clock" class="svg-14"></i> {{ __('platform.dashboard.pending_queue_btn', ['count' => $pendingCount]) }}
  </a>
  <a href="{{ route('platform.customers.create') }}" class="btn btn-brand-01">
    <i data-feather="plus" class="svg-14"></i> {{ __('platform.dashboard.create_customer_btn') }}
  </a>
@endsection

@section('content')
  {{-- Primary Platform KPI Grid (6 required cards) --}}
  <div class="row row-sm mg-b-20">
    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_total_companies') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="briefcase" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($totalCompanies) }}</div>
          <div class="tx-11 tx-color-03 mg-t-6">{{ __('platform.dashboard.kpi_total_companies_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_pending') }}</div>
            <span class="stk-kpi-icon" style="background-color:#fef3c7;color:#b45309;"><i data-feather="clock" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($pendingCount) }}</div>
          <div class="tx-11 mg-t-6"><a href="{{ route('platform.pending.index') }}" class="tx-semibold">{{ __('platform.dashboard.review_queue_link') }}</a></div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_active') }}</div>
            <span class="stk-kpi-icon" style="background-color:#dcfce7;color:#15803d;"><i data-feather="check-circle" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($activeCount) }}</div>
          <div class="tx-11 tx-color-03 mg-t-6">{{ __('platform.dashboard.kpi_active_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_suspended') }}</div>
            <span class="stk-kpi-icon" style="background-color:#fee2e2;color:#b91c1c;"><i data-feather="pause-circle" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($suspendedCount) }}</div>
          <div class="tx-11 tx-color-03 mg-t-6">{{ __('platform.dashboard.kpi_suspended_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_expired') }}</div>
            <span class="stk-kpi-icon" style="background-color:#f1f5f9;color:#475569;"><i data-feather="calendar" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($expiredCount) }}</div>
          <div class="tx-11 tx-color-03 mg-t-6">{{ __('platform.dashboard.kpi_expired_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-4 col-xl-2 mg-b-15">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-8">
            <div class="stk-kpi-label mg-b-0">{{ __('platform.dashboard.kpi_users') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="users" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($totalUsers) }}</div>
          <div class="tx-11 tx-color-03 mg-t-6">{{ __('platform.dashboard.kpi_users_sub') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Actions & Pending Queue + Distribution --}}
  <div class="row row-sm mg-b-20">
    <div class="col-lg-8 mg-b-15 mg-lg-b-0">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.pending_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.dashboard.pending_sub') }}</span>
          </div>
          <a href="{{ route('platform.pending.index') }}" class="btn btn-white btn-xs">{{ __('platform.dashboard.view_all_pending') }}</a>
        </div>
        <div class="card-body p-0">
          @if($pendingQueue->isEmpty())
            <div class="p-4 text-center tx-color-03 tx-13">
              {{ __('platform.dashboard.no_pending') }}
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('platform.dashboard.col_company') }}</th>
                    <th>{{ __('platform.dashboard.col_owner') }}</th>
                    <th>{{ __('platform.dashboard.col_industry') }}</th>
                    <th>{{ __('platform.dashboard.col_registered') }}</th>
                    <th class="text-right">{{ __('app.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($pendingQueue as $pendingCo)
                    @php $owner = $pendingCo->users->first(); @endphp
                    <tr>
                      <td>
                        <div class="tx-semibold tx-color-01">{{ $pendingCo->name }}</div>
                        <span class="stk-tenant-code">{{ $pendingCo->code }}</span>
                      </td>
                      <td>
                        <div class="tx-13 tx-color-01">{{ $owner?->name ?? '—' }}</div>
                        <div class="tx-11 tx-color-03">{{ $owner?->email ?? '—' }}</div>
                      </td>
                      <td class="tx-12 tx-color-02">{{ $pendingCo->industry ?? '—' }}</td>
                      <td class="tx-12 tx-color-03">{{ $pendingCo->created_at ? \App\Support\StockoraFormat::dateTime($pendingCo->created_at) : '—' }}</td>
                      <td class="text-right">
                        <a href="{{ route('platform.customers.show', $pendingCo) }}#activateSection" class="btn btn-brand-01 btn-xs">
                          {{ __('platform.dashboard.review_activate_btn') }}
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.breakdown_heading') }}</h6>
          <span class="tx-12 tx-color-03">{{ __('platform.dashboard.breakdown_sub') }}</span>
        </div>
        <div class="card-body">
          @php $safeTotal = max(1, $totalCompanies); @endphp
          <div class="mg-b-20">
            @foreach(\App\Models\Company::ACCOUNT_STATUSES as $stKey => $stMeta)
              @php $stVal = (int) ($statusCounts[$stKey] ?? 0); @endphp
              <div class="mg-b-10">
                <div class="d-flex justify-content-between tx-12 mg-b-4">
                  <span class="tx-medium tx-color-01">{{ __('platform.account_statuses.' . $stKey) }}</span>
                  <span class="tx-numeric tx-semibold">{{ $stVal }}</span>
                </div>
                <div class="stk-progress-track">
                  <div class="stk-progress-fill bg-{{ $stMeta['badge'] === 'dark' ? 'secondary' : $stMeta['badge'] }}" style="width: {{ round(($stVal / $safeTotal) * 100) }}%;"></div>
                </div>
              </div>
            @endforeach
          </div>

          <div class="pt-3 border-top">
            <div class="tx-11 text-uppercase tx-semibold tx-color-03 mg-b-10">{{ __('platform.dashboard.tier_distribution') }}</div>
            <div class="d-flex flex-wrap justify-content-between" style="gap: 8px;">
              @foreach(\App\Models\Company::ACCESS_TYPES as $tierKey => $tierMeta)
                <div class="p-2 flex-fill text-center" style="border: 1px solid var(--stk-border); border-radius: 8px; min-width: 72px;">
                  @include('partials.access-type-badge', ['accessType' => $tierKey])
                  <div class="tx-16 tx-bold tx-color-01 tx-numeric mg-t-4">{{ (int) ($accessTypeCounts[$tierKey] ?? 0) }}</div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Trend, Expiring Soon, Recent Activity --}}
  <div class="row row-sm mg-b-20">
    <div class="col-lg-4 mg-b-15 mg-lg-b-0">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.trend_heading') }}</h6>
          <span class="tx-12 tx-color-03">{{ __('platform.dashboard.trend_sub') }}</span>
        </div>
        <div class="card-body">
          @foreach($registrationTrend as $dayRow)
            <div class="stk-trend-row">
              <span class="stk-trend-label">{{ $dayRow['label'] }}</span>
              <div class="stk-progress-track flex-grow-1">
                <div class="stk-progress-fill bg-primary" style="width: {{ round(($dayRow['count'] / $trendMax) * 100) }}%;"></div>
              </div>
              <span class="stk-trend-count tx-numeric">{{ $dayRow['count'] }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-lg-4 mg-b-15 mg-lg-b-0">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.expiring_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.dashboard.expiring_sub') }}</span>
          </div>
          <a href="{{ route('platform.access.index', ['expiring_soon' => 1]) }}" class="btn btn-white btn-xs">{{ __('platform.dashboard.access_list_btn') }}</a>
        </div>
        <div class="card-body p-0">
          @if($expiringSoon->isEmpty())
            <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.dashboard.no_expiring') }}</div>
          @else
            <div class="list-group list-group-flush">
              @foreach($expiringSoon as $expCo)
                <a href="{{ route('platform.customers.show', $expCo) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                  <div>
                    <div class="tx-semibold tx-color-01">{{ $expCo->name }}</div>
                    <div class="tx-11 tx-color-03">{{ $expCo->code }} &bull; {{ __('platform.access_types.' . $expCo->access_type) }}</div>
                  </div>
                  <span class="badge badge-warning">{{ $expCo->access_expires_at ? \App\Support\StockoraFormat::shortDate($expCo->access_expires_at) : '—' }}</span>
                </a>
              @endforeach
            </div>
          @endif

          @if($recentSuspensions->isNotEmpty())
            <div class="px-3 py-2 bg-light border-top border-bottom tx-11 text-uppercase tx-semibold tx-color-03">{{ __('platform.dashboard.recent_suspensions') }}</div>
            <div class="list-group list-group-flush">
              @foreach($recentSuspensions as $susCo)
                <a href="{{ route('platform.customers.show', $susCo) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                  <div>
                    <div class="tx-semibold tx-color-01">{{ $susCo->name }}</div>
                    <div class="tx-11 text-danger text-truncate" style="max-width: 190px;">{{ $susCo->status_reason }}</div>
                  </div>
                  <span class="tx-11 tx-color-03">{{ $susCo->suspended_at?->locale(app()->getLocale())->translatedFormat('d M') }}</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.recent_regs_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.dashboard.recent_regs_sub') }}</span>
          </div>
          <a href="{{ route('platform.customers.index') }}" class="btn btn-white btn-xs">{{ __('platform.dashboard.all_customers_btn') }}</a>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            @foreach($recentRegistrations as $regCo)
              <a href="{{ route('platform.customers.show', $regCo) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div class="mg-r-10">
                  <div class="tx-semibold tx-color-01">{{ $regCo->name }}</div>
                  <div class="tx-11 tx-color-03">{{ $regCo->code }} &bull; {{ $regCo->created_at ? \App\Support\StockoraFormat::dateTime($regCo->created_at) : '—' }}</div>
                </div>
                @include('partials.company-status-badge', ['status' => $regCo->effectiveStatus()])
              </a>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Pending / Unapplied Manual Payments --}}
  @if($recentPendingPayments->isNotEmpty())
    <div class="card mg-b-20">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
        <div>
          <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.platform.index_heading') }}</h6>
          <span class="tx-12 tx-color-03">{{ __('payments.platform.kpi_pending') }}: {{ $pendingPaymentsCount }} &bull; {{ __('payments.platform.kpi_verified_unapplied') }}: {{ $verifiedUnappliedPaymentsCount }}</span>
        </div>
        <a href="{{ route('platform.payments.index') }}" class="btn btn-white btn-xs">{{ __('navigation.platform_payments') }}</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('payments.customer.col_reference') }}</th>
                <th>{{ __('platform.dashboard.col_company') }}</th>
                <th>{{ __('payments.customer.col_plan_period') }}</th>
                <th class="text-right">{{ __('payments.customer.col_amount') }}</th>
                <th>{{ __('payments.customer.col_status') }}</th>
                <th class="text-right">{{ __('payments.customer.col_actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentPendingPayments as $rPay)
                <tr>
                  <td><a href="{{ route('platform.payments.show', $rPay) }}" class="tx-semibold">{{ $rPay->payment_reference }}</a></td>
                  <td>{{ $rPay->company?->name ?? '—' }}</td>
                  <td>{{ $rPay->plan_name_snapshot }} ({{ $rPay->billingIntervalLabel() }})</td>
                  <td class="text-right tx-numeric">{{ $rPay->formattedAmount() }}</td>
                  <td>
                    <span class="badge {{ $rPay->statusBadgeClass() }}">{{ $rPay->statusLabel() }}</span>
                    @if($rPay->isVerified() && !$rPay->isApplied())
                      <span class="badge badge-light ml-1">{{ __('payments.customer.awaiting_apply_badge') }}</span>
                    @endif
                  </td>
                  <td class="text-right">
                    <a href="{{ route('platform.payments.show', $rPay) }}" class="btn btn-xs btn-brand-01">{{ __('payments.platform.review_btn') }}</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Recent Platform Audit Activity --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.dashboard.audit_heading') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('platform.dashboard.audit_sub') }}</span>
      </div>
      <a href="{{ route('platform.logs.index') }}" class="btn btn-white btn-xs">{{ __('platform.dashboard.full_log_btn') }}</a>
    </div>
    <div class="card-body p-0">
      @if($recentLogs->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.dashboard.no_audit_logs') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.dashboard.col_time') }}</th>
                <th>{{ __('platform.dashboard.col_company') }}</th>
                <th>{{ __('app.action') }}</th>
                <th>{{ __('platform.dashboard.col_actor') }}</th>
                <th>{{ __('platform.dashboard.col_details') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentLogs as $log)
                <tr>
                  <td class="tx-12 tx-color-03 text-nowrap">{{ $log->created_at ? \App\Support\StockoraFormat::dateTime($log->created_at) : '—' }}</td>
                  <td>
                    @if($log->company)
                      <a href="{{ route('platform.customers.show', $log->company) }}" class="tx-semibold">{{ $log->company->name }}</a>
                      <div class="tx-11 tx-color-03">{{ $log->company->code }}</div>
                    @elseif($log->subscriptionPlan)
                      <a href="{{ route('platform.plans.show', $log->subscriptionPlan) }}" class="tx-semibold">{{ __('platform.common.plan_prefix') }}: {{ $log->subscriptionPlan->name }}</a>
                      <div class="tx-11 tx-color-03">{{ $log->subscriptionPlan->code }}</div>
                    @elseif($log->subscriptionFeature)
                      <a href="{{ route('platform.features.index') }}" class="tx-semibold">{{ __('platform.common.feature_prefix') }}: {{ $log->subscriptionFeature->name }}</a>
                      <div class="tx-11 tx-color-03"><code>{{ $log->subscriptionFeature->feature_key }}</code></div>
                    @else
                      <span class="tx-color-03">&mdash;</span>
                    @endif
                  </td>
                  <td><span class="badge badge-light tx-11">{{ $log->action }}</span></td>
                  <td class="tx-12">
                    @if($log->platformAdmin)
                      <span class="tx-semibold">{{ $log->platformAdmin->name }}</span>
                      <div class="tx-11 tx-color-03">{{ __('platform.common.platform_admin') }}</div>
                    @elseif($log->actorUser)
                      <span class="tx-semibold">{{ $log->actorUser->name }}</span>
                      <div class="tx-11 tx-color-03">{{ __('platform.common.customer_user') }}</div>
                    @else
                      <span class="tx-color-03">{{ __('platform.common.system') }}</span>
                    @endif
                  </td>
                  <td class="tx-12 tx-color-02">{{ $log->note ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
