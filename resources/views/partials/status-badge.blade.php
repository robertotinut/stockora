@php
  $rawStatus = strtoupper((string) ($status ?? 'UNKNOWN'));
  $badgeClass = match ($rawStatus) {
      'RECEIVED', 'POSTED', 'COMPLETED', 'HISTORICALLY_VERIFIED', 'VERIFIED_ACQUISITION', 'ACTIVE', 'ACCEPTED', 'PAID', 'IN', 'TRANSFER_IN' => 'badge-success',
      'ORDERED', 'PARTIAL', 'BASELINE_INITIALIZATION', 'ESTIMATED_LEGACY_BASELINE', 'DEFAULT', 'ADJUSTMENT' => 'badge-primary',
      'CHAIN_CONSISTENT', 'MIXED_BASELINE_AND_ACQUISITION', 'REVERSAL_IN', 'TRANSFER_OUT' => 'badge-info',
      'PENDING', 'UNPAID', 'OUT', 'ISSUE' => 'badge-warning',
      'CANCELLED', 'REVERSED', 'CHAIN_BREAK', 'UNVERIFIED_QUARANTINED', 'NEEDS_REVIEW', 'INACTIVE', 'REVOKED', 'REVERSAL', 'REVERSAL_OUT' => 'badge-danger',
      'DRAFT', 'EXPIRED' => 'badge-secondary',
      default => 'badge-light',
  };
  $displayLabel = $label ?? $rawStatus;
@endphp
<span class="badge {{ $badgeClass }}">{{ $displayLabel }}</span>
