@extends($useAdminLayout ? 'layouts.admin' : 'layouts.billing-shell')

@section('title', __('payments.customer.page_title'))
@section('page_title', __('payments.customer.page_heading'))
@section('page_subtitle', __('payments.customer.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('payments.customer.breadcrumb') }}</li>
@endsection

@section('actions')
  @if(Route::has('plans.index'))
    <a href="{{ route('plans.index') }}" class="btn btn-white" target="_blank" rel="noopener">
      <i data-feather="columns" class="svg-14"></i> {{ __('payments.customer.compare_plans_btn') }}
    </a>
  @endif
  @if($canManageBilling && $effectiveStatus !== \App\Models\Company::STATUS_CANCELLED && ($billingSettings['allow_customer_payment_submissions'] ?? true))
    <a href="{{ route('subscription.payments.create') }}" class="btn btn-brand-01">
      <i data-feather="upload" class="svg-14"></i> {{ __('payments.customer.submit_payment_btn') }}
    </a>
  @endif
@endsection

@section('content')
  @include('partials.billing-reminder-banner', [
    'reminder' => $reminder,
    'company' => $company,
    'resolvedPlanName' => $resolvedPlan?->name,
  ])

  @if($effectiveStatus === \App\Models\Company::STATUS_CANCELLED)
    <div class="alert alert-dark mg-b-20">{{ __('payments.customer.cancelled_blocked_banner') }}</div>
  @elseif($effectiveStatus === \App\Models\Company::STATUS_SUSPENDED)
    <div class="alert alert-danger mg-b-20">{{ __('payments.customer.suspended_notice_banner') }}</div>
  @endif

  @if($pendingPayment)
    <div class="alert alert-info mg-b-20 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center" style="gap: 10px 16px;">
      <div>
        <strong class="d-block mg-b-2">{{ __('payments.customer.pending_notice_title', ['ref' => $pendingPayment->payment_reference]) }}</strong>
        <div class="tx-13">
          {{ __('payments.customer.pending_notice_body', [
              'date' => \App\Support\StockoraFormat::date($pendingPayment->payment_date),
              'plan' => $pendingPayment->plan_name_snapshot,
              'amount' => $pendingPayment->formattedAmount(),
          ]) }}
        </div>
      </div>
      <div class="d-flex flex-wrap flex-shrink-0" style="gap: 8px;">
        <a href="{{ route('subscription.payments.show', $pendingPayment->id) }}" class="btn btn-xs btn-brand-01">
          {{ __('payments.customer.view_payment_btn') }}
        </a>
      </div>
    </div>
  @endif

  <div class="row row-sm mg-b-20">
    {{-- Current Subscription Summary --}}
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.customer.current_subscription') }}</h6>
          @include('partials.company-status-badge', ['status' => $effectiveStatus])
        </div>
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mg-b-14 pb-3 border-bottom">
            <div>
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.workspace') }}</div>
              <div class="tx-15 tx-bold tx-color-01">{{ $company->name }}</div>
              <span class="stk-tenant-code">{{ $company->code }}</span>
            </div>
            <div class="text-right">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.current_plan') }}</div>
              <div class="tx-15 tx-bold tx-color-01">{{ $resolvedPlan?->name ?? __('subscription.no_plan') }}</div>
              @if($resolvedPlan)
                <span class="tx-12 tx-color-03">{{ $resolvedPlan->formattedPrice() }} / {{ $resolvedPlan->billingIntervalLabel() }}</span>
              @endif
            </div>
          </div>

          <div class="row row-sm tx-13 mg-b-14">
            <div class="col-6 mg-b-10">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.activated_at') }}</div>
              <div class="tx-semibold tx-color-01">
                {{ $company->activated_at ? \App\Support\StockoraFormat::dateTime($company->activated_at) : __('platform.common.pending') }}
              </div>
            </div>
            <div class="col-6 mg-b-10">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.expires_at') }}</div>
              <div class="tx-semibold tx-color-01">
                @if($company->access_expires_at)
                  {{ \App\Support\StockoraFormat::dateTime($company->access_expires_at) }}
                  @if($reminder['days_remaining'] !== null && $reminder['days_remaining'] > 0)
                    <span class="d-block tx-11 tx-color-03">{{ __('payments.customer.days_remaining', ['days' => $reminder['days_remaining']]) }}</span>
                  @endif
                @else
                  {{ __('payments.customer.unlimited_access') }}
                @endif
              </div>
            </div>
          </div>

          @if($resolvedPlan && $resolvedPlan->is_internal)
            <div class="alert alert-light border tx-12 mb-0">
              <i data-feather="shield" class="svg-13 mg-r-4"></i> {{ __('payments.customer.internal_plan_note') }}
            </div>
          @elseif($resolvedPlan && $resolvedPlan->isFreePlan())
            <div class="alert alert-light border tx-12 mb-0">
              <i data-feather="info" class="svg-13 mg-r-4"></i> {{ __('payments.customer.free_plan_note') }}
            </div>
          @else
            <div class="alert alert-light border tx-12 mb-0">
              <i data-feather="shield" class="svg-13 mg-r-4"></i> {{ __('payments.customer.manual_policy_note') }}
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Official Manual Payment Instructions --}}
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.customer.instructions_heading') }}</h6>
          <span class="tx-12 tx-color-03">{{ __('payments.customer.instructions_sub') }}</span>
        </div>
        <div class="card-body">
          @if($instructions->isEmpty())
            <div class="tx-13 tx-color-03">{{ __('payments.customer.no_instructions') }}</div>
          @else
            <div class="row row-sm">
              @foreach($instructions as $inst)
                <div class="col-md-6 mg-b-12">
                  <div class="p-3 h-100 border rounded" style="background-color: var(--stk-bg-canvas);">
                    <div class="d-flex justify-content-between align-items-start mg-b-6" style="gap: 6px;">
                      <strong class="tx-13 tx-color-01">{{ $inst->method_name }}</strong>
                      @if($inst->is_default)
                        <span class="badge badge-primary">{{ __('payments.customer.default_channel') }}</span>
                      @endif
                    </div>
                    <div class="tx-11 tx-color-03 mg-b-6">{{ $inst->channelLabel() }} @if($inst->bank_name) &bull; {{ $inst->bank_name }} @endif</div>
                    @if($inst->account_number)
                      <div class="tx-13 tx-bold tx-numeric tx-color-01">{{ $inst->account_number }}</div>
                    @endif
                    @if($inst->account_name)
                      <div class="tx-12 tx-color-02 mg-b-6">{{ $inst->account_name }}</div>
                    @endif
                    @if($inst->localizedInstructions())
                      <div class="tx-11 tx-color-03 pt-2 border-top">{{ $inst->localizedInstructions() }}</div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif
          <div class="tx-12 tx-color-03 mg-t-4">
            <i data-feather="clock" class="svg-12 mg-r-4"></i>
            {{ app()->getLocale() === 'id' ? ($billingSettings['verification_sla_note_id'] ?? '') : ($billingSettings['verification_sla_note'] ?? '') }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Available Plans --}}
  <div class="card mg-b-20">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
      <div>
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.customer.available_plans_heading') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('payments.customer.available_plans_sub') }}</span>
      </div>
    </div>
    <div class="card-body">
      <div class="row row-sm">
        @foreach($availablePlans as $planOpt)
          @php
            $isCurrentPlan = $resolvedPlan && (int) $resolvedPlan->id === (int) $planOpt->id;
          @endphp
          <div class="col-md-6 col-xl-3 mg-b-15">
            <div class="card h-100 {{ $isCurrentPlan ? 'border-primary shadow-sm' : '' }}">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mg-b-8">
                  <span class="stk-tenant-code">{{ $planOpt->code }}</span>
                  @if($isCurrentPlan)
                    <span class="badge badge-primary">{{ __('payments.customer.current_badge') }}</span>
                  @endif
                </div>
                <h6 class="tx-bold tx-color-01 mg-b-4">{{ $planOpt->name }}</h6>
                <div class="tx-20 tx-bold tx-numeric tx-color-01 mg-b-2">{{ $planOpt->formattedPrice() }}</div>
                <div class="tx-11 tx-color-03 mg-b-10">{{ $planOpt->billingIntervalLabel() }}</div>
                <p class="tx-12 tx-color-02 flex-grow-1 mg-b-12">{{ $planOpt->description ?? '—' }}</p>

                @if($planOpt->isFreePlan())
                  <span class="btn btn-xs btn-light disabled">{{ __('payments.customer.free_no_checkout') }}</span>
                @elseif($canManageBilling && $effectiveStatus !== \App\Models\Company::STATUS_CANCELLED && ($billingSettings['allow_customer_payment_submissions'] ?? true))
                  <a href="{{ route('subscription.payments.create', ['plan' => $planOpt->id]) }}" class="btn btn-sm btn-block {{ $isCurrentPlan ? 'btn-brand-01' : 'btn-white' }}">
                    {{ $isCurrentPlan ? __('payments.customer.renew_plan_btn') : __('payments.customer.choose_plan_btn') }}
                  </a>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Usage Quotas if Active --}}
  @if(!empty($usageSummary['items']))
    <div class="card mg-b-20">
      <div class="card-header">
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.customer.usage_heading') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('payments.customer.usage_sub') }}</span>
      </div>
      <div class="card-body">
        <div class="row row-sm">
          @foreach($usageSummary['items'] as $uKey => $uItem)
            <div class="col-md-4 col-lg-2 mg-b-10">
              <div class="p-2 border rounded h-100">
                <div class="tx-11 tx-color-03">{{ __('subscription.limits.' . $uKey) }}</div>
                <div class="tx-16 tx-bold tx-numeric tx-color-01 mg-t-4">
                  {{ \App\Support\StockoraFormat::number($uItem['usage']) }}
                  <span class="tx-12 tx-normal tx-color-03">
                    / {{ $uItem['is_unlimited'] ? __('subscription.unlimited') : \App\Support\StockoraFormat::number($uItem['limit']) }}
                  </span>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Payment History Table --}}
  <div class="card">
    <div class="card-header">
      <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.customer.history_heading') }}</h6>
      <span class="tx-12 tx-color-03">{{ __('payments.customer.history_sub') }}</span>
    </div>
    <div class="card-body p-0">
      @if($payments->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('payments.customer.no_payments') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('payments.customer.col_reference') }}</th>
                <th>{{ __('payments.customer.col_plan_period') }}</th>
                <th>{{ __('payments.customer.col_method') }}</th>
                <th class="text-right">{{ __('payments.customer.col_amount') }}</th>
                <th>{{ __('payments.customer.col_payment_date') }}</th>
                <th>{{ __('payments.customer.col_status') }}</th>
                <th class="text-right">{{ __('payments.customer.col_actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($payments as $pay)
                <tr>
                  <td>
                    <a href="{{ route('subscription.payments.show', $pay->id) }}" class="tx-semibold tx-color-01">{{ $pay->payment_reference }}</a>
                    <div class="tx-11 tx-color-03">{{ $pay->requestTypeLabel() }}</div>
                  </td>
                  <td>
                    <div class="tx-semibold tx-color-01">{{ $pay->plan_name_snapshot }}</div>
                    <div class="tx-11 tx-color-03">{{ $pay->billingIntervalLabel() }} &bull; {{ __('payments.checkout.summary_days', ['days' => $pay->duration_days]) }}</div>
                  </td>
                  <td class="tx-12">
                    <div>{{ $pay->payment_method_name }}</div>
                    @if($pay->customer_transfer_reference)
                      <div class="tx-11 tx-color-03">{{ $pay->customer_transfer_reference }}</div>
                    @endif
                  </td>
                  <td class="text-right tx-numeric tx-semibold">{{ $pay->formattedAmount() }}</td>
                  <td class="tx-12">{{ \App\Support\StockoraFormat::shortDate($pay->payment_date) }}</td>
                  <td>
                    <span class="badge {{ $pay->statusBadgeClass() }}">{{ $pay->statusLabel() }}</span>
                    @if($pay->isVerified())
                      <div class="tx-11 mg-t-4">
                        @if($pay->isApplied())
                          <span class="badge badge-light text-success">{{ __('payments.customer.applied_badge') }}</span>
                        @else
                          <span class="badge badge-light text-warning">{{ __('payments.customer.awaiting_apply_badge') }}</span>
                        @endif
                      </div>
                    @elseif($pay->isRejected() && $pay->rejection_reason)
                      <div class="tx-11 text-danger mg-t-4">{{ \Illuminate\Support\Str::limit($pay->rejection_reason, 70) }}</div>
                    @endif
                  </td>
                  <td class="text-right">
                    <a href="{{ route('subscription.payments.show', $pay->id) }}" class="btn btn-xs btn-white">
                      {{ __('payments.customer.view_payment_btn') }}
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if($payments->hasPages())
          <div class="card-footer">
            {{ $payments->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection
