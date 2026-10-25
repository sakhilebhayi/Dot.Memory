# Participation API (R-9)

Opt-in, person-level expertise signals for InfoDot community rewards (requirement R-9, section 6). Dot.Memory may hold
which topics a person has contributed to, and how many times, **only if that person opted in**. Memory is never the
points ledger: Dot.Dopemine owns points. This API is separate from the Sanctum-authenticated `/api/intelligence/*`
loop API, which cannot read or write person data (see "The generic API and person subjects").

## Privacy model

| Rule | How it is enforced |
|---|---|
| Opt-in, default off | No consent row means no consent. Events for a person without a current grant are refused with `403 consent_required`; nothing is stored. |
| Keyed by `user_uuid` only | InfoDot's user UUID. No name, e-mail, handle or team. `team_id`, `user_id` and `subject_label` stay null. |
| Topic and count only | A row is `signature = expertise:<topic>` + `source = participation:<kind>`. The context read returns topic and counts and nothing else. |
| No content | The encrypted `detail` holds the opaque `contribution_ref` only. Event bodies are validated against a closed field list; unknown fields (title, body, e-mail, ...) are rejected with 422, not dropped. Topic is a slug, refs are opaque ids (no spaces, no `@`). |
| Revoke purges immediately | `PUT .../consent {granted:false}` deletes the person's rows (every person-subject row for that UUID) in the same transaction as the consent change. |
| Hard erase | `DELETE /api/participation/{user_uuid}` removes every person-subject row for that UUID (any platform) and the consent record. Nothing is soft-deleted. |
| Retention limit | `participation:prune` (daily 03:10) hard-deletes rows older than `PARTICIPATION_RETENTION_DAYS` (default 365, by `occurred_at`), rows of anyone without a current grant, and revoked-consent records older than the window. |
| No ranking, no discovery | There is no endpoint that takes a topic and returns people, and none that orders people. A read always names exactly one person, and InfoDot calls it on that person's behalf. |

SQLite note: the purge paths enable `PRAGMA secure_delete`, so deleted bytes are zero-filled in the database file
rather than left in free pages.

**Residual copies the application cannot reach:** the nightly `memory:backup-database` keeps 14 dated copies in
`storage/backups`, and any off-host copy of those. A purged row can therefore survive in an older backup for up to 14
days (longer if backups are copied elsewhere). This is a deploy/ops decision; see "Owner steps" in the pull request.

## Authentication

Server-to-server, signed. Same scheme as Dot.Dopemine's points endpoints:

- `X-InfoDot-Timestamp`: unix seconds.
- `X-InfoDot-Signature`: `sha256=` + HMAC-SHA256(secret, `"{timestamp}.{raw body}"`).
- Window: 300 seconds either side (`PARTICIPATION_TOLERANCE`).
- A GET or DELETE has no body and signs the empty string.
- Secret: `PARTICIPATION_SECRET_INFODOT`. Unset or empty means nobody can authenticate (fails closed).
- Failure: `401 {"code":"invalid_signature","message":"Invalid signature."}`.

All errors are `{"code": "...", "message": "..."}` (validation errors add `errors`).

## Endpoints

### `PUT /api/participation/{user_uuid}/consent`

Body `{"granted": true|false}` (strict boolean, nothing else). Records the decision.

```json
{"data": {"user_uuid": "...", "consent": {"granted": false, "granted_at": "...Z", "revoked_at": "...Z", "source": "infodot"}, "purged": 3}}
```

`purged` is the number of rows deleted by a revoke (0 on grant).

### `POST /api/participation/events`

```json
{"event_id": "answer.accepted:77", "user_uuid": "...", "occurred_at": "2026-10-22T09:00:00Z",
 "topic": "laravel-queues", "kind": "accepted", "contribution_ref": "answer:77"}
```

