<div class="dot-card dot-pad">
    <h3 class="dot-card__title dot-card__title--spaced">Durability Outcomes</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" class="dot-spinner-icon">progress_activity</span>
    </div>
    <div wire:loading.remove.delay class="dot-table-wrap">
        <table class="dot-table">
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Check</th>
                    <th>Period</th>
                    <th>Checked</th>
                    <th>Integrity</th>
                    <th>Result</th>
                    <th>Verified</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->outcomes as $outcome)
                    @php
                        $color = match($outcome->result) {
                            'pass' => '#22c55e',
                            'degraded' => '#f59e0b',
                            default => '#ef4444',
                        };
                    @endphp
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="color:#f4f4f5;">{{ $outcome->storageTier->name ?? '—' }}</td>
                        <td style="color:#a1a1aa;">{{ str_replace('_', ' ', $outcome->check_type) }}</td>
                        <td style="color:#71717a;font-size:12px;">
                            {{ $outcome->audit_period_start->format('M d') }} – {{ $outcome->audit_period_end->format('M d, Y') }}
                        </td>
                        <td style="padding:8px 10px;" class="metric-val">{{ number_format($outcome->items_passed) }}/{{ number_format($outcome->items_checked) }}</td>
                        <td style="padding:8px 10px;" class="metric-val">{{ $outcome->integrity_score !== null ? number_format($outcome->integrity_score * 100, 2) . '%' : '—' }}</td>
                        <td>
                            <span class="dot-badge" style="background:{{ $color }}1f;color:{{ $color }};">{{ ucfirst($outcome->result) }}</span>
                        </td>
                        <td style="color:#71717a;">{{ $outcome->verified_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:1.5rem 10px;color:#52525b;text-align:center;">No durability outcomes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
