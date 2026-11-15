@extends($useAdminLayout ? 'layouts.admin' : 'layouts.billing-shell')

@section('title', __('payments.detail.page_title', ['ref' => $payment->payment_reference]))
@section('page_title', __('payments.detail.page_heading'))
@section('page_subtitle', __('payments.detail.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('subscription.index') }}">{{ __('payments.customer.breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $payment->payment_reference }}</li>
@endsection

@section('actions')
  <a href="{{ route('subscription.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('payments.customer.back_subscription') }}
  </a>
@endsection

@section('content')
  <div class="row row-sm">
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
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.submitted_at_label') }}</div>
              <div class="tx-color-01">{{ \App\Support\StockoraFormat::dateTime($payment->submitted_at ?? $payment->created_at) }}</div>
            </div>
          </div>

          @if($payment->customer_note)
            <div class="pt-3 border-top tx-13">
              <div class="tx-11 text-uppercase tx-color-03 mg-b-4">{{ __('payments.detail.customer_note_label') }}</div>
              <div class="tx-color-02">{{ $payment->customer_note }}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Verification & Application Progress --}}
      <div class="card">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.detail.verification_section') }}</h6>
        </div>
        <div class="card-body tx-13">
          @if($payment->isVerified())
            <div class="mg-b-10">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.verified_at_label') }}</div>
              <div class="tx-semibold text-success">{{ \App\Support\StockoraFormat::dateTime($payment->verified_at) }}</div>
              @if($payment->verification_note)
                <div class="tx-12 tx-color-02 mg-t-4">{{ $payment->verification_note }}</div>
              @endif
            </div>
          @endif

          @if($payment->isRejected())
            <div class="alert alert-danger mb-0">
              <div class="tx-11 text-uppercase mg-b-2">{{ __('payments.detail.rejection_reason_label') }} ({{ \App\Support\StockoraFormat::dateTime($payment->rejected_at) }})</div>
              <div class="tx-semibold">{{ $payment->rejection_reason }}</div>
            </div>
          @endif

          @if($payment->isCancelled())
            <div class="alert alert-secondary mb-0">
              <div class="tx-11 text-uppercase mg-b-2">{{ __('payments.detail.cancelled_at_label') }}: {{ \App\Support\StockoraFormat::dateTime($payment->cancelled_at) }}</div>
              <div>{{ $payment->cancellation_reason ?: '—' }}</div>
            </div>
          @endif

          @if($payment->isApplied())
            <div class="pt-3 border-top">
              <div class="tx-11 text-uppercase tx-color-03">{{ __('payments.detail.applied_at_label') }}</div>
              <div class="tx-semibold tx-color-01">{{ \App\Support\StockoraFormat::dateTime($payment->applied_to_subscription_at) }}</div>
              <div class="tx-12 tx-color-02 mg-t-4">
                {{ __('payments.detail.applied_period_label') }}:
                @if($payment->period_ends_at)
                  <strong>{{ \App\Support\StockoraFormat::dateTime($payment->period_ends_at) }}</strong>
                @else
                  <strong>{{ __('payments.customer.unlimited_access') }}</strong>
                @endif
              </div>
            </div>
          @elseif($payment->isVerified())
            <div class="alert alert-info mb-0 mg-t-10">
              {{ __('payments.customer.awaiting_apply_badge') }}
            </div>
          @elseif($payment->isPending())
            <div class="tx-color-03">{{ __('payments.customer.manual_policy_note') }}</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      {{-- Private Payment Proof Card --}}
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
                <img src="{{ route('subscription.payments.proof', $payment->id) }}" alt="{{ $payment->payment_reference }}" style="max-width: 100%; max-height: 320px; object-fit: contain;">
              </div>
            @endif
            <a href="{{ route('subscription.payments.proof', ['paymentId' => $payment->id, 'download' => 1]) }}" class="btn btn-white btn-sm btn-block">
              <i data-feather="download" class="svg-14"></i> {{ __('payments.detail.proof_download_btn') }}
            </a>
          @else
            <div class="tx-13 tx-color-03">{{ __('payments.detail.proof_none') }}</div>
          @endif
        </div>
      </div>

      {{-- Customer Cancel Action --}}
      @if($canManageBilling && $payment->canCustomerCancel())
        <div class="card border-warning">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold text-warning">{{ __('payments.detail.cancel_heading') }}</h6>
          </div>
          <div class="card-body">
            <p class="tx-12 tx-color-03">{{ __('payments.detail.cancel_help') }}</p>
            <form action="{{ route('subscription.payments.cancel', $payment->id) }}" method="POST" onsubmit="return confirm(@json(__('payments.detail.cancel_confirm')));">
              @csrf
              <div class="form-group">
                <input type="text" name="cancellation_reason" class="form-control form-control-sm" placeholder="{{ __('payments.detail.cancel_reason_ph') }}">
              </div>
              <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
                <i data-feather="x-circle" class="svg-14"></i> {{ __('payments.detail.cancel_btn') }}
              </button>
            </form>
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection
