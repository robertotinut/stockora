@php
  $statusValue = strtoupper((string) ($status ?? 'PENDING_ACTIVATION'));
  $cssClass = match ($statusValue) {
      'ACTIVE' => 'stk-status-active',
      'SUSPENDED' => 'stk-status-suspended',
      'EXPIRED' => 'stk-status-expired',
      'CANCELLED' => 'stk-status-cancelled',
      default => 'stk-status-pending',
  };
  $transKey = "platform.account_statuses.{$statusValue}";
  $label = \Illuminate\Support\Facades\Lang::has($transKey)
      ? __($transKey)
      : (\App\Models\Company::ACCOUNT_STATUSES[$statusValue]['label'] ?? str_replace('_', ' ', $statusValue));
@endphp
<span class="stk-status-pill {{ $cssClass }}">{{ $label }}</span>
