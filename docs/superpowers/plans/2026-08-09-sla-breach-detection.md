# SLA-Breach Detection & Escalation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement wiki.md §5's missing SLA-contract evaluation logic: a shared evaluator used by
both a new admin-only observation-recording form and this platform's first scheduled job, which
daily re-evaluates the latest observation per retrieval class and escalates governance-tier
breaches for admin review.

**Architecture:** One pure evaluation service (`RetrievalSlaEvaluator`) is the single source of
truth for "does this observation meet its class's contract." The recording form calls it at
creation time; the scheduled command calls it to keep already-recorded observations consistent
with the class's *current* contract, and raises a `RetrievalSlaEscalation` for the two
governance-tier classes on a fresh breach.

**Tech Stack:** Laravel (per repo's existing conventions), Livewire, PHPUnit, Jetstream Teams.

## Global Constraints

- `RetrievalSlaEvaluator::meetsContract()` is the only place contract-vs-metrics comparison logic
  lives — spec §1.
- Recording and Acknowledge actions are both gated by `hasTeamRole($team, 'admin')` — this repo's
  real role vocabulary (`app/Providers/JetstreamServiceProvider.php`: exactly `admin`/`editor`).
- The scan command mutates only `sla_met`/`degraded_mode_triggered` on existing
  `RetrievalObservation` rows — no other field, no deletion, no new observation rows.
- Escalations are raised only for classes where `completeness_required` or `zero_loss_required` is
  `true` (`retr:audit:warm`, `retr:archive:cold`) — never the other two classes.
- An already-`open` escalation refreshes `retrieval_observation_id` on rescan but preserves its
  original `detected_at`; a recovered class's open escalation is never auto-cleared.
- The free-text acknowledgment field is named `resolution_detail`, not `notes`/`review_notes` —
  this repo's `tests/Unit/StoreWithoutReadingInvariantTest.php` forbids field names containing
  `note`, `comment`, `description`, and several other content-shaped fragments. `RetrievalSlaEscalation`
  must be added to that test's coverage.
- No editing/deleting recorded observations or escalations after creation — append-only.

---

## Task 1: `RetrievalSlaEvaluator` service

**Files:**
- Create: `app/Services/RetrievalSlaEvaluator.php`
- Test: `tests/Unit/RetrievalSlaEvaluatorTest.php`

**Interfaces:**
- Consumes: `App\Models\RetrievalClass` (existing, unmodified — `p95_target_ms`,
  `p99_target_ms`, `zero_loss_required` attributes).
- Produces: `App\Services\RetrievalSlaEvaluator::meetsContract(RetrievalClass $class, array
  $metrics): bool`, where `$metrics` is `['p95_latency_ms' => int, 'p99_latency_ms' => ?int,
  'failure_count' => int]`. Consumed by Task 2 (recording form) and Task 4 (scan command).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Models\RetrievalClass;
use App\Models\StorageTier;
use App\Services\RetrievalSlaEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrievalSlaEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_p95_breach_fails_regardless_of_other_metrics(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        $met = (new RetrievalSlaEvaluator)->meetsContract($class, [
            'p95_latency_ms' => 900,
            'p99_latency_ms' => 1500,
            'failure_count' => 0,
        ]);

        $this->assertFalse($met);
    }

    public function test_a_p99_breach_fails_only_when_the_class_has_a_p99_target(): void
    {
        $withTarget = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);
        $withoutTarget = $this->makeClass(['p95_target_ms' => 1500, 'p99_target_ms' => null, 'zero_loss_required' => false]);

        $metWithTarget = (new RetrievalSlaEvaluator)->meetsContract($withTarget, [
            'p95_latency_ms' => 500,
            'p99_latency_ms' => 2500,
            'failure_count' => 0,
        ]);

        $metWithoutTarget = (new RetrievalSlaEvaluator)->meetsContract($withoutTarget, [
            'p95_latency_ms' => 500,
            'p99_latency_ms' => 2500,
            'failure_count' => 0,
        ]);

        $this->assertFalse($metWithTarget);
        $this->assertTrue($metWithoutTarget);
    }

    public function test_a_zero_loss_class_fails_on_any_failure_even_with_good_latency(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 86_400_000, 'p99_target_ms' => null, 'zero_loss_required' => true]);

        $met = (new RetrievalSlaEvaluator)->meetsContract($class, [
            'p95_latency_ms' => 100,
            'p99_latency_ms' => null,
            'failure_count' => 1,
        ]);

        $this->assertFalse($met);
    }

    public function test_a_non_zero_loss_class_tolerates_failures(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        $met = (new RetrievalSlaEvaluator)->meetsContract($class, [
            'p95_latency_ms' => 100,
            'p99_latency_ms' => 200,
            'failure_count' => 50,
        ]);

        $this->assertTrue($met);
    }

    public function test_a_fully_compliant_observation_meets_contract(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => true]);

        $met = (new RetrievalSlaEvaluator)->meetsContract($class, [
            'p95_latency_ms' => 620,
            'p99_latency_ms' => 1500,
            'failure_count' => 0,
        ]);

        $this->assertTrue($met);
    }

    private function makeClass(array $overrides): RetrievalClass
    {
        $tier = StorageTier::create([
            'code' => 'hot-'.uniqid(),
            'name' => 'Hot',
            'latency_target_ms' => 50,
            'backing' => 'Graph store + cache projections',
        ]);

        return RetrievalClass::create(array_merge([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Test Class',
            'serves' => 'Test consumer',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => 2000,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Test action',
        ], $overrides));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/RetrievalSlaEvaluatorTest.php`
Expected: FAIL — class `App\Services\RetrievalSlaEvaluator` not found.

- [ ] **Step 3: Write the evaluator**

```php
<?php

namespace App\Services;

use App\Models\RetrievalClass;

/**
 * The single place wiki.md §5's Retrieval SLA Contract is evaluated
 * against a window's raw metrics — used identically by the recording
 * form (app/Livewire/Memory/SlaDashboard.php) and the scheduled scan
 * (app/Console/Commands/ScanSlaBreaches.php), see
 * docs/superpowers/specs/2026-08-09-sla-breach-detection-design.md.
 *
 * `completeness_required` has no corresponding measured field on
 * RetrievalObservation today, so it deliberately does not factor into
 * this computation — only what is genuinely measurable does.
 */
class RetrievalSlaEvaluator
{
    /**
     * @param  array{p95_latency_ms: int, p99_latency_ms: ?int, failure_count: int}  $metrics
     */
    public function meetsContract(RetrievalClass $class, array $metrics): bool
    {
        if ($metrics['p95_latency_ms'] > $class->p95_target_ms) {
            return false;
        }

        if ($class->p99_target_ms !== null
            && $metrics['p99_latency_ms'] !== null
            && $metrics['p99_latency_ms'] > $class->p99_target_ms) {
            return false;
        }

        if ($class->zero_loss_required && $metrics['failure_count'] > 0) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/RetrievalSlaEvaluatorTest.php`
Expected: 5 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/RetrievalSlaEvaluator.php tests/Unit/RetrievalSlaEvaluatorTest.php
git commit -m "feat(memory): add RetrievalSlaEvaluator — the single source of truth for contract evaluation"
```

---

## Task 2: Recording form on `SlaDashboard`

**Files:**
- Modify: `app/Livewire/Memory/SlaDashboard.php`
- Modify: `resources/views/livewire/memory/sla-dashboard.blade.php`
- Test: `tests/Feature/Memory/RetrievalObservationRecordingTest.php`

**Interfaces:**
- Consumes: `App\Services\RetrievalSlaEvaluator::meetsContract()` (Task 1).
- Produces: `SlaDashboard::canGovern(): bool`, `::startRecordingObservation(int $classId): void`,
  `::cancelRecordingObservation(): void`, `::saveObservation(): void` — public Livewire API;
  public properties `$recordingClassId`, `$observationWindowStart`, `$observationWindowEnd`,
  `$observationRequestCount`, `$observationFailureCount`, `$observationP50`, `$observationP95`,
  `$observationP99`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\SlaDashboard;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RetrievalObservationRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_record_a_compliant_observation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->set('observationWindowStart', now()->subWeek()->toDateTimeString())
            ->set('observationWindowEnd', now()->toDateTimeString())
            ->set('observationRequestCount', '10000')
            ->set('observationFailureCount', '0')
            ->set('observationP50', '200')
            ->set('observationP95', '620')
            ->set('observationP99', '1500')
            ->call('saveObservation');

        $this->assertDatabaseHas('retrieval_observations', [
            'retrieval_class_id' => $class->id,
            'sla_met' => true,
            'degraded_mode_triggered' => false,
        ]);
    }

    public function test_an_admin_can_record_a_breaching_observation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->set('observationWindowStart', now()->subWeek()->toDateTimeString())
            ->set('observationWindowEnd', now()->toDateTimeString())
            ->set('observationRequestCount', '10000')
            ->set('observationFailureCount', '0')
            ->set('observationP50', '900')
            ->set('observationP95', '1200')
            ->set('observationP99', '2500')
            ->call('saveObservation');

        $this->assertDatabaseHas('retrieval_observations', [
            'retrieval_class_id' => $class->id,
            'sla_met' => false,
            'degraded_mode_triggered' => true,
        ]);
    }

    public function test_a_non_admin_cannot_record_an_observation(): void
    {
        $member = User::factory()->create(['current_team_id' => null]);
        $class = $this->makeClass([]);

        Livewire::actingAs($member)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->assertForbidden();

        $this->assertSame(0, RetrievalObservation::count());
    }

    private function makeClass(array $overrides): RetrievalClass
    {
        $tier = StorageTier::create([
            'code' => 'hot-'.uniqid(),
            'name' => 'Hot',
            'latency_target_ms' => 50,
            'backing' => 'Graph store + cache projections',
        ]);

        return RetrievalClass::create(array_merge([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Test Class',
            'serves' => 'Test consumer',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => 2000,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Test action',
        ], $overrides));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Memory/RetrievalObservationRecordingTest.php`
Expected: FAIL — `startRecordingObservation`/`saveObservation` methods don't exist.

- [ ] **Step 3: Implement the Livewire component changes**

Read `app/Livewire/Memory/SlaDashboard.php` first, then replace its full contents:

```php
<?php

namespace App\Livewire\Memory;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
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

    #[Computed]
    public function classes(): Collection
    {
        return RetrievalClass::with(['storageTier', 'observations' => function ($query) {
            $query->orderByDesc('window_end')->limit(8);
        }])->orderBy('class_key')->get();
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

    public function render()
    {
        return view('livewire.memory.sla-dashboard');
    }
}
```

- [ ] **Step 4: Add the recording form to the view**

Read `resources/views/livewire/memory/sla-dashboard.blade.php` first. Inside the `@forelse($this->classes as $class)` loop, immediately before the closing `</div>` of each class card (the `border:1px solid rgba(255,255,255,0.07);` card, right after the existing `On breach:` line), add:

```blade
                @if ($this->canGovern())
                    <button wire:click="startRecordingObservation({{ $class->id }})" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;margin-top:0.75rem;">
                        Record observation
                    </button>
                @endif

                @if ($recordingClassId === $class->id)
                    <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid rgba(255,255,255,0.06);">
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="datetime-local" wire:model="observationWindowStart" class="dot-input" style="font-size:11px;">
                            <input type="datetime-local" wire:model="observationWindowEnd" class="dot-input" style="font-size:11px;">
                        </div>
                        @error('observationWindowStart') <div style="color:#ef4444;font-size:10px;">{{ $message }}</div> @enderror
                        @error('observationWindowEnd') <div style="color:#ef4444;font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="number" wire:model="observationRequestCount" placeholder="Requests" class="dot-input" style="width:90px;font-size:11px;">
                            <input type="number" wire:model="observationFailureCount" placeholder="Failures" class="dot-input" style="width:90px;font-size:11px;">
                        </div>
                        @error('observationRequestCount') <div style="color:#ef4444;font-size:10px;">{{ $message }}</div> @enderror
                        @error('observationFailureCount') <div style="color:#ef4444;font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                            <input type="number" wire:model="observationP50" placeholder="p50 ms" class="dot-input" style="width:80px;font-size:11px;">
                            <input type="number" wire:model="observationP95" placeholder="p95 ms" class="dot-input" style="width:80px;font-size:11px;">
                            <input type="number" wire:model="observationP99" placeholder="p99 ms" class="dot-input" style="width:80px;font-size:11px;">
                        </div>
                        @error('observationP95') <div style="color:#ef4444;font-size:10px;">{{ $message }}</div> @enderror
                        <div style="display:flex;gap:0.5rem;">
                            <button wire:click="saveObservation" class="dot-btn dot-btn-primary" style="font-size:10.5px;padding:5px 9px;">Save</button>
                            <button wire:click="cancelRecordingObservation" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;">Cancel</button>
                        </div>
                    </div>
                @endif
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Memory/RetrievalObservationRecordingTest.php`
Expected: 3 passed.

- [ ] **Step 6: Re-run the pre-existing dashboard test**

Run: `php artisan test --compact tests/Feature/Memory/SlaDashboardTest.php`
Expected: all 4 still passing.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Memory/SlaDashboard.php resources/views/livewire/memory/sla-dashboard.blade.php tests/Feature/Memory/RetrievalObservationRecordingTest.php
git commit -m "feat(memory): admin-gated observation recording on the SLA dashboard"
```

---

## Task 3: `RetrievalSlaEscalation` model + migration + invariant coverage

**Files:**
- Create: `database/migrations/2026_08_09_130001_create_retrieval_sla_escalations_table.php`
- Create: `app/Models/RetrievalSlaEscalation.php`
- Modify: `tests/Unit/StoreWithoutReadingInvariantTest.php`
- Test: `tests/Unit/RetrievalSlaEscalationTest.php`

**Interfaces:**
- Consumes: `App\Models\RetrievalClass`, `App\Models\RetrievalObservation`, `App\Models\User`
  (all existing, unmodified).
- Produces: `App\Models\RetrievalSlaEscalation` with fillable `retrieval_class_id,
  retrieval_observation_id, breach_action, status, detected_at, acknowledged_by,
  resolution_detail, acknowledged_at`; relations `retrievalClass(): BelongsTo`,
  `retrievalObservation(): BelongsTo`, `acknowledgedBy(): BelongsTo`. Consumed by Task 4 (scan
  command creates/refreshes rows) and Task 5 (review UI reads/updates rows).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governance-tier SLA breach awaiting human acknowledgment (wiki.md
 * §5's "Escalate"/"Integrity incident" breach_action rows only —
 * retr:audit:warm and retr:archive:cold). Raised by
 * App\Console\Commands\ScanSlaBreaches, reviewed via
 * App\Livewire\Memory\SlaDashboard's Acknowledge action. Never
 * auto-executes anything — breach_action is a human instruction, not a
 * system action this app performs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retrieval_sla_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retrieval_class_id')->constrained('retrieval_classes')->cascadeOnDelete();
            $table->foreignId('retrieval_observation_id')->constrained('retrieval_observations')->cascadeOnDelete();
            $table->string('breach_action');
            $table->string('status')->default('open');
            $table->timestamp('detected_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_detail')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['retrieval_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_sla_escalations');
    }
};
```

- [ ] **Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A governance-tier SLA breach awaiting acknowledgment (wiki.md §5).
 * `resolution_detail` is named deliberately, not `notes`/`review_notes`
 * — see tests/Unit/StoreWithoutReadingInvariantTest.php, which forbids
 * content-shaped field names including "note"/"comment"/"description".
 */
class RetrievalSlaEscalation extends Model
{
    protected $fillable = [
        'retrieval_class_id',
        'retrieval_observation_id',
        'breach_action',
        'status',
        'detected_at',
        'acknowledged_by',
        'resolution_detail',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function retrievalClass(): BelongsTo
    {
        return $this->belongsTo(RetrievalClass::class);
    }

    public function retrievalObservation(): BelongsTo
    {
        return $this->belongsTo(RetrievalObservation::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
```

