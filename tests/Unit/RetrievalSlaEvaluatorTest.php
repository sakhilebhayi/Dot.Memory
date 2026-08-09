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
