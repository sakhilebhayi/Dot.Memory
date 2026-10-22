<?php

namespace App\Http\Controllers\Participation;

use App\Http\Controllers\Controller;
use App\Models\LoopRecord;
use App\Services\Participation\ParticipationStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Opt-in participation signals (R-9), called server-to-server by InfoDot on behalf of a person.
 * Every response is JSON; every error is `{code, message}`. See docs/participation-api.md.
 *
 * Memory records topic + count for people who said yes, forgets them on request, and never
 * answers "who knows X". It is not the points ledger.
 */
class ParticipationController extends Controller
{
    public const EVENT_FIELDS = ['event_id', 'user_uuid', 'occurred_at', 'topic', 'kind', 'contribution_ref'];

    public function __construct(private readonly ParticipationStore $store) {}

    /**
     * PUT /api/participation/{user_uuid}/consent  {granted: bool}
     */
    public function setConsent(Request $request, string $userUuid): JsonResponse
    {
        $uuid = $this->uuid($userUuid);

        if ($uuid === null) {
            return $this->invalidUuid();
        }

        $payload = $request->json()->all();
        $unknown = array_diff(array_keys($payload), ['granted']);
        $validator = Validator::make($payload, ['granted' => 'required|boolean']);

        if ($unknown !== [] || $validator->fails() || ! is_bool($payload['granted'] ?? null)) {
            return $this->error('invalid_request', 'Body must be {"granted": true|false}.', 422);
        }

        $result = $this->store->setConsent($uuid, $payload['granted']);

        return response()->json(['data' => [
            ...$this->presentConsent($uuid),
            'purged' => $result['purged'],
        ]]);
    }

    /**
     * POST /api/participation/events  one event, or {"events": [...]} up to the configured maximum.
     */
    public function storeEvents(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === [] || array_is_list($payload)) {
            return $this->error('invalid_request', 'Body must be a JSON object.', 422);
        }

        $batch = array_key_exists('events', $payload);

        if ($batch) {
            $max = max(1, (int) config('participation.max_events_per_request'));

            if (array_keys($payload) !== ['events'] || ! is_array($payload['events']) || ! array_is_list($payload['events']) || $payload['events'] === []) {
                return $this->error('invalid_request', 'Body must be {"events": [ ... ]} with at least one event.', 422);
            }

            if (count($payload['events']) > $max) {
                return $this->error('too_many_events', "At most {$max} events per request.", 422);
            }

            $events = $payload['events'];
        } else {
            $events = [$payload];
        }

        $clean = [];
        $problems = [];

        foreach ($events as $index => $event) {
            [$valid, $errors] = $this->validateEvent(is_array($event) ? $event : []);

            if ($valid === null) {
                $problems[$batch ? "events.{$index}" : 'event'] = $errors;
            } else {
                $clean[] = $valid;
            }
        }

        if ($problems !== []) {
            return response()->json([
                'code' => 'invalid_request',
                'message' => 'The event is not valid.',
                'errors' => $problems,
            ], 422);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                $outcome = DB::transaction(fn () => $this->recordAll($clean));
            } catch (UniqueConstraintViolationException $e) {
                // A concurrent replay won the insert; the next attempt finds the existing row and returns it.
                if ($attempt >= 2) {
                    throw $e;
                }

                continue;
            }

