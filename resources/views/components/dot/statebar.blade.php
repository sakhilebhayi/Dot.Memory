@props([
    'tone' => 'idle',
    'state',
    'detail' => null,
    'meta' => null,
    'linkLabel' => null,
    'linkHref' => null,
])
{{--
    The instrument's pulse: one lamp, the state in words, and what it covers.
    Answers "does anything need me?" before a single figure is read. The lamp
    never carries the meaning alone -- the state is always also written out.
--}}
<div {{ $attributes->class(['dot-statebar', 'dot-statebar--'.$tone]) }} role="status">
    <span class="dot-statebar__bay">
        <span class="dot-statebar__lamp" aria-hidden="true"></span>
    </span>
    <span class="dot-statebar__state">{{ $state }}</span>
    @if ($detail)
        <span class="dot-statebar__detail">{{ $detail }}</span>
    @endif
    @if ($meta)
        <span class="dot-statebar__meta">{{ $meta }}</span>
    @endif
    @if ($linkHref && $linkLabel)
        <a href="{{ $linkHref }}" class="dot-statebar__link">{{ $linkLabel }}</a>
    @endif
</div>
