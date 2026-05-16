# Phase 2: Ingredient Library - Research

**Researched:** 2026-05-16
**Domain:** Food data ingestion (CIQUAL/USDA/OFF), multi-source ingredient schema, live search, private ingredient CRUD, per-user pricing
**Confidence:** HIGH (schema, Laravel patterns, Inertia search); MEDIUM (external data format details)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **One `ingredients` table** holds both official and private ingredients, distinguished by `user_id` (null = official).
- **Category is required** on every ingredient; nested 2-level tree (category → subcategory), default set seeded, expandable without schema change.
- **Nutrition, allergens, conversions** follow Project.md §6. Allergens reuse Phase 1 `allergens` table; two states per ingredient: `contains` and `may_contain`. Conversions use a per-ingredient `ingredient_conversions` table layered on Phase 1 `units`; everything normalises to grams.
- **Multi-language names** stored in a separate translations table; schema must not hard-limit to two languages.
- **Reserved frozen-dessert fields** (`total_solids_pct`, `fat_pct`, `msnf_pct`, `pac_coefficient`, `pod_coefficient`, `de_value`, `brix`, `ingredient_class`) on the `ingredients` table — nullable, unused this phase.
- **Numeric precision** — `DECIMAL` columns, `brick/math` `BigDecimal` for all arithmetic.
- **Live as-you-type search** — debounced; matches Greek and English names; compact list rows (name EL/EN, calories, allergen icons).
- **Three filters at launch:** Source (official / my private), Allergen-free (exclude containing), Verified only.
- **Dedicated ingredient detail page** per ingredient (shareable route).
- **Private ingredient creation** — blank form or duplicate-from-official; minimum required: name + category; allergens/conversions/price optional.
- **Price recording** on the detail page; per-user private; full dated history; amount-for-quantity+unit normalised to per-gram cost via unit converter; EUR default; no FX conversion.
- **Ingredient verification** — global flag (not per-user); stores who verified and when; only Moderator/Admin can verify; re-import that changes stored data resets to unverified.
- **Three separate idempotent Artisan commands** (one per source); source authority order: CIQUAL primary → USDA backfill → OFF enrichment.
- **CIQUAL XML bundled in repo**; USDA and OFF fetched on demand.
- **Full imports run** — all three sources, thousands of ingredients, before Phase 3.
- **Greek-name strategy** — OFF cross-reference where matched; English fallback otherwise; no machine-translation.
- **Incomplete records imported, not skipped.**
- **Newly imported ingredients are unverified by default.**

### Claude's Discretion

- Starter category tree (exact names and initial set within "2-level, default set, expandable").
- Which nutrient set to store and display (CIQUAL exposes ~74; curated panel covering INGR-03).
- Exact permission name for verification (`verify-ingredients` vs. reuse `review-ingredients`).
- Cross-source matching strategy (USDA backfill + OFF enrichment matching against CIQUAL).
- Debounce timing, search ranking, pagination/virtualisation for results list.
- Import-command ergonomics (progress, batching, transaction handling, on-demand USDA/OFF download/cache).
- Duplicate-from-official UX (which fields copy, link to source).
- Detail-page layout and price-history presentation.

### Deferred Ideas (OUT OF SCOPE)

- Private-ingredient submission → moderator review → promotion (INGR-09, 10, 11 — Phase 7).
- Dedicated "My Prices" overview page.
- Rich faceted filtering beyond the three launch filters.
- Machine-translation of ingredient names.
- Automatic FX conversion of prices.
- FoodEx2 classification codes (schema keeps optional `foodex2_code` hook only).
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INGR-01 | User can search the official ingredient library by name | Live search pattern, whereFullText / LIKE + FULLTEXT index, Inertia partial reload with `only`, debounce hook |
| INGR-02 | Official ingredient library is seeded from CIQUAL, USDA FDC, and Open Food Facts | Three Artisan import commands; CIQUAL XML structure; USDA CSV download and `food_portion`; OFF bulk CSV/JSONL with Greece filter |
| INGR-03 | Each ingredient stores nutrition values (calories, macros, sugars, sodium, fiber, micronutrients) | CIQUAL 74-nutrient field set; curated panel schema; DECIMAL + brick/math |
| INGR-04 | Each ingredient stores allergen information using the EU 14-allergen model | Reuse Phase 1 `allergens` table; pivot with `contains` / `may_contain` state |
| INGR-05 | Each ingredient stores unit-conversion data (portion/density) for normalising quantities to grams | `ingredient_conversions` table layered on `units`; USDA `food_portion` as data source |
| INGR-06 | Ingredient names stored and displayed in multiple languages (Greek and English minimum) | `ingredient_translations` table; locale from HandleInertiaRequests shared props |
| INGR-07 | User can create a private ingredient when the official library lacks one | Private ingredient CRUD; `user_id` ownership gate; FormRequest per action |
| INGR-08 | User can record a price for an ingredient with date and currency | `ingredient_prices` table; per-user; full history; normalise to per-gram |
</phase_requirements>

---

## Summary

Phase 2 delivers the ingredient library every recipe in Phase 3 draws from. It has three distinct technical surfaces: (1) a **data-ingestion pipeline** that imports thousands of ingredients from three external sources into a well-structured local schema, (2) a **live search + browse UI** built with Inertia partial reloads and a debounced search hook, and (3) a **private ingredient and pricing CRUD** following Phase 1 controller/FormRequest/Inertia patterns.

The biggest complexity is the ingestion pipeline. CIQUAL's 3,484-food XML is bundled in the repo and parsed with XMLReader (streaming, low memory). USDA Foundation Foods and SR Legacy (up to 54 MB CSV uncompressed) and Open Food Facts (~9 GB uncompressed global CSV, or the ~0.9 GB Greece-tagged subset) are fetched on demand, cached locally, and processed with `DB::upsert()` in chunks of ~500 rows. Each command is independently re-runnable: idempotency is guaranteed by upserting on a stable source key rather than insert-or-fail.

