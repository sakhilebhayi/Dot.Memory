<?php

namespace App\Services\Participation;

use App\Models\LoopRecord;
use App\Models\ParticipationConsent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The only code that reads or writes participation records (R-9).
 *
 * A participation record is an ordinary loop_records row (so it shares the
 * envelope, the encrypted detail column and the idempotency key) marked by
 * platform = infodot, subject_type = person, source = participation:<kind>
 * and signature = expertise:<topic>. It carries NO content: the encrypted
 * detail holds the contribution_ref only, and team_id, user_id and
 * subject_label stay null.
 *
 * Memory is not the points ledger and this is not a profile: nothing here
 * can answer "who knows topic X", rank people, or return anything but
 * topic + count for one named, consenting person.
 */
class ParticipationStore
{
    public const PLATFORM = 'infodot';

    public const SUBJECT_TYPE = 'person';

    public const SOURCE_PREFIX = 'participation:';

    public const SIGNATURE_PREFIX = 'expertise:';

    /** Namespaces the idempotency key so it can never collide with the Sanctum intelligence API's event ids. */
    public const EVENT_PREFIX = 'participation:infodot:';

    /**
     * The participation rows (narrow marker). Used for counting and pruning.
     *
     * @return Builder<LoopRecord>
     */
    public function rows(?string $userUuid = null): Builder
    {
        return LoopRecord::query()
            ->where('platform', self::PLATFORM)
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('source', 'like', self::SOURCE_PREFIX.'%')
            ->where('signature', 'like', self::SIGNATURE_PREFIX.'%')
            ->when($userUuid !== null, fn (Builder $q) => $q->where('subject_id', $userUuid));
    }

    public function hasConsent(string $userUuid, bool $lock = false): bool
    {
        $query = ParticipationConsent::query()->where('user_uuid', $userUuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        return (bool) $query->value('granted');
    }

    public function consent(string $userUuid): ?ParticipationConsent
    {
        return ParticipationConsent::query()->where('user_uuid', $userUuid)->first();
    }

    /**
     * Record the person's decision. Revoking purges their participation rows in the same transaction.
     *
     * @return array{consent: ParticipationConsent, purged: int}
     */
    public function setConsent(string $userUuid, bool $granted): array
    {
        $this->secureDelete();

        return DB::transaction(function () use ($userUuid, $granted): array {
            $consent = ParticipationConsent::query()->where('user_uuid', $userUuid)->lockForUpdate()->first()
                ?? new ParticipationConsent(['user_uuid' => $userUuid, 'source' => 'infodot']);

            $purged = 0;

            if ($granted) {
                if (! $consent->granted) {
                    $consent->granted_at = now();
                }
                $consent->granted = true;
                $consent->revoked_at = null;
            } else {
                $consent->granted = false;
                $consent->revoked_at = now();
                $purged = $this->deleteAllFor($userUuid);
            }

            $consent->save();

            return ['consent' => $consent, 'purged' => $purged];
        });
    }

    /**
     * Hard-delete everything held about a person: every person-subject row for them, whatever platform wrote
     * it (a superset of the participation rows), and their consent record. Idempotent.
     *
     * @return array{records_deleted: int, consent_deleted: bool}
     */
    public function erase(string $userUuid): array
    {
        $this->secureDelete();

        return DB::transaction(function () use ($userUuid): array {
            $records = $this->deleteAllFor($userUuid);
            $consent = ParticipationConsent::query()->where('user_uuid', $userUuid)->delete() > 0;

            return ['records_deleted' => $records, 'consent_deleted' => $consent];
        });
    }

    /**
     * topic => counts for one person, straight from a grouped count over the envelope columns. The
     * encrypted detail is never read, and nothing but topic and counts leaves this method.
     *
     * @return list<array{topic: string, count: int, kinds: array<string, int>}>
     */
    public function counts(string $userUuid): array
    {
        $rows = $this->rows($userUuid)
            ->select('signature', 'source', DB::raw('count(*) as total'))
            ->groupBy('signature', 'source')
            ->get();

        $topics = [];

        foreach ($rows as $row) {
            $topic = substr((string) $row->signature, strlen(self::SIGNATURE_PREFIX));
            $kind = substr((string) $row->source, strlen(self::SOURCE_PREFIX));

            $topics[$topic] ??= ['topic' => $topic, 'count' => 0, 'kinds' => array_fill_keys((array) config('participation.kinds'), 0)];
            $topics[$topic]['count'] += (int) $row->total;
            $topics[$topic]['kinds'][$kind] = ($topics[$topic]['kinds'][$kind] ?? 0) + (int) $row->total;
        }

        $list = array_values($topics);
        usort($list, fn (array $a, array $b): int => [$b['count'], $a['topic']] <=> [$a['count'], $b['topic']]);

        return $list;
    }

    /**
     * Retention and consent sweep. Hard-deletes participation rows older than the retention window and
     * rows of anyone without a current grant, then revoked consent records older than the window.
     *
     * @return array{expired: int, unconsented: int, consents: int}
     */
    public function prune(?int $retentionDays = null): array
    {
        $this->secureDelete();

        $days = $retentionDays ?? (int) config('participation.retention_days');
        $cutoff = now()->subDays($days);

        return DB::transaction(function () use ($cutoff): array {
            $expired = $this->rows()->where('occurred_at', '<', $cutoff)->delete();

            $unconsented = $this->rows()
                ->whereNotIn('subject_id', ParticipationConsent::query()->where('granted', true)->select('user_uuid'))
                ->delete();

            // A revoked record only restates the default (no consent), so it need not outlive the window.
            $consents = ParticipationConsent::query()
                ->where('granted', false)
                ->where('updated_at', '<', $cutoff)
                ->delete();

            return ['expired' => $expired, 'unconsented' => $unconsented, 'consents' => $consents];
        });
    }

    private function deleteAllFor(string $userUuid): int
    {
        return LoopRecord::query()
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('subject_id', $userUuid)
            ->delete();
    }

    /**
     * On SQLite a plain DELETE leaves the old bytes in free pages; secure_delete zero-fills them so a
     * purge really removes the data from the file. Other drivers have no equivalent switch.
     */
    private function secureDelete(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA secure_delete = ON');
        }
    }
}
