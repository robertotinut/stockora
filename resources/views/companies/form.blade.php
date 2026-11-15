@extends('layouts.admin')

@section('title', $company->exists ? __('operations.company_ui.edit_title') : __('operations.company_ui.create_title'))
@section('page_title', $company->exists ? __('operations.company_ui.edit_heading', ['code' => $company->code]) : __('operations.company_ui.create_heading'))
@section('page_subtitle', __('operations.company_ui.form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('companies.index') }}">{{ __('operations.company_ui.breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $company->exists ? __('app.edit') : __('app.create') }}</li>
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-lg-7 mg-b-20 mg-lg-b-0">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('operations.company_ui.workspace_details') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($company->exists)
              @method('PUT')
            @endif

            <div class="row row-sm">
              <div class="col-md-5 form-group">
                <label>{{ __('operations.company_ui.company_code') }} <span class="tx-danger">*</span></label>
                <input type="text" name="code" class="form-control stk-mono text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $company->code) }}" placeholder="{{ __('operations.company_ui.company_code_placeholder') }}" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="form-text text-muted">{{ __('operations.company_ui.company_code_help') }}</small>
              </div>

              <div class="col-md-7 form-group">
                <label>{{ __('operations.company_ui.col_company_name') }} <span class="tx-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $company->name) }}" placeholder="{{ __('operations.company_ui.company_name_placeholder') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label>{{ __('operations.company_ui.business_industry') }}</label>
                <select name="industry" class="form-control @error('industry') is-invalid @enderror">
                  <option value="">{{ __('operations.company_ui.industry_optional') }}</option>
                  @foreach(($industries ?? \App\Models\Company::INDUSTRIES) as $item)
                    <option value="{{ $item }}" {{ old('industry', $company->industry) === $item ? 'selected' : '' }}>{{ $item }}</option>
                  @endforeach
                </select>
                @error('industry')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6 form-group">
                <label>{{ __('operations.company_ui.phone_number') }}</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $company->phone) }}" placeholder="e.g. +62 21 555 0188">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-group">
              <label>{{ __('app.address') }}</label>
              <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('operations.company_ui.address_placeholder') }}">{{ old('address', $company->address) }}</textarea>
              @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
              <label>{{ __('auth.company_logo_label') ?? __('onboarding.company_logo_label') }}</label>
              <div class="stk-upload-box">
                <div class="stk-logo-preview">
                  @if($company->exists && $company->logoUrl())
                    <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}">
                  @else
                    <i data-feather="image" class="svg-18 tx-color-04"></i>
                  @endif
                </div>
                <div class="flex-grow-1" style="min-width: 0;">
                  <input type="file" name="logo" class="form-control-file tx-13 @error('logo') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                  <small class="form-text text-muted mb-0">{{ __('operations.company_ui.logo_help') }}</small>
                  @if($company->exists && $company->logo_path)
                    <div class="custom-control custom-checkbox mg-t-6">
                      <input type="checkbox" name="remove_logo" value="1" class="custom-control-input" id="removeLogoCheck">
                      <label class="custom-control-label tx-12 text-danger" for="removeLogoCheck">{{ __('operations.company_ui.remove_current_logo') }}</label>
                    </div>
                  @endif
                  @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>

            @if($company->exists)
              <div class="form-group mg-b-0">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="isActiveCheck" {{ old('is_active', $company->is_active) ? 'checked' : '' }}>
                  <label class="custom-control-label" for="isActiveCheck">{{ __('operations.company_ui.active_workspace_check') }}</label>
                </div>
              </div>
            @endif

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ route('companies.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $company->exists ? __('app.save_changes') : __('operations.company_ui.create_workspace_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('operations.company_ui.isolation_policy_title') }}</h6>
        </div>
        <div class="card-body tx-13">
          <p class="tx-color-03 mg-b-12">
            {{ __('operations.company_ui.isolation_policy_body') }}
          </p>
          @if(!$company->exists)
            <div class="alert alert-info tx-12 mg-b-0">
              {{ __('operations.company_ui.create_info_banner', ['code' => 'WH-MAIN']) }}
            </div>
          @else
            <div class="alert alert-warning tx-12 mg-b-0">
              {{ __('operations.company_ui.edit_warning_banner') }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