            break;
        }

        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }

        $status = in_array(true, array_column($outcome, 'created'), true) ? 201 : 200;
        $data = array_map(fn (array $row): array => $row['data'], $outcome);

        return response()->json(['data' => $batch ? $data : $data[0]], $status);
    }

    /**
     * GET /api/participation/{user_uuid}/context  topic -> counts, plus the consent state. Nothing else.
     */
    public function context(string $userUuid): JsonResponse
    {
        $uuid = $this->uuid($userUuid);

        if ($uuid === null) {
            return $this->invalidUuid();
        }

        if (! $this->store->hasConsent($uuid)) {
            return $this->consentRequired();
        }

        return response()->json(['data' => [
            'user_uuid' => $uuid,
            'consent' => $this->presentConsent($uuid)['consent'],
            'topics' => $this->store->counts($uuid),
        ]]);
    }

    /**
     * DELETE /api/participation/{user_uuid}  hard-delete the person's rows and consent record. Idempotent.
     */
    public function erase(string $userUuid): JsonResponse
    {
        $uuid = $this->uuid($userUuid);

        if ($uuid === null) {
            return $this->invalidUuid();
        }

        return response()->json(['data' => ['user_uuid' => $uuid, ...$this->store->erase($uuid)]]);
    }

    /**
     * Validate and normalise one event.
     *
     * @param  array<mixed>  $event
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private function validateEvent(array $event): array
    {
        if ($event === []) {
            return [null, ['Event must be a non-empty object.']];
        }

        $unknown = array_diff(array_keys($event), self::EVENT_FIELDS);

        if ($unknown !== []) {
            // Name the offending keys, never echo values: unknown fields are how content would sneak in.
            return [null, ['Unknown field(s): '.implode(', ', array_map('strval', $unknown)).'. Only '.implode(', ', self::EVENT_FIELDS).' are accepted.']];
        }

        $validator = Validator::make($event, [
            'event_id' => ['required', 'string', 'max:200', 'regex:'.config('participation.reference_pattern')],
            'user_uuid' => ['required', 'string', 'uuid'],
            'occurred_at' => ['required', 'string', 'date'],
            'topic' => ['required', 'string', 'max:'.(int) config('participation.topic_max_length'), 'regex:'.config('participation.topic_pattern')],
            'kind' => ['required', 'string', 'in:'.implode(',', (array) config('participation.kinds'))],
            'contribution_ref' => ['required', 'string', 'max:128', 'regex:'.config('participation.reference_pattern')],
        ]);

        if ($validator->fails()) {
            return [null, array_map(fn (array $messages): string => $messages[0], array_values($validator->errors()->toArray()))];
        }

        $at = CarbonImmutable::parse($event['occurred_at'])->utc()->setMicroseconds(0);

        if ($at->isAfter(now()->addDay())) {
            return [null, ['occurred_at cannot be in the future.']];
        }

        if ($at->isBefore(now()->subDays((int) config('participation.retention_days')))) {
            return [null, ['occurred_at is outside the retention window.']];
        }

        return [[...$event, 'user_uuid' => strtolower($event['user_uuid']), 'occurred_at' => $at], []];
    }

    /**
     * @param  list<array<string, mixed>>  $events  validated
     * @return JsonResponse|list<array{created: bool, data: array<string, mixed>}>
     */
    private function recordAll(array $events): JsonResponse|array
    {
        // Consent first, under a row lock, so a revoke racing this request cannot leave rows behind.
        foreach (array_unique(array_column($events, 'user_uuid')) as $uuid) {
            if (! $this->store->hasConsent($uuid, lock: true)) {
                return $this->consentRequired();
            }
        }

        $keys = array_map(fn (array $e): string => ParticipationStore::EVENT_PREFIX.$e['event_id'], $events);
        $existing = LoopRecord::query()->whereIn('event_id', $keys)->get()->keyBy('event_id');

        $seen = [];

        foreach ($events as $i => $event) {
            $row = $existing->get($keys[$i]);
            $fingerprint = json_encode([$event['user_uuid'], $event['topic'], $event['kind'], $event['contribution_ref'], $event['occurred_at']->timestamp]);

            if (($row !== null && ! $this->sameEvent($row, $event)) || (isset($seen[$keys[$i]]) && $seen[$keys[$i]] !== $fingerprint)) {
                return $this->error('event_conflict', 'This event_id was already used for a different event.', 409);
            }

            $seen[$keys[$i]] = $fingerprint;
        }

        $results = [];

        foreach ($events as $i => $event) {
            $row = $existing->get($keys[$i]) ?? LoopRecord::query()->where('event_id', $keys[$i])->first();
            $created = false;

            if ($row === null) {
                $row = LoopRecord::query()->create([
                    'loop_id' => $keys[$i],
                    'event_id' => $keys[$i],
                    'stage' => 'observation',
                    'platform' => ParticipationStore::PLATFORM,
                    'source' => ParticipationStore::SOURCE_PREFIX.$event['kind'],
                    'subject_type' => ParticipationStore::SUBJECT_TYPE,
                    'subject_id' => $event['user_uuid'],
                    'signature' => ParticipationStore::SIGNATURE_PREFIX.$event['topic'],
                    'detail' => ['contribution_ref' => $event['contribution_ref']],
                    'occurred_at' => $event['occurred_at'],
                ]);
                $created = true;
            }

            $results[] = ['created' => $created, 'data' => [
                'event_id' => $event['event_id'],
                'user_uuid' => $event['user_uuid'],
                'topic' => $event['topic'],
                'kind' => $event['kind'],
                'contribution_ref' => $event['contribution_ref'],
                'occurred_at' => $event['occurred_at']->toIso8601ZuluString(),
            ]];

            $existing->put($keys[$i], $row);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sameEvent(LoopRecord $row, array $event): bool
    {
        return $row->subject_id === $event['user_uuid']
            && $row->signature === ParticipationStore::SIGNATURE_PREFIX.$event['topic']
            && $row->source === ParticipationStore::SOURCE_PREFIX.$event['kind']
            && ($row->detail['contribution_ref'] ?? null) === $event['contribution_ref']
            && $row->occurred_at?->equalTo($event['occurred_at']);
    }

    /**
     * @return array{consent: array<string, mixed>}
     */
    private function presentConsent(string $uuid): array
    {
        $consent = $this->store->consent($uuid);

        return [
            'user_uuid' => $uuid,
            'consent' => [
                'granted' => (bool) $consent?->granted,
                'granted_at' => $consent?->granted_at?->toIso8601ZuluString(),
                'revoked_at' => $consent?->revoked_at?->toIso8601ZuluString(),
                'source' => $consent?->source ?? 'infodot',
            ],
        ];
    }

    private function uuid(string $value): ?string
    {
        return Str::isUuid($value) ? strtolower($value) : null;
    }

    private function invalidUuid(): JsonResponse
    {
        return $this->error('invalid_user_uuid', 'user_uuid must be a UUID.', 422);
    }

    private function consentRequired(): JsonResponse
    {
        return $this->error('consent_required', 'This person has not opted in to participation records.', 403);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }
}
