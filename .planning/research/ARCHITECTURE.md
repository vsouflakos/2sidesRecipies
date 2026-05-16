# Architecture Research

**Domain:** Professional recipe management platform with versioning, nested sub-recipes, metrics engine, and AI agent
**Researched:** 2026-05-16
**Confidence:** HIGH

---

## Standard Architecture

### System Overview

```
┌────────────────────────────────────────────────────────────────┐
│                    Presentation Layer (React 19 + Inertia v3)  │
│  ┌───────────────┐  ┌────────────────┐  ┌──────────────────┐   │
│  │ Recipe Editor │  │ Metrics Panel  │  │  AI Chat Panel   │   │
│  │ (draft-aware) │  │ (live rollup)  │  │ (streaming SSE)  │   │
│  └───────┬───────┘  └───────┬────────┘  └────────┬─────────┘   │
└──────────┼──────────────────┼───────────────────-┼─────────────┘
           │  Inertia props   │  Inertia props      │ SSE / XHR
┌──────────┼──────────────────┼─────────────────────┼─────────────┐
│                    HTTP Layer (Laravel 13 Controllers)           │
│  ┌────────────────┐  ┌──────────────┐  ┌───────────────────┐    │
│  │ RecipeController│  │MetricsCtrl  │  │  AgentController  │    │
│  │ DraftController │  │(read-only)  │  │  (stream proxy)   │    │
│  └────────┬───────┘  └──────┬───────┘  └──────────┬────────┘    │
└───────────┼─────────────────┼──────────────────────┼────────────┘
            │                 │                       │
┌───────────┼─────────────────┼───────────────────────┼───────────┐
│                    Domain Services Layer                         │
│  ┌────────────────┐  ┌───────────────┐  ┌────────────────────┐  │
│  │ DraftManager   │  │ MetricsEngine │  │  RecipeAgent       │  │
│  │ VersionManager │  │ (rolls up     │  │  (Laravel AI SDK;  │  │
│  │                │  │  sub-recipes) │  │   provider-agnostic│  │
│  └────────┬───────┘  └──────┬────────┘  └─────────┬──────────┘  │
│           │                 │                      │             │
│  ┌────────────────┐  ┌──────────────────────────────────────┐    │
│  │ UnitConverter  │  │           IngredientLibrary           │    │
│  │ (→ grams)      │  │  (official | private | submissions)  │    │
│  └────────┬───────┘  └──────────────────────────────────────┘    │
└───────────┼─────────────────────────────────────────────────────┘
            │
┌───────────┼─────────────────────────────────────────────────────┐
│                    Data Layer (Eloquent + SQLite / MySQL)        │
│  recipes  recipe_versions  recipe_drafts  recipe_draft_edits     │
│  recipe_ingredients  recipe_step  recipe_components (sub-recipe) │
│  ingredients  ingredient_nutrients  ingredient_conversions       │
│  units  allergens  ingredient_allergens  ingredient_translations  │
│  ingredient_submissions  recipe_tests  recipe_test_ratings       │
│  ai_conversations  ai_messages                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Boundary |
|-----------|---------------|----------|
| `DraftManager` | Owns the working-draft lifecycle: apply edit, recall (undo last edit), commit to version | Never touches `recipe_versions` directly — only `recipe_drafts` + `recipe_draft_edits` |
| `VersionManager` | Commits a draft snapshot as an immutable `RecipeVersion`; enforces version ordering | Never mutates a version once written |
| `MetricsEngine` | Recursively resolves sub-recipe trees, normalizes all quantities to grams via `UnitConverter`, aggregates nutrition/cost/allergens with `BigDecimal` | Read-only: never mutates recipes; may be called on any version or draft |
| `UnitConverter` | Converts `(amount, unit, ingredient_id)` → grams using `ingredient_conversions` table with category-density fallback | Stateless pure service; all data comes from DB at call time |
| `RecipeAgent` | Wraps the Laravel AI SDK `Agent`; exposes a small set of scoped tools that operate on the working draft only; streams responses via SSE | Cannot write to `recipe_versions` directly; all edits go through `DraftManager` |
| `IngredientLibrary` | Resolves ingredient queries across the three-tier (official / private / submitted) visibility hierarchy | Enforces ownership: private ingredients are user-scoped; official rows are read-only to users |
| `ModerationService` | Transitions a submitted ingredient through the `pending → reviewing → approved/rejected` states; syncs approved submissions to the official library | Only accessible to Moderator/Admin roles via policy |
| `ImportPipeline` | Artisan commands that parse CIQUAL XML, USDA JSON, and Open Food Facts JSONL into normalised rows; idempotent via `external_id` + `source` composite key | Runs offline; never invoked from the HTTP layer |

---

## Domain Data Model

### 1. Recipe Versioning and Working Draft

The spec defines three layers: saved version history, working draft, and current/active version. The implementation uses a **snapshot-per-version + append-only edit log** model rather than full event sourcing — version history requires auditability, but the working draft only needs step-by-step undo within a single editing session.

**Tables:**

```
recipes
  id, user_id, title, slug, visibility (private|published), created_at, updated_at