The search surface needs FULLTEXT indexes on the ingredient name translation columns (MySQL/MariaDB, which is the production target; SQLite dev fallback uses `LIKE`). Inertia v3 partial reloads with `only: ['ingredients']` and `preserveState: true` drive the debounced as-you-type experience without full page refreshes.

**Primary recommendation:** Implement the schema in Wave 1 (migrations + models + seeders), the import pipeline in Wave 2 (three Artisan commands), the search + browse UI in Wave 3, and private ingredient CRUD + pricing in Wave 4. This ordering ensures Phase 3 has a populated library to develop against.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `brick/math` | 0.14.x (already installed) | BigDecimal for all DECIMAL arithmetic | Project requirement; no float drift |
| `spatie/laravel-permission` | already installed | Permission gate for verify action | Phase 1 foundation; `verify-ingredients` permission |
| PHP XMLReader | built-in | Streaming CIQUAL XML parser | SimpleXML loads entire file into memory; XMLReader streams it node by node |
| `League\Csv` / `fgetcsv()` | built-in PHP or `league/csv` | USDA and OFF CSV parsing | UTF-8 handling; chunked reading |
| `Illuminate\Http\Client` (Laravel HTTP) | Laravel 13 built-in | On-demand fetch of USDA and OFF downloads | Already available; streamed download to storage |
| Inertia v3 partial reloads | already installed | Live search without full page reload | `router.reload({ only: ['ingredients'], preserveState: true })` |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `league/csv` | ~9.x | Robust CSV parsing with BOM handling | If USDA/OFF CSVs have encoding edge cases; check if already in vendor |
| Laravel Scout | built-in | Full-text search abstraction | Only if search performance on SQLite becomes a blocker in dev; not recommended as the default path given the project already uses MySQL in production |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `whereFullText` + FULLTEXT index | Laravel Scout + Meilisearch | Scout adds operational complexity (separate service); FULLTEXT index is sufficient for thousands of rows |
| `DB::upsert()` chunks | `updateOrCreate()` per row | `DB::upsert()` is 50–100× faster for bulk; use it for import commands |
| XMLReader streaming | `simplexml_load_file()` | SimpleXML loads the entire 3,484-food CIQUAL XML into memory; fine for 3 MB but XMLReader is the scalable habit |
| PHP `fgetcsv()` | `league/csv` | Both work; `league/csv` adds BOM stripping and UTF-8 normalisation for free |

**Installation (no new deps expected):**
```bash
# brick/math and spatie/laravel-permission already installed.
# Only add league/csv if fgetcsv encoding issues arise:
composer require league/csv
```

---

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Console/Commands/
│   ├── ImportCiqual.php          # php artisan ingredients:import-ciqual
│   ├── ImportUsdaFdc.php         # php artisan ingredients:import-usda
│   └── ImportOpenFoodFacts.php   # php artisan ingredients:import-off
├── Http/Controllers/Ingredients/
│   ├── IngredientController.php  # index (search/browse) + show (detail)
│   ├── PrivateIngredientController.php  # store, update, destroy (private)
│   └── IngredientPriceController.php    # store (price recording)
├── Http/Requests/Ingredients/
│   ├── StoreIngredientRequest.php
│   ├── UpdateIngredientRequest.php
│   └── StorePriceRequest.php
├── Http/Requests/Admin/
│   └── VerifyIngredientRequest.php
├── Http/Controllers/Admin/
│   └── IngredientVerificationController.php  # store (verify action)
├── Models/
│   ├── Ingredient.php
│   ├── IngredientCategory.php
│   ├── IngredientTranslation.php
│   ├── IngredientConversion.php
│   └── IngredientPrice.php
├── Concerns/
│   └── IngredientValidationRules.php
resources/js/pages/ingredients/
├── index.tsx          # search + browse
├── show.tsx           # ingredient detail
└── create.tsx         # private ingredient form
```

### Pattern 1: Multi-language Name Storage

**What:** A separate `ingredient_translations` table holds one row per (ingredient_id, locale) pair, containing the ingredient name and optional description. The `Ingredient` model has a `translations()` hasMany relationship and a `nameFor(string $locale)` helper.

**When to use:** Every ingredient; the locale comes from the `locale` already shared by `HandleInertiaRequests`.

**Example:**
```php
// ingredient_translations table:
// id | ingredient_id | locale | name | created_at | updated_at
// UNIQUE KEY (ingredient_id, locale)

// Ingredient model:
public function translations(): HasMany
{
    return $this->hasMany(IngredientTranslation::class);
}

public function nameFor(string $locale): string
{
    return $this->translations->firstWhere('locale', $locale)?->name
        ?? $this->translations->firstWhere('locale', 'en')?->name
        ?? '—';
}
```

### Pattern 2: Idempotent Import Command (upsert-on-stable-key)

**What:** Each import command reads its source, maps to an array of rows, then calls `DB::upsert()` in chunks. The unique key is the source's stable identifier (`alim_code` for CIQUAL, `fdc_id` for USDA, `code` for OFF). A re-run updates existing rows and inserts new ones; it never duplicates.

**When to use:** All three import commands.

**Example:**
```php
// Source: Laravel docs + established project pattern
$chunks = array_chunk($rows, 500);
foreach ($this->withProgressBar($chunks) as $chunk) {
    DB::table('ingredients')->upsert(
        $chunk,
        uniqueBy: ['source', 'source_id'],   // unique index required
        update:   ['name_cache', 'calories', 'protein', 'fat', 'carbs',
                   'verified' => false,        // re-import resets verification
                   'updated_at' => now()]
    );
}
```

Note: `DB::upsert()` requires a database-level unique index on `(source, source_id)`. Create it in the migration.

### Pattern 3: Inertia v3 Debounced Live Search

**What:** The search page keeps search/filter state in URL query params. A debounced `useCallback` calls `router.reload({ only: ['ingredients'], preserveState: true, replace: true })` on state change. The controller reads `$request->string('search')` and returns a paginated prop.

**When to use:** Ingredient search page; the same pattern the admin user list uses (see `UserController::index()`).

**Example:**
```tsx
// resources/js/pages/ingredients/index.tsx
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEBOUNCE_MS = 300;

