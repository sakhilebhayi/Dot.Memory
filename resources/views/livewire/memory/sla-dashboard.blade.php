<div class="dot-card dot-pad">
    @if ($this->canGovern() && $this->openEscalations->isNotEmpty())
        <div style="border:1px solid var(--bad);border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
            <h4 style="font-family:'IBM Plex Sans Condensed','IBM Plex Sans',system-ui,sans-serif;font-size:0.8rem;font-weight:700;color:var(--text);margin:0 0 0.75rem;">Escalations</h4>
            @foreach ($this->openEscalations as $escalation)
                <div style="padding:0.6rem 0;border-top:1px solid var(--rule);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <span style="color:var(--text);font-weight:600;font-size:0.82rem;">{{ $escalation->retrievalClass->class_key }}</span>
                            <span style="color:var(--text-quiet);font-size:0.72rem;margin-left:0.5rem;">{{ $escalation->breach_action }}</span>
                        </div>
                        <button wire:click="startAcknowledging({{ $escalation->id }})" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;">
                            Acknowledge
                        </button>
                    </div>
                    @if ($acknowledgingEscalationId === $escalation->id)
                        <div style="margin-top:0.5rem;">
                            <textarea wire:model="resolutionDetail" class="dot-input" rows="2" placeholder="What did you do about it? (required)"></textarea>
                            @error('resolutionDetail') <div style="color:var(--bad);font-size:10px;margin-top:4px;">{{ $message }}</div> @enderror
                            <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                                <button wire:click="confirmAcknowledge" class="dot-btn dot-btn-primary" style="font-size:10.5px;padding:5px 9px;">Confirm acknowledge</button>
                                <button wire:click="cancelAcknowledging" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <h3 class="dot-card__title dot-card__title--spaced">Retrieval SLA Attainment</h3>
    <div wire:loading.delay class="dot-loading-overlay">
        <span class="material-symbols-rounded dot-spin" class="dot-spinner-icon">progress_activity</span>
    </div>
    <div wire:loading.remove.delay style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        @forelse($this->classes as $class)
            @php
                $latest = $class->observations->first();
                $met = $latest?->sla_met;
                $color = $latest === null ? 'var(--text-faint)' : ($met ? 'var(--ok)' : 'var(--bad)');
            @endphp
            <div style="border:1px solid var(--rule);border-radius:10px;padding:1rem;background:var(--panel-raised);">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-quiet);">{{ $class->class_key }}</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">{{ $class->serves }}</div>

                <div style="display:flex;align-items:baseline;gap:6px;margin-top:0.75rem;">
                    <span class="metric-val" style="font-size:1.5rem;font-weight:600;color:{{ $color }};">
                        {{ $latest ? $latest->p95_latency_ms . 'ms' : '—' }}
                    </span>
                    <span style="font-size:11px;color:var(--text-faint);">p95 (target ≤ {{ $class->p95_target_ms }}ms)</span>
                </div>

                @if($class->p99_target_ms)
                <div style="font-size:11px;color:var(--text-quiet);margin-top:2px;">
                    p99 {{ $latest?->p99_latency_ms ?? '—' }}ms (target ≤ {{ $class->p99_target_ms }}ms)
                </div>
                @endif

                <div style="margin-top:0.75rem;display:flex;align-items:center;gap:6px;">
                    <span class="dot-badge" style="background:{{ $met === null ? 'var(--rule-strong)' : ($met ? 'var(--ok-quiet)' : 'var(--bad-quiet)') }};color:{{ $color }};">
                        {{ $met === null ? 'No data' : ($met ? 'Meeting contract' : 'Breach') }}
                    </span>
                    @if($latest?->degraded_mode_triggered)
                        <span class="dot-badge" style="background:var(--warn-quiet);color:var(--warn);">Degraded mode</span>
                    @endif
                </div>

                <div style="font-size:10px;color:var(--text-faint);margin-top:0.5rem;">On breach: {{ $class->breach_action }}</div>

                @if ($this->canGovern())
                    <button wire:click="startRecordingObservation({{ $class->id }})" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;margin-top:0.75rem;">
                        Record observation
                    </button>
                @endif

                @if ($recordingClassId === $class->id)
                    <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--rule);">
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="datetime-local" wire:model="observationWindowStart" class="dot-input" style="font-size:11px;">
                            <input type="datetime-local" wire:model="observationWindowEnd" class="dot-input" style="font-size:11px;">
                        </div>
                        @error('observationWindowStart') <div style="color:var(--bad);font-size:10px;">{{ $message }}</div> @enderror
                        @error('observationWindowEnd') <div style="color:var(--bad);font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="number" wire:model="observationRequestCount" placeholder="Requests" class="dot-input" style="width:90px;font-size:11px;">
                            <input type="number" wire:model="observationFailureCount" placeholder="Failures" class="dot-input" style="width:90px;font-size:11px;">
                        </div>
                        @error('observationRequestCount') <div style="color:var(--bad);font-size:10px;">{{ $message }}</div> @enderror
                        @error('observationFailureCount') <div style="color:var(--bad);font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="number" wire:model="observationP50" placeholder="p50 ms" class="dot-input" style="width:80px;font-size:11px;">
                            <input type="number" wire:model="observationP95" placeholder="p95 ms" class="dot-input" style="width:80px;font-size:11px;">
                            <input type="number" wire:model="observationP99" placeholder="p99 ms" class="dot-input" style="width:80px;font-size:11px;">
                        </div>
                        @error('observationP95') <div style="color:var(--bad);font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.5rem;">
                            <button wire:click="saveObservation" class="dot-btn dot-btn-primary" style="font-size:10.5px;padding:5px 9px;">Save</button>
                            <button wire:click="cancelRecordingObservation" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;">Cancel</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p style="font-size:0.8rem;color:var(--text-faint);">No retrieval classes configured yet.</p>
        @endforelse
    </div>
</div>
