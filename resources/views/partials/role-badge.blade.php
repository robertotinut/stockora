@php
  $roleKey = strtolower((string) ($role ?? 'viewer'));
  $badgeClass = match ($roleKey) {
      'owner' => 'stk-badge-owner',
      'admin' => 'stk-badge-admin',
      'manager' => 'stk-badge-manager',
      'staff' => 'stk-badge-staff',
      'viewer' => 'stk-badge-viewer',
      default => 'badge-secondary',
  };
  $transKey = "permissions.roles.{$roleKey}.label";
  $defaultLabel = \Illuminate\Support\Facades\Lang::has($transKey)
      ? strtoupper(__($transKey))
      : strtoupper($roleKey);
  $label = $label ?? $defaultLabel;
@endphp
<span class="badge {{ $badgeClass }}">{{ $label }}</span>
