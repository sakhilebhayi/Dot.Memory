<?php

namespace Tests\Unit;

use App\Models\DurabilityOutcome;
use App\Models\Index;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\StorageTier;
use Tests\TestCase;

/**
 * Structural test for wiki.md §2's "store without reading" principle.
 *
 * Dot.Memory must never model or expose the actual content it stores —
 * only telemetry about storage/retrieval performance. This test asserts
 * that invariant directly against the domain models' $fillable arrays
 * (and, as a defense-in-depth check, their $casts), rather than relying
 * on convention: no field name may suggest it can hold arbitrary
 * tenant-authored content.
 */
class StoreWithoutReadingInvariantTest extends TestCase
{
    /**
     * Field-name fragments that would indicate a content-holding column
     * (free text bodies, blobs, payloads, query/result data) rather than
     * a telemetry value (a count, a latency, a boolean, a timestamp, or
     * a short identifying label).
     */
    private const FORBIDDEN_FRAGMENTS = [
        'content', 'body', 'payload', 'text', 'blob', 'data',
        'query', 'result_set', 'value_json', 'json', 'document',
        'message', 'note', 'description', 'comment',
    ];

    /**
     * Field names that are allowed even though they might loosely match
     * a forbidden fragment — they are structural labels, not content.
     */
    private const ALLOWLIST = [
        'backing', // StorageTier: short label like "Warm store"
        'name', 'code', 'type', 'status', 'result', 'check_type',
    ];

    public function test_retrieval_observation_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new RetrievalObservation);
    }

    public function test_durability_outcome_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new DurabilityOutcome);
    }

    public function test_retrieval_class_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new RetrievalClass);
    }

    public function test_storage_tier_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new StorageTier);
    }

    public function test_index_has_no_content_holding_field(): void
    {
        $this->assertModelFieldsAreTelemetryOnly(new Index);
    }

    private function assertModelFieldsAreTelemetryOnly(object $model): void
    {
        $fillable = $model->getFillable();

        $this->assertNotEmpty($fillable, get_class($model).' should declare $fillable.');

        foreach ($fillable as $field) {
            if (in_array($field, self::ALLOWLIST, true)) {
                continue;
            }

            foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
                $this->assertStringNotContainsString(
                    $fragment,
                    strtolower($field),
                    sprintf(
                        '%s::$fillable contains "%s", which matches forbidden fragment "%s" — '
                        .'Dot.Memory must never hold a field capable of storing tenant content '
                        .'(wiki.md §2, "store without reading").',
                        get_class($model),
                        $field,
                        $fragment
                    )
                );
            }
        }
    }
}