recipe_versions
  id, recipe_id, version_number (integer, sequential), snapshot (JSON — full recipe state),
  committed_at, committed_by (user_id), notes

recipe_drafts
  id, recipe_id, base_version_id (FK → recipe_versions), data (JSON — current draft state),
  created_at, updated_at
  [one row per recipe; upserted on every edit]

recipe_draft_edits
  id, recipe_draft_id, sequence (integer, ordered), operation (JSON — what changed),
  applied_by (user_id | 'agent'), applied_at
  [append-only; Recall deletes the last row and reverts draft.data]
```

**Data flow — Save / Recall:**

```
User edits field
  → DraftManager::applyEdit(RecipeDraft, EditOperation)
  → UPDATE recipe_drafts SET data = merged_state
  → INSERT recipe_draft_edits (sequence = last + 1)

User clicks Recall (undo)
  → DraftManager::recallLastEdit(RecipeDraft)
  → SELECT last row FROM recipe_draft_edits
  → DELETE that row
  → Replay remaining edits onto base_version snapshot → UPDATE recipe_drafts.data

User clicks Save (commit)
  → VersionManager::commit(RecipeDraft)
  → INSERT recipe_versions (snapshot = current draft.data, version_number = max + 1)
  → DELETE recipe_draft_edits WHERE recipe_draft_id = ...
  → UPDATE recipe_drafts SET base_version_id = new version, data = new snapshot
```

**Why snapshot-per-version, not pure event sourcing:** The recipe domain has a modest number of versions per recipe (dozens, not millions). Full event sourcing overhead is not justified. Snapshots make version display and metric calculation trivial — load one JSON row. The edit log inside the draft is only for the current working session's undo stack.

**Why JSON snapshot, not normalized version rows:** A recipe's structure is semi-hierarchical (ingredients, steps, sub-recipe links). Normalizing each historical state into relational rows creates painful joins. The snapshot JSON is the authoritative record of a historical version; the relational tables are the live, editable state.

### 2. Nested Sub-Recipes with Metric Roll-Up

A recipe can reference another recipe as a component (e.g. a dough, a sauce). The reference is to a **specific version** of the sub-recipe, not the live draft, so the parent recipe's metrics are stable.

**Table:**

```
recipe_components
  id, parent_recipe_id, component_recipe_version_id (FK → recipe_versions),
  quantity (DECIMAL 10,4), unit_id (FK → units), position
```

**Recursive roll-up in `MetricsEngine`:**

1. Build the component tree: start from the target recipe version's snapshot; find all `recipe_components` rows; recursively load each referenced `recipe_version.snapshot`.
2. Detect cycles: maintain a visited set of `recipe_version_id` values. A cycle means a recipe eventually includes itself — throw a `CircularSubRecipeException` caught at the controller level and surfaced to the UI as a validation error.
3. Normalize each sub-recipe's total output to grams (using its yield and `UnitConverter`).
4. Compute proportional metrics: if the parent uses "200 g of Béchamel" and the Béchamel version yields 500 g, the contribution factor is 200/500. Apply that to all of the sub-recipe's per-gram nutritional values.
5. Sum across all ingredient lines and sub-recipe contributions using `brick/math` `BigDecimal` throughout.

**Why a specific version reference, not the live recipe:** If the sub-recipe is updated, the parent's saved metrics must not silently change. Chefs need to make a conscious decision to "upgrade" the component — exactly like package pinning. The UI should display a "sub-recipe has a newer version" indicator.

### 3. Ingredient Model — Three-Tier Visibility

```
ingredients
  id, source (ciqual|usda|off|user), external_id, owner_user_id (NULL for official),
  visibility (official|private|submitted), category_id, foodex2_code,
  -- nutrition (per 100 g, DECIMAL 10,4 columns)
  energy_kcal, protein, fat, saturated_fat, carbohydrate, sugar, fiber, sodium,
  -- frozen-dessert reserved fields (nullable; post-MVP)
  total_solids_pct, fat_pct, msnf_pct, sugar_pct, other_solids_pct, water_pct,
  pac_coefficient, pod_coefficient, de_value, brix, ingredient_class,
  created_at, updated_at

