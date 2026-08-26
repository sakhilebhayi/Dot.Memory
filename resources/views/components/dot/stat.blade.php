@props(['label', 'value', 'tone' => 'neutral', 'context' => null])

{{-- A number earns its place only with a label that says what it means and,
     where we have one, a line of context. A bare figure makes the reader do
     the interpreting. --}}
<div {{ $attributes->merge(['class' => 'dot-card dot-stat']) }}>
    <div class="dot-stat__label">{{ $label }}</div>
    <div class="dot-stat__value dot-stat__value--{{ $tone }}">{{ $value }}</div>
    @if ($context)
        <div class="dot-stat__context">{{ $context }}</div>
    @endif
</div>
