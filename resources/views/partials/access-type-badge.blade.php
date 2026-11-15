@php
  $tierValue = strtoupper((string) ($accessType ?? 'FREE'));
  $cssClass = match ($tierValue) {
      'STARTER' => 'stk-tier-starter',
      'PROFESSIONAL' => 'stk-tier-professional',
      'ENTERPRISE' => 'stk-tier-enterprise',
      default => 'stk-tier-free',
  };
  $transKey = "platform.access_types.{$tierValue}";
  $label = \Illuminate\Support\Facades\Lang::has($transKey)
      ? __($transKey)
      : (\App\Models\Company::ACCESS_TYPES[$tierValue]['label'] ?? $tierValue);
@endphp
<span class="stk-tier-pill {{ $cssClass }}">{{ $label }}</span>