ingredient_translations
  id, ingredient_id, locale, name, name_aliases (JSON array of alternate names)

ingredient_conversions
  id, ingredient_id, from_amount (DECIMAL 10,4), from_unit_id, gram_weight (DECIMAL 10,4),
  modifier (e.g. "sifted", "packed", "chopped"), source (usda_portion|curated|user),
  notes

ingredient_submissions
  id, ingredient_id (FK → ingredients; the private ingredient being submitted),
  submitted_by (user_id), status (pending|reviewing|approved|rejected),
  reviewer_user_id, review_notes, submitted_at, reviewed_at
```

**Three-tier hierarchy for `IngredientLibrary` queries:**

1. Official (`visibility = official`, `owner_user_id IS NULL`) — globally visible, editable only by Admins via the import pipeline or after moderation promotes a submission.
2. Private (`visibility = private`, `owner_user_id = current_user`) — visible only to the owning user.
3. Submitted (`visibility = submitted`) — visible to the submitting user and to Moderators/Admins.

When a user attaches an ingredient to a recipe, the library searches in priority order: official first (by name match across `ingredient_translations`), then their own private ingredients. The UI surfaces both with a visual distinction.

**Submission → moderation flow:**

```
User creates private ingredient → ingredients row (visibility=private)
User clicks "Submit for review"
  → INSERT ingredient_submissions (status=pending)
  → UPDATE ingredients SET visibility=submitted

Moderator reviews:
  If approved:
    → UPDATE ingredients SET visibility=official, owner_user_id=NULL
    → UPDATE ingredient_submissions SET status=approved
  If rejected:
    → UPDATE ingredients SET visibility=private
    → UPDATE ingredient_submissions SET status=rejected, review_notes=...
```

Authorization: a `IngredientPolicy` with `submit()`, `review()`, and `promote()` gates. The `review` and `promote` gates check `$user->hasRole('moderator')` or `$user->hasRole('admin')`.

### 4. Metrics Engine

The engine is a **pure read service** — it never writes, never caches, always recomputes on demand from the current draft or a specific version snapshot. This keeps the data model simple: no denormalized metric columns that can drift out of sync.

**Input:** `(RecipeVersion|RecipeDraft, scale_factor = 1.0)`

**Output:** a typed `RecipeMetrics` value object (not persisted):

```php
class RecipeMetrics
{
    public function __construct(
        public readonly BigDecimal $energyKcal,
        public readonly BigDecimal $protein,
        public readonly BigDecimal $fat,
        public readonly BigDecimal $carbohydrate,
        public readonly BigDecimal $fiber,
        public readonly BigDecimal $sodium,
        public readonly BigDecimal $totalWeightGrams,
        public readonly BigDecimal $costTotal,
        public readonly BigDecimal $costPerPortion,
        public readonly array     $allergens,         // ['contains' => [...], 'may_contain' => [...]]
        public readonly array     $dietaryTags,
        public readonly array     $subRecipeMetrics,  // keyed by component id, recursive
    ) {}
}
```

**Precision rule:** Every intermediate value uses `BigDecimal`; rounding only happens at presentation time (the controller or Inertia transformer rounds to the display precision). Never store a rounded value in the metrics value object.

**Computation steps:**

```
For each ingredient line in the recipe:
  1. UnitConverter::toGrams(amount, unit, ingredient_id) → BigDecimal grams
  2. Load ingredient nutrition per 100 g
  3. Scale: nutrient_contribution = (grams / 100) * nutrient_per_100g
  4. Accumulate into running totals

For each sub-recipe component:
  1. UnitConverter::toGrams(quantity, unit) → grams_used
  2. Recursively compute MetricsEngine::compute(component_version)
  3. component_yield_grams = component_metrics.totalWeightGrams
  4. factor = grams_used / component_yield_grams
  5. Accumulate: parent_energy += component_metrics.energyKcal * factor (etc.)