- `kind` is one of `accepted`, `verified` (`config/participation.php`).
- `topic` matches `^[a-z0-9]+(-[a-z0-9]+)*$`, at most 64 characters.
- `event_id` and `contribution_ref` are opaque ids: letters, digits, `. _ : -`.
- `occurred_at` must be within the retention window and not more than a day ahead.
- Up to `PARTICIPATION_MAX_EVENTS` (default 50) events may be sent as `{"events": [ ... ]}`; a batch is atomic.
- `201` when recorded; `200` with the same body on a replay of the same `event_id` (never counted twice);
  `409 event_conflict` if the id was used for a different event; `403 consent_required`; `422 invalid_request`.

### `GET /api/participation/{user_uuid}/context`

```json
{"data": {"user_uuid": "...", "consent": {"granted": true, "granted_at": "...", "revoked_at": null, "source": "infodot"},
 "topics": [{"topic": "laravel-queues", "count": 3, "kinds": {"accepted": 2, "verified": 1}}]}}
```

`403 consent_required` without a current grant. Counts come from a grouped count over the envelope columns; the
encrypted `detail` is never read.

### `DELETE /api/participation/{user_uuid}`

Hard-deletes the person's rows and consent record. Idempotent.

```json
{"data": {"user_uuid": "...", "records_deleted": 2, "consent_deleted": true}}
```

## How it fits Brain and Dopemine

- InfoDot (the authoritative source of contributions) sends an event when a contribution is accepted or verified, only
  for people who have opted in, and asks for the context when it wants to personalise (for example suggesting unanswered
  questions). Dot.Dopemine keeps awarding points from its own signed intake; nothing here changes a balance.
- Dot.Brain may ask for one named person's counts through InfoDot's call path, not a separate path.

## What it deliberately does not do

- No "who knows topic X" lookup across people, and no ranking or scoring of people.
- No points, balances, reversals or audit of awards. That is Dopemine's ledger.
- No contribution content, titles, questions, answers, e-mails, names or organisation data.
- No expiry of consent by itself: a grant stays until the person revokes it (or InfoDot erases them).

## Storage

Rows are ordinary `loop_records` (`platform=infodot`, `subject_type=person`, `subject_id=<user_uuid>`, stage
`observation`, `event_id=participation:infodot:<event_id>` so it cannot collide with the Sanctum API's ids). Consent is
`participation_consents`. The counting and pruning queries select participation rows by `source LIKE 'participation:%'`
and `signature LIKE 'expertise:%'`; revoke and erase are wider on purpose and remove every person-subject row for that UUID,
whatever platform wrote it.

The Sanctum intelligence API no longer answers for people without consent, and never returns participation rows: see
the next section.

## The generic API and person subjects

`App\Services\Memory\LoopRecordAccess` is the single place that decides what the Sanctum `/api/intelligence/*` API
may do with records about a person. The rule:

| Request | Result |
|---|---|
| `GET /api/intelligence/context` with `subject_type=person` (any case or padding) and no current consent for `subject_id` | `403 {"code":"consent_required"}`, the same answer whether or not anything is held. |
| The same with current consent | `200`, built only from person-subject rows that already exist from other platforms (new ones cannot be written; see below). Participation rows (`source` or `event_id` or `loop_id` starting `participation:`, and everything with `platform=infodot` and `subject_type=person`) are never included; they are readable only through `GET /api/participation/{user_uuid}/context`. |
| Any other subject type | Exactly as before. |
| `POST events\|decisions\|actions\|outcomes` for a person subject, or with a `participation:`-prefixed `source`, `event_id` or `loop_id`, or whose `event_id` addresses an existing person or participation row | `403 {"code":"person_subject_reserved"}`. Person records are written only through this participation API, which checks consent, validates a closed field list and applies retention. |

The filter is applied in SQL to every read of `loop_records` (`LoopRecordAccess::readable()`), not after loading.
`LoopRecordAccessGuardTest` fails if a new route or class touches `loop_records` without being classified.
