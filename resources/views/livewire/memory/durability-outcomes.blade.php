<div class="dot-card" style="padding:1.5rem;">
    <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Durability Outcomes</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" style="font-size:22px;color:#818cf8;">progress_activity</span>
    </div>
    <div wire:loading.remove.delay style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="text-align:left;color:#71717a;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;">
                    <th style="padding:8px 10px;">Tier</th>
                    <th style="padding:8px 10px;">Check</th>
                    <th style="padding:8px 10px;">Period</th>
                    <th style="padding:8px 10px;">Checked</th>
                    <th style="padding:8px 10px;">Integrity</th>
                    <th style="padding:8px 10px;">Result</th>
                    <th style="padding:8px 10px;">Verified</th>
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
                        <td style="padding:8px 10px;color:#f4f4f5;">{{ $outcome->storageTier->name ?? '—' }}</td>
                        <td style="padding:8px 10px;color:#a1a1aa;">{{ str_replace('_', ' ', $outcome->check_type) }}</td>
                        <td style="padding:8px 10px;color:#71717a;font-size:12px;">
                            {{ $outcome->audit_period_start->format('M d') }} – {{ $outcome->audit_period_end->format('M d, Y') }}
                        </td>
                        <td style="padding:8px 10px;" class="metric-val">{{ number_format($outcome->items_passed) }}/{{ number_format($outcome->items_checked) }}</td>
                        <td style="padding:8px 10px;" class="metric-val">{{ $outcome->integrity_score !== null ? number_format($outcome->integrity_score * 100, 2) . '%' : '—' }}</td>
                        <td style="padding:8px 10px;">
                            <span class="dot-badge" style="background:{{ $color }}1f;color:{{ $color }};">{{ ucfirst($outcome->result) }}</span>
                        </td>
                        <td style="padding:8px 10px;color:#71717a;">{{ $outcome->verified_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:1.5rem 10px;color:#52525b;text-align:center;">No durability outcomes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
