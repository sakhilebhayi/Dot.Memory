<div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">What the ecosystem knows</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">Every problem the Dot platforms have lived through, what fixed it, and what it taught us — collected here so no experience is ever lost.</p>
        </div>
    </div>

    @if ($this->figures['total'] === 0)
        <div class="dot-card" style="padding:3rem 2.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:34px;color:var(--accent);margin-bottom:0.8rem;display:inline-block;">psychology</span>
            <h2 style="font-family:'Syne',sans-serif;font-size:1.05rem;color:#f4f4f5;margin:0 0 0.5rem;">Your knowledge base is still growing</h2>
            <p style="font-size:0.82rem;color:#71717a;max-width:32rem;margin:0 auto 1.2rem;line-height:1.6;">
                As the Dot ecosystem runs, its guardian records every production problem, every fix, and every lesson here —
                so the next time something goes wrong, the answer is already waiting.
            </p>
            <a href="{{ route('reliability.index') }}" style="font-size:0.8rem;color:var(--accent);text-decoration:none;">See what is being watched right now →</a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
            <div class="dot-card" style="padding:1.25rem 1.5rem;">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Experiences recorded</div>
                <div style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $this->figures['total'] }}</div>
            </div>
            <div class="dot-card" style="padding:1.25rem 1.5rem;">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Problems fixed</div>
                <div style="font-size:2rem;font-weight:600;color:#22c55e;">{{ $this->figures['fixed'] }}</div>
            </div>
            <div class="dot-card" style="padding:1.25rem 1.5rem;">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Being watched now</div>
                <div style="font-size:2rem;font-weight:600;color:{{ $this->figures['watching'] > 0 ? '#fbbf24' : '#f4f4f5' }};">{{ $this->figures['watching'] }}</div>
            </div>
            <div class="dot-card" style="padding:1.25rem 1.5rem;">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Platforms covered</div>
                <div style="font-size:2rem;font-weight:600;color:#f4f4f5;">{{ $this->figures['platforms'] }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start;">
            <div class="dot-card" style="padding:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <h2 style="font-family:'Syne',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0;">Recently learned</h2>
                    <a href="{{ route('knowledge.index') }}" style="font-size:0.75rem;color:var(--accent);text-decoration:none;">Browse all knowledge →</a>
                </div>

                @foreach ($this->recentKnowledge as $entry)
                    <a href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}"
                       style="display:block;padding:0.9rem 1rem;margin:0 -1rem;border-top:1px solid rgba(255,255,255,0.05);text-decoration:none;">
                        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.3rem;">
                            <span style="font-size:0.86rem;font-weight:600;color:#f4f4f5;">{{ $entry['human']['headline'] }}</span>
                            <span style="font-size:0.66rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:99px;background:rgba(129,140,248,0.12);color:var(--accent);">{{ $entry['human']['status_label'] }}</span>
                        </div>
                        <div style="font-size:0.76rem;color:#71717a;line-height:1.5;">{{ $entry['human']['what_was_learned'] }}</div>
                    </a>
                @endforeach
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                <div class="dot-card" style="padding:1.5rem;">
                    <h2 style="font-family:'Syne',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0 0 0.9rem;">How this memory is used</h2>
                    <div style="font-size:2rem;font-weight:600;color:var(--accent);margin-bottom:0.2rem;">{{ $this->brainUsage['lookups_week'] }}</div>
                    <div style="font-size:0.76rem;color:#71717a;line-height:1.5;margin-bottom:0.8rem;">
                        times this week Dot.Brain consulted this archive before deciding how to respond to a live problem.
                    </div>
                    @if ($this->brainUsage['last_lookup'] !== null)
                        <div style="font-size:0.72rem;color:#52525b;">Last consulted {{ \Illuminate\Support\Carbon::parse($this->brainUsage['last_lookup'])->diffForHumans() }}</div>
                    @else
                        <div style="font-size:0.72rem;color:#52525b;">Not consulted yet — the first live problem will change that.</div>
                    @endif
                </div>

                <div class="dot-card" style="padding:1.5rem;">
                    <h2 style="font-family:'Syne',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0 0 0.7rem;">Explore</h2>
                    <a href="{{ route('knowledge.timeline') }}" style="display:block;font-size:0.8rem;color:#d4d4d8;text-decoration:none;padding:0.45rem 0;">📅 How knowledge has grown over time</a>
                    <a href="{{ route('knowledge.insights') }}" style="display:block;font-size:0.8rem;color:#d4d4d8;text-decoration:none;padding:0.45rem 0;">💡 Recurring problems &amp; gaps</a>
                    <a href="{{ route('reliability.index') }}" style="display:block;font-size:0.8rem;color:#d4d4d8;text-decoration:none;padding:0.45rem 0;">🔧 Storage reliability (technical)</a>
                </div>
            </div>
        </div>
    @endif
</div>
