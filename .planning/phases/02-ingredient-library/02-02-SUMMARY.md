---
phase: 02-ingredient-library
plan: 02
subsystem: import-pipeline
tags: [artisan, xml, csv, upsert, ciqual, usda, open-food-facts, allergens, translations, conversions]

# Dependency graph
requires:
  - phase: 02-ingredient-library
    plan: 01
    provides: ingredient schema, models, factories, category seeder, test scaffold
provides:
  - ingredients:import-ciqual Artisan command
  - ingredients:import-usda Artisan command
  - ingredients:import-off Artisan command
  - IngredientImporter shared support class
  - bundled CIQUAL 2025 XML (CC-BY 4.0, 60-food representative subset)
  - 7 test fixture files for all three sources
affects: [02-03-search-ui, 02-04-private-crud, 02-05-detail-page, phase-03-recipe-engine]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "XMLReader streaming for CIQUAL XML — avoids loading 3-5 MB into memory; reads ALIM nodes one at a time"
    - "Two-pass verified-reset: read old hashes BEFORE upsert, reset verified where hash differs"
    - "Private _ keys stripped from upsert rows — _name_en/_name_fr/_fdc_id carry translation data without polluting the DB payload"
    - "fgetcsv() row-by-row streaming for USDA and OFF CSVs"
    - "OFF country filter applied in PHP while streaming; no full decompression to memory"
    - "OFF cross-source matching by normalised English name (lowercased name_cache)"
    - "Http::sink() download path for on-demand USDA/OFF fetch"

key-files:
  created:
    - app/Support/Ingredients/IngredientImporter.php
    - app/Console/Commands/ImportCiqual.php
    - app/Console/Commands/ImportUsdaFdc.php
    - app/Console/Commands/ImportOpenFoodFacts.php
    - database/data/ciqual-2025.xml
    - tests/fixtures/ingredients/ciqual-sample.xml
    - tests/fixtures/ingredients/usda-food.csv
    - tests/fixtures/ingredients/usda-food-nutrient.csv
    - tests/fixtures/ingredients/usda-nutrient.csv
    - tests/fixtures/ingredients/usda-food-portion.csv
    - tests/fixtures/ingredients/usda-measure-unit.csv
    - tests/fixtures/ingredients/off-products.csv
  modified:
    - tests/Feature/Ingredients/ImportCiqualTest.php
    - tests/Feature/Ingredients/ImportUsdaTest.php
    - tests/Feature/Ingredients/ImportOpenFoodFactsTest.php

key-decisions:
  - "CIQUAL XML bundled as a 60-food representative subset (CC-BY 4.0) rather than the full 3484-food file — sufficient for development and import pipeline testing; full dataset added by running the real command against the live ANSES download"
  - "Test fixture paths updated from tests/fixtures/ (scaffold expectation) to tests/fixtures/ingredients/ (plan spec) — tests updated to match"
  - "USDA tests use individual --*-file options (not --source-file pointing to a directory) — matches the actual command signature designed for independent file control"
  - "beforeEach IngredientCategorySeeder added to all 3 import test files — import commands resolve category_id by slug and require the category tree to be seeded"
  - "UnitSeeder added to USDA import test beforeEach — food_portion sync resolves unit by name; without seeded units the conversion sync silently skips all rows"
  - "Two-pass verified-reset implemented in IngredientImporter; caller MUST call resetVerifiedForChangedRows BEFORE upsertIngredients (documented in PHPDoc)"
  - "OFF sodium column converts from g/100g (OFF format) to mg/100g (schema format) — factor of 1000"

# Metrics
duration: ~60min
completed: 2026-05-16
---

# Phase 2 Plan 02: Ingredient Import Pipeline Summary

**Three idempotent Artisan commands (CIQUAL, USDA FDC, Open Food Facts) plus a shared IngredientImporter support class — chunked upsert, two-pass verified-reset, translation/allergen/conversion sync — backed by 7 test fixtures and 14 passing tests.**

## Performance

- **Duration:** ~60 min
- **Tasks:** 3
- **Files created:** 13 (1 support class, 3 commands, 1 bundled XML, 7 test fixtures)
- **Files modified:** 3 (import test files scaffolded in Plan 02-01, updated with real assertions)

## Accomplishments

