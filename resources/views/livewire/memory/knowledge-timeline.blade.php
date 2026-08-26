<div>
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;">Timeline</h1>
        <p style="font-size:0.78rem;color:#52525b;margin:0;">How the ecosystem's knowledge has grown — problems discovered, fixed, and reused, in the order it happened.</p>
    </div>

    @forelse ($this->days as $day => $events)
        <div style="margin-bottom:1.75rem;">
            <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#52525b;margin-bottom:0.6rem;">{{ $day }}</div>
            <div class="dot-card" style="padding:0.4rem 0;">
                @foreach ($events as $event)
                    <div style="display:flex;align-items:flex-start;gap:0.9rem;padding:0.7rem 1.3rem;{{ !$loop->first ? 'border-top:1px solid rgba(255,255,255,0.05);' : '' }}">
                        <span class="material-symbols-rounded" style="font-size:17px;color:var(--accent);margin-top:0.05rem;">{{ $event['icon'] }}</span>
                        <div style="flex:1;">
                            @if ($event['link'] !== null)
                                <a href="{{ $event['link'] }}" style="font-size:0.82rem;color:#d4d4d8;text-decoration:none;line-height:1.5;">{{ $event['text'] }}</a>
                            @else
                                <span style="font-size:0.82rem;color:#a1a1aa;line-height:1.5;">{{ $event['text'] }}</span>
                            @endif
                        </div>
                        <span style="font-size:0.7rem;color:#3f3f46;white-space:nowrap;">{{ $event['at']->timezone(config('app.timezone'))->format('H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="dot-card" style="padding:3rem 2.5rem;text-align:center;">
            <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.05rem;color:#f4f4f5;margin:0 0 0.5rem;">The story hasn't started yet</h2>
            <p style="font-size:0.82rem;color:#71717a;max-width:32rem;margin:0 auto;line-height:1.6;">
                Once the ecosystem's guardian records its first experience, this timeline becomes the running story of what the Dot platforms have lived through and learned.
            </p>
        </div>
    @endforelse
</div>
