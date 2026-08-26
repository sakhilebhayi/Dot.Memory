@php
    $a = $this->attention;
    $tone = match ($a['tone']) {
        'urgent' => 'bad',
        'warning' => 'warn',
        'watching' => 'info',
        default => 'ok',
    };
    $glyph = match ($a['tone']) {
        'urgent' => 'priority_high',
        'warning' => 'schedule',
        'watching' => 'visibility',
        'quiet' => 'psychology',
        default => 'check_circle',
    };
@endphp

<div>
    <x-dot.page
        title="What the ecosystem knows"
        lede="Every problem the Dot platforms have lived through, what fixed it, and what it taught us."
    />

    {{-- The page's first answer is a sentence, not a number: does anything
         need you right now? Icon and wording carry it as well as colour. --}}
    <x-dot.card class="dot-attention dot-attention--{{ $tone }}">
        <span class="material-symbols-rounded dot-attention__icon" aria-hidden="true">{{ $glyph }}</span>
        <div class="dot-attention__text">
            <p class="dot-attention__headline">{{ $a['headline'] }}</p>
            <p class="dot-attention__detail">{{ $a['detail'] }}</p>
        </div>
        @if ($a['link'] !== null)
            <a class="dot-attention__link" href="{{ $a['link']['url'] }}">{{ $a['link']['label'] }} &rarr;</a>
        @endif
    </x-dot.card>

    @if ($this->figures['total'] === 0)
        <x-dot.empty title="Your knowledge base is still growing">
            As the Dot platforms run, their guardian records every production problem, every fix and every
            lesson here &mdash; so the next time something goes wrong, the answer is already waiting.
            <x-slot:action>
                <a href="{{ route('reliability.index') }}">See what is being watched right now &rarr;</a>
            </x-slot:action>
        </x-dot.empty>
    @else
        <div class="dot-grid dot-grid--metrics dot-stack">
            <x-dot.stat label="Experiences recorded" :value="$this->figures['total']" tone="accent" />
            <x-dot.stat label="Problems fixed" :value="$this->figures['fixed']" tone="ok" />
            <x-dot.stat
                label="Being watched now"
                :value="$this->figures['watching']"
                :tone="$this->figures['watching'] > 0 ? 'warn' : 'neutral'"
            />
            <x-dot.stat label="Platforms covered" :value="$this->figures['platforms']" />
        </div>

        <div class="dot-grid dot-grid--split">
            <x-dot.card class="dot-pad" title="Recently learned">
                <x-slot:action>
                    <a href="{{ route('knowledge.index') }}">Browse all knowledge &rarr;</a>
                </x-slot:action>

                <ul class="dot-list">
                    @foreach ($this->recentKnowledge as $entry)
                        <li>
                            <a class="dot-list__row" href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}">
                                <span class="dot-list__title">
                                    {{ $entry['human']['headline'] }}
                                    <x-dot.status
                                        :label="$entry['human']['status_label']"
                                        :tone="$entry['human']['status_label'] === 'Fixed' ? 'ok' : 'info'"
                                    />
                                </span>
                                <span class="dot-list__body">{{ $entry['human']['what_was_learned'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-dot.card>

            <div class="dot-stack">
                <x-dot.card class="dot-pad" title="How this memory is used">
                    <div class="dot-stat__value dot-stat__value--accent">{{ $this->brainUsage['lookups_week'] }}</div>
                    <p class="dot-note">
                        times this week Dot.Brain consulted this archive before deciding how to respond to a live problem.
                    </p>
                    <p class="dot-note dot-note--faint">
                        @if ($this->brainUsage['last_lookup'] !== null)
                            Last consulted {{ \Illuminate\Support\Carbon::parse($this->brainUsage['last_lookup'])->diffForHumans() }}
                        @else
                            Not consulted yet &mdash; the first live problem will change that.
                        @endif
                    </p>
                </x-dot.card>

                <x-dot.card class="dot-pad" title="Explore">
                    <ul class="dot-links">
                        <li><a href="{{ route('knowledge.timeline') }}">
                            <span class="material-symbols-rounded" aria-hidden="true">timeline</span>How knowledge has grown</a></li>
                        <li><a href="{{ route('knowledge.insights') }}">
                            <span class="material-symbols-rounded" aria-hidden="true">lightbulb</span>Recurring problems &amp; gaps</a></li>
                        <li><a href="{{ route('reliability.index') }}">
                            <span class="material-symbols-rounded" aria-hidden="true">speed</span>Storage reliability</a></li>
                    </ul>
                </x-dot.card>
            </div>
        </div>
    @endif
</div>
