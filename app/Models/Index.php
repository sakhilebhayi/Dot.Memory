<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A graph, vector, or audit-log index — wiki.md §4.
 *
 * Identity and health only (key, version, type, status, entry count).
 * Never models indexed content itself.
 */
class Index extends Model
{
    use HasFactory;

    protected $table = 'indexes';

    public const TYPE_GRAPH = 'graph';
    public const TYPE_VECTOR = 'vector';
    public const TYPE_AUDIT_LOG = 'audit-log';

    protected $fillable = [
        'index_key',
        'version',
        'type',
        'storage_tier_id',
        'status',
        'entry_count',
        'last_rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'entry_count' => 'integer',
            'last_rebuilt_at' => 'datetime',
        ];
    }

    public function storageTier(): BelongsTo
    {
        return $this->belongsTo(StorageTier::class);
    }
}
