# Knowledge recommendations API (R-9 sections 5 and 6)

Dot.Brain runs a rules-only recommender that decides which InfoDot contributions deserve a place in the library. It writes
each result to Memory as a `decision` record through the existing Sanctum intelligence API. This API lets **InfoDot's
moderators read those recommendations and report the outcome** (adopted or declined), and lets **Brain read the
outcomes back to grade itself**.

Memory is not the ledger and holds no person data here. Every record is about a *contribution* (an opaque ref such as
`infodot:answer:123`), never a person. The only person-like value is `actor_ref`, an opaque moderator uuid that is stored
for audit and **never returned to Brain** (it is not returned to InfoDot either).

## How the pieces connect

1. Brain writes a decision: `POST /api/intelligence/decisions` (Sanctum bearer), `subject_type = infodot-contribution`,
   `subject_id = <contribution ref>`, `signature = knowledge-candidate`, `confidence`, idempotent on `event_id`.
2. InfoDot lists recommendations: `GET /api/knowledge-recommendations` (signed as `infodot`).
3. A moderator decides; InfoDot reports it: `POST /api/knowledge-recommendations/{decision_event_id}/outcome` (signed as `infodot`).
4. Brain reads outcomes: `GET /api/knowledge-recommendations/outcomes` (signed as `brain`).

### The decision record's `detail`

The verdict and the reasons live in the (encrypted) `detail` of Brain's decision record, because a decision record has
no verdict column. The shape Memory reads:

```json
{
  "verdict": "recommend_for_library",
  "rule_version": "kc-rules-1",
  "rationale": [
    {"rule": "accepted-answer", "effect": "+0.4", "detail": "The asker accepted this answer."}
  ]
}
```

- `verdict` is one of `recommend_for_library`, `watch`, `do_not_recommend`. A record with any other (or no) verdict is
  not a recommendation and is never listed.
