@extends('layouts.platform')

@section('title', __('platform.features_title'))
@section('page_title', __('platform.features_ui.heading'))
@section('page_subtitle', __('platform.features_ui.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.features_title') }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.plans.index') }}" class="btn btn-white">
    <i data-feather="layers" class="svg-14"></i> {{ __('platform.features_ui.manage_plans_btn') }}
  </a>
  <button type="button" class="btn btn-brand-01" data-toggle="modal" data-target="#createFeatureModal">
    <i data-feather="plus" class="svg-14"></i> {{ __('platform.features_ui.add_feature_btn') }}
  </button>
@endsection

@section('content')
  <div class="card mg-b-20">
    <div class="card-body">
      <form action="{{ route('platform.features.index') }}" method="GET" class="row row-sm align-items-end">
        <div class="col-md-4 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('app.category') }}</label>
          <select name="category" class="form-control">
            <option value="">{{ __('platform.features_ui.all_categories') }}</option>
            @foreach($categories as $cat)
              @php
                $catKey = "subscription.categories.{$cat}";
                $catText = \Illuminate\Support\Facades\Lang::has($catKey) ? __($catKey) : $cat;
              @endphp
              <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $catText }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.features_ui.feature_type') }}</label>
          <select name="type" class="form-control">
            <option value="">{{ __('platform.features_ui.all_types') }}</option>
            @foreach($types as $tKey => $tLabel)
              <option value="{{ $tKey }}" {{ $type === $tKey ? 'selected' : '' }}>{{ $tKey }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('app.status') }}</label>
          <select name="status" class="form-control">
            <option value="">{{ __('platform.common.all') }}</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('app.active') }}</option>
            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>{{ __('app.inactive') }}</option>
          </select>
        </div>
        <div class="col-md-3 d-flex" style="gap: 8px;">
          <button type="submit" class="btn btn-brand-01 flex-grow-1">{{ __('app.filter') }}</button>
          <a href="{{ route('platform.features.index') }}" class="btn btn-white">{{ __('app.reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  @forelse($groupedFeatures as $catName => $items)
    @php
      $catKey = "subscription.categories.{$catName}";
      $catLabel = \Illuminate\Support\Facades\Lang::has($catKey) ? __($catKey) : $catName;
    @endphp
    <div class="card mg-b-20">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mg-b-0 tx-semibold tx-color-01">{{ $catLabel }}</h6>
        <span class="badge badge-light">{{ __('platform.features_ui.features_count', ['count' => $items->count()]) }}</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.plans_ui.col_key') }}</th>
                <th>{{ __('platform.features_ui.col_feature_desc') }}</th>
                <th>{{ __('app.type') }}</th>
                <th>{{ __('platform.features_ui.col_plans_attached') }}</th>
                <th>{{ __('app.status') }}</th>
                <th class="text-right">{{ __('app.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($items as $feat)
                @php
                  $featTrans = "subscription.features.{$feat->feature_key}";
                  $limTrans = "subscription.limits.{$feat->feature_key}";
                  $featLabel = \Illuminate\Support\Facades\Lang::has($featTrans)
                    ? __($featTrans)
                    : (\Illuminate\Support\Facades\Lang::has($limTrans) ? __($limTrans) : $feat->name);
                @endphp
                <tr>
                  <td class="tx-12 text-nowrap"><code>{{ $feat->feature_key }}</code></td>
                  <td>
                    <div class="tx-semibold tx-color-01">{{ $featLabel }}</div>
                    @if(app()->getLocale() === 'en' && $feat->description)
                      <div class="tx-11 tx-color-03">{{ $feat->description }}</div>
                    @endif
                    @if(isset($limitSpecs[$feat->feature_key]))
                      <div class="tx-11 text-primary mg-t-2">
                        {{ __('platform.features_ui.basis_prefix') }} <code>{{ $limitSpecs[$feat->feature_key]['table'] }}</code> ({{ $limitSpecs[$feat->feature_key]['basis'] }})
                      </div>
                    @endif
                  </td>
                  <td class="tx-11">
                    <span class="badge badge-light">{{ $feat->feature_type }}</span>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap" style="gap: 4px;">
                      @forelse($feat->plans as $p)
                        @php $isNotIncluded = $feat->isBoolean() && !in_array(strtolower((string) $p->pivot?->value), ['1', 'true', 'yes', 'on'], true); @endphp
                        @php $formatted = $feat->formatDisplayValue($p->pivot?->value); @endphp
                        <a href="{{ route('platform.plans.show', $p) }}" class="badge {{ $isNotIncluded ? 'badge-light text-muted' : 'badge-info' }}">
                          {{ $p->code }}: {{ $formatted }}
                        </a>
                      @empty
                        <span class="tx-11 tx-color-03">{{ __('platform.features_ui.no_plans_attached') }}</span>
                      @endforelse
                    </div>
                  </td>
                  <td>
                    @if($feat->is_active)
                      <span class="badge badge-success">{{ __('app.active') }}</span>
                    @else
                      <span class="badge badge-secondary">{{ __('app.inactive') }}</span>
                    @endif
                  </td>
                  <td class="text-right text-nowrap">
                    <div class="d-inline-flex align-items-center" style="gap: 4px;">
                      @if($feat->is_active)
                        <form action="{{ route('platform.features.deactivate', $feat) }}" method="POST" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-white btn-xs text-danger">{{ __('platform.plans_ui.deactivate_btn') }}</button>
                        </form>
                      @else
                        <form action="{{ route('platform.features.activate', $feat) }}" method="POST" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-white btn-xs text-success">{{ __('platform.plans_ui.activate_btn') }}</button>
                        </form>
                      @endif

                      @if($feat->plans->isEmpty())
                        <form action="{{ route('platform.features.destroy', $feat) }}" method="POST" class="d-inline" data-confirm="{{ __('platform.features_ui.confirm_delete_msg', ['key' => $feat->feature_key]) }}" data-confirm-title="{{ __('platform.features_ui.confirm_delete_title') }}">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-white btn-xs text-danger">{{ __('app.delete') }}</button>
                        </form>
                      @else
                        <span class="tx-11 tx-color-03 pd-l-4" title="{{ __('platform.features_ui.in_use_title') }}">{{ __('platform.features_ui.in_use_badge') }}</span>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @empty
    <div class="card mg-b-20">
      <div class="card-body p-4 text-center tx-13 tx-color-03">{{ __('platform.features_ui.no_features_match') }}</div>
    </div>
  @endforelse

  {{-- Unimplemented Future Metrics Transparency Card --}}
  <div class="card">
    <div class="card-header">
      <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.features_ui.notes_heading') }}</h6>
    </div>
    <div class="card-body tx-12 tx-color-02">
      <p class="mg-b-8">
        <strong>{{ __('platform.features_ui.notes_intro_prefix') }}</strong> {{ __('platform.features_ui.notes_intro_body') }}
      </p>
      <ul class="mg-b-0 pd-l-20">
        @foreach($unimplementedNotes as $metric => $reason)
          <li><code>{{ $metric }}</code>: {{ $reason }}</li>
        @endforeach
      </ul>
    </div>
  </div>

  {{-- Create Feature Modal --}}
  <div class="modal fade" id="createFeatureModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <form action="{{ route('platform.features.store') }}" method="POST" class="modal-content">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title tx-semibold">{{ __('platform.features_ui.modal_title') }}</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="new_feat_key">{{ __('platform.features_ui.feature_key') }} <span class="text-danger">*</span></label>
            <input type="text" id="new_feat_key" name="feature_key" class="form-control" placeholder="{{ __('platform.features_ui.feature_key_ph') }}" required>
            <small class="form-text text-muted">{{ __('platform.features_ui.feature_key_help') }}</small>
          </div>
          <div class="form-group">
            <label for="new_feat_name">{{ __('platform.features_ui.display_name') }} <span class="text-danger">*</span></label>
            <input type="text" id="new_feat_name" name="name" class="form-control" required>
          </div>
          <div class="row row-sm">
            <div class="col-md-6 form-group">
              <label for="new_feat_cat">{{ __('app.category') }} <span class="text-danger">*</span></label>
              <select id="new_feat_cat" name="category" class="form-control" required>
                @foreach($categories as $cat)
                  @php
                    $catKey = "subscription.categories.{$cat}";
                    $catText = \Illuminate\Support\Facades\Lang::has($catKey) ? __($catKey) : $cat;
                  @endphp
                  <option value="{{ $cat }}">{{ $catText }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label for="new_feat_type">{{ __('platform.features_ui.feature_type') }} <span class="text-danger">*</span></label>
              <select id="new_feat_type" name="feature_type" class="form-control" required>
                @foreach($types as $tKey => $tLabel)
                  <option value="{{ $tKey }}">{{ $tKey }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="new_feat_default">{{ __('platform.features_ui.default_value_opt') }}</label>
            <input type="text" id="new_feat_default" name="default_value" class="form-control" placeholder="{{ __('platform.features_ui.default_value_ph') }}">
          </div>
          <div class="form-group mb-0">
            <label for="new_feat_desc">{{ __('app.description') }}</label>
            <textarea id="new_feat_desc" name="description" rows="2" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-white" data-dismiss="modal">{{ __('app.cancel') }}</button>
          <button type="submit" class="btn btn-brand-01">{{ __('platform.features_ui.save_feature_btn') }}</button>
        </div>
      </form>
    </div>
  </div>
@endsection
