@extends('layouts.platform')

@section('title', __('platform.customer_create.heading'))
@section('page_title', __('platform.customer_create.heading'))
@section('page_subtitle', __('platform.customer_create.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.customers.index') }}">{{ __('platform.customers_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.customer_create.breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.customers.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('platform.customer_create.back_btn') }}
  </a>
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.customer_create.card_heading') }}</h6>
          <span class="tx-12 tx-color-03">{{ __('platform.customer_create.card_sub') }}</span>
        </div>
        <div class="card-body">
          <form action="{{ route('platform.customers.store') }}" method="POST" id="directCreateCustomerForm">
            @csrf

            <div class="tx-11 text-uppercase tx-semibold tx-color-03 mg-b-12">{{ __('platform.customer_create.sec_company') }}</div>

            <div class="row row-sm">
              <div class="col-md-8 form-group">
                <label for="company_name">{{ __('platform.customer_create.company_name') }} <span class="text-danger">*</span></label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="{{ old('company_name') }}" placeholder="{{ __('platform.customer_create.company_name_ph') }}" required>
              </div>
              <div class="col-md-4 form-group">
                <label for="company_code">{{ __('platform.customer_create.company_code') }}</label>
                <input type="text" id="company_code" name="company_code" class="form-control text-uppercase" value="{{ old('company_code') }}" placeholder="{{ __('platform.customer_create.company_code_ph') }}">
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="industry">{{ __('platform.customer_create.industry') }} <span class="text-danger">*</span></label>
                <select id="industry" name="industry" class="form-control" required>
                  <option value="">{{ __('platform.customer_create.select_industry') }}</option>
                  @foreach($industries as $ind)
                    <option value="{{ $ind }}" {{ old('industry') === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label for="phone">{{ __('platform.customer_create.business_phone') }} <span class="text-danger">*</span></label>
                <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+62 21 555 0100" required>
              </div>
            </div>

            <div class="form-group">
              <label for="address">{{ __('platform.customer_create.business_address') }} <span class="text-danger">*</span></label>
              <textarea id="address" name="address" rows="2" class="form-control" placeholder="{{ __('platform.customer_create.address_ph') }}" required>{{ old('address') }}</textarea>
            </div>

            <hr class="mg-y-24">

            <div class="tx-11 text-uppercase tx-semibold tx-color-03 mg-b-12">{{ __('platform.customer_create.sec_owner') }}</div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="owner_name">{{ __('platform.customer_create.owner_name') }} <span class="text-danger">*</span></label>
                <input type="text" id="owner_name" name="owner_name" class="form-control" value="{{ old('owner_name') }}" placeholder="{{ __('platform.customer_create.owner_name_ph') }}" required>
              </div>
              <div class="col-md-6 form-group">
                <label for="owner_email">{{ __('platform.customer_create.owner_email') }} <span class="text-danger">*</span></label>
                <input type="email" id="owner_email" name="owner_email" class="form-control" value="{{ old('owner_email') }}" placeholder="owner@customer.com" required>
              </div>
            </div>

            <div class="custom-control custom-checkbox mg-b-20">
              <input type="checkbox" class="custom-control-input" id="confirm_existing_owner" name="confirm_existing_owner" value="1" {{ old('confirm_existing_owner') ? 'checked' : '' }}>
              <label class="custom-control-label tx-13 tx-color-02" for="confirm_existing_owner">
                {{ __('platform.customer_create.confirm_existing_owner') }}
              </label>
            </div>

            <hr class="mg-y-24">

            <div class="tx-11 text-uppercase tx-semibold tx-color-03 mg-b-12">{{ __('platform.customer_create.sec_access') }}</div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="access_type">{{ __('platform.customers.access_type_label') }} <span class="text-danger">*</span></label>
                <select id="access_type" name="access_type" class="form-control" required>
                  @foreach($accessTypes as $tKey => $tMeta)
                    <option value="{{ $tKey }}" {{ old('access_type', 'PROFESSIONAL') === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label for="initial_status">{{ __('platform.customer_create.initial_status') }} <span class="text-danger">*</span></label>
                <select id="initial_status" name="initial_status" class="form-control" required>
                  <option value="ACTIVE" {{ old('initial_status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' }}>{{ __('platform.customer_create.status_active_opt') }}</option>
                  <option value="PENDING_ACTIVATION" {{ old('initial_status') === 'PENDING_ACTIVATION' ? 'selected' : '' }}>{{ __('platform.customer_create.status_pending_opt') }}</option>
                </select>
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="access_starts_at">{{ __('platform.customer_create.access_starts_at') }}</label>
                <input type="date" id="access_starts_at" name="access_starts_at" class="form-control" value="{{ old('access_starts_at', now()->toDateString()) }}">
              </div>
              <div class="col-md-6 form-group">
                <label for="access_expires_at">{{ __('platform.customer_create.access_expires_at') }}</label>
                <input type="date" id="access_expires_at" name="access_expires_at" class="form-control" value="{{ old('access_expires_at') }}">
                <small class="form-text text-muted">{{ __('platform.customer_create.expires_help') }}</small>
              </div>
            </div>

            <div class="form-group">
              <label for="admin_notes">{{ __('platform.customer_create.admin_notes') }}</label>
              <textarea id="admin_notes" name="admin_notes" rows="2" class="form-control" placeholder="{{ __('platform.customer_create.admin_notes_ph') }}">{{ old('admin_notes') }}</textarea>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top" style="gap: 10px;">
              <a href="{{ route('platform.customers.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01 px-4">{{ __('platform.customer_create.submit_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4 mg-t-15 mg-lg-t-0">
      <div class="card">
        <div class="card-header">
          <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.customer_create.security_heading') }}</h6>
        </div>
        <div class="card-body tx-13 tx-color-02">
          <p class="mg-b-12">
            <strong>{{ __('platform.customer_create.no_predictable_pw_title') }}</strong> {{ __('platform.customer_create.no_predictable_pw_body') }}
          </p>
          <p class="mg-b-12">
            <strong>{{ __('platform.customer_create.safe_existing_title') }}</strong> {{ __('platform.customer_create.safe_existing_body') }}
          </p>
          <p class="mb-0">
            <strong>{{ __('platform.customer_create.mail_notice_title') }}</strong> {{ __('platform.customer_create.mail_notice_body', ['mailer' => config('mail.default')]) }}
            @if(config('mail.default') === 'log')
              {{ __('platform.customer_create.mail_sandbox_note') }}
            @endif
          </p>
        </div>
      </div>
    </div>
  </div>
@endsection
