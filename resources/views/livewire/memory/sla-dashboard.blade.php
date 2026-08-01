<div class="dot-card" style="padding:1.5rem;">
    <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Retrieval SLA Attainment</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" style="font-size:22px;color:#818cf8;">progress_activity</span>
    </div>
    <div wire:loading.remove.delay style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        @forelse($this->classes as $class)
            @php
                $latest = $class->observations->first();
                $met = $latest?->sla_met;
                $color = $latest === null ? '#52525b' : ($met ? '#22c55e' : '#ef4444');
            @endphp
            <div style="border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:1rem;background:rgba(255,255,255,0.02);">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#71717a;">{{ $class->class_key }}</div>
                <div style="font-size:12px;color:#a1a1aa;margin-top:2px;">{{ $class->serves }}</div>

                <div style="display:flex;align-items:baseline;gap:6px;margin-top:0.75rem;">
                    <span class="metric-val" style="font-size:1.5rem;font-weight:600;color:{{ $color }};">
                        {{ $latest ? $latest->p95_latency_ms . 'ms' : '—' }}
                    </span>
                    <span style="font-size:11px;color:#52525b;">p95 (target ≤ {{ $class->p95_target_ms }}ms)</span>
                </div>

                @if($class->p99_target_ms)
                <div style="font-size:11px;color:#71717a;margin-top:2px;">
                    p99 {{ $latest?->p99_latency_ms ?? '—' }}ms (target ≤ {{ $class->p99_target_ms }}ms)
                </div>
                @endif

                <div style="margin-top:0.75rem;display:flex;align-items:center;gap:6px;">
                    <span class="dot-badge" style="background:{{ $met === null ? 'rgba(113,113,122,0.15)' : ($met ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)') }};color:{{ $color }};">
                        {{ $met === null ? 'No data' : ($met ? 'Meeting contract' : 'Breach') }}
                    </span>
                    @if($latest?->degraded_mode_triggered)
                        <span class="dot-badge" style="background:rgba(245,158,11,0.12);color:#f59e0b;">Degraded mode</span>
                    @endif
                </div>

                <div style="font-size:10px;color:#52525b;margin-top:0.5rem;">On breach: {{ $class->breach_action }}</div>
            </div>
        @empty
            <p style="font-size:0.8rem;color:#52525b;">No retrieval classes configured yet.</p>
        @endforelse
    </div>
</div>
