---
phase: 3
slug: recipe-core-metrics
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-05-16
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHPUnit 12) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter={filter}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={relevant filter}`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

One row per task across all 8 plans. The automated command is pulled verbatim from each task's `<verify><automated>` block. Wave numbers reflect the post-revision dependency graph (Plan 07 moved to Wave 6, Plan 08 to Wave 7 to resolve the `show.tsx` write conflict).

| Task ID | Plan | Wave | Requirements | Test Type | Automated Command | Status |
|---------|------|------|--------------|-----------|-------------------|--------|
| 03-01-T1 | 01 | 1 | RECIPE-04, RECIPE-13, VERSION-01 | Feature/Schema | `php artisan migrate:fresh --seed && php artisan migrate:status` | ⬜ pending |
| 03-01-T2 | 01 | 1 | RECIPE-04, RECIPE-13, VERSION-01 | Feature/Schema | `php artisan migrate:fresh --seed --no-interaction && php artisan test --compact --filter=RecipeSchemaTest` | ⬜ pending |
| 03-01-T3 | 01 | 1 | RECIPE-07, METRIC-04 + all Wave 0 scaffolds | Feature/Unit | `php artisan test --compact tests/Feature/Recipes/ 2>&1 \| tail -20` | ⬜ pending |
| 03-02-T1 | 02 | 2 | METRIC-01, METRIC-04, METRIC-07, METRIC-09, METRIC-10 | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ⬜ pending |
| 03-02-T2 | 02 | 2 | METRIC-02, METRIC-03, METRIC-05, METRIC-06 | Unit | `php artisan test --compact --filter='CostCalculatorTest\|ShrinkageCalculatorTest\|BakersPercentageCalculatorTest'` | ⬜ pending |
| 03-03-T1 | 03 | 2 | ALLG-01, ALLG-02, ALLG-03, RECIPE-06 | Feature | `php artisan test --compact --filter='AllergenRollupTest\|CircularReferenceTest'` | ⬜ pending |
| 03-03-T2 | 03 | 2 | METRIC-08, VERSION-02, VERSION-04 | Unit/Feature | `php artisan test --compact --filter='MetricsRollupTest\|RecipeDraftTest'` | ⬜ pending |
| 03-04-T1 | 04 | 3 | VERSION-01, VERSION-03, METRIC-03 | Feature | `php artisan route:list --path=recipes && php artisan test --compact --filter=RecipeVersionTest` | ⬜ pending |
| 03-04-T2 | 04 | 3 | RECIPE-01, RECIPE-02, RECIPE-03, RECIPE-06, RECIPE-07, VERSION-02, VERSION-04, METRIC-04 | Feature | `php artisan test --compact --filter='RecipeCrudTest\|RecipeDraftTest\|RecipeVersionTest'` | ⬜ pending |
| 03-04-T3 | 04 | 3 | RECIPE-05, RECIPE-09, VERSION-05, VERSION-06 | Feature | `php artisan test --compact --filter='RecipeVersionTest\|SubRecipeTest\|RecipeCrudTest'` | ⬜ pending |
| 03-05-T1 | 05 | 4 | RECIPE-04, RECIPE-11, VERSION-02 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-05-T2 | 05 | 4 | RECIPE-01, RECIPE-03, RECIPE-13 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-05-T3 | 05 | 4 | RECIPE-02, RECIPE-04, RECIPE-10, VERSION-02 | Build/Feature | `npm run build 2>&1 \| tail -15 && php artisan test --compact --filter=RecipeCrudTest` | ⬜ pending |
| 03-06-T1 | 06 | 5 | METRIC-01, METRIC-02, METRIC-03, METRIC-07, ALLG-01, ALLG-02, ALLG-03 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-06-T2 | 06 | 5 | METRIC-05, METRIC-06, RECIPE-07, RECIPE-08 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-06-T3 | 06 | 5 | METRIC-01..07, ALLG-01..03, RECIPE-07, RECIPE-08 | Build/Feature | `npm run build 2>&1 \| tail -15 && php artisan test --compact --filter='RecipeCrudTest\|RecipeDraftTest'` | ⬜ pending |
| 03-07-T1 | 07 | 6 | RECIPE-05, RECIPE-06, VERSION-06 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-07-T2 | 07 | 6 | RECIPE-09, VERSION-03, VERSION-04 | Build/Feature | `npm run build 2>&1 \| tail -15 && php artisan test --compact --filter='RecipeDraftTest\|RecipeVersionTest'` | ⬜ pending |
| 03-07-T3 | 07 | 6 | VERSION-05 | Build/Feature | `npm run build 2>&1 \| tail -15 && php artisan test --compact --filter=RecipeVersionTest` | ⬜ pending |
| 03-08-T1 | 08 | 7 | RECIPE-12 | Build | `npm run build 2>&1 \| tail -15` | ⬜ pending |
| 03-08-T2 | 08 | 7 | RECIPE-12 | Build/Feature | `npm run build 2>&1 \| tail -15 && php artisan test --compact --filter=RecipeSearchTest` | ⬜ pending |
| 03-08-T3 | 08 | 7 | full integrated phase | Manual + full suite | Human-verify checkpoint; `php artisan test --compact` (full suite green) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Wave 0 is delivered entirely by Plan 03-01 Task 3 — all 13 Phase 3 test files plus the recipe factories. The task authors real (red) assertions and sets the two flags below to `true` at execution time.

- [x] `tests/Feature/Recipes/RecipeSchemaTest.php` — RECIPE-04, RECIPE-13, VERSION-01
- [x] `tests/Feature/Recipes/RecipeCrudTest.php` — RECIPE-01..03, RECIPE-07..11
- [x] `tests/Feature/Recipes/RecipeDraftTest.php` — VERSION-02..04, RECIPE-07, METRIC-04 (includes the Apply-to-Draft scaling test, Blocker 3)
- [x] `tests/Feature/Recipes/RecipeVersionTest.php` — VERSION-01, VERSION-05, RECIPE-08
- [x] `tests/Feature/Recipes/RecipeSearchTest.php` — RECIPE-12
- [x] `tests/Feature/Recipes/SubRecipeTest.php` — RECIPE-05, VERSION-06
- [x] `tests/Feature/Recipes/CircularReferenceTest.php` — RECIPE-06
- [x] `tests/Feature/Recipes/Metrics/NutritionCalculatorTest.php` — METRIC-01, 04, 07, 09, 10
- [x] `tests/Feature/Recipes/Metrics/CostCalculatorTest.php` — METRIC-02, 03
- [x] `tests/Feature/Recipes/Metrics/ShrinkageCalculatorTest.php` — METRIC-05
- [x] `tests/Feature/Recipes/Metrics/BakersPercentageCalculatorTest.php` — METRIC-06
- [x] `tests/Feature/Recipes/Metrics/AllergenRollupTest.php` — ALLG-01..03
- [x] `tests/Feature/Recipes/Metrics/MetricsRollupTest.php` — METRIC-08
- [x] `database/factories/Recipe*Factory.php` — shared fixtures for all recipe tests
- [x] `tests/Pest.php` — shared base test case already covers feature tests (confirm only)

*`wave_0_complete` and `nyquist_compliant` are set `true` by Plan 03-01 Task 3 once these files exist with real assertions and the suite runs without parse errors.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Builder UX — sections, inline search, quick-create, auto-save indicator | RECIPE-01..04, RECIPE-10..13 | Visual + interactive flow; React component behavior not unit-testable end to end | Plan 03-08 Task 3 checkpoint, steps 1–2 |
| Live metrics panel — nutrition toggle, food cost %, allergen chips, baker's section, gap banner | METRIC-01..07, ALLG-01..03 | Visual rendering + interactive toggle; values are server-tested, presentation is not | Plan 03-08 Task 3 checkpoint, steps 3–4 |
| View-only scaling — italic quantities, Apply-to-Draft button appearance | RECIPE-07, RECIPE-08 | Client-side recompute is visual; the Apply-to-Draft mutation IS automated (RecipeDraftTest, Blocker 3) | Plan 03-08 Task 3 checkpoint, step 5 |
| Save Version / Recall / version compare / sub-recipe update cue | VERSION-03..06, RECIPE-05 | Interactive flow + diff highlight rendering | Plan 03-08 Task 3 checkpoint, steps 6–8 |
| Responsive layout + light/dark theme — metrics panel mobile collapse | UI-03 (carried) | Cross-viewport visual check | Plan 03-08 Task 3 checkpoint, step 10 |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter (by Plan 03-01 Task 3 at execution)

**Approval:** pending
