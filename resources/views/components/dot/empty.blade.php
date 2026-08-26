@props(['title', 'icon' => 'psychology'])

{{-- An empty screen is an invitation, not a dead end: what is missing, why,
     and what to do about it. --}}
<div {{ $attributes->merge(['class' => 'dot-card dot-empty']) }}>
    <span class="material-symbols-rounded dot-empty__icon" aria-hidden="true">{{ $icon }}</span>
    <h2 class="dot-empty__title">{{ $title }}</h2>
    <div class="dot-empty__body">{{ $slot }}</div>
    @isset($action)
        <div class="dot-empty__action">{{ $action }}</div>
    @endisset
</div>
