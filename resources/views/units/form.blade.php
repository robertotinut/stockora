@extends('layouts.admin')

@section('title', $unit->exists ? __('products.edit_unit') : __('products.create_unit'))
@section('page_title', $unit->exists ? __('products.edit_unit_heading', ['name' => $unit->name]) : __('products.create_unit_heading'))
@section('page_subtitle', __('products.unit_form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('units.index') }}">{{ __('products.units_nav') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $unit->exists ? __('app.edit') : __('app.add') }}</li>
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-6">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.unit_details_title') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ $unit->exists ? route('units.update', $unit) : route('units.store') }}" method="POST">
            @csrf
            @if($unit->exists)
              @method('PUT')
            @endif

            <div class="form-group">
              <label>{{ __('products.unit_name_label') }} <span class="tx-danger">*</span></label>
              <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $unit->name) }}" placeholder="{{ __('products.unit_name_placeholder') }}" required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group mg-b-0">
              <label>{{ __('products.unit_symbol') }} <span class="tx-danger">*</span></label>
              <input type="text" name="symbol" class="form-control @error('symbol') is-invalid @enderror" value="{{ old('symbol', $unit->symbol) }}" placeholder="{{ __('products.unit_symbol_placeholder') }}" required>
              @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ route('units.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $unit->exists ? __('app.save_changes') : __('products.create_unit_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
