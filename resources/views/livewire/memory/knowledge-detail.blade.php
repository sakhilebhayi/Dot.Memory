@php
    $human = $this->human;
    $statusTone = match ($human['status_label']) {
        'Fixed' => 'ok',
        'Needs a person' => 'bad',
        'Fix was undone' => 'warn',
        default => 'info',
    };
@endphp

<div>
    <a class="dot-back" href="{{ route('knowledge.index') }}">
        <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>All knowledge
    </a>

    <x-dot.page :title="$human['headline']">
        <x-slot:lede>
            {{ $human['platform_label'] }} &middot; first noticed {{ $incident->detected_at->diffForHumans() }}
        </x-slot:lede>
        <x-slot:actions>
            <x-dot.status :label="$human['status_label']" :tone="$statusTone" />
            <x-dot.status :label="$human['severity_label']" />
        </x-slot:actions>
    </x-dot.page>

    {{-- Progressive disclosure: the plain answer first, then context, then
         evidence, and only then the machine-readable detail. --}}
    <div class="dot-grid dot-grid--split">
        <div class="dot-stack">
            <x-dot.card class="dot-pad">
                <h2 class="dot-lede-label">What happened</h2>
                <p class="dot-prose">{{ $human['what_happened'] }}</p>
            </x-dot.card>

            <x-dot.card class="dot-pad">
                <h2 class="dot-lede-label">What was learned</h2>
                <p class="dot-prose">{{ $human['what_was_learned'] }}</p>
            </x-dot.card>

            <x-dot.card class="dot-pad">
                <h2 class="dot-lede-label">Why it matters</h2>
                <p class="dot-prose">{{ $human['why_it_matters'] }}</p>
            </x-dot.card>

            <x-dot.card class="dot-pad" title="History">
                <ol class="dot-timeline">
                    @foreach ($this->history as $event)
                        <li>
                            <time datetime="{{ $event['at']?->toISOString() }}">
                                {{ $event['at']->timezone(config('app.timezone'))->format('j M H:i') }}
                            </time>
                            <span>{{ $event['event'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </x-dot.card>

            {{-- The technical record stays one click away rather than gone:
                 engineers and agents need it, everyone else does not. --}}
            <x-dot.card class="dot-pad">
                <button
                    type="button"
                    class="dot-disclosure"
                    x-data="{ open: false }"
                    x-on:click="open = !open; $refs.detail.hidden = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="technical-detail"
                >
                    <span class="material-symbols-rounded" aria-hidden="true">expand_more</span>
                    Technical detail
                    <span class="dot-disclosure__hint">for engineers and agents</span>
                </button>
                <div id="technical-detail" x-ref="detail" hidden>
                    <pre class="dot-code dot-scroll-x">{{ json_encode([
                        'incident_uid' => $incident->incident_uid,
                        'platform' => $incident->platform,
                        'component' => $incident->component,
                        'signature' => $incident->signature,
                        'severity' => $incident->severity,
                        'status' => $incident->status,
                        'detection_source' => $incident->detection_source,
                        'detected_at' => $incident->detected_at?->toISOString(),
                        'resolved_at' => $incident->resolved_at?->toISOString(),
                        'deploy_result' => $incident->deploy_result,
                        'validation_result' => $incident->validation_result,
                        'rollback_occurred' => $incident->rollback_occurred,
                        'record' => $incident->record,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </x-dot.card>
        </div>

        <div class="dot-stack">
            <x-dot.card class="dot-pad" title="How much to trust this">
                <p class="dot-trust">{{ $human['trust']['label'] }}</p>
                <p class="dot-note">{{ $human['trust']['explanation'] }}</p>
            </x-dot.card>

            {{-- Makes the three-platform relationship legible without asking
                 anyone to know the architecture (ADR-0015). --}}
            <x-dot.card class="dot-pad" title="Used by">
                @if ($this->usage['count'] > 0)
                    <p class="dot-note">
                        Dot.Brain consulted this experience
                        <strong>{{ $this->usage['count'] }}</strong>
                        {{ Str::plural('time', $this->usage['count']) }} while deciding how to respond to live
                        problems{{ $this->usage['last'] !== null ? ', most recently '.\Illuminate\Support\Carbon::parse($this->usage['last'])->diffForHumans() : '' }}.
                    </p>
                @else
                    <p class="dot-note">
                        Not consulted yet. If this problem recurs anywhere in the ecosystem, this record is what
                        the guardian will reach for.
                    </p>
                @endif
            </x-dot.card>

            <x-dot.card class="dot-pad" title="Related knowledge">
                @forelse ($this->relatedKnowledge as $entry)
                    <a class="dot-related" href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}">
                        <span>Same problem, {{ $entry['incident']->detected_at->diffForHumans() }}</span>
                        <x-dot.status
                            :label="$entry['human']['status_label']"
                            :tone="$entry['human']['status_label'] === 'Fixed' ? 'ok' : 'info'"
                        />
                    </a>
                @empty
                    <p class="dot-note">This is the only record of this problem so far.</p>
                @endforelse
            </x-dot.card>
        </div>
    </div>
</div>
