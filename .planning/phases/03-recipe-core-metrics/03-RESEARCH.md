# Phase 3: Recipe Core & Metrics - Research

**Researched:** 2026-05-16
**Domain:** Recipe data model, versioning/working-draft, metrics engine, allergen roll-up, Laravel/Inertia v3/React 19
**Confidence:** HIGH (all findings grounded in the live codebase and Phase 2 conventions; no speculative stack choices)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Recipe builder & content entry**
- Single-page builder — ingredient lines, preparation steps, recipe metadata, and the live metrics panel all live on one editing screen with collapsible sections.
- Ingredient lines via inline search-as-you-type — reusing the existing live debounced search; also surfaces recipes for sub-recipe selection.
- Quick-create for missing ingredients — shortcut creates a private ingredient without leaving the builder.
- Preparation steps are ordered text blocks grouped into named sections (e.g. "Dough", "Filling", "Assembly").
- Ingredient lines are grouped into the same named sections as steps.
- Per-line prep & loss — each ingredient line carries a free-text prep note and an optional numeric yield/loss %.
- Images — a single optional hero image per recipe, plus an optional image per step.
- Cuisine is chosen from a seeded, expandable list. Tags are free-form with autocomplete.
- Difficulty is a named-tier enum — Easy / Medium / Hard / Expert.

**Versioning & working draft**
- Edits auto-persist to the working draft (debounced). No explicit "apply" or "save draft" step.
- Recall undoes one logical action at a time (discrete edit, not a keystroke, not a full discard).
- Version comparison is a side-by-side diff of two chosen versions.
- Versions are auto-numbered (v1, v2, v3…); each Save can carry an optional short "what changed" note.
- Creating a recipe commits v1 immediately.
- Duplicate creates a fresh, independent recipe seeded from the source's current version (no lineage link, own v1).

**Metrics engine — presentation & behavior**
- Sticky side panel — always visible on desktop, collapses on mobile.
- Nutrition: toggle between per-portion and per-100 g.
- Food cost % — selling-price-per-portion field sits inline in the metrics panel.
- Missing data → partial metric + gap flag (never silent, never hidden).
- Baker's percentages auto-offered when a flour-category ingredient is present; multiple flour lines can sum to the 100% base.
- All metric arithmetic uses `brick/math` + DECIMAL columns.
- Every ingredient line quantity is normalized to grams via the Phase 2 unit-conversion infrastructure.

**Sub-recipes & scaling**
- Sub-recipes added through the same inline search row (unified "add component" gesture).
- Sub-recipe line quantity is a weight drawn from the component's yield (gram weight; scales proportionally).
- Version pin holds; "update available" cue shown when a newer version exists. Never auto-follows.
- Scaling and portion-count changes are view-only what-ifs by default. An explicit action lets the chef commit a scaled version as a draft edit.

**Recipe list & search**
- Visual cards with hero image — grid layout.
- Each card shows: hero image, name, cuisine, total time, difficulty, cost per portion, calories per portion, allergen icons.
- Live name search + collapsible filter panel with six mandated filters (tag, cuisine, allergen, ingredient, difficulty, time).
- Default sort: recently updated first.

### Claude's Discretion
- Circular-reference rejection error message wording and surfacing location.
- Allergen roll-up display (contains vs may-contain presentation in metrics panel).
- Calorie / nutrient-density metric presentation.
- Seeded cuisine starter list (exact names and count).
- Cooking-loss / shrinkage metric details beyond per-line yield/loss % (e.g. recipe-level finished-weight override).
- Debounce timings, search ranking, list pagination/virtualization.
- Quick-create-ingredient UX (modal vs inline expansion).
- Builder section management UX (adding/renaming/reordering sections), drag interactions, per-step image upload mechanics.
- Data model / schema design for recipes, versions, working drafts, lines, steps, sections, and sub-recipe references.

