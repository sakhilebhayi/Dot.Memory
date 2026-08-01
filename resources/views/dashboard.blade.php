<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Storage &amp; Retrieval SLA Dashboard</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ now()->format('l, F j, Y') }} · Dot.Memory stores without reading — everything below is operational telemetry, never content.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Retrieval Classes</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $retrievalClassCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Classes Meeting SLA</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#22c55e;">{{ $classesMeetingSla }}/{{ $retrievalClassCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Active Indexes</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#f4f4f5;">{{ $activeIndexCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Durability Failures (90d)</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:{{ $recentDurabilityFailures > 0 ? '#ef4444' : '#22c55e' }};">{{ $recentDurabilityFailures }}</div>
        </div>
    </div>

    <livewire:memory.sla-dashboard />

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.25rem;">
        <livewire:memory.index-inventory />
        <livewire:memory.durability-outcomes />
    </div>
</div>
</x-app-layout>
