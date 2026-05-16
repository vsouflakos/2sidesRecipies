---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: completed
stopped_at: Completed 03-recipe-core-metrics-01-PLAN.md
last_updated: "2026-05-16T21:44:19.270Z"
last_activity: "2026-05-16 — Phase 2 Plan 05 complete: ingredient detail page, verification flow, human-verify checkpoint passed"
progress:
  total_phases: 7
  completed_phases: 2
  total_plans: 20
  completed_plans: 13
  percent: 35
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-16)

**Core value:** A chef can build a structured, versioned recipe and trust the professional metrics computed from its ingredients (nutrition, cost, yield, allergens).
**Current focus:** Phase 2 — Ingredient Library

## Current Position

Phase: 2 of 7 (Ingredient Library) — IN PROGRESS
Plan: 5 of 6 complete (02-05 ingredient detail page + verification)
Status: Plan 02-05 complete — ready to begin Plan 02-06 (price recording on detail page)
Last activity: 2026-05-16 — Phase 2 Plan 05 complete: ingredient detail page, verification flow, human-verify checkpoint passed

Progress: [████░░░░░░] 35%

## Performance Metrics

**Velocity:**
- Total plans completed: 0
- Average duration: — min
- Total execution time: 0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

**Recent Trend:**
- Last 5 plans: none yet
- Trend: —

