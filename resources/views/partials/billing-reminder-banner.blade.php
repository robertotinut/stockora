@php
  $stage = $reminder['stage'] ?? \App\Services\RenewalReminderService::STAGE_NONE;
  $requiresAttention = (bool) ($reminder['requires_attention'] ?? false);
  $daysRemaining = $reminder['days_remaining'] ?? null;
  $expiresAt = $reminder['expires_at'] ?? null;
  $canSubmit = (bool) ($reminder['can_submit_payment'] ?? false);
  $planName = $resolvedPlanName ?? ($company?->subscriptionPlan?->name ?? ($company?->access_type ?? 'Starter'));
@endphp

@if($requiresAttention && in_array($stage, [
    \App\Services\RenewalReminderService::STAGE_REMINDER_30,
    \App\Services\RenewalReminderService::STAGE_REMINDER_7,
    \App\Services\RenewalReminderService::STAGE_REMINDER_3,
    \App\Services\RenewalReminderService::STAGE_EXPIRED,
    \App\Services\RenewalReminderService::STAGE_PENDING_ACTIVATION,
], true))
  @php
    $alertClass = match ($stage) {
        \App\Services\RenewalReminderService::STAGE_REMINDER_3,
        \App\Services\RenewalReminderService::STAGE_EXPIRED => 'alert-danger',
        \App\Services\RenewalReminderService::STAGE_REMINDER_7,
        \App\Services\RenewalReminderService::STAGE_PENDING_ACTIVATION => 'alert-warning',
        default => 'alert-info',
    };
    $iconName = $stage === \App\Services\RenewalReminderService::STAGE_EXPIRED ? 'alert-octagon' : 'clock';
    $titleText = match ($stage) {
        \App\Services\RenewalReminderService::STAGE_REMINDER_3 => __('payments.reminders.title_3', ['days' => max(0, (int) $daysRemaining)]),
        \App\Services\RenewalReminderService::STAGE_REMINDER_7 => __('payments.reminders.title_7', ['days' => max(0, (int) $daysRemaining)]),
        \App\Services\RenewalReminderService::STAGE_REMINDER_30 => __('payments.reminders.title_30', ['days' => max(0, (int) $daysRemaining)]),
        \App\Services\RenewalReminderService::STAGE_EXPIRED => __('payments.reminders.title_expired'),
        default => __('payments.reminders.title_pending_activation'),
    };
    $bodyText = match ($stage) {
        \App\Services\RenewalReminderService::STAGE_EXPIRED => __('payments.reminders.body_expired', [
            'date' => \App\Support\StockoraFormat::dateTime($expiresAt),
        ]),
        \App\Services\RenewalReminderService::STAGE_PENDING_ACTIVATION => __('payments.reminders.body_pending_activation'),
        default => __('payments.reminders.body_active_soon', [
            'plan' => $planName,
            'date' => \App\Support\StockoraFormat::dateTime($expiresAt),
        ]),
    };
  @endphp

  <div class="alert {{ $alertClass }} mg-b-20" role="alert" id="billingReminderBanner">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center" style="gap: 10px 16px;">
      <div class="d-flex align-items-start" style="gap: 10px;">
        <i data-feather="{{ $iconName }}" class="svg-18 flex-shrink-0 mg-t-2"></i>
        <div>
          <strong class="d-block mg-b-2">{{ $titleText }}</strong>
          <div class="tx-13">{{ $bodyText }}</div>
        </div>
      </div>
      <div class="d-flex flex-wrap flex-shrink-0" style="gap: 8px;">
        @if($canSubmit)
          <a href="{{ route('subscription.payments.create') }}" class="btn btn-xs {{ $stage === \App\Services\RenewalReminderService::STAGE_REMINDER_3 || $stage === \App\Services\RenewalReminderService::STAGE_EXPIRED ? 'btn-danger' : 'btn-brand-01' }}">
            {{ __('payments.reminders.cta_submit') }}
          </a>
        @endif
        @if(!request()->routeIs('subscription.index'))
          <a href="{{ route('subscription.index') }}" class="btn btn-xs btn-white">
            {{ __('payments.reminders.cta_manage') }}
          </a>
        @endif
      </div>
    </div>
  </div>
@endif
