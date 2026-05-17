---
phase: 04-recipe-tests
plan: 01
subsystem: database
tags: [laravel, eloquent, migrations, enums, pest, tdd, factories]

# Dependency graph
requires:
  - phase: 03-recipe-core-metrics
    provides: Recipe, RecipeVersion, User models and factories that RecipeTest belongs to

provides:
  - recipe_tests and recipe_test_photos tables with all documented columns
  - TestType and TestVerdict backed string enums with label() methods
  - RecipeTest model with enum casts, array casts, and relations
  - RecipeTestPhoto model with url() accessor and Storage disk integration
  - Recipe model extended with tests() HasMany and latestTest() HasOne relations
  - RecipeTestFactory with trial definition and experiment() state
  - RecipeTestPhotoFactory
  - Wave 0 RecipeTestTest feature suite (13 tests, RED — routes not yet built)

affects: [04-02-recipe-tests-backend, 04-03-recipe-tests-frontend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Backed string enums with label() method following Difficulty.php convention
    - Eloquent url() Attribute accessor via Storage::disk(config('filesystems.default')) for serialized photo URLs
    - Wave 0 TDD: write real Pest assertions against routes that don't exist yet (RED state)
    - Factory state methods (experiment()) for variant shapes

key-files:
  created:
    - database/migrations/2026_05_17_000011_create_recipe_tests_table.php
    - database/migrations/2026_05_17_000012_create_recipe_test_photos_table.php
    - app/Enums/TestType.php
    - app/Enums/TestVerdict.php
    - app/Models/RecipeTest.php
    - app/Models/RecipeTestPhoto.php
    - database/factories/RecipeTestFactory.php
    - database/factories/RecipeTestPhotoFactory.php
    - tests/Feature/Recipes/RecipeTestTest.php
  modified:
    - app/Models/Recipe.php

key-decisions:
  - "Wave 0 test suite writes real assertions (no skip/markTestIncomplete) — 13 tests are RED because routes/controller are not yet built; this gives plan 04-02 a concrete GREEN target"
  - "RecipeTestPhoto.url() accessor uses Storage::disk(config('filesystems.default', 'public')) so disk is configurable and tests can fake it with Storage::fake()"
  - "recipe_version_id FK uses restrictOnDelete (not cascadeOnDelete) to prevent accidental test data loss when recipe versions are retained for historical tracking"

patterns-established:
  - "Backed enum pattern: enum Foo: string { case X = 'x'; public function label(): string { ... } } — mirror Difficulty.php"
  - "url() Attribute accessor on photo models: Storage::disk(config(...)) + $appends = ['url'] ensures URL is always serialized"
  - "Wave 0 TDD: seed RolesAndPermissionsSeeder, build owner with assignRole('User'), build recipe+version with factories, write real HTTP assertions — all RED until backend plan ships"

requirements-completed: [TEST-01, TEST-02, TEST-03, TEST-04]

# Metrics
duration: 25min
completed: 2026-05-17
---

# Phase 4 Plan 01: Data Layer + Wave 0 Test Suite Summary

**recipe_tests/recipe_test_photos tables, TestType/TestVerdict enums, RecipeTest/RecipeTestPhoto models with url accessor, and 13-test RED Pest suite covering TEST-01..04**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-05-17T10:55:00Z
- **Completed:** 2026-05-17T11:20:00Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- Created `recipe_tests` table (14 columns: id, recipe_id, recipe_version_id, user_id, type, tested_at, tasting_notes, overall_rating, ratings JSON, hypothesis, outcome_narrative, verdict, change_rows JSON, timestamps) and `recipe_test_photos` table with cascadeOnDelete FK on recipe_test_id
- Created `TestType` (Trial/Experiment) and `TestVerdict` (Worked/DidntWork/Inconclusive) backed string enums following the established Difficulty.php pattern
- Created `RecipeTest` model with enum casts for type/verdict, array casts for ratings/change_rows, datetime cast for tested_at, and full relations (recipe, recipeVersion, user, photos)
- Created `RecipeTestPhoto` model with `url()` Attribute accessor via `Storage::disk()` and `$appends = ['url']` so URL is always serialized
- Extended `Recipe` model with `tests()` HasMany and `latestTest()` HasOne (latestOfMany) relations
- Created `RecipeTestFactory` with `experiment()` state (sets type=Experiment, hypothesis, outcome_narrative, verdict=Worked, change_rows) and `RecipeTestPhotoFactory`
- Wrote 13-test Wave 0 Pest feature suite in `tests/Feature/Recipes/RecipeTestTest.php` — all RED (404s because routes don't exist) with no parse errors, covering TEST-01..04 auth gates and CRUD
- Ran `php artisan storage:link` to create `public/storage` symlink for photo upload support in plan 04-03

## Task Commits

Each task was committed atomically:

1. **Task 1: Create migrations and enums** - `0fbaf48` (feat)
2. **Task 2: Create RecipeTest + RecipeTestPhoto models, factories, and the Recipe relations** - `9a4eea2` (feat)
3. **Task 3: Write the Wave 0 RecipeTestTest feature suite (RED)** - `859de82` (test)

## Files Created/Modified

- `database/migrations/2026_05_17_000011_create_recipe_tests_table.php` - recipe_tests table with all 14 columns including JSON ratings/change_rows
- `database/migrations/2026_05_17_000012_create_recipe_test_photos_table.php` - recipe_test_photos table with cascadeOnDelete
- `app/Enums/TestType.php` - Trial/Experiment backed string enum with label()
- `app/Enums/TestVerdict.php` - Worked/DidntWork/Inconclusive backed string enum with label()
- `app/Models/RecipeTest.php` - Full Eloquent model with enum casts, array casts, and 4 relations
- `app/Models/RecipeTestPhoto.php` - Model with url() accessor via Storage::disk and $appends=['url']
- `app/Models/Recipe.php` - Added tests() HasMany and latestTest() HasOne relations
- `database/factories/RecipeTestFactory.php` - Trial definition + experiment() state method
- `database/factories/RecipeTestPhotoFactory.php` - Photo factory
- `tests/Feature/Recipes/RecipeTestTest.php` - 13-test Wave 0 Pest suite (RED)

## Decisions Made

- Wave 0 test suite writes real assertions (no skip/markTestIncomplete) — tests are RED because routes/controller are not yet built; this gives plan 04-02 a concrete GREEN target
- `RecipeTestPhoto.url()` uses `Storage::disk(config('filesystems.default', 'public'))` so disk is configurable and tests can use `Storage::fake()`
- `recipe_version_id` FK uses `restrictOnDelete` (not cascadeOnDelete) to prevent accidental test data loss when recipe versions are retained for historical tracking

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All data layer artifacts are in place for plan 04-02 (backend controller + routes)
- Wave 0 test suite provides the RED→GREEN target: `php artisan test --compact --filter=RecipeTestTest` shows 13 failing tests, all with 404s (routes missing)
- `public/storage` symlink is in place for photo upload support

---
*Phase: 04-recipe-tests*
*Completed: 2026-05-17*