- [ ] **Step 3: Extend the structural invariant test**

Read `tests/Unit/StoreWithoutReadingInvariantTest.php` first. Add this import alongside the
existing model imports:

```php
use App\Models\RetrievalSlaEscalation;
```

Add this test method, alongside the other `test_*_has_no_content_holding_field` methods:

```php
    public function test_retrieval_sla_escalation_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new RetrievalSlaEscalation);
    }
```

No `ALLOWLIST` change is needed: `resolution_detail` contains none of the `FORBIDDEN_FRAGMENTS`
substrings (`content`, `body`, `payload`, `text`, `blob`, `data`, `query`, `result_set`,
`value_json`, `json`, `document`, `message`, `note`, `description`, `comment`) — the same is true
of `breach_action`, already an existing, unallowlisted field on `RetrievalClass`. The new test
method above is the only change this step makes.

- [ ] **Step 4: Write the failing model test**

```php
<?php

namespace Tests\Unit;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrievalSlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_escalation_belongs_to_its_class_observation_and_acknowledger(): void
    {
        $tier = StorageTier::create([
            'code' => 'warm',
            'name' => 'Warm',
            'latency_target_ms' => null,
            'backing' => 'Warm store',
        ]);

        $class = RetrievalClass::create([
            'class_key' => RetrievalClass::AUDIT,
            'name' => 'Audit Access',
            'serves' => 'Governance/audit-log access',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 30_000,
            'p99_target_ms' => null,
            'completeness_required' => true,
            'zero_loss_required' => false,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
        ]);

        $observation = RetrievalObservation::create([
            'retrieval_class_id' => $class->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 1000,
            'failure_count' => 0,
            'p50_latency_ms' => 20_000,
            'p95_latency_ms' => 45_000,
            'p99_latency_ms' => null,
            'sla_met' => false,
            'degraded_mode_triggered' => true,
        ]);

        $admin = User::factory()->withPersonalTeam()->create();

        $escalation = RetrievalSlaEscalation::create([
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => $class->breach_action,
            'status' => 'acknowledged',
            'detected_at' => now(),
            'acknowledged_by' => $admin->id,
            'resolution_detail' => 'Paged SRE, filed INFRA-4521.',
            'acknowledged_at' => now(),
        ]);

        $this->assertSame($class->id, $escalation->retrievalClass->id);
        $this->assertSame($observation->id, $escalation->retrievalObservation->id);
        $this->assertSame($admin->id, $escalation->acknowledgedBy->id);
    }
}
```