export default function IngredientIndex({ ingredients, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const timer = useRef<ReturnType<typeof setTimeout>>(null);

    const reload = useCallback((params: Record<string, string>) => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            router.reload({
                data: params,
                only: ['ingredients'],
                preserveState: true,
                replace: true,
            });
        }, DEBOUNCE_MS);
    }, []);

    useEffect(() => {
        reload({ search, source: filters.source, allergen_free: filters.allergen_free });
    }, [search, filters.source, filters.allergen_free]);

    // ...
}
```

Server side:
```php
// IngredientController::index()
public function index(Request $request): Response
{
    $search   = $request->string('search')->toString();
    $source   = $request->string('source', 'all')->toString();
    $verified = $request->boolean('verified_only');

    $ingredients = Ingredient::query()
        ->with(['translations', 'allergens'])
        ->when($search, fn ($q) => $q->whereHas('translations', fn ($t) =>
            $t->whereFullText('name', $search)   // MySQL/MariaDB production
              ->orWhere('name', 'like', "%{$search}%")  // SQLite fallback via DB conditional
        ))
        ->when($source === 'official', fn ($q) => $q->whereNull('user_id'))
        ->when($source === 'private',  fn ($q) => $q->where('user_id', auth()->id()))
        ->when($verified,              fn ($q) => $q->where('verified', true))
        ->orderBy('name_cache')  // denormalised for fast sort
        ->paginate(30)
        ->withQueryString();

    return Inertia::render('ingredients/index', [
        'ingredients' => $ingredients,
        'filters'     => ['search' => $search, 'source' => $source, 'verified_only' => $verified],
    ]);
}
```

**Search implementation note:** The project uses SQLite for development (per INTEGRATIONS.md). `whereFullText` is MySQL/PostgreSQL-only. Use a database-conditional approach: wrap in a `when(DB::getDriverName() !== 'sqlite')` check, falling back to `LIKE` for SQLite tests. Add a FULLTEXT index on `ingredient_translations.name` in the migration (MySQL only).

### Pattern 4: Cross-Source Matching Strategy

**What:** USDA is used as backfill (new ingredients CIQUAL does not have) and as conversion-data enrichment (food_portion rows for CIQUAL ingredients). OFF is used purely as enrichment (Greek names, allergen tags).

**Matching approach (Claude's discretion — recommended):**

For USDA backfill:
- The import maintains a separate `ingredient_source_keys` table (or columns `source` + `source_id` directly on `ingredients`). CIQUAL rows are stored with `source='ciqual'` and `source_id=alim_code`. USDA rows are stored with `source='usda'` and `source_id=fdc_id`. No fuzzy matching between sources — USDA simply adds rows not present in CIQUAL. The source of truth for duplicates is a manually-maintained cross-reference table (future work; not needed for Phase 2 bootstrap).

For USDA food_portion enrichment of CIQUAL ingredients:
- USDA `food_portion.csv` rows are stored in `ingredient_conversions` regardless of whether the parent ingredient came from CIQUAL or USDA. The `fdc_id` is the key; CIQUAL ingredients that happen to have a matching USDA entry can be linked by a stored `usda_fdc_id` optional column on `ingredients`.

For OFF enrichment (Greek names):
- OFF products are matched to existing ingredients by barcode (OFF `code` field) or by exact English name normalisation (`product_name_en`). When a match is found, a Greek translation is added to `ingredient_translations` if one does not already exist. If no match, the OFF product is added as a new ingredient with `source='off'`.

**Idempotency guarantee:** All three matching operations upsert on their stable key; re-running a command is always safe.

### Pattern 5: Allergen Pivot with State

**What:** A `ingredient_allergen` pivot table with three columns: `ingredient_id`, `allergen_id`, `state` (enum: `contains` | `may_contain`). Managed via the existing `Allergen` model from Phase 1.

**Example:**
```php
// Ingredient model:
public function allergens(): BelongsToMany
{
    return $this->belongsToMany(Allergen::class, 'ingredient_allergen')
                ->withPivot('state')
                ->withTimestamps();
}

