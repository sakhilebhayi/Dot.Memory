# SLA-Breach Detection & Escalation — Design Spec

**Status:** Approved by user, ready for implementation planning.
**Platform:** Dot.Memory
**Date:** 2026-08-09

## Context

`Dot.Brain/platforms/dot-memory.md`'s Autonomy Classification audit (§Level 1, §Level 2,
2026-08-08) confirmed zero automation in this codebase — no `app/Console/Commands`, no
`Schedule::` entries, no `app/Jobs`, no `app/Notifications`. It names its own suggested Level 1
candidate as a weekly `memory.integrity.check_completed` durability check writing to
`durability_outcomes`.

That candidate cannot be honestly built: `DurabilityOutcome`/`StorageTier`'s own docblocks state
this app "never models the content that lives in the tier" — it owns no actual storage to check
against. Today `DurabilityOutcome` rows exist only via the one-time demo seeder
(`database/seeders/DatabaseSeeder.php:228`); a scheduled command claiming to "perform a check"
here would have nothing real to check and would just fabricate pass/fail numbers. That is not
something this program builds.

A second, genuinely groundable gap exists in the same domain: wiki.md §5 documents a real,
already-modeled Retrieval SLA Contract — four `RetrievalClass` rows, each with `p95_target_ms`,
`p99_target_ms`, `completeness_required`, `zero_loss_required`, and a human-readable
`breach_action` — paired with per-window `RetrievalObservation` rows carrying `sla_met` and
`degraded_mode_triggered` flags. Verified directly: `grep -rn "RetrievalObservation::create\|
sla_met\|degraded_mode_triggered" app database` shows these flags are only ever hand-set by the
seeder (`database/seeders/DatabaseSeeder.php:162-217`) — nothing in application code ever
evaluates an observation against its class's contract. The existing `SlaDashboard` Livewire
component (`app/Livewire/Memory/SlaDashboard.php`) already reads and renders both flags plus the
class's `breach_action` text — the display path is real and complete; only the evaluation logic
that should feed it is missing.

Presented to the user as an explicit choice; **the user chose to build the SLA-breach detection
job** over the durability-check candidate.

A follow-up gap surfaced during exploration: `RetrievalObservation` rows have no real creation
path at all today — only the seeder. Every other scheduled job built this program (Central's
sessions, Billing's invoices, Auction's auctions) reacts to data created continuously through real,
already-existing app usage; this table has none. Presented to the user as a second explicit
choice — build a minimal manual recording capability alongside the detection job, or detection
only. **The user chose to add the recording capability too**, matching the precedent already set
for Dopemine's hand-recorded ledgers.

## Goal

Implement the missing SLA-contract evaluation logic wiki.md §5 describes: a shared evaluator used
both by a new minimal recording form (so real observations can exist) and by this platform's first
scheduled job, which re-evaluates the latest observation per class daily and, for the two classes
whose documented `breach_action` is itself a human governance instruction, raises an escalation
for admin review.

## 1. Shared evaluation logic

New `App\Services\RetrievalSlaEvaluator`:

```php
public function meetsContract(RetrievalClass $class, array $metrics): bool
{
    if ($metrics['p95_latency_ms'] > $class->p95_target_ms) {
        return false;
    }

    if ($class->p99_target_ms !== null
        && $metrics['p99_latency_ms'] !== null
        && $metrics['p99_latency_ms'] > $class->p99_target_ms) {
        return false;
    }

    if ($class->zero_loss_required && $metrics['failure_count'] > 0) {
        return false;
    }

    return true;
}
```

- `$metrics` is a plain array with keys `p95_latency_ms`, `p99_latency_ms`, `failure_count` —
  accepting a plain array (not a `RetrievalObservation`) lets the recording form call this
  *before* a model instance exists, and lets the scheduled job call it against an existing
  instance's attributes without a wrapper.
