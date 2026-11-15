@extends('layouts.platform')

@section('title', __('payments.platform.show_title', ['ref' => $payment->payment_reference]))
@section('page_title', __('payments.platform.show_heading'))
@section('page_subtitle', __('payments.platform.show_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.payments.index') }}">{{ __('payments.platform.index_breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $payment->payment_reference }}</li>
@endsection

@section('actions')
  @if($payment->company)
    <a href="{{ route('platform.customers.show', $payment->company) }}" class="btn btn-white">
      <i data-feather="briefcase" class="svg-14"></i> {{ __('payments.platform.open_customer_btn') }}
    </a>
  @endif
  <a href="{{ route('platform.payments.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('app.back') }}
  </a>
@endsection

@section('content')
  <div class="row row-sm">
    {{-- Left Column: Payment Info, Workspace Context, Proof & Audit Trail --}}
    <div class="col-lg-7 mg-b-20">
      <div class="card mg-b-20">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.reference_label') }}</div>
            <h5 class="mg-b-0 tx-bold tx-color-01">{{ $payment->payment_reference }}</h5>
          </div>
          <span class="badge {{ $payment->statusBadgeClass() }}" style="font-size: 12px;">{{ $payment->statusLabel() }}</span>
        </div>
        <div class="card-body">
          <div class="row row-sm tx-13">
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.plan_snapshot_label') }}</div>
              <div class="tx-semibold tx-color-01">{{ $payment->plan_name_snapshot }} ({{ $payment->plan_code_snapshot }})</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.amount_label') }}</div>
              <div class="tx-18 tx-bold tx-numeric tx-color-01">{{ $payment->formattedAmount() }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.request_type_label') }}</div>
              <div class="tx-color-01">{{ $payment->requestTypeLabel() }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.interval_duration_label') }}</div>
              <div class="tx-color-01">{{ $payment->billingIntervalLabel() }} &bull; {{ __('payments.checkout.summary_days', ['days' => $payment->duration_days]) }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.payment_date_label') }}</div>
              <div class="tx-color-01">{{ \App\Support\StockoraFormat::date($payment->payment_date) }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.channel_label') }}</div>
              <div class="tx-color-01">{{ $payment->payment_method_name }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.transfer_ref_label') }}</div>
              <div class="tx-color-01">{{ $payment->customer_transfer_reference ?: '—' }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.payer_name_label') }}</div>
              <div class="tx-color-01">{{ $payment->payer_name ?: '—' }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.source_label') }}</div>
              <div class="tx-color-01">{{ $payment->sourceLabel() }}</div>
            </div>
            <div class="col-sm-6 mg-b-12">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.submitted_by_label') }}</div>
              <div class="tx-color-01">
                @if($payment->requestedByUser)
                  {{ $payment->requestedByUser->name }} ({{ $payment->requestedByUser->email }})
                @elseif($payment->recordedByAdmin)
                  {{ $payment->recordedByAdmin->name }} (Admin)
                @else
                  &mdash;
                @endif
              </div>
            </div>
          </div>

          @if($payment->customer_note)
            <div class="pt-3 border-top tx-13 mg-b-10">
              <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.detail.customer_note_label') }}</div>
              <div class="tx-color-02">{{ $payment->customer_note }}</div>
            </div>
          @endif

          @if($payment->admin_note)
            <div class="pt-3 border-top tx-13">
              <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.platform.internal_admin_note_label') }}</div>
              <div class="tx-color-02">{{ $payment->admin_note }}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Customer Workspace Context --}}
      @if($payment->company)
        <div class="card mg-b-20">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.platform.company_context_heading') }}</h6>
            @include('partials.company-status-badge', ['status' => $payment->company->effectiveStatus()])
          </div>
          <div class="card-body tx-13">
            <div class="row row-sm">
              <div class="col-sm-6 mg-b-10">
                <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.workspace') }}</div>
                <div class="tx-semibold tx-color-01">{{ $payment->company->name }} <span class="stk-tenant-code ml-1">{{ $payment->company->code }}</span></div>
              </div>
              <div class="col-sm-6 mg-b-10">
                <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.current_plan') }}</div>
                <div class="tx-semibold tx-color-01">{{ $payment->company->subscriptionPlan?->name ?? __('subscription.no_plan') }} ({{ $payment->company->access_type }})</div>
              </div>
              <div class="col-sm-6">
                <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.activated_at') }}</div>
                <div>{{ $payment->company->activated_at ? \App\Support\StockoraFormat::dateTime($payment->company->activated_at) : __('platform.common.pending') }}</div>
              </div>
              <div class="col-sm-6">
                <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.customer.expires_at') }}</div>
                <div>{{ $payment->company->access_expires_at ? \App\Support\StockoraFormat::dateTime($payment->company->access_expires_at) : __('payments.customer.unlimited_access') }}</div>
              </div>
            </div>
          </div>
        </div>
      @endif

      {{-- Payment Proof Inspection --}}
      <div class="card mg-b-20">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.detail.proof_heading') }}</h6>
        </div>
        <div class="card-body">
          @if($payment->hasProof())
            <div class="tx-12 tx-color-02 mg-b-10">
              <strong>{{ $payment->payment_proof_original_name }}</strong>
              @if($payment->payment_proof_size)
                <span class="tx-color-03">({{ number_format($payment->payment_proof_size / 1024, 1) }} KB)</span>
              @endif
            </div>
            @if($payment->isImageProof())
              <div class="border rounded overflow-hidden mg-b-12 text-center" style="background-color: #f8fafc;">
                <img src="{{ route('platform.payments.proof', $payment) }}" alt="{{ $payment->payment_reference }}" style="max-width: 100%; max-height: 360px; object-fit: contain;">
              </div>
            @endif
            <a href="{{ route('platform.payments.proof', ['payment' => $payment, 'download' => 1]) }}" class="btn btn-white btn-sm">
              <i data-feather="download" class="svg-14"></i> {{ __('payments.detail.proof_download_btn') }}
            </a>
          @else
            <div class="tx-13 tx-color-03">{{ __('payments.detail.proof_none') }}</div>
          @endif
        </div>
      </div>

      {{-- Audit Trail --}}
      <div class="card">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.platform.audit_trail_heading') }}</h6>
        </div>
        <div class="card-body p-0">
          @if($auditLogs->isEmpty())
            <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.customer_show.no_audit_events') }}</div>
          @else
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>{{ __('platform.customer_show.col_timestamp') }}</th>
                    <th>{{ __('platform.customer_show.col_action') }}</th>
                    <th>{{ __('platform.dashboard.col_actor') }}</th>
                    <th>{{ __('platform.customer_show.col_note') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($auditLogs as $log)
                    <tr>
                      <td class="tx-12 tx-color-03 text-nowrap">{{ $log->created_at ? \App\Support\StockoraFormat::dateTime($log->created_at) : '—' }}</td>
                      <td><span class="badge badge-light">{{ $log->action }}</span></td>
                      <td class="tx-12">
                        @if($log->platformAdmin)
                          <strong>{{ $log->platformAdmin->name }}</strong>
                        @elseif($log->actorUser)
                          <strong>{{ $log->actorUser->name }}</strong>
                        @else
                          {{ __('platform.common.system') }}
                        @endif
                      </td>
                      <td class="tx-12">{{ $log->note ?? '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Right Column: Verification, Rejection & Subscription Application Actions --}}
    <div class="col-lg-5">
      @if($payment->isPending())
        {{-- Verify Payment --}}
        <div class="card border-success mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold text-success">{{ __('payments.platform.verify_heading') }}</h6>
          </div>
          <div class="card-body">
            <p class="tx-12 tx-color-03">{{ __('payments.platform.verify_help') }}</p>
            <form action="{{ route('platform.payments.verify', $payment) }}" method="POST">
              @csrf
              <div class="custom-control custom-checkbox mg-b-12">
                <input type="checkbox" class="custom-control-input" id="confirm_verify" name="confirm_verify" value="1" required>
                <label class="custom-control-label tx-13 tx-semibold" for="confirm_verify">
                  {{ __('payments.platform.verify_checkbox') }}
                </label>
              </div>
              <div class="form-group">
                <label for="verification_note">{{ __('payments.platform.verify_note_label') }}</label>
                <input type="text" name="verification_note" id="verification_note" class="form-control form-control-sm" value="{{ old('verification_note') }}">
              </div>
              <div class="form-group">
                <label for="verify_admin_note">{{ __('payments.platform.internal_admin_note_label') }}</label>
                <textarea name="admin_note" id="verify_admin_note" rows="2" class="form-control form-control-sm">{{ old('admin_note', $payment->admin_note) }}</textarea>
              </div>
              <button type="submit" class="btn btn-success btn-block">{{ __('payments.platform.verify_btn') }}</button>
            </form>
          </div>
        </div>

        {{-- Reject Payment --}}
        <div class="card border-danger mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold text-danger">{{ __('payments.platform.reject_heading') }}</h6>
          </div>
          <div class="card-body">
            <p class="tx-12 tx-color-03">{{ __('payments.platform.reject_help') }}</p>
            <form action="{{ route('platform.payments.reject', $payment) }}" method="POST">
              @csrf
              <div class="form-group">
                <label for="rejection_reason">{{ __('payments.platform.reject_reason_label') }} <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" id="rejection_reason" rows="2" class="form-control form-control-sm" required>{{ old('rejection_reason') }}</textarea>
              </div>
              <div class="form-group">
                <label for="reject_admin_note">{{ __('payments.platform.internal_admin_note_label') }}</label>
                <input type="text" name="admin_note" id="reject_admin_note" class="form-control form-control-sm" value="{{ old('admin_note', $payment->admin_note) }}">
              </div>
              <button type="submit" class="btn btn-danger btn-block">{{ __('payments.platform.reject_btn') }}</button>
            </form>
          </div>
        </div>

        {{-- Admin Cancel --}}
        <div class="card">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.platform.admin_cancel_heading') }}</h6>
          </div>
          <div class="card-body">
            <form action="{{ route('platform.payments.cancel', $payment) }}" method="POST">
              @csrf
              <div class="form-group">
                <input type="text" name="cancellation_reason" class="form-control form-control-sm" placeholder="{{ __('payments.detail.cancel_reason_ph') }}">
              </div>
              <button type="submit" class="btn btn-white btn-sm btn-block text-danger">{{ __('payments.platform.admin_cancel_btn') }}</button>
            </form>
          </div>
        </div>
      @elseif($payment->isVerified())
        @if($payment->isApplied())
          <div class="card border-success">
            <div class="card-header">
              <h6 class="mg-b-0 tx-semibold text-success">{{ __('payments.platform.already_applied_title') }}</h6>
            </div>
            <div class="card-body tx-13">
              <p class="mb-0">
                {{ __('payments.platform.already_applied_body', [
                    'date' => \App\Support\StockoraFormat::dateTime($payment->applied_to_subscription_at),
                    'admin' => $payment->appliedByAdmin?->name ?? 'Platform Admin',
                    'action' => $payment->applied_action ?? 'APPLIED',
                    'expires' => $payment->period_ends_at ? \App\Support\StockoraFormat::dateTime($payment->period_ends_at) : __('payments.customer.unlimited_access'),
                ]) }}
              </p>
            </div>
          </div>
        @else
          <div class="card border-primary">
            <div class="card-header">
              <h6 class="mg-b-0 tx-semibold text-primary">{{ __('payments.platform.apply_heading') }}</h6>
            </div>
            <div class="card-body">
              <p class="tx-12 tx-color-03">{{ __('payments.platform.apply_help') }}</p>

              @if(!$applicationPreview['can_apply'])
                <div class="alert alert-danger mb-0 tx-13">
                  {{ $applicationPreview['blocked_reason'] }}
                </div>
              @else
                <div class="p-3 border rounded mg-b-15 tx-12" style="background-color: var(--stk-bg-canvas);">
                  <div class="tx-semibold tx-color-01 mg-b-8">{{ __('payments.platform.apply_preview_title') }}</div>
                  <div class="d-flex justify-content-between mg-b-4">
                    <span class="tx-color-03">{{ __('payments.platform.apply_preview_transition') }}</span>
                    <strong>{{ $applicationPreview['from_status'] }} &rarr; {{ $applicationPreview['target_status'] }}</strong>
                  </div>
                  <div class="d-flex justify-content-between mg-b-4">
                    <span class="tx-color-03">{{ __('payments.platform.apply_preview_action') }}</span>
                    <strong>{{ $applicationPreview['applied_action'] }}</strong>
                  </div>
                  <div class="d-flex justify-content-between mg-b-4">
                    <span class="tx-color-03">{{ __('payments.platform.apply_preview_plan') }}</span>
                    <strong>{{ $payment->subscriptionPlan?->name ?? $payment->plan_name_snapshot }}</strong>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="tx-color-03">{{ __('payments.platform.apply_preview_expires') }}</span>
                    <strong>{{ $applicationPreview['period_ends_at'] ? \App\Support\StockoraFormat::dateTime($applicationPreview['period_ends_at']) : __('payments.customer.unlimited_access') }}</strong>
                  </div>
                </div>

                <form action="{{ route('platform.payments.apply', $payment) }}" method="POST">
                  @csrf
                  <div class="form-group">
                    <label for="expiration_action">{{ __('payments.platform.apply_expiry_mode_label') }}</label>
                    <select name="expiration_action" id="expiration_action" class="form-control form-control-sm">
                      <option value="AUTO" selected>{{ __('payments.expiry_actions.AUTO') }}</option>
                      <option value="KEEP_EXISTING">{{ __('payments.expiry_actions.KEEP_EXISTING') }}</option>
                      <option value="EXTEND_CURRENT">{{ __('payments.expiry_actions.EXTEND_CURRENT') }}</option>
                      <option value="SET_FROM_START">{{ __('payments.expiry_actions.SET_FROM_START') }}</option>
                      <option value="CUSTOM_DATE">{{ __('payments.expiry_actions.CUSTOM_DATE') }}</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="apply_duration_days">{{ __('payments.platform.apply_duration_days_label') }}</label>
                    <input type="number" name="duration_days" id="apply_duration_days" class="form-control form-control-sm" min="1" max="3650" value="{{ old('duration_days', $payment->duration_days) }}">
                  </div>

                  <div class="form-group" id="customExpiresGroup" style="display: none;">
                    <label for="custom_expires_at">{{ __('payments.platform.apply_custom_date_label') }}</label>
                    <input type="date" name="custom_expires_at" id="custom_expires_at" class="form-control form-control-sm" value="{{ old('custom_expires_at', now()->addDays($payment->duration_days)->toDateString()) }}">
                  </div>

                  <div class="form-group">
                    <label for="apply_note">{{ __('payments.detail.verified_note_label') }}</label>
                    <input type="text" name="note" id="apply_note" class="form-control form-control-sm" value="{{ old('note') }}">
                  </div>

                  <div class="form-group">
                    <label for="apply_admin_note">{{ __('payments.platform.internal_admin_note_label') }}</label>
                    <textarea name="admin_note" id="apply_admin_note" rows="2" class="form-control form-control-sm">{{ old('admin_note', $payment->admin_note) }}</textarea>
                  </div>

                  <div class="custom-control custom-checkbox mg-b-14">
                    <input type="checkbox" class="custom-control-input" id="confirm_apply" name="confirm_apply" value="1" required>
                    <label class="custom-control-label tx-13 tx-semibold" for="confirm_apply">
                      {{ __('payments.platform.apply_checkbox') }}
                    </label>
                  </div>

                  <button type="submit" class="btn btn-brand-01 btn-block">{{ __('payments.platform.apply_btn') }}</button>
                </form>
              @endif
            </div>
          </div>
        @endif
      @elseif($payment->isRejected())
        <div class="card border-danger">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold text-danger">{{ __('payments.statuses.REJECTED') }}</h6>
          </div>
          <div class="card-body tx-13">
            <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.detail.rejected_at_label') }}</div>
            <div class="mg-b-10">{{ \App\Support\StockoraFormat::dateTime($payment->rejected_at) }}</div>
            <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.detail.rejection_reason_label') }}</div>
            <div class="tx-semibold text-danger">{{ $payment->rejection_reason }}</div>
          </div>
        </div>
      @else
        <div class="card">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.statuses.CANCELLED') }}</h6>
          </div>
          <div class="card-body tx-13">
            <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.detail.cancelled_at_label') }}</div>
            <div class="mg-b-10">{{ \App\Support\StockoraFormat::dateTime($payment->cancelled_at) }}</div>
            <div class="tx-11 text-uppercase tx-color-03 mg-b-2">{{ __('payments.detail.cancellation_reason_label') }}</div>
            <div>{{ $payment->cancellation_reason ?: '—' }}</div>
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script>
$(function () {
  function syncCustomDateVisibility() {
    if ($('#expiration_action').val() === 'CUSTOM_DATE') {
      $('#customExpiresGroup').show();
    } else {
      $('#customExpiresGroup').hide();
    }
  }
  $('#expiration_action').on('change', syncCustomDateVisibility);
  syncCustomDateVisibility();
});
</script>
@endpush
