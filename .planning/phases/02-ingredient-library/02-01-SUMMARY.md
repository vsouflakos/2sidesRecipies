---
phase: 02-ingredient-library
plan: 01
subsystem: database
tags: [eloquent, migrations, factories, seeders, spatie-permission, pest, ingredient-schema]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: units table, allergens table, users table, spatie/laravel-permission roles
provides:
  - Six ingredient tables (ingredient_categories, ingredients, ingredient_translations, ingredient_allergen, ingredient_conversions, ingredient_prices)
  - Five Eloquent models with full relationships and helper methods
  - Six model factories (Ingredient, IngredientCategory, IngredientTranslation, IngredientConversion, IngredientPrice, Allergen)
  - Idempotent IngredientCategorySeeder (13-category tree)
  - verify-ingredients permission seeded onto Moderator + Admin
  - 12 scaffolded Pest test files for the whole phase
affects: [02-02-import-pipeline, 02-03-search-ui, 02-04-private-crud, 02-05-detail-page, 02-06-pricing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Multi-source ingredient in one table distinguished by user_id (null = official)"
    - "Multi-language names in separate ingredient_translations table; nameFor() locale fallback helper"
    - "Allergen pivot carries a state column (contains | may_contain)"
    - "MySQL-only FULLTEXT index guarded by DB::getDriverName() so SQLite test runs do not error"
    - "Wave 0 test scaffold writes real assertions (no skip) for red-to-green targets"

key-files:
  created:
    - database/migrations/2026_05_16_140143_create_ingredient_categories_table.php
    - database/migrations/2026_05_16_140144_create_ingredients_table.php
    - database/migrations/2026_05_16_140145_create_ingredient_translations_table.php
    - database/migrations/2026_05_16_140146_create_ingredient_allergen_table.php
    - database/migrations/2026_05_16_140147_create_ingredient_conversions_table.php
    - database/migrations/2026_05_16_140147_create_ingredient_prices_table.php
    - app/Models/Ingredient.php
    - app/Models/IngredientCategory.php
    - app/Models/IngredientTranslation.php
    - app/Models/IngredientConversion.php
    - app/Models/IngredientPrice.php
    - database/seeders/IngredientCategorySeeder.php
    - tests/Feature/Ingredients/ (12 Pest test files)
  modified:
    - app/Models/User.php
    - app/Models/Allergen.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "Seeded 50 subcategories (the full RESEARCH.md Starter Category Tree) rather than the plan-text figure of 41 — the enumerated tree is the authoritative source and counts to 50"
  - "Added HasFactory + AllergenFactory to the Phase 1 Allergen model so ingredient tests can build allergen rows without seeding all 14"
  - "IngredientConversion/IngredientPrice factories resolve a gram Unit via firstOrCreate (no UnitFactory exists in Phase 1)"

patterns-established:
  - "Pattern: ingredient source authority via (source, source_id) unique index — required for DB::upsert idempotency in Plan 02-02"
  - "Pattern: nameFor(locale) translation fallback el -> en -> dash"
  - "Pattern: factory states ->private(User) and ->verified(User) on IngredientFactory"

requirements-completed: [INGR-03, INGR-04, INGR-05, INGR-06, INGR-08]

# Metrics
duration: ~40min
completed: 2026-05-16
---

# Phase 2 Plan 01: Ingredient Data Schema Summary

**Six ingredient tables, five Eloquent models with full relationships, six factories, an idempotent 13-category seeder, the verify-ingredients permission, and 12 scaffolded Pest test files — the schema foundation every other Phase 2 plan builds on.**

## Performance

- **Duration:** ~40 min (across one reconnect)
- **Tasks:** 3
- **Files created:** 24 (6 migrations, 5 models, 6 factories, 1 seeder, 12 test files — IngredientSchemaTest counted with Task 1)
- **Files modified:** 4 (User, Allergen, RolesAndPermissionsSeeder, DatabaseSeeder)

## Accomplishments

- Six ingredient tables migrate cleanly on SQLite and MySQL; `ingredients` carries 29 nutrition columns + 10 reserved frozen-dessert decimal columns + `ingredient_class`, with a unique index on `(source, source_id)` and soft deletes.
- Five Eloquent models (`Ingredient`, `IngredientCategory`, `IngredientTranslation`, `IngredientConversion`, `IngredientPrice`) with `category`, `user`, `verifiedBy`, `translations`, `conversions`, `prices`, `allergens` relationships and `nameFor`/`isOfficial`/`isPrivate` helpers.
- `IngredientCategorySeeder` seeds the full 13-category tree idempotently via `firstOrCreate` on slug.
- `verify-ingredients` permission added to Moderator and Admin without touching existing permissions.
- 12 Pest test files scaffolded with real assertions; the 4 schema/model files (`IngredientSchemaTest`, `IngredientAllergenTest`, `IngredientConversionTest`, `IngredientTranslationTest`) are green (13 tests, 26 assertions); the other 8 are red, awaiting Plans 02-02 to 02-06.

## Task Commits

1. **Task 1: Six ingredient migrations + schema test (TDD RED)** - `a1013b0` (test)
2. **Task 2: Models, factories, category seeder, verify permission (TDD GREEN)** - `f00fe11` (feat)
3. **Task 3: Scaffold all 11 remaining phase test files** - `344818a` (test)

_Note: Task 1 followed TDD — the failing schema test and migrations were committed together as the RED phase; Task 2 turned it GREEN._

## Files Created/Modified

- `database/migrations/*_create_ingredient_*` - Six tables: categories, ingredients, translations, allergen pivot, conversions, prices
- `app/Models/Ingredient.php` - Core model: SoftDeletes, 39 decimal casts, 7 relationships, nameFor/isOfficial/isPrivate
- `app/Models/IngredientCategory.php` - Self-referential 2-level tree (parent/children) + ingredients hasMany
- `app/Models/IngredientTranslation.php` / `IngredientConversion.php` / `IngredientPrice.php` - Supporting models with casts and relationships
- `app/Models/User.php` - Added `ingredients()` and `ingredientPrices()` hasMany
- `app/Models/Allergen.php` - Added HasFactory trait
- `database/factories/*` - Six factories incl. AllergenFactory; IngredientFactory has private()/verified() states
- `database/seeders/IngredientCategorySeeder.php` - Idempotent 13-category / 50-subcategory tree
- `database/seeders/RolesAndPermissionsSeeder.php` - verify-ingredients permission
- `database/seeders/DatabaseSeeder.php` - Registers IngredientCategorySeeder
- `tests/Feature/Ingredients/*` - 12 Pest test files covering INGR-01 to INGR-08 + verify

## Decisions Made

- **Subcategory count:** The plan text says "41 subcategories" but the RESEARCH.md "Starter Category Tree" — the source the task designates as authoritative — enumerates 50. Seeded all 50. The schema test asserts the 13 roots (unambiguous in both sources).
- **AllergenFactory:** Phase 1's `Allergen` model had no factory. Added `HasFactory` + `AllergenFactory` so ingredient tests can create individual allergen rows without seeding all 14 EU allergens.
- **Unit resolution in factories:** No `UnitFactory` exists; `IngredientConversionFactory` and `IngredientPriceFactory` resolve a gram unit via `Unit::firstOrCreate` to stay self-contained.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added HasFactory + AllergenFactory to the Allergen model**
- **Found during:** Task 2 (running IngredientSchemaTest)
- **Issue:** `IngredientSchemaTest` and `IngredientAllergenTest` call `Allergen::factory()`, but the Phase 1 `Allergen` model had neither the `HasFactory` trait nor a factory — `Call to undefined method App\Models\Allergen::factory()`.
- **Fix:** Created `database/factories/AllergenFactory.php` and added the `HasFactory` trait to `app/Models/Allergen.php`. The plan's `files_modified` list already included `app/Models/Allergen.php`.
- **Files modified:** app/Models/Allergen.php, database/factories/AllergenFactory.php
- **Verification:** IngredientSchemaTest and IngredientAllergenTest pass.
- **Committed in:** `f00fe11` (Task 2 commit)

**2. [Rule 3 - Blocking] Factories resolve a Unit without a UnitFactory**
- **Found during:** Task 2 (writing IngredientConversionFactory / IngredientPriceFactory)
- **Issue:** Both factories need a `from_unit_id` / `unit_id` FK, but Phase 1 shipped no `UnitFactory`, so `Unit::factory()` would fail.
- **Fix:** Both factories call `Unit::firstOrCreate(['name' => 'gram'], ...)` to obtain a valid unit id.
- **Files modified:** database/factories/IngredientConversionFactory.php, database/factories/IngredientPriceFactory.php
- **Verification:** IngredientConversionTest passes; factory rows persist.
- **Committed in:** `f00fe11` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 3 - blocking)
**Impact on plan:** Both auto-fixes were necessary to make Task 2's tests run at all. No scope creep — both files were already in the plan's `files_modified` set or are factories the plan explicitly requested.

## Issues Encountered

- A mid-execution socket disconnect interrupted the run after Task 1 was committed and Task 2's files were written but uncommitted. Resumed by verifying git state, re-running pint + the schema test, then committing Task 2 and proceeding to Task 3. Stray empty junk files from a Windows shell-quoting accident were cleaned up before resuming.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Schema, models, factories, and category tree are ready. Plan 02-02 (import pipeline) can rely on the `(source, source_id)` unique index for `DB::upsert` idempotency and the `data_hash` column for the verified-reset gate.
- 8 test files are deliberately red; each turns green when its owning plan (02-02 import, 02-03 search, 02-04 private CRUD, 02-05 detail, 02-06 pricing) ships.

## Self-Check: PASSED

- All 6 migration files present and migrate cleanly (`migrate:fresh --seed` exits 0).
- All 5 models + 6 factories + seeder present.
- All 12 test files present under `tests/Feature/Ingredients/`.
- Commits `a1013b0`, `f00fe11`, `344818a` confirmed in `git log`.
- 4 schema/model test files green: 13 tests / 26 assertions passing.
- `verify-ingredients` permission confirmed seeded; 13 category roots confirmed.

---
*Phase: 02-ingredient-library*
*Completed: 2026-05-16*
