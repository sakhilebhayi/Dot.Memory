<x-dot.panel title="Durability outcomes" flush>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin">progress_activity</span>
    </div>
    <div wire:loading.remove.delay class="dot-scroll-x">
        <table class="dot-ledger">
            <thead>
                <tr>
                    <th scope="col">Tier</th>
                    <th scope="col">Check</th>
                    <th scope="col">Period</th>
                    <th scope="col" class="dot-ledger__num-head">Checked</th>
                    <th scope="col" class="dot-ledger__num-head">Integrity</th>
                    <th scope="col">Result</th>
                    <th scope="col">Verified</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->outcomes as $outcome)
                    <tr>
                        <td class="dot-ledger__key">{{ $outcome->storageTier->name ?? '—' }}</td>
                        <td>{{ str_replace('_', ' ', $outcome->check_type) }}</td>
                        <td class="dot-ledger__when">
                            {{ $outcome->audit_period_start->format('M d') }} – {{ $outcome->audit_period_end->format('M d, Y') }}
                        </td>
                        <td class="dot-ledger__num">{{ number_format($outcome->items_passed) }}/{{ number_format($outcome->items_checked) }}</td>
                        <td class="dot-ledger__num">{{ $outcome->integrity_score !== null ? number_format($outcome->integrity_score * 100, 2).'%' : '—' }}</td>
                        <td>
                            <x-dot.status
                                :label="ucfirst($outcome->result)"
                                :tone="match ($outcome->result) {
                                    'pass' => 'ok',
                                    'degraded' => 'warn',
                                    default => 'bad',
                                }"
                            />
                        </td>
                        <td class="dot-ledger__when">{{ $outcome->verified_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="dot-ledger__empty">No durability outcomes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dot.panel>
