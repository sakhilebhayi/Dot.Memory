@props(['tone' => 'neutral', 'icon' => null, 'label'])

{{-- Status never depends on colour alone: the word is always present, and an
     icon carries the meaning again for anyone who cannot separate the hues. --}}
<span {{ $attributes->merge(['class' => 'dot-status dot-status--'.$tone]) }}>
    @if ($icon)
        <span class="material-symbols-rounded dot-status__icon" aria-hidden="true">{{ $icon }}</span>
    @endif
    {{ $label }}
</span>
