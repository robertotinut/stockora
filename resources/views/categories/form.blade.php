@extends('layouts.admin')

@section('title', $category->exists ? __('products.edit_category') : __('products.create_category'))
@section('page_title', $category->exists ? __('products.edit_cat_heading', ['name' => $category->name]) : __('products.create_cat_heading'))
@section('page_subtitle', __('products.cat_form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">{{ __('products.categories_nav') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $category->exists ? __('app.edit') : __('app.add') }}</li>
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-7">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.cat_details_title') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" method="POST">
            @csrf
            @if($category->exists)
              @method('PUT')
            @endif

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('products.cat_code_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $category->code) }}" placeholder="e.g. ATK" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-8">
                <label>{{ __('products.cat_name_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" placeholder="Office Supplies" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-group mg-b-0">
              <label>{{ __('app.description') }}</label>
              <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="{{ __('products.cat_desc_placeholder') }}">{{ old('description', $category->description) }}</textarea>
              @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ route('categories.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $category->exists ? __('app.save_changes') : __('products.create_cat_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
