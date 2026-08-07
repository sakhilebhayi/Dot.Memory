<div class="relative min-h-screen flex flex-col justify-center items-center px-5 py-12 overflow-hidden" style="background: radial-gradient(ellipse 80% 60% at 15% 0%, rgba(242,167,11,0.10) 0%, transparent 60%), var(--ink);">
    {{-- Same signature element as welcome.blade.php's hero — line-art memory card + graph nodes,
    echoing the logo's SD-card icon and the knowledge graph it stores. --}}
    <svg class="hidden lg:block absolute right-[6%] bottom-[8%] h-[55%] w-auto opacity-[0.12] pointer-events-none" viewBox="0 0 260 320" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M50 20H170L210 60V300C210 305.523 205.523 310 200 310H50C44.4772 310 40 305.523 40 300V30C40 24.4772 44.4772 20 50 20Z" stroke="#eef1f5" stroke-width="3"/>
        <path d="M170 20V50C170 55.523 174.477 60 180 60H210" stroke="#eef1f5" stroke-width="3"/>
        <path d="M80 60H130V95H80V60Z" stroke="#eef1f5" stroke-width="2"/>
        <path d="M95 60V95M115 60V95" stroke="#eef1f5" stroke-width="1.5"/>
        <circle cx="75" cy="150" r="6" stroke="#f2a70b" stroke-width="2.5"/>
        <circle cx="150" cy="130" r="6" stroke="#f2a70b" stroke-width="2.5"/>
        <circle cx="120" cy="200" r="6" stroke="#f2a70b" stroke-width="2.5"/>
        <circle cx="175" cy="230" r="6" stroke="#f2a70b" stroke-width="2.5"/>
        <circle cx="70" cy="250" r="6" stroke="#f2a70b" stroke-width="2.5"/>
        <path d="M80 154L145 133M126 202L145 136M126 203L172 228M75 244L116 204M81 152L118 197" stroke="#eef1f5" stroke-width="1.25"/>
    </svg>

    <div class="relative z-10 mb-8">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md px-6 py-8 sm:px-8 bg-[var(--ink-soft)] border border-[var(--line)] rounded-2xl shadow-xl">
        {{ $slot }}
    </div>
</div>
