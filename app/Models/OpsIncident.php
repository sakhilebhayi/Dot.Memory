<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One archived operational incident published by the Dot.Brain guardian.
 *
 * DATA-PLANE MODEL -- deliberately different from the telemetry models
 * covered by StoreWithoutReadingInvariantTest. Incident archives ARE the
 * content another platform asked us to store ("Knowledge Pack archives and
 * the audit trails", wiki.md §1). The envelope columns (identifiers,
 * enums, booleans, timestamps) exist only so the PUBLISHER can retrieve
 * and aggregate its own records; everything narrative -- diagnosis, root
 * cause, actions, code changes -- lives in the opaque `record` blob,
 * encrypted at rest, which Dot.Memory never reads or filters on
 * (wiki.md §2: store without reading).
 */
class OpsIncident extends Model
{
    use HasFactory;

    public const SEVERITIES = ['sev1', 'sev2', 'sev3', 'sev4'];

    public const STATUSES = ['open', 'remediating', 'resolved', 'escalated', 'rolled_back'];

    protected $fillable = [
        'incident_uid',
        'platform',
        'environment',
        'detection_source',
        'signature',
        'component',
        'severity',
        'status',
        'tests_result',
        'deploy_result',
        'validation_result',
        'rollback_occurred',
        'detected_at',
        'resolved_at',
        'record',
    ];

    protected function casts(): array
    {
        return [
            'rollback_occurred' => 'boolean',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'record' => 'encrypted:array',
        ];
    }
}
