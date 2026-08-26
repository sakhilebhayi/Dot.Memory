# Dot.Memory Dashboard — "Control Room" Design

**Status:** approved direction, ready for implementation planning
**Date:** 2026-08-26

## Why this exists

The dashboard was rebuilt on tokens and components earlier today, and it still read as
generated output. The reason was specific, not vague: near-black ground, one bright indigo
accent, equal metric tiles in a grid, floating cards with gaps. That is the default shape of
every AI-produced dashboard. The earlier pass fixed hygiene — accessibility, responsiveness,
duplication — but never made an aesthetic decision, and hygiene is not taste.

This spec makes the decision.

## The direction

**A control room.** Dot.Memory is where an operator sits to work out what happened and what
usually fixes it, for an ecosystem whose first platform runs a mine. The instrument panel is
the honest metaphor: dense, calm, legible at a glance, built for someone who reads it for an
hour rather than admires it for five seconds.

Three consequences follow, and they are the whole design:

1. **Panels share edges.** No floating cards, no gutters between them. Regions are divided by
   hairlines, the way an instrument is. This single rule does more than any colour choice to
   stop the page reading as a SaaS template.
2. **A persistent state bar.** A strip across the top carrying one lamp, the current state in
   words, and which platforms are covered. It is the instrument's pulse and it never leaves.
3. **Figures are readouts.** Monospace, zero-padded (`02`, `27`), right-aligned in tables.
   Prose is sans. The two never mix roles.

### Day and night are both first-class

Control rooms have day and night display modes, so this is authentic rather than a
concession. Night is the default. Day is **not** an inversion: lamp amber darkens from
`#f2a70b` to `#8a5c07` to hold contrast on light, glow is dropped (a night affordance), and
rules lighten rather than simply flipping.

## Palette

Amber is the signal colour because it is simultaneously Dot.Memory's own brand accent
(sampled from the logo, already used on the signed-out page) and the universal control-room
signal. Brand and domain agree; take it.

| Role | Night | Day |
|---|---|---|
| Ground | `#0b0e12` | `#f7f8f9` |
| Raised / bar | `#0f141a` | `#eef1f4` |
| Rule | `#202a36` | `#d3d9e0` |
| Rule, inner | `#1a222c` | `#e2e6ea` |
| Text | `#e8eef4` | `#14181d` |
| Text, secondary | `#7e93a8` | `#54606d` |
| Text, label | `#5f6a77` | `#78828f` |
| Signal (attention) | `#f2a70b` | `#8a5c07` |
| Good | `#4d8c5f` | `#2f7043` |
| Inert / neutral bars | `#2b3a49` | `#c3ccd6` |

Status is never carried by colour alone: every lamp is paired with a word (`PROVEN FIX`,
`NO RELIABLE FIX`, `WATCHING`).

## Typography

Unchanged from the platform's documented identity — Space Grotesk (display), IBM Plex Sans
(prose), IBM Plex Mono (all figures, labels and state words). Micro-labels are uppercase mono
at ~8.5px with `.14em` tracking. Prose never goes below 11px.

## Structural change: the pattern is the object

Today incidents are primary and patterns are a derived "Insights" page. That is backwards for
how this gets used. A **pattern** (one platform + one signature) becomes a first-class object
with its own page, and individual incidents are its occurrences.

The pattern page carries, in one page:

- Header: the pattern in plain language, times seen, times fixed, current verdict.
- Recurrence strip: when it struck across the window.
- **Occurrence ledger**: one row per occurrence — when, what was tried, what resulted, how
  long it took. Figures monospace and aligned.
- **Expand in place**: opening a row reveals that incident's full narrative (what happened /
  what was learned / why it matters / evidence) inline, without navigating away. Closing
  returns you to the ledger with your place intact.

This is the answer to needing both the individual record and the pattern: the ledger is the
comparison, the expansion is the narrative, and neither costs you the other.

## Pages

| Page | Change |
|---|---|
| Overview | State bar; one lead sentence naming what needs a person; three shared-edge readout panels (needs a person / resolved 7d / consulted 7d); pattern ledger below. No metric tile grid. |
| Patterns (new) | The object above. Replaces the current Insights page as the primary investigative surface. |
| Knowledge | Becomes the search surface over occurrences; keeps filters, adopts ledger density. |
| Timeline | Kept; restyled to instrument rules, day grouping retained. |
| Incident | Remains addressable by URL for linking, but expansion in the pattern ledger becomes the usual path in. |
| Reliability | Restyled onto the same panels and tables so it stops looking like a different product. |

## Non-negotiables carried forward

Everything the previous pass established stays: visible keyboard focus, skip link, labelled
controls, `aria-expanded` on disclosures, reduced-motion guard, no colour-only status, no
horizontal overflow, responsive collapse, the toast host, and the accessible modal. The
component library gains variants rather than being replaced — `x-dot.panel` joins
`x-dot.card`, and pages continue to compose rather than restyle.

Expansion rows must be a real disclosure: a `button` with `aria-expanded` and `aria-controls`,
not a clickable div.

## Out of scope

- The intelligence-loop APIs and every backend contract stay exactly as they are.
- No new dependencies; Alpine comes from Livewire (the duplicate CDN copy is already gone).
- Dot.Mines and other platforms are untouched. If the panel vocabulary proves out here, it can
  be proposed to the shared design system later — not now.

## How we will know it worked

- The page no longer resolves to "dark card grid": panels share edges, and there is no
  four-tile metric row anywhere.
- Someone can answer "does anything need me, and has this happened before?" without scrolling.
- Both day and night pass contrast checks and are visually deliberate, not inverted.
  Measured, not assumed: every text token clears 4.5:1 against **both** `--panel` and
  `--panel-sunken`, since a token that passes on one and fails on the other still fails
  wherever the ledger detail band and the sidebar are drawn. The one deliberate exception
  is `--text-ghost`, used solely for a readout's padding zeros: those are `aria-hidden`
  and the plain figure is exposed to screen readers, so they are a visual device rather
  than text.
- The full existing suite stays green, with new tests for the pattern page and the
  expand-in-place disclosure.
