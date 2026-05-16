---
phase: 02-ingredient-library
verified: 2026-05-16T00:00:00Z
status: passed
score: 5/5 success criteria verified
re_verification: false
---

# Phase 2: Ingredient Library — Verification Report

**Phase Goal:** Users can search and use a rich official ingredient library, and can create private ingredients when something is missing
**Verified:** 2026-05-16
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | User can search the official ingredient library by name and get results in Greek and English | VERIFIED | `IngredientSearchTest` passes (52 total tests, 0 failures). `IngredientController::index` uses `whereHas('translations', whereFullText/like)` over `ingredient_translations`. Greek (`el`) and English (`en`) translations are stored via `syncTranslation`. |
| 2 | Ingredients returned show nutrition values (calories, macros, micronutrients), allergen flags, and unit-conversion data | VERIFIED | `IngredientDetailTest` and `IngredientAllergenTest` and `IngredientConversionTest` pass. `Ingredient` model has all 29 nutrition columns cast to `decimal:4`. `IngredientDetailResource` exposes nutrition, allergens (with pivot state), and conversions. The detail page renders `NutritionPanel`, `AllergenPanel`, and a Conversions tab. |
| 3 | An Artisan import command can be run for each of CIQUAL, USDA FDC, and Open Food Facts independently and idempotently | VERIFIED | `ImportCiqualTest`, `ImportUsdaTest`, `ImportOpenFoodFactsTest` all pass. Three commands exist: `ingredients:import-ciqual` (XMLReader streaming, two-file --alim-file + --compo-file), `ingredients:import-usda` (CSV streaming with data_type filter), `ingredients:import-off`. All use `IngredientImporter::upsertIngredients` with DB::upsert on `(source, source_id)` — idempotent by design. `resetVerifiedForChangedRows` resets verified badge on data change. Dev database holds 3,895 real ingredients as live evidence. |
| 4 | User can create a private ingredient with nutrition, allergen, and conversion data that only they can see | VERIFIED | `PrivateIngredientTest` passes. `PrivateIngredientController::store` sets `user_id = auth()->id()`, `source = 'user'`. `IngredientPolicy` allows update/delete only by owner and blocks official ingredients. `IngredientController::index` base scope excludes other users' private ingredients via `->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', auth()->id()))`. |
| 5 | User can record a price for any ingredient (private or official) with date and currency | VERIFIED | `IngredientPriceTest` passes. `IngredientPriceController::store` creates `IngredientPrice` with `user_id = auth()->id()`. `PerGramCostCalculator` uses `Brick\Math\BigDecimal` — no PHP floats. `StorePriceRequest` validates `before_or_equal:today`. Prices tab added to detail page with `PriceForm` and `PriceHistory`. |

**Score: 5/5**

---

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Models/Ingredient.php` | VERIFIED | 196 lines. Has `SoftDeletes`, all relationships (`translations`, `allergens`, `conversions`, `prices`, `category`, `user`, `verifiedBy`), `nameFor()`, `isOfficial()`, `isPrivate()` helpers. 29 nutrition columns + 11 frozen-dessert reserved columns. |
| `app/Models/IngredientCategory.php` | VERIFIED | Exists with `parent`, `children`, `ingredients` relations. |
| `app/Models/IngredientTranslation.php` | VERIFIED | Exists with `ingredient` relation. |
| `app/Models/IngredientConversion.php` | VERIFIED | Exists with `ingredient`, `unit` relations. |
| `app/Models/IngredientPrice.php` | VERIFIED | Exists with `ingredient`, `user`, `unit` relations. |
| `app/Console/Commands/ImportCiqual.php` | VERIFIED | Uses XMLReader streaming with `--alim-file`/`--compo-file` flags. Correct ANSES constituent code map. No `simplexml_load_file` on full file. |
| `app/Console/Commands/ImportUsdaFdc.php` | VERIFIED | CSV streaming with column-header detection. `data_type` filter for real foods. `syncConversion` for food_portion rows. |
| `app/Console/Commands/ImportOpenFoodFacts.php` | VERIFIED | `syncTranslation` for Greek enrichment. `syncAllergens` for allergen pivot. `Http::` for streaming download path. |
| `app/Support/Ingredients/IngredientImporter.php` | VERIFIED | Has `upsertIngredients` (array_chunk 500), `resetVerifiedForChangedRows`, `syncTranslation`, `syncAllergens`, `syncConversion`, `dataHash`. `verified` excluded from upsert update columns. |
| `app/Http/Controllers/Ingredients/IngredientController.php` | VERIFIED | `index()` with full-text search, 4 filters, private-user scope. `show()` with abort(404) for other users' private ingredients. |
| `app/Http/Controllers/Ingredients/PrivateIngredientController.php` | VERIFIED | Full CRUD. `auth()->id()` on store. `authorize('update', ...)` and `authorize('delete', ...)`. |
| `app/Http/Controllers/Admin/IngredientVerificationController.php` | VERIFIED | Sets `verified_by` + `verified_at`. Route gated by `permission:verify-ingredients` middleware. |
| `app/Http/Controllers/Ingredients/IngredientPriceController.php` | VERIFIED | `per_gram_cost` computed. `auth()->id()` on store. Private-ingredient visibility guard. |
| `app/Support/Ingredients/PerGramCostCalculator.php` | VERIFIED | Uses `Brick\Math\BigDecimal` throughout. Zero PHP float arithmetic found (grep count = 0). |
| `app/Policies/IngredientPolicy.php` | VERIFIED | `update` and `delete` check `user_id !== null && user_id === $user->id`. |
| `app/Concerns/IngredientValidationRules.php` | VERIFIED | `required_without_all:name_el,name` rule. All 29 nutrition columns, allergens array, conversions array rules. |
| `database/seeders/RolesAndPermissionsSeeder.php` | VERIFIED | `verify-ingredients` added to moderator and admin permissions. |
| `database/seeders/IngredientCategorySeeder.php` | VERIFIED | `firstOrCreate` seeder pattern. Wired in `DatabaseSeeder`. |
| `resources/js/pages/ingredients/index.tsx` | VERIFIED | `router.reload`, `only: ['ingredients']`, `replace: true`, `aria-busy` all present. |
| `resources/js/pages/ingredients/show.tsx` | VERIFIED | `Tabs` with Nutrition / Allergens / Conversions / Prices. |
| `resources/js/pages/ingredients/create.tsx` | VERIFIED | Exists. |
| `resources/js/components/ingredients/ingredient-row.tsx` | VERIFIED | Exists. |
| `resources/js/components/ingredients/allergen-icons.tsx` | VERIFIED | `aria-label` present. |
| `resources/js/components/ingredients/nutrition-panel.tsx` | VERIFIED | `energy_kcal`, `vitamin_a_ug` present. Em dash (`—`) for null values. |
| `resources/js/components/ingredients/allergen-panel.tsx` | VERIFIED | `may_contain` and `destructive` colour contract. |
| `resources/js/components/ingredients/verify-action.tsx` | VERIFIED | `router.post` to verify route. |
| `resources/js/components/ingredients/price-form.tsx` | VERIFIED | `useForm` present. `prices` route reference present. |
| `resources/js/components/ingredients/price-history.tsx` | VERIFIED | `per_gram_cost` column rendered. |
| `resources/js/components/app-sidebar.tsx` | VERIFIED | Ingredients nav entry with Wayfinder `ingredientsIndex()` href. |
| `lang/en/app.php` | VERIFIED | `Ingredient Library`, `Search ingredients` keys present. |
| `lang/el/app.php` | VERIFIED | `ingredients` section with Greek translations present. |
| `tests/Feature/Ingredients/` (12 files) | VERIFIED | All 12 files exist. Zero `->skip()` calls. All 52 tests pass. |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `app/Models/Ingredient.php` | `ingredient_translations` table | `translations() hasMany` | WIRED | `function translations` returns `hasMany(IngredientTranslation::class)` |
| `app/Models/Ingredient.php` | `allergens` table | `allergens() belongsToMany` with pivot | WIRED | `belongsToMany(Allergen::class, 'ingredient_allergen')->withPivot('state')` |
| `database/seeders/RolesAndPermissionsSeeder.php` | `verify-ingredients` permission | `Permission::firstOrCreate` | WIRED | Line 22 adds to `$moderatorPermissions` array |
| `app/Console/Commands/ImportCiqual.php` | `ingredients` table | `DB::upsert` on `(source, source_id)` | WIRED | `IngredientImporter::upsertIngredients` uses `DB::table('ingredients')->upsert(..., ['source', 'source_id'], ...)` |
| `app/Console/Commands/ImportOpenFoodFacts.php` | `ingredient_translations` table | Greek name enrichment | WIRED | `$importer->syncTranslation($matchedId, 'el', $productNameEl)` on line 169 |
| `app/Console/Commands/ImportUsdaFdc.php` | `ingredient_conversions` table | `food_portion` gram-weight rows | WIRED | `$importer->syncConversion(...)` called in `syncPortions()` |
| `resources/js/pages/ingredients/index.tsx` | `/ingredients` route | `router.reload` partial | WIRED | `router.reload({ data: ..., only: ['ingredients'], replace: true })` |
| `app/Http/Controllers/Ingredients/IngredientController.php` | `ingredient_translations` table | `whereHas` translations search | WIRED | `->whereHas('translations', fn ($t) => $t->when(DB::getDriverName() !== 'sqlite', ...whereFullText..., ...where like...))` |
| `resources/js/components/app-sidebar.tsx` | `/ingredients` | Ingredients nav entry | WIRED | Imports `ingredientsIndex` from Wayfinder `@/routes/ingredients`; used in `mainNavItems` |
| `routes/web.php` | `verify-ingredients` permission | middleware on verify route | WIRED | `Route::middleware(['auth', 'verified', 'permission:verify-ingredients'])` wraps the verify route |
| `app/Http/Controllers/Admin/IngredientVerificationController.php` | `ingredients` table | sets `verified_by` + `verified_at` | WIRED | Lines 20-21: `$ingredient->verified_by = auth()->id(); $ingredient->verified_at = now();` |
| `resources/js/pages/ingredients/show.tsx` | `/ingredients/{id}` controller | Inertia page render `ingredients/show` | WIRED | Controller returns `Inertia::render('ingredients/show', ...)` |
| `app/Http/Controllers/Ingredients/IngredientPriceController.php` | `ingredient_prices` table | `per_gram_cost` computed + stored | WIRED | `per_gram_cost` computed via `PerGramCostCalculator` and stored in `IngredientPrice::create(...)` |
| `app/Support/Ingredients/PerGramCostCalculator.php` | `units` table `base_factor` | weight-unit gram conversion | WIRED | `BigDecimal::of($unit->base_factor)` used for weight units; `ingredient_conversions` lookup for volume/count |