- [ ] **Step 5: Run all three test files, migrate, re-run**

```bash
php artisan migrate
php artisan test --compact tests/Unit/RetrievalSlaEscalationTest.php tests/Unit/StoreWithoutReadingInvariantTest.php
```

Expected: 1 passed (escalation test) + 6 passed (invariant test — 5 existing + 1 new).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_09_130001_create_retrieval_sla_escalations_table.php app/Models/RetrievalSlaEscalation.php tests/Unit/StoreWithoutReadingInvariantTest.php tests/Unit/RetrievalSlaEscalationTest.php
git commit -m "feat(memory): add RetrievalSlaEscalation model + structural invariant coverage"
```

---

## Task 4: `memory:scan-sla-breaches` command (Level 1 + Level 2)

**Files:**
- Create: `app/Console/Commands/ScanSlaBreaches.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Memory/ScanSlaBreachesTest.php`

**Interfaces:**
- Consumes: `App\Services\RetrievalSlaEvaluator::meetsContract()` (Task 1),
  `App\Models\RetrievalSlaEscalation` (Task 3), `App\Models\RetrievalClass::latestObservation():
  ?RetrievalObservation` (existing, unmodified).
- Produces: console command `memory:scan-sla-breaches`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Memory;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanSlaBreachesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_class_with_no_observations_is_skipped_without_error(): void
    {
        $this->makeClass(['p95_target_ms' => 800, 'zero_loss_required' => false]);

        $this->artisan('memory:scan-sla-breaches')->assertExitCode(0);

        $this->assertSame(0, RetrievalSlaEscalation::count());
    }

    public function test_a_stale_observation_is_corrected_against_a_changed_contract(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 800, 'zero_loss_required' => false]);
        $observation = $this->makeObservation($class, ['p95_latency_ms' => 700, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        // Contract tightened after the observation was recorded.
        $class->update(['p95_target_ms' => 500]);

        $this->artisan('memory:scan-sla-breaches')->assertExitCode(0);

        $observation->refresh();
        $this->assertFalse($observation->sla_met);
        $this->assertTrue($observation->degraded_mode_triggered);
    }

    public function test_a_breach_on_a_non_governance_class_updates_flags_but_raises_no_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AGENT_CONTEXT, 'p95_target_ms' => 800, 'completeness_required' => false, 'zero_loss_required' => false]);
        $this->makeObservation($class, ['p95_latency_ms' => 900, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(0, RetrievalSlaEscalation::count());
    }

    public function test_a_breach_on_a_governance_class_raises_an_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AUDIT, 'p95_target_ms' => 30_000, 'completeness_required' => true, 'zero_loss_required' => false, 'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event']);
        $observation = $this->makeObservation($class, ['p95_latency_ms' => 45_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertDatabaseHas('retrieval_sla_escalations', [
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
            'status' => 'open',
        ]);
    }

    public function test_rescanning_refreshes_the_linked_observation_but_preserves_detected_at(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::ARCHIVE, 'p95_target_ms' => 86_400_000, 'zero_loss_required' => true, 'breach_action' => 'Integrity incident, mandatory pack']);
        $firstObservation = $this->makeObservation($class, ['p95_latency_ms' => 100, 'failure_count' => 1, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');
        $firstDetectedAt = RetrievalSlaEscalation::first()->detected_at;

        $this->travel(1)->day();

        $secondObservation = $this->makeObservation($class, ['window_start' => now()->subDay(), 'window_end' => now(), 'p95_latency_ms' => 100, 'failure_count' => 2, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(1, RetrievalSlaEscalation::count());
        $escalation = RetrievalSlaEscalation::first();
        $this->assertSame($secondObservation->id, $escalation->retrieval_observation_id);
        $this->assertEquals($firstDetectedAt->timestamp, $escalation->detected_at->timestamp);
    }

    public function test_a_recovered_class_does_not_auto_clear_its_open_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AUDIT, 'p95_target_ms' => 30_000, 'completeness_required' => true, 'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event']);
        $this->makeObservation($class, ['p95_latency_ms' => 45_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');
        $this->assertDatabaseHas('retrieval_sla_escalations', ['retrieval_class_id' => $class->id, 'status' => 'open']);

        $this->makeObservation($class, ['window_start' => now(), 'window_end' => now()->addWeek(), 'p95_latency_ms' => 5_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(1, RetrievalSlaEscalation::count());
        $this->assertDatabaseHas('retrieval_sla_escalations', ['retrieval_class_id' => $class->id, 'status' => 'open']);
    }

    private function makeClass(array $overrides): RetrievalClass
    {
        $tier = StorageTier::create([
            'code' => 'tier-'.uniqid(),
            'name' => 'Tier',
            'latency_target_ms' => 50,
            'backing' => 'Test backing',
        ]);

        return RetrievalClass::create(array_merge([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Test Class',
            'serves' => 'Test consumer',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => null,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Test action',
        ], $overrides));
    }

    private function makeObservation(RetrievalClass $class, array $overrides): RetrievalObservation
    {
        return RetrievalObservation::create(array_merge([
            'retrieval_class_id' => $class->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 1000,
            'failure_count' => 0,
            'p50_latency_ms' => 100,
            'p95_latency_ms' => 200,
            'p99_latency_ms' => null,
            'sla_met' => true,
            'degraded_mode_triggered' => false,
        ], $overrides));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Memory/ScanSlaBreachesTest.php`
