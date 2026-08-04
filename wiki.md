---
title: Dot.Memory — Platform Wiki
version: 0.5.0
status: draft
owners: [Memory Platform Lead]
platform-id: dot-memory
last-review: 2026-08-04
---

# Dot.Memory

Purpose: this is Dot.Memory's own knowledge home — owned and maintained by the Dot.Memory team. It describes what this platform is, what it stores, and how it connects to the wider Dot Ecosystem. Dot.Brain never edits this file; it only reads what we choose to publish.

> **Related:** [Dot.Brain's ingested view of this platform](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-memory.md) · [Dot.Brain's Memory Orchestration spec](https://github.com/sakhilebhayi/Dot.Brain/blob/main/brain.memory.md)

---

## 1. What Dot.Memory Is

Dot.Memory is the persistence substrate for the Dot Ecosystem: knowledge-graph storage, vector indexes for semantic retrieval, Knowledge Pack archives, and the audit trails that every other platform's governance gates write to. It is infrastructure, not a knowledge consumer — Dot.Memory stores content without reading it.

**Status:** early-stage, now with a first hand-authored code pass. This repository contains a
Jetstream Teams application shell plus the domain models, migrations, dashboard, and seeder
described in §3–§5 below — but it is **unverified**: it was written in an environment without
PHP, Composer, or PostgreSQL, so nothing here has actually been installed, migrated, or run. Treat
every section below as the current design intent that the code *attempts* to follow, not proven
shipped behavior, until a real `composer install && php artisan migrate && php artisan test` pass
confirms it and the change log is updated accordingly.

## 2. Design Principle: Store Without Reading

The one rule that shapes everything else here: **Dot.Memory the infrastructure stores knowledge without reading it; Dot.Memory the platform publishes only its own operational telemetry** (latency, durability, integrity — never content). A storage platform that mined its tenants' knowledge would be the ecosystem's deepest possible trust violation, so the telemetry plane and the data plane are architecturally separate, not just policy-separate.

## 3. Storage Tiers

| Tier | Contents | Latency target | Backing |
|---|---|---|---|
| Hot | Actively referenced graph nodes/edges, verified lessons, registries | ≤ 50 ms p95 (per-op) | Graph store + cache projections |
| Warm | Unreferenced 90 days–2 years, provisional conclusions, dormant edges | ≤ 2 s p95 | Warm store |
| Cold | Superseded/retracted knowledge, full ledger history | ≤ 5 min (async) | Immutable cold archive |

Promotion is demand-driven; demotion is policy-driven and logged. Nothing skips Cold to deletion — see §6.

## 4. Entities We Own

| Entity | Natural key | Notes |
|---|---|---|
| Storage tier | tier ID | hot / warm / cold |
| Index | index ID + version | graph, vector, and audit-log indexes |
| Retrieval class | `retr:<consumer-class>:<tier>` | the unit of our SLA contract, §5 |
| Retrieval observation | class × tier × window | latency/failure aggregates — telemetry only |
| Durability outcome | audit period | verified restore tests, integrity checks |

Stored content itself is never modeled as our entity — it belongs to whichever platform published it.

## 5. Retrieval SLA Contract

Four named, consumer-visible contracts:

| Class | Serves | Contract | On breach |
|---|---|---|---|
| `retr:agent-context:hot` | Colony agents assembling working context | p95 ≤ 800 ms, p99 ≤ 2 s | Degraded-mode flag: agents disclose stale-context risk |
| `retr:surface:hot` | Human-facing surfaces | p95 ≤ 1.5 s | Serve cached-with-timestamp instead of blocking |
| `retr:audit:warm` | Governance/audit-log access | p95 ≤ 30 s, completeness guaranteed | Escalate — audit failure is a governance event |
| `retr:archive:cold` | Compliance retrieval | ≤ 24 h, zero-loss | Integrity incident, mandatory pack |

How retrieval fails is specified, not improvised — degraded-mode behavior is part of the contract, not an afterthought.

## 6. Forgetting Policy

We never delete knowledge; we let it lose salience:

1. **Supersede** — the only mutation; superseded versions demote toward Cold with the chain intact.
2. **Dormancy** — confidence decay stops a node from being served by context retrieval, but it stays retrievable by provenance.
3. **Expiry** — `valid_until` passing behaves like dormancy and flags the node for a successor observation.
4. **Never-forget set** — verified lessons, the ledger, and hard-invariant evidence are exempt from demotion below Hot/Warm.
5. **Legal erasure** — the one real tension with never-delete; standing design is crypto-shredding (destroy the key, not the record) — see Dot.Brain's [ADR-0009](https://github.com/sakhilebhayi/Dot.Brain/blob/main/adr/ADR-0009-crypto-shredding-legal-erasure.md).

## 7. Events We Emit

| Event | Trigger | Frequency |
|---|---|---|
| `memory.sla.breach` | A retrieval class misses its contract | target: 0 |
| `memory.tier.migration_completed` | Content ages between tiers | daily cycles |
| `memory.integrity.check_completed` | Scheduled durability verification | weekly |

## 8. Connecting to Dot.Brain

Dot.Memory participates in the ecosystem as a registered platform (`dot-memory`) that publishes Knowledge Packs about its own operation — never about the content it stores.

| Payload type | Cadence | Contains |
|---|---|---|
| `observation` | weekly | retrieval-performance aggregates |
| `insight` | per finding | access-pattern *shapes* only — never query text or result content |
| `outcome` | per period | SLA and durability verification results |
| `incident` | per incident | SLA breaches, integrity events |

We subscribe to Dot.Brain recommendations on index strategy, tier-policy tuning, and capacity forecasting. Full manifest, entity/event mapping, and a worked publish→PR round-trip are maintained on the Brain side at [`platforms/dot-memory.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-memory.md) — that document is Dot.Brain's ingested view and is authoritative for integration mechanics; this wiki is authoritative for what Dot.Memory actually *is*.

## 9. Roadmap

- [x] Scaffold the Jetstream Teams application shell (auth, profile, 2FA, API tokens, ecosystem SSO) — **unverified, not yet run**
- [x] Model storage tiers, indexes, retrieval classes, retrieval observations, and durability outcomes as Eloquent models + migrations — **unverified, not yet migrated**
- [x] Build a first SLA-attainment / index-inventory / durability-outcomes monitoring dashboard — **unverified, not yet rendered**
- [x] Seed realistic example telemetry matching the four SLA classes' targets — **unverified, not yet seeded**
- [ ] Actually install, migrate, and test the above against a real PHP/Postgres environment
- [ ] Stand up the real graph store and vector index (hot tier) — the current app only tracks index *metadata*, not a real index
- [ ] Wire the four retrieval SLA classes to real degraded-mode behavior (the schema models the contract; nothing yet measures live traffic against it)
- [ ] Publish the first `observation` Knowledge Pack (hello-pack per Dot.Brain's onboarding procedure)
- [ ] Warm/cold tiering automation
- [ ] Crypto-shredding implementation for legal erasure

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 0.5.0 | 2026-08-04 | Platform-loop pass | **Architecture-hardening pass matching the Dot.Finance/Dot.Notify pattern (see Dot.Finance commit `2f75bdb`, Dot.Notify commit `e671436`) — investigated, and deliberately did *not* add a tenant-scope trait.** Read every model in `app/Models` and the `2026_08_01_100002_create_memory_tables` migration: `StorageTier`, `Index`, `RetrievalClass`, `RetrievalObservation`, and `DurabilityOutcome` — Dot.Memory's entire domain schema — have **no `user_id` or `team_id` column at all**, by explicit design (see `Team.php`'s own comment: "Tenant key = owning platform … Team intentionally has no relations into the domain models below", and §7/§2 of this wiki: telemetry is ecosystem-level, not per-team/per-user). This was already independently verified once before, in the 0.2.1 changelog entry, which confirmed `/dashboard`, `/indexes`, and `/durability` are correctly gated only by `auth:sanctum` + `verified` with no team-scoping. Grepping `app/` and `routes/` for `user_id`/`team_id`/`currentTeam` turned up exactly two hits, both Jetstream/Fortify framework code unrelated to the domain models (`CreateNewUser`'s personal-access-token `user_id`, `InviteTeamMember`'s membership `team_id` lookup) — nothing in `app/Http/Controllers/Memory/*` or `app/Livewire/Memory/*` filters by tenant, because there is no tenant column to filter by. `NotificationBell` (the one place per-user scoping legitimately applies — Jetstream's own `notifications` table) was already correctly scoped to `auth()->user()->notifications()` before this pass; left untouched. **Conclusion: applying `HasUserScope` or `HasTeamScope` here would contradict this platform's own documented trust boundary** ("store without reading" / telemetry-plane-not-data-plane, wiki.md §2) by pretending these ecosystem-shared models are per-tenant when they are not — so no trait was created, no model was changed, no controller/Livewire query was touched, and no cross-tenant regression test was added, because there is no tenant boundary here for such a test to prove. This is the intended "skip legitimately global/shared catalog models" outcome the rollout's own instructions called for, not an incomplete pass. Added `phpstan.neon.dist` (level 5, Larastan extension) as dev tooling anyway, matching the rollout's tooling baseline — config present, execution unverified in this sandbox (`vendor/bin/phpstan analyse --memory-limit=1G` produced no stdout/stderr and exit code 1 with no diagnostic, consistent with the same silent-failure behavior recorded on every other platform in this rollout). `composer audit` found 6 pre-existing advisories (1 high — noncanonical-host check bypass, CVE-2026-69246 — and 5 medium) against `guzzlehttp/guzzle`, predating this pass; fixed via `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises --with-all-dependencies` (7.12.3 → 7.15.2) — `composer audit` clean afterward. Verified for real against a fresh, isolated `dot_memory_pilot` PostgreSQL 16 database (created and dropped for this pass, not the persistent `dot_memory_verify` db `.env` otherwise points at): `php artisan migrate` (10 migrations, clean) and `php artisan test` both before and after the Guzzle bump — 14 tests, 14 passed, 0 failed, identical counts pre- and post-update, so the dependency bump is behavior-neutral. |
| 0.4.0 | 2026-08-03 | Sakhile Bhayi | Redesigned `resources/views/welcome.blade.php`'s marketing surface: it had shipped as the untouched default Laravel/Jetstream scaffold page (no nav, no hero, no brand mark — just the "Let's get started" documentation card), so this pass builds a full custom marketing page from scratch — nav + hero + features + storage-tiers table + CTA + footer — matching the structural pattern already piloted on `mines`' `welcome.blade.php`. Nav and footer brand marks use the real `public/images/logo.png`. Hero background is a real, licensed Unsplash photo of a server rack (photo by Kevin Ache, @kevinache, unsplash.com/photos/a-rack-of-servers-in-a-server-room-2JJ3wBHu4_0), hotlinked via Unsplash's CDN with a dark gradient overlay tuned for WCAG-adequate text contrast, photographer credited inline as an HTML comment. Copy is drawn only from this wiki's real §1/§3/§5 content (storage tiers, retrieval SLA contracts, forgetting policy) — no fabricated stats or testimonials. The CDN image URL was verified with `curl -sI` before use (HTTP/2 200). |
| 0.1.0 | 2026-08-01 | Memory Platform Lead | Initial wiki: architecture blueprint derived from Dot.Brain's platforms/dot-memory.md and brain.memory.md, adapted to platform-owned framing |
| 0.3.0 | 2026-08-02 | Sakhile Bhayi | **Executed for real, for the first time** — and it did not boot: `storage/framework/{cache,sessions,testing,views}`, `storage/app/{public,private}`, and `bootstrap/cache/.gitignore` were entirely absent from this repo (unlike the platform it was copied from), so nothing had ever created them and `realpath(storage_path('framework/views'))` returned `false`, crashing Laravel's Blade compiler on the very first `composer install`. Fixed by adding the missing directories with their standard `.gitignore` placeholders, matching Dot.Plug's real structure. After the fix: `composer install`, `migrate` (10 migrations, clean), and the full test suite all ran clean — 14 tests, 14 passed, 0 failed, including the "store without reading" invariant tests, now genuinely verified rather than "verified directly against the schema" by review alone. |
| 0.2.0 | 2026-08-01 | Claude Sonnet 5 (AI, hand-authored scaffolding pass) | First code pass: Jetstream Teams shell copied from Dot.Billing's verified boilerplate; domain models/migrations for StorageTier, Index, RetrievalClass, RetrievalObservation, DurabilityOutcome; SLA/index/durability monitoring dashboard; seeder with example telemetry matching §5's targets; structural test asserting no model can hold tenant content. **Entirely unverified** — written without PHP/Composer/PostgreSQL access, so nothing has been installed, migrated, or run. Roadmap items above marked done reflect code written, not code proven working. |
| 0.2.1 | 2026-08-01 | Claude Sonnet 5 (AI, incremental pass) | Re-verified access scoping: `/dashboard`, `/indexes`, `/durability` are correctly gated only by `auth:sanctum` + `verified` (no team-scoping), matching the ecosystem-wide telemetry design in §2/§4 — nothing incorrectly restricts or under-restricts access. Checked every Livewire component (`SlaDashboard`, `IndexInventory`, `DurabilityOutcomes`, `NotificationBell`) for the unchecksummed-argument IDOR pattern found elsewhere this session — none exists; the only record-scoped method (`NotificationBell::markAsRead`) is correctly scoped to `auth()->user()->notifications()`. Re-verified "store without reading": all five domain models' `$fillable` arrays and the `2026_08_01_100002_create_memory_tables` migration still contain zero content-shaped columns, and `StoreWithoutReadingInvariantTest` still covers all five models — the invariant is fully intact. Found and fixed one concrete gap: the dedicated `/indexes` (Index Inventory) and `/durability` (Durability Outcomes) pages existed with real routes, controllers, and views but had no navigation links anywhere (desktop or mobile nav) — reachable only by hand-typing the URL. Added nav links in `resources/views/navigation-menu.blade.php`. |

## Open Questions

- Regulator direct access to `retr:audit:warm`: mediated through the consuming platform, or a standing authenticated read path?
- Cold-tier retention horizons per content class, pending regulatory jurisdiction rules.