Allergens (EU Reg 1169/2011):
  Union of all ingredient allergens; maintain 'contains' vs 'may_contain' distinction;
  sub-recipe allergens propagate with the same distinction

Baker's percentage:
  Computed as a post-pass: (each ingredient gram weight / total flour gram weight) * 100
  Only meaningful when recipe has flour-type ingredients; flag in output if no flour found
```

### 5. Unit Conversion Data Model

```
units
  id, name, symbol, type (weight|volume|count|custom), base_factor (DECIMAL 20,10)
  -- base_factor: how many grams/ml in this unit (for weight/volume); NULL for count/custom
  -- e.g. gram: type=weight, base_factor=1; cup: type=volume, base_factor=NULL (ingredient-specific)

ingredient_conversions
  id, ingredient_id, from_amount, from_unit_id, gram_weight (DECIMAL 10,4),
  modifier, source, notes
```

**Conversion resolution order in `UnitConverter::toGrams(amount, unit, ingredient_id)`:**

1. If `unit.type = weight`: `grams = amount * unit.base_factor` — exact, no ingredient lookup needed.
2. If `unit.type = volume` and ingredient has a matching `ingredient_conversions` row: proportional interpolation from the stored reference measure.
3. If `unit.type = volume` and ingredient has a category-level density fallback in a `categories` table: `grams = amount_ml * density`.
4. If `unit.type = count`: must have an explicit `ingredient_conversions` row (e.g., "1 egg = 50 g"). Throw `NoConversionException` if absent — surfaced to the UI as a data-quality warning on the ingredient.
5. No general-purpose volume-to-gram factor (cup → grams without an ingredient is meaningless).

**Why no package for this:** Standard-unit math (g↔kg, ml↔l) is two lines. The hard problem — flour by volume → grams — is data, not code. The custom service is 100 lines and uses the DB.

### 6. AI Agent Architecture

The AI agent uses the **Laravel AI SDK** (not MCP). MCP is for exposing the app to external AI clients; the AI SDK is for building AI features into the app — which is what the per-recipe chat is.

**Component structure:**

```
app/
└── AI/
    ├── RecipeAgent.php           (extends Agent; scoped tools, streaming enabled)
    ├── Tools/
    │   ├── ReadRecipeTool.php    (returns current draft JSON to the agent context)
    │   ├── UpdateIngredientLine.php  (calls DraftManager::applyEdit)
    │   ├── AddIngredientLine.php
    │   ├── RemoveIngredientLine.php
    │   ├── UpdateStep.php
    │   ├── AddStep.php
    │   ├── UpdateYield.php
    │   └── CreateVariantDraft.php    (creates a new working draft from a version)
    └── AgentController.php       (streams SSE; authenticates; loads recipe context)
```

**Provider-agnostic via AI SDK config:**

```php
// config/ai.php — swap provider without touching agent code
'default' => env('AI_PROVIDER', 'anthropic'),
```

**Context scope discipline:** The agent is instantiated per-request with access only to the single recipe's draft, its version history summaries, and its test feedback. It is not given access to the full ingredient library or other users' recipes. Tools are minimal and scoped — avoiding context bloat as the Laravel team recommends.

**Edit flow:**

```
User sends message in chat
  → POST /recipes/{recipe}/agent
  → AgentController streams SSE response

Agent decides to apply an edit:
  → Calls UpdateIngredientLine tool (server-side)
  → Tool calls DraftManager::applyEdit(draft, operation)
  → Tool returns confirmation JSON to agent
  → Agent communicates the change to user in natural language

User sees "Accept / Recall" in UI:
  → Accept: no action needed (already in draft)
  → Recall: POST /recipes/{recipe}/draft/recall
             → DraftManager::recallLastEdit(draft)
```

**Conversations are persisted** so the agent has history across page reloads:

```
ai_conversations
  id, recipe_id, user_id, created_at

ai_messages
  id, conversation_id, role (user|assistant|tool), content (TEXT), metadata (JSON), created_at
```

### 7. Data Import Pipelines (CIQUAL / USDA / Open Food Facts)

Each source is a dedicated Artisan command, all idempotent. They run offline (never from HTTP) and are separated because their source formats, update cadences, and licensing terms differ.

**Command structure:**

```
app/Console/Commands/Import/
  ImportCiqualCommand.php       (reads CIQUAL XML → upsert ingredients)
  ImportUsdaCommand.php         (reads USDA FDC JSON ZIP → upsert ingredients + conversions)
  ImportOpenFoodFactsCommand.php (reads OFF JSONL → enrich existing ingredients + Greek names)
