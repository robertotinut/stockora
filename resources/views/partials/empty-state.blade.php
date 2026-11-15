@php
  $title = $title ?? __('app.empty_default_title');
  $description = $description ?? __('app.empty_default_desc');
  $icon = $icon ?? 'inbox';
  $actionUrl = $actionUrl ?? null;
  $actionLabel = $actionLabel ?? null;
@endphp

<div class="stk-empty-state">
  <div class="stk-empty-icon">
    <i data-feather="{{ $icon }}" class="svg-20"></i>
  </div>
  <div class="stk-empty-title">{{ $title }}</div>
  <p class="stk-empty-desc">{{ $description }}</p>
  @if($actionUrl && $actionLabel)
    <a href="{{ $actionUrl }}" class="btn btn-sm btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ $actionLabel }}
    </a>
  @endif
</div>
