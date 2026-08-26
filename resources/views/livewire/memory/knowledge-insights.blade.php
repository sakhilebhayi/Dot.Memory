<div>
    <x-dot.page
        title="Insights"
        lede="What the archive says when you step back: patterns, slow burns, and where the ecosystem is still flying blind."
    />

    <div class="dot-grid dot-grid--pair">
        <div class="dot-stack">
            <x-dot.card class="dot-pad" title="Recurring problems">
                @forelse ($this->recurringProblems as $problem)
                    {{-- A button, not a link: this opens a dialog in place
                         rather than navigating, and the element should say
                         so to anyone using a keyboard or screen reader. --}}
                    <button type="button" class="dot-insight" wire:click="inspect('{{ $problem['latest_uid'] }}')">
                        <span class="dot-insight__title">{{ $problem['headline'] }}</span>
                        <span class="dot-insight__meta">
                            Seen {{ $problem['occurrences'] }} times, fixed {{ $problem['fixed'] }}
                            &mdash; <span class="dot-insight__verdict">{{ $problem['trust']['label'] }}</span>
                        </span>
                    </button>
                @empty
                    <p class="dot-note">No problem has repeated itself yet &mdash; every recorded experience so far has been a first.</p>
                @endforelse
            </x-dot.card>

            <x-dot.card class="dot-pad" title="Waiting too long">
                @forelse ($this->longWatches as $watch)
                    <a class="dot-insight" href="{{ route('knowledge.show', $watch['uid']) }}">
                        <span class="dot-insight__title dot-insight__title--warn">{{ $watch['headline'] }}</span>
                        <span class="dot-insight__meta">Unresolved since {{ $watch['since'] }} &mdash; deserves a look.</span>
                    </a>
                @empty
                    <p class="dot-note">Nothing has been stuck for more than a day.</p>
                @endforelse
            </x-dot.card>
        </div>

        <x-dot.card class="dot-pad" title="Where the ecosystem is covered">
            @if (count($this->coverage['covered']) > 0)
                <p class="dot-note dot-note--faint">Contributing operational knowledge:</p>
                <div class="dot-chips">
                    @foreach ($this->coverage['covered'] as $name)
                        <x-dot.status :label="$name" tone="ok" icon="check" />
                    @endforeach
                </div>
            @endif

            @if (count($this->coverage['uncovered']) > 0)
                <p class="dot-note dot-note--faint">Not yet watched &mdash; the ecosystem has no operational memory of these:</p>
                <div class="dot-chips">
                    @foreach ($this->coverage['uncovered'] as $name)
                        <x-dot.status :label="$name" />
                    @endforeach
                </div>
                <p class="dot-note dot-note--faint">
                    Each platform joins by enrolling with the guardian &mdash; one manifest file and a health endpoint.
                </p>
            @endif
        </x-dot.card>
    </div>

    <x-dot.modal name="insight-detail" title="Insight detail">
        @if ($this->inspected !== null)
            @php($i = $this->inspected)
            <h3 class="dot-lede-label">What was detected</h3>
            <p class="dot-prose">{{ $i['human']['headline'] }}</p>

            <h3 class="dot-lede-label dot-lede-label--spaced">Why it matters</h3>
            <p class="dot-prose">{{ $i['human']['why_it_matters'] }}</p>

            <h3 class="dot-lede-label dot-lede-label--spaced">Supporting evidence</h3>
            <p class="dot-prose">
                Seen {{ $i['occurrences'] }} times, resolved {{ $i['resolved'] }}.
                @if ($i['first_seen'])
                    First recorded {{ \Illuminate\Support\Carbon::parse($i['first_seen'])->diffForHumans() }}.
                @endif
            </p>

            <h3 class="dot-lede-label dot-lede-label--spaced">How much to trust this</h3>
            <p class="dot-prose"><strong>{{ $i['human']['trust']['label'] }}</strong> &mdash; {{ $i['human']['trust']['explanation'] }}</p>

            <h3 class="dot-lede-label dot-lede-label--spaced">What was learned</h3>
            <p class="dot-prose">{{ $i['human']['what_was_learned'] }}</p>
        @else
            <p class="dot-prose">That record could not be loaded.</p>
        @endif

        <x-slot:footer>
            @if ($this->inspected !== null)
                <a class="dot-btn" href="{{ route('knowledge.show', $this->inspected['uid']) }}">Open the full record</a>
            @endif
        </x-slot:footer>
    </x-dot.modal>
</div>
