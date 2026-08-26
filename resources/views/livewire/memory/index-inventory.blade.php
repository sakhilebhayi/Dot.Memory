<div class="dot-card dot-pad">
    <h3 class="dot-card__title dot-card__title--spaced">Index Inventory</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" class="dot-spinner-icon">progress_activity</span>
    </div>
    <div wire:loading.remove.delay class="dot-table-wrap">
        <table class="dot-table">
            <thead>
                <tr>
                    <th>Index</th>
                    <th>Type</th>
                    <th>Version</th>
                    <th>Tier</th>
                    <th>Status</th>
                    <th>Entries</th>
                    <th>Last rebuilt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->indexes as $index)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="color:#f4f4f5;font-family:'IBM Plex Mono',monospace;">{{ $index->index_key }}</td>
                        <td style="color:#a1a1aa;">{{ $index->type }}</td>
                        <td style="color:#a1a1aa;">v{{ $index->version }}</td>
                        <td style="color:#a1a1aa;">{{ $index->storageTier->name ?? '—' }}</td>
                        <td>
                            <span class="dot-badge" style="background:{{ $index->status === 'active' ? 'rgba(34,197,94,0.12)' : 'rgba(245,158,11,0.12)' }};color:{{ $index->status === 'active' ? '#22c55e' : '#f59e0b' }};">
                                {{ $index->status }}
                            </span>
                        </td>
                        <td style="padding:8px 10px;" class="metric-val">{{ number_format($index->entry_count) }}</td>
                        <td style="color:#71717a;">{{ $index->last_rebuilt_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:1.5rem 10px;color:#52525b;text-align:center;">No indexes registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
