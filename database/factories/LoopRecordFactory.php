<?php

namespace Database\Factories;

use App\Models\LoopRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoopRecord>
 */
class LoopRecordFactory extends Factory
{
    protected $model = LoopRecord::class;

    public function definition(): array
    {
        return [
            'loop_id' => 'loop-'.$this->faker->unique()->uuid(),
            'event_id' => 'evt-'.$this->faker->unique()->uuid(),
            'stage' => 'observation',
            'platform' => 'dot-mines',
            'source' => 'telemetry',
            'subject_type' => 'customer',
            'subject_id' => (string) $this->faker->numberBetween(1, 999),
            'signature' => 'engagement-decline',
            'occurred_at' => now(),
            'detail' => null,
        ];
    }

    public function decision(float $confidence = 0.8, float $risk = 0.2): static
    {
        return $this->state(fn (): array => [
            'stage' => 'decision',
            'platform' => 'dot-brain',
            'confidence' => $confidence,
            'risk' => $risk,
            'autonomy_level' => 'recommend',
            'requires_approval' => true,
        ]);
    }

    public function action(string $status = 'succeeded'): static
    {
        return $this->state(fn (): array => [
            'stage' => 'action',
            'platform' => 'dot-dopemine',
            'action_kind' => 'engagement_intervention',
            'executor_platform' => 'dot-dopemine',
            'approval_status' => 'approved',
            'execution_status' => $status,
        ]);
    }

    public function outcome(string $verdict = 'improved'): static
    {
        return $this->state(fn (): array => [
            'stage' => 'outcome',
            'platform' => 'dot-mines',
            'verdict' => $verdict,
            'measure' => 'engagement',
        ]);
    }
}
