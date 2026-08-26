<div>
    <x-dot.page
        title="Knowledge"
        lede="Everything the ecosystem has learned from running in production. Search it, filter it, reuse it."
    />

    @if ($this->totalArchived === 0)
        <x-dot.empty title="Nothing recorded yet">
            As the Dot platforms run, every production problem and its resolution is captured here automatically.
        </x-dot.empty>
    @else
        {{-- Search leads, because this is a knowledge platform: finding
             something is the primary act, not a filter on a table.
             Every control is labelled -- a placeholder vanishes the moment
             someone types, leaving a screen-reader user with an unnamed
             field halfway through a search. --}}
        <x-dot.card class="dot-filters">
            <div class="dot-field dot-field--grow">
                <label class="sr-only" for="knowledge-search">Search what we&rsquo;ve learned</label>
                <input
                    id="knowledge-search"
                    class="dot-input"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search what we&rsquo;ve learned &mdash; try &ldquo;production data&rdquo;"
                >
                <span class="dot-field__hint" wire:loading wire:target="search" aria-hidden="true">Searching&hellip;</span>
            </div>

            <div class="dot-field">
                <label class="sr-only" for="knowledge-platform">Filter by platform</label>
                <select id="knowledge-platform" class="dot-select" wire:model.live="platform">
                    <option value="">All platforms</option>
                    @foreach ($this->platformOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="dot-field">
                <label class="sr-only" for="knowledge-status">Filter by state</label>
                <select id="knowledge-status" class="dot-select" wire:model.live="status">
                    <option value="">Any state</option>
                    <option value="open">Being watched</option>
                    <option value="remediating">Fix in progress</option>
                    <option value="resolved">Fixed</option>
                    <option value="escalated">Needs a person</option>
                    <option value="rolled_back">Fix was undone</option>
                </select>
            </div>
        </x-dot.card>

        <p class="dot-result-count" aria-live="polite">
            {{ $this->entries->count() }} of {{ $this->totalArchived }} shown
        </p>

        @forelse ($this->entries as $entry)
            @php($human = $entry['human'])
            <x-dot.card class="dot-entry" :href="route('knowledge.show', $entry['incident']->incident_uid)">
                <span class="dot-list__title">
                    {{ $human['headline'] }}
                    <x-dot.status
                        :label="$human['status_label']"
                        :tone="$human['status_label'] === 'Fixed' ? 'ok' : ($human['status_label'] === 'Needs a person' ? 'bad' : 'info')"
                    />
                    <x-dot.status :label="$human['severity_label']" />
                </span>
                <span class="dot-list__body">{{ $human['what_was_learned'] }}</span>
                <span class="dot-list__meta">
                    {{ $entry['incident']->detected_at->diffForHumans() }} &middot; {{ $human['platform_label'] }}
                </span>
            </x-dot.card>
        @empty
            <x-dot.empty title="Nothing matches that search" icon="search_off">
                Try fewer words, or clear the filters to see everything the ecosystem has learned.
            </x-dot.empty>
        @endforelse
    @endif
</div>
