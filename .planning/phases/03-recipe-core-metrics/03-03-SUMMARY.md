---
phase: 03-recipe-core-metrics
plan: "03"
subsystem: recipe-services
tags: [allergen-rollup, circular-reference, draft-manager, metrics-aggregator, big-decimal]
dependency_graph:
  requires: [03-01, 03-02]
  provides: [AllergenRollupService, CircularReferenceDetector, RecipeDraftManager, MetricsAggregator, AggregatedMetrics, MetricsRollupService, DraftSequenceMismatchException]
  affects: [03-04]
tech_stack:
  added: []
  patterns: [BigDecimal exact arithmetic, BFS graph traversal, DB transaction log-then-save, readonly value object]
key_files:
  created:
    - app/Support/Recipes/AllergenRollupService.php
    - app/Support/Recipes/CircularReferenceDetector.php
    - app/Support/Recipes/RecipeDraftManager.php
    - app/Support/Recipes/MetricsAggregator.php
    - app/Support/Recipes/AggregatedMetrics.php
    - app/Support/Recipes/MetricsRollupService.php
    - app/Exceptions/DraftSequenceMismatchException.php
  modified: []
decisions:
  - "AllergenRollupService.compute(Recipe) returns {contains, may_contain} arrays to match test contract rather than a flat slug=>state map"
  - "MetricsRollupService created separately from MetricsAggregator to match MetricsRollupTest contract (computeForLine signature)"
  - "CircularReferenceDetector BFS traverses live RecipeIngredientLine table (not version snapshot) for current draft state"
  - "DraftSequenceMismatchException extends RuntimeException; controller maps it to 409 Conflict (Plan 04)"
metrics:
  duration_minutes: 9
  completed_date: "2026-05-17"
  tasks_completed: 2
  files_created: 7
  files_modified: 0
---

# Phase 03 Plan 03: Orchestration Services Summary

**One-liner:** Allergen roll-up with contains-beats-may_contain priority, BFS cycle detection across the full sub-recipe graph, draft edit log with sequence-guarded Recall, and sub-recipe metric scaling via BigDecimal scaleFactor.

## What Was Built

Five service/value-object classes under `app/Support/Recipes/` and one exception class:

**AllergenRollupService** — `merge()` folds multiple allergen sets with `contains` always winning over `may_contain` (ALLG-02). `forRecipeLines()` resolves ingredient pivot states and sub-recipe `cached_allergen_slugs` (ALLG-03). `compute(Recipe)` returns `{contains: [...], may_contain: [...]}` arrays (ALLG-01). All 3 AllergenRollupTest cases green.

**CircularReferenceDetector** — `wouldCreateCycle(parentId, candidateId)` runs BFS from the candidate recipe through the live `recipe_ingredient_lines` table, resolving `sub_recipe_version_id → recipe_id` via a join. Tracks a `$visited` set to prevent infinite loops. The 3-node cycle (A→B→C→A) is caught by the full traversal, not just a direct-parent check (Pitfall 2). Service is ready; CircularReferenceTest will turn green when Plan 04 registers the draft controller routes.

**RecipeDraftManager** — `applyEdit()` wraps a DB transaction: inserts a `RecipeDraftEdit` row with `before_snapshot = draft.data` then updates `draft.data` and increments `edit_sequence` (VERSION-02). `recall()` checks `expectedSequence === draft.edit_sequence` (throws `DraftSequenceMismatchException` on mismatch per Pitfall 5), then restores `before_snapshot` and deletes the last edit row in a transaction (VERSION-04).

**AggregatedMetrics** — readonly value object holding `$lines`, `$portions`, `$totalYieldG` as BigDecimal. The lines array is the shape NutritionCalculator/CostCalculator consume.

**MetricsAggregator** — `prepareLines(RecipeDraft)` iterates draft sections, normalizing ingredient lines and scaling sub-recipe lines by `scaleFactor = lineGrams / yield_g`. Asserts `yield_g` is non-null before dividing (Pitfall 4).

**MetricsRollupService** — `computeForLine(RecipeIngredientLine, RecipeVersion)` computes exact BigDecimal scale factor and returns scaled `energy_kcal`, `protein_g`, `cost`. Both MetricsRollupTest cases (250g/500g-yield = 0.5 scale, 100g/300g-yield = 1/3 exact) pass.

**DraftSequenceMismatchException** — extends `RuntimeException`, carries expected/actual sequence for the controller to surface as 409 Conflict.

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| AllergenRollupTest (3 tests) | GREEN | ALLG-01/02/03 all passing |
| MetricsRollupTest (2 tests) | GREEN | METRIC-08 scale factor exact |
| CircularReferenceTest (2 tests) | RED (expected) | Routes live in Plan 04 |
| RecipeDraftTest (4 tests) | RED (expected) | Routes live in Plan 04 |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Task 1 already committed by Plan 02**
- **Found during:** Task 1
- **Issue:** Plan 02 (feat 8f856be) had already committed AllergenRollupService and CircularReferenceDetector as part of its GramNormalizer/NutritionCalculator work. Files on disk were identical to what I wrote.
- **Fix:** No action — files were correct. Verified with `git diff HEAD` (no diff). Task 1 treated as pre-done.
- **Commit:** 8f856be (Plan 02's commit)

**2. [Rule 2 - Interface mismatch] MetricsRollupService vs MetricsAggregator**
- **Found during:** Task 2
- **Issue:** MetricsRollupTest imports `App\Support\Recipes\MetricsRollupService` and calls `computeForLine($subLine, $version)`. The plan specified `MetricsAggregator` with `prepareLines(RecipeDraft)`. These are different interfaces.
- **Fix:** Created both: `MetricsRollupService` to match the test contract (direct sub-recipe line scaling), and `MetricsAggregator` for the draft-level orchestration used by Plan 04 controllers.
- **Files created:** `MetricsRollupService.php`, `MetricsAggregator.php`
- **Commit:** 7f59f04

## Self-Check: PASSED

All 7 files exist on disk. Both commits (8f856be Task 1, 7f59f04 Task 2) present in git log.
