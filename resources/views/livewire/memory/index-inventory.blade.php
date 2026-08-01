<div class="dot-card" style="padding:1.5rem;">
    <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Index Inventory</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" style="font-size:22px;color:#818cf8;">progress_activity</span>
    </div>
    <div wire:loading.remove.delay style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="text-align:left;color:#71717a;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;">
                    <th style="padding:8px 10px;">Index</th>
                    <th style="padding:8px 10px;">Type</th>
                    <th style="padding:8px 10px;">Version</th>
                    <th style="padding:8px 10px;">Tier</th>
                    <th style="padding:8px 10px;">Status</th>
                    <th style="padding:8px 10px;">Entries</th>
                    <th style="padding:8px 10px;">Last rebuilt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->indexes as $index)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="padding:8px 10px;color:#f4f4f5;font-family:'JetBrains Mono',monospace;">{{ $index->index_key }}</td>
                        <td style="padding:8px 10px;color:#a1a1aa;">{{ $index->type }}</td>
                        <td style="padding:8px 10px;color:#a1a1aa;">v{{ $index->version }}</td>
                        <td style="padding:8px 10px;color:#a1a1aa;">{{ $index->storageTier->name ?? '—' }}</td>
                        <td style="padding:8px 10px;">
                            <span class="dot-badge" style="background:{{ $index->status === 'active' ? 'rgba(34,197,94,0.12)' : 'rgba(245,158,11,0.12)' }};color:{{ $index->status === 'active' ? '#22c55e' : '#f59e0b' }};">
                                {{ $index->status }}
                            </span>
                        </td>
                        <td style="padding:8px 10px;" class="metric-val">{{ number_format($index->entry_count) }}</td>
                        <td style="padding:8px 10px;color:#71717a;">{{ $index->last_rebuilt_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:1.5rem 10px;color:#52525b;text-align:center;">No indexes registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
