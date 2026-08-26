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
        /* --- Design tokens --------------------------------------------
           One source of truth for the dashboard's visual language. Before
           this, ~270 inline style attributes across nine views each
           repeated their own hex values, so "change the muted grey" meant
           nine edits and a drift. Components below consume these; pages
           should not reach past them for raw colour. */
        :root {
            --accent: #818cf8;
            --accent-rgb: 129,140,248;
            --accent-quiet: rgba(129,140,248,0.12);

            /* Ground and surfaces */
            --bg: #09090b;
            --surface: #141416;
            --surface-raised: #18181b;
            --line: rgba(255,255,255,0.07);
            --line-strong: rgba(255,255,255,0.11);

            /* Text, in descending emphasis. Every step is deliberate:
               the ladder is what carries hierarchy without extra colour. */
            --text: #f4f4f5;
            --text-muted: #a1a1aa;
            --text-quiet: #71717a;
            --text-faint: #52525b;
            --text-ghost: #3f3f46;

            /* Meaning. Never used alone -- every status pairs a colour with
               an icon and a word, so nothing depends on seeing hue. */
            --ok: #22c55e;
            --warn: #fbbf24;
            --bad: #f87171;
            --info: #818cf8;

            /* Rhythm */
            --r-sm: 6px; --r: 8px; --r-lg: 12px; --r-pill: 99px;
            --gap-xs: 0.4rem; --gap-sm: 0.75rem; --gap: 1.25rem; --gap-lg: 2rem;

            /* Type scale */
            --t-display: 1.5rem;
            --t-title: 1.05rem;
            --t-section: 0.95rem;
            --t-body: 0.85rem;
            --t-sm: 0.78rem;
            --t-xs: 0.72rem;
            --t-micro: 0.66rem;
        }
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
        .user-row__text { min-width:0; flex:1; }
        .user-signout { background:none; border:0; color:var(--text-faint); cursor:pointer; display:flex; padding:4px; border-radius:var(--r-sm); }
        .user-signout:hover { color:var(--text); background:rgba(255,255,255,0.06); }
        .user-signout .material-symbols-rounded { font-size:17px; }

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
        /* --- Components ----------------------------------------------
           Everything the dashboard is built from. Pages compose these and
           do not restyle them; that is what stops nine views drifting into
           nine slightly different products. */
        .dot-card { background:var(--surface); border:1px solid var(--line); border-radius:var(--r-lg); }
        .dot-card--link { display:block; text-decoration:none; color:inherit; transition:border-color .14s ease, background .14s ease; }
        .dot-card--link:hover { border-color:var(--line-strong); background:var(--surface-raised); }
        .dot-card__head { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); margin-bottom:0.9rem; }
        .dot-card__title { font-family:'Space Grotesk',sans-serif; font-size:var(--t-section); font-weight:600; color:var(--text); margin:0; }
        .dot-card__action a, .dot-card__action button { font-size:var(--t-xs); color:var(--accent); text-decoration:none; background:none; border:0; cursor:pointer; }

        .dot-page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:var(--gap); margin-bottom:var(--gap); flex-wrap:wrap; }
        .dot-page-head__title { font-family:'Space Grotesk',sans-serif; font-size:var(--t-display); font-weight:700; color:var(--text); margin:0 0 0.2rem; letter-spacing:-0.01em; }
        .dot-page-head__lede { font-size:var(--t-sm); color:var(--text-faint); margin:0; max-width:60ch; line-height:1.6; }

        .dot-stat { padding:1.25rem 1.5rem; }
        .dot-stat__label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-faint); margin-bottom:0.7rem; }
        .dot-stat__value { font-size:2rem; font-weight:600; line-height:1.1; color:var(--text); }
        .dot-stat__value--accent { color:var(--accent); }
        .dot-stat__value--ok { color:var(--ok); }
        .dot-stat__value--warn { color:var(--warn); }
        .dot-stat__value--bad { color:var(--bad); }
        .dot-stat__context { font-size:var(--t-xs); color:var(--text-quiet); margin-top:0.35rem; line-height:1.5; }

        .dot-status { display:inline-flex; align-items:center; gap:0.25rem; font-size:var(--t-micro); font-weight:600; padding:0.15rem 0.5rem; border-radius:var(--r-pill); background:rgba(255,255,255,0.06); color:var(--text-muted); white-space:nowrap; }
        .dot-status__icon { font-size:13px; }
        .dot-status--ok { background:rgba(34,197,94,0.12); color:var(--ok); }
        .dot-status--warn { background:rgba(251,191,36,0.12); color:var(--warn); }
        .dot-status--bad { background:rgba(248,113,113,0.12); color:var(--bad); }
        .dot-status--info { background:var(--accent-quiet); color:var(--accent); }

        .dot-empty { padding:3rem 2.5rem; text-align:center; }
        .dot-empty__icon { font-size:34px; color:var(--accent); margin-bottom:0.8rem; display:inline-block; }
        .dot-empty__title { font-family:'Space Grotesk',sans-serif; font-size:var(--t-title); color:var(--text); margin:0 0 0.5rem; }
        .dot-empty__body { font-size:var(--t-body); color:var(--text-quiet); max-width:34rem; margin:0 auto; line-height:1.6; }
        .dot-empty__action { margin-top:1.2rem; }
        .dot-empty__action a { font-size:var(--t-sm); color:var(--accent); text-decoration:none; }

        /* Page furniture ---------------------------------------------- */
        .dot-pad { padding:1.5rem; }
        .dot-stack { display:flex; flex-direction:column; gap:var(--gap); }
        .dot-grid.dot-stack { display:grid; }
        .dot-note { font-size:var(--t-sm); color:var(--text-quiet); line-height:1.6; margin:0.2rem 0 0; }
        .dot-note--faint { font-size:var(--t-xs); color:var(--text-faint); margin-top:0.7rem; }

        /* The attention banner: one sentence answering "does anything need
           me?", ahead of any metric. */
        .dot-attention { display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; padding:1.25rem 1.5rem; margin-bottom:var(--gap); }
        .dot-attention__icon { font-size:22px; margin-top:0.1rem; }
        .dot-attention__text { flex:1 1 16rem; min-width:0; }
        .dot-attention__headline { font-size:1rem; font-weight:600; color:var(--text); margin:0 0 0.2rem; }
        .dot-attention__detail { font-size:var(--t-body); color:var(--text-muted); margin:0; line-height:1.6; }
        .dot-attention__link { font-size:var(--t-sm); font-weight:600; text-decoration:none; white-space:nowrap; margin-left:auto; }
        .dot-attention--ok .dot-attention__icon, .dot-attention--ok .dot-attention__link { color:var(--ok); }
        .dot-attention--warn .dot-attention__icon, .dot-attention--warn .dot-attention__link { color:var(--warn); }
        .dot-attention--bad .dot-attention__icon, .dot-attention--bad .dot-attention__link { color:var(--bad); }
        .dot-attention--info .dot-attention__icon, .dot-attention--info .dot-attention__link { color:var(--accent); }

        /* Lists of knowledge: rows that are whole links, so keyboard and
           middle-click behave the way people expect. */
        .dot-list { list-style:none; margin:0 -1.5rem -1.5rem; padding:0; }
        .dot-list__row { display:block; padding:0.9rem 1.5rem; border-top:1px solid rgba(255,255,255,0.05); text-decoration:none; transition:background .12s ease; }
        .dot-list__row:hover { background:rgba(255,255,255,0.03); }
        .dot-list__title { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; font-size:var(--t-body); font-weight:600; color:var(--text); margin-bottom:0.25rem; }
        .dot-list__body { display:block; font-size:var(--t-sm); color:var(--text-quiet); line-height:1.55; }
        .dot-list__meta { display:block; font-size:var(--t-xs); color:var(--text-faint); margin-top:0.35rem; }

        .dot-links { list-style:none; margin:0; padding:0; }
        .dot-links a { display:flex; align-items:center; gap:0.55rem; padding:0.45rem 0; font-size:var(--t-body); color:var(--text-muted); text-decoration:none; }
        .dot-links a:hover { color:var(--text); }
        .dot-links .material-symbols-rounded { font-size:17px; color:var(--text-faint); }

        .dot-entry { padding:1.1rem 1.4rem; margin-bottom:var(--gap-sm); }

        /* Filter bar: labelled controls, consistent field styling. */
        .dot-filters { display:flex; gap:var(--gap-sm); flex-wrap:wrap; align-items:center; padding:0.9rem 1rem; margin-bottom:var(--gap); }
        .dot-field { position:relative; }
        .dot-field--grow { flex:1; min-width:14rem; }
        .dot-input, .dot-select { width:100%; background:var(--bg); border:1px solid rgba(255,255,255,0.09); border-radius:var(--r); padding:0.55rem 0.8rem; font-size:var(--t-body); color:var(--text); font-family:inherit; }
        .dot-select { color:var(--text-muted); font-size:var(--t-sm); }
        .dot-input:hover, .dot-select:hover { border-color:var(--line-strong); }
        .dot-field__hint { position:absolute; right:0.7rem; top:50%; transform:translateY(-50%); font-size:var(--t-xs); color:var(--accent); }
        .dot-result-count { font-size:var(--t-xs); color:var(--text-faint); margin:0 0 var(--gap-sm); }

        /* Detail page ------------------------------------------------- */
        .dot-back { display:inline-flex; align-items:center; gap:0.35rem; font-size:var(--t-xs); color:var(--text-quiet); text-decoration:none; margin-bottom:0.8rem; }
        .dot-back:hover { color:var(--text-muted); }
        .dot-back .material-symbols-rounded { font-size:15px; }
        .dot-lede-label { font-family:'Space Grotesk',sans-serif; font-size:var(--t-section); font-weight:600; color:var(--accent); margin:0 0 0.5rem; }
        .dot-prose { font-size:var(--t-body); color:var(--text-muted); line-height:1.65; margin:0; }
        .dot-trust { font-size:var(--t-body); font-weight:600; color:var(--accent); margin:0 0 0.35rem; }
        .dot-timeline { list-style:none; margin:0; padding:0; }
        .dot-timeline li { display:flex; gap:0.8rem; padding:0.45rem 0; border-top:1px solid rgba(255,255,255,0.05); }
        .dot-timeline time { font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; }
        .dot-timeline span { font-size:var(--t-sm); color:var(--text-muted); }
        .dot-related { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); padding:0.55rem 0; border-top:1px solid rgba(255,255,255,0.05); font-size:var(--t-sm); color:var(--text-muted); text-decoration:none; }
        .dot-related:hover { color:var(--text); }
        .dot-disclosure { display:flex; align-items:center; gap:0.5rem; width:100%; padding:0; background:none; border:0; cursor:pointer; text-align:left; font-family:inherit; font-size:var(--t-body); font-weight:600; color:var(--text-quiet); }
        .dot-disclosure:hover { color:var(--text-muted); }
        .dot-disclosure .material-symbols-rounded { font-size:16px; color:var(--text-faint); transition:transform .14s ease; }
        .dot-disclosure[aria-expanded="true"] .material-symbols-rounded { transform:rotate(180deg); }
        .dot-disclosure__hint { font-size:var(--t-micro); font-weight:400; color:var(--text-ghost); }
        .dot-code { background:var(--bg); border:1px solid var(--line); border-radius:var(--r); padding:1rem; font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-muted); line-height:1.6; margin:1rem 0 0; }

        /* Timeline & insights ----------------------------------------- */
        .dot-day { margin-bottom:1.75rem; }
        .dot-day__label { font-size:var(--t-xs); font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-faint); margin:0 0 0.6rem; }
        .dot-day__card { padding:0.4rem 0; }
        .dot-events { list-style:none; margin:0; padding:0; }
        .dot-event { display:flex; align-items:flex-start; gap:0.9rem; padding:0.7rem 1.3rem; }
        .dot-event + .dot-event { border-top:1px solid rgba(255,255,255,0.05); }
        .dot-event__icon { font-size:17px; color:var(--accent); margin-top:0.05rem; }
        .dot-event__text { flex:1; font-size:var(--t-body); color:var(--text-muted); line-height:1.5; }
        .dot-event__text a { color:var(--text-muted); text-decoration:none; }
        .dot-event__text a:hover { color:var(--text); }
        .dot-event__time { font-size:var(--t-xs); color:var(--text-ghost); white-space:nowrap; }

        .dot-lede-label--spaced { margin-top:1.4rem; }
        .dot-btn { display:inline-flex; align-items:center; gap:0.4rem; padding:0.5rem 0.9rem; border-radius:var(--r); background:var(--accent-quiet); border:1px solid rgba(129,140,248,0.28); color:var(--accent); font-size:var(--t-sm); font-weight:600; text-decoration:none; cursor:pointer; font-family:inherit; }
        .dot-btn:hover { background:rgba(129,140,248,0.18); }
        .dot-insight { width:100%; text-align:left; background:none; border:0; border-top:1px solid rgba(255,255,255,0.05); cursor:pointer; font-family:inherit; }
        .dot-insight { display:block; padding:0.7rem 0; border-top:1px solid rgba(255,255,255,0.05); text-decoration:none; }
        .dot-insight__title { display:block; font-size:var(--t-body); font-weight:600; color:var(--text); margin-bottom:0.25rem; }
        .dot-insight__title--warn { color:var(--warn); }
        .dot-insight__meta { display:block; font-size:var(--t-sm); color:var(--text-quiet); }
        .dot-insight__verdict { color:var(--accent); }
        .dot-chips { display:flex; flex-wrap:wrap; gap:var(--gap-xs); margin:0.4rem 0 1.1rem; }

        /* Tables: one set of rules, so the Reliability pages read as the
           same product as the Knowledge pages. */
        .dot-card__title--spaced { margin-bottom:1.25rem; font-size:0.875rem; font-weight:700; }
        .dot-spinner-icon { font-size:22px; color:var(--accent); }

        .dot-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .dot-table { width:100%; border-collapse:collapse; font-size:13px; }
        .dot-table thead tr { text-align:left; color:var(--text-quiet); font-size:11px; text-transform:uppercase; letter-spacing:0.06em; }
        .dot-table th, .dot-table td { padding:8px 10px; }
        .dot-table tbody tr { border-top:1px solid var(--line); }
        .dot-table tbody tr:hover { background:rgba(255,255,255,0.02); }
        .dot-table__num { font-family:'IBM Plex Mono',monospace; color:var(--text-muted); }

        /* Modal ------------------------------------------------------- */
        .dot-modal { position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(0,0,0,0.6); backdrop-filter:blur(2px); }
        .dot-modal__panel { width:100%; max-height:85vh; overflow-y:auto; background:var(--surface); border:1px solid var(--line-strong); border-radius:var(--r-lg); animation:dot-modal-in .14s ease-out; }
        @keyframes dot-modal-in { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
        .dot-modal__head { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); padding:1.1rem 1.4rem; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--surface); }
        .dot-modal__title { font-family:'Space Grotesk',sans-serif; font-size:var(--t-title); font-weight:600; color:var(--text); margin:0; }
        .dot-modal__close { background:none; border:0; color:var(--text-quiet); cursor:pointer; display:flex; padding:0.2rem; border-radius:var(--r-sm); }
        .dot-modal__close:hover { color:var(--text); background:rgba(255,255,255,0.06); }
        .dot-modal__body { padding:1.4rem; }
        .dot-modal__foot { padding:1rem 1.4rem; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:var(--gap-sm); }

        /* Toasts ------------------------------------------------------ */
        .dot-toasts { position:fixed; right:1rem; bottom:1rem; z-index:70; display:flex; flex-direction:column; gap:0.5rem; width:min(24rem, calc(100vw - 2rem)); }
        .dot-toast { display:flex; align-items:flex-start; gap:0.6rem; padding:0.8rem 0.9rem; border-radius:var(--r); background:var(--surface-raised); border:1px solid var(--line-strong); box-shadow:0 8px 24px rgba(0,0,0,0.4); animation:dot-toast-in .16s ease-out; }
        @keyframes dot-toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        .dot-toast__icon { font-size:18px; flex-shrink:0; margin-top:0.05rem; }
        .dot-toast__text { flex:1; min-width:0; font-size:var(--t-sm); color:var(--text-muted); line-height:1.5; }
        .dot-toast__title { display:block; color:var(--text); font-weight:600; margin-bottom:0.1rem; }
        .dot-toast__close { background:none; border:0; color:var(--text-ghost); cursor:pointer; display:flex; padding:0; }
        .dot-toast__close:hover { color:var(--text-muted); }
        .dot-toast__close .material-symbols-rounded { font-size:16px; }
        .dot-toast--success .dot-toast__icon { color:var(--ok); }
        .dot-toast--error .dot-toast__icon { color:var(--bad); }
        .dot-toast--warning .dot-toast__icon { color:var(--warn); }
        .dot-toast--info .dot-toast__icon { color:var(--info); }

        @media (max-width: 560px) {
            .dot-toasts { right:0.5rem; left:0.5rem; bottom:0.5rem; width:auto; }
        }
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
    {{-- Alpine comes from Livewire, which bundles it. Loading it from a CDN
         as well ran TWO Alpine instances: components initialised twice and
         $wire went undefined, which is why the notification bell had been
         throwing "$wire is not defined" on every page. --}}
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
                <div class="user-avatar" aria-hidden="true">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div class="user-row__text">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-team">{{ Auth::user()->currentTeam->name ?? 'Personal' }}</div>
                </div>
                {{-- Sign out lived only in Jetstream's nav bar, which was a
                     whole second navigation competing with this sidebar for
                     the same destinations. Moving it here let that bar go. --}}
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <button type="submit" class="user-signout" title="Sign out" aria-label="Sign out">
                        <span class="material-symbols-rounded" aria-hidden="true">logout</span>
                    </button>
                </form>
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

    <div class="content-wrap">
        <main id="main-content" tabindex="-1">{{ $slot }}</main>
    </div>

    {{-- One toast host for the whole app, outside the scrolling content so
         a notification is never clipped by an overflow container. --}}
    <x-dot.toasts />

    @stack('modals')
    @livewireScripts
</body>
</html>
