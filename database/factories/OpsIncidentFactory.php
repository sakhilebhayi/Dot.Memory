<?php

namespace Database\Factories;

use App\Models\OpsIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpsIncident>
 */
class OpsIncidentFactory extends Factory
{
    protected $model = OpsIncident::class;

    public function definition(): array
    {
        return [
            'incident_uid' => 'grd-'.$this->faker->unique()->uuid(),
            'platform' => 'dot-mines',
            'environment' => 'production',
            'detection_source' => 'guardian-health-poll',
            'signature' => 'dot-mines:'.$this->faker->randomElement(['queue', 'integration_sync', 'database']).':critical',
            'component' => $this->faker->randomElement(['queue', 'integration_sync', 'database']),
            'severity' => $this->faker->randomElement(OpsIncident::SEVERITIES),
            'status' => 'open',
            'rollback_occurred' => false,
            'detected_at' => now()->subHour(),
            'record' => null,
        ];
    }
}