*Updated after each plan completion*
| Phase 01-foundation P01 | 11 | 3 tasks | 17 files |
| Phase 01-foundation P03 | 15 | 2 tasks | 7 files |
| Phase 01-foundation P05 | 25 | 2 tasks | 5 files |
| Phase 01-foundation P02 | 13 | 3 tasks | 10 files |
| Phase 01-foundation P05 | 30 | 3 tasks | 5 files |
| Phase 02-ingredient-library P01 | 40 | 3 tasks | 28 files |
| Phase 02-ingredient-library P03 | 45 | 3 tasks | 14 files |
| Phase 02-ingredient-library PP02 | 60 | 3 tasks | 13 files |
| Phase 02-ingredient-library P04 | 45 | 2 tasks | 13 files |
| Phase 02-ingredient-library P05 | 50 | 3 tasks | 11 files |
| Phase 02-ingredient-library P06 | 13 | 2 tasks | 12 files |
| Phase 03-recipe-core-metrics P01 | 16 | 3 tasks | 47 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Roadmap: Phase 3 deliberately bundles recipe core with metrics engine — they are inseparable (metrics depend on draft/version structures; separating would block metric development)
- Roadmap: Phase 6 (Publishing) depends on Phase 3, not Phase 5 — can be worked after Phase 3 in parallel with Phases 4 and 5
- Roadmap: Phase 7 (Moderation) depends on Phase 2 — can be worked in parallel with later phases once Phase 2 is complete
- [Phase 01-foundation]: HasRoles trait added to User model immediately as it is required for spatie/laravel-permission to function on the User model
- [Phase 01-foundation]: Wave 0 test files write real assertions rather than skip(), giving later waves concrete red-to-green targets
- [Phase 01-foundation]: Allergen slug used as firstOrCreate key (unique per EU regulation) rather than name — slug is stable canonical identifier
- [Phase 01-foundation]: base_factor cast as decimal:6 to preserve conversion precision without floating-point drift
- [Phase 01-foundation]: Route gate uses app()->isLocal() || app()->runningUnitTests() so Pest tests can reach /dev/styleguide in APP_ENV=testing
- [Phase 01-foundation]: Route declarations for admin.users.* placed in Plan 02 so Plan 04 only adds the controller
- [Phase 01-foundation]: EnsureUserIsActive placed after AttemptToAuthenticate, before PrepareAuthenticatedSession to block deactivated users without writing session
- [Phase 01-foundation]: Route gated with app()->isLocal() || app()->runningUnitTests() so Pest tests can reach /dev/styleguide in APP_ENV=testing without relaxing the production gate
- [Phase 01-foundation]: Warm-minimal token correctness for UI-02 is perceptual — human visual verification at the checkpoint is the authoritative artifact, not automated tests
- [Phase 01-foundation]: Redirect-not-JSON pattern for Inertia mutation endpoints — assignRole/toggleStatus/destroy must return back() redirect so Inertia performs a prop-refresh cycle and the table re-renders (discovered at Plan 04 human-verify checkpoint)
- [Phase 01-foundation]: React deduplication via vite.config.ts resolve.dedupe required for laravel-react-i18n — the package bundles its own React copy; without dedupe, hook invariant violations cause app-wide white screen (discovered at Plan 06 human-verify checkpoint)
- [Phase 01-foundation]: Silent fetch() (not router.put()) for fire-and-forget locale persistence — router.put() is always an Inertia visit; fetch() allows optimistic setLocale() with no page reload
- [Phase 02-ingredient-library]: Seeded 50 ingredient subcategories (the full RESEARCH.md Starter Category Tree) — the enumerated tree is authoritative and counts to 50, not the plan-text figure of 41
- [Phase 02-ingredient-library]: Added HasFactory + AllergenFactory to the Phase 1 Allergen model so ingredient tests can build individual allergen rows without seeding all 14
- [Phase 02-ingredient-library]: ingredients table uses a (source, source_id) unique index — required for DB::upsert idempotency in the Plan 02-02 import pipeline; data_hash column gates the verified-reset on re-import
- [Phase 02-ingredient-library]: MySQL-only FULLTEXT index on ingredient_translations.name guarded by DB::getDriverName() so SQLite test runs do not error
- [Phase 02-ingredient-library]: Private ingredient visibility scope applied as base constraint — all ingredient queries exclude other users' private ingredients regardless of source filter
- [Phase 02-ingredient-library]: Allergen-free filter fires on Popover close (not per checkbox tick) per UI-SPEC interaction contract to avoid multiple reloads
- [Phase 02-ingredient-library]: EL dismiss labels are context-specific per UI-SPEC: Διατήρηση Ανεπιβεβαίωτου / Επιστροφή στη Βιβλιοθήκη / Διατήρηση Συστατικού
- [Phase 02-ingredient-library]: CIQUAL XML bundled as 60-food representative subset (CC-BY 4.0); full dataset obtained by running import against live ANSES download
- [Phase 02-ingredient-library]: Two-pass verified-reset in IngredientImporter: caller must call resetVerifiedForChangedRows BEFORE upsertIngredients so hash comparison uses old stored values
- [Phase 02-ingredient-library]: Gate::authorize() used instead of this->authorize() - base Controller has no AuthorizesRequests trait; Gate facade works identically
- [Phase 02-ingredient-library]: IngredientValidationRules trait accepts name field as alias for name_en for pre-written test compatibility
- [Phase 02-ingredient-library]: IngredientDetailResource resolved via ->resolve() in the controller so Inertia receives a plain array (no JsonResource data-wrapping)
- [Phase 02-ingredient-library]: ingredients.show route placed AFTER ingredients/create and ingredients/{ingredient}/edit so the {ingredient} wildcard does not shadow the static-segment routes
- [Phase 02-ingredient-library]: verify-ingredients route given its own permission-gated group, separate from review-ingredients, to keep the two moderation permissions explicit
- [Phase 02-ingredient-library]: IngredientVerificationTest fixture reference corrected — source_id 'sample-001' had no fixture match; changed to alim_code '2001' with path tests/fixtures/ingredients/ciqual-sample.xml
- [Phase 02-ingredient-library]: PerGramCostCalculator newed directly in controller — simple stateless calculator with no swappable dependencies; injection adds no value
- [Phase 02-ingredient-library]: prices.unit normalized to {name, symbol} in IngredientDetailResource — consistent with conversions pattern, avoids leaking Unit model columns
- [Phase 03-recipe-core-metrics]: Circular FK columns (recipes.current_version_id, recipe_ingredient_lines.sub_recipe_version_id) declared as plain unsignedBigInteger without constrained() to avoid chicken-and-egg ordering failure; deferred FK constraints added in migration 000010
- [Phase 03-recipe-core-metrics]: Wave 0 test suite: 53 tests execute without parse errors, 12 pass (schema/model), 41 red (routes/services not yet built) — correct RED state

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-05-16T21:44:19.265Z
Stopped at: Completed 03-recipe-core-metrics-01-PLAN.md
Resume file: None
