@extends('layouts.platform')

@section('title', __('payments.platform.create_title'))
@section('page_title', __('payments.platform.create_heading'))
@section('page_subtitle', __('payments.platform.create_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.payments.index') }}">{{ __('payments.platform.index_breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('payments.platform.create_breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.payments.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('app.back') }}
  </a>
@endsection

@section('content')
  <div class="alert alert-info mg-b-20">
    <i data-feather="shield" class="svg-14 mg-r-4"></i> {{ __('payments.platform.offline_no_auto_apply_notice') }}
  </div>

  <form action="{{ route('platform.payments.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="card">
      <div class="card-body">
        <div class="row row-sm">
          <div class="col-md-6 form-group">
            <label for="company_id">{{ __('payments.platform.offline_company_label') }} <span class="text-danger">*</span></label>
            <select name="company_id" id="company_id" class="form-control" required>
              <option value="">&mdash;</option>
              @foreach($companies as $co)
                <option value="{{ $co->id }}" {{ (int) old('company_id', $selectedCompanyId) === (int) $co->id ? 'selected' : '' }}>
                  {{ $co->name }} ({{ $co->code }}) &mdash; {{ $co->account_status }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 form-group">
            <label for="subscription_plan_id">{{ __('payments.platform.offline_plan_label') }} <span class="text-danger">*</span></label>
            <select name="subscription_plan_id" id="subscription_plan_id" class="form-control" required>
              @foreach($plans as $pl)
                <option value="{{ $pl->id }}" data-price="{{ (float) $pl->price }}" data-interval="{{ $pl->billing_interval }}" {{ (int) old('subscription_plan_id') === (int) $pl->id ? 'selected' : '' }}>
                  {{ $pl->name }} ({{ $pl->code }}) &mdash; {{ $pl->formattedPrice() }} / {{ $pl->billingIntervalLabel() }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4 form-group">
            <label for="request_type">{{ __('payments.platform.offline_type_label') }} <span class="text-danger">*</span></label>
            <select name="request_type" id="request_type" class="form-control" required>
              @foreach(\App\Models\SubscriptionPayment::REQUEST_TYPES as $rtKey => $rtLabel)
                <option value="{{ $rtKey }}" {{ old('request_type', 'MANUAL_OFFLINE') === $rtKey ? 'selected' : '' }}>{{ __('payments.request_types.' . $rtKey) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4 form-group">
            <label for="billing_interval">{{ __('payments.platform.offline_interval_label') }} <span class="text-danger">*</span></label>
            <select name="billing_interval" id="billing_interval" class="form-control" required>
              <option value="MONTHLY" {{ old('billing_interval', 'MONTHLY') === 'MONTHLY' ? 'selected' : '' }}>{{ __('subscription.billing_intervals.MONTHLY') }}</option>
              <option value="YEARLY" {{ old('billing_interval') === 'YEARLY' ? 'selected' : '' }}>{{ __('subscription.billing_intervals.YEARLY') }}</option>
              <option value="CUSTOM" {{ old('billing_interval') === 'CUSTOM' ? 'selected' : '' }}>{{ __('subscription.billing_intervals.CUSTOM') }}</option>
            </select>
          </div>

          <div class="col-md-4 form-group">
            <label for="duration_days">{{ __('payments.platform.offline_duration_label') }} <span class="text-danger">*</span></label>
            <input type="number" name="duration_days" id="duration_days" class="form-control" min="1" max="3650" value="{{ old('duration_days', 30) }}" required>
          </div>

          <div class="col-md-4 form-group">
            <label for="amount">{{ __('payments.platform.offline_amount_label') }}</label>
            <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount') }}">
            <small class="form-text text-muted">{{ __('payments.platform.offline_amount_hint') }}</small>
          </div>

          <div class="col-md-4 form-group">
            <label for="payment_date">{{ __('payments.checkout.payment_date_label') }} <span class="text-danger">*</span></label>
            <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
          </div>

          <div class="col-md-4 form-group">
            <label for="customer_transfer_reference">{{ __('payments.checkout.transfer_ref_label') }}</label>
            <input type="text" name="customer_transfer_reference" id="customer_transfer_reference" class="form-control" value="{{ old('customer_transfer_reference') }}">
          </div>

          <div class="col-md-6 form-group">
            <label for="payment_instruction_id">{{ __('payments.platform.offline_method_select') }}</label>
            <select name="payment_instruction_id" id="payment_instruction_id" class="form-control">
              <option value="">&mdash;</option>
              @foreach($instructions as $inst)
                <option value="{{ $inst->id }}" {{ (int) old('payment_instruction_id') === (int) $inst->id ? 'selected' : '' }}>
                  {{ $inst->method_name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 form-group">
            <label for="payment_method_name">{{ __('payments.platform.offline_method_custom') }}</label>
            <input type="text" name="payment_method_name" id="payment_method_name" class="form-control" value="{{ old('payment_method_name') }}" placeholder="Bank Transfer / Invoice Settlement / Cash Desk">
          </div>

          <div class="col-md-6 form-group">
            <label for="payer_name">{{ __('payments.checkout.payer_name_label') }}</label>
            <input type="text" name="payer_name" id="payer_name" class="form-control" value="{{ old('payer_name') }}">
          </div>

          <div class="col-md-6 form-group">
            <label for="recorded_status">{{ __('payments.platform.offline_recorded_status') }} <span class="text-danger">*</span></label>
            <select name="recorded_status" id="recorded_status" class="form-control" required>
              <option value="PENDING" {{ old('recorded_status', 'PENDING') === 'PENDING' ? 'selected' : '' }}>{{ __('payments.platform.offline_status_pending') }}</option>
              <option value="VERIFIED" {{ old('recorded_status') === 'VERIFIED' ? 'selected' : '' }}>{{ __('payments.platform.offline_status_verified') }}</option>
            </select>
          </div>

          <div class="col-12 form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="payment_received_confirmed" name="payment_received_confirmed" value="1" {{ old('payment_received_confirmed') ? 'checked' : '' }}>
              <label class="custom-control-label tx-semibold tx-13" for="payment_received_confirmed">
                {{ __('payments.platform.offline_received_checkbox') }}
              </label>
            </div>
          </div>

          @php
            $maxMb = (int) ceil(($billingSettings['payment_proof_max_kb'] ?? 5120) / 1024);
          @endphp
          <div class="col-md-6 form-group">
            <label for="payment_proof">{{ __('payments.checkout.proof_label', ['max' => $maxMb]) }}</label>
            <input type="file" name="payment_proof" id="payment_proof" class="form-control-file" accept=".jpg,.jpeg,.png,.webp,.pdf">
          </div>

          <div class="col-md-6 form-group">
            <label for="verification_note">{{ __('payments.platform.verify_note_label') }}</label>
            <input type="text" name="verification_note" id="verification_note" class="form-control" value="{{ old('verification_note') }}">
          </div>

          <div class="col-md-6 form-group mb-md-0">
            <label for="customer_note">{{ __('payments.checkout.customer_note_label') }}</label>
            <textarea name="customer_note" id="customer_note" rows="3" class="form-control">{{ old('customer_note') }}</textarea>
          </div>

          <div class="col-md-6 form-group mb-0">
            <label for="admin_note">{{ __('payments.platform.offline_admin_note_label') }}</label>
            <textarea name="admin_note" id="admin_note" rows="3" class="form-control">{{ old('admin_note') }}</textarea>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-end" style="gap: 8px;">
        <a href="{{ route('platform.payments.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-brand-01">{{ __('payments.platform.offline_submit_btn') }}</button>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
<script>
$(function () {
  function prefillOfflineDefaults() {
    var opt = $('#subscription_plan_id option:selected');
    if (!opt.length) return;
    var planInterval = opt.data('interval') || 'MONTHLY';
    var chosenInterval = $('#billing_interval').val() || planInterval;
    var basePrice = parseFloat(opt.data('price') || 0);

    if (chosenInterval === 'YEARLY') {
      $('#duration_days').val(365);
      if (planInterval === 'MONTHLY') {
        $('#amount').val(Math.round(basePrice * 12));
      } else {
        $('#amount').val(Math.round(basePrice));
      }
    } else {
      $('#duration_days').val(30);
      $('#amount').val(Math.round(basePrice));
    }
  }

  $('#subscription_plan_id, #billing_interval').on('change', function () {
    prefillOfflineDefaults();
  });

  if (!$('#amount').val()) {
    prefillOfflineDefaults();
  }
});
</script>
@endpush