- `IngredientImporter` support class with 6 public methods: `upsertIngredients` (500-row chunks in DB transactions), `resetVerifiedForChangedRows` (two-pass verified guard), `syncTranslation`, `syncAllergens` (pivot sync with contains > may_contain priority), `syncConversion` (idempotent keyed on ingredient_id + from_unit_id + source_ref), `dataHash` (md5 of serialised nutrition payload).
- `ingredients:import-ciqual`: XMLReader streaming, NUTRIENT_MAP for 30 CIQUAL constituent codes, GROUP_CATEGORY_MAP for G01-G20, English + French translation sync post-upsert. CC-BY 4.0 60-food representative dataset committed in `database/data/ciqual-2025.xml`.
- `ingredients:import-usda`: fgetcsv CSV streaming for 5 files (food, nutrient, food_nutrient, food_portion, measure_unit), NUTRIENT_MAP for 28 USDA nutrient ids, CATEGORY_MAP for USDA food category ids, food_portion conversion sync resolving unit by name against Phase 1 units table, Http::sink() on-demand download path.
- `ingredients:import-off`: TAB-separated CSV stream parsing, Greece country filter in PHP, English name cross-source matching, Greek translation enrichment, allergen tag parsing (en: prefix stripped), Http::sink() gz download path.
- 7 test fixtures: CIQUAL sample XML (3 foods), 5 USDA CSVs (5 foods each), OFF tab-separated CSV (4 products with Greek names, allergens, traces, Greece filter).
- 14 tests pass: 5 CIQUAL, 4 USDA, 5 OFF.

## Task Commits

1. **Task 1: IngredientImporter + test fixtures** - `8cd13a0` (feat)
2. **Task 2: CIQUAL import command + bundled XML** - `4a8d4a0` (feat)
3. **Task 3: USDA FDC + OFF import commands** - `aa0ace8` (feat)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Test fixture paths: scaffold used tests/fixtures/, plan specifies tests/fixtures/ingredients/**
- **Found during:** Task 1
- **Issue:** The Plan 02-01 test scaffold created import tests referencing `tests/fixtures/ciqual-sample.xml`, `tests/fixtures/usda-sample`, and `tests/fixtures/off-sample.csv` (flat directory). This plan specified fixtures under `tests/fixtures/ingredients/`.
- **Fix:** Placed all 7 fixtures under `tests/fixtures/ingredients/` (plan spec) and updated the test files to use the new paths. Test paths and command signatures were reconciled.
- **Files modified:** ImportCiqualTest.php, ImportUsdaTest.php, ImportOpenFoodFactsTest.php
- **Committed in:** `8cd13a0`

**2. [Rule 3 - Blocking] USDA test used --source-file with a directory; command uses individual --*-file options**
- **Found during:** Task 1 (test scaffold analysis)
- **Issue:** The scaffold test passed `--source-file` with a directory path `tests/fixtures/usda-sample`. The command designed by the plan uses five individual file options (`--food-file`, `--nutrient-file`, etc.).
- **Fix:** Updated ImportUsdaTest.php to pass the 5 individual --*-file options pointing to the correct fixture files.
- **Files modified:** ImportUsdaTest.php
- **Committed in:** `8cd13a0`

**3. [Rule 2 - Missing Critical] beforeEach category seeder required for all 3 import tests**
- **Found during:** Task 2 (first test run)
- **Issue:** Import commands resolve `category_id` by slug and fall back to `id=1` if not found. With RefreshDatabase + no seeding, the categories table is empty and FK constraint would fail on upsert.
- **Fix:** Added `beforeEach(fn() => $this->seed(IngredientCategorySeeder::class))` to all 3 import test files.
- **Files modified:** ImportCiqualTest.php, ImportUsdaTest.php, ImportOpenFoodFactsTest.php
- **Committed in:** per-task commits

**4. [Rule 2 - Missing Critical] UnitSeeder required for USDA conversion sync test**
- **Found during:** Task 3 (conversion test)
- **Issue:** The food_portion conversion sync resolves unit by name from the Phase 1 units table. Without `UnitSeeder` running in beforeEach, no units exist and all portion rows are silently skipped, causing the `toBeGreaterThan(0)` assertion to fail.
- **Fix:** Added `$this->seed(UnitSeeder::class)` to ImportUsdaTest.php beforeEach.
- **Files modified:** ImportUsdaTest.php
- **Committed in:** `aa0ace8`

## Self-Check: PASSED

- All 4 PHP files present: IngredientImporter.php, ImportCiqual.php, ImportUsdaFdc.php, ImportOpenFoodFacts.php
- All 7 fixture files present under tests/fixtures/ingredients/
- database/data/ciqual-2025.xml present with CC-BY 4.0 attribution
- Commits 8cd13a0, 4a8d4a0, aa0ace8 confirmed in git log
- 14 tests passing: ImportCiqualTest (5), ImportUsdaTest (4), ImportOpenFoodFactsTest (5)
- All 3 commands appear in `php artisan list`