```

**Idempotency key:** `(source, external_id)` composite unique index on `ingredients`. Re-running the import updates nutrition values but never creates duplicate rows.

**Pipeline stages per command:**

```
1. Parse     — transform source file format into a common DTO (PHP object)
2. Normalize — map source nutrient names to our column names; convert units if needed
3. Deduplicate — match against existing rows by external_id; resolve conflicts
4. Upsert    — INSERT ... ON CONFLICT DO UPDATE
5. Translate — for CIQUAL (French names only): insert ingredient_translations for 'fr';
               queue a translation task for 'el' (Greek) and 'en'
6. Conversions — for USDA: upsert ingredient_conversions from food_portion rows
7. Allergens — for OFF: upsert ingredient_allergens from allergens_tags field
```

**Source priority for nutrition values:** CIQUAL rows take precedence. USDA rows fill gaps (where `source = 'ciqual'` row does not exist for that food). OFF rows add allergen data and Greek names but do not overwrite CIQUAL/USDA nutrition values (OFF user-contributed nutrition data is less reliable).

---

## Recommended Project Structure Extensions

The existing `app/` structure (controllers in feature subdirectories, concerns as traits) stays intact. New domain code fits into these locations:

```
app/
├── AI/                           # Agent + tools (new)
│   ├── RecipeAgent.php
│   └── Tools/
├── Console/Commands/Import/      # Import pipeline commands (new)
├── Concerns/                     # Existing traits; add IngredientOwnershipRules.php
├── Http/Controllers/
│   ├── Ingredients/              # New feature group
│   ├── Recipes/                  # New feature group
│   │   ├── RecipeController.php
│   │   ├── DraftController.php
│   │   ├── VersionController.php
│   │   └── AgentController.php
│   └── Moderation/               # New feature group
├── Http/Requests/
│   ├── Ingredients/
│   └── Recipes/
├── Models/
│   ├── Recipe.php
│   ├── RecipeVersion.php
│   ├── RecipeDraft.php
│   ├── RecipeDraftEdit.php
│   ├── RecipeComponent.php
│   ├── RecipeIngredient.php
│   ├── RecipeStep.php
│   ├── RecipeTest.php
│   ├── Ingredient.php
│   ├── IngredientTranslation.php
│   ├── IngredientConversion.php
│   ├── IngredientSubmission.php
│   ├── Unit.php
│   ├── Allergen.php
│   └── AiConversation.php
├── Policies/
│   ├── RecipePolicy.php
│   ├── IngredientPolicy.php
│   └── IngredientSubmissionPolicy.php
└── Services/
    ├── DraftManager.php
    ├── VersionManager.php
    ├── MetricsEngine.php
    ├── UnitConverter.php
    └── IngredientLibrary.php
```

---

## Data Flow Diagrams

### Recipe Edit → Save Flow

```
User edits ingredient quantity
  ↓
PATCH /recipes/{id}/draft/ingredient-lines/{line_id}
  ↓
DraftController → FormRequest validates
  ↓
DraftManager::applyEdit(draft, EditOperation)
  ├── Merges operation into recipe_drafts.data (JSON)
  └── Appends row to recipe_draft_edits (append-only undo log)
  ↓
MetricsEngine::compute(draft) → RecipeMetrics value object
  ↓
Inertia::render with updated draft + metrics → React re-renders live panel
```

### Recall (Undo) Flow

```
User clicks Recall
  ↓
POST /recipes/{id}/draft/recall
  ↓
DraftManager::recallLastEdit(draft)
  ├── DELETE last recipe_draft_edits row (highest sequence)
  ├── Replay remaining edits onto base_version snapshot
  └── UPDATE recipe_drafts.data
  ↓
Return updated draft via Inertia redirect → UI reflects undone state
```

### Save (Commit) Flow

```
User clicks Save
  ↓
POST /recipes/{id}/versions
  ↓
VersionManager::commit(draft)
  ├── INSERT recipe_versions (snapshot = draft.data, version_number++)
  ├── DELETE recipe_draft_edits WHERE recipe_draft_id = draft.id
  └── UPDATE recipe_drafts (base_version_id = new version)
  ↓
