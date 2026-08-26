@props(['title' => null, 'as' => 'div', 'href' => null])

{{-- The one surface. A card that is a link stays a link (not a div with a
     click handler), so it keeps keyboard focus and middle-click for free. --}}
@php($tag = $href ? 'a' : $as)
<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'dot-card'.($href ? ' dot-card--link' : '')]) }}
>
    @if ($title || isset($action))
        <div class="dot-card__head">
            @if ($title)<h2 class="dot-card__title">{{ $title }}</h2>@endif
            @isset($action)<div class="dot-card__action">{{ $action }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
</{{ $tag }}>
