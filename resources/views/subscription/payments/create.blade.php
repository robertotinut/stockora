@extends($useAdminLayout ? 'layouts.admin' : 'layouts.billing-shell')

@section('title', __('payments.checkout.page_title'))
@section('page_title', __('payments.checkout.page_heading'))
@section('page_subtitle', __('payments.checkout.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('subscription.index') }}">{{ __('payments.customer.breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('payments.checkout.breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('subscription.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('payments.customer.back_subscription') }}
  </a>
@endsection

@section('content')
  @include('partials.billing-reminder-banner', [
    'reminder' => $reminder,
    'company' => $company,
    'resolvedPlanName' => $currentPlan?->name,
  ])

  @if($pendingPayment)
    <div class="alert alert-warning mg-b-20">
      <strong class="d-block mg-b-4">{{ __('payments.checkout.replace_pending_title', ['ref' => $pendingPayment->payment_reference]) }}</strong>
      <p class="tx-13 mb-0">
        {{ __('payments.checkout.replace_pending_body', [
            'amount' => $pendingPayment->formattedAmount(),
            'plan' => $pendingPayment->plan_name_snapshot,
        ]) }}
      </p>
    </div>
  @endif

  <form action="{{ route('subscription.payments.store') }}" method="POST" enctype="multipart/form-data" id="customerPaymentForm">
    @csrf
    <div class="row row-sm">
      <div class="col-lg-8 mg-b-20">
        <div class="card mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.checkout.step_plan') }}</h6>
          </div>
          <div class="card-body">
            <div class="row row-sm">
              <div class="col-md-7 form-group">
                <label for="subscription_plan_id">{{ __('payments.checkout.plan_label') }} <span class="text-danger">*</span></label>
                <select name="subscription_plan_id" id="subscription_plan_id" class="form-control" required>
                  @foreach($paidPlans as $pOpt)
                    @php
                      $isSelectedPlan = (int) old('subscription_plan_id', $selectedPlanId) === (int) $pOpt->id;
                    @endphp
                    <option
                      value="{{ $pOpt->id }}"
                      data-name="{{ $pOpt->name }} ({{ $pOpt->code }})"
                      data-price="{{ (float) $pOpt->price }}"
                      data-plan-interval="{{ $pOpt->billing_interval }}"
                      {{ $isSelectedPlan ? 'selected' : '' }}
                    >
                      {{ $pOpt->name }} ({{ $pOpt->code }}) &mdash; {{ $pOpt->formattedPrice() }} / {{ $pOpt->billingIntervalLabel() }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-5 form-group">
                <label for="billing_interval">{{ __('payments.checkout.interval_label') }} <span class="text-danger">*</span></label>
                <select name="billing_interval" id="billing_interval" class="form-control" required>
                  <option value="MONTHLY" {{ old('billing_interval', 'MONTHLY') === 'MONTHLY' ? 'selected' : '' }}>{{ __('payments.checkout.interval_monthly') }}</option>
                  <option value="YEARLY" {{ old('billing_interval') === 'YEARLY' ? 'selected' : '' }}>{{ __('payments.checkout.interval_yearly') }}</option>
                  <option value="CUSTOM" {{ old('billing_interval') === 'CUSTOM' ? 'selected' : '' }}>{{ __('payments.checkout.interval_custom') }}</option>
                </select>
              </div>
            </div>
            <div class="tx-12 tx-color-03">
              {{ __('payments.checkout.yearly_multiplier_hint') }} {{ __('payments.checkout.server_price_note') }}
            </div>
          </div>
        </div>

        <div class="card mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.checkout.step_channel') }}</h6>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="payment_instruction_id">{{ __('payments.checkout.instruction_label') }} <span class="text-danger">*</span></label>
              <select name="payment_instruction_id" id="payment_instruction_id" class="form-control" required>
                @foreach($instructions as $inst)
                  @php
                    $isSelInst = (int) old('payment_instruction_id', $instructions->firstWhere('is_default', true)?->id ?? $instructions->first()?->id) === (int) $inst->id;
                  @endphp
                  <option
                    value="{{ $inst->id }}"
                    data-bank="{{ $inst->bank_name ?? '' }}"
                    data-holder="{{ $inst->account_name ?? '' }}"
                    data-number="{{ $inst->account_number ?? '' }}"
                    data-instructions="{{ $inst->localizedInstructions() ?? '' }}"
                    {{ $isSelInst ? 'selected' : '' }}
                  >
                    {{ $inst->method_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="p-3 border rounded mg-b-16" style="background-color: var(--stk-bg-canvas);" id="selectedInstructionCard">
              <div class="tx-12 tx-semibold tx-color-01" id="instBankTitle">—</div>
              <div class="tx-15 tx-bold tx-numeric tx-color-01 mg-t-4" id="instAccountNumber">—</div>
              <div class="tx-12 tx-color-02" id="instAccountHolder">—</div>
              <div class="tx-12 tx-color-03 mg-t-6 pt-2 border-top" id="instDirections">—</div>
            </div>

            <div class="row row-sm">
              <div class="col-md-4 form-group">
                <label for="payment_date">{{ __('payments.checkout.payment_date_label') }} <span class="text-danger">*</span></label>
                <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="customer_transfer_reference">{{ __('payments.checkout.transfer_ref_label') }}</label>
                <input type="text" name="customer_transfer_reference" id="customer_transfer_reference" class="form-control" value="{{ old('customer_transfer_reference') }}" placeholder="{{ __('payments.checkout.transfer_ref_ph') }}">
              </div>
              <div class="col-md-4 form-group">
                <label for="payer_name">{{ __('payments.checkout.payer_name_label') }}</label>
                <input type="text" name="payer_name" id="payer_name" class="form-control" value="{{ old('payer_name', Auth::user()->name) }}" placeholder="{{ __('payments.checkout.payer_name_ph') }}">
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.checkout.step_proof') }}</h6>
          </div>
          <div class="card-body">
            @php
              $maxMb = (int) ceil(($billingSettings['payment_proof_max_kb'] ?? 5120) / 1024);
            @endphp
            <div class="form-group">
              <label for="payment_proof">{{ __('payments.checkout.proof_label', ['max' => $maxMb]) }}</label>
              <input type="file" name="payment_proof" id="payment_proof" class="form-control-file" accept=".jpg,.jpeg,.png,.webp,.pdf">
              <small class="form-text text-muted">{{ __('payments.checkout.proof_private_note') }}</small>
            </div>

            <div class="form-group mb-0">
              <label for="customer_note">{{ __('payments.checkout.customer_note_label') }}</label>
              <textarea name="customer_note" id="customer_note" rows="3" class="form-control" placeholder="{{ __('payments.checkout.customer_note_ph') }}">{{ old('customer_note') }}</textarea>
            </div>

            @if($pendingPayment)
              <div class="custom-control custom-checkbox mg-t-15 pt-3 border-top">
                <input type="checkbox" class="custom-control-input" id="replace_existing_pending" name="replace_existing_pending" value="1" {{ old('replace_existing_pending') ? 'checked' : '' }}>
                <label class="custom-control-label tx-13 tx-semibold text-warning" for="replace_existing_pending">
                  {{ __('payments.checkout.replace_pending_checkbox', ['ref' => $pendingPayment->payment_reference]) }}
                </label>
              </div>
            @endif
          </div>
        </div>
      </div>

      {{-- Sticky Summary Column --}}
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.checkout.summary_heading') }}</h6>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between mg-b-8 tx-13">
              <span class="tx-color-03">{{ __('payments.customer.workspace') }}</span>
              <strong class="tx-color-01 text-right">{{ $company->name }}</strong>
            </div>
            <div class="d-flex justify-content-between mg-b-8 tx-13">
              <span class="tx-color-03">{{ __('payments.checkout.summary_plan') }}</span>
              <strong class="tx-color-01 text-right" id="summaryPlan">—</strong>
            </div>
            <div class="d-flex justify-content-between mg-b-8 tx-13">
              <span class="tx-color-03">{{ __('payments.checkout.summary_interval') }}</span>
              <strong class="tx-color-01" id="summaryInterval">—</strong>
            </div>
            <div class="d-flex justify-content-between mg-b-12 pb-3 border-bottom tx-13">
              <span class="tx-color-03">{{ __('payments.checkout.summary_duration') }}</span>
              <strong class="tx-color-01" id="summaryDuration">—</strong>
            </div>

            <div class="mg-b-16">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.checkout.summary_total') }}</div>
              <div class="tx-24 tx-bold tx-numeric tx-color-01" id="summaryTotal">Rp 0</div>
            </div>

            <div class="alert alert-light border tx-12 mg-b-16">
              {{ __('payments.checkout.summary_policy') }}
            </div>

            <button type="submit" class="btn btn-brand-01 btn-block" {{ $paidPlans->isEmpty() || $instructions->isEmpty() ? 'disabled' : '' }}>
              <i data-feather="check-circle" class="svg-14"></i> {{ __('payments.checkout.submit_btn') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
<script>
$(function () {
  var locale = @json(app()->getLocale());
  var daysTpl = @json(__('payments.checkout.summary_days', ['days' => '__DAYS__']));
  var intervalLabels = {
    MONTHLY: @json(__('subscription.billing_intervals.MONTHLY')),
    YEARLY: @json(__('subscription.billing_intervals.YEARLY')),
    CUSTOM: @json(__('subscription.billing_intervals.CUSTOM'))
  };

  function formatIdr(val) {
    var num = Math.round(Number(val) || 0);
    return 'Rp ' + num.toLocaleString(locale === 'id' ? 'id-ID' : 'en-US');
  }

  function syncIntervalOptions() {
    var opt = $('#subscription_plan_id option:selected');
    var planInterval = opt.data('plan-interval') || 'MONTHLY';
    var $sel = $('#billing_interval');

    $sel.find('option').prop('disabled', false);
    if (planInterval === 'YEARLY') {
      $sel.find('option[value="MONTHLY"], option[value="CUSTOM"]').prop('disabled', true);
      if ($sel.val() !== 'YEARLY') {
        $sel.val('YEARLY');
      }
    } else if (planInterval === 'CUSTOM') {
      $sel.find('option[value="MONTHLY"], option[value="YEARLY"]').prop('disabled', true);
      $sel.val('CUSTOM');
    } else {
      $sel.find('option[value="CUSTOM"]').prop('disabled', true);
      if ($sel.val() === 'CUSTOM') {
        $sel.val('MONTHLY');
      }
    }
  }

  function updateSummary() {
    var opt = $('#subscription_plan_id option:selected');
    if (!opt.length) return;
    var planInterval = opt.data('plan-interval') || 'MONTHLY';
    var chosenInterval = $('#billing_interval').val() || planInterval;
    var basePrice = parseFloat(opt.data('price') || 0);
    var total = basePrice;
    var days = 30;

    if (planInterval === 'MONTHLY' && chosenInterval === 'YEARLY') {
      total = basePrice * 12;
      days = 365;
    } else if (chosenInterval === 'YEARLY') {
      days = 365;
    }

    $('#summaryPlan').text(opt.data('name') || '—');
    $('#summaryInterval').text(intervalLabels[chosenInterval] || chosenInterval);
    $('#summaryDuration').text(daysTpl.replace('__DAYS__', days));
    $('#summaryTotal').text(formatIdr(total));
  }

  function updateInstructionCard() {
    var opt = $('#payment_instruction_id option:selected');
    if (!opt.length) return;
    $('#instBankTitle').text(opt.data('bank') || opt.text());
    $('#instAccountNumber').text(opt.data('number') || '—');
    $('#instAccountHolder').text(opt.data('holder') || '—');
    $('#instDirections').text(opt.data('instructions') || '—');
  }

  $('#subscription_plan_id').on('change', function () {
    syncIntervalOptions();
    updateSummary();
  });
  $('#billing_interval').on('change', updateSummary);
  $('#payment_instruction_id').on('change', updateInstructionCard);

  syncIntervalOptions();
  updateSummary();
  updateInstructionCard();
});
</script>
@endpush
