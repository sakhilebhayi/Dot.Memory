<div>
    <x-dot.page
        title="Patterns"
        lede="A pattern is a problem that has a history. Open one to see every time it happened, what was done, and whether that worked."
    >
        <x-slot:actions>
            <div class="dot-segmented" role="group" aria-label="Which patterns to show">
                <button
                    type="button"
                    wire:click="$set('filter', 'all')"
                    class="dot-segmented__btn @if($filter === 'all') is-on @endif"
                    @if($filter === 'all') aria-current="true" @endif
                >Everything</button>
                <button
                    type="button"
                    wire:click="$set('filter', 'recurring')"
                    class="dot-segmented__btn @if($filter === 'recurring') is-on @endif"
                    @if($filter === 'recurring') aria-current="true" @endif
                >Recurring only</button>
            </div>
        </x-slot:actions>
    </x-dot.page>

    @if ($this->patterns->isEmpty())
        <x-dot.empty title="{{ $filter === 'recurring' ? 'Nothing has happened twice yet' : 'No patterns recorded yet' }}">
            @if ($filter === 'recurring')
                Every problem so far has been a one-off. That is the good outcome &mdash; a pattern
                appears here the moment something happens a second time.
            @else
                As the Dot platforms run, each problem they hit is grouped with its earlier
                occurrences here, so a fix that has worked before is easy to find.
            @endif
        </x-dot.empty>
    @else
        <div class="dot-panels dot-panels--1">
            <div class="dot-scroll-x">
                <table class="dot-ledger dot-ledger--patterns">
                    <caption class="sr-only">
                        Patterns ordered by how often they have occurred. Select a pattern to see its occurrences.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Expand</span></th>
                            <th scope="col">Pattern</th>
                            <th scope="col">Platform</th>
                            <th scope="col">Last 12 weeks</th>
                            <th scope="col" class="dot-ledger__num-head">Seen</th>
                            <th scope="col" class="dot-ledger__num-head">Fixed</th>
                            <th scope="col">Last</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->patterns as $pattern)
                            @php $isOpen = $expanded === $pattern['key']; @endphp
                            <tr class="dot-ledger__row--expandable @if($isOpen) dot-ledger__row--open @endif">
                                <td class="dot-ledger__toggle">
                                    <button
                                        type="button"
                                        wire:click="toggle('{{ $pattern['key'] }}')"
                                        class="dot-ledger__toggle-btn"
                                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                    >
                                        <span class="material-symbols-rounded dot-ledger__caret" aria-hidden="true">chevron_right</span>
                                        <span class="sr-only">{{ $isOpen ? 'Hide' : 'Show' }} occurrences of {{ $pattern['headline'] }}</span>
                                    </button>
                                </td>
                                <td>
                                    <span class="dot-ledger__headline">{{ $pattern['headline'] }}</span>
                                    @if ($pattern['unresolved'] > 0)
                                        <x-dot.status label="{{ $pattern['unresolved'] }} open" tone="warn" />
                                    @endif
                                    <span class="dot-ledger__sub">{{ $pattern['trust']['label'] }}</span>
                                </td>
                                <td class="dot-ledger__key">{{ $pattern['platform'] }}</td>
                                <td>
                                    <div class="dot-spark" role="img"
                                         aria-label="Occurred {{ $pattern['occurrences'] }} times over the last 12 weeks">
                                        @foreach ($pattern['marks'] as $mark)
                                            <span
                                                class="dot-spark__mark @if($mark['height'] === 0) dot-spark__mark--empty @endif"
                                                style="height:{{ max(2, $mark['height']) }}px"
                                            ></span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="dot-ledger__num">{{ $pattern['occurrences'] }}</td>
                                <td class="dot-ledger__num">{{ $pattern['resolved'] }}</td>
                                <td class="dot-ledger__when">{{ $pattern['last_seen']->diffForHumans() }}</td>
                            </tr>

                            @if ($isOpen)
                                {{-- Expanded in place: the rows above stay on screen, which is
                                     the whole point of opening this one. --}}
                                <tr class="dot-ledger__detail">
                                    <td colspan="7">
                                        <div class="dot-ledger__detail-inner">
                                            <p class="dot-ledger__learned">{{ $pattern['learned'] }}</p>
                                            <p class="dot-ledger__trust">{{ $pattern['trust']['explanation'] }}</p>

                                            <h3 class="dot-ledger__evidence-title">
                                                Every time this happened
                                            </h3>
                                            <ol class="dot-occurrences">
                                                @foreach ($this->occurrences as $occurrence)
                                                    <li class="dot-occurrence">
                                                        <time class="dot-occurrence__when"
                                                              datetime="{{ $occurrence['detected_at']->toIso8601String() }}">
                                                            {{ $occurrence['detected_at']->format('d M Y · H:i') }}
                                                        </time>
                                                        <div class="dot-occurrence__body">
                                                            <p class="dot-occurrence__meta">
                                                                <x-dot.status
                                                                    :label="$occurrence['status_label']"
                                                                    :tone="$occurrence['status'] === 'resolved' ? 'ok' : 'warn'"
                                                                />
                                                                <span class="dot-occurrence__took">{{ $occurrence['took'] }}</span>
                                                                @if ($occurrence['rolled_back'])
                                                                    <span class="dot-occurrence__flag">fix rolled back</span>
                                                                @endif
                                                                <a href="{{ route('knowledge.show', $occurrence['uid']) }}">Full record &rarr;</a>
                                                            </p>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Beside the ledger, not in it: neither of these is a pattern. One is
         a problem with no ending yet, the other is an absence. --}}
    <div class="dot-panels dot-panels--2 dot-panels--secondary">
        <x-dot.panel title="Waiting too long">
            @if ($this->longWatches->isEmpty())
                <p class="dot-note">Nothing has been open for more than a day.</p>
            @else
                <ul class="dot-links">
                    @foreach ($this->longWatches as $watch)
                        <li>
                            <a href="{{ route('knowledge.show', $watch['uid']) }}">
                                <span class="material-symbols-rounded" aria-hidden="true">hourglass_top</span>
                                <span>{{ $watch['headline'] }}</span>
                                <span class="dot-links__meta">{{ $watch['since'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-dot.panel>

        <x-dot.panel title="What is not covered">
            @if (empty($this->coverage['uncovered']))
                <p class="dot-note">Every active platform has contributed knowledge here.</p>
            @else
                <p class="dot-note">
                    These platforms are running but have recorded nothing yet, so this archive
                    cannot answer questions about them:
                </p>
                <ul class="dot-tags">
                    @foreach (array_slice($this->coverage['uncovered'], 0, 8) as $platform)
                        <li class="dot-tag">{{ $platform }}</li>
                    @endforeach
                    @if (count($this->coverage['uncovered']) > 8)
                        <li class="dot-tag dot-tag--more">
                            +{{ count($this->coverage['uncovered']) - 8 }} more
                        </li>
                    @endif
                </ul>
            @endif
        </x-dot.panel>
    </div>
</div>