Inertia redirect to recipe show with new version active
```

### Metrics Roll-Up Flow (Sub-Recipe)

```
MetricsEngine::compute(RecipeVersion $version, float $scaleFactor = 1.0)
  ↓
  For each RecipeIngredient in version.snapshot:
    UnitConverter::toGrams(amount, unit, ingredient_id) → grams
    Load Ingredient nutrient values (per 100 g)
    Accumulate: nutrient += (grams / 100) * nutrient_value [BigDecimal]
  ↓
  For each RecipeComponent in version.snapshot:
    Load component RecipeVersion
    Recursive: MetricsEngine::compute(component_version) → childMetrics
    factor = toGrams(component.quantity, component.unit) / childMetrics.totalWeightGrams
    Accumulate: nutrient += childMetrics.nutrient * factor [BigDecimal]
  ↓
  Allergen union: collect all 'contains' + 'may_contain' from ingredients + components
  ↓
  Return RecipeMetrics (value object, never persisted)
```

### AI Agent Edit Flow

```
User sends message in chat
  ↓
POST /recipes/{id}/agent/message (streaming)
  ↓
AgentController → load RecipeDraft + conversation history
  ↓
RecipeAgent::stream(messages, tools=[...])
  ↓ (SSE to browser)
Agent decides to call UpdateIngredientLine tool:
  ├── Tool calls DraftManager::applyEdit → writes to DB
  └── Tool returns confirmation to agent
  ↓
Agent narrates the change in natural language → streamed to browser
  ↓
