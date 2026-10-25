<?php

namespace App\Console\Commands;

use App\Services\Participation\ParticipationStore;
use Illuminate\Console\Command;

/**
 * Daily retention and consent sweep for participation records (R-9). Hard-deletes; idempotent; prints counts only.
 */
class PruneParticipation extends Command
{
    protected $signature = 'participation:prune';

    protected $description = 'Hard-delete participation rows past the retention window or without a current consent.';

    public function handle(ParticipationStore $store): int
    {
        $result = $store->prune();

        $this->info(sprintf(
            'Pruned participation: %d expired, %d without consent, %d stale consent record(s).',
            $result['expired'],
            $result['unconsented'],
            $result['consents'],
        ));

        return self::SUCCESS;
    }
}
