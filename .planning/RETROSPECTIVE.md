# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — MVP

**Shipped:** 2026-05-18
**Phases:** 7 | **Plans:** 34 | **Timeline:** 3 days (2026-05-16 → 2026-05-18, 231 commits)

### What Was Built
- A ~3,900-ingredient library seeded from CIQUAL / USDA FDC / Open Food Facts via idempotent Artisan imports, with nutrition, EU 14-allergen flags, and unit conversions — plus private ingredient creation.
- Structured, versioned recipes: any-unit ingredient lines, ordered steps, nested sub-recipes with circular-reference detection, immutable version history, and a Save/Recall working-draft layer.
- A full metrics engine — nutrition, cost, food cost %, yield/scaling, shrinkage, baker's percentages, allergen roll-up — all correct through nested sub-recipes with exact `brick/math` arithmetic.
- A provider-agnostic conversational AI agent that reads the recipe + test feedback, applies validated draft edits, and creates variants.
- Recipe tests, a publishable public library, and a user-submission → moderator-promotion ingredient pipeline.

### What Worked
- **Dependency-ordered phasing.** The roadmap followed hard domain dependencies (lookups → ingredients → recipes+metrics → tests → AI → publishing → moderation). No phase was blocked waiting on an upstream gap.
- **Wave 0 RED test suites.** Every phase opened with real failing assertions (no `skip()`), giving each implementation wave a concrete green target. This caught contract mismatches early and kept VERIFICATION honest.
- **Bundling inseparable work.** Phase 3 deliberately combined recipe core with the metrics engine — metrics depend on the draft/version structures, so separating them would have stalled both. The bundle paid off.
- **Human-verify checkpoints.** 4 phases ended with a live human walkthrough; several real bugs (auth.permissions empty, builder draft desync, SSE framing) only surfaced there — `Prism::fake()` and Inertia test infra never exercised them.

### What Was Inefficient
- **Bugs that only the human checkpoint could catch.** `getPermissionNames()` returning no role-derived permissions, the React `useState` initializer not resyncing on partial reload, and SSE/XSRF streaming issues all passed automated tests and failed live. Test-only verification gave false confidence between checkpoints.
- **SUMMARY frontmatter drift.** `requirements_completed` and `one_liner` fields were inconsistently populated by plan executors — the milestone audit had to cross-reference VERIFICATION.md tables instead. `gsd-tools milestone complete` consequently produced empty accomplishments and a wrong task count (3).
- **Nyquist validation left partial.** 5 of 7 phases have a VALIDATION.md strategy doc that was never marked compliant. Phases still passed full VERIFICATION, but formal validation coverage is incomplete.

### Patterns Established
- **Wave 0 writes real assertions**, never `skip()` / `markTestIncomplete()` — the RED suite is the plan's acceptance contract.
- **Stub Inertia pages before routes** — `assertInertia()` triggers a Vite manifest lookup; the page must exist in the build or GET tests fail (learned Phase 4, reused Phases 6 & 7).
- **Deferred-FK migrations for circular references** — declare the column as plain `unsignedBigInteger`, add the constraint in a later migration.
- **Snapshot-on-publish** — published recipes store ingredient names as strings so they survive later ingredient renames/deletes.
- **Agent edits apply as deltas, not full-draft replaces** — preserves unrelated fields and keeps each Apply to a single Recall step.
- **`setLayoutProps()` inside `useEffect`** for dynamic breadcrumbs — assigning a function to `layout.breadcrumbs` throws React error #31 in production builds.

### Key Lessons
1. **A live human checkpoint catches a distinct bug class.** Streaming, CSRF cookies, role-derived permissions, and client-state resync survived green test suites. Keep an end-to-end human-verify step per phase; do not treat passing tests as sufficient for integration-sensitive work.
2. **Enforce SUMMARY frontmatter discipline.** Inconsistent `requirements_completed` / `one_liner` fields broke downstream automation (audit cross-referencing, milestone accomplishment extraction). Validate frontmatter at plan completion, not at milestone close.
3. **Wave 0 RED suites are worth the upfront cost.** Concrete failing assertions caught service/route/contract mismatches before they compounded across waves.
4. **Don't skip Nyquist validation when the workflow toggle is on.** `nyquist_validation: true` was set, yet 5 phases never completed it — partial coverage is a milestone-audit finding that could have been avoided per-phase.

### Cost Observations
- Model mix: not instrumented this milestone — no per-model telemetry captured.
- Timeline: 7 phases / 34 plans in 3 calendar days, 231 commits.
- Notable: `commit_docs: true` and `parallelization: true` with `mode: yolo` — phases ran with minimal gating, which kept velocity high; the cost was deferred bug discovery to human checkpoints.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 MVP | 7 | 34 | Baseline — dependency-ordered phasing, Wave 0 RED suites, per-phase human checkpoints |

### Cumulative Quality

| Milestone | Requirements | Phases Passed | Nyquist Compliant |
|-----------|--------------|---------------|-------------------|
| v1.0 MVP | 67/67 | 7/7 | 2/7 (phases 3, 5) |

### Top Lessons (Verified Across Milestones)

1. *(Pending second milestone for cross-validation.)* — v1.0 candidate: a live human checkpoint catches a bug class automated tests miss.