- `completeness_required` has no corresponding measured field on `RetrievalObservation` today (no
  "completeness" number is ever recorded) — the evaluator does not invent one. Only what is
  genuinely measurable (p95/p99 latency, zero-loss) factors into the computation. This is a
  deliberate, honest scope limit, not an oversight — see Out of Scope.
- One method, used identically by both call sites below — a single source of truth for what
  "meets contract" means, matching wiki.md §5's own table exactly.

## 2. Recording form

Added to the existing `SlaDashboard` Livewire component (`app/Livewire/Memory/SlaDashboard.php`),
where breach status already renders. Gated by `hasTeamRole($team, 'admin')` — this app's own
existing role vocabulary (confirmed: exactly `admin`/`editor` in
`app/Providers/JetstreamServiceProvider.php`), reusing the standing "team admin stands in for a
not-yet-built dedicated authority" pattern from every other platform this program (here, wiki.md
§7's undocumented "SRE Lead" role, the same shape as Dopemine's Ethics Officer stand-in).

An admin picks a `RetrievalClass` and logs `window_start`, `window_end`, `request_count`,
`failure_count`, `p50_latency_ms`, `p95_latency_ms`, `p99_latency_ms`. On save, `sla_met` and
`degraded_mode_triggered` are computed immediately via `RetrievalSlaEvaluator::meetsContract()` —
not left pending for the scheduled job. The job's role (below) is to keep already-recorded
observations consistent with the *current* contract, not to perform the first evaluation.

## 3. Level 1 — `memory:scan-sla-breaches`