### Deferred Ideas (OUT OF SCOPE)
- Recipe tests / trial runs and structured experiments (TEST-01…04) — Phase 4.
- Per-recipe AI agent (AI-01…07) — Phase 5.
- Publishing & public library (PUB-01…04) — Phase 6.
- Frozen-dessert balancing (gelato/sorbet PAC/POD/overrun metrics) — post-MVP.
- EU-format nutrition label export (NUTR-01) and printable production sheet (PROD-01) — v2.
- Cost data beyond manual entry (COST-01) — v2.
- Collaborative comments / annotations (COLLAB-01) — v2.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| RECIPE-01 | User can create a recipe with structured ingredient lines (quantity, unit, ingredient) | Schema: `recipe_ingredient_lines` table with quantity, unit_id, ingredient_id; reuses `Unit` model |
| RECIPE-02 | User can add ordered preparation steps to a recipe | Schema: `recipe_steps` table with `order` column and `section_id` FK; same section grouping as lines |
| RECIPE-03 | An ingredient line can use any unit — weight, volume, or count | Handled by `Unit` model (type: weight/volume/count); gram normalization via `PerGramCostCalculator` pattern |
| RECIPE-04 | User can set recipe yield, portion size, time, difficulty, cuisine/category, and tags | Schema on `recipes` table; `Difficulty` enum (Easy/Medium/Hard/Expert); cuisine seeded list; tags polymorphic or dedicated table |
| RECIPE-05 | A recipe can include another recipe as a nested sub-recipe component | `recipe_ingredient_lines.sub_recipe_version_id` nullable FK; `RecipeVersion` pivot |
| RECIPE-06 | Circular sub-recipe references are detected and rejected | Service-layer graph walk before attaching; FormRequest validation; clear error returned via Inertia |
| RECIPE-07 | User can scale a recipe up or down and see quantities and metrics recompute | Frontend-only scaling multiplier applied to quantities; metrics recalculated client-side or via dedicated endpoint |
| RECIPE-08 | User can adjust portion count while viewing a recipe without creating a new version | View-only state in the metrics panel; no version bump, no draft modification |
| RECIPE-09 | User can duplicate a recipe to create a new editable copy | Controller action clones latest version data into a new recipe + v1; no lineage FK |
| RECIPE-10 | User can attach a hero image and optional step images to a recipe | Laravel file storage (local/S3); `spatie/laravel-medialibrary` or direct storage; hero image on `recipes`, step image on `recipe_steps` |
| RECIPE-11 | User can write free-text chef notes on a recipe | `notes` text column on `recipes` (part of working draft); included in version snapshot |
| RECIPE-12 | User can search and filter recipes by tag, cuisine, allergen, ingredient, difficulty, and time | Full-text search on `name` + filter scopes; Inertia partial reload pattern (mirrors ingredient search) |
| RECIPE-13 | An ingredient line can carry a prep action and a yield/loss percentage | `prep_note` text and `yield_pct` decimal columns on `recipe_ingredient_lines` |
| VERSION-01 | Every recipe keeps an immutable history of saved versions | `recipe_versions` table; versions are append-only; never mutated after creation |
| VERSION-02 | Edits accumulate in a mutable working draft, separate from saved versions | `recipe_drafts` table (one per recipe per user) OR JSON column; auto-saved debounced |
| VERSION-03 | User can Save the working draft, committing it as a new version | Controller action snapshots draft into a new `recipe_versions` row |
| VERSION-04 | User can Recall to undo the last applied edit on the working draft | `recipe_draft_edits` log table (one row per logical action); Recall pops the last row and reapplies snapshot |
| VERSION-05 | User can view and compare past versions of a recipe | Side-by-side diff view; version data stored as JSON snapshot in `recipe_versions` |
| VERSION-06 | A sub-recipe reference pins to a specific version of the component recipe | `recipe_ingredient_lines.sub_recipe_version_id` FK to `recipe_versions` |
| METRIC-01 | App computes nutrition per portion and per 100 g | `NutritionCalculator` service: sum (ingredient nutrition per 100 g × grams_used / 100) across all lines; divide by portions for per-portion; divide total by total_yield_g × 100 for per-100 g |
| METRIC-02 | App computes cost per portion and total recipe cost | `CostCalculator` service: sum (per_gram_cost × grams_used) across lines; divide by portions |
| METRIC-03 | App computes food cost % from cost and a user-entered selling price per portion | `(cost_per_portion / selling_price) × 100`; selling price held in UI state or in a `recipe_selling_price` field |
| METRIC-04 | App computes recipe yield and supports scaling calculations | Yield stored on recipe; scaling = multiply all quantities by a factor |
| METRIC-05 | App computes cooking loss / shrinkage from per-ingredient yield percentages | `ShrinkageCalculator`: sum raw weight vs sum (raw × yield_pct / 100); delta is loss |
| METRIC-06 | App computes baker's percentages and hydration ratio | `BakersPercentageCalculator`: each ingredient weight / total flour weight × 100; hydration = total water / total flour × 100 |
| METRIC-07 | App computes calorie / nutrient density | kcal per 100 g (already in METRIC-01) and kcal per portion |
| METRIC-08 | All metrics roll up correctly through nested sub-recipes | Recursive `MetricsRollupService`: for each sub-recipe line, fetch its pinned version's snapshot metrics and scale by (gram_weight / sub_recipe_yield_g) |
| METRIC-09 | All metric arithmetic uses exact decimal math with no floating-point drift | `Brick\Math\BigDecimal` throughout all calculators; DECIMAL columns in DB |
| METRIC-10 | App normalizes every ingredient line quantity to grams | `GramNormalizer` (extends `PerGramCostCalculator` logic): weight units via `base_factor`, volume/count via `ingredient_conversions` |
| ALLG-01 | App derives a recipe's allergens from its ingredients using EU 14-allergen model | `AllergenRollupService`: union of all ingredient allergen rows across lines |
| ALLG-02 | App distinguishes "contains" from "may contain" allergens | Pivot `state` column already in `ingredient_allergen` — same pattern for recipe derivation |
| ALLG-03 | Allergen information rolls up through nested sub-recipes, preserving both states | Sub-recipe allergens fetched from pinned version snapshot and merged; "contains" beats "may_contain" |
</phase_requirements>

---

## Summary

Phase 3 is the largest and most structurally complex phase in the project. It introduces six interconnected concerns: the recipe data model, the working-draft/versioning layer, the metrics engine, allergen roll-up, nested sub-recipes, and the recipe list. Every concern depends on accurate decimal arithmetic and gram normalization, both of which are already established patterns in Phase 2.

The good news: the codebase is fully prepared. `brick/math` is installed (transitive dependency), `PerGramCostCalculator` demonstrates the exact computation pattern, `Ingredient` carries all nutrition columns, `Unit` carries `base_factor`, and `IngredientConversion` handles volume/count-to-gram conversions. The Phase 2 ingredient search, Inertia partial-reload debounce, shadcn/ui primitives, and policy pattern all transfer directly.

The key architectural decision is the working-draft model. Research into the CONTEXT.md decisions confirms the right model: a `recipe_drafts` table (one draft per recipe) holding the full mutable state, plus a `recipe_draft_edits` log for Recall. This is simpler than event-sourcing and more testable than a JSON event log. Versions are immutable snapshots stored as JSON blobs alongside relational rows for searchability.