// Setting allergens:
$ingredient->allergens()->sync([
    $allergenId => ['state' => 'contains'],
]);
```

### Anti-Patterns to Avoid

- **Wrapping the entire import in one transaction.** If row 40,000 fails, the whole import rolls back. Use per-chunk transactions instead.
- **Using `updateOrCreate()` per row in a loop for bulk import.** Emits one query per row. Use `DB::upsert()` in chunks.
- **Hardcoding locale in ingredient names.** Names live in `ingredient_translations`; the `Ingredient` model never stores a single-language name field (except `name_cache` as a denormalised sort/search helper).
- **Calling `simplexml_load_file()` on CIQUAL XML.** It loads the entire file into memory. Use XMLReader streaming.
- **Using `router.visit()` for live search.** It replaces navigation history on every keystroke. Use `router.reload()` with `replace: true` and `preserveState: true`.
- **Gating verify on the `Moderator` role directly.** Gate on the `verify-ingredients` permission. Role-based gates make future fine-grained control expensive.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Bulk upsert | Per-row `updateOrCreate()` loop | `DB::upsert($rows, ['source','source_id'], $updateCols)` | 50–100× faster; single query per chunk |
| Decimal arithmetic on nutrition / price | `round()` or PHP `float` | `brick/math` `BigDecimal` | Already installed; avoids float drift at scale |
| Allergen EU compliance | Custom allergen table | Phase 1 `allergens` table (already seeded) | Already seeded with all 14 EU Reg. 1169/2011 allergens |
| Unit base conversion | Custom conversion math | Phase 1 `units` table + `base_factor` | Standard intra-type math is already modelled |
| XML streaming | `simplexml_load_file()` | PHP `XMLReader` (built-in) | CIQUAL is ~3–5 MB XML; XMLReader uses O(1) memory |
| HTTP file download | `file_get_contents()` | `Http::sink()` / `Http::withOptions(['stream' => true])` | Laravel HTTP Client supports streaming to disk without memory spike |
| Role permission enforcement | Checking `$user->hasRole('Moderator')` | `$this->authorize('verify-ingredients')` | Consistent with Phase 1 pattern; permission-gated |
| Progress output in commands | Custom output loop | `$this->withProgressBar($items, $callback)` | Built into Laravel artisan; one line |

**Key insight:** The hard part of ingredient import is data, not code. The matching and normalisation logic matters more than the transport layer; use Laravel's built-in tools for the transport so energy goes into data quality.

---

## Common Pitfalls

### Pitfall 1: whereFullText Fails on SQLite Test Database

**What goes wrong:** The production database is MySQL (per INTEGRATIONS.md), but tests run on SQLite in-memory (`phpunit.xml`). `whereFullText()` is MySQL/PostgreSQL-only and throws an exception on SQLite.

**Why it happens:** The query builder routes `whereFullText` to a grammar method that SQLite does not implement.

**How to avoid:** Wrap the full-text clause in a database driver check:
```php
->when(
    DB::getDriverName() !== 'sqlite',
    fn ($q) => $q->whereFullText('name', $search),
    fn ($q) => $q->where('name', 'like', "%{$search}%")
)
```
Or use a `LIKE` search exclusively if the dataset stays under ~10,000 rows during testing, and reserve FULLTEXT for production.

**Warning signs:** `BadMethodCallException: Call to undefined method ... grammar` in Pest test output.

### Pitfall 2: Import Resets `verified` on Re-run Without Tracking Data Changes

**What goes wrong:** The decision says "a re-import that changes stored data resets verification." If every re-run unconditionally sets `verified = false`, unchanged ingredients lose their verified status on every import — moderators' work is erased.

**Why it happens:** Naive upsert always overwrites `verified`.

**How to avoid:** Only reset `verified` when nutrient or name data actually changes. One approach: store a `data_hash` column (hash of the imported payload) alongside `verified`. On upsert, compare hashes; only reset `verified` when the hash differs:
```php
$hash = md5(serialize($nutrientValues));
// In the upsert update clause:
// verified = IF(data_hash != VALUES(data_hash), FALSE, verified),
// data_hash = VALUES(data_hash)
```
MySQL supports this conditional in a raw upsert expression. Alternatively, implement as two passes: upsert without touching `verified`, then a second query that resets `verified = false WHERE data_hash != new_hash AND verified = true`.

**Warning signs:** Moderators report verified ingredients losing their badge after routine imports.

### Pitfall 3: Large OFF CSV Download Bloating Storage

**What goes wrong:** The Open Food Facts full CSV is ~9 GB uncompressed. Naively downloading it to `storage/` bloats the repo path and local disk.

**Why it happens:** `Http::get(url)->body()` buffers the entire response in memory.

**How to avoid:** Use `Http::sink()` to stream directly to a temp file path, and clean up after processing:
```php
$path = storage_path('app/tmp/off-import.csv.gz');
Http::sink($path)->get($offUrl);
// stream-parse the gz without full unzip...
// after processing:
@unlink($path);
```
For the Greece-specific subset (~0.9 GB compressed), download `https://static.openfoodfacts.org/data/en.openfoodfacts.org.products.csv.gz` and filter client-side on `countries_en` containing `Greece`.

**Warning signs:** Import command running out of memory; `storage/app/tmp/` growing unboundedly.

### Pitfall 4: Brick/Math BigDecimal Not Used for DECIMAL Column Reads

**What goes wrong:** Eloquent casts `DECIMAL` columns as PHP `float` by default. Once a float is created, precision is already lost.

**Why it happens:** The `decimal:N` Eloquent cast still returns a PHP `string`, not a `BigDecimal`. Multiplying two `decimal:6` cast values returns a PHP float.

**How to avoid:** Use `decimal:N` cast for storage (already done for `base_factor` in `Unit`), but always wrap reads in `BigDecimal::of($value)` before any arithmetic:
```php
use Brick\Math\BigDecimal;
$calories = BigDecimal::of($ingredient->calories);
$portionGrams = BigDecimal::of($conversion->gram_weight);
$portionCalories = $calories->multipliedBy($portionGrams)->dividedBy(100, 2);
```

**Warning signs:** Nutrition totals drift from expected values; float formatting shows unexpected rounding.

### Pitfall 5: Inertia Partial Reload Stale State After Filter Change

**What goes wrong:** When a user changes a filter (source or allergen-free), the search results update but URL params do not, breaking page refresh / deep-link sharing.

**Why it happens:** `preserveState: true` preserves React component state but does not automatically update the browser URL's query string.

**How to avoid:** Always pass `replace: true` along with `preserveState: true` in `router.reload()` so the URL stays in sync with the applied filters.

