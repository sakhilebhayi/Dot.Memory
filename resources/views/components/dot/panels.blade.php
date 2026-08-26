@props(['cols' => 4])
{{--
    The shared-edge grid. A 1px gap over a ruled background renders as
    hairline dividers between panels, so regions read as parts of one
    instrument face instead of separate floating cards.
--}}
<div {{ $attributes->class(['dot-panels', 'dot-panels--'.$cols]) }}>
    {{ $slot }}
</div>
