<div>
    <a href="{{ route('knowledge.index') }}" style="font-size:0.75rem;color:#71717a;text-decoration:none;">← All knowledge</a>

    <div style="margin:0.8rem 0 1.5rem;">
        <div style="display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;margin-bottom:0.4rem;">
            <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0;">{{ $this->human['headline'] }}</h1>
            <span style="font-size:0.7rem;font-weight:600;padding:0.2rem 0.6rem;border-radius:99px;background:rgba(129,140,248,0.12);color:var(--accent);">{{ $this->human['status_label'] }}</span>
            <span style="font-size:0.7rem;font-weight:600;padding:0.2rem 0.6rem;border-radius:99px;background:rgba(255,255,255,0.06);color:#a1a1aa;">{{ $this->human['severity_label'] }}</span>
        </div>
        <p style="font-size:0.76rem;color:#52525b;margin:0;">{{ $this->human['platform_label'] }} · first noticed {{ $incident->detected_at->diffForHumans() }}</p>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start;">
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:var(--accent);margin:0 0 0.5rem;">What happened</h2>
                <p style="font-size:0.85rem;color:#d4d4d8;line-height:1.65;margin:0;">{{ $this->human['what_happened'] }}</p>
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:var(--accent);margin:0 0 0.5rem;">What was learned</h2>
                <p style="font-size:0.85rem;color:#d4d4d8;line-height:1.65;margin:0;">{{ $this->human['what_was_learned'] }}</p>
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:var(--accent);margin:0 0 0.5rem;">Why it matters</h2>
                <p style="font-size:0.85rem;color:#d4d4d8;line-height:1.65;margin:0;">{{ $this->human['why_it_matters'] }}</p>
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:#f4f4f5;margin:0 0 0.9rem;">History</h2>
                @foreach ($this->history as $event)
                    <div style="display:flex;gap:0.8rem;padding:0.45rem 0;border-top:1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:0.72rem;color:#52525b;white-space:nowrap;">{{ $event['at']->timezone(config('app.timezone'))->format('j M H:i') }}</span>
                        <span style="font-size:0.8rem;color:#a1a1aa;">{{ $event['event'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="dot-card" x-data="{ open: false }" style="padding:1.5rem;">
                <button @click="open = !open" style="background:none;border:none;padding:0;cursor:pointer;display:flex;align-items:center;gap:0.5rem;width:100%;">
                    <span class="material-symbols-rounded" style="font-size:16px;color:#52525b;" x-text="open ? 'expand_less' : 'expand_more'"></span>
                    <span style="font-size:0.8rem;font-weight:600;color:#71717a;">Technical detail</span>
                    <span style="font-size:0.68rem;color:#3f3f46;">for engineers and agents</span>
                </button>
                <div x-show="open" x-cloak style="margin-top:1rem;">
                    <pre style="background:#09090b;border:1px solid rgba(255,255,255,0.07);border-radius:8px;padding:1rem;font-family:'JetBrains Mono',monospace;font-size:0.7rem;color:#a1a1aa;overflow-x:auto;line-height:1.6;margin:0;">{{ json_encode(['incident_uid' => $incident->incident_uid, 'platform' => $incident->platform, 'component' => $incident->component, 'signature' => $incident->signature, 'severity' => $incident->severity, 'status' => $incident->status, 'detection_source' => $incident->detection_source, 'detected_at' => $incident->detected_at?->toISOString(), 'resolved_at' => $incident->resolved_at?->toISOString(), 'tests_result' => $incident->tests_result, 'deploy_result' => $incident->deploy_result, 'validation_result' => $incident->validation_result, 'rollback_occurred' => $incident->rollback_occurred, 'record' => $incident->record], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:#f4f4f5;margin:0 0 0.6rem;">How much to trust this</h2>
                <div style="font-size:0.8rem;font-weight:600;color:var(--accent);margin-bottom:0.35rem;">{{ $this->human['trust']['label'] }}</div>
                <p style="font-size:0.76rem;color:#71717a;line-height:1.6;margin:0;">{{ $this->human['trust']['explanation'] }}</p>
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:#f4f4f5;margin:0 0 0.6rem;">Used by</h2>
                @if ($this->usage['count'] > 0)
                    <p style="font-size:0.78rem;color:#a1a1aa;line-height:1.6;margin:0;">
                        Dot.Brain consulted this experience {{ $this->usage['count'] }} {{ Str::plural('time', $this->usage['count']) }}
                        while deciding how to respond to live problems{{ $this->usage['last'] !== null ? ', most recently '.\Illuminate\Support\Carbon::parse($this->usage['last'])->diffForHumans() : '' }}.
                    </p>
                @else
                    <p style="font-size:0.78rem;color:#71717a;line-height:1.6;margin:0;">Not consulted yet. If this problem recurs anywhere in the ecosystem, this record is what the guardian will reach for.</p>
                @endif
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Syne',sans-serif;font-size:0.9rem;color:#f4f4f5;margin:0 0 0.6rem;">Related knowledge</h2>
                @forelse ($this->relatedKnowledge as $entry)
                    <a href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}" style="display:block;padding:0.5rem 0;border-top:1px solid rgba(255,255,255,0.05);text-decoration:none;">
                        <div style="font-size:0.76rem;color:#d4d4d8;">Same problem, {{ $entry['incident']->detected_at->diffForHumans() }}</div>
                        <div style="font-size:0.7rem;color:#52525b;">{{ $entry['human']['status_label'] }}</div>
                    </a>
                @empty
                    <p style="font-size:0.76rem;color:#71717a;margin:0;">This is the only record of this problem so far.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
