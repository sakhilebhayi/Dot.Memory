@php
    $a = $this->attention;
    $tone = match ($a['tone']) {
        'urgent' => 'bad',
        'warning' => 'warn',
        'watching' => 'warn',
        'quiet' => 'idle',
        default => 'ok',
    };
@endphp

<div>
    <x-dot.page
        title="What the ecosystem knows"
        lede="Every problem the Dot platforms have lived through, what fixed it, and what it taught us."
    />

    {{-- The instrument's pulse. The state is written out, not just lit, so
         nothing here depends on seeing a colour. --}}
    <x-dot.statebar
        :tone="$tone"
        :state="$a['headline']"
        :detail="$a['detail']"
        :meta="$this->coverage['platforms'].' platforms · last signal '.$this->coverage['last_seen']"
        :link-label="$a['link']['label'] ?? null"
        :link-href="$a['link']['url'] ?? null"
    />

    @if ($this->figures['total'] === 0)
        <x-dot.empty title="Your knowledge base is still growing">
            As the Dot platforms run, their guardian records every production problem, every fix and every
            lesson here &mdash; so the next time something goes wrong, the answer is already waiting.
            <x-slot:action>
                <a href="{{ route('reliability.index') }}">See what is being watched right now &rarr;</a>
            </x-slot:action>
        </x-dot.empty>
    @else
        {{-- Readouts share edges: one gauge cluster, not four floating tiles. --}}
        <div class="dot-stack">
        <x-dot.panels :cols="4">
            <x-dot.panel>
                <x-dot.readout label="Experiences recorded" :value="$this->figures['total']" />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout label="Problems fixed" :value="$this->figures['fixed']" tone="ok" />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout
                    label="Being watched now"
                    :value="$this->figures['watching']"
                    :tone="$this->figures['watching'] > 0 ? 'warn' : null"
                />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout label="Platforms covered" :value="$this->figures['platforms']" :pad="2" />
            </x-dot.panel>
        </x-dot.panels>

        <x-dot.panels cols="split">
            <x-dot.panel
                title="Recently learned"
                action-label="Browse all →"
                :action-href="route('knowledge.index')"
            >
                <ul class="dot-list dot-list--flush">
                    @foreach ($this->recentKnowledge as $entry)
                        <li>
                            <a class="dot-list__row" href="{{ route('knowledge.show', $entry['incident']->incident_uid) }}">
                                <span class="dot-list__title">
                                    {{ $entry['human']['headline'] }}
                                    <x-dot.status
                                        :label="$entry['human']['status_label']"
                                        :tone="$entry['human']['status_label'] === 'Fixed' ? 'ok' : 'info'"
                                    />
                                    @if ($entry['occurrences'] > 1)
                                        <span class="dot-list__repeat">{{ $entry['occurrences'] }}&times;</span>
                                    @endif
                                </span>
                                <span class="dot-list__body">{{ $entry['human']['what_was_learned'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-dot.panel>

            <x-dot.panel title="How this memory is used">
                <x-dot.readout
                    label="Lookups this week"
                    :value="$this->brainUsage['lookups_week']"
                    tone="signal"
                    context="Times Dot.Brain consulted this archive before deciding how to respond to a live problem."
                />
                <p class="dot-note dot-note--faint">
                    @if ($this->brainUsage['last_lookup'] !== null)
                        Last consulted {{ \Illuminate\Support\Carbon::parse($this->brainUsage['last_lookup'])->diffForHumans() }}
                    @else
                        Not consulted yet &mdash; the first live problem will change that.
                    @endif
                </p>

                <ul class="dot-links dot-links--spaced">
                    <li><a href="{{ route('knowledge.patterns') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">repeat</span>Patterns that keep recurring</a></li>
                    <li><a href="{{ route('knowledge.timeline') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">timeline</span>How knowledge has grown</a></li>
                    <li><a href="{{ route('reliability.index') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">speed</span>Storage reliability</a></li>
                </ul>
            </x-dot.panel>
        </x-dot.panels>
        </div>
    @endif
</div>
