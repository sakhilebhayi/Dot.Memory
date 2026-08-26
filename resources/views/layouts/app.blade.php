<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dot.Memory</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', corePlugins: { preflight: false } }</script>
    <script>
        // Dark mode: this dashboard's own markup is intentionally fixed-dark (inline styles,
        // not Tailwind dark: classes), so this toggle mainly affects Jetstream-rendered pages
        // (Profile, Team Settings) which do use dark: classes. Kept for theme consistency
        // ecosystem-wide and so those subpages respect the user's light/dark preference.
        (function () {
            const stored = localStorage.getItem('dot-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        :root { --accent: #818cf8; --accent-rgb: 129,140,248; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; background:#09090b; color:#f4f4f5; font-family:'IBM Plex Sans',system-ui,sans-serif; font-size:14px; line-height:1.5; }
        .material-symbols-rounded { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; line-height:1; user-select:none; }
        [x-cloak] { display:none!important; }
        .sidebar { position:fixed; left:0; top:0; width:260px; height:100vh; background:#0d0d10; border-right:1px solid rgba(255,255,255,0.06); display:flex; flex-direction:column; z-index:40; overflow:hidden; }
        .sidebar::before { content:''; position:absolute; top:-80px; left:-80px; width:320px; height:320px; background:radial-gradient(circle, rgba(129,140,248,0.1) 0%, transparent 65%); pointer-events:none; }
        .sidebar-brand { padding:20px 18px 14px; display:flex; align-items:center; gap:11px; flex-shrink:0; }
        .brand-icon { width:36px; height:36px; border-radius:10px; background:rgba(129,140,248,0.12); border:1px solid rgba(129,140,248,0.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .brand-icon .material-symbols-rounded { font-size:18px; color:#818cf8; }
        .brand-name { font-family:'Space Grotesk',sans-serif; font-size:14.5px; font-weight:700; color:#f4f4f5; letter-spacing:-0.01em; line-height:1.2; }
        .brand-status { display:flex; align-items:center; gap:5px; margin-top:3px; }
        .live-dot { width:6px; height:6px; border-radius:50%; background:#818cf8; flex-shrink:0; animation:live-pulse 2.8s ease-in-out infinite; }
        @keyframes live-pulse { 0%,100% { opacity:1; box-shadow:0 0 0 0 rgba(129,140,248,0.45); } 60% { opacity:.6; box-shadow:0 0 0 5px rgba(129,140,248,0); } }
        .brand-subtitle { font-size:10px; font-weight:500; color:#3f3f46; text-transform:uppercase; letter-spacing:0.09em; }
        .sidebar-divider { height:1px; background:rgba(255,255,255,0.06); margin:4px 14px 8px; }
        .sidebar-nav { padding:0 10px; flex:1; overflow-y:auto; scrollbar-width:none; }
        .sidebar-nav::-webkit-scrollbar { display:none; }
        .nav-section-label { font-size:10px; font-weight:600; color:#3f3f46; text-transform:uppercase; letter-spacing:0.1em; padding:14px 8px 5px; }
        .nav-item { display:flex; align-items:center; gap:9px; padding:7.5px 10px; border-radius:8px; font-size:13px; font-weight:500; color:#71717a; text-decoration:none; transition:background .13s,color .13s,transform .13s; margin-bottom:1px; }
        .nav-item:hover { background:rgba(255,255,255,0.05); color:#d4d4d8; transform:translateX(1px); }
        .nav-item.active { background:rgba(129,140,248,0.1); color:#818cf8; font-weight:600; }
        .nav-icon { font-size:17px; width:20px; text-align:center; flex-shrink:0; }
        .sidebar-footer { padding:10px 14px 14px; border-top:1px solid rgba(255,255,255,0.06); flex-shrink:0; }
        .user-row { display:flex; align-items:center; gap:9px; padding:8px 6px; border-radius:8px; }
        .user-avatar { width:28px; height:28px; border-radius:50%; background:rgba(129,140,248,0.18); border:1px solid rgba(129,140,248,0.28); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#818cf8; flex-shrink:0; font-family:'Space Grotesk',sans-serif; }
        .user-name { font-size:12px; font-weight:600; color:#d4d4d8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .user-team { font-size:10px; color:#52525b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .topbar { position:fixed; top:0; left:260px; right:0; height:54px; background:rgba(9,9,11,0.85); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; padding:0 22px; z-index:30; gap:12px; }
        .topbar-title { font-family:'Space Grotesk',sans-serif; font-size:14px; font-weight:700; color:#f4f4f5; flex:1; }
        .topbar-team { font-size:11px; color:#52525b; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.07); border-radius:6px; padding:3px 8px; font-weight:500; white-space:nowrap; }
        .topbar-btn { width:30px; height:30px; border-radius:7px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; color:#71717a; cursor:pointer; transition:background .13s,color .13s; text-decoration:none; flex-shrink:0; }
        .topbar-btn:hover { background:rgba(255,255,255,0.09); color:#d4d4d8; }
        .topbar-btn .material-symbols-rounded { font-size:17px; }
        .content-wrap { margin-left:260px; padding-top:54px; min-height:100vh; }

        /* --- Accessibility floor -------------------------------------
           Keyboard users could not see where they were: the app defined a
           single :focus rule in total. Focus is drawn on the accent so it
           reads against the near-black ground at any nesting depth. */
        :where(a, button, input, select, textarea, [tabindex]):focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 6px;
        }

        /* Skip link: the sidebar is 12+ tabbable items before the content. */
        /* Visually hidden but announced -- used for control labels whose
           visible text would be redundant next to the control itself. */
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

        .skip-link { position:absolute; left:-9999px; top:0; z-index:100; background:var(--accent); color:#09090b; padding:10px 16px; border-radius:0 0 8px 0; font-weight:600; font-size:13px; }
        .skip-link:focus { left:0; }

        /* The brand's live dot pulsed forever with no escape hatch. Motion
           is decoration here, so it is the first thing to go. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        /* Hidden on desktop; the only control that reveals the sidebar
           once it slides off-canvas. Declared BEFORE the breakpoints:
           equal-specificity rules are won by source order, and having this
           sit after them left mobile with no way to reach navigation. */
        .sidebar-toggle { display:none; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:transparent; border:1px solid rgba(255,255,255,0.09); color:#a1a1aa; cursor:pointer; flex-shrink:0; }
        .sidebar-toggle:hover { color:#f4f4f5; background:rgba(255,255,255,0.05); }

        /* A backdrop makes "tap outside to close" discoverable. */
        .sidebar.is-open::after { content:''; position:fixed; inset:0 0 0 260px; background:rgba(0,0,0,0.5); }

        /* --- Responsive ----------------------------------------------
           The app had NO breakpoints: a fixed 260px sidebar and fixed
           multi-column grids, so every dashboard page overflowed on a
           phone. Grids collapse by intent rather than by shrinking:
           on a narrow screen the primary column comes first and the
           supporting rail follows it. */
        .dot-grid { display:grid; gap:1rem; }
        .dot-grid--metrics { grid-template-columns:repeat(4, minmax(0, 1fr)); }
        .dot-grid--split { grid-template-columns:minmax(0, 2fr) minmax(0, 1fr); gap:1.25rem; align-items:start; }
        .dot-grid--pair { grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1.25rem; align-items:start; }

        @media (max-width: 1100px) {
            .dot-grid--metrics { grid-template-columns:repeat(2, minmax(0, 1fr)); }
            .dot-grid--split, .dot-grid--pair { grid-template-columns:minmax(0, 1fr); }
        }

        @media (max-width: 860px) {
            .sidebar { transform:translateX(-100%); transition:transform .2s ease; }
            .sidebar.is-open { transform:translateX(0); }
            .topbar { left:0; padding:0 14px; }
            .content-wrap { margin-left:0; }
            .sidebar-toggle { display:inline-flex; }
        }

        @media (max-width: 560px) {
            .dot-grid--metrics { grid-template-columns:minmax(0, 1fr); }
        }


        /* Long content (tables, code) must scroll inside its own box
           rather than pushing the page sideways. */
        .dot-scroll-x { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .dot-card { background:#141416; border:1px solid rgba(255,255,255,0.07); border-radius:12px; }
        .dot-card:hover { border-color:rgba(255,255,255,0.11); }
        .metric-val { font-family:'IBM Plex Mono',monospace; font-weight:500; letter-spacing:-0.02em; }
        .dot-input { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; color:#f4f4f5; font-family:'Inter',sans-serif; font-size:13px; padding:8px 12px; width:100%; transition:border-color .15s,box-shadow .15s; outline:none; }
        .dot-input:focus { border-color:rgba(129,140,248,0.45); box-shadow:0 0 0 3px rgba(129,140,248,0.07); }
        .dot-input::placeholder { color:#3f3f46; }
        .dot-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all .14s; border:none; text-decoration:none; font-family:'Inter',sans-serif; }
        .dot-btn-primary { background:#818cf8; color:#09090b; }
        .dot-btn-primary:hover { filter:brightness(1.1); }
        .dot-btn-ghost { background:rgba(255,255,255,0.06); color:#a1a1aa; border:1px solid rgba(255,255,255,0.08); }
        .dot-btn-ghost:hover { background:rgba(255,255,255,0.1); color:#f4f4f5; }
        .dot-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:100px; font-size:11px; font-weight:600; }
        .dot-badge-accent { background:rgba(129,140,248,0.12); color:#818cf8; }
        select.dot-input option { background:#1a1a1f; }
        .dot-loading-overlay { display:flex; align-items:center; justify-content:center; padding:2.5rem 0; }
        .dot-spin { animation: dot-spin 0.9s linear infinite; }
        @keyframes dot-spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
    </style>
    @livewireStyles
    <script defer src="https://unpkg.com/alpinejs@3.10.2/dist/cdn.min.js"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
    <x-banner />

    <aside class="sidebar" id="primary-navigation" aria-label="Main navigation">
        <div class="sidebar-brand">
            <div class="brand-icon" style="background:transparent;border:none;">
                <img src="{{ asset('images/mark.png') }}" alt="Dot.Memory" style="width:36px;height:36px;border-radius:10px;object-fit:contain;">
            </div>
            <div>
                <div class="brand-name">Dot.Memory</div>
                <div class="brand-status">
                    <div class="live-dot"></div>
                    <span class="brand-subtitle">Ecosystem Memory</span>
                </div>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">home</span>
                Overview
            </a>

            <div class="nav-section-label">Knowledge</div>
            <a href="{{ route('knowledge.index') }}" class="nav-item {{ request()->routeIs('knowledge.index') || request()->routeIs('knowledge.show') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">psychology</span>
                What we know
            </a>
            <a href="{{ route('knowledge.timeline') }}" class="nav-item {{ request()->routeIs('knowledge.timeline') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">timeline</span>
                Timeline
            </a>
            <a href="{{ route('knowledge.insights') }}" class="nav-item {{ request()->routeIs('knowledge.insights') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">lightbulb</span>
                Insights
            </a>

            <div class="nav-section-label">Reliability</div>
            <a href="{{ route('reliability.index') }}" class="nav-item {{ request()->routeIs('reliability.*') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">speed</span>
                SLA Dashboard
            </a>
            <a href="{{ route('indexes.index') }}" class="nav-item {{ request()->routeIs('indexes.*') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">storage</span>
                Index Inventory
            </a>
            <a href="{{ route('durability.index') }}" class="nav-item {{ request()->routeIs('durability.*') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">verified</span>
                Durability Outcomes
            </a>
            <div class="sidebar-divider" style="margin:10px 0;"></div>
            <a href="{{ route('profile.show') }}" class="nav-item {{ request()->routeIs('profile.show') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">manage_accounts</span>
                Profile & Settings
            </a>
        </nav>

        @auth
        <div class="sidebar-footer">
            <div class="user-row">
                <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div style="min-width:0;flex:1;">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</div>
                </div>
            </div>
        </div>
        @endauth
    </aside>

    <header class="topbar">
        <button
            type="button"
            class="sidebar-toggle"
            aria-label="Show navigation"
            aria-expanded="false"
            aria-controls="primary-navigation"
            onclick="const s=document.querySelector('.sidebar');const open=s.classList.toggle('is-open');this.setAttribute('aria-expanded',open?'true':'false');this.setAttribute('aria-label',open?'Hide navigation':'Show navigation');"
        >
            <span class="material-symbols-rounded" style="font-size:20px;" aria-hidden="true">menu</span>
        </button>
        <div class="topbar-title">
            @isset($header){{ $header }}
            @else
            Dot.Memory
            @endisset
        </div>
        @auth
        <span class="topbar-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</span>
        <livewire:notification-bell />
        @endauth
        <button
            type="button"
            onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('dot-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');"
            class="topbar-btn"
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
        >
            <span class="material-symbols-rounded" aria-hidden="true">dark_mode</span>
        </button>
        <a href="{{ route('profile.show') }}" class="topbar-btn" title="Profile">
            <span class="material-symbols-rounded">account_circle</span>
        </a>
    </header>

    @livewire('navigation-menu')

    <div class="content-wrap">
        <main id="main-content" tabindex="-1">{{ $slot }}</main>
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