---

### Requirements Coverage

| Requirement | Plans | Description | Status | Evidence |
|-------------|-------|-------------|--------|----------|
| INGR-01 | 02-03 | User can search the official ingredient library by name | SATISFIED | `IngredientSearchTest` passes; `whereFullText`/`like` on `ingredient_translations.name` |
| INGR-02 | 02-02 | Official library seeded from CIQUAL, USDA FDC, Open Food Facts | SATISFIED | Three import commands exist and tests pass; 3,895 real ingredients in dev DB |
| INGR-03 | 02-01, 02-05 | Ingredients store nutrition values (29 columns) | SATISFIED | All 29 nutrition columns in migration; cast to `decimal:4`; exposed in `IngredientDetailResource` |
| INGR-04 | 02-01, 02-02, 02-04 | Ingredients store EU 14-allergen data | SATISFIED | `ingredient_allergen` pivot with `state` column; `syncAllergens` in importer; `AllergenChecklist` in create form |
| INGR-05 | 02-01, 02-02, 02-04 | Ingredients store unit-conversion data | SATISFIED | `ingredient_conversions` table; `syncConversion` in importer; `ConversionRows` in create form |
| INGR-06 | 02-01, 02-02, 02-03 | Names stored and displayed in Greek and English minimum | SATISFIED | `ingredient_translations` table with locale; `syncTranslation` for en/fr/el; search matches both; `nameFor(locale)` helper |
| INGR-07 | 02-04 | User can create a private ingredient | SATISFIED | `PrivateIngredientController` with full CRUD; `IngredientPolicy` owner-only; `PrivateIngredientTest` passes |
| INGR-08 | 02-06 | User can record a price with date and currency | SATISFIED | `IngredientPriceController::store`; `PerGramCostCalculator` (brick/math); `IngredientPriceTest` passes |