The metrics engine must be a PHP service layer (`app/Support/Recipes/`) with discrete calculator classes per metric group, composing `BigDecimal` arithmetic throughout. The React metrics panel reads computed metrics as Inertia props and recomputes lightweight view-only scaling locally.

**Primary recommendation:** Build the schema and service layer first (wave 1), then the builder UI (wave 2), then the metrics panel (wave 3), then search/list (wave 4). The draft/version system underpins everything and must be solid before the UI.

---

## Standard Stack

### Core — already installed, no new deps required for core recipe logic

| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| `brick/math` | 0.12.x (transitive via composer) | Exact BigDecimal arithmetic for all metrics | Already in vendor |
| `laravel/framework` | v13 | Eloquent, migrations, policies, file storage | Installed |
| `inertiajs/inertia-laravel` | v3 | Server-side rendering bridge | Installed |
| `@inertiajs/react` | v3 | Client router, form helpers, useHttp | Installed |
| `spatie/laravel-permission` | 7.4 | Policy authorization (RecipePolicy mirrors IngredientPolicy) | Installed |
| `react` | v19 | UI with hooks and concurrent features | Installed |
| `tailwindcss` | v4 | Styling | Installed |
| shadcn/ui primitives | — | Sheet, Card, Badge, Skeleton, Dialog, Popover, Tabs, Collapsible, ScrollArea, Sonner | Already in `resources/js/components/ui/` |
| `lucide-react` | 0.475.x | Icons | Installed |

### New Dependencies Needed

