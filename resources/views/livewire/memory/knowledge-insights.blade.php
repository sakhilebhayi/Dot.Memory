<div>
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;">Insights</h1>
        <p style="font-size:0.78rem;color:#52525b;margin:0;">What the archive says when you step back: patterns, slow burns, and where the ecosystem is still flying blind.</p>
    </div>

    <div class="dot-grid dot-grid--pair">
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Space Grotesk',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0 0 0.9rem;">Recurring problems</h2>
                @forelse ($this->recurringProblems as $problem)
                    <a href="{{ route('knowledge.show', $problem['latest_uid']) }}" style="display:block;padding:0.7rem 0;border-top:1px solid rgba(255,255,255,0.05);text-decoration:none;">
                        <div style="font-size:0.82rem;font-weight:600;color:#f4f4f5;margin-bottom:0.25rem;">{{ $problem['headline'] }}</div>
                        <div style="font-size:0.74rem;color:#71717a;">Seen {{ $problem['occurrences'] }} times, fixed {{ $problem['fixed'] }} — <span style="color:var(--accent);">{{ $problem['trust']['label'] }}</span></div>
                    </a>
                @empty
                    <p style="font-size:0.78rem;color:#71717a;margin:0;line-height:1.6;">No problem has repeated itself yet — every recorded experience so far has been a first.</p>
                @endforelse
            </div>

            <div class="dot-card" style="padding:1.5rem;">
                <h2 style="font-family:'Space Grotesk',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0 0 0.9rem;">Waiting too long</h2>
                @forelse ($this->longWatches as $watch)
                    <a href="{{ route('knowledge.show', $watch['uid']) }}" style="display:block;padding:0.7rem 0;border-top:1px solid rgba(255,255,255,0.05);text-decoration:none;">
                        <div style="font-size:0.82rem;font-weight:600;color:#fbbf24;margin-bottom:0.25rem;">{{ $watch['headline'] }}</div>
                        <div style="font-size:0.74rem;color:#71717a;">Unresolved since {{ $watch['since'] }} — deserves a look.</div>
                    </a>
                @empty
                    <p style="font-size:0.78rem;color:#71717a;margin:0;line-height:1.6;">Nothing has been stuck for more than a day.</p>
                @endforelse
            </div>
        </div>

        <div class="dot-card" style="padding:1.5rem;">
            <h2 style="font-family:'Space Grotesk',sans-serif;font-size:0.95rem;color:#f4f4f5;margin:0 0 0.9rem;">Where the ecosystem is covered</h2>
            @if (count($this->coverage['covered']) > 0)
                <div style="font-size:0.74rem;color:#52525b;margin-bottom:0.4rem;">Contributing operational knowledge:</div>
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1.1rem;">
                    @foreach ($this->coverage['covered'] as $name)
                        <span style="font-size:0.72rem;font-weight:600;padding:0.25rem 0.65rem;border-radius:99px;background:rgba(34,197,94,0.12);color:#22c55e;">{{ $name }}</span>
                    @endforeach
                </div>
            @endif
            @if (count($this->coverage['uncovered']) > 0)
                <div style="font-size:0.74rem;color:#52525b;margin-bottom:0.4rem;">Not yet watched — the ecosystem has no operational memory of these:</div>
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                    @foreach ($this->coverage['uncovered'] as $name)
                        <span style="font-size:0.72rem;padding:0.25rem 0.65rem;border-radius:99px;background:rgba(255,255,255,0.05);color:#71717a;">{{ $name }}</span>
                    @endforeach
                </div>
                <p style="font-size:0.72rem;color:#52525b;margin:1rem 0 0;line-height:1.6;">Each platform joins by enrolling with the guardian — one manifest file and a health endpoint.</p>
            @endif
        </div>
    </div>
</div>