This platform's first scheduled command, `->daily()`. For each `RetrievalClass`, finds its latest
`RetrievalObservation` (by `window_end` desc, matching the existing
`RetrievalClass::latestObservation()` helper and the dashboard's own "current state" concept) and
re-runs the evaluator against it. If the freshly-computed `sla_met`/`degraded_mode_triggered`
differ from what's stored, updates the row.

This has genuine, ongoing value beyond a one-time backfill: wiki.md §7 states SLA definitions are
"reviewed semi-annually" — a target change on `RetrievalClass` can make a previously-compliant
observation newly out of contract, and this scan is what keeps the dashboard's flags truthful
against the *current* contract rather than the contract that existed when the observation was
first recorded.

Pure computation — no mutation to anything but these two flags on already-existing rows.

## 4. Level 2 — escalation for governance-tier breaches only

Immediately after the Level 1 pass, for classes where `completeness_required` or
`zero_loss_required` is `true` — `retr:audit:warm` and `retr:archive:cold`, matching wiki.md §5's
own table exactly (not a re-derived threshold; these are the two rows whose documented "on breach"
text is itself a human instruction: "Escalate to SRE Lead + Security Agent" and "Integrity
incident, mandatory pack") — if the latest observation is now a breach (`sla_met === false`):

- If an `open` `RetrievalSlaEscalation` already exists for that class, refresh its
  `retrieval_observation_id` to point at the current latest breaching observation (fresher
  evidence for the reviewing admin) but preserve its original `detected_at` — the same
  refresh-not-duplicate pattern used for Dopemine's retirement candidates and Design's drift
  notices.
- Otherwise create one, with `breach_action` snapshotting the class's own `breach_action` text at
  detection time (so the record stays meaningful even if the class's `breach_action` text changes
  later) and `detected_at = now()`.

An escalation for a class that recovers (`sla_met` becomes `true` again) is **not** auto-cleared —
a human already needs to look at what happened, mirroring the reasoning already established for
Dopemine's retirement candidates: a governance-tier breach has real stakes, unlike Design's
drift notices, which have none.

`RetrievalSlaEscalation` fields: `retrieval_class_id`, `retrieval_observation_id`,
`breach_action`, `status` (`open`/`acknowledged`, default `open`), `detected_at`,
`acknowledged_by`, `resolution_detail`, `acknowledged_at`.

### Naming note: `resolution_detail`, not `review_notes`

Every other Level 2 review action built this program (Central's dismiss, Dopemine's decertify
reason, the HR/Farms notification designs) used a `notes`/`review_notes`-shaped field. This
platform has its own structural content-safety invariant
(`tests/Unit/StoreWithoutReadingInvariantTest.php`) asserting that no domain model's `$fillable`
may contain a field name matching a list of content-shaped fragments — which explicitly includes
`note`, `comment`, and `description`. `review_notes`/`notes` would trip that pattern. The
free-text acknowledgment field is named `resolution_detail` instead — it matches none of the
forbidden fragments and reads naturally, while still functioning identically (a required free-text
field explaining what the admin did in response). This is a deliberate adaptation to this
platform's own established naming culture, not a functional change from the pattern used
elsewhere.

`RetrievalSlaEscalation` is also added to `StoreWithoutReadingInvariantTest`'s coverage — the same
structural strengthening move already applied when touching adjacent domains this program (e.g.
Dopemine's `WellbeingObservation` becoming the fourth layer of that platform's ethics gate).

## 5. Review UI

An "Escalations" section added to `SlaDashboard`'s view, admin-gated (same check as the recording
form), visible only when open escalations exist. One action — **Acknowledge** — requiring
`resolution_detail` (matching the "notes required" pattern used everywhere else this program, just
under this platform's own field name). There is no "confirm vs. dismiss" binary here, unlike
Dopemine's retirement candidates: there is no further system action to execute (nothing to
decertify or settle) — the class's own `breach_action` text already tells the admin what to do
outside the app (page SRE, file an incident); acknowledging just records that a human saw it and
what they did.

## Out of Scope

- The durability-check candidate the audit itself suggested — explicitly rejected as unbuildable
  honestly (see Context).
- Evaluating `completeness_required` — no measured "completeness" field exists on
  `RetrievalObservation` and this spec does not invent one.
- A real cross-platform telemetry ingestion pipeline (the `memory.sla.breach`/
  `memory.tier.migration_completed` events wiki.md §7 describes) — the manual recording form is a
  deliberate MVP stand-in, matching this program's established "hand-recorded ledger" pattern
  (Dopemine's `MechanicOutcome`/`WellbeingObservation`), not a replacement for real ingestion.
- Editing or deleting recorded observations or escalations after creation — append-only, matching
  every other hand-recorded ledger this program.
- Any change to `DurabilityOutcome`, `Index`, or `StorageTier` — untouched by this spec.

## Testing Notes

- `RetrievalSlaEvaluator::meetsContract()`: a p95 breach fails regardless of p99/failures; a p99
  breach fails only when the class has a `p99_target_ms` set (surface's class has none); a
  zero-loss class fails on any nonzero `failure_count` even when latency is fine; a class with
  `zero_loss_required = false` tolerates failures.
- Recording form: admin can save an observation with immediately-correct `sla_met`/
  `degraded_mode_triggered`; a non-admin cannot record.
- `memory:scan-sla-breaches`: a class whose contract changed since its latest observation was
  recorded gets its flags corrected; a class with no observations is skipped without error; a
  breach on `agent-context`/`surface` (non-governance tiers) updates flags but raises no
  escalation; a breach on `audit`/`archive` raises one; a second breach on an already-open-escalation
  class refreshes `retrieval_observation_id` but preserves `detected_at`; a class that recovers
  leaves its existing open escalation untouched (not auto-cleared).
- Review UI: a non-admin cannot acknowledge; acknowledging without `resolution_detail` is
  rejected; acknowledging a real escalation sets `status = acknowledged`, `acknowledged_by`,
  `acknowledged_at`.
- `StoreWithoutReadingInvariantTest`'s new `RetrievalSlaEscalation` coverage passes with
  `resolution_detail` present, confirming the naming choice actually satisfies the invariant it
  was chosen to satisfy.