### Pitfall 6: Missing Unique Index for DB::upsert()

**What goes wrong:** `DB::upsert()` silently ignores the `uniqueBy` argument if no unique index exists; it behaves like a plain insert and duplicates rows on re-run.

**Why it happens:** Laravel's query builder does not validate that the unique index exists before emitting the SQL.

**How to avoid:** Always create the unique index in the migration:
```php
$table->unique(['source', 'source_id']);
```
Verify with `php artisan db:show --tables` after migrating.

---

## Code Examples

Verified patterns from official sources and the existing codebase:

### Artisan Command with Progress Bar and Chunked Upsert

```php
// app/Console/Commands/ImportCiqual.php
// Source: Laravel docs (withProgressBar) + existing seeder pattern (firstOrCreate on stable key)
public function handle(): int
{
    $xml = simplexml_load_string(
        file_get_contents(database_path('data/ciqual-2025.xml'))
    ); // OK for 3 MB bundled file

    $rows = $this->mapXmlToRows($xml);
    $chunks = array_chunk($rows, 500);

    $this->withProgressBar($chunks, function (array $chunk): void {
        DB::transaction(function () use ($chunk): void {
            DB::table('ingredients')->upsert(
                $chunk,
                uniqueBy: ['source', 'source_id'],
                update:   ['name_cache', 'calories', 'protein_g', 'fat_g',
                           'carbs_g', 'updated_at'],
            );
        });
    });

    $this->newLine();
    $this->info("CIQUAL import complete.");

    return self::SUCCESS;
}
```

### Streaming HTTP Download

```php
// Source: Laravel HTTP Client docs (Http::sink)
$tmpPath = storage_path('app/tmp/usda-foundation.zip');
Http::withOptions(['stream' => true])->sink($tmpPath)->get(
    'https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_foundation_food_csv_2021-04-28.zip'
);
// unzip, process CSV, then unlink($tmpPath)
```

### FULLTEXT Index Migration (MySQL-conditional)

```php
// In ingredients migration — add after table creation:
if (DB::getDriverName() === 'mysql') {
    DB::statement('ALTER TABLE ingredient_translations ADD FULLTEXT INDEX ft_name (name)');
}
```

### Allergen Sync During Import

```php
// Source: spatie/laravel-permission pattern + Phase 1 AllergenSeeder
$allergenMap = Allergen::pluck('id', 'slug'); // ['gluten' => 1, 'eggs' => 2, ...]
$pivotData = [];
foreach ($offProduct['allergens_tags'] as $tag) {
    $slug = str_replace('en:', '', $tag); // OFF tag: "en:gluten"
    if (isset($allergenMap[$slug])) {
        $pivotData[$allergenMap[$slug]] = ['state' => 'contains'];
    }
}
foreach ($offProduct['traces_tags'] as $tag) {
    $slug = str_replace('en:', '', $tag);
    if (isset($allergenMap[$slug]) && ! isset($pivotData[$allergenMap[$slug]])) {
        $pivotData[$allergenMap[$slug]] = ['state' => 'may_contain'];
    }
}
$ingredient->allergens()->sync($pivotData);
```

---

## External Data Sources — Authoritative Details

### CIQUAL 2025 (PRIMARY)

| Property | Value |
|----------|-------|
| URL | https://ciqual.anses.fr (download page) / also on Zenodo and data.gouv.fr |
| License | CC-BY 4.0 / Etalab 2.0 — attribution required: "Anses. 2025. Ciqual French food composition table 2025" |
| Format | XML (primary) + Excel |
| Size | ~3–5 MB XML (confirmed: 3,484 foods × 74 constituents) |
| Acquisition | Bundle XML in repo under `database/data/ciqual-2025.xml` |
| Stable key | `alim_code` (numeric food code, stable across versions) |
| Food name fields | `alim_nom_fr` (French), `alim_nom_eng` (English) |
| Nutrient value | Per 100 g, expressed as `teneur`; confidence code `code_confiance` |
| Groups | `alim_grp_code` + `alim_grp_nom_fr` (food group name) |
| Nutrient count | 74 constituents including energy, protein, fat, SFA, MUFA, PUFA, cholesterol, carbohydrates, sugars, starch, fibre, sodium, vitamins (A, B1–B12, C, D, E, K), minerals (Ca, Fe, Mg, P, K, Zn, etc.) |
| Greek names | Not available — English fallback until OFF enrichment |

**Confidence:** HIGH (official ANSES documentation + confirmed by MCP GitHub repo field names).

### USDA FoodData Central (BACKFILL + CONVERSION DATA)

| Property | Value |
|----------|-------|
| URL | https://fdc.nal.usda.gov/download-datasets/ |
| License | CC0 (public domain) |
| Recommended dataset | **SR Legacy** (final stable release, April 2018) — 7,793 foods, 6.7 MB zipped CSV, 54 MB uncompressed. Stable `fdc_id`. |
| Foundation Foods | April 2026 release, 3.7 MB zipped — smaller, more analytically rigorous, fewer foods. Use as supplement. |
| Stable key | `fdc_id` |
| Key CSV files | `food.csv` (fdc_id, description, food_category_id), `food_nutrient.csv` (fdc_id, nutrient_id, amount), `nutrient.csv` (id, name, unit_name), `food_portion.csv` (id, fdc_id, gram_weight, description, amount, measure_unit_id) |
| `food_portion` columns | `fdc_id`, `seq_num`, `amount`, `measure_unit_id`, `portion_description`, `modifier`, `gram_weight`, `data_points`, `footnote`, `min_year_acquired` |
| Acquisition | Download on-demand via command; cache zip in `storage/app/tmp/` |
| Use in Phase 2 | (a) Add foods missing from CIQUAL (`source='usda'`); (b) Add `ingredient_conversions` rows for both USDA-sourced and CIQUAL-matched ingredients |

