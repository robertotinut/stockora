@extends('layouts.admin')

@section('title', __('operations.company_ui.breadcrumb'))
@section('page_title', __('operations.company_ui.title'))
@section('page_subtitle', __('operations.company_ui.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.company_ui.breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('company.select') }}" class="btn btn-white">
    <i data-feather="repeat" class="svg-14"></i> {{ __('operations.company_ui.workspace_switcher') }}
  </a>
  @if(Auth::user()?->canAccessModule('company_settings', 'manage'))
    <a href="{{ route('companies.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('operations.company_ui.new_company') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.company_ui.registered_workspaces') }}</h6>
        <span class="badge badge-light tx-numeric">{{ $companies->total() }}</span>
      </div>
      <form action="{{ route('companies.index') }}" method="GET" class="form-inline" style="gap: 6px;">
        <div class="input-group input-group-sm">
          <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('operations.company_ui.search_placeholder') }}">
          <div class="input-group-append">
            <button type="submit" class="btn btn-white"><i data-feather="search" class="svg-14"></i></button>
          </div>
        </div>
        @if($search !== '')
          <a href="{{ route('companies.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($companies->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('operations.company_ui.no_companies_title'),
          'description' => __('operations.company_ui.no_companies_desc'),
          'icon' => 'briefcase',
          'actionUrl' => Auth::user()?->canAccessModule('company_settings', 'manage') ? route('companies.create') : null,
          'actionLabel' => Auth::user()?->canAccessModule('company_settings', 'manage') ? __('operations.company_ui.create_company_btn') : null,
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.company_ui.col_code') }}</th>
              <th>{{ __('operations.company_ui.col_company_name') }}</th>
              <th>{{ __('operations.company_ui.col_your_role') }}</th>
              <th class="text-right">{{ __('operations.company_ui.col_warehouses') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($companies as $company)
              <tr>
                <td>
                  <div class="d-flex align-items-center" style="gap: 6px;">
                    <span class="stk-tenant-code">{{ $company->code }}</span>
                    @if((int) $activeCompanyId === (int) $company->id)
                      <span class="badge badge-primary">{{ __('operations.company_ui.active_session') }}</span>
                    @endif
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center" style="gap: 10px;">
                    @if($company->logoUrl())
                      <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" style="width: 30px; height: 30px; border-radius: 7px; object-fit: cover; border: 1px solid var(--stk-border);">
                    @endif
                    <div>
                      <div class="tx-medium tx-color-01">{{ $company->name }}</div>
                      @if($company->industry || $company->phone)
                        <div class="tx-11 tx-color-03">
                          {{ $company->industry ?? '' }}{{ ($company->industry && $company->phone) ? ' • ' : '' }}{{ $company->phone ?? '' }}
                        </div>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  @if($company->pivot?->role)
                    @include('partials.role-badge', ['role' => $company->pivot->role])
                  @else
                    <span class="badge badge-light">-</span>
                  @endif
                </td>
                <td class="text-right tx-numeric">{{ $company->warehouses_count }}</td>
                <td class="text-center">
                  @include('partials.status-badge', ['status' => $company->is_active ? 'ACTIVE' : 'INACTIVE'])
                </td>
                <td class="text-right text-nowrap">
                  @if((int) $activeCompanyId !== (int) $company->id && $company->is_active)
                    <form action="{{ route('company.switch') }}" method="POST" class="d-inline">
                      @csrf
                      <input type="hidden" name="company_id" value="{{ $company->id }}">
                      <button type="submit" class="btn btn-xs btn-white">{{ __('operations.company_ui.switch_to') }}</button>
                    </form>
                  @endif
                  @if(in_array($company->pivot->role ?? '', ['owner', 'admin'], true) && (Auth::user()?->canAccessModule('company_settings', 'edit') ?? false))
                    <a href="{{ route('companies.edit', $company) }}" class="btn btn-xs btn-outline-primary mg-l-4">{{ __('app.edit') }}</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($companies->hasPages())
      <div class="card-footer">{{ $companies->links() }}</div>
    @endif
  </div>
@endsection
