<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One moderator outcome (adopted | declined) for a knowledge recommendation. APPEND-ONLY: rows are
 * inserted and never changed or removed, so the history of a decision is the history of its rows and
 * the current state is the latest row. Holds opaque ids only; `actor_ref` is never returned to Brain.
 */
class KnowledgeOutcome extends Model
{
    public const UPDATED_AT = null;

    public const ADOPTED = 'adopted';

    public const DECLINED = 'declined';

    public const OUTCOMES = [self::ADOPTED, self::DECLINED];

    protected $fillable = ['decision_event_id', 'contribution_ref', 'outcome', 'actor_ref', 'decided_at'];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('knowledge_outcomes is append-only: record a new row instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('knowledge_outcomes is append-only: rows are never deleted.');
        });
    }
}
