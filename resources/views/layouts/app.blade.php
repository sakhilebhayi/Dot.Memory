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
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', corePlugins: { preflight: false } }</script>
    <script>
        // `.dark` means NIGHT, and night is the default: this is an operations
        // console, and unlike a marketing page it is read in a dim room far more
        // often than a bright one. A stored preference always wins, so the choice
        // is one click away and it sticks. Applied before first paint to avoid a
        // white flash on a console someone is watching at 3am.
        (function () {
            if (localStorage.getItem('dot-theme') !== 'light') {
                document.documentElement.classList.add('dark');
            }
        })();

        function dotToggleTheme(button) {
            const night = document.documentElement.classList.toggle('dark');
            localStorage.setItem('dot-theme', night ? 'dark' : 'light');
            const label = night ? 'Switch to day' : 'Switch to night';
            button.querySelector('[data-theme-label]').textContent = label;
            button.title = label;
        }
        window.dotToggleTheme = dotToggleTheme;
    </script>
    <style>
        /* --- Design tokens --------------------------------------------
           One source of truth for the dashboard's visual language. Before
           this, ~270 inline style attributes across nine views each
           repeated their own hex values, so "change the muted grey" meant
           nine edits and a drift. Components below consume these; pages
           should not reach past them for raw colour. */
        /* --- Palette: one instrument, two lighting conditions ---------
           Night is the default because this is an operations console and
           that is the condition it is read in most. Day is not an
           inversion of it: the signal DARKENS to hold contrast on a light
           ground, and the glow is dropped entirely, because glow is a
           night affordance -- on white it reads as a smudge.

           `.dark` on <html> means NIGHT. Keeping that class name (rather
           than a truer `.night`) is deliberate: Jetstream's own pages use
           Tailwind `dark:` variants, and they are correct to follow the
           same switch. */
        :root {
            /* DAY */
            --ground: #f7f8f9;
            --panel: #ffffff;
            --panel-raised: #f1f3f6;
            --panel-sunken: #eceef2;
            --rule: rgba(15,20,28,0.11);
            --rule-strong: rgba(15,20,28,0.2);

            --text: #10151c;
            --text-muted: #46505c;
            --text-quiet: #525c68;   /* 6.8:1 on panel, 5.9:1 on sunken */
            --text-faint: #5f6a77;   /* 5.5:1 on panel, 4.7:1 on sunken */
            --text-ghost: #a3acb6;

            /* Signal amber. The logo already carries this colour, and it
               is what every real control room uses to mean "look here" --
               brand and domain agreeing is rare enough to take. Darkened
               here so it clears 4.5:1 on white; the night value would not. */
            --signal: #8a5c07;       /* 5.8:1 on panel, 5.0:1 on sunken */
            --signal-rgb: 138,92,7;
            --signal-quiet: rgba(138,92,7,0.1);

            --ok: #17794a;
            --warn: #8a5c07;
            --bad: #b3332f;
            --info: #1f5f96;
            --ok-quiet: rgba(23,121,74,0.1);
            --warn-quiet: rgba(138,92,7,0.1);
            --bad-quiet: rgba(179,51,47,0.1);

            --lamp-glow: none;

            /* Backwards compatibility: pages written against the previous
               token names keep working and inherit the new palette rather
               than being left on the old one. */
            --bg: var(--ground);
            --surface: var(--panel);
            --surface-raised: var(--panel-raised);
            --line: var(--rule);
            --line-strong: var(--rule-strong);
            --accent: var(--signal);
            --accent-rgb: var(--signal-rgb);
            --accent-quiet: var(--signal-quiet);

            /* Rhythm. Radii shrink to near-nothing: an instrument face is
               cut, not moulded, and the 12px pill corners were doing more
               to make this look like every other dashboard than any
               single colour was. */
            --r-sm: 2px; --r: 3px; --r-lg: 4px; --r-pill: 99px;
            --gap-xs: 0.4rem; --gap-sm: 0.75rem; --gap: 1.25rem; --gap-lg: 2rem;

            --t-display: 1.5rem;
            --t-title: 1.05rem;
            --t-section: 0.95rem;
            --t-body: 0.85rem;
            --t-sm: 0.78rem;
            --t-xs: 0.72rem;
            --t-micro: 0.66rem;
        }

        :root.dark {
            /* NIGHT */
            --ground: #0b0e12;
            --panel: #11151b;
            --panel-raised: #161b23;
            --panel-sunken: #080b0e;
            --rule: rgba(255,255,255,0.075);
            --rule-strong: var(--rule-strong);

            --text: #e6eaf0;
            --text-muted: #98a2b0;
            --text-quiet: #8b95a3;   /* 6.0:1 on panel, 6.5:1 on sunken */
            --text-faint: #78828f;   /* 4.7:1 on panel, 5.1:1 on sunken */
            --text-ghost: #363e48;

            --signal: #f2a70b;
            --signal-rgb: 242,167,11;
            --signal-quiet: rgba(242,167,11,0.13);

            --ok: #3fbf7f;
            --warn: #f2a70b;
            --bad: #ef6461;
            --info: #5b9dd9;
            --ok-quiet: rgba(63,191,127,0.13);
            --warn-quiet: rgba(242,167,11,0.13);
            --bad-quiet: rgba(239,100,97,0.13);

            --lamp-glow: 0 0 10px -1px currentColor;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; background:var(--ground); color:var(--text); font-family:'IBM Plex Sans',system-ui,sans-serif; font-size:14px; line-height:1.5; }
        .material-symbols-rounded { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; line-height:1; user-select:none; }
        [x-cloak] { display:none!important; }
        .sidebar { position:fixed; left:0; top:0; width:240px; height:100vh; background:var(--panel-sunken); border-right:1px solid var(--rule); display:flex; flex-direction:column; z-index:40; overflow:hidden; }
        .sidebar-brand { padding:20px 18px 14px; display:flex; align-items:center; gap:11px; flex-shrink:0; }
        .brand-icon { width:32px; height:32px; border-radius:var(--r); background:var(--signal-quiet); border:1px solid rgba(var(--signal-rgb),0.3); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .brand-icon .material-symbols-rounded { font-size:17px; color:var(--signal); }
        .brand-icon--mark { background:transparent; border:0; }
        .brand-mark { width:32px; height:32px; object-fit:contain; }
        .brand-name { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:14px; font-weight:700; color:var(--text); letter-spacing:0.01em; line-height:1.2; }
        .brand-status { display:flex; align-items:center; gap:5px; margin-top:3px; }
        .live-dot { width:5px; height:5px; border-radius:50%; background:var(--ok); color:var(--ok); box-shadow:var(--lamp-glow); flex-shrink:0; }
        .brand-subtitle { font-size:10px; font-weight:500; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.09em; }
        .sidebar-divider { height:1px; background:var(--rule); margin:4px 0 8px; }
        .sidebar-nav { padding:0; flex:1; overflow-y:auto; scrollbar-width:none; }
        .sidebar-nav::-webkit-scrollbar { display:none; }
        .nav-section-label { font-size:10px; font-weight:600; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.12em; padding:14px 10px 5px; }
        .nav-item { position:relative; display:flex; align-items:center; gap:9px; padding:7px 10px 7px 13px; font-size:13px; font-weight:500; color:var(--text-quiet); text-decoration:none; transition:background .13s,color .13s; }
        .nav-item:hover { background:var(--panel-raised); color:var(--text); }
        .nav-item.active { background:var(--panel-raised); color:var(--text); font-weight:600; }
        .nav-item.active::before { content:''; position:absolute; left:0; top:0; bottom:0; width:2px; background:var(--signal); }
        .nav-icon { font-size:17px; width:20px; text-align:center; flex-shrink:0; }
        .sidebar-footer { padding:10px 14px 14px; border-top:1px solid var(--rule); flex-shrink:0; }
        .user-row { display:flex; align-items:center; gap:9px; padding:8px 6px; border-radius:8px; }
        .user-avatar { width:26px; height:26px; border-radius:var(--r); background:var(--panel-raised); border:1px solid var(--rule-strong); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:var(--text-muted); flex-shrink:0; font-family:'IBM Plex Mono',monospace; }
        .user-name { font-size:12px; font-weight:600; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .user-team { font-size:10px; color:var(--text-faint); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .user-row__text { min-width:0; flex:1; }
        .user-signout { background:none; border:0; color:var(--text-faint); cursor:pointer; display:flex; padding:4px; border-radius:var(--r-sm); }
        .user-signout:hover { color:var(--text); background:var(--panel-raised); }
        .user-signout .material-symbols-rounded { font-size:17px; }

        .topbar { position:fixed; top:0; left:240px; right:0; height:50px; background:var(--panel-sunken); border-bottom:1px solid var(--rule); display:flex; align-items:center; padding:0 20px; z-index:30; gap:12px; }
        .topbar-title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:13px; font-weight:700; color:var(--text); flex:1; letter-spacing:0.01em; }
        .topbar-team { font-family:'IBM Plex Mono',monospace; font-size:11px; color:var(--text-quiet); border:1px solid var(--rule); border-radius:var(--r-sm); padding:3px 8px; white-space:nowrap; }
        .topbar-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--rule); background:transparent; display:flex; align-items:center; justify-content:center; color:var(--text-quiet); cursor:pointer; transition:background .13s,color .13s; text-decoration:none; flex-shrink:0; }
        .topbar-btn:hover { background:var(--panel-raised); color:var(--text); }
        .topbar-btn .material-symbols-rounded { font-size:17px; }
        .theme-icon-night { display:none; }
        :root.dark .theme-icon-night { display:inline; }
        :root.dark .theme-icon-day { display:none; }
        .content-wrap { margin-left:240px; padding-top:50px; min-height:100vh; }

        /* --- Accessibility floor -------------------------------------
           Keyboard users could not see where they were: the app defined a
           single :focus rule in total. Focus is drawn on the accent so it
           reads against the near-black ground at any nesting depth. */
        :where(a, button, input, select, textarea, [tabindex]):focus-visible {
            outline: 2px solid var(--signal);
            outline-offset: 2px;
            border-radius: var(--r-sm);
        }

        /* Skip link: the sidebar is 12+ tabbable items before the content. */
        /* Visually hidden but announced -- used for control labels whose
           visible text would be redundant next to the control itself. */
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

        .skip-link { position:absolute; left:-9999px; top:0; z-index:100; background:var(--signal); color:#0b0e12; padding:10px 16px; border-radius:0 0 var(--r) 0; font-weight:600; font-size:13px; }
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
        .sidebar-toggle { display:none; align-items:center; justify-content:center; width:32px; height:32px; border-radius:var(--r-sm); background:transparent; border:1px solid var(--rule); color:var(--text-muted); cursor:pointer; flex-shrink:0; }
        .sidebar-toggle:hover { color:var(--text); background:var(--panel-raised); }

        /* A backdrop makes "tap outside to close" discoverable. */
        .sidebar.is-open::after { content:''; position:fixed; inset:0 0 0 240px; background:rgba(0,0,0,0.5); }

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

        @media (max-width: 620px) {
            /* On a phone the table must fit rather than scroll: reading a
               pattern's story should never require dragging it sideways.
               "Last seen" goes because the ledger is already newest-first,
               and the team pill goes because the sidebar footer names the
               team anyway -- neither is worth a clipped control. */
            .dot-ledger--patterns th:nth-child(7), .dot-ledger--patterns td:nth-child(7) { display:none; }
            .dot-ledger--patterns td:nth-child(2), .dot-ledger--patterns th:nth-child(2) { min-width:7rem; }
            .dot-ledger th, .dot-ledger td { padding-left:0.6rem; padding-right:0.6rem; }
            .topbar-team { display:none; }
            .topbar { padding:0 10px; gap:8px; }
        }

        @media (max-width: 560px) {
            .dot-grid--metrics { grid-template-columns:minmax(0, 1fr); }
        }


        /* Long content (tables, code) must scroll inside its own box
           rather than pushing the page sideways. */
        .dot-scroll-x { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        /* --- Control room --------------------------------------------
           The structural idea. Previously every block was a rounded card
           floating in a gutter, which is the shape of every generated
           dashboard. Here regions SHARE EDGES and are divided by hairlines,
           the way panels on an instrument do. It is one 1px grid gap over a
           ruled background -- cheap to render, and it does more to break the
           template read than any colour choice. */
        .dot-panels { display:grid; gap:1px; background:var(--rule); border:1px solid var(--rule); border-radius:var(--r-lg); overflow:hidden; }
        .dot-panels > * { background:var(--panel); min-width:0; }
        .dot-panels--4 { grid-template-columns:repeat(4, minmax(0,1fr)); }
        .dot-panels--3 { grid-template-columns:repeat(3, minmax(0,1fr)); }
        .dot-panels--2 { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .dot-panels--split { grid-template-columns:minmax(0,2fr) minmax(0,1fr); }

        /* A panel standing on its own draws its own edge; inside a panel
           grid the grid owns every edge, so the panel drops its border and
           shares one with its neighbours instead. */
/* Panel breakpoints live HERE, after the base grids they override.
           Declared with the other responsive rules they sat above those
           definitions and lost on source order, leaving four readouts
           jammed across a phone. */
        @media (max-width: 1100px) {
            .dot-panels--4 { grid-template-columns:repeat(2, minmax(0,1fr)); }
            .dot-panels--3, .dot-panels--split { grid-template-columns:minmax(0,1fr); }
        }
        @media (max-width: 620px) {
            .dot-panels--4, .dot-panels--2 { grid-template-columns:minmax(0,1fr); }
            .dot-readout__value { font-size:1.6rem; }
            .dot-readout__label { min-height:0; }
        }

                .dot-panel { padding:1.15rem 1.25rem; display:flex; flex-direction:column; background:var(--panel); border:1px solid var(--rule); border-radius:var(--r-lg); }
        .dot-panels > .dot-panel { border:0; border-radius:0; }
        .dot-panel--flush { padding:0; }
        .dot-panel__head { display:flex; align-items:baseline; justify-content:space-between; gap:var(--gap-sm); padding:0.85rem 1.25rem; border-bottom:1px solid var(--rule); }
        .dot-panel__title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-sm); font-weight:600; color:var(--text); margin:0; letter-spacing:0.02em; }
        .dot-panel__action { font-size:var(--t-xs); color:var(--signal); text-decoration:none; white-space:nowrap; }
        .dot-panel__action:hover { text-decoration:underline; }
        .dot-panel__body { padding:1.15rem 1.25rem; }

        /* --- Readouts -------------------------------------------------
           Figures are instrument readings, not headlines: monospace, tabular,
           zero-padded so a column of them stays aligned and a number that
           grows a digit does not shove the layout. The padding zeros are
           dimmed rather than hidden -- the eye lands on the significant
           digits, but the gauge keeps its full width. */
        .dot-readout { display:flex; flex-direction:column; gap:0.5rem; }
        .dot-readout__label { min-height:2.2em; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.11em; color:var(--text-faint); }
        .dot-readout__value { font-family:'IBM Plex Mono',monospace; font-variant-numeric:tabular-nums; font-size:1.9rem; font-weight:500; line-height:1; color:var(--text); letter-spacing:-0.02em; }
        .dot-readout__pad { color:var(--text-ghost); }
        .dot-readout__unit { font-size:0.8rem; color:var(--text-quiet); margin-left:0.2rem; letter-spacing:0; }
        .dot-readout__value--signal { color:var(--signal); }
        .dot-readout__value--ok { color:var(--ok); }
        .dot-readout__value--warn { color:var(--warn); }
        .dot-readout__value--bad { color:var(--bad); }
        .dot-readout__context { font-size:var(--t-xs); color:var(--text-quiet); line-height:1.5; }

        /* --- State bar ------------------------------------------------
           The instrument's pulse, and the answer to "does anything need me?"
           before any figure is read. One lamp, the state in words, and what
           it covers. Sits at the top of every operational page. */
        .dot-statebar { display:flex; align-items:center; gap:0.9rem; flex-wrap:wrap; padding:0 1.25rem 0 0; border:1px solid var(--rule); border-radius:var(--r-lg); background:var(--panel); margin-bottom:var(--gap); overflow:hidden; }
        .dot-statebar__bay { display:flex; align-items:center; justify-content:center; align-self:stretch; padding:0.85rem 1rem; border-right:1px solid var(--rule); background:var(--panel-sunken); }
        .dot-statebar--ok { --lamp:var(--ok); }
        .dot-statebar--warn { --lamp:var(--warn); }
        .dot-statebar--bad { --lamp:var(--bad); }
        .dot-statebar--idle { --lamp:var(--text-faint); }
        .dot-statebar__lamp { width:9px; height:9px; border-radius:50%; background:var(--lamp); color:var(--lamp); box-shadow:var(--lamp-glow); flex-shrink:0; }
        .dot-statebar__state { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-body); font-weight:600; color:var(--text); padding:0.85rem 0; }
        .dot-statebar__detail { font-size:var(--t-sm); color:var(--text-muted); flex:1 1 14rem; min-width:0; line-height:1.5; }
        .dot-statebar__meta { font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; }
        .dot-statebar__link { font-size:var(--t-sm); font-weight:600; color:var(--signal); text-decoration:none; white-space:nowrap; }
        .dot-statebar__link:hover { text-decoration:underline; }

        /* --- Ledger ---------------------------------------------------
           Rows meant to be COMPARED down a column, so figures are tabular and
           the rules are hairlines rather than zebra fill. */
        .dot-ledger { width:100%; border-collapse:collapse; font-size:var(--t-sm); }
        .dot-ledger th { text-align:left; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-faint); padding:0.6rem 0.85rem; border-bottom:1px solid var(--rule); white-space:nowrap; }
        .dot-ledger td { padding:0.7rem 0.85rem; border-bottom:1px solid var(--rule); color:var(--text-muted); vertical-align:top; }
        .dot-ledger tbody tr:last-child td { border-bottom:0; }
        .dot-ledger__num { font-family:'IBM Plex Mono',monospace; font-variant-numeric:tabular-nums; color:var(--text); text-align:right; white-space:nowrap; }
        .dot-ledger__key { font-family:'IBM Plex Mono',monospace; color:var(--text); }

        /* --- Recurrence strip -----------------------------------------
           A pattern is a thing that keeps happening, so it gets shown as a
           run of marks over time rather than a number. Marks are squares on
           a baseline, not a chart: this is a tally, and reading it as one
           avoids implying a precision the counts do not have. */
        .dot-spark { display:flex; align-items:flex-end; gap:1px; height:22px; }
        .dot-spark__mark { width:3px; background:var(--signal); opacity:0.85; border-radius:1px; flex-shrink:0; }
        .dot-spark__mark--empty { background:var(--rule-strong); opacity:1; height:2px !important; }

        .dot-panels--1 { grid-template-columns:minmax(0,1fr); }
        .dot-ledger__empty { padding:2rem 1.25rem; color:var(--text-faint); text-align:center; }

        /* --- Pattern ledger -------------------------------------------
           The comparison surface. Every column here answers a question a
           reader actually asks: how often, how often fixed, how recently. */
        /* The headline is the row's subject and everything else is an
           annotation on it, so it takes the slack and the rest shrink to
           their content. Without this the pattern column was starved and
           every headline wrapped to four lines. */
        .dot-ledger--patterns td:nth-child(2), .dot-ledger--patterns th:nth-child(2) { width:100%; min-width:11rem; padding-left:0; }
        .dot-ledger--patterns td:nth-child(3), .dot-ledger--patterns th:nth-child(3),
        .dot-ledger--patterns td:nth-child(5), .dot-ledger--patterns th:nth-child(5),
        .dot-ledger--patterns td:nth-child(6), .dot-ledger--patterns th:nth-child(6),
        .dot-ledger--patterns td:nth-child(7), .dot-ledger--patterns th:nth-child(7) { white-space:nowrap; width:1%; }
        .dot-ledger--patterns td { vertical-align:middle; }
        .dot-ledger__num-head { text-align:right; }
        .dot-ledger__toggle { width:1%; padding:0.7rem 0.4rem 0.7rem 0.7rem; }
        .dot-ledger__toggle-btn { display:flex; align-items:center; background:none; border:0; padding:2px; cursor:pointer; color:inherit; }
        .dot-ledger__headline { font-size:var(--t-body); font-weight:600; color:var(--text); margin-right:0.4rem; }
        .dot-ledger__sub { display:block; font-size:var(--t-xs); color:var(--text-quiet); margin-top:0.2rem; }
        .dot-ledger__when { font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; }
        .dot-ledger__learned { font-size:var(--t-body); color:var(--text); line-height:1.6; margin:0 0 0.4rem; max-width:74ch; }
        .dot-ledger__trust { font-size:var(--t-sm); color:var(--text-quiet); line-height:1.6; margin:0 0 1.2rem; max-width:74ch; }
        .dot-ledger__evidence-title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-xs); font-weight:600; text-transform:uppercase; letter-spacing:0.11em; color:var(--text-faint); margin:0 0 0.7rem; }

        .dot-occurrences { list-style:none; margin:0; padding:0; }
        .dot-occurrence { display:flex; align-items:baseline; gap:1rem; padding:0.5rem 0; border-top:1px solid var(--rule); }
        .dot-occurrence:first-child { border-top:0; padding-top:0; }
        .dot-occurrence__when { font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; flex-shrink:0; padding-top:0.15rem; }
        .dot-occurrence__body { min-width:0; }
        .dot-occurrence__took { font-family:'IBM Plex Mono',monospace; color:var(--text-muted); }
        .dot-occurrence__meta { display:flex; align-items:center; gap:0.7rem; flex-wrap:wrap; font-size:var(--t-xs); color:var(--text-faint); margin:0; }
        .dot-occurrence__meta a { color:var(--signal); text-decoration:none; }
        .dot-occurrence__meta a:hover { text-decoration:underline; }
        .dot-occurrence__flag { color:var(--bad); }

        /* --- Segmented control ----------------------------------------
           Two mutually exclusive views of the same list, so they are shown
           as one control with a thrown switch rather than two buttons. */
        .dot-segmented { display:inline-flex; border:1px solid var(--rule); border-radius:var(--r-sm); overflow:hidden; }
        .dot-segmented__btn { background:transparent; border:0; padding:0.4rem 0.8rem; font-family:inherit; font-size:var(--t-xs); font-weight:500; color:var(--text-quiet); cursor:pointer; }
        .dot-segmented__btn + .dot-segmented__btn { border-left:1px solid var(--rule); }
        .dot-segmented__btn:hover { background:var(--panel-raised); color:var(--text); }
        .dot-segmented__btn.is-on { background:var(--signal-quiet); color:var(--signal); font-weight:600; }

        @media (max-width: 900px) {
            /* The spark and the platform column are the first things a
               narrow screen can afford to lose; the figures are not. The
               breakpoint is set by where the table would start to overflow
               its container, not by a round device width. */
            .dot-ledger--patterns th:nth-child(3), .dot-ledger--patterns td:nth-child(3),
            .dot-ledger--patterns th:nth-child(4), .dot-ledger--patterns td:nth-child(4) { display:none; }
            .dot-occurrence { flex-direction:column; gap:0.3rem; }
        }

        /* --- Expand in place ------------------------------------------
           An occurrence opens INSIDE the ledger rather than in a dialog: the
           comparison you opened it from stays on screen, which a dialog
           covers up. */
        .dot-ledger__row--expandable { cursor:pointer; }
        .dot-ledger__row--expandable:hover { background:var(--panel-raised); }
        .dot-ledger__row--open { background:var(--panel-raised); }
        .dot-ledger__detail td { padding:0; border-bottom:1px solid var(--rule); }
        .dot-ledger__detail-inner { padding:1.1rem 1.25rem 1.3rem 1.6rem; border-left:1px solid var(--rule-strong); background:var(--panel-sunken); }
        .dot-ledger__detail-inner > p, .dot-ledger__detail-inner > h3 { max-width:min(74ch, 100%); }
        .dot-ledger__caret { font-size:16px; color:var(--text-faint); transition:transform .14s ease; display:inline-block; }
        .dot-ledger__row--open .dot-ledger__caret { transform:rotate(90deg); color:var(--signal); }

        /* --- Components ----------------------------------------------
           Everything the dashboard is built from. Pages compose these and
           do not restyle them; that is what stops nine views drifting into
           nine slightly different products. */
        .dot-card { background:var(--panel); border:1px solid var(--rule); border-radius:var(--r-lg); }
        .dot-card--link { display:block; text-decoration:none; color:inherit; transition:border-color .14s ease, background .14s ease; }
        .dot-card--link:hover { border-color:var(--rule-strong); background:var(--panel-raised); }
        .dot-card__head { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); margin-bottom:0.9rem; }
        .dot-card__title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-section); font-weight:600; color:var(--text); margin:0; }
        .dot-card__action a, .dot-card__action button { font-size:var(--t-xs); color:var(--accent); text-decoration:none; background:none; border:0; cursor:pointer; }

        .dot-page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:var(--gap); margin-bottom:var(--gap); flex-wrap:wrap; }
        .dot-page-head__title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-display); font-weight:700; color:var(--text); margin:0 0 0.25rem; letter-spacing:-0.015em; }
        .dot-page-head__lede { font-size:var(--t-sm); color:var(--text-quiet); margin:0; max-width:62ch; line-height:1.6; }

        .dot-stat { padding:1.25rem 1.5rem; }
        .dot-stat__label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-faint); margin-bottom:0.7rem; }
        .dot-stat__value { font-family:'IBM Plex Mono',monospace; font-variant-numeric:tabular-nums; font-size:1.9rem; font-weight:500; line-height:1.1; color:var(--text); letter-spacing:-0.02em; }
        .dot-stat__value--accent { color:var(--accent); }
        .dot-stat__value--ok { color:var(--ok); }
        .dot-stat__value--warn { color:var(--warn); }
        .dot-stat__value--bad { color:var(--bad); }
        .dot-stat__context { font-size:var(--t-xs); color:var(--text-quiet); margin-top:0.35rem; line-height:1.5; }

        .dot-status { display:inline-flex; align-items:center; gap:0.25rem; font-size:var(--t-micro); font-weight:600; text-transform:uppercase; letter-spacing:0.06em; padding:0.15rem 0.45rem; border-radius:var(--r-sm); background:var(--panel-raised); color:var(--text-muted); white-space:nowrap; }
        .dot-status__icon { font-size:13px; }
        .dot-status--ok { background:var(--ok-quiet); color:var(--ok); }
        .dot-status--warn { background:var(--warn-quiet); color:var(--warn); }
        .dot-status--bad { background:var(--bad-quiet); color:var(--bad); }
        .dot-status--info { background:var(--accent-quiet); color:var(--accent); }

        .dot-empty { padding:3rem 2.5rem; text-align:center; }
        .dot-empty__icon { font-size:30px; color:var(--text-faint); margin-bottom:0.8rem; display:inline-block; }
        .dot-empty__title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-title); color:var(--text); margin:0 0 0.5rem; }
        .dot-empty__body { font-size:var(--t-body); color:var(--text-quiet); max-width:34rem; margin:0 auto; line-height:1.6; }
        .dot-empty__action { margin-top:1.2rem; }
        .dot-empty__action a { font-size:var(--t-sm); color:var(--accent); text-decoration:none; }

        /* Page furniture ---------------------------------------------- */
        .dot-page-body { padding:1.75rem 2rem 3rem; }
        @media (max-width: 860px) { .dot-page-body { padding:1.25rem 1.1rem 2.5rem; } }
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
        .dot-list__row { display:block; padding:0.9rem 1.5rem; border-top:1px solid var(--panel-raised); text-decoration:none; transition:background .12s ease; }
        .dot-list__row:hover { background:var(--panel-raised); }
        .dot-list__title { display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap; font-size:var(--t-body); font-weight:600; color:var(--text); margin-bottom:0.25rem; }
        .dot-list__repeat { font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--signal); }
        .dot-list__body { display:block; font-size:var(--t-sm); color:var(--text-quiet); line-height:1.55; }
        .dot-list__meta { display:block; font-size:var(--t-xs); color:var(--text-faint); margin-top:0.35rem; }

        /* A list that fills its panel edge-to-edge: the panel already owns
           the padding, so the rows cancel it and re-apply their own. */
        .dot-list--flush { margin:0; padding:0; }
        .dot-list--flush .dot-list__row { padding:0.85rem 1.25rem; border-top:1px solid var(--rule); }
        .dot-list--flush li:first-child .dot-list__row { border-top:0; }
        .dot-links--spaced { margin-top:1.1rem; padding-top:0.9rem; border-top:1px solid var(--rule); }
        .dot-links { list-style:none; margin:0; padding:0; }
        .dot-links a { display:flex; align-items:center; gap:0.55rem; padding:0.45rem 0; font-size:var(--t-body); color:var(--text-muted); text-decoration:none; }
        .dot-links a:hover { color:var(--text); }
        .dot-links .material-symbols-rounded { font-size:17px; color:var(--text-faint); }

        .dot-links__meta { margin-left:auto; font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; }
        .dot-tags { list-style:none; display:flex; flex-wrap:wrap; gap:0.4rem; margin:0.8rem 0 0; padding:0; }
        .dot-tag { font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-muted); border:1px solid var(--rule); border-radius:var(--r-sm); padding:0.2rem 0.5rem; }
        .dot-tag--more { color:var(--text-faint); border-style:dashed; }
        .dot-panels--secondary { margin-top:var(--gap); }
        .dot-entry { padding:1.1rem 1.4rem; margin-bottom:var(--gap-sm); }

        /* Filter bar: labelled controls, consistent field styling. */
        .dot-filters { display:flex; gap:var(--gap-sm); flex-wrap:wrap; align-items:center; padding:0.9rem 1rem; margin-bottom:var(--gap); }
        .dot-field { position:relative; }
        .dot-field--grow { flex:1; min-width:14rem; }
        .dot-input, .dot-select { width:100%; background:var(--panel-sunken); border:1px solid var(--rule); border-radius:var(--r-sm); padding:0.5rem 0.7rem; font-size:var(--t-body); color:var(--text); font-family:inherit; }
        .dot-select { color:var(--text-muted); font-size:var(--t-sm); }
        .dot-input:hover, .dot-select:hover { border-color:var(--line-strong); }
        .dot-field__hint { position:absolute; right:0.7rem; top:50%; transform:translateY(-50%); font-size:var(--t-xs); color:var(--accent); }
        .dot-result-count { font-size:var(--t-xs); color:var(--text-faint); margin:0 0 var(--gap-sm); }

        /* Detail page ------------------------------------------------- */
        .dot-back { display:inline-flex; align-items:center; gap:0.35rem; font-size:var(--t-xs); color:var(--text-quiet); text-decoration:none; margin-bottom:0.8rem; }
        .dot-back:hover { color:var(--text-muted); }
        .dot-back .material-symbols-rounded { font-size:15px; }
        .dot-lede-label { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-section); font-weight:600; color:var(--accent); margin:0 0 0.5rem; }
        .dot-prose { font-size:var(--t-body); color:var(--text-muted); line-height:1.65; margin:0; }
        .dot-trust { font-size:var(--t-body); font-weight:600; color:var(--accent); margin:0 0 0.35rem; }
        .dot-timeline { list-style:none; margin:0; padding:0; }
        .dot-timeline li { display:flex; gap:0.8rem; padding:0.45rem 0; border-top:1px solid var(--panel-raised); }
        .dot-timeline time { font-size:var(--t-xs); color:var(--text-faint); white-space:nowrap; }
        .dot-timeline span { font-size:var(--t-sm); color:var(--text-muted); }
        .dot-related { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); padding:0.55rem 0; border-top:1px solid var(--panel-raised); font-size:var(--t-sm); color:var(--text-muted); text-decoration:none; }
        .dot-related:hover { color:var(--text); }
        .dot-disclosure { display:flex; align-items:center; gap:0.5rem; width:100%; padding:0; background:none; border:0; cursor:pointer; text-align:left; font-family:inherit; font-size:var(--t-body); font-weight:600; color:var(--text-quiet); }
        .dot-disclosure:hover { color:var(--text-muted); }
        .dot-disclosure .material-symbols-rounded { font-size:16px; color:var(--text-faint); transition:transform .14s ease; }
        .dot-disclosure[aria-expanded="true"] .material-symbols-rounded { transform:rotate(180deg); }
        .dot-disclosure__hint { font-size:var(--t-micro); font-weight:400; color:var(--text-ghost); }
        .dot-code { background:var(--panel-sunken); border:1px solid var(--rule); border-radius:var(--r-sm); padding:1rem; font-family:'IBM Plex Mono',monospace; font-size:var(--t-xs); color:var(--text-muted); line-height:1.6; margin:1rem 0 0; }

        /* Timeline & insights ----------------------------------------- */
        .dot-day { margin-bottom:1.75rem; }
        .dot-day__label { font-size:var(--t-xs); font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-faint); margin:0 0 0.6rem; }
        .dot-day__card { padding:0.4rem 0; }
        .dot-events { list-style:none; margin:0; padding:0; }
        .dot-event { display:flex; align-items:flex-start; gap:0.9rem; padding:0.7rem 1.3rem; }
        .dot-event + .dot-event { border-top:1px solid var(--panel-raised); }
        .dot-event__icon { font-size:17px; color:var(--accent); margin-top:0.05rem; }
        .dot-event__text { flex:1; font-size:var(--t-body); color:var(--text-muted); line-height:1.5; }
        .dot-event__text a { color:var(--text-muted); text-decoration:none; }
        .dot-event__text a:hover { color:var(--text); }
        .dot-event__time { font-size:var(--t-xs); color:var(--text-ghost); white-space:nowrap; }

        .dot-lede-label--spaced { margin-top:1.4rem; }
        .dot-btn { display:inline-flex; align-items:center; gap:0.4rem; padding:0.5rem 0.9rem; border-radius:var(--r); background:var(--accent-quiet); border:1px solid rgba(129,140,248,0.28); color:var(--accent); font-size:var(--t-sm); font-weight:600; text-decoration:none; cursor:pointer; font-family:inherit; }
        .dot-btn:hover { background:rgba(129,140,248,0.18); }
        .dot-insight { width:100%; text-align:left; background:none; border:0; border-top:1px solid var(--panel-raised); cursor:pointer; font-family:inherit; }
        .dot-insight { display:block; padding:0.7rem 0; border-top:1px solid var(--panel-raised); text-decoration:none; }
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
        .dot-table tbody tr:hover { background:var(--panel-raised); }
        .dot-table__num { font-family:'IBM Plex Mono',monospace; color:var(--text-muted); }

        /* Modal ------------------------------------------------------- */
        .dot-modal { position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(0,0,0,0.6); backdrop-filter:blur(2px); }
        .dot-modal__panel { width:100%; max-height:85vh; overflow-y:auto; background:var(--surface); border:1px solid var(--line-strong); border-radius:var(--r-lg); animation:dot-modal-in .14s ease-out; }
        @keyframes dot-modal-in { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
        .dot-modal__head { display:flex; align-items:center; justify-content:space-between; gap:var(--gap-sm); padding:1.1rem 1.4rem; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--surface); }
        .dot-modal__title { font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif; font-size:var(--t-title); font-weight:600; color:var(--text); margin:0; }
        .dot-modal__close { background:none; border:0; color:var(--text-quiet); cursor:pointer; display:flex; padding:0.2rem; border-radius:var(--r-sm); }
        .dot-modal__close:hover { color:var(--text); background:var(--panel-raised); }
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
        .metric-val { font-family:'IBM Plex Mono',monospace; font-variant-numeric:tabular-nums; font-weight:500; letter-spacing:-0.02em; }

        /* Buttons and badges. This block used to redeclare .dot-input on the
           old indigo palette AND in Inter, and because it sits below the
           component layer it quietly won on source order -- the field
           styling above was never actually reaching the page. */
        .dot-btn { display:inline-flex; align-items:center; gap:6px; padding:0.45rem 0.85rem; border-radius:var(--r-sm); font-size:var(--t-sm); font-weight:600; cursor:pointer; transition:background .14s,color .14s; border:1px solid transparent; text-decoration:none; font-family:inherit; }
        .dot-btn-primary { background:var(--signal); color:var(--panel); }
        .dot-btn-primary:hover { filter:brightness(1.08); }
        .dot-btn-ghost { background:transparent; color:var(--text-muted); border-color:var(--rule); }
        .dot-btn-ghost:hover { background:var(--panel-raised); color:var(--text); }
        .dot-badge { display:inline-flex; align-items:center; padding:0.15rem 0.45rem; border-radius:var(--r-sm); font-size:var(--t-micro); font-weight:600; text-transform:uppercase; letter-spacing:0.06em; }
        .dot-badge-accent { background:var(--signal-quiet); color:var(--signal); }
        .dot-input::placeholder { color:var(--text-faint); }
        select.dot-select option, select.dot-input option { background:var(--panel); color:var(--text); }
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
            <div class="brand-icon brand-icon--mark">
                <img src="{{ asset('images/mark.png') }}" alt="Dot.Memory" class="brand-mark">
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
            <a href="{{ route('knowledge.patterns') }}" class="nav-item {{ request()->routeIs('knowledge.patterns') ? 'active' : '' }}">
                <span class="material-symbols-rounded nav-icon">repeat</span>
                Patterns
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
        {{-- Names the action, not the state: in night it offers day, and in
             day it offers night. Icon and label are kept in step by the same
             handler that flips the class. --}}
        <button type="button" class="topbar-btn" data-theme-toggle onclick="window.dotToggleTheme(this)">
            <span class="material-symbols-rounded theme-icon-night" aria-hidden="true">light_mode</span>
            <span class="material-symbols-rounded theme-icon-day" aria-hidden="true">dark_mode</span>
            <span class="sr-only" data-theme-label>Switch to day</span>
        </button>
        <a href="{{ route('profile.show') }}" class="topbar-btn">
            <span class="material-symbols-rounded" aria-hidden="true">account_circle</span>
            <span class="sr-only">Profile &amp; settings</span>
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
