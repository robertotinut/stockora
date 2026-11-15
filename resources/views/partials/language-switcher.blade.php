@php
  $switcherRoute = $route ?? (request()->is('platform', 'platform/*') ? 'platform.locale.update' : 'locale.update');
  $currentLocale = app()->getLocale();
  $redirectTarget = request()->getRequestUri();
@endphp

<form action="{{ route($switcherRoute) }}" method="POST" class="stk-lang-switcher d-inline-flex align-items-center" aria-label="{{ __('app.language_switcher_label') }}">
  @csrf
  <input type="hidden" name="redirect_to" value="{{ $redirectTarget }}">
  <div class="btn-group btn-group-sm stk-lang-group" role="group" aria-label="{{ __('app.language_switcher_label') }}">
    <button
      type="submit"
      name="locale"
      value="en"
      class="btn stk-lang-btn {{ $currentLocale === 'en' ? 'is-active' : '' }}"
      title="English"
      aria-pressed="{{ $currentLocale === 'en' ? 'true' : 'false' }}"
    >EN</button>
    <button
      type="submit"
      name="locale"
      value="id"
      class="btn stk-lang-btn {{ $currentLocale === 'id' ? 'is-active' : '' }}"
      title="Bahasa Indonesia"
      aria-pressed="{{ $currentLocale === 'id' ? 'true' : 'false' }}"
    >ID</button>
  </div>
</form>
