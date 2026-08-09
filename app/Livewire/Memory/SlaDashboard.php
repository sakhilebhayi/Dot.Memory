<?php

namespace App\Livewire\Memory;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Services\RetrievalSlaEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Renders current SLA attainment per retrieval class (wiki.md §5).
 *
 * Reads only aggregated RetrievalObservation rows — never anything
 * about what was retrieved. Recording a new observation and
 * acknowledging an escalation are both gated by canGovern() — the
 * team-admin role standing in for wiki.md §7's undocumented "SRE Lead"
 * identity, until a dedicated ecosystem-wide role exists.
 */
class SlaDashboard extends Component
{
    public ?int $recordingClassId = null;

    public string $observationWindowStart = '';

    public string $observationWindowEnd = '';

    public string $observationRequestCount = '';

    public string $observationFailureCount = '';

    public string $observationP50 = '';

    public string $observationP95 = '';

    public string $observationP99 = '';

    public ?int $acknowledgingEscalationId = null;

    public string $resolutionDetail = '';

    #[Computed]
    public function classes(): Collection
    {
        return RetrievalClass::with(['storageTier', 'observations' => function ($query) {
            $query->orderByDesc('window_end')->limit(8);
        }])->orderBy('class_key')->get();
    }

    #[Computed]
    public function openEscalations(): Collection
    {
        return RetrievalSlaEscalation::query()
            ->where('status', 'open')
            ->with('retrievalClass')
            ->latest('detected_at')
            ->get();
    }

    public function canGovern(): bool
    {
        $user = Auth::user();
        $team = $user?->currentTeam;

        return $user && $team && $user->hasTeamRole($team, 'admin');
    }

    public function startRecordingObservation(int $classId): void
    {
        abort_unless($this->canGovern(), 403);

        RetrievalClass::findOrFail($classId);

        $this->recordingClassId = $classId;
        $this->observationWindowStart = '';
        $this->observationWindowEnd = '';
        $this->observationRequestCount = '';
        $this->observationFailureCount = '';
        $this->observationP50 = '';
        $this->observationP95 = '';
        $this->observationP99 = '';
    }

    public function cancelRecordingObservation(): void
    {
        $this->recordingClassId = null;
    }

    public function saveObservation(): void
    {
        abort_unless($this->canGovern(), 403);

        $class = RetrievalClass::findOrFail($this->recordingClassId);

        $this->validate([
            'observationWindowStart' => ['required', 'date'],
            'observationWindowEnd' => ['required', 'date', 'after_or_equal:observationWindowStart'],
            'observationRequestCount' => ['required', 'integer', 'min:0'],
            'observationFailureCount' => ['required', 'integer', 'min:0'],
            'observationP50' => ['nullable', 'integer', 'min:0'],
            'observationP95' => ['required', 'integer', 'min:0'],
            'observationP99' => ['nullable', 'integer', 'min:0'],
        ]);

        $metrics = [
            'p95_latency_ms' => (int) $this->observationP95,
            'p99_latency_ms' => $this->observationP99 === '' ? null : (int) $this->observationP99,
            'failure_count' => (int) $this->observationFailureCount,
        ];

        $met = app(RetrievalSlaEvaluator::class)->meetsContract($class, $metrics);

        RetrievalObservation::create([
            'retrieval_class_id' => $class->id,
            'window_start' => $this->observationWindowStart,
            'window_end' => $this->observationWindowEnd,
            'request_count' => $this->observationRequestCount,
            'failure_count' => $metrics['failure_count'],
            'p50_latency_ms' => $this->observationP50 === '' ? null : (int) $this->observationP50,
            'p95_latency_ms' => $metrics['p95_latency_ms'],
            'p99_latency_ms' => $metrics['p99_latency_ms'],
            'sla_met' => $met,
            'degraded_mode_triggered' => ! $met,
        ]);

        $this->recordingClassId = null;

        unset($this->classes);
    }

    public function startAcknowledging(int $escalationId): void
    {
        abort_unless($this->canGovern(), 403);

        RetrievalSlaEscalation::findOrFail($escalationId);

        $this->acknowledgingEscalationId = $escalationId;
        $this->resolutionDetail = '';
    }

    public function cancelAcknowledging(): void
    {
        $this->acknowledgingEscalationId = null;
    }

    public function confirmAcknowledge(): void
    {
        abort_unless($this->canGovern(), 403);

        $this->validate([
            'resolutionDetail' => ['required', 'string', 'max:2000'],
        ]);

        $escalation = RetrievalSlaEscalation::findOrFail($this->acknowledgingEscalationId);

        $escalation->update([
            'status' => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'resolution_detail' => $this->resolutionDetail,
            'acknowledged_at' => now(),
        ]);

        $this->acknowledgingEscalationId = null;
        $this->resolutionDetail = '';

        unset($this->openEscalations);
    }

    public function render()
    {
        return view('livewire.memory.sla-dashboard');
    }
}
