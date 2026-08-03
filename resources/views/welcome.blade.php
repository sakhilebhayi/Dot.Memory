<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dot.Memory — Persistence Substrate for the Dot Ecosystem</title>
    <meta name="description" content="Knowledge-graph storage, vector indexes for semantic retrieval, Knowledge Pack archives, and the audit trails every Dot Ecosystem platform's governance gates write to.">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#0b0b10;color:#e4e4e7;font-family:'Inter',system-ui,sans-serif;font-size:15px;line-height:1.6;overflow-x:hidden}
        :root{--accent:#8b5cf6;--accent-soft:rgba(139,92,246,0.12)}
        a{color:inherit}
        h1,h2,h3{font-family:'Space Grotesk',sans-serif}
        .wrap{max-width:1180px;margin:0 auto;padding-inline:max(1.5rem,5vw)}
        .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border-radius:10px;background:var(--accent);color:#0b0b10;font-weight:700;text-decoration:none;transition:filter .15s}
        .btn-primary:hover{filter:brightness(1.12)}
        .btn-ghost{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border-radius:10px;background:transparent;border:1px solid rgba(255,255,255,0.14);color:#a1a1aa;text-decoration:none;font-weight:600;transition:all .15s}
        .btn-ghost:hover{border-color:rgba(139,92,246,0.5);color:#f4f4f5}
        .badge{display:inline-flex;align-items:center;gap:7px;padding:6px 14px;background:var(--accent-soft);border:1px solid rgba(139,92,246,0.3);border-radius:100px;font-size:12px;font-weight:600;color:#c4b5fd}
        .card{background:#141419;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.75rem;transition:border-color .2s}
        .card:hover{border-color:rgba(139,92,246,0.35)}
        .card-icon{width:44px;height:44px;border-radius:12px;background:var(--accent-soft);border:1px solid rgba(139,92,246,0.25);display:flex;align-items:center;justify-content:center;margin-bottom:1.1rem;font-size:20px}
        table.tiers{width:100%;border-collapse:collapse;font-size:13.5px}
        table.tiers th,table.tiers td{padding:12px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.08)}
        table.tiers th{color:#a1a1aa;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
        table.tiers td{color:#d4d4d8}
        @media (max-width: 640px){ table.tiers{font-size:12px} }
    </style>
</head>
<body>
    {{-- Nav --}}
    <nav style="position:sticky;top:0;z-index:50;background:rgba(11,11,16,0.85);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,0.06);">
        <div class="wrap" style="height:64px;display:flex;align-items:center;justify-content:space-between;">
            <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <img src="{{ asset('images/logo.png') }}" alt="Dot.Memory" style="height:34px;width:auto;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700;letter-spacing:-0.01em;color:#f4f4f5;">Dot.Memory</span>
            </a>
            <div style="display:flex;align-items:center;gap:12px;">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-primary" style="padding:9px 20px;font-size:14px;">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-ghost" style="padding:9px 20px;font-size:14px;">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn-primary" style="padding:9px 20px;font-size:14px;">Get started</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section style="position:relative;padding:8rem max(1.5rem,5vw) 6rem;overflow:hidden;">
        <!-- Photographic Background: real server-rack photo by Kevin Ache (@kevinache), unsplash.com/photos/a-rack-of-servers-in-a-server-room-2JJ3wBHu4_0 -->
        <div style="position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1695668548342-c0c1ad479aee?q=80&amp;w=2400&amp;auto=format&amp;fit=crop');background-size:cover;background-position:center;"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(11,11,16,0.88) 0%,rgba(11,11,16,0.93) 55%,#0b0b10 100%);"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(90deg,#0b0b10 0%,rgba(11,11,16,0.55) 45%,transparent 80%);"></div>

        <div class="wrap" style="position:relative;max-width:760px;">
            <div class="badge">
                <span>Persistence Substrate</span>
            </div>
            <h1 style="font-size:clamp(2.3rem,5.5vw,3.6rem);font-weight:700;color:#f4f4f5;line-height:1.12;letter-spacing:-0.02em;margin:1.4rem 0 1.3rem;">
                The knowledge-graph and vector store<br>the whole ecosystem writes to
            </h1>
            <p style="font-size:1.08rem;color:#a1a1aa;max-width:600px;margin-bottom:2.2rem;line-height:1.7;">
                Dot.Memory is infrastructure, not a knowledge consumer — knowledge-graph storage, vector indexes for semantic retrieval, Knowledge Pack archives, and the audit trails every Dot Ecosystem platform's governance gates write to. We store content without reading it.
            </p>
            <div style="display:flex;gap:14px;flex-wrap:wrap;">
                @guest
                    <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                    <a href="#tiers" class="btn-ghost">See storage tiers</a>
                @endguest
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-primary">Go to Dashboard</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" style="padding:1rem max(1.5rem,5vw) 5rem;">
        <div class="wrap">
            <div style="text-align:center;max-width:640px;margin:0 auto 3rem;">
                <h2 style="font-size:2rem;font-weight:700;color:#f4f4f5;letter-spacing:-0.02em;margin-bottom:0.75rem;">Built to store without reading</h2>
                <p style="color:#a1a1aa;font-size:15px;">Every other platform's data has one place it eventually lands.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem;">
                <div class="card">
                    <div class="card-icon">🗂️</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Tiered Storage</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Hot, warm, and cold tiers with explicit per-tier latency targets. Promotion is demand-driven; demotion is policy-driven and logged.</p>
                </div>
                <div class="card">
                    <div class="card-icon">📡</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Retrieval SLA Contracts</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Four named, consumer-visible contracts — agent-context, surface, audit, and archive — each with a defined on-breach behavior, not an afterthought.</p>
                </div>
                <div class="card">
                    <div class="card-icon">🕸️</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Knowledge Graph &amp; Vector Indexes</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">A graph store plus vector indexes for semantic retrieval, versioned per index, backing every platform's Knowledge Pack archive.</p>
                </div>
                <div class="card">
                    <div class="card-icon">🔒</div>
                    <h3 style="font-size:1rem;font-weight:700;color:#f4f4f5;margin-bottom:0.5rem;">Full Audit Trail</h3>
                    <p style="font-size:13.5px;color:#a1a1aa;">Verified restore tests and integrity checks back every durability outcome. Nothing skips cold storage straight to deletion.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Capabilities: storage tiers table --}}
    <section id="tiers" style="padding:1rem max(1.5rem,5vw) 6rem;">
        <div class="wrap" style="max-width:900px;">
            <div style="text-align:center;margin-bottom:2.5rem;">
                <h2 style="font-size:1.8rem;font-weight:700;color:#f4f4f5;letter-spacing:-0.02em;margin-bottom:0.6rem;">Storage tiers</h2>
                <p style="color:#a1a1aa;font-size:14.5px;">Promotion is demand-driven. Demotion is policy-driven and logged.</p>
            </div>
            <div class="card" style="overflow-x:auto;">
                <table class="tiers">
                    <thead>
                        <tr><th>Tier</th><th>Contents</th><th>Latency target</th><th>Backing</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Hot</td><td>Actively referenced graph nodes/edges, verified lessons, registries</td><td>≤ 50 ms p95</td><td>Graph store + cache</td></tr>
                        <tr><td>Warm</td><td>Unreferenced 90 days–2 years, provisional conclusions, dormant edges</td><td>≤ 2 s p95</td><td>Warm store</td></tr>
                        <tr><td>Cold</td><td>Superseded/retracted knowledge, full ledger history</td><td>≤ 5 min (async)</td><td>Immutable cold archive</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section style="padding:2rem max(1.5rem,5vw) 7rem;text-align:center;">
        <div class="wrap" style="max-width:600px;padding:3rem 2.5rem;background:#141419;border:1px solid rgba(139,92,246,0.18);border-radius:20px;">
            <h2 style="font-size:1.7rem;font-weight:700;color:#f4f4f5;letter-spacing:-0.02em;margin-bottom:0.75rem;">Give your platform a durable memory</h2>
            <p style="font-size:14px;color:#a1a1aa;margin-bottom:2rem;">Store your team's knowledge-graph data, vector indexes, and audit trail in the substrate the rest of the Dot Ecosystem already relies on.</p>
            @guest
                <a href="{{ route('register') }}" class="btn-primary">Create your free account</a>
            @else
                <a href="{{ url('/dashboard') }}" class="btn-primary">Go to your Dashboard</a>
            @endguest
        </div>
    </section>

    {{-- Footer --}}
    <footer style="border-top:1px solid rgba(255,255,255,0.06);padding:2.5rem max(1.5rem,5vw);">
        <div class="wrap" style="display:flex;flex-direction:column;align-items:center;gap:1rem;text-align:center;">
            <img src="{{ asset('images/logo.png') }}" alt="Dot.Memory" style="height:30px;width:auto;opacity:0.9;">
            <p style="font-size:12px;color:#52525b;">&copy; {{ date('Y') }} Dot.Memory · Persistence substrate for the Dot Ecosystem</p>
        </div>
    </footer>
</body>
</html>