- `rationale` entries are `{rule, effect, detail}` (strings). A plain string entry (Brain's loop schema allows them) is
  returned as `{rule: null, effect: null, detail: "<the string>"}`.
- The nested spellings `decision.verdict`, `decision.rationale`, `decision.rule_version` and `recommendation` are also
  understood. Nothing else in `detail` is ever returned.

## Authentication

Server-to-server, signed. The same scheme as the participation API (and Dot.Dopemine's points intake):

- `X-InfoDot-Timestamp`: unix seconds.
- `X-InfoDot-Signature`: `sha256=` + HMAC-SHA256(secret, `"{timestamp}.{raw body}"`). A GET signs `"{timestamp}."`.
- Window: 300 seconds either side (`KNOWLEDGE_TOLERANCE`). The query string is not part of the signed text.
- **One secret per consumer**: `KNOWLEDGE_SECRET_INFODOT` (consumer `infodot`) and `KNOWLEDGE_SECRET_BRAIN` (consumer
  `brain`). Generate each with `openssl rand -hex 32`; keep them different. A consumer whose secret is unset or empty can
  never authenticate (fails closed). If the two secrets are identical, the consumer cannot be told apart and every
  signature is refused.
- These secrets are separate from `PARTICIPATION_SECRET_INFODOT` and from Sanctum tokens; none opens the others' APIs.
- Rate limit: 120 requests per minute per client address (`KNOWLEDGE_RATE_LIMIT`), applied before the signature check.
  Over the limit: `429`.

### Permission matrix

| Endpoint | `infodot` signature | `brain` signature | unsigned / bad signature |
|---|---|---|---|
| `GET /api/knowledge-recommendations` | allowed | `403 forbidden_consumer` | `401 invalid_signature` |
| `POST /api/knowledge-recommendations/{decision_event_id}/outcome` | allowed | `403 forbidden_consumer` | `401 invalid_signature` |
| `GET /api/knowledge-recommendations/outcomes` | `403 forbidden_consumer` | allowed | `401 invalid_signature` |

All errors are `{"code": "...", "message": "..."}` (validation errors add `errors`). Examples:

```json
{"code": "invalid_signature", "message": "Invalid signature."}
{"code": "forbidden_consumer", "message": "This consumer may not call this endpoint."}
```

## Endpoints

### `GET /api/knowledge-recommendations` (infodot)

Query (all optional):

| Parameter | Values | Default |
|---|---|---|
| `status` | `open` (no outcome yet), `decided` (has an outcome), `all` | `open` |
| `verdict` | `recommend_for_library`, `watch`, `do_not_recommend` | any |
| `limit` | 1 to 100 | 50 |
| `cursor` | the `next_cursor` of the previous page | first page |

Newest first (by `decided_at`, ties by internal id). Only decisions with `subject_type = infodot-contribution` and
`signature = knowledge-candidate` are ever returned; nothing for any other subject type, person, or participation record.

```json
{
  "items": [
    {
      "decision_event_id": "evt-9f1c",
      "contribution_ref": "infodot:answer:123",
      "verdict": "recommend_for_library",
      "confidence": 0.82,
      "rationale": [
        {"rule": "accepted-answer", "effect": "+0.4", "detail": "The asker accepted this answer."},
        {"rule": "verified", "effect": "+0.3", "detail": "Two moderators verified it."}
      ],
      "rule_version": "kc-rules-1",
      "decided_at": "2026-10-23T09:00:00.000000Z",
      "status": "open",
      "outcome_at": null
    }
  ],
  "next_cursor": "WyIyMDI2LTEwLTIzIDA5OjAwOjAwIiw0Ml0"
}
```

`status` is `open`, `adopted` or `declined`, derived from the **latest** outcome row; `outcome_at` is that row's time.
`next_cursor` is `null` on the last page. The cursor is opaque. A filter that matches few rows can make the server skip
many; it examines at most 2000 decision rows per request and then returns a `next_cursor` to continue (possibly with
fewer than `limit` items, even none). Keep following `next_cursor` until it is `null`.

A contribution can have more than one decision (each Brain run has its own `event_id`); each is listed and graded
separately.

### `POST /api/knowledge-recommendations/{decision_event_id}/outcome` (infodot)

```json
{"outcome": "adopted", "actor_ref": "0b2f1d3e-5a64-4c1e-9d0a-7f31c2b8e411"}
```

- `outcome`: `adopted` or `declined`.
- `actor_ref`: the moderator's opaque uuid. Stored as given; not checked against any user table; never returned.
  Anything that is not a uuid (an e-mail address, a name) is refused with 422.

Response (no `actor_ref`):

```json
{"data": {"decision_event_id": "evt-9f1c", "contribution_ref": "infodot:answer:123", "status": "adopted", "outcome_at": "2026-10-23T10:15:00.000000Z"}}
```

| Situation | Result |
|---|---|
| First outcome for the decision | `201`, a row is appended |
| Same outcome as the latest one again | `200`, no-op (nothing written, even if `actor_ref` differs) |
| The other outcome (adopted to declined or back), within 30 days of the **first** outcome | `201`, a new row is appended; history is kept |
| The other outcome after 30 days | `409 outcome_locked`, nothing written |
| `decision_event_id` is not a knowledge recommendation (unknown, other subject type, other signature, no valid verdict) | `404 decision_not_found` |
| Bad `outcome` or `actor_ref`, or missing field | `422` |

The window (`KNOWLEDGE_CHANGE_WINDOW_DAYS`, default 30) is measured from the first outcome recorded for the decision, so
repeated flips cannot extend it.

### `GET /api/knowledge-recommendations/outcomes` (brain)

Query: `since` (optional ISO 8601 timestamp, inclusive; any offset), `limit` (1 to 500, default 100).

```json
{
  "items": [
    {"contribution_ref": "infodot:answer:123", "decision_event_id": "evt-9f1c", "outcome": "adopted", "decided_at": "2026-10-23T10:15:00.000000Z"}
  ]
}
```

Every outcome **row** is returned, oldest first, so a changed mind shows up as a later row for the same
`decision_event_id`; the latest row wins. No `actor_ref`, no moderator data, and nothing from the decision record. To poll
incrementally, pass the `decided_at` of the last item you processed as `since` (inclusive, so that item repeats; handling
it twice is harmless).

## Storage

`knowledge_outcomes` (additive, guarded migration `2026_10_23_100000_create_knowledge_outcomes_table`): `id`,
`decision_event_id` (indexed), `contribution_ref`, `outcome`, `actor_ref`, `decided_at` (indexed), `created_at`.
**Append-only**: the model refuses updates and deletes, and a test fails if application code starts doing either.

## What it deliberately does not do

- It does not read or return the `detail` of any other record, any person subject, or any participation row. Decision
  rows are read only through `LoopRecordAccess::readable()` and narrowed to one subject type and signature.
  `LoopRecordAccessGuardTest` classifies these routes and the one class that reads decisions.
- It does not change a decision record, award points, or decide anything. Memory remembers; the moderator decides.
- It stores no content, title, name or e-mail, and returns `actor_ref` to nobody.
- The existing Sanctum `/api/intelligence/*` API, the participation API and the consent gate are unchanged.
