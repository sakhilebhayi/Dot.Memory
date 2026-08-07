<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Memory — The persistence substrate for the Dot Ecosystem</title>
        <meta name="description" content="Knowledge-graph storage, vector indexes for semantic retrieval, Knowledge Pack archives, and the audit trails every Dot Ecosystem platform's governance gates write to. We store content without reading it.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --ink: #12141a;
                --ink-soft: #191c24;
                --amber: #f2a70b;
                --amber-soft: #f7c548;
                --gold: #f4c94c;
                --cold: #7b93b8;
                --paper: #eef1f5;
                --mist: #9aa4b6;
                --line: rgba(238, 241, 245, 0.1);
                --font-display: 'Space Grotesk', system-ui, sans-serif;
                --font-body: 'IBM Plex Sans', system-ui, sans-serif;
                --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            html { background: var(--ink); }
            body { font-family: var(--font-body); background: var(--ink); color: var(--paper); }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(238, 241, 245, 0.03); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            id="site-header"
            class="fixed top-0 left-0 right-0 z-50 transition-colors duration-300 border-b border-transparent"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Memory" class="h-14 sm:h-[4.5rem] w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--mist)]">
                    <a href="#tiers" class="link-underline hover:text-[var(--paper)] pb-0.5">Storage tiers</a>
                    <a href="#features" class="link-underline hover:text-[var(--paper)] pb-0.5">What it does</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12141a] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--mist)] hover:text-[var(--paper)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12141a] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Get started
                                </a>
                            @endif
                        @endauth

                        <button id="menu-toggle" class="md:hidden press p-2 -mr-2 text-[var(--paper)]" aria-label="Toggle menu" aria-expanded="false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path id="icon-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                                <path id="icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div id="mobile-menu" class="hidden md:hidden border-t border-[var(--line)] bg-[#12141a]">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#tiers" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Storage tiers</a>
                    <a href="#features" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">What it does</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-end overflow-hidden">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 80% 60% at 15% 0%, rgba(242,167,11,0.10) 0%, transparent 60%), var(--ink);"></div>

            <!-- Signature element: line-art memory card + graph nodes — echoes the logo's own SD-card icon and the "knowledge graph" it stores -->
            <svg class="hidden lg:block absolute right-[4%] bottom-[6%] h-[70%] w-auto opacity-[0.16] pointer-events-none" viewBox="0 0 260 320" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M50 20H170L210 60V300C210 305.523 205.523 310 200 310H50C44.4772 310 40 305.523 40 300V30C40 24.4772 44.4772 20 50 20Z" stroke="#eef1f5" stroke-width="3"/>
                <path d="M170 20V50C170 55.523 174.477 60 180 60H210" stroke="#eef1f5" stroke-width="3"/>
                <path d="M80 60H130V95H80V60Z" stroke="#eef1f5" stroke-width="2"/>
                <path d="M95 60V95M115 60V95" stroke="#eef1f5" stroke-width="1.5"/>
                <!-- graph nodes scattered on the card, tracing knowledge-graph edges -->
                <circle cx="75" cy="150" r="6" stroke="#f2a70b" stroke-width="2.5"/>
                <circle cx="150" cy="130" r="6" stroke="#f2a70b" stroke-width="2.5"/>
                <circle cx="120" cy="200" r="6" stroke="#f2a70b" stroke-width="2.5"/>
                <circle cx="175" cy="230" r="6" stroke="#f2a70b" stroke-width="2.5"/>
                <circle cx="70" cy="250" r="6" stroke="#f2a70b" stroke-width="2.5"/>
                <path d="M80 154L145 133M126 202L145 136M126 203L172 228M75 244L116 204M81 152L118 197" stroke="#eef1f5" stroke-width="1.25"/>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-6">
                        Persistence substrate
                    </p>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--paper)] mb-6">
                        We store the memory.<br>We don't read it.
                    </h1>

                    <p class="text-lg text-[var(--mist)] leading-relaxed max-w-xl mb-10">
                        Dot.Memory is the knowledge-graph storage, vector indexes, and Knowledge Pack archive the whole Dot Ecosystem writes to — plus the audit trail every governance gate depends on. It's infrastructure, not a knowledge consumer.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12141a] font-display font-semibold rounded-lg transition-colors">
                                Get started
                            </a>
                            <a href="#tiers" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                                See the storage tiers
                            </a>
                        </div>
                    @endguest
                </div>
            </div>

            <!-- Instrumentation strip — real SLA classes from wiki.md §5, not fabricated metrics -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#12141a]/60 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--mist)]">
                    <span>Agent context: p95 ≤ 800ms</span>
                    <span class="text-[var(--amber)]">·</span>
                    <span>Surface: p95 ≤ 1.5s</span>
                    <span class="text-[var(--amber)]">·</span>
                    <span>Audit: completeness guaranteed</span>
                    <span class="text-[var(--amber)]">·</span>
                    <span>Archive: zero-loss</span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                        Built to store without reading
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'Tiers', 'title' => 'Hot, warm, cold storage', 'body' => 'Actively referenced graph nodes live in Hot (≤50ms p95). Unreferenced knowledge ages into Warm, then Cold — promotion is demand-driven, demotion is policy-driven and logged.'],
                            ['tag' => 'Retrieval', 'title' => 'Named SLA contracts', 'body' => 'Four consumer-visible retrieval classes — agent-context, surface, audit, archive — each with an explicit, specified on-breach behavior, not an afterthought.'],
                            ['tag' => 'Indexes', 'title' => 'Graph & vector indexes', 'body' => 'A graph store plus versioned vector indexes for semantic retrieval, backing every platform\'s Knowledge Pack archive.'],
                            ['tag' => 'Forgetting', 'title' => 'Supersede, never delete', 'body' => 'Knowledge loses salience through dormancy and expiry, not deletion. Superseded versions demote toward Cold with the provenance chain intact.'],
                            ['tag' => 'Durability', 'title' => 'Verified restore tests', 'body' => 'Scheduled integrity checks and durability outcomes back every tier. Nothing skips Cold straight to deletion.'],
                            ['tag' => 'Trust', 'title' => 'Telemetry, never content', 'body' => 'We publish latency, durability, and integrity data about our own operation — never the knowledge itself. The data plane and telemetry plane are architecturally separate.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--amber)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--mist)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Storage tiers — real table from wiki.md §3, styled as the platform's own data artifact -->
        <section id="tiers" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--amber)] mb-4">Storage tiers</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                            Warm and cold aren't an afterthought
                        </h2>
                        <p class="text-[var(--mist)] leading-relaxed max-w-sm">
                            Every tier has a named latency target and a real backing store. Promotion is demand-driven; demotion is policy-driven and logged — nothing skips Cold to deletion.
                        </p>
                    </div>

                    <div class="reveal overflow-x-auto" data-reveal>
                        <table class="w-full text-left border-collapse min-w-[520px]">
                            <thead>
                                <tr class="font-mono text-[11px] tracking-[0.12em] uppercase text-[var(--mist)]">
                                    <th class="pb-4 pr-4 font-medium border-b border-[var(--line)]">Tier</th>
                                    <th class="pb-4 pr-4 font-medium border-b border-[var(--line)]">Contents</th>
                                    <th class="pb-4 pr-4 font-medium border-b border-[var(--line)]">Latency</th>
                                    <th class="pb-4 font-medium border-b border-[var(--line)]">Backing</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <tr class="row-hover transition-colors">
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-display font-semibold text-[var(--amber)]">Hot</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] text-[var(--paper)]">Referenced graph nodes/edges, verified lessons</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-mono text-[var(--mist)]">≤ 50ms p95</td>
                                    <td class="py-4 border-b border-[var(--line)] text-[var(--mist)]">Graph store + cache</td>
                                </tr>
                                <tr class="row-hover transition-colors">
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-display font-semibold text-[var(--gold)]">Warm</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] text-[var(--paper)]">Unreferenced 90 days–2yrs, provisional conclusions</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-mono text-[var(--mist)]">≤ 2s p95</td>
                                    <td class="py-4 border-b border-[var(--line)] text-[var(--mist)]">Warm store</td>
                                </tr>
                                <tr class="row-hover transition-colors">
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-display font-semibold text-[var(--cold)]">Cold</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] text-[var(--paper)]">Superseded/retracted knowledge, full ledger history</td>
                                    <td class="py-4 pr-4 border-b border-[var(--line)] font-mono text-[var(--mist)]">≤ 5min async</td>
                                    <td class="py-4 border-b border-[var(--line)] text-[var(--mist)]">Immutable archive</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 50% at 50% 100%, rgba(242,167,11,0.08) 0%, transparent 65%), var(--ink);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Give your platform a durable memory
                </h2>
                <p class="text-[var(--mist)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Knowledge-graph storage, vector indexes, and audit trails — the substrate the rest of the Dot Ecosystem already writes to.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--amber)] hover:bg-[var(--amber-soft)] text-[#12141a] font-display font-semibold rounded-lg transition-colors">
                            Get started
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Memory" class="h-11 w-auto opacity-90">
                </a>
                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--mist)]">
                    <a href="{{ route('policy.show') }}" class="hover:text-[var(--paper)] transition-colors">Privacy</a>
                    <a href="{{ route('cookies') }}" class="hover:text-[var(--paper)] transition-colors">Cookies</a>
                    <a href="{{ route('terms.show') }}" class="hover:text-[var(--paper)] transition-colors">Terms</a>
                </div>
                <p class="font-mono text-xs tracking-wide text-[var(--mist)]">
                    &copy; {{ date('Y') }} Dot.Memory. Persistence substrate for the Dot Ecosystem.
                </p>
            </div>
        </footer>

        <script>
            // Nav scroll state + mobile menu (vanilla JS — no Alpine dependency on this guest page)
            const header = document.getElementById('site-header');
            const onScroll = () => {
                header.classList.toggle('bg-[#12141a]/95', window.pageYOffset > 24);
                header.classList.toggle('backdrop-blur-md', window.pageYOffset > 24);
                header.classList.toggle('border-[var(--line)]', window.pageYOffset > 24);
                header.classList.toggle('border-transparent', window.pageYOffset <= 24);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const iconOpen = document.getElementById('icon-open');
            const iconClose = document.getElementById('icon-close');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    const isOpen = !mobileMenu.classList.contains('hidden');
                    mobileMenu.classList.toggle('hidden', isOpen);
                    iconOpen.classList.toggle('hidden', !isOpen);
                    iconClose.classList.toggle('hidden', isOpen);
                    menuToggle.setAttribute('aria-expanded', String(!isOpen));
                });
            }

            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