Expected: FAIL — command `memory:scan-sla-breaches` doesn't exist.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\RetrievalClass;
use App\Models\RetrievalSlaEscalation;
use App\Services\RetrievalSlaEvaluator;
use Illuminate\Console\Command;

/**
 * This platform's first scheduled job (Dot.Brain audit's Level 1/2
 * candidate, wiki.md §5's Retrieval SLA Contract).
 *
 * Level 1 (no escalation): for every retrieval class, re-evaluate its
 * latest observation against the class's CURRENT contract and correct
 * sla_met/degraded_mode_triggered if they've drifted — genuinely useful
 * since wiki.md §7 says SLA targets are reviewed semi-annually.
 *
 * Level 2 (proposal only, never auto-executes): for the two
 * governance-tier classes (completeness_required or zero_loss_required),
 * a fresh breach raises or refreshes an open RetrievalSlaEscalation for
 * a canGovern() admin to acknowledge (see
 * App\Livewire\Memory\SlaDashboard::confirmAcknowledge()).
 */
class ScanSlaBreaches extends Command
{
    protected $signature = 'memory:scan-sla-breaches';

    protected $description = 'Re-evaluate the latest observation per retrieval class against its current contract and escalate governance-tier breaches.';

    public function handle(RetrievalSlaEvaluator $evaluator): int
    {
        RetrievalClass::all()->each(function (RetrievalClass $class) use ($evaluator): void {
            $observation = $class->latestObservation();

            if (! $observation) {
                return;
            }

            $met = $evaluator->meetsContract($class, [
                'p95_latency_ms' => $observation->p95_latency_ms,
                'p99_latency_ms' => $observation->p99_latency_ms,
                'failure_count' => $observation->failure_count,
            ]);

            if ($observation->sla_met !== $met || $observation->degraded_mode_triggered !== ! $met) {
                $observation->forceFill([
                    'sla_met' => $met,
                    'degraded_mode_triggered' => ! $met,
                ])->save();
            }

            $isGovernanceTier = $class->completeness_required || $class->zero_loss_required;

            if (! $isGovernanceTier || $met) {
                return;
            }

            $openEscalation = RetrievalSlaEscalation::where('retrieval_class_id', $class->id)
                ->where('status', 'open')
                ->first();

            if ($openEscalation) {
                $openEscalation->update([
                    'retrieval_observation_id' => $observation->id,
                    'breach_action' => $class->breach_action,
                ]);

                return;
            }

            RetrievalSlaEscalation::create([
                'retrieval_class_id' => $class->id,
                'retrieval_observation_id' => $observation->id,
                'breach_action' => $class->breach_action,
                'status' => 'open',
                'detected_at' => now(),
            ]);
        });

        $this->info('SLA breach scan complete.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule the command**

Read `routes/console.php` first, then replace its full contents:

```php
<?php

use App\Console\Commands\ScanSlaBreaches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Platform Jobs ──────────────────────────────────────────────────
// This platform's first scheduled process — see
// docs/superpowers/specs/2026-08-09-sla-breach-detection-design.md.
Schedule::command(ScanSlaBreaches::class)
    ->daily()
    ->withoutOverlapping();
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Memory/ScanSlaBreachesTest.php`
Expected: 6 passed.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/ScanSlaBreaches.php routes/console.php tests/Feature/Memory/ScanSlaBreachesTest.php
git commit -m "feat(memory): add memory:scan-sla-breaches — flag refresh + governance-tier escalation"
```

---

## Task 5: Review UI (Acknowledge action) on `SlaDashboard`

**Files:**
- Modify: `app/Livewire/Memory/SlaDashboard.php`
- Modify: `resources/views/livewire/memory/sla-dashboard.blade.php`
- Test: `tests/Feature/Memory/EscalationAcknowledgmentTest.php`

**Interfaces:**
- Consumes: `App\Models\RetrievalSlaEscalation` (Task 3), `SlaDashboard::canGovern()` (Task 2).
- Produces: `SlaDashboard::openEscalations(): Collection` (computed property),
  `::startAcknowledging(int $escalationId): void`, `::confirmAcknowledge(): void`; public
  properties `$acknowledgingEscalationId`, `$resolutionDetail`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\SlaDashboard;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EscalationAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_acknowledge_an_open_escalation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->set('resolutionDetail', 'Paged SRE, filed INFRA-4521.')
            ->call('confirmAcknowledge');

        $escalation->refresh();
        $this->assertSame('acknowledged', $escalation->status);
        $this->assertSame($admin->id, $escalation->acknowledged_by);
        $this->assertSame('Paged SRE, filed INFRA-4521.', $escalation->resolution_detail);
        $this->assertNotNull($escalation->acknowledged_at);
    }

    public function test_acknowledging_requires_resolution_detail(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->set('resolutionDetail', '')
            ->call('confirmAcknowledge')
            ->assertHasErrors(['resolutionDetail']);

        $this->assertSame('open', $escalation->fresh()->status);
    }

    public function test_a_non_admin_cannot_acknowledge(): void
    {
        $member = User::factory()->create(['current_team_id' => null]);
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($member)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->assertForbidden();

        $this->assertSame('open', $escalation->fresh()->status);
    }

    public function test_the_dashboard_lists_open_escalations_for_an_admin(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->assertSee('Escalations');
    }

    private function makeOpenEscalation(): RetrievalSlaEscalation
    {
        $tier = StorageTier::create([
            'code' => 'warm-'.uniqid(),
            'name' => 'Warm',
            'latency_target_ms' => null,
            'backing' => 'Warm store',
        ]);

        $class = RetrievalClass::create([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Audit Access',
            'serves' => 'Governance/audit-log access',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 30_000,
            'p99_target_ms' => null,
            'completeness_required' => true,
            'zero_loss_required' => false,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
        ]);

        $observation = RetrievalObservation::create([
            'retrieval_class_id' => $class->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 1000,
            'failure_count' => 0,
            'p50_latency_ms' => 20_000,
            'p95_latency_ms' => 45_000,
            'p99_latency_ms' => null,
            'sla_met' => false,
            'degraded_mode_triggered' => true,
        ]);

        return RetrievalSlaEscalation::create([
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => $class->breach_action,
            'status' => 'open',
            'detected_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Memory/EscalationAcknowledgmentTest.php`
Expected: FAIL — `startAcknowledging`/`confirmAcknowledge` methods don't exist.

- [ ] **Step 3: Add the computed property and methods to `SlaDashboard`**

Read the current `app/Livewire/Memory/SlaDashboard.php` first (from Task 2). Add this import:

```php
use App\Models\RetrievalSlaEscalation;
```

Add these public properties, alongside the observation-recording properties from Task 2:

```php
    public ?int $acknowledgingEscalationId = null;

    public string $resolutionDetail = '';
```

Add this computed property, alongside `classes()`:

```php
    #[Computed]
    public function openEscalations(): Collection
    {
        return RetrievalSlaEscalation::query()
            ->where('status', 'open')
            ->with('retrievalClass')
            ->latest('detected_at')
            ->get();
    }
```

Add these methods, after `saveObservation()`:

```php
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
```

- [ ] **Step 4: Add the escalations section to the view**

Read the current `resources/views/livewire/memory/sla-dashboard.blade.php` first (from Task 2).
Add this block immediately after the opening `<div class="dot-card" style="padding:1.5rem;">` tag,
before the existing `<h3>Retrieval SLA Attainment</h3>`:

```blade
    @if ($this->canGovern() && $this->openEscalations->isNotEmpty())
        <div style="border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
            <h4 style="font-family:'Syne',sans-serif;font-size:0.8rem;font-weight:700;color:#f4f4f5;margin:0 0 0.75rem;">Escalations</h4>
            @foreach ($this->openEscalations as $escalation)
                <div style="padding:0.6rem 0;border-top:1px solid rgba(255,255,255,0.06);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <span style="color:#f4f4f5;font-weight:600;font-size:0.82rem;">{{ $escalation->retrievalClass->class_key }}</span>
                            <span style="color:#71717a;font-size:0.72rem;margin-left:0.5rem;">{{ $escalation->breach_action }}</span>
                        </div>
                        <button wire:click="startAcknowledging({{ $escalation->id }})" class="dot-btn dot-btn-ghost" style="font-size:10.5px;padding:5px 9px;">
                            Acknowledge
                        </button>
                    </div>
                    @if ($acknowledgingEscalationId === $escalation->id)
                        <div style="margin-top:0.5rem;">
                            <textarea wire:model="resolutionDetail" class="dot-input" rows="2" placeholder="What did you do about it? (required)"></textarea>
                            @error('resolutionDetail') <div style="color:#ef4444;font-size:10px;margin-top:4px;">{{ $message }}</div> @enderror
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Memory/EscalationAcknowledgmentTest.php`
Expected: 4 passed.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Memory/SlaDashboard.php resources/views/livewire/memory/sla-dashboard.blade.php tests/Feature/Memory/EscalationAcknowledgmentTest.php
git commit -m "feat(memory): escalation review UI (Acknowledge, resolution_detail required)"
```

---

## Task 6: Full regression

**Files:** none (verification only).

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass, including every pre-existing suite (`SlaDashboardTest`,
`StoreWithoutReadingInvariantTest`, `AuthenticationTest`, `EcosystemErrorPagesTest`) alongside all
five new suites from this plan.

- [ ] **Step 2: Run Pint across the whole diff one final time**

```bash
vendor/bin/pint --dirty --format agent
```

If it reformats anything, `git add -A` and amend or add a small formatting commit.

- [ ] **Step 3: Confirm the migration applies cleanly from scratch**

```bash
php artisan migrate:fresh
php artisan test --compact
```

Expected: identical pass count to Step 1.

- [ ] **Step 4: Report**

Summarize the final test count and confirm this plan's scope (Tasks 1-5) is fully implemented
against `docs/superpowers/specs/2026-08-09-sla-breach-detection-design.md`. No commits in this
task beyond an optional Pint formatting fix — this is verification-only.
