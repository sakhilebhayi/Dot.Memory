<div>
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;">Knowledge</h1>
        <p style="font-size:0.78rem;color:#52525b;margin:0;">Everything the ecosystem has learned from running in production. Search it, filter it, reuse it.</p>
    </div>

    @if ($this->totalArchived === 0)
        <div class="dot-card" style="padding:3rem 2.5rem;text-align:center;">
            <h2 style="font-family:'Syne',sans-serif;font-size:1.05rem;color:#f4f4f5;margin:0 0 0.5rem;">Nothing recorded yet</h2>
            <p style="font-size:0.82rem;color:#71717a;max-width:32rem;margin:0 auto;line-height:1.6;">
                As the Dot platforms run, every production problem and its resolution is captured here automatically.
            </p>
        </div>
    @else
        <div class="dot-card" style="padding:0.9rem 1rem;margin-bottom:1.25rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search what we've learned — try &quot;production data&quot; or &quot;deployment&quot;"
                   style="flex:1;min-width:16rem;background:#09090b;border:1px solid rgba(255,255,255,0.09);border-radius:8px;padding:0.55rem 0.8rem;font-size:0.82rem;color:#f4f4f5;outline:none;">
            <select wire:model.live="platform" style="background:#09090b;border:1px solid rgba(255,255,255,0.09);border-radius:8px;padding:0.55rem 0.7rem;font-size:0.78rem;color:#d4d4d8;">
                <option value="">All platforms</option>
                @foreach ($this->platformOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" style="background:#09090b;border:1px solid rgba(255,255,255,0.09);border-radius:8px;padding:0.55rem 0.7rem;font-size:0.78rem;color:#d4d4d8;">
                <option value="">Any state</option>
                <option value="open">Being watched</option>
                <option value="remediating">Fix in progress</option>
                <option value="resolved">Fixed</option>
                <option value="escalated">Needs a person</option>
                <option value="rolled_back">Fix was undone</option>
            </select>
        </div>

        @forelse ($this->entries as $entry)
            <a href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}" class="dot-card"
               style="display:block;padding:1.1rem 1.4rem;margin-bottom:0.75rem;text-decoration:none;">
                <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.35rem;">
                    <span style="font-size:0.9rem;font-weight:600;color:#f4f4f5;">{{ $entry['human']['headline'] }}</span>
                    <span style="font-size:0.66rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:99px;background:rgba(129,140,248,0.12);color:var(--accent);">{{ $entry['human']['status_label'] }}</span>
                    <span style="font-size:0.66rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:99px;background:rgba(255,255,255,0.06);color:#a1a1aa;">{{ $entry['human']['severity_label'] }}</span>
                </div>
                <div style="font-size:0.78rem;color:#71717a;line-height:1.55;">{{ $entry['human']['what_was_learned'] }}</div>
                <div style="font-size:0.7rem;color:#52525b;margin-top:0.4rem;">{{ $entry['incident']->detected_at->diffForHumans() }} · {{ $entry['human']['platform_label'] }}</div>
            </a>
        @empty
            <div class="dot-card" style="padding:2rem;text-align:center;">
                <p style="font-size:0.82rem;color:#71717a;margin:0;">Nothing matches that search — try fewer words, or clear the filters.</p>
            </div>
        @endforelse
    @endif
</div>
