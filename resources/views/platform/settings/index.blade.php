@extends('layouts.platform')

@section('title', __('platform.settings_title'))
@section('page_title', __('platform.settings.heading'))
@section('page_subtitle', __('platform.settings.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.settings.breadcrumb') }}</li>
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-lg-6 mg-b-20">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.settings.config_heading') }}</h6>
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tbody>
              <tr>
                <th class="tx-12 tx-color-03 border-top-0">{{ __('platform.settings.active_db') }}</th>
                <td class="border-top-0"><code>{{ $databaseName }}</code></td>
              </tr>
              <tr>
                <th class="tx-12 tx-color-03">{{ __('platform.settings.auth_guard') }}</th>
                <td><code>{{ __('platform.settings.auth_guard_desc') }}</code></td>
              </tr>
              <tr>
                <th class="tx-12 tx-color-03">{{ __('platform.settings.mail_transport') }}</th>
                <td>
                  <code>{{ $mailTransport }}</code>
                  @if($mailTransport === 'log')
                    <span class="badge badge-warning mg-l-5">{{ __('platform.settings.mail_sandbox_badge') }}</span>
                  @else
                    <span class="badge badge-success mg-l-5">{{ __('platform.settings.mail_active_badge') }}</span>
                  @endif
                </td>
              </tr>
              <tr>
                <th class="tx-12 tx-color-03">{{ __('platform.settings.expiration_enforcement') }}</th>
                <td class="tx-12">{{ __('platform.settings.expiration_desc') }}</td>
              </tr>
              <tr>
                <th class="tx-12 tx-color-03">{{ __('platform.settings.support_email') }}</th>
                <td>{{ $support['email'] ?? '—' }}</td>
              </tr>
              <tr>
                <th class="tx-12 tx-color-03">{{ __('platform.settings.support_phone') }}</th>
                <td>{{ $support['phone'] ?? '—' }} ({{ $support['hours'] ?? '—' }})</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mg-b-20">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.settings.super_admins_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.settings.cli_hint') }} <code>php artisan stockora:platform-admin:create</code></span>
          </div>
          <span class="badge badge-dark">{{ $platformAdmins->count() }}</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('platform.settings.col_operator') }}</th>
                  <th>{{ __('app.status') }}</th>
                  <th>{{ __('platform.settings.col_last_login') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($platformAdmins as $pa)
                  <tr>
                    <td>
                      <div class="tx-semibold tx-color-01">{{ $pa->name }}</div>
                      <div class="tx-11 tx-color-03">{{ $pa->email }}</div>
                    </td>
                    <td>
                      <span class="badge {{ $pa->is_active ? 'badge-success' : 'badge-secondary' }}">
                        {{ $pa->is_active ? __('app.active') : __('app.inactive') }}
                      </span>
                    </td>
                    <td class="tx-12 tx-color-03">{{ $pa->last_login_at ? \App\Support\StockoraFormat::dateTime($pa->last_login_at) : __('platform.settings.never') }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Manual Billing & Renewal Reminder Settings --}}
  <div class="card mg-b-20" id="billingSettingsSection">
    <div class="card-header">
      <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('payments.platform.settings_heading') }}</h6>
      <span class="tx-12 tx-color-03">{{ __('payments.platform.settings_sub') }}</span>
    </div>
    <div class="card-body">
      <form action="{{ route('platform.settings.billing.update') }}" method="POST">
        @csrf
        <div class="row row-sm">
          <div class="col-md-4 form-group">
            <label for="renewal_reminder_windows">{{ __('payments.platform.reminder_windows_label') }} <span class="text-danger">*</span></label>
            <input type="text" name="renewal_reminder_windows" id="renewal_reminder_windows" class="form-control" value="{{ old('renewal_reminder_windows', implode(', ', $billingSettings['renewal_reminder_windows'] ?? [30, 7, 3])) }}" required>
          </div>
          <div class="col-md-4 form-group">
            <label for="payment_proof_max_kb">{{ __('payments.platform.proof_max_kb_label') }} <span class="text-danger">*</span></label>
            <input type="number" name="payment_proof_max_kb" id="payment_proof_max_kb" class="form-control" min="512" max="5120" value="{{ old('payment_proof_max_kb', $billingSettings['payment_proof_max_kb'] ?? 5120) }}" required>
          </div>
          <div class="col-md-4 form-group d-flex align-items-end">
            <div class="custom-control custom-checkbox mg-b-8">
              <input type="checkbox" class="custom-control-input" id="allow_customer_payment_submissions" name="allow_customer_payment_submissions" value="1" {{ old('allow_customer_payment_submissions', ($billingSettings['allow_customer_payment_submissions'] ?? true) ? '1' : '') ? 'checked' : '' }}>
              <label class="custom-control-label tx-13" for="allow_customer_payment_submissions">{{ __('payments.platform.allow_submissions_label') }}</label>
            </div>
          </div>
          <div class="col-md-6 form-group">
            <label for="verification_sla_note">{{ __('payments.platform.sla_note_en_label') }}</label>
            <input type="text" name="verification_sla_note" id="verification_sla_note" class="form-control" value="{{ old('verification_sla_note', $billingSettings['verification_sla_note'] ?? '') }}">
          </div>
          <div class="col-md-6 form-group">
            <label for="verification_sla_note_id">{{ __('payments.platform.sla_note_id_label') }}</label>
            <input type="text" name="verification_sla_note_id" id="verification_sla_note_id" class="form-control" value="{{ old('verification_sla_note_id', $billingSettings['verification_sla_note_id'] ?? '') }}">
          </div>
        </div>
        <button type="submit" class="btn btn-brand-01 btn-sm">{{ __('payments.platform.save_billing_settings_btn') }}</button>
      </form>
    </div>
  </div>

  {{-- Manual Payment Instructions CRUD --}}
  <div class="card mg-b-20" id="paymentInstructionsSection">
    <div class="card-header">
      <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('payments.customer.instructions_heading') }}</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>{{ __('payments.customer.col_method') }}</th>
              <th>Bank / Account</th>
              <th>{{ __('app.status') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($paymentInstructions as $inst)
              <tr>
                <td>
                  <span class="stk-tenant-code">{{ $inst->method_code }}</span>
                  @if($inst->is_default)
                    <span class="badge badge-primary ml-1">{{ __('payments.customer.default_channel') }}</span>
                  @endif
                </td>
                <td>
                  <div class="tx-semibold">{{ $inst->method_name }}</div>
                  <div class="tx-11 tx-color-03">{{ $inst->channelLabel() }}</div>
                </td>
                <td class="tx-12">
                  <div>{{ $inst->bank_name ?: '—' }} &bull; {{ $inst->account_number ?: '—' }}</div>
                  <div class="tx-11 tx-color-03">{{ $inst->account_name ?: '—' }}</div>
                </td>
                <td>
                  <span class="badge {{ $inst->is_active ? 'badge-success' : 'badge-secondary' }}">
                    {{ $inst->is_active ? __('app.active') : __('app.inactive') }}
                  </span>
                </td>
                <td class="text-right">
                  <form action="{{ route('platform.payment-instructions.toggle', $inst) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-white">
                      {{ $inst->is_active ? __('app.inactive') : __('app.active') }}
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-body border-top">
      <h6 class="tx-semibold tx-13 mg-b-12">{{ __('payments.platform.add_instruction_heading') }}</h6>
      <form action="{{ route('platform.payment-instructions.store') }}" method="POST">
        @csrf
        <div class="row row-sm">
          <div class="col-md-3 form-group">
            <input type="text" name="method_code" class="form-control form-control-sm" placeholder="BANK_BRI_MANUAL" required>
          </div>
          <div class="col-md-4 form-group">
            <input type="text" name="method_name" class="form-control form-control-sm" placeholder="Bank Transfer - BRI" required>
          </div>
          <div class="col-md-3 form-group">
            <select name="channel_type" class="form-control form-control-sm" required>
              @foreach($channelTypes as $chKey => $chLabel)
                <option value="{{ $chKey }}">{{ __('payments.channels.' . $chKey) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 form-group">
            <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="10">
          </div>
          <div class="col-md-4 form-group">
            <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Bank BRI">
          </div>
          <div class="col-md-4 form-group">
            <input type="text" name="account_name" class="form-control form-control-sm" placeholder="PT Stockora Demo">
          </div>
          <div class="col-md-4 form-group">
            <input type="text" name="account_number" class="form-control form-control-sm" placeholder="000-000-0000 (Placeholder)">
          </div>
          <div class="col-md-6 form-group">
            <input type="text" name="instructions" class="form-control form-control-sm" placeholder="Instructions (EN)">
          </div>
          <div class="col-md-6 form-group">
            <input type="text" name="instructions_id" class="form-control form-control-sm" placeholder="Instruksi (ID)">
          </div>
          <div class="col-12 d-flex align-items-center justify-content-between">
            <div class="d-flex" style="gap: 16px;">
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="new_inst_active" name="is_active" value="1" checked>
                <label class="custom-control-label tx-12" for="new_inst_active">{{ __('app.active') }}</label>
              </div>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="new_inst_default" name="is_default" value="1">
                <label class="custom-control-label tx-12" for="new_inst_default">{{ __('payments.customer.default_channel') }}</label>
              </div>
            </div>
            <button type="submit" class="btn btn-brand-01 btn-sm">{{ __('app.save') }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
