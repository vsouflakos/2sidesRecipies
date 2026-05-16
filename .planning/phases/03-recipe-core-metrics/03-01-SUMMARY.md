---
phase: 03-recipe-core-metrics
plan: 01
subsystem: database
tags: [laravel, eloquent, migrations, pest, brick-math, enums, factories, seeders]

requires:
  - phase: 02-ingredient-library
    provides: Ingredient, Unit, IngredientConversion, Allergen models and unit base_factor infrastructure

provides:
  - Ten recipe-domain migrations (cuisines, tags, recipes, recipe_sections, recipe_ingredient_lines, recipe_steps, recipe_versions, recipe_drafts, recipe_draft_edits, recipe_tag)
  - Nine Eloquent models with relationships and casts (Recipe, RecipeSection, RecipeIngredientLine, RecipeStep, RecipeVersion, RecipeDraft, RecipeDraftEdit, Cuisine, Tag)
  - App\Enums\Difficulty backed string enum (Easy/Medium/Hard/Expert)
  - Eight recipe domain factories for test fixtures
  - CuisineSeeder with 13 cuisines seeded idempotently
  - brick/math as explicit direct composer dependency
  - 13 Phase 3 test files with real red assertions (Wave 0 complete)

affects: [03-02-metrics-calculators, 03-03-allergen-rollup, 03-04-recipe-crud, 03-05-recipe-builder-ui, 03-06-metrics-panel, 03-07-sub-recipes, 03-08-recipe-search]

tech-stack:
  added: [brick/math ^0.14.8]
  patterns:
    - Plain unsignedBigInteger columns for circular FK references (current_version_id, sub_recipe_version_id) — deferred constraints added in final migration
    - Backed string enum with label() match mirroring App\Enums\AccountStatus
    - Wave 0 test files write real assertions (not skip()) targeting unbuilt routes/services

key-files:
  created:
    - app/Enums/Difficulty.php
    - app/Models/Recipe.php
    - app/Models/RecipeSection.php
    - app/Models/RecipeIngredientLine.php
    - app/Models/RecipeStep.php
    - app/Models/RecipeVersion.php
    - app/Models/RecipeDraft.php
    - app/Models/RecipeDraftEdit.php
    - app/Models/Cuisine.php
    - app/Models/Tag.php
    - database/factories/RecipeFactory.php
    - database/factories/RecipeVersionFactory.php
    - database/factories/RecipeDraftFactory.php
    - database/seeders/CuisineSeeder.php
    - tests/Feature/Recipes/RecipeSchemaTest.php
    - tests/Feature/Recipes/RecipeDraftTest.php
    - tests/Feature/Recipes/Metrics/NutritionCalculatorTest.php
  modified:
    - app/Models/User.php
    - database/seeders/DatabaseSeeder.php
    - .planning/phases/03-recipe-core-metrics/03-VALIDATION.md
    - composer.json

key-decisions:
  - "Circular FK columns (recipes.current_version_id, recipe_ingredient_lines.sub_recipe_version_id) declared as plain unsignedBigInteger without ->constrained() in create migrations to avoid chicken-and-egg ordering failure; deferred FK constraints added in migration 000010"
  - "RecipeDraftEdit has no HasFactory trait per plan — direct Model::create() in tests is sufficient for the simple audit-log pattern"
  - "Wave 0 test suite executes without parse errors: 53 tests, 12 pass (pure schema/model tests), 41 red (routes and services not yet built)"

patterns-established:
  - "Deferred FK pattern: declare columns as plain unsignedBigInteger in circular-reference migrations, add foreign() constraints in a later migration after all referenced tables exist"
  - "Recipe test factories use Recipe::factory() as the base fixture; RecipeVersion, RecipeDraft chained onto it"

requirements-completed: [RECIPE-04, RECIPE-13, VERSION-01, RECIPE-07, METRIC-04]

duration: 16min
completed: 2026-05-17
---

# Phase 03 Plan 01: Recipe Core Data Layer & Wave 0 Test Scaffold Summary

**Nine recipe-domain tables with Eloquent models, Difficulty enum, 13 cuisines seeded, brick/math direct dep, and 13 red test files establishing Wave 0 green targets for all Phase 3 requirements**

## Performance

- **Duration:** 16 min
- **Started:** 2026-05-17T00:26:03Z
- **Completed:** 2026-05-17T00:42:00Z
- **Tasks:** 3
- **Files modified:** 47

## Accomplishments

- Ten migrations executed cleanly — cuisines, tags, recipes (with difficulty/selling_price), recipe_sections, recipe_ingredient_lines (with prep_note/yield_pct), recipe_steps, recipe_versions (append-only with JSON snapshot), recipe_drafts, recipe_draft_edits, recipe_tag
- Nine models with full relationships, casts, and fillable lists; User model extended with recipes() hasMany; eight factories and CuisineSeeder (13 cuisines, idempotent firstOrCreate)
- 13 test files across tests/Feature/Recipes/ and tests/Feature/Recipes/Metrics/ — all with real assertions, suite runs without parse errors (53 tests, 12 pass now, 41 red as expected), Wave 0 complete

## Task Commits

1. **Task 1: brick/math dependency, Difficulty enum, migrations** - `6245b2e` (feat)
2. **Task 2: Recipe-domain models, factories, cuisine seeder** - `c1bc687` (feat)
3. **Task 3: Wave 0 — scaffold all 13 Phase 3 test files** - `71ff0bd` (feat)

## Files Created/Modified

- `app/Enums/Difficulty.php` - Backed string enum Easy/Medium/Hard/Expert with label()
- `database/migrations/2026_05_17_000001..000010` - Ten recipe-domain migrations
- `app/Models/Recipe.php` - Main recipe model with HasFactory, SoftDeletes, 8 relationships, Difficulty cast
- `app/Models/RecipeIngredientLine.php` - Ingredient line with isSubRecipe() helper
- `app/Models/RecipeVersion.php` - Append-only version with JSON snapshot and cached metric columns
- `app/Models/RecipeDraft.php` / `RecipeDraftEdit.php` - Working draft + edit log
- `app/Models/Cuisine.php` / `Tag.php` - Supporting lookup models
- `database/factories/Recipe*Factory.php` - Eight factories for test fixture building
- `database/seeders/CuisineSeeder.php` - 13 cuisines seeded idempotently
- `tests/Feature/Recipes/RecipeSchemaTest.php` - 6 schema tests, all green
- `tests/Feature/Recipes/RecipeDraftTest.php` - Includes apply_scale Blocker 3 test (red)
- `tests/Feature/Recipes/Metrics/*.php` - Five calculator/rollup test files (all red)
- `.planning/phases/03-recipe-core-metrics/03-VALIDATION.md` - wave_0_complete and nyquist_compliant set true

## Decisions Made

- Circular FK columns declared as plain `unsignedBigInteger()->nullable()` (no `->constrained()`) in recipes and recipe_ingredient_lines migrations to avoid forward-reference ordering failure; the actual `foreign()` constraints are applied in migration 000010 after recipe_versions exists.
- Wave 0 tests deliberately target not-yet-built routes and services — they execute without parse errors and fail correctly (404 for missing routes, container binding for missing services).

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All Phase 3 data layer is ready; Plan 03-02 can build NutritionCalculator, CostCalculator, ShrinkageCalculator, and BakersPercentageCalculator against the red test targets
- Plan 03-03 can build AllergenRollupService and MetricsRollupService
- Plan 03-04 can build the recipe CRUD + draft/version controllers
- All factories and the CuisineSeeder are available as fixtures across all later plans

---
*Phase: 03-recipe-core-metrics*
*Completed: 2026-05-17*
