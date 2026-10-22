<?php

namespace App\Services\Memory;

use App\Models\LoopRecord;
use App\Models\ParticipationConsent;
use App\Services\Participation\ParticipationStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

/**
 * The one place that decides what the generic (Sanctum) intelligence API may do with loop_records
 * that concern a PERSON. Person-level data exists only through the opt-in participation API
 * (docs/participation-api.md), so for the generic API:
 *
 *   READS   Every read goes through readable(). It never returns a participation row (those are only
 *           readable through the signed participation context endpoint), and it returns a person-subject
 *           row only while that person's consent is current. Asking for a person subject without
 *           consent is refused with 403 consent_required, not answered with an empty pack, so the
 *           refusal is the same whether or not anything is held.
 *   WRITES  A write about a person subject, or into the participation namespace, is refused with
 *           403 person_subject_reserved. Person records are written only by the participation API,
 *           which checks consent, validates a closed field list and applies retention. The same check
 *           covers an existing row addressed by event_id, so a replay cannot overwrite one.
 *
 * Subjects of any other type behave exactly as before. A new route, query or service that returns
 * loop_records must go through this class; LoopRecordAccessGuardTest fails until it is classified.
 */
class LoopRecordAccess
{
    public const PERSON = 'person';

    /** Reserved by the participation API in source, event_id and loop_id. */
    public const RESERVED_PREFIX = 'participation:';

    public function __construct(private readonly ParticipationStore $participation) {}

    /** Case, padding and collation safe: 'Person ' is a person subject too. */
    public static function isPersonType(?string $subjectType): bool
    {
        return strtolower(trim((string) $subjectType)) === self::PERSON;
    }

    /**
     * True for anything the participation API owns: a person subject (whatever the platform, which also
     * covers the platform=infodot person namespace participation rows live in) or any participation-prefixed
     * source / event_id / loop_id.
     *
     * @param  array<string, mixed>|LoopRecord  $envelope
     */
    public static function isProtected(array|LoopRecord $envelope): bool
    {
        $get = fn (string $key): string => strtolower(trim((string) (is_array($envelope) ? ($envelope[$key] ?? '') : $envelope->getAttribute($key))));

        if (self::isPersonType($get('subject_type'))) {
            return true;
        }

        foreach (['source', 'event_id', 'loop_id'] as $key) {
            if (str_starts_with($get($key), self::RESERVED_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuse a generic write that touches person data or the participation namespace.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function assertWritable(array $envelope, ?LoopRecord $existing = null): void
    {
        if (self::isProtected($envelope) || ($existing !== null && self::isProtected($existing))) {
            throw new HttpResponseException(response()->json([
                'code' => 'person_subject_reserved',
                'message' => 'Records about a person are written only through the opt-in participation API.',
            ], 403));
        }
    }

    /**
     * Refuse a read that names a person subject without that person's current consent.
     */
    public function assertReadable(string $subjectType, string $subjectId): void
    {
        if (self::isPersonType($subjectType) && ! $this->participation->hasConsent($this->normaliseId($subjectId))) {
            throw new HttpResponseException(response()->json([
                'code' => 'consent_required',
                'message' => 'This person has not opted in to participation records.',
            ], 403));
        }
    }

    /**
     * Narrow a loop_records query to what the generic API may return. Applied in SQL so a row is never
     * loaded, not filtered afterwards. Uses lower()/trim() so odd casing or padding cannot slip past.
     *
     * @param  Builder<LoopRecord>  $query
     * @return Builder<LoopRecord>
     */
    public function readable(Builder $query): Builder
    {
        return $query
            // Participation rows: never, even for a consenting person.
            ->whereRaw("lower(coalesce(source, '')) not like ?", [self::RESERVED_PREFIX.'%'])
            ->whereRaw("lower(coalesce(event_id, '')) not like ?", [self::RESERVED_PREFIX.'%'])
            ->whereRaw("lower(coalesce(loop_id, '')) not like ?", [self::RESERVED_PREFIX.'%'])
            ->whereRaw('not (lower(platform) = ? and lower(trim(subject_type)) = ?)', [ParticipationStore::PLATFORM, self::PERSON])
            // Person subjects: only while consent is current.
            ->where(function (Builder $q): void {
                $q->whereRaw('lower(trim(subject_type)) <> ?', [self::PERSON])
                    ->orWhereIn('subject_id', ParticipationConsent::query()->where('granted', true)->select('user_uuid'));
            });
    }

    private function normaliseId(string $subjectId): string
    {
        $id = trim($subjectId);

        return Str::isUuid($id) ? strtolower($id) : $id;
    }
}
