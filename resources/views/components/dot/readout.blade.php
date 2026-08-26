@props([
    'label',
    'value',
    'unit' => null,
    'context' => null,
    'tone' => null,
    'pad' => 3,
])
{{--
    An instrument reading, not a headline. The figure is monospace and
    zero-padded to a fixed width so a column of readouts stays aligned and a
    count that gains a digit does not shove the layout sideways. The padding
    zeros render dimmed: the eye lands on the significant digits while the
    gauge keeps its full width.

    Screen readers get the plain number -- the padding is a visual device and
    reading "zero zero seven" aloud would be worse, not better.
--}}
@php
    $isNumeric = is_int($value) || (is_string($value) && ctype_digit($value));
    $digits = $isNumeric ? (string) (int) $value : null;
    $padding = $isNumeric ? str_repeat('0', max(0, $pad - strlen($digits))) : '';
@endphp
<div {{ $attributes->class(['dot-readout']) }}>
    <div class="dot-readout__label">{{ $label }}</div>
    <div class="dot-readout__value @if($tone) dot-readout__value--{{ $tone }} @endif">
        @if ($isNumeric)
            <span aria-hidden="true">@if($padding !== '')<span class="dot-readout__pad">{{ $padding }}</span>@endif{{ $digits }}</span>
            <span class="sr-only">{{ $digits }}</span>
        @else
            {{ $value }}
        @endif
        @if ($unit)<span class="dot-readout__unit">{{ $unit }}</span>@endif
    </div>
    @if ($context)
        <div class="dot-readout__context">{{ $context }}</div>
    @endif
</div>
