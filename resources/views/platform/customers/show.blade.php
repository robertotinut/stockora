@extends('layouts.platform')

@section('title', __('platform.customer_show.tab_title', ['name' => $company->name]))
@section('page_title', $company->name)
@section('page_subtitle', __('platform.customer_show.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.customers.index') }}">{{ __('platform.customers_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $company->code }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.customers.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('platform.customer_show.back_directory') }}
  </a>
@endsection

@section('content')
  @if(session('sandbox_setup_preview'))
    @php $preview = session('sandbox_setup_preview'); @endphp
    <div class="stk-sandbox-warning mg-b-20">
      <div class="d-flex align-items-start" style="gap: 10px;">
        <i data-feather="key" class="svg-18 text-warning flex-shrink-0 mg-t-2"></i>
        <div class="w-100">
          <strong class="d-block tx-color-01 mg-b-4">{{ __('platform.customer_show.setup_preview_title') }}</strong>
          <p class="tx-12 tx-color-02 mg-b-8">
            {{ __('platform.customer_show.setup_preview_body', ['email' => $preview['email']]) }}
          </p>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control bg-white" id="sandboxSetupUrl" value="{{ $preview['setup_url'] }}" readonly>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Top Identity Banner --}}
  <div class="card mg-b-20">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start" style="gap: 16px;">
        <div class="d-flex align-items-center" style="gap: 14px;">
          <div class="stk-logo-preview" style="width: 58px; height: 58px;">
            @if($company->logoUrl())
              <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}">
            @else
              <span>{{ $company->initials() }}</span>
            @endif
          </div>
          <div>
            <div class="d-flex flex-wrap align-items-center mg-b-6" style="gap: 8px;">
              <h5 class="tx-bold tx-color-01 mb-0">{{ $company->name }}</h5>
              <span class="stk-tenant-code">{{ $company->code }}</span>
              @include('partials.company-status-badge', ['status' => $effectiveStatus])
              @include('partials.access-type-badge', ['accessType' => $company->access_type])
            </div>
            <div class="tx-12 tx-color-03">
              {{ $company->industry ?? __('platform.customer_show.industry_not_set') }}
              @if($company->phone) &bull; {{ $company->phone }} @endif
              &bull; {{ __('platform.customer_show.registered_at', ['date' => $company->created_at ? \App\Support\StockoraFormat::dateTime($company->created_at) : '—']) }}
            </div>
            @if($company->status_reason)
              <div class="tx-12 text-danger mg-t-4">
                <strong>{{ __('platform.customer_show.latest_note') }}</strong> {{ $company->status_reason }}
              </div>
            @endif
          </div>
        </div>

        <div class="text-md-right tx-12">
          <div>{{ __('platform.customers.start_inline') }} <strong class="tx-color-01">{{ $company->access_starts_at ? \App\Support\StockoraFormat::dateTime($company->access_starts_at) : __('platform.common.not_started') }}</strong></div>
          <div>{{ __('platform.customers.expires_inline') }} <strong class="tx-color-01">{{ $company->access_expires_at ? \App\Support\StockoraFormat::dateTime($company->access_expires_at) : __('platform.common.unlimited') }}</strong></div>
          @if($company->activated_at)
            <div class="tx-color-03">{{ __('platform.customer_show.activated_at_by', ['date' => \App\Support\StockoraFormat::dateTime($company->activated_at), 'by' => $company->activatedByAdmin ? __('platform.customer_show.by_actor', ['name' => $company->activatedByAdmin->name]) : '']) }}</div>
          @endif
          @if($company->suspended_at)
            <div class="text-danger">{{ __('platform.customer_show.suspended_at_by', ['date' => \App\Support\StockoraFormat::dateTime($company->suspended_at), 'by' => $company->suspendedByAdmin ? __('platform.customer_show.by_actor', ['name' => $company->suspendedByAdmin->name]) : '']) }}</div>
          @endif
        </div>
      </div>

      {{-- Telemetry Strip (Zero tenant-user joining required) --}}
      <div class="row row-sm mg-t-20 pt-3 border-top text-center">
        <div class="col-4 col-md-2 mg-b-10 mg-md-b-0">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_users') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['members']->count() }}</div>
        </div>
        <div class="col-4 col-md-2 mg-b-10 mg-md-b-0">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_warehouses') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['warehousesCount'] }}</div>
        </div>
        <div class="col-4 col-md-2 mg-b-10 mg-md-b-0">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_products') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['productsCount'] }}</div>
        </div>
        <div class="col-4 col-md-2">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_purchases') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['purchasesCount'] }}</div>
        </div>
        <div class="col-4 col-md-2">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_movements') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['movementsCount'] }}</div>
        </div>
        <div class="col-4 col-md-2">
          <div class="tx-11 text-uppercase tx-color-03">{{ __('platform.customer_show.telemetry_invites') }}</div>
          <div class="tx-18 tx-bold tx-color-01 tx-numeric">{{ $summary['pendingInvitesCount'] }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-sm">
    {{-- Left Column: Lifecycle Governance & Activation Controls --}}
    <div class="col-lg-6 mg-b-20">
      @if($effectiveStatus === \App\Models\Company::STATUS_PENDING_ACTIVATION)
        <div class="card border-warning mg-b-20" id="activateSection">
          <div class="card-header bg-warning-light">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_show.manual_activation_heading') }}</h6>
          </div>
          <div class="card-body">
            <p class="tx-13 tx-color-02 mg-b-16">
              {{ __('platform.customer_show.manual_activation_desc') }}
            </p>
            <form action="{{ route('platform.customers.activate', $company) }}" method="POST" id="activateCustomerForm">
              @csrf
              <div class="row row-sm">
                <div class="col-md-6 form-group">
                  <label for="act_access_type">{{ __('platform.customers.access_type_label') }} <span class="text-danger">*</span></label>
                  <select id="act_access_type" name="access_type" class="form-control" required>
                    @foreach($accessTypes as $tKey => $tMeta)
                      <option value="{{ $tKey }}" {{ ($company->access_type ?: 'STARTER') === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-6 form-group">
                  <label for="act_starts_at">{{ __('platform.customer_show.start_date') }}</label>
                  <input type="date" id="act_starts_at" name="access_starts_at" class="form-control" value="{{ now()->toDateString() }}">
                </div>
              </div>
              <div class="form-group">
                <label for="act_expires_at">{{ __('platform.customer_show.expires_optional') }}</label>
                <input type="date" id="act_expires_at" name="access_expires_at" class="form-control">
                <small class="form-text text-muted">{{ __('platform.customer_show.expires_manual_help') }}</small>
              </div>
              <div class="form-group">
                <label for="act_note">{{ __('platform.customer_show.activation_note') }}</label>
                <input type="text" id="act_note" name="note" class="form-control" placeholder="{{ __('platform.customer_show.activation_note_ph') }}">
              </div>
              <button type="submit" class="btn btn-success btn-block">
                <i data-feather="check-circle" class="svg-14"></i> {{ __('platform.customer_show.activate_btn') }}
              </button>
            </form>
          </div>
        </div>
      @elseif(in_array($effectiveStatus, [\App\Models\Company::STATUS_SUSPENDED, \App\Models\Company::STATUS_EXPIRED, \App\Models\Company::STATUS_CANCELLED], true))
        <div class="card border-info mg-b-20" id="reactivateSection">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_show.reactivate_heading') }}</h6>
          </div>
          <div class="card-body">
            <form action="{{ route('platform.customers.reactivate', $company) }}" method="POST">
              @csrf
              <div class="row row-sm">
                <div class="col-md-6 form-group">
                  <label for="re_access_type">{{ __('platform.customers.access_type_label') }}</label>
                  <select id="re_access_type" name="access_type" class="form-control">
                    @foreach($accessTypes as $tKey => $tMeta)
                      <option value="{{ $tKey }}" {{ $company->access_type === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-6 form-group">
                  <label for="re_expires_at">{{ __('platform.customer_show.new_expires_at') }}</label>
                  <input type="date" id="re_expires_at" name="access_expires_at" class="form-control" value="{{ $company->access_expires_at && $company->access_expires_at->isFuture() ? $company->access_expires_at->toDateString() : now()->addDays(30)->toDateString() }}">
                </div>
              </div>
              <div class="custom-control custom-checkbox mg-b-12">
                <input type="checkbox" class="custom-control-input" id="re_clear_exp" name="clear_expiration" value="1">
                <label class="custom-control-label tx-12 tx-color-02" for="re_clear_exp">{{ __('platform.customer_show.clear_exp_unlimited') }}</label>
              </div>
              <div class="form-group">
                <label for="re_reason">{{ __('platform.customer_show.reactivation_note') }}</label>
                <input type="text" id="re_reason" name="reason" class="form-control" placeholder="{{ __('platform.customer_show.reactivation_note_ph') }}">
              </div>
              <button type="submit" class="btn btn-brand-01 btn-block">{{ __('platform.customer_show.reactivate_btn') }}</button>
            </form>
          </div>
        </div>
      @endif

      {{-- Subscription Plan Assignment & Resolved Entitlements --}}
      <div class="card mg-b-20" id="subscriptionPlanSection">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.customer_show.assign_plan_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.customer_show.assign_plan_sub') }}</span>
          </div>
          @if($resolvedPlan)
            <a href="{{ route('platform.plans.show', $resolvedPlan) }}" class="btn btn-white btn-xs">
              <i data-feather="layers" class="svg-12"></i> {{ $resolvedPlan->code }}
            </a>
          @endif
        </div>
        <div class="card-body">
          <div class="row row-sm mg-b-15 pb-3 border-bottom tx-12">
            <div class="col-6 col-sm-4 mg-b-10">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.current_plan') }}</div>
              <div class="tx-semibold tx-color-01">
                {{ $resolvedPlan?->name ?? __('platform.customers.unassigned') }}
                @if($resolvedPlan)
                  <span class="badge badge-light ml-1">{{ $resolvedPlan->code }}</span>
                @endif
              </div>
            </div>
            <div class="col-6 col-sm-4 mg-b-10">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.price_interval') }}</div>
              <div class="tx-semibold tx-color-01">
                @if($resolvedPlan)
                  {{ $resolvedPlan->formattedPrice() }} / {{ $resolvedPlan->billingIntervalLabel() }}
                @else
                  &mdash;
                @endif
              </div>
            </div>
            <div class="col-6 col-sm-4 mg-b-10">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.plan_status') }}</div>
              <div>
                @if($resolvedPlan && $resolvedPlan->is_active)
                  <span class="badge badge-success">{{ __('platform.customer_show.active_plan') }}</span>
                @elseif($resolvedPlan)
                  <span class="badge badge-secondary">{{ __('platform.customer_show.inactive_plan') }}</span>
                @else
                  <span class="badge badge-warning">{{ __('platform.customer_show.no_plan') }}</span>
                @endif
              </div>
            </div>
            <div class="col-6 col-sm-4">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.access_tier') }}</div>
              <div>@include('partials.access-type-badge', ['accessType' => $company->access_type])</div>
            </div>
            <div class="col-6 col-sm-4">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.activated_date') }}</div>
              <div class="tx-color-01">{{ $company->activated_at ? \App\Support\StockoraFormat::dateTime($company->activated_at) : __('platform.common.pending') }}</div>
            </div>
            <div class="col-6 col-sm-4">
              <div class="tx-color-03 text-uppercase tx-11">{{ __('platform.customer_show.expiration_date') }}</div>
              <div class="tx-color-01">{{ $company->access_expires_at ? \App\Support\StockoraFormat::dateTime($company->access_expires_at) : __('platform.common.unlimited') }}</div>
            </div>
          </div>

          <form action="{{ route('platform.customers.assign-plan', $company) }}" method="POST" id="assignPlanForm" class="mg-b-16 pb-3 border-bottom">
            @csrf
            <div class="row row-sm">
              <div class="col-md-7 form-group">
                <label for="assign_plan_id">{{ __('platform.customer_show.assign_change_plan') }} <span class="text-danger">*</span></label>
                <select id="assign_plan_id" name="subscription_plan_id" class="form-control" required>
                  @foreach($activePlans as $planOpt)
                    <option value="{{ $planOpt->id }}" {{ (int) ($resolvedPlan?->id ?? 0) === (int) $planOpt->id ? 'selected' : '' }}>
                      {{ $planOpt->name }} ({{ $planOpt->code }})@if($planOpt->is_internal) [{{ __('platform.plans_ui.internal_badge') }}]@endif &mdash; {{ $planOpt->formattedPrice() }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-5 form-group">
                <label for="assign_expires_at">{{ __('platform.customer_show.update_expiration') }} <span class="tx-color-03 font-weight-normal">({{ __('app.optional') }})</span></label>
                <input type="date" id="assign_expires_at" name="access_expires_at" class="form-control" value="">
                <small class="form-text text-muted">{{ __('platform.customer_show.blank_preserves_exp') }}</small>
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="assign_note">{{ __('platform.customer_show.assignment_note') }}</label>
                <input type="text" id="assign_note" name="note" class="form-control" placeholder="{{ __('platform.customer_show.assignment_note_ph') }}">
              </div>
              <div class="col-md-6 form-group">
                <label for="assign_admin_notes">{{ __('platform.customer_show.internal_admin_notes') }}</label>
                <input type="text" id="assign_admin_notes" name="admin_notes" class="form-control" value="{{ $company->admin_notes }}" placeholder="{{ __('platform.customer_show.internal_admin_notes_ph') }}">
              </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="assign_clear_exp" name="clear_expiration" value="1">
                <label class="custom-control-label tx-12 tx-color-02" for="assign_clear_exp">{{ __('platform.customer_show.clear_exp_short') }}</label>
              </div>
              <button type="submit" class="btn btn-brand-01 btn-sm">{{ __('platform.customer_show.assign_plan_btn') }}</button>
            </div>
            <div class="tx-11 tx-color-03 mg-t-8">
              {{ __('platform.customer_show.assign_plan_help', ['status' => __('platform.account_statuses.' . $effectiveStatus)]) }}
            </div>
          </form>

          {{-- Resolved Resource Usage vs Limits & Feature Access --}}
          <div class="d-flex justify-content-between align-items-center mg-b-8">
            <div class="tx-12 tx-semibold tx-color-01">{{ __('platform.customer_show.usage_heading') }}</div>
            @if(!empty($usageSummary['has_warnings']))
              <span class="badge {{ $usageSummary['has_reached'] ? 'badge-danger' : 'badge-warning' }}">
                {{ $usageSummary['has_reached'] ? __('subscription.status.reached') : __('subscription.status.warning') }}
              </span>
            @endif
          </div>

          <div class="row row-sm mg-b-12">
            @foreach($usageSummary['items'] as $lKey => $uItem)
              @php
                $badgeCls = match($uItem['status']) {
                    'reached' => 'badge-danger',
                    'warning' => 'badge-warning',
                    'unlimited' => 'badge-primary',
                    default => 'badge-success',
                };
                $barCls = match($uItem['status']) {
                    'reached' => 'bg-danger',
                    'warning' => 'bg-warning',
                    default => 'bg-primary',
                };
              @endphp
              <div class="col-6 col-sm-6 mg-b-10">
                <div class="p-2 border rounded bg-light h-100">
                  <div class="d-flex justify-content-between align-items-start mg-b-4" style="gap: 4px;">
                    <div class="tx-11 tx-color-03 lh-4">
                      {{ __("subscription.limits.{$lKey}") }}
                      <span class="d-block tx-10 text-muted">
                        {{ $uItem['basis'] === 'monthly' ? __('subscription.basis_monthly') : __('subscription.basis_lifetime') }}
                      </span>
                    </div>
                    <span class="badge {{ $badgeCls }}" style="font-size: 10px;">
                      @if($uItem['is_unlimited'])
                        {{ __('subscription.unlimited') }}
                      @else
                        {{ $uItem['percentage'] }}%
                      @endif
                    </span>
                  </div>
                  <div class="tx-14 tx-bold tx-numeric tx-color-01">
                    {{ \App\Support\StockoraFormat::number($uItem['usage']) }}
                    <span class="tx-12 tx-normal tx-color-03">
                      / @if(!$entitlements['has_plan'])
                          <span class="text-danger">0 ({{ __('subscription.no_plan') }})</span>
                        @elseif($uItem['is_unlimited'])
                          <span class="text-primary">{{ __('subscription.unlimited') }}</span>
                        @else
                          {{ \App\Support\StockoraFormat::number($uItem['limit']) }}
                        @endif
                    </span>
                  </div>
                  @if(!$uItem['is_unlimited'])
                    <div class="stk-progress-track mg-t-6">
                      <div class="stk-progress-fill {{ $barCls }}" style="width: {{ min(100, max($uItem['usage'] > 0 ? 4 : 0, (int) $uItem['percentage'])) }}%;"></div>
                    </div>
                  @endif
                </div>
              </div>
            @endforeach
          </div>

          <div class="tx-12 tx-semibold tx-color-01 mg-b-6">{{ __('platform.customer_show.features_heading') }}</div>
          <div class="d-flex flex-wrap" style="gap: 6px;">
            @foreach($entitlements['features'] as $fKey => $fMeta)
              @php
                $fLabel = __("subscription.features.{$fKey}");
                if ($fLabel === "subscription.features.{$fKey}") {
                    $fLabel = $fMeta['name'];
                }
              @endphp
              @if($fMeta['type'] === \App\Models\SubscriptionFeature::TYPE_BOOLEAN)
                <span class="badge {{ $fMeta['enabled'] ? 'badge-success' : 'badge-light tx-color-03 border' }}" style="font-weight: 500;">
                  {{ $fLabel }}: {{ $fMeta['enabled'] ? __('subscription.enabled') : __('subscription.disabled') }}
                </span>
              @elseif($fMeta['type'] === \App\Models\SubscriptionFeature::TYPE_TEXT)
                <span class="badge badge-info" style="font-weight: 500;">
                  {{ $fLabel }}: {{ $fMeta['display_value'] }}
                </span>
              @endif
            @endforeach
          </div>
        </div>
      </div>

      {{-- Access Tier & Expiration Window Management --}}
      <div class="card mg-b-20">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_show.access_controls_heading') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ route('platform.customers.access-type', $company) }}" method="POST" class="mg-b-20 pb-3 border-bottom">
            @csrf
            <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.customer_show.change_access_type') }}</label>
            <div class="row row-sm align-items-end">
              <div class="col-md-5 form-group mg-md-b-0">
                <select name="access_type" class="form-control">
                  @foreach($accessTypes as $tKey => $tMeta)
                    <option value="{{ $tKey }}" {{ $company->access_type === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 form-group mg-md-b-0">
                <input type="text" name="note" class="form-control" placeholder="{{ __('platform.customer_show.tier_note_ph') }}">
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-white btn-block">{{ __('platform.customer_show.update_tier_btn') }}</button>
              </div>
            </div>
          </form>

          <form action="{{ route('platform.customers.extend', $company) }}" method="POST">
            @csrf
            <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.customer_show.extend_exp_label') }}</label>
            <div class="row row-sm align-items-end">
              <div class="col-md-5 form-group mg-b-10">
                <input type="date" name="access_expires_at" class="form-control" value="{{ $company->access_expires_at ? $company->access_expires_at->toDateString() : now()->addMonths(1)->toDateString() }}">
              </div>
              <div class="col-md-7 form-group mg-b-10">
                <input type="text" name="note" class="form-control" placeholder="{{ __('platform.customer_show.ext_note_ph') }}">
              </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="clear_expiration" name="clear_expiration" value="1">
                <label class="custom-control-label tx-12 tx-color-02" for="clear_expiration">{{ __('platform.customer_show.remove_exp_unlimited') }}</label>
              </div>
              <button type="submit" class="btn btn-white btn-sm">{{ __('platform.customer_show.apply_exp_btn') }}</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Suspend / Expire / Cancel Safeguard Controls --}}
      @if(in_array(\App\Models\Company::STATUS_SUSPENDED, $allowedTransitions, true) || in_array(\App\Models\Company::STATUS_EXPIRED, $allowedTransitions, true) || in_array(\App\Models\Company::STATUS_CANCELLED, $allowedTransitions, true))
        <div class="card mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_show.restriction_heading') }}</h6>
          </div>
          <div class="card-body">
            @if(in_array(\App\Models\Company::STATUS_SUSPENDED, $allowedTransitions, true))
              <form action="{{ route('platform.customers.suspend', $company) }}" method="POST" class="mg-b-15" data-confirm="{{ __('platform.customer_show.suspend_confirm_msg', ['name' => $company->name]) }}" data-confirm-title="{{ __('platform.customer_show.suspend_confirm_title') }}" data-confirm-btn="{{ __('platform.customer_show.suspend_confirm_btn') }}">
                @csrf
                <label for="suspend_reason" class="tx-12 tx-semibold text-danger">{{ __('platform.customer_show.suspend_label') }}</label>
                <div class="input-group">
                  <input type="text" id="suspend_reason" name="reason" class="form-control" placeholder="{{ __('platform.customer_show.suspend_ph') }}" required>
                  <div class="input-group-append">
                    <button type="submit" class="btn btn-danger">{{ __('platform.customer_show.suspend_btn') }}</button>
                  </div>
                </div>
              </form>
            @endif

            <div class="d-flex flex-wrap" style="gap: 8px;">
              @if(in_array(\App\Models\Company::STATUS_EXPIRED, $allowedTransitions, true))
                <form action="{{ route('platform.customers.expire', $company) }}" method="POST" data-confirm="{{ __('platform.customer_show.expire_confirm_msg', ['name' => $company->name]) }}" data-confirm-title="{{ __('platform.customer_show.expire_confirm_title') }}" data-confirm-btn="{{ __('platform.customer_show.expire_confirm_btn') }}" data-confirm-btn-class="btn-warning">
                  @csrf
                  <button type="submit" class="btn btn-white btn-sm text-warning border-warning">{{ __('platform.customer_show.mark_expired_btn') }}</button>
                </form>
              @endif

              @if(in_array(\App\Models\Company::STATUS_CANCELLED, $allowedTransitions, true))
                <form action="{{ route('platform.customers.cancel', $company) }}" method="POST" class="d-inline-flex align-items-center" style="gap: 6px;" data-confirm="{{ __('platform.customer_show.cancel_confirm_msg', ['name' => $company->name]) }}" data-confirm-title="{{ __('platform.customer_show.cancel_confirm_title') }}" data-confirm-btn="{{ __('platform.customer_show.cancel_confirm_btn') }}">
                  @csrf
                  <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('platform.customer_show.cancel_reason_ph') }}" required style="width: 190px;">
                  <button type="submit" class="btn btn-white btn-sm text-dark">{{ __('platform.customer_show.cancel_account_btn') }}</button>
                </form>
              @endif
            </div>
          </div>
        </div>
      @endif
    </div>

    {{-- Right Column: Profile, Notes, Team Roster --}}
    <div class="col-lg-6 mg-b-20">
      <div class="card mg-b-20">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_show.profile_heading') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ route('platform.customers.update', $company) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row row-sm">
              <div class="col-md-4 form-group">
                <label for="prof_code">{{ __('app.code') }}</label>
                <input type="text" id="prof_code" name="code" class="form-control text-uppercase" value="{{ old('code', $company->code) }}" required>
              </div>
              <div class="col-md-8 form-group">
                <label for="prof_name">{{ __('platform.customer_create.company_name') }}</label>
                <input type="text" id="prof_name" name="name" class="form-control" value="{{ old('name', $company->name) }}" required>
              </div>
            </div>
            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="prof_industry">{{ __('platform.customer_create.industry') }}</label>
                <select id="prof_industry" name="industry" class="form-control">
                  <option value="">{{ __('platform.customer_show.not_set') }}</option>
                  @foreach($industries as $ind)
                    <option value="{{ $ind }}" {{ old('industry', $company->industry) === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label for="prof_phone">{{ __('app.phone') }}</label>
                <input type="text" id="prof_phone" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}">
              </div>
            </div>
            <div class="form-group">
              <label for="prof_address">{{ __('app.address') }}</label>
              <textarea id="prof_address" name="address" rows="2" class="form-control">{{ old('address', $company->address) }}</textarea>
            </div>
            <div class="form-group">
              <label for="prof_admin_notes">{{ __('platform.customer_show.admin_notes_hidden_label') }}</label>
              <textarea id="prof_admin_notes" name="admin_notes" rows="3" class="form-control" placeholder="{{ __('platform.customer_show.admin_notes_ph') }}">{{ old('admin_notes', $company->admin_notes) }}</textarea>
            </div>
            <button type="submit" class="btn btn-brand-01 btn-sm">{{ __('platform.customer_show.save_profile_btn') }}</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.customer_show.roster_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.customer_show.roster_sub') }}</span>
          </div>
          <span class="badge badge-light">{{ __('platform.customer_show.active_badge_count', ['count' => $summary['members']->count()]) }}</span>
        </div>
        <div class="card-body p-0">
          @if($summary['members']->isEmpty())
            <div class="p-3 tx-13 tx-color-03 text-center">{{ __('platform.customer_show.no_active_users') }}</div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('platform.customer_show.col_user') }}</th>
                    <th>{{ __('platform.customer_show.col_role') }}</th>
                    <th>{{ __('platform.customer_show.col_default') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($summary['members'] as $member)
                    <tr>
                      <td>
                        <div class="tx-semibold tx-color-01">{{ $member->name }}</div>
                        <div class="tx-11 tx-color-03">{{ $member->email }}</div>
                      </td>
                      <td>@include('partials.role-badge', ['role' => $member->pivot->role])</td>
                      <td class="tx-12">{{ $member->pivot->is_default ? __('app.yes') : __('app.no') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Subscription Payments & Renewal History --}}
  <div class="card mg-b-20" id="customerPaymentsSection">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
      <div>
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.platform.customer_section_heading') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('payments.platform.customer_section_sub') }}</span>
      </div>
      <div class="d-flex flex-wrap" style="gap: 8px;">
        <a href="{{ route('platform.payments.index', ['company_id' => $company->id]) }}" class="btn btn-white btn-xs">{{ __('payments.platform.index_breadcrumb') }}</a>
        <a href="{{ route('platform.payments.create', ['company_id' => $company->id]) }}" class="btn btn-brand-01 btn-xs">
          <i data-feather="plus" class="svg-12"></i> {{ __('payments.platform.record_for_customer_btn') }}
        </a>
      </div>
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
                    <a href="{{ route('platform.payments.show', $pay) }}" class="tx-semibold">{{ $pay->payment_reference }}</a>
                    <div class="tx-11 tx-color-03">{{ $pay->requestTypeLabel() }} &bull; {{ $pay->sourceLabel() }}</div>
                  </td>
                  <td>
                    <div class="tx-semibold">{{ $pay->plan_name_snapshot }}</div>
                    <div class="tx-11 tx-color-03">{{ $pay->billingIntervalLabel() }}</div>
                  </td>
                  <td class="text-right tx-numeric tx-semibold">{{ $pay->formattedAmount() }}</td>
                  <td class="tx-12">{{ \App\Support\StockoraFormat::shortDate($pay->payment_date) }}</td>
                  <td>
                    <span class="badge {{ $pay->statusBadgeClass() }}">{{ $pay->statusLabel() }}</span>
                    @if($pay->isVerified())
                      <span class="badge badge-light ml-1">{{ $pay->isApplied() ? __('payments.customer.applied_badge') : __('payments.customer.awaiting_apply_badge') }}</span>
                    @endif
                  </td>
                  <td class="text-right">
                    <a href="{{ route('platform.payments.show', $pay) }}" class="btn btn-xs btn-white">{{ __('payments.platform.review_btn') }}</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Customer Platform Audit Trail --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.customer_show.audit_heading') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('platform.customer_show.audit_sub', ['code' => $company->code]) }}</span>
      </div>
      <a href="{{ route('platform.logs.index', ['company_id' => $company->id]) }}" class="btn btn-white btn-xs">{{ __('platform.customer_show.open_audit_filter') }}</a>
    </div>
    <div class="card-body p-0">
      @if($auditLogs->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.customer_show.no_audit_events') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.customer_show.col_timestamp') }}</th>
                <th>{{ __('platform.customer_show.col_action') }}</th>
                <th>{{ __('platform.dashboard.col_actor') }}</th>
                <th>{{ __('platform.customer_show.col_note') }}</th>
                <th>{{ __('platform.customer_show.col_changes') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($auditLogs as $log)
                <tr>
                  <td class="tx-12 tx-color-03 text-nowrap">{{ $log->created_at ? \App\Support\StockoraFormat::dateTime($log->created_at, true) : '—' }}</td>
                  <td><span class="badge badge-light">{{ $log->action }}</span></td>
                  <td class="tx-12">
                    @if($log->platformAdmin)
                      <strong>{{ $log->platformAdmin->name }}</strong>
                    @elseif($log->actorUser)
                      <strong>{{ $log->actorUser->name }}</strong> <span class="tx-color-03">{{ __('platform.customer_show.owner_suffix') }}</span>
                    @else
                      <span class="tx-color-03">{{ __('platform.common.automated_system') }}</span>
                    @endif
                  </td>
                  <td class="tx-12">{{ $log->note ?? '—' }}</td>
                  <td class="tx-11 tx-color-03">
                    @if($log->previous_values && isset($log->previous_values['account_status'], $log->new_values['account_status']))
                      <span>{{ $log->previous_values['account_status'] }} &rarr; <strong>{{ $log->new_values['account_status'] }}</strong></span>
                    @elseif($log->new_values)
                      <code>{{ \Illuminate\Support\Str::limit(json_encode($log->new_values), 70) }}</code>
                    @else
                      &mdash;
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
