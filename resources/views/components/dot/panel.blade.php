@props([
    'title' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'flush' => false,
])
{{--
    One region of the instrument face. Panels are meant to sit inside
    <x-dot.panels>, where they share edges with their neighbours rather than
    floating in a gutter.
--}}
<section {{ $attributes->class(['dot-panel', 'dot-panel--flush' => $title !== null || $flush]) }}>
    @if ($title)
        <div class="dot-panel__head">
            <h2 class="dot-panel__title">{{ $title }}</h2>
            @if ($actionHref && $actionLabel)
                <a href="{{ $actionHref }}" class="dot-panel__action">{{ $actionLabel }}</a>
            @endif
        </div>
    @endif

    @if ($title && ! $flush)
        <div class="dot-panel__body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
