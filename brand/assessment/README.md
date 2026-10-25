# Brand experience baseline: Dot.Memory (automated static checks only)

> **Advisory. Written by an AI session. No profile is ratified.** This is a first baseline from the Brand Feeling Engine. It reads this repository's source with the engine's static checks and nothing else: no rendered pages, no assessment by a human or an AI expert, no observed behaviour, no customer feedback. Nothing here blocks a merge or a deploy. The brand profile it is measured against is the shared ecosystem draft, which the owner has not ratified, and this repository has no product profile of its own yet. Scores are low on purpose: every check that could not run counts as zero and is listed.

| | |
|---|---|
| **Evidenced score** | **14.7 / 100** |
| Ceiling for this kind of evidence | 62.2 |
| Attainment | 23.63 % |
| State | `not_ready` |
| Confidence | Low |
| Blockers | 2 |
| Commit analysed | `c57c2f7bf753c60e909f1a93f4b76ac9f65cb91d` |
| Engine | 0.3.0 |
| Rubric | bxqs 1.0.0 (sha256 `f5a4f8cb49b821bb41cb2ea3a5355089f87e60e6233cc6439a0f7ea680342bbd`) |
| Profile | dot-ecosystem 0.1.0 (draft; sha256 `e21c37306843707affd13abbb1eca37c8b4102aafa79336411163dd88ca0dde0`) |

## Dimensions

| Dimension | Status | Points earned / weight | Ceiling (this evidence) |
|---|---|---|---|
| Emotional clarity and positioning | partially evidenced | 0.4781 / 8 | 4 |
| Brand distinctiveness | partially evidenced | 0.525 / 7 | 4.2 |
| Usability and cognitive load | partially evidenced | 1.9309 / 12 | 7.2 |
| Visual and interaction craftsmanship | partially evidenced | 1.1794 / 9 | 7.2 |
| Functional completeness | partially evidenced | 4.55 / 10 | 9 |
| Trust and transparency | partially evidenced | 0.8 / 10 | 7 |
| Perceived and demonstrated value | not measured | 0 / 8 | 4 |
| Accessibility and inclusion | partially evidenced | 3.5399 / 12 | 8.4 |
| Cross-platform consistency | partially evidenced | 0.42 / 6 | 4.8 |
| Reliability and recovery from errors | partially evidenced | 1.2799 / 8 | 6.4 |
| Retention through continuing value | not measured | 0 / 5 | 0 |
| Measurable customer outcomes | not measured | 0 / 5 | 0 |

## Blockers recorded by the engine

- An unsupported claim was found. (`d6.claim_lint.41f818897e3f`, `resources/views/welcome.blade.php`)
- WCAG AA failure of criterion 1.4.3. (`d8.token_pair_contrast.2e40f4432a4c`, `resources/views/layouts/app.blade.php`)

## What the engine would have you do first

12 task(s) were proposed; the first six, in the engine's order:

1. Resolve d6.claim_lint: Remove the claim. Outcome, return and performance statements need a cited, current source 
2. Resolve d8.token_pair_contrast: Adjust one of the two tokens until the pair reaches 4.5:1 on the light theme, or stop usin
3. Resolve d10.health_endpoint: Make the endpoint return a 503 when the database or another required dependency fails, so 
4. Resolve d8.labels_alt_lang: Add a visible <label for="id"> (preferred) or an aria-label. If the label lives in another
5. Resolve d8.focus_visible: Add focus-visible:ring-2 (or an outline) beside outline-none, or drop outline-none.
6. Resolve d8.labels_alt_lang: Use a <button> or <a href>.

The full list, with files and lines, is in `2026-10-25-c57c2f7.plan.md`.

## What this baseline is not

- It does not say how the product feels to a person. Dimensions 7 (perceived value), 11 (retention) and 12 (customer outcomes) cannot be measured from source and stay at zero.
- 19 automated check(s) had no result (not built yet, or they need a rendered page, axe, Lighthouse or a test report); they count as zero and are named in the score file under `missing_evidence`.
- Static checks can raise false positives (for example a label that lives in another file, or a template that is never mounted); each finding names the file and line so it can be dismissed.
- `release_ready` needs observed or customer evidence that does not exist for any Dot product yet. The score will move when assessments by a fresh reviewer and real sessions are added.

## Files

- `2026-10-25-c57c2f7.json`: the score report (the numbers above)
- `2026-10-25-c57c2f7.evidence.json`: the evidence that was scored
- `2026-10-25-c57c2f7.analysis.json`: the raw static analysis
- `2026-10-25-c57c2f7.plan.md`: the ranked task list