| Library | Purpose | Decision |
|---------|---------|---------|
| Image storage | Hero image + step images (RECIPE-10) | Use **Laravel's built-in file storage** (`Storage::disk('public')`). No new package needed for Phase 3. Store path string in DB. Image optimization is v2 scope. |
| Drag-and-drop (section/step reorder) | Builder UX | Use `@hello-pangea/dnd` (React DnD) or native HTML5 drag. `@hello-pangea/dnd` is the maintained fork of `react-beautiful-dnd` and works with React 19. ADD only if drag reorder is in scope for Phase 3 (Claude's Discretion). |

**Installation (if drag-and-drop is added):**
```bash
npm install @hello-pangea/dnd
```

**No new PHP packages are needed.** All metric arithmetic, conversions, and allergen logic can be implemented with the existing stack.

### Version Verification
- `brick/math` is confirmed in `/vendor/` (transitive dep of another package; must be explicitly added to `composer.json` require if used directly to prevent it being removed)
- Confirmed via `vendor/composer/installed.json` grep for "brick/math"

**Action required:** Add `brick/math` to `composer.json` `require` block so it is an explicit direct dependency:
```bash
composer require brick/math
```

---

## Architecture Patterns

### Recommended Database Schema

```
recipes
├── id, user_id (owner), name, slug
├── hero_image_path (nullable)
├── yield_amount (decimal:4), yield_unit_id (FK units), portions (decimal:4)
├── portion_size_g (decimal:4, nullable)
├── prep_time_minutes (int, nullable), cook_time_minutes (int, nullable)
├── difficulty (enum: easy/medium/hard/expert)
├── cuisine_id (FK cuisines, nullable)
├── notes (text, nullable)
├── current_version_id (FK recipe_versions, nullable, after v1 created)
├── timestamps, softDeletes

cuisines
├── id, name (string), slug (string, unique)

recipe_tags (pivot: recipes ↔ tags)
tags
├── id, name (string, unique)

recipe_sections
├── id, recipe_id (FK), name (string), order (int)
└── (section is the grouping container for both lines and steps)

recipe_ingredient_lines
├── id, recipe_id (FK), section_id (FK recipe_sections)
├── ingredient_id (FK ingredients, nullable — null when sub_recipe_version_id set)
├── sub_recipe_version_id (FK recipe_versions, nullable)
├── quantity (decimal:6), unit_id (FK units, nullable — null for sub-recipe lines)
├── quantity_g (decimal:6, nullable — pre-normalized gram weight, cached)
├── prep_note (text, nullable), yield_pct (decimal:4, nullable, 0–100)
├── is_flour_base (boolean, default false — for baker's %)
├── order (int)

recipe_steps
├── id, recipe_id (FK), section_id (FK recipe_sections)
├── instruction (text), order (int)
├── step_image_path (nullable)

recipe_versions
├── id, recipe_id (FK), version_number (int)
├── committed_by (FK users), committed_at (timestamp)
├── change_note (string, nullable)
├── snapshot (JSON — full recipe state: lines, steps, sections, metadata, cached metrics)
├── cached_nutrition_json (JSON, nullable — for fast card display)
├── cached_cost_per_portion (decimal:8, nullable)
├── cached_allergen_slugs (JSON array, nullable)

recipe_drafts
├── id, recipe_id (FK, unique), user_id (FK)
├── data (JSON — mirrors version snapshot structure, mutable)
├── edit_sequence (int — monotonic counter per logical edit)
├── updated_at

recipe_draft_edits  (Recall log — one row per logical edit)
├── id, recipe_draft_id (FK), sequence (int)
├── action (string — 'add_line', 'remove_line', 'edit_quantity', 'add_step', etc.)
├── before_snapshot (JSON — state before this edit, for Recall)
├── created_at
```

### Recommended Project Structure

```
app/
├── Enums/
│   └── Difficulty.php           # Easy / Medium / Hard / Expert
├── Models/
│   ├── Recipe.php
│   ├── RecipeSection.php
│   ├── RecipeIngredientLine.php
│   ├── RecipeStep.php
│   ├── RecipeVersion.php
│   ├── RecipeDraft.php
│   ├── RecipeDraftEdit.php
│   ├── Cuisine.php
│   └── Tag.php
├── Http/Controllers/Recipes/
│   ├── RecipeController.php          # index, show (builder), store, destroy
│   ├── RecipeVersionController.php   # store (Save), index, show (compare)
│   ├── RecipeDraftController.php     # update (auto-save), recall
│   ├── RecipeSearchController.php    # search endpoint for inline picker
│   └── RecipeDuplicateController.php # store
├── Http/Requests/Recipes/
│   ├── StoreRecipeRequest.php
│   ├── UpdateRecipeDraftRequest.php
│   └── StoreRecipeVersionRequest.php
├── Policies/
│   └── RecipePolicy.php
├── Support/Recipes/
│   ├── GramNormalizer.php            # quantity + unit → grams (extends PerGramCostCalculator logic)
│   ├── NutritionCalculator.php       # METRIC-01, METRIC-07
│   ├── CostCalculator.php            # METRIC-02, METRIC-03
│   ├── ShrinkageCalculator.php       # METRIC-05
│   ├── BakersPercentageCalculator.php # METRIC-06
│   ├── AllergenRollupService.php     # ALLG-01, ALLG-02, ALLG-03
│   ├── MetricsAggregator.php         # METRIC-08: orchestrates roll-up through sub-recipes
│   ├── CircularReferenceDetector.php # RECIPE-06
│   └── RecipeDraftManager.php        # VERSION-02, VERSION-04: apply edit, recall
resources/js/
├── pages/recipes/
│   ├── index.tsx                  # Recipe list grid
│   ├── create.tsx                 # New recipe (immediately creates v1)
│   └── show.tsx                   # Single-page builder (draft editing + metrics panel)
├── components/recipes/
│   ├── recipe-card.tsx            # Grid card with metrics summary
│   ├── recipe-filters.tsx         # Collapsible filter panel
│   ├── recipe-builder/
│   │   ├── section-block.tsx      # Named section (lines + steps grouped)
│   │   ├── ingredient-line-row.tsx
│   │   ├── step-row.tsx
│   │   ├── ingredient-search-combobox.tsx  # Unified line picker (ingredients + recipes)
│   │   └── quick-create-ingredient-modal.tsx
│   ├── metrics-panel/
│   │   ├── metrics-panel.tsx      # Sticky side panel container
│   │   ├── nutrition-section.tsx  # Toggle per-portion / per-100 g
│   │   ├── cost-section.tsx       # Cost + selling price + food cost %
│   │   ├── allergen-section.tsx   # Contains / may-contain display
│   │   ├── bakers-section.tsx     # Baker's % (conditional)
│   │   └── data-gap-banner.tsx    # Missing data indicator
│   └── version-compare.tsx        # Side-by-side diff
tests/Feature/Recipes/
│   ├── RecipeSchemaTest.php
│   ├── RecipeCrudTest.php
│   ├── RecipeDraftTest.php
│   ├── RecipeVersionTest.php
│   ├── RecipeSearchTest.php
│   ├── SubRecipeTest.php
│   ├── CircularReferenceTest.php
│   └── Metrics/
│       ├── NutritionCalculatorTest.php
│       ├── CostCalculatorTest.php
│       ├── ShrinkageCalculatorTest.php
│       ├── BakersPercentageCalculatorTest.php
│       ├── AllergenRollupTest.php
│       └── MetricsRollupTest.php   # Sub-recipe roll-up precision
```

### Pattern 1: Metric Calculator Services

Each metric calculator is a stateless service class with a single public method, receiving typed value objects and returning `BigDecimal`. No Eloquent in calculators — data is prepared by the controller/aggregator and passed in.

```php
// app/Support/Recipes/NutritionCalculator.php
namespace App\Support\Recipes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class NutritionCalculator
{
    /**
     * @param  array<array{grams: BigDecimal, energy_kcal: ?string, protein_g: ?string, ...}>  $lines
     */
    public function compute(array $lines, BigDecimal $portions): NutritionResult
    {
        $totalKcal = BigDecimal::zero();
        $missingLines = [];

        foreach ($lines as $line) {
            if ($line['energy_kcal'] === null) {
                $missingLines[] = $line['name'];
                continue;
            }
            // Per 100 g value × grams_used / 100
            $kcalForLine = BigDecimal::of($line['energy_kcal'])
                ->multipliedBy($line['grams'])
                ->dividedBy('100', 4, RoundingMode::HALF_UP);
            $totalKcal = $totalKcal->plus($kcalForLine);
        }

        $perPortion = $totalKcal->dividedBy($portions, 4, RoundingMode::HALF_UP);

        return new NutritionResult($totalKcal, $perPortion, $missingLines);
    }
}
```

### Pattern 2: Working Draft Auto-Save (Inertia v3 Pattern)

```typescript
// In show.tsx builder — debounced auto-save using Inertia router
import { router } from '@inertiajs/react';
import { useCallback, useRef } from 'react';
import { update as updateDraft } from '@/actions/recipes/recipe-draft';

function useAutoSave(recipeId: number) {
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    return useCallback((draftData: RecipeDraftData) => {
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            router.put(updateDraft({ recipe: recipeId }), draftData, {
                preserveState: true,
                preserveScroll: true,
                only: ['draft', 'metrics'],  // partial reload
            });
        }, 600);
    }, [recipeId]);
}
```

### Pattern 3: Circular Reference Detection

```php
// app/Support/Recipes/CircularReferenceDetector.php
namespace App\Support\Recipes;

use App\Models\RecipeVersion;

class CircularReferenceDetector
{
    /**
     * Returns true if adding $candidateRecipeId as a sub-recipe of $parentRecipeId
     * would create a cycle.
     */
    public function wouldCreateCycle(int $parentRecipeId, int $candidateRecipeId): bool
    {
        if ($parentRecipeId === $candidateRecipeId) {
            return true;
        }
        // BFS/DFS: collect all recipe IDs reachable from $candidateRecipeId via sub-recipe lines
        $visited = [];
        $queue = [$candidateRecipeId];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if ($current === $parentRecipeId) {
                return true;
            }

            // Find sub-recipe IDs referenced by the current recipe's latest version
            $subIds = RecipeVersion::query()
                ->where('recipe_id', $current)
                ->latest('version_number')
                ->first()
                ?->ingredient_lines_with_sub_recipe_ids ?? [];

            foreach ($subIds as $id) {
                $queue[] = $id;
            }
        }

        return false;
    }
}
```

### Pattern 4: Sub-Recipe Metrics Roll-Up

```php
// app/Support/Recipes/MetricsAggregator.php
// Recursive roll-up: for sub-recipe lines, scale the pinned version's cached metrics

public function aggregateForDraft(RecipeDraft $draft): AggregatedMetrics
{
    $lines = $this->prepareLines($draft);  // resolves gram weight for each line
    
    foreach ($lines as &$line) {
        if ($line['type'] === 'sub_recipe') {
            $version = RecipeVersion::find($line['sub_recipe_version_id']);
            $scaleFactor = $line['grams']->dividedBy(
                BigDecimal::of($version->yield_g), 10, RoundingMode::HALF_UP
            );
            // Scale the sub-recipe's cached metrics by scaleFactor
            $line['nutrition'] = $this->scaleNutrition($version->cached_nutrition, $scaleFactor);
            $line['cost_g']    = BigDecimal::of($version->cached_cost_per_gram)->multipliedBy($scaleFactor);
            $line['allergens'] = $version->cached_allergen_slugs;
        }
    }

    return new AggregatedMetrics($lines, BigDecimal::of($draft->data['portions']));
}
```

### Pattern 5: Recipe Version Snapshot

Store the complete recipe state as a JSON snapshot in `recipe_versions.snapshot`. This means version comparison does not need to reconstruct data from normalized relational rows — it just diffs two JSON blobs. The relational rows in `recipe_ingredient_lines`, `recipe_steps`, `recipe_sections` represent the **live draft state**, not the version history.

```json
{
  "name": "Pain au Levain",
  "portions": 8,
  "yield_g": 1200,
  "sections": [
    {
      "name": "Levain",
      "lines": [
        {"type": "ingredient", "ingredient_id": 42, "name_cache": "Bread Flour", "quantity": "200", "unit": "g", "prep_note": "sifted", "yield_pct": "100"}
      ],
      "steps": [
        {"instruction": "Mix levain and let ferment 8 hours", "order": 1}
      ]
    }
  ],
  "notes": "Use 75% hydration starter"
}
```

### Anti-Patterns to Avoid

- **Float arithmetic for metrics:** Never use PHP `float` or JS `number` for metric sums. Use `BigDecimal` in PHP and display-only rounding in React (never feed a rounded float back into a calculation).
- **Mutating version rows:** `recipe_versions` is append-only. Never UPDATE a version row after creation.
- **Auto-following sub-recipe versions:** A parent recipe's sub-recipe reference must never silently update to a newer version. Always require explicit chef action.
- **Storing metrics only in JSON columns:** Cache computed metrics in JSON for speed, but always recompute from source data when the draft changes. Never treat the cache as authoritative.
- **Single `recipe_ingredient_lines` table for both ingredients and sub-recipes without a type discriminator:** Use a nullable `sub_recipe_version_id` with a check constraint — if it's non-null, `ingredient_id` and `unit_id` are null (a sub-recipe line provides its own unit via yield_g).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Exact decimal arithmetic | Custom float-rounding helpers | `Brick\Math\BigDecimal` with `RoundingMode::HALF_UP` | Float drift accumulates across 20+ ingredients; BigDecimal is already in vendor |
| Authorization (recipe owner checks) | Manual `$recipe->user_id === auth()->id()` in controllers | `RecipePolicy` + `Gate::authorize()` (exact pattern from `IngredientPolicy`) | Consistent, testable, single location |
| Image file management | Custom upload logic | `Storage::disk('public')->put(...)` with Laravel's built-in file storage | Phase 3 scope is store + retrieve; optimization is v2 |
| Tag autocomplete | Custom fuzzy search | Inertia partial reload with `Tag::where('name', 'like', "%{$q}%")` — same pattern as ingredient search | Re-use established debounce pattern |
| Allergen derivation | Custom set-union logic | `AllergenRollupService` composing `collect()` union operations | The 14-allergen set is small; simple union with state priority (contains beats may_contain) is sufficient and testable |
| Version diff | Rich diff library | Simple field-by-field comparison of two JSON snapshots, highlighted in React | The data is structured; a generic diff library adds complexity without benefit |

**Key insight:** Phase 3 adds zero new infrastructure packages. Every hard problem (decimal math, auth, file storage, search debounce) is already solved by the existing stack or is trivially composable from it.

---

## Common Pitfalls

### Pitfall 1: Float Leakage from JavaScript Back to PHP
**What goes wrong:** React scaling multiplier (a JS `number`) is sent as a query param and used in a PHP float calculation, introducing drift into a "what-if" display but also silently poisoning a commit if the user saves.
**Why it happens:** The natural instinct is to round in JS for display and pass the rounded float back.
**How to avoid:** Scaling in React is display-only. When committing a scaled version, send the original quantities + the integer scale factor (e.g. `scale_numerator: 3, scale_denominator: 1`) and compute in PHP with BigDecimal.
**Warning signs:** Any `parseFloat()` in a value that flows back to the server.

### Pitfall 2: Circular Reference Detection Miss
**What goes wrong:** Chef adds Recipe A as sub-recipe of Recipe B. Later adds Recipe B as sub-recipe of Recipe A. If detection only checks direct parents (not transitive), the cycle slips through.
**Why it happens:** BFS/DFS not implemented, just a direct self-reference check.
**How to avoid:** `CircularReferenceDetector` must traverse the full sub-recipe graph from the candidate recipe, not just one level. Test with 3-node cycles (A→B→C→A).
**Warning signs:** Detection only checks `$candidateId !== $parentId`.

### Pitfall 3: Working Draft vs. Version Confusion
**What goes wrong:** A controller reads `recipe_ingredient_lines` (which holds the live draft) to compute metrics for a versioned view. User sees v2 metrics when they asked to view v1.
**Why it happens:** The relational lines tables represent the draft, not history. Version history lives in the JSON snapshot.
**How to avoid:** When displaying a historical version, always load from `recipe_versions.snapshot` JSON, never from the live relational tables.
**Warning signs:** A version-compare or version-show controller that queries `recipe_ingredient_lines` without filtering by version.

### Pitfall 4: Sub-Recipe Metric Scale-Factor Error
**What goes wrong:** A sub-recipe produces 500 g (yield). The parent uses 250 g of it. Metrics should scale by 250/500 = 0.5. If yield is not stored on the pinned version (or is null), the scale factor defaults to 1.0 and metrics double.
**Why it happens:** `yield_g` not stored on `recipe_versions`, or not cached.
**How to avoid:** Always snapshot `yield_g` (resolved from `yield_amount × unit base_factor`) into `recipe_versions`. Assert it's non-null before computing scale.
**Warning signs:** Missing `yield_g` on version snapshot; metrics panel shows sub-recipe contribution at 2× or 0.

### Pitfall 5: Recall Breaks When Draft Edit Log Is Out of Sync
**What goes wrong:** An auto-save fires mid-Recall, inserting a new edit row while the Recall is deleting the last one. Recall removes the auto-save row instead of the intended edit.
**Why it happens:** No optimistic locking / sequence check on the draft edit log.
**How to avoid:** Recall endpoint accepts `expected_sequence` (the edit sequence number the client believes is current). If mismatch, return 409 and prompt a page refresh.
**Warning signs:** Recall doesn't pass any sequence identifier.

### Pitfall 6: Brick/Math Not in composer.json require
**What goes wrong:** `brick/math` is a transitive dependency. If the package that pulls it in is removed or its version changes, `brick/math` disappears from vendor without warning.
**Why it happens:** Using a transitive dependency without declaring it directly.
**How to avoid:** Run `composer require brick/math` to add it to `require` explicitly.
**Warning signs:** `composer.json` shows `brick/math` absent from `require` and `require-dev`.

### Pitfall 7: Ingredient Search Returns Too Many Results in Builder Combobox
**What goes wrong:** The inline line picker uses the full ingredient index endpoint (returns 30 paginated rows). The builder shows recipe results mixed with ingredient results without clear visual distinction.
**Why it happens:** Reusing the full index response without adapting it to the picker's context.
**How to avoid:** Create a dedicated lightweight search endpoint (`GET /search/components?q=`) that returns a flat list of `{type: 'ingredient'|'recipe', id, name, unit_hint}` with a combined limit of 10. Debounce at 300 ms.
**Warning signs:** Picker triggers a full page reload or returns paginated results.

---

## Code Examples

### GramNormalizer (extends PerGramCostCalculator pattern)

```php
// app/Support/Recipes/GramNormalizer.php
// Source: PerGramCostCalculator.php — same logic generalized for lines

namespace App\Support\Recipes;

use App\Models\IngredientConversion;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class GramNormalizer
{
    public function normalize(BigDecimal $quantity, Unit $unit, ?int $ingredientId): ?BigDecimal
    {
        if ($unit->type === 'weight') {
            return $quantity->multipliedBy(BigDecimal::of($unit->base_factor));
        }

        if ($ingredientId === null) {
            return null;
        }

        $conversion = IngredientConversion::query()
            ->where('ingredient_id', $ingredientId)
            ->where('from_unit_id', $unit->id)
            ->first();

        if ($conversion === null) {
            return null;
        }

        $gramsPerUnit = BigDecimal::of($conversion->gram_weight)
            ->dividedBy(BigDecimal::of($conversion->from_amount), 10, RoundingMode::HALF_UP);

        return $quantity->multipliedBy($gramsPerUnit);
    }
}
```

### Allergen Roll-Up with State Priority

```php
// app/Support/Recipes/AllergenRollupService.php

namespace App\Support\Recipes;

class AllergenRollupService
{
    /**
     * Merge allergen states from multiple sources.
     * 'contains' always wins over 'may_contain'.
     *
     * @param  array<array{slug: string, state: string}>  ...$allergenSets
     * @return array<string, string>  slug => state
     */
    public function merge(array ...$allergenSets): array
    {
        $merged = [];

        foreach ($allergenSets as $set) {
            foreach ($set as $allergen) {
                $slug = $allergen['slug'];
                $state = $allergen['state'];

                if (!isset($merged[$slug]) || $state === 'contains') {
                    $merged[$slug] = $state;
                }
            }
        }

        return $merged;
    }
}
```

### Difficulty Enum (mirrors AccountStatus pattern)

```php
// app/Enums/Difficulty.php

namespace App\Enums;

enum Difficulty: string
{
    case Easy   = 'easy';
    case Medium = 'medium';
    case Hard   = 'hard';
    case Expert = 'expert';

    public function label(): string
    {
        return match($this) {
            self::Easy   => 'Easy',
            self::Medium => 'Medium',
            self::Hard   => 'Hard',
            self::Expert => 'Expert',
        };
    }
}
```

### Inertia Partial Reload for Metrics (live panel update)

```typescript
// Triggered after any draft auto-save completes
router.reload({
    only: ['metrics', 'draft'],
    preserveState: true,
    preserveScroll: true,
});
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Storing version history as diffs | Full JSON snapshots per version | Simpler read path, no reconstruction needed; storage cost is acceptable for recipe-scale data |
| Custom decimal handling in PHP | `brick/math` BigDecimal | Zero drift; already established in Phase 2 |
| Separate ingredient and step editors | Single-page builder with sections grouping both | Matches professional recipe workflow; avoids context switching |
| Allergen display as free text | EU 14-allergen slugs with contains/may-contain states | Structured, filterable, legally meaningful |

---

## Open Questions

1. **Image storage disk configuration**
   - What we know: `Storage::disk('public')` works out of the box with Herd.
   - What's unclear: Whether S3 or a separate disk is configured in `.env` for production.
   - Recommendation: Use `config('filesystems.default')` disk for Phase 3; add S3 config as a separate task if needed.

2. **Drag-and-drop for section/step reorder**
   - What we know: No DnD library is currently installed; HTML5 drag has accessibility limitations.
   - What's unclear: Whether the UX spec requires true drag-and-drop or if up/down arrow buttons suffice for Phase 3.
   - Recommendation: Use up/down reorder buttons (simpler, accessible, no new dep) in Phase 3; upgrade to `@hello-pangea/dnd` in Phase 4 or v2 if user feedback demands it. This is Claude's Discretion per CONTEXT.md.

3. **selling_price persistence scope**
   - What we know: Selling price is entered inline in the metrics panel per CONTEXT.md.
   - What's unclear: Is it persisted to the recipe/draft, or is it a session/view-only field?
   - Recommendation: Persist to `recipe_drafts.data` as part of the draft state, included in version snapshots. This allows the chef to return to the builder and see their selling price intact.

4. **Full-text search for recipe names on MySQL vs SQLite**
   - What we know: The ingredient search uses a FULLTEXT index guarded by `DB::getDriverName()`.
   - What's unclear: Whether recipe names warrant FULLTEXT or if `LIKE '%...%'` is sufficient (recipes are fewer in number than ingredients).
   - Recommendation: Use `LIKE` for recipe name search initially (recipes are user-private and much fewer than the official ingredient library); add FULLTEXT if performance proves insufficient.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest v4 + PHPUnit v12 |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=Recipe` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RECIPE-01 | Recipe can be created with ingredient lines | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-02 | Ordered steps attached to recipe | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-03 | Lines accept weight, volume, count units | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-04 | Recipe metadata fields stored correctly | Feature | `php artisan test --compact --filter=RecipeSchemaTest` | ❌ Wave 0 |
| RECIPE-05 | Sub-recipe line attaches to a pinned version | Feature | `php artisan test --compact --filter=SubRecipeTest` | ❌ Wave 0 |
| RECIPE-06 | Circular reference rejected | Feature | `php artisan test --compact --filter=CircularReferenceTest` | ❌ Wave 0 |
| RECIPE-07 | Scale factor recomputes quantities | Feature (or Unit) | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-08 | Portion change is view-only (no version bump) | Feature | `php artisan test --compact --filter=RecipeVersionTest` | ❌ Wave 0 |
| RECIPE-09 | Duplicate creates independent recipe + v1 | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-10 | Hero image stored; step image stored | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-11 | Chef notes persisted and versioned | Feature | `php artisan test --compact --filter=RecipeCrudTest` | ❌ Wave 0 |
| RECIPE-12 | Search + filter returns correct recipes | Feature | `php artisan test --compact --filter=RecipeSearchTest` | ❌ Wave 0 |
| RECIPE-13 | prep_note and yield_pct stored on line | Feature | `php artisan test --compact --filter=RecipeSchemaTest` | ❌ Wave 0 |
| VERSION-01 | Version rows are immutable (no UPDATE) | Feature | `php artisan test --compact --filter=RecipeVersionTest` | ❌ Wave 0 |
| VERSION-02 | Draft auto-saved separately from versions | Feature | `php artisan test --compact --filter=RecipeDraftTest` | ❌ Wave 0 |
| VERSION-03 | Save commits draft as new version number | Feature | `php artisan test --compact --filter=RecipeDraftTest` | ❌ Wave 0 |
| VERSION-04 | Recall removes last edit and restores prior state | Feature | `php artisan test --compact --filter=RecipeDraftTest` | ❌ Wave 0 |
| VERSION-05 | Two versions can be compared (diff data returned) | Feature | `php artisan test --compact --filter=RecipeVersionTest` | ❌ Wave 0 |
| VERSION-06 | Sub-recipe stays pinned to original version | Feature | `php artisan test --compact --filter=SubRecipeTest` | ❌ Wave 0 |
| METRIC-01 | Nutrition per portion and per 100 g computed correctly | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ❌ Wave 0 |
| METRIC-02 | Cost per portion computed correctly | Unit | `php artisan test --compact --filter=CostCalculatorTest` | ❌ Wave 0 |
| METRIC-03 | Food cost % = cost/selling × 100 | Unit | `php artisan test --compact --filter=CostCalculatorTest` | ❌ Wave 0 |
| METRIC-04 | Yield and scaling factor applied | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ❌ Wave 0 |
| METRIC-05 | Cooking loss derived from yield_pct | Unit | `php artisan test --compact --filter=ShrinkageCalculatorTest` | ❌ Wave 0 |
| METRIC-06 | Baker's % and hydration computed | Unit | `php artisan test --compact --filter=BakersPercentageCalculatorTest` | ❌ Wave 0 |
| METRIC-07 | Calorie density (kcal per 100 g and per portion) | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ❌ Wave 0 |
| METRIC-08 | Sub-recipe metrics rolled up with correct scale | Unit | `php artisan test --compact --filter=MetricsRollupTest` | ❌ Wave 0 |
| METRIC-09 | No floating-point drift (verified with known values) | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ❌ Wave 0 |
| METRIC-10 | All units normalize to grams correctly | Unit | `php artisan test --compact --filter=NutritionCalculatorTest` | ❌ Wave 0 |
| ALLG-01 | Recipe allergens derived from ingredient allergens | Feature | `php artisan test --compact --filter=AllergenRollupTest` | ❌ Wave 0 |
| ALLG-02 | contains vs may_contain distinguished | Feature | `php artisan test --compact --filter=AllergenRollupTest` | ❌ Wave 0 |
| ALLG-03 | Allergens roll up through nested sub-recipes | Feature | `php artisan test --compact --filter=AllergenRollupTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<relevant test class>`
- **Per wave merge:** `php artisan test --compact tests/Feature/Recipes/ tests/Unit/`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

All Phase 3 test files are new — none exist yet. Wave 0 must create:

- [ ] `tests/Feature/Recipes/RecipeSchemaTest.php` — covers RECIPE-04, RECIPE-13, VERSION-01
- [ ] `tests/Feature/Recipes/RecipeCrudTest.php` — covers RECIPE-01..03, RECIPE-07..11
- [ ] `tests/Feature/Recipes/RecipeDraftTest.php` — covers VERSION-02..04
- [ ] `tests/Feature/Recipes/RecipeVersionTest.php` — covers VERSION-01, VERSION-05, RECIPE-08
- [ ] `tests/Feature/Recipes/RecipeSearchTest.php` — covers RECIPE-12
- [ ] `tests/Feature/Recipes/SubRecipeTest.php` — covers RECIPE-05, VERSION-06
- [ ] `tests/Feature/Recipes/CircularReferenceTest.php` — covers RECIPE-06
- [ ] `tests/Feature/Recipes/Metrics/NutritionCalculatorTest.php` — covers METRIC-01, 04, 07, 09, 10
- [ ] `tests/Feature/Recipes/Metrics/CostCalculatorTest.php` — covers METRIC-02, 03
- [ ] `tests/Feature/Recipes/Metrics/ShrinkageCalculatorTest.php` — covers METRIC-05
- [ ] `tests/Feature/Recipes/Metrics/BakersPercentageCalculatorTest.php` — covers METRIC-06
- [ ] `tests/Feature/Recipes/Metrics/AllergenRollupTest.php` — covers ALLG-01..03
- [ ] `tests/Feature/Recipes/Metrics/MetricsRollupTest.php` — covers METRIC-08
- [ ] `database/factories/RecipeFactory.php`, `RecipeVersionFactory.php`, `RecipeDraftFactory.php` — shared fixtures for all recipe tests

---

## Sources

### Primary (HIGH confidence)
- Live codebase — `app/Support/Ingredients/PerGramCostCalculator.php`, `app/Models/Ingredient.php`, `app/Enums/AccountStatus.php`, `app/Policies/IngredientPolicy.php` — patterns confirmed directly
- Live codebase — `database/migrations/2026_05_16_140144_create_ingredients_table.php` — DECIMAL column precision pattern
- Live codebase — `resources/js/pages/ingredients/index.tsx` — debounced Inertia partial reload pattern
- `.planning/phases/03-recipe-core-metrics/03-CONTEXT.md` — all implementation decisions
- `Project.md §3.2, §3.3, §5` — metrics catalogue, versioning semantics
- `vendor/composer/installed.json` — confirmed `brick/math` is in vendor

### Secondary (MEDIUM confidence)
- `.planning/codebase/CONVENTIONS.md`, `ARCHITECTURE.md`, `STRUCTURE.md`, `TESTING.md` — confirmed codebase conventions
- EU Regulation 1169/2011 Annex II — 14 mandatory allergens (contains/may-contain distinction is regulatory)

### Tertiary (LOW confidence)
- `@hello-pangea/dnd` for drag-and-drop — mentioned as option; not verified against React 19 compatibility in this project. Flag for validation if chosen.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries confirmed in vendor/package.json; no speculative additions
- Database schema: HIGH — directly modeled on Phase 2 migration patterns; decisions from CONTEXT.md
- Architecture (service classes): HIGH — mirrors `PerGramCostCalculator` and `IngredientPolicy` patterns exactly
- Allergen roll-up: HIGH — EU 14-allergen model with contains/may-contain already implemented in Phase 2 for ingredients; extension to recipes is mechanical
- Frontend patterns: HIGH — mirrors existing Inertia partial-reload and debounce patterns from ingredient index
- Drag-and-drop: LOW — library not yet installed; not confirmed for React 19 in this project

**Research date:** 2026-05-16
**Valid until:** 2026-06-16 (stable stack; no fast-moving dependencies)