All 8 required INGR requirements for Phase 2 are SATISFIED. INGR-09, INGR-10, INGR-11 are correctly deferred to Phase 7.

---

### Anti-Patterns Found

No blockers or warnings found. The only `return null` instances in the codebase were intentional (calculator returning null when no conversion exists, used correctly as a validation signal). No TODO/FIXME/placeholder implementation stubs found in production code.

| File | Pattern | Severity | Assessment |
|------|---------|----------|------------|
| `app/Support/Ingredients/PerGramCostCalculator.php` | `return null` | Info | Intentional API — null signals no conversion available; caller converts to validation error |

---

### Human Verification Required

The following items cannot be verified programmatically and require a human review session:

**1. Live search behaviour in the browser**
- Test: Log in, open `/ingredients`, type 3–4 characters in the search box
- Expected: Results update live without a full page reload; URL query string updates; skeleton rows show during the debounced reload
- Why human: Debounce timing (300ms), aria-busy skeleton rendering, and browser URL sync cannot be asserted by backend tests

**2. Greek/English display based on user locale**
- Test: Switch the app locale to Greek (el), open the ingredient library
- Expected: Ingredient names display in Greek where a Greek translation exists; the secondary-locale name below shows English
- Why human: Locale switching and rendering are visual/interactive behaviours