Frontend shows "Recall" button (last edit is the agent's change)
```

---

## Architectural Patterns

### Pattern 1: Service Layer Over Eloquent

**What:** Domain services (`DraftManager`, `MetricsEngine`, `UnitConverter`) own all business logic. Controllers are thin: validate → call service → return Inertia response.

**Why:** The metrics engine and draft manager need to be called from controllers, AI tools, and Artisan commands. Putting logic in controllers would force duplication.

**Trade-off:** Adds a service layer on top of standard Laravel MVC. Justified here because the domain logic is non-trivial and multi-caller.

### Pattern 2: Append-Only Edit Log for Working Draft

**What:** Each edit to the working draft appends a row to `recipe_draft_edits`. Recall deletes the last row and replays the remaining log onto the base snapshot. The draft's `data` column is the materialized current state.

**Why:** Provides step-by-step undo without event sourcing's complexity. The log is ephemeral (cleared on Save), so it never grows unboundedly.

**Trade-off:** Replay on Recall is O(n) in the number of pending edits. For a normal editing session (10–50 edits), this is instant. If a session somehow accumulates hundreds of unsaved edits, replay is still sub-second for recipe-scale data.

### Pattern 3: JSON Snapshot for Version History

**What:** Each `recipe_versions` row stores the full recipe state as JSON. Relational ingredient/step tables hold the *live* editable state; historical states are snapshots.

**Why:** Historical versions are read-only. Normalizing them into relational rows would require version-scoped joins on every table. JSON snapshots allow loading any version with one query and computing metrics without version-parameterized joins.

**Trade-off:** Snapshots consume more storage than diffs. At recipe scale (a recipe JSON is 10–50 KB), 50 versions = < 2.5 MB per recipe. Acceptable.

### Pattern 4: BigDecimal for All Numeric Operations

**What:** Every calculation in `MetricsEngine` and `UnitConverter` uses `brick/math` `BigDecimal`. `DECIMAL(10,4)` columns for nutrition values, `DECIMAL(20,10)` for conversion factors. Never PHP `float`.

**Why:** Float drift compounds across scaling and sub-recipe roll-up. A recipe scaled to 1000 portions with 5 sub-recipe levels would accumulate visible errors with float arithmetic.

**Implementation note:** Laravel Eloquent returns DECIMAL columns as strings. Always construct `BigDecimal::of($model->protein)` — never `BigDecimal::of((float) $model->protein)`.

### Pattern 5: Scoped AI Tools

**What:** The `RecipeAgent` exposes exactly the tools needed to edit the working draft — no more. It does not have direct DB access, cannot commit versions, and cannot touch other users' data.

**Why:** Tool count directly impacts token consumption (context bloat). Keeping the tool set minimal (8–10 tools) keeps inference costs predictable. All tools route through `DraftManager`, maintaining the same business rule enforcement as the UI path.

---

## Anti-Patterns

### Anti-Pattern 1: Computing Metrics on Write

**What people do:** Store computed nutrition totals on the `recipe_versions` or `recipes` table and update them whenever an ingredient changes.

**Why it's wrong:** Cached metrics go stale when sub-recipe ingredients change. Invalidation logic becomes a web of observers and events. When metrics are wrong, it's hard to know why.

**Do this instead:** Always compute on read from the source ingredient data. Recipe-scale computation with `BigDecimal` takes < 50 ms. Add a Redis cache keyed on `version_id` (immutable) if profiling reveals a bottleneck.

### Anti-Pattern 2: Referencing the Live Recipe in a Sub-Recipe Component

**What people do:** Store `parent.component_recipe_id` (not `component_recipe_version_id`) so the parent always uses the latest sub-recipe.

**Why it's wrong:** Parent recipe metrics silently change when the sub-recipe is modified. A chef who committed a version and cooked from it gets different metrics the next day.

**Do this instead:** Always pin to a specific `recipe_version_id`. Surface "newer version available" in the UI and make upgrading a conscious user action.

### Anti-Pattern 3: One Import Seeder That Does Everything

**What people do:** A single `DatabaseSeeder` that calls CIQUAL + USDA + OFF in sequence, making the import order hard to manage and re-runs impossible.

**Why it's wrong:** Each source has a different update cadence (CIQUAL updates annually, USDA monthly, OFF continuously). Coupling them means re-running any one requires running all.

**Do this instead:** Three independent Artisan commands, each idempotent via `(source, external_id)` unique index. Run any one independently. Run all three in order (CIQUAL → USDA → OFF) for a fresh seed.

### Anti-Pattern 4: Putting AI Tool Logic in the Agent Class

**What people do:** Implement the recipe mutation logic inline inside the tool definition in the Agent class.

**Why it's wrong:** The same mutation logic then can't be used by the HTTP controller (when the user edits without AI). Creates two code paths for the same operation that can diverge.

**Do this instead:** Each AI tool is a thin wrapper that calls the same `DraftManager` method the HTTP controller calls. The tool adds the AI-specific concerns (confirmation language, error formatting for the LLM).

---

## Build Order — Component Dependencies

The domain has hard dependencies that dictate build sequence:

**Phase 1: Foundation (no dependencies)**
- `units` table + `UnitConverter` — needed by every downstream component
- `allergens` lookup table — small, seeded, referenced everywhere
- Role system (User / Moderator / Admin)

**Phase 2: Ingredient Library (depends on Phase 1)**
- `ingredients` + `ingredient_translations` + `ingredient_nutrients`
- `ingredient_conversions` (fed by USDA import)
- `ingredient_allergens`
- `IngredientLibrary` service
- Import pipeline commands (CIQUAL → USDA → OFF)

**Phase 3: Recipe Core (depends on Phase 2)**
- `recipes` + `recipe_versions` + `recipe_drafts` + `recipe_draft_edits`
- `recipe_ingredients` + `recipe_steps`
- `DraftManager` + `VersionManager`
- Basic recipe CRUD + draft edit / recall / save flows

**Phase 4: Sub-Recipes + Metrics Engine (depends on Phase 3)**
- `recipe_components` table
- `MetricsEngine` with recursive roll-up
- Metrics panel on recipe UI
- Baker's percentage, allergen union, dietary tags

**Phase 5: Recipe Tests (depends on Phase 3)**
- `recipe_tests` + `recipe_test_ratings` + test photos (file storage)
- Trial run and structured experiment flows

**Phase 6: AI Agent (depends on Phases 3, 4, 5)**
- `ai_conversations` + `ai_messages`
- `RecipeAgent` + tool implementations (thin wrappers around DraftManager)
- `AgentController` with SSE streaming
- Agent reads test feedback (Phase 5 must exist)

**Phase 7: Moderation + Submission Workflow (depends on Phase 2)**
- `ingredient_submissions`
- `ModerationService` + `IngredientPolicy`
- Moderator queue UI

**Phase 8: Publishing + Public Library (depends on Phase 3)**
- `recipes.visibility` column (reserved from Phase 3)
- Public recipe discovery UI

---

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| AI provider (OpenAI / Anthropic / etc.) | Laravel AI SDK `Agent` class; provider configured in `config/ai.php` | Never hardcode provider; use adapter; API key in `.env` |
| CIQUAL 2025 | Offline Artisan command reads XML file | CC-BY; file checked into `storage/imports/` or downloaded at deploy time |
| USDA FoodData Central | Offline Artisan command reads JSON ZIP download | CC0; large file (~400 MB uncompressed); do not bundle in repo |
| Open Food Facts | Offline Artisan command reads JSONL dump | ODbL; ~50 GB full dump; filter to `countries:greece` subset (~300 MB) |
| File storage (test photos) | Laravel's `Storage` facade + configured disk | Local in dev; S3-compatible in production |

### Internal Boundaries

| Boundary | Communication | Constraint |
|----------|---------------|------------|
| Controller ↔ DraftManager | Direct PHP method call | Controller must not build `EditOperation` objects — only pass validated request data; DraftManager constructs the operation |
| DraftManager ↔ MetricsEngine | MetricsEngine called by controller after DraftManager applies edit; not called by DraftManager | MetricsEngine is read-only; DraftManager must not call it |
| RecipeAgent tool ↔ DraftManager | Tool calls DraftManager::applyEdit — same path as HTTP | Never a separate mutation path for AI edits |
| MetricsEngine ↔ UnitConverter | MetricsEngine calls UnitConverter per ingredient line | UnitConverter is stateless; no Eloquent query inside the loop if ingredient data is pre-loaded |
| ImportPipeline ↔ IngredientLibrary | Import commands write directly to DB; IngredientLibrary is a read service | Import bypasses IngredientLibrary intentionally — library is for user-facing resolution |

---

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0–1k users | Monolith is correct. SQLite in dev, MySQL/Postgres in prod. No queue needed for metrics (compute on request). |
| 1k–50k users | Add Redis cache keyed on `recipe_version_id` for computed `RecipeMetrics` (immutable versions can be cached indefinitely). Move import pipeline to scheduled jobs. Add queue for AI agent responses if latency becomes visible. |
| 50k+ users | Read replica for recipe and ingredient queries. Consider separating import pipeline to a worker process. Cache ingredient conversion data in Redis (changes rarely). |

**First bottleneck:** `MetricsEngine` on popular public recipes. Fix: cache `RecipeMetrics` per `recipe_version_id` in Redis (versions are immutable, cache never needs invalidation). Working draft metrics are never cached — they change with every edit.

**Second bottleneck:** USDA/OFF import re-runs on large tables. Fix: incremental imports via `updated_since` parameter on USDA API; OFF provides daily diff dumps.

---

## Sources

- Laravel AI SDK vs MCP: [https://laravel.com/blog/laravel-ai-sdk-boost-or-mcp-which-tool-do-you-need](https://laravel.com/blog/laravel-ai-sdk-boost-or-mcp-which-tool-do-you-need)
- Laravel AI SDK tool-calling architecture: [https://dev.to/martintonev/how-to-use-laravel-ai-sdk-in-production-agents-tools-streaming-rag-4mfk](https://dev.to/martintonev/how-to-use-laravel-ai-sdk-in-production-agents-tools-streaming-rag-4mfk)
- brick/math BigDecimal: [https://github.com/brick/math](https://github.com/brick/math)
- Laravel Eloquent versioning packages surveyed: [https://github.com/indracollective/laravel-revisor](https://github.com/indracollective/laravel-revisor), [https://github.com/Grazulex/laravel-draftable](https://github.com/Grazulex/laravel-draftable)
- Recursive CTE for hierarchical rollup: [https://www.ituonline.com/blogs/mastering-common-table-expressions-efficient-recursion-and-hierarchical-data-in-sql/](https://www.ituonline.com/blogs/mastering-common-table-expressions-efficient-recursion-and-hierarchical-data-in-sql/)
- Event sourcing vs snapshot tradeoffs: [https://domaincentric.net/blog/event-sourcing-snapshotting](https://domaincentric.net/blog/event-sourcing-snapshotting)
- USDA FoodData Central download: [https://fdc.nal.usda.gov/download-datasets/](https://fdc.nal.usda.gov/download-datasets/)
- Laravel Authorization (Policies): [https://laravel.com/docs/13.x/authorization](https://laravel.com/docs/13.x/authorization)

---

*Architecture research for: twosides — professional recipe management platform*
*Researched: 2026-05-16*
