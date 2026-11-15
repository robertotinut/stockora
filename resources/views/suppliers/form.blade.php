@extends('layouts.admin')

@section('title', $supplier->exists ? __('suppliers.edit_title') : __('suppliers.create_title'))
@section('page_title', $supplier->exists ? __('suppliers.edit_heading', ['name' => $supplier->name]) : __('suppliers.create_heading'))
@section('page_subtitle', __('suppliers.form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">{{ __('suppliers.page_title') }}</a></li>
  @if($supplier->exists)
    <li class="breadcrumb-item"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->code }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('app.edit') }}</li>
  @else
    <li class="breadcrumb-item active" aria-current="page">{{ __('app.add') }}</li>
  @endif
@endsection

@section('content')
  @if(!$supplier->exists)
    @include('partials.quota-banner', ['limitKey' => 'max_suppliers'])
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('suppliers.profile_title') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" method="POST">
            @csrf
            @if($supplier->exists)
              @method('PUT')
            @endif

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('suppliers.code') }} <span class="tx-danger">*</span></label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $supplier->code) }}" placeholder="SUP-001" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-8">
                <label>{{ __('suppliers.name') }} <span class="tx-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $supplier->name) }}" placeholder="e.g. PT Mitra Distribusi" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('suppliers.pic_label') }}</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}">
              </div>
              <div class="form-group col-md-4">
                <label>{{ __('suppliers.phone_label') }}</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}">
              </div>
              <div class="form-group col-md-4">
                <label>{{ __('app.email') }}</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $supplier->email) }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-group mg-b-0">
              <label>{{ __('app.address') }}</label>
              <textarea name="address" rows="3" class="form-control">{{ old('address', $supplier->address) }}</textarea>
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $supplier->exists ? __('app.save_changes') : __('suppliers.create_sup_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
