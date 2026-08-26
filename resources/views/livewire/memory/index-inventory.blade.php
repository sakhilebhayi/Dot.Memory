<x-dot.panel title="Index inventory" flush>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin">progress_activity</span>
    </div>
    <div wire:loading.remove.delay class="dot-scroll-x">
        <table class="dot-ledger">
            <thead>
                <tr>
                    <th scope="col">Index</th>
                    <th scope="col">Type</th>
                    <th scope="col">Version</th>
                    <th scope="col">Tier</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="dot-ledger__num-head">Entries</th>
                    <th scope="col">Last rebuilt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->indexes as $index)
                    <tr>
                        <td class="dot-ledger__key">{{ $index->index_key }}</td>
                        <td>{{ $index->type }}</td>
                        <td class="dot-ledger__key">v{{ $index->version }}</td>
                        <td>{{ $index->storageTier->name ?? '—' }}</td>
                        <td>
                            <x-dot.status
                                :label="$index->status"
                                :tone="$index->status === 'active' ? 'ok' : 'warn'"
                            />
                        </td>
                        <td class="dot-ledger__num">{{ number_format($index->entry_count) }}</td>
                        <td class="dot-ledger__when">{{ $index->last_rebuilt_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="dot-ledger__empty">No indexes registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dot.panel>
