<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One stage of an intelligence loop (Dot.Brain's
 * schemas/intelligence-loop.schema.json). Rows sharing a loop_id form one
 * cycle: observation -> decision -> action -> outcome.
 *
 * DATA-PLANE MODEL, like OpsIncident: these are first-party ecosystem
 * records published by Dot platforms about their own operation, not
 * third-party tenant content. Envelope columns are queryable so Memory can
 * group, count and grade; the narrative stays in the encrypted `detail`
 * blob, which Memory stores without reading (wiki.md §2, carve-out 0.7.0).
 */
class LoopRecord extends Model
{
    use HasFactory;

    public const STAGES = ['observation', 'decision', 'action', 'outcome'];

    public const VERDICTS = ['improved', 'unchanged', 'worsened', 'inconclusive'];

    public const AUTONOMY_LEVELS = ['observe', 'recommend', 'approve', 'execute', 'autonomous'];

    public const APPROVAL_STATUSES = ['not_required', 'pending', 'approved', 'rejected'];

    public const EXECUTION_STATUSES = ['pending', 'succeeded', 'failed', 'refused', 'rolled_back'];

    protected $fillable = [
        'loop_id', 'event_id', 'stage', 'platform', 'source',
        'subject_type', 'subject_id', 'subject_label', 'signature',
        'team_id', 'user_id',
        'confidence', 'risk', 'autonomy_level', 'requires_approval',
        'action_kind', 'executor_platform', 'mechanic_ref', 'approval_status', 'execution_status',
        'verdict', 'measure',
        'detail', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'risk' => 'float',
            'requires_approval' => 'boolean',
            'detail' => 'encrypted:array',
            'occurred_at' => 'datetime',
        ];
    }
}
