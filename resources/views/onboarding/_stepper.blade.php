@php
  $currentStep = (int) ($step ?? 1);
  $steps = [
    1 => __('auth.stepper.account'),
    2 => __('auth.stepper.company'),
    3 => __('auth.stepper.ready'),
  ];
@endphp

<div class="stk-stepper" role="progressbar" aria-valuenow="{{ $currentStep }}" aria-valuemin="1" aria-valuemax="3" aria-label="{{ __('auth.stepper_aria') }}">
  @foreach($steps as $num => $label)
    @php
      $stateClass = $num < $currentStep ? 'is-done' : ($num === $currentStep ? 'is-active' : '');
    @endphp
    <div class="stk-step {{ $stateClass }}">
      <span class="stk-step-circle">
        @if($num < $currentStep)
          <i data-feather="check" class="svg-12"></i>
        @else
          {{ $num }}
        @endif
      </span>
      <span class="stk-step-label">{{ $label }}</span>
    </div>
    @if(!$loop->last)
      <div class="stk-step-line {{ $num < $currentStep ? 'is-done' : '' }}"></div>
    @endif
  @endforeach
</div>