**Confidence:** HIGH (official USDA FDC download page confirmed; `food_portion` column names from official field description documentation pattern).

### Open Food Facts (ENRICHMENT + GREEK PRODUCTS)

| Property | Value |
|----------|-------|
| URL | https://static.openfoodfacts.org/data/en.openfoodfacts.org.products.csv.gz |
| License | ODbL 1.0 — share-alike applies only to published enhanced databases; internal storage and in-app display are fine |
| Format | Tab-separated CSV (not comma); gzipped |
| Full size | ~0.9 GB compressed, ~9 GB uncompressed |
| Greece subset | Filter on `countries_en` column containing "Greece"; estimated ~8,500 products per Project.md §6 |
| Key fields | `code` (barcode/stable key), `product_name`, `product_name_el` (Greek), `product_name_en` (English), `allergens_en` (contains), `traces_en` (may-contain traces), `countries_en`, `brands`, `categories_en`, `energy-kcal_100g`, `proteins_100g`, `fat_100g`, `carbohydrates_100g`, `sugars_100g`, `fiber_100g`, `sodium_100g` |
| Acquisition | Download full CSV gz on-demand; filter to Greece in PHP while streaming (do not decompress fully to memory) |
| Allergen format | Tags like `en:gluten`, `en:milk` in allergens_en / traces_en (colon-separated) |

**Confidence:** MEDIUM (confirmed from OFF data page and openfoodfacts-exports GitHub README; exact Greece product count of "~8,500" from Project.md §6).

---

## Proposed Database Schema

### Tables to Create

```
ingredient_categories
  id, parent_id (nullable FK → self), name, slug, sort_order, timestamps
  (2-level tree: parent_id NULL = category, parent_id set = subcategory)

ingredients
  id, user_id (nullable FK → users — null = official), category_id (FK),
  source ENUM('ciqual','usda','off','user'), source_id (VARCHAR, source's stable key),
  usda_fdc_id (nullable, for cross-reference),
  name_cache (VARCHAR — denormalised from primary locale, for ORDER BY),
  verified BOOLEAN DEFAULT false, verified_by (nullable FK → users), verified_at (nullable TIMESTAMP),
  data_hash VARCHAR(32, for change detection to gate verified reset),
  foodex2_code (nullable VARCHAR — reserved, unused),
  -- nutrition per 100g (all DECIMAL(10,4), nullable):
  energy_kcal, protein_g, fat_g, saturated_fat_g, monounsaturated_fat_g,
  polyunsaturated_fat_g, carbs_g, sugars_g, starch_g, fibre_g, sodium_mg,
  -- micronutrients (DECIMAL(10,4), nullable):
  calcium_mg, iron_mg, magnesium_mg, phosphorus_mg, potassium_mg, zinc_mg,
  vitamin_a_ug, vitamin_b1_mg, vitamin_b2_mg, vitamin_b3_mg, vitamin_b5_mg,
  vitamin_b6_mg, vitamin_b9_ug, vitamin_b12_ug, vitamin_c_mg, vitamin_d_ug,
  vitamin_e_mg, vitamin_k_ug, cholesterol_mg,
  -- frozen-dessert reserved fields (all nullable):
  total_solids_pct DECIMAL(8,4), fat_pct DECIMAL(8,4), msnf_pct DECIMAL(8,4),
  sugar_pct DECIMAL(8,4), other_solids_pct DECIMAL(8,4), water_pct DECIMAL(8,4),
  pac_coefficient DECIMAL(8,4), pod_coefficient DECIMAL(8,4),
  de_value DECIMAL(8,4), brix DECIMAL(8,4),
  ingredient_class ENUM('sugar','dairy','fat','fruit','stabilizer','emulsifier','cocoa','alcohol','egg') nullable,
  timestamps, softDeletes
  UNIQUE KEY (source, source_id)

ingredient_translations
  id, ingredient_id (FK), locale (VARCHAR(10)), name (VARCHAR(500)), timestamps
  UNIQUE KEY (ingredient_id, locale)
  FULLTEXT INDEX ft_name (name)  -- MySQL only

ingredient_allergen  (pivot)
  id, ingredient_id (FK), allergen_id (FK), state ENUM('contains','may_contain'), timestamps
  UNIQUE KEY (ingredient_id, allergen_id)

ingredient_conversions
  id, ingredient_id (FK), from_amount DECIMAL(10,4), from_unit_id (FK → units),
  gram_weight DECIMAL(10,4), modifier VARCHAR(100) nullable,
  source ENUM('usda','user','curated'), source_ref VARCHAR(50) nullable, timestamps
  INDEX (ingredient_id)

ingredient_prices
  id, user_id (FK → users), ingredient_id (FK → ingredients),
  amount DECIMAL(12,4), currency CHAR(3) DEFAULT 'EUR',
  quantity DECIMAL(10,4), unit_id (FK → units),
  per_gram_cost DECIMAL(16,8),  -- normalised, computed on store
  recorded_at DATE, notes TEXT nullable, timestamps
  INDEX (user_id, ingredient_id, recorded_at)
```

### Starter Category Tree (Claude's Discretion)

