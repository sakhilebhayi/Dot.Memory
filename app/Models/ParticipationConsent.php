<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person's opt-in for participation records (R-9). Default is NO consent:
 * a person with no row, or a row with granted = false, has nothing recorded
 * and nothing readable. Holds the decision and its dates only.
 */
class ParticipationConsent extends Model
{
    protected $fillable = ['user_uuid', 'granted', 'granted_at', 'revoked_at', 'source'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
