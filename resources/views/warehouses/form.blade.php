@extends('layouts.admin')

@section('title', $warehouse->exists ? __('warehouses.edit_title') : __('warehouses.create_title'))
@section('page_title', $warehouse->exists ? __('warehouses.edit_heading', ['code' => $warehouse->code]) : __('warehouses.create_heading'))
@section('page_subtitle', __('warehouses.form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">{{ __('warehouses.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $warehouse->exists ? __('app.edit') : __('app.add') }}</li>
@endsection

@section('content')
  @if(!$warehouse->exists || !$warehouse->is_active)
    @include('partials.quota-banner', ['limitKey' => 'max_warehouses'])
  @endif

  <div class="row">
    <div class="col-lg-7">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('warehouses.config_title') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ $warehouse->exists ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" method="POST">
            @csrf
            @if($warehouse->exists)
              @method('PUT')
            @endif

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('warehouses.code') }} <span class="tx-danger">*</span></label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $warehouse->code) }}" placeholder="WH-CBG-01" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-8">
                <label>{{ __('warehouses.name') }} <span class="tx-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $warehouse->name) }}" placeholder="Bandung Branch Warehouse" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-group mg-b-0 pt-2">
              <div class="custom-control custom-checkbox mg-b-10">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="chkActive"
                       {{ old('is_active', $warehouse->is_active) ? 'checked' : '' }}>
                <label class="custom-control-label" for="chkActive">{{ __('warehouses.chk_active_label') }}</label>
              </div>

              <div class="custom-control custom-checkbox">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" class="custom-control-input" id="chkDefault"
                       {{ old('is_default', $warehouse->is_default) ? 'checked' : '' }}>
                <label class="custom-control-label" for="chkDefault">{{ __('warehouses.chk_default_label') }}</label>
              </div>
              <small class="form-text text-muted mg-t-6">{{ __('warehouses.chk_default_hint') }}</small>
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ route('warehouses.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $warehouse->exists ? __('app.save_changes') : __('warehouses.create_wh_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