```
1. Vegetables
   1.1 Leafy greens
   1.2 Root vegetables
   1.3 Brassicas
   1.4 Alliums (onion, garlic, leek)
   1.5 Fruiting vegetables (tomato, pepper, aubergine)
   1.6 Fungi (mushrooms)
2. Fruits
   2.1 Citrus
   2.2 Stone fruits
   2.3 Berries & small fruits
   2.4 Tropical fruits
   2.5 Pomes (apple, pear)
3. Grains & Starches
   3.1 Wheat & flours
   3.2 Rice & other grains
   3.3 Legumes (dried)
   3.4 Pasta & noodles
   3.5 Bread & bakery products
4. Dairy & Eggs
   4.1 Milk & cream
   4.2 Cheese
   4.3 Yoghurt & fermented
   4.4 Butter & ghee
   4.5 Eggs
5. Meat & Poultry
   5.1 Beef & veal
   5.2 Pork
   5.3 Lamb & mutton
   5.4 Poultry
   5.5 Processed meats & charcuterie
6. Fish & Seafood
   6.1 Finfish
   6.2 Shellfish & crustaceans
   6.3 Preserved & smoked fish
7. Oils, Fats & Condiments
   7.1 Vegetable oils
   7.2 Animal fats
   7.3 Vinegars & acids
   7.4 Sauces & condiments
8. Herbs & Spices
   8.1 Fresh herbs
   8.2 Dried spices
9. Nuts & Seeds
   9.1 Nuts
   9.2 Seeds
   9.3 Nut butters & pastes
10. Sweeteners & Sugar Products
    10.1 Sugars & syrups
    10.2 Honey & natural sweeteners
    10.3 Artificial & low-calorie sweeteners
11. Beverages
    11.1 Juices & nectars
    11.2 Soft drinks
    11.3 Alcoholic beverages
    11.4 Hot beverages (coffee, tea)
12. Prepared & Convenience Foods
    12.1 Stocks & broths
    12.2 Canned & preserved
    12.3 Frozen prepared
13. Other / Uncategorised
    13.1 Food additives & starches
    13.2 Supplements & enrichments
```

### Curated Nutrient Panel (Claude's Discretion — covering INGR-03)

Stored in `ingredients` table as individual DECIMAL columns (not JSON — allows indexed queries):

**Energy:** `energy_kcal`
**Macros:** `protein_g`, `fat_g`, `carbs_g`
**Fat detail:** `saturated_fat_g`, `monounsaturated_fat_g`, `polyunsaturated_fat_g`
**Carb detail:** `sugars_g`, `starch_g`
**Fibre:** `fibre_g`
**Minerals (INGR-03 sodium + micro):** `sodium_mg`, `calcium_mg`, `iron_mg`, `magnesium_mg`, `phosphorus_mg`, `potassium_mg`, `zinc_mg`
**Vitamins:** `vitamin_a_ug`, `vitamin_b1_mg`, `vitamin_b2_mg`, `vitamin_b3_mg`, `vitamin_b6_mg`, `vitamin_b9_ug`, `vitamin_b12_ug`, `vitamin_c_mg`, `vitamin_d_ug`, `vitamin_e_mg`, `vitamin_k_ug`
**Other:** `cholesterol_mg`

Total: 29 nutrition columns. All nullable (incomplete records imported). CIQUAL covers most; USDA fills gaps.

### Permission for Verification (Claude's Discretion)

Use `verify-ingredients` as a new named permission — distinct from `review-ingredients` (which is for Phase 7 moderation queue). Add `verify-ingredients` to both Moderator and Admin in `RolesAndPermissionsSeeder`.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `firstOrCreate()` per row in a loop | `DB::upsert()` in chunks | Laravel 8.0 (2020) | Import commands run in minutes not hours |
| Full Inertia page visit for every search keystroke | `router.reload({ only: [...] })` with `preserveState` | Inertia v1+ | No page flicker; URL stays in sync |
| Separate `router.get()` for search | Same page reload via `router.reload()` | Inertia v2+ | No history spam; back button works correctly |
| `Inertia::lazy()` for deferred props | `Inertia::optional()` | Inertia v3 | `lazy()` removed in v3 |
| Axios for XHR within Inertia | Built-in XHR client or `useHttp` hook | Inertia v3 | Axios no longer bundled |

**Deprecated/outdated:**
- `Inertia::lazy()`: Removed in v3; use `Inertia::optional()`.
- `router.cancel()`: Renamed to `router.cancelAll()` in v3.

---

## Open Questions

1. **CIQUAL 2025 XML exact download URL and filename**
   - What we know: Available from anses.fr, zenodo.org, and data.gouv.fr under CC-BY 4.0.
   - What's unclear: Exact filename for the 2025 release XML file.
   - Recommendation: Download manually from https://ciqual.anses.fr and commit to `database/data/ciqual-2025.xml` before the import command is written. Alternatively, download during the Wave 2 import task.

2. **USDA SR Legacy vs. Foundation Foods for conversion data**
   - What we know: SR Legacy is the final stable release (2018) with 7,793 foods and food_portion data; Foundation Foods (April 2026) is smaller but more analytically rigorous.
   - What's unclear: Whether Foundation Foods' food_portion coverage is sufficient for the ingredient types this app needs.
   - Recommendation: Use SR Legacy as the primary USDA dataset (stable, largest food_portion dataset); use Foundation Foods as a supplementary update for specific nutrients.

3. **Search performance at scale with SQLite in dev**
   - What we know: `whereFullText` is MySQL-only; SQLite falls back to `LIKE`; 3,000+ ingredients after import.
   - What's unclear: Whether `LIKE '%query%'` on 3,000 rows in SQLite will be acceptably fast for dev.
   - Recommendation: 3,000 rows with `LIKE` is fast enough for dev (sub-millisecond). FULLTEXT index is for production scale. No action needed.

