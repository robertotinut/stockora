@extends('layouts.admin')

@section('title', __('subscription.locked.page_title'))
@section('page-title', __('subscription.locked.page_title'))

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('navigation.dashboard') }}</a></li>
<li class="breadcrumb-item active">{{ __('subscription.locked.breadcrumb') }}</li>
@endsection

@section('content')
<div class="row justify-content-center py-3">
    <div class="col-lg-8 col-xl-7">
        <div class="card border-warning shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-light text-warning" style="width: 64px; height: 64px;">
                    <i data-feather="lock" style="width: 28px; height: 28px;"></i>
                </div>
                <span class="badge badge-warning mb-2">{{ __('subscription.locked.badge') }}</span>
                <h4 class="tx-22 tx-bold mb-2">{{ __('subscription.locked.heading', ['feature' => $featureLabel]) }}</h4>
                <p class="tx-color-03 mb-4">{{ $message }}</p>

                <div class="card bg-light border-0 text-left mb-4">
                    <div class="card-body py-3 px-4">
                        <div class="row">
                            <div class="col-sm-6 mb-2 mb-sm-0">
                                <small class="tx-color-03 d-block">{{ __('subscription.locked.current_workspace') }}</small>
                                <strong>{{ $company->name }}</strong>
                                <span class="badge badge-secondary ml-1">{{ $company->code }}</span>
                            </div>
                            <div class="col-sm-6">
                                <small class="tx-color-03 d-block">{{ __('subscription.locked.current_plan') }}</small>
                                <strong>{{ $planName }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info text-left mb-4">
                    <h6 class="tx-13 tx-bold mb-1"><i data-feather="help-circle" class="wd-14 mr-1"></i>{{ __('subscription.locked.upgrade_title') }}</h6>
                    <p class="tx-13 mb-2">{{ __('subscription.locked.upgrade_body') }}</p>
                    <div class="tx-12">
                        @if(!empty($support['email']))
                            <span class="mr-3"><strong>{{ __('app.email') }}:</strong> {{ $support['email'] }}</span>
                        @endif
                        @if(!empty($support['whatsapp']))
                            <span><strong>WhatsApp:</strong> {{ $support['whatsapp'] }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-center" style="gap: 0.75rem;">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                        <i data-feather="arrow-left" class="wd-14 mr-1"></i> {{ __('subscription.locked.back_dashboard') }}
                    </a>
                    <a href="{{ route('plans.index') }}" class="btn btn-primary" target="_blank" rel="noopener">
                        <i data-feather="layers" class="wd-14 mr-1"></i> {{ __('subscription.locked.compare_plans') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
