@props(['title', 'lede' => null])

{{-- Every page opens the same way, answering "where am I / what is this
     about" before anything else competes for attention. --}}
<header {{ $attributes->merge(['class' => 'dot-page-head']) }}>
    <div class="dot-page-head__text">
        <h1 class="dot-page-head__title">{{ $title }}</h1>
        @if ($lede)
            <p class="dot-page-head__lede">{{ $lede }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="dot-page-head__actions">{{ $actions }}</div>
    @endisset
</header>