4. **OFF data freshness for Greece**
   - What we know: Full OFF CSV is ~9 GB; country filter in the file; Greece subset ~8,500 products.
   - What's unclear: Whether a pre-filtered Greece-specific download exists at a stable URL.
   - Recommendation: Download the full CSV gz, stream-parse it, filter on `countries_en LIKE '%Greece%'` in PHP. Do not decompress to disk.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.7 + PHPUnit 12 |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=Ingredient` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INGR-01 | Search returns results matching both Greek and English names | Feature | `php artisan test --compact --filter=IngredientSearchTest` | ❌ Wave 0 |
| INGR-02 | CIQUAL import creates ingredients; re-run does not duplicate | Feature | `php artisan test --compact --filter=ImportCiqualTest` | ❌ Wave 0 |
| INGR-02 | USDA import creates new ingredients; re-run is idempotent | Feature | `php artisan test --compact --filter=ImportUsdaTest` | ❌ Wave 0 |
| INGR-02 | OFF import enriches Greek names; re-run is idempotent | Feature | `php artisan test --compact --filter=ImportOpenFoodFactsTest` | ❌ Wave 0 |
| INGR-03 | Ingredient detail page shows nutrition values | Feature | `php artisan test --compact --filter=IngredientDetailTest` | ❌ Wave 0 |
| INGR-04 | Allergen pivot stores contains / may_contain states correctly | Feature | `php artisan test --compact --filter=IngredientAllergenTest` | ❌ Wave 0 |
| INGR-05 | ingredient_conversions rows created from USDA food_portion data | Feature | `php artisan test --compact --filter=IngredientConversionTest` | ❌ Wave 0 |
| INGR-06 | Ingredient name returned in user's locale (el / en) | Feature | `php artisan test --compact --filter=IngredientTranslationTest` | ❌ Wave 0 |
| INGR-07 | User can create/update/delete a private ingredient; not visible to others | Feature | `php artisan test --compact --filter=PrivateIngredientTest` | ❌ Wave 0 |
| INGR-08 | User can record a price; history is per-user private; per-gram computed | Feature | `php artisan test --compact --filter=IngredientPriceTest` | ❌ Wave 0 |
| verify | Only Moderator/Admin can verify; re-import resets verification | Feature | `php artisan test --compact --filter=IngredientVerificationTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=Ingredient`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

All test files for this phase are new:

- [ ] `tests/Feature/Ingredients/IngredientSearchTest.php` — covers INGR-01
- [ ] `tests/Feature/Ingredients/ImportCiqualTest.php` — covers INGR-02 (CIQUAL)
- [ ] `tests/Feature/Ingredients/ImportUsdaTest.php` — covers INGR-02 (USDA)
- [ ] `tests/Feature/Ingredients/ImportOpenFoodFactsTest.php` — covers INGR-02 (OFF)
- [ ] `tests/Feature/Ingredients/IngredientDetailTest.php` — covers INGR-03
- [ ] `tests/Feature/Ingredients/IngredientAllergenTest.php` — covers INGR-04
- [ ] `tests/Feature/Ingredients/IngredientConversionTest.php` — covers INGR-05
- [ ] `tests/Feature/Ingredients/IngredientTranslationTest.php` — covers INGR-06
- [ ] `tests/Feature/Ingredients/PrivateIngredientTest.php` — covers INGR-07
- [ ] `tests/Feature/Ingredients/IngredientPriceTest.php` — covers INGR-08
- [ ] `tests/Feature/Ingredients/IngredientVerificationTest.php` — covers verify action

No new framework install needed — Pest 4.7 is already configured.

**Import command tests use local fixture files** (small sample XMLs/CSVs in `tests/fixtures/`) to avoid network calls in the test suite. The commands should accept an optional `--source-file` argument for this purpose.

---

## Sources

### Primary (HIGH confidence)

- Existing codebase files (`app/Models/Unit.php`, `app/Models/Allergen.php`, `database/migrations/`, `database/seeders/`) — Phase 1 foundation shapes
- Laravel 13 docs (scout, upsert, HTTP client, artisan commands, withProgressBar)
- Inertia v3 docs (partial reloads, router.reload, preserveState, only, useHttp)
- ANSES CIQUAL official page (https://ciqual.anses.fr) — 3,484 foods × 74 nutrients, XML + Excel formats, CC-BY 4.0
- USDA FDC download page (https://fdc.nal.usda.gov/download-datasets/) — SR Legacy 6.7 MB zipped CSV, food_portion confirmed
- Open Food Facts data page (https://world.openfoodfacts.org/data) — ODbL, full CSV 0.9 GB compressed, tab-separated, `allergens_en` / `traces_en` / `product_name_el` fields

### Secondary (MEDIUM confidence)

- CIQUAL MCP GitHub (https://github.com/zzgael/ciqual-mcp) — confirmed field names `alim_code`, `alim_nom_fr`, `alim_nom_eng`
- USDA Foundation Foods documentation (Apr 2024 PDF) — confirmed `food_portion` CSV structure concept
- Laravel News `whereFullText` article — confirmed MySQL/MariaDB/PostgreSQL support; SQLite unsupported

### Tertiary (LOW confidence)

- OFF Greece product count ("~8,500 Greece-tagged products") — from Project.md §6, not independently verified in 2025
- OFF country-specific download URL pattern (`gr.openfoodfacts.org`) — unconfirmed; full CSV with country filter is the safe fallback

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already installed; patterns verified in existing codebase
- Schema: HIGH — follows Project.md §6 canonical model and Phase 1 patterns directly
- Import pipeline: MEDIUM — data source formats confirmed at API/doc level; exact CIQUAL 2025 XML filename and OFF Greece subset URL need verification at implementation time
- Search: HIGH — Inertia partial reload + LIKE/FULLTEXT pattern confirmed from docs and codebase
- Pitfalls: HIGH — SQLite/whereFullText pitfall confirmed by official Laravel docs; others derived from the locked decisions

**Research date:** 2026-05-16
**Valid until:** 2026-08-16 (stable stack; re-check USDA/CIQUAL download URLs before import commands are written)