**3. Allergen icon rendering**
- Test: View an ingredient that has `contains: gluten` and `may_contain: milk` allergens
- Expected: Gluten icon at full opacity, milk icon at 50% opacity; hovering shows the allergen name tooltip
- Why human: Visual opacity, tooltip, and icon selection require browser inspection

**4. Moderator verify flow with confirmation dialog**
- Test: Log in as a Moderator, open an unverified ingredient's detail page, click "Mark as Verified"
- Expected: Dialog appears with correct copy; confirming sets the badge and shows a sonner toast; plain User does not see the button
- Why human: Dialog interaction, permission-gated UI visibility, and toast rendering

**5. Light/dark theme consistency**
- Test: Toggle dark mode; verify the ingredient library, detail page, create form, and price form
- Expected: All components render correctly in both themes with no colour contrast failures
- Why human: Theme rendering is visual

**6. CIQUAL import requires two real ANSES files**
- Note: The bundled `database/data/ciqual-2025.xml` from the original plan was replaced by `--alim-file` + `--compo-file` flags. The dev database holds 3,484 CIQUAL 2025 records as evidence the rewritten command works against the real dataset. The fixture files for tests are `tests/fixtures/ingredients/ciqual-alim-sample.xml` and `tests/fixtures/ingredients/ciqual-compo-sample.xml`.
- Why human: Confirms the operational import documentation/runbook is clear for future imports

---

### Gaps Summary

No gaps found. All five success criteria are verified against the actual codebase. All 52 tests in `tests/Feature/Ingredients/` pass. All 8 requirement IDs (INGR-01 through INGR-08) are satisfied with concrete implementation evidence.

One design evolution from the original plan: the CIQUAL import command was rewritten from a bundled single-file `--source-file` signature to a two-file `--alim-file` + `--compo-file` signature matching the official ANSES two-file export format. This change was intentional (the original bundled XML approach used a fabricated format) and is documented in the additional context. The command correctly uses `XMLReader` streaming and the correct constituent codes.

---

_Verified: 2026-05-16_
_Verifier: Claude (gsd-verifier)_
