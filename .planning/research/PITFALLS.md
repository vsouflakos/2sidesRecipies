# Pitfalls Research

**Domain:** Professional recipe management — nutrition/cost metrics, versioning, nested sub-recipes, unit conversion, EU compliance, AI agent editing, multi-language food data
**Researched:** 2026-05-16
**Confidence:** HIGH (critical pitfalls derived from domain knowledge, official specs, and verified sources); MEDIUM (specific implementation counts from WebSearch)

---

## Critical Pitfalls

### Pitfall 1: Float Arithmetic in Nutrition and Cost Math

**What goes wrong:**
PHP `float` accumulates rounding error across scaling, sub-recipe roll-up, and multi-ingredient summation. A recipe scaled to 47 portions can silently produce nutrient totals that differ from the correct value by several grams, or cost totals off by cents — enough to fail EU nutrition label rounding rules and erode chef trust in the tool.

The classic failure: round each ingredient's contribution first, then sum. A recipe with 9 ingredients each contributing 0.4 g added sugar reports 0 g (each rounds to 0 below the 0.5 threshold) when the correct display is 4 g.

**Why it happens:**
Developers reach for `float` because it's the default numeric type. Nutrition databases store values to 2–4 decimal places, which looks safe, but scaling multiplies the error on every ingredient line. Sub-recipe roll-up compounds this across levels.

**How to avoid:**
Use `brick/math` `BigDecimal` / `BigRational` for **all** arithmetic from the moment an ingredient quantity is read from the database. Store every numeric column as `DECIMAL(precision, scale)` — never `DOUBLE` or `FLOAT`. Apply rounding rules only at the final display layer, never mid-calculation. The project already declares `brick/math` as a dependency — enforce its use in a single `MetricsEngine` service and reject any PR that uses `float` arithmetic for quantities or nutrients.

**Warning signs:**
- Nutrition totals differ by small fractions from spreadsheet manual checks.
- Scaling a recipe to a non-integer multiplier produces a value that cannot be reproduced.
- Database schema contains `FLOAT` or `DOUBLE` columns for nutrient values.
- Two code paths compute the same metric and return different results.

**Phase to address:**
Metrics engine phase (ingredient schema + conversion + nutrition roll-up). Define the `BigDecimal`-only rule in a `CONTRIBUTING` note or code-review checklist before any metric calculation code is written.

---

### Pitfall 2: Circular Sub-Recipe References Crashing the Stack

**What goes wrong:**
A recipe can be added as a component of another recipe. Without a cycle-detection guard, Recipe A containing Recipe B containing Recipe A causes infinite recursion in the metric roll-up traversal and crashes the process or exhausts memory. This is not hypothetical — it can happen through copy-paste ingredient entry or when the AI agent creates a variant that references the parent.

**Why it happens:**
Developers implement the roll-up as a straightforward recursive function and forget that the data model is a directed graph, not a tree. The problem doesn't appear in development with small seed data; it emerges in production when users build complex recipe webs.

**How to avoid:**
At the database layer, enforce a cycle-prevention check in the `recipe_ingredients` insertion path: before inserting sub-recipe S as a component of recipe P, run a recursive CTE (Common Table Expression) that traverses the ancestor chain of P and asserts S does not appear in it. This check must also cover the AI agent's `add_ingredient` tool. Additionally, keep a `max_depth` guard in the roll-up service (e.g. depth > 10 = bail with an error) as a safety net.

**Warning signs:**
- No cycle check exists in the ingredient-line creation logic.
- The roll-up function is purely recursive with no visited-set tracking.
- The AI agent tool for adding ingredients does not validate sub-recipe eligibility.

**Phase to address:**
Nested sub-recipe implementation phase, before the metrics engine is built on top of it.

---

### Pitfall 3: Unit Conversion Falling Back to Wrong Density

**What goes wrong:**
When a volume-to-weight conversion is missing from the `ingredient_conversions` table, the engine silently falls back to a category-level density or, worse, uses a generic water density (1 g/ml for everything). A cup of bread flour weighs ~120 g; at water density it reports 237 g — almost double. Nutrition, cost, and baker's percentage calculations are all wrong, and the error is invisible unless a chef manually cross-checks.

**Why it happens:**
The happy path (USDA `food_portion` data exists for the ingredient) works. Developers don't test the fallback path. USDA `food_portion` coverage is ~80% at best for Foundation Foods; for professional and specialty ingredients it drops further. The fallback path is never exercised during development against the seed data subset.

**How to avoid:**
Log every fallback conversion loudly — write a DB record flagging "this ingredient line used a fallback conversion for `from_unit → grams`" and surface it to the user as a visible warning ("Conversion estimated — verify gram weight for X"). Build a dedicated admin/moderator view listing all ingredients with no confirmed conversion data. When importing USDA `food_portion`, track `source = 'usda'` vs. `source = 'override'` vs. `source = 'category_fallback'` in the `ingredient_conversions` table so fallback use is queryable.

**Warning signs:**
- No `source` or `confidence` column on `ingredient_conversions`.
- No logging when the fallback path is hit.
- Test suite only tests ingredients that have USDA portion data.
- No UI indicator that a conversion was estimated.

**Phase to address:**
Unit conversion service implementation phase.

---

### Pitfall 4: Nutrient Unit Mismatch When Merging CIQUAL and USDA Data

**What goes wrong:**
CIQUAL and USDA FDC use the same nutrient names but different units for some micronutrients. For example, vitamin D in CIQUAL is expressed in µg (micrograms); in USDA it can appear as IU (International Units) depending on the dataset. Sodium is always mg in both, but energy in CIQUAL uses kcal and kJ side by side. Merging the two sources without normalizing to a single canonical unit per nutrient produces silently wrong values — a vitamin D entry mixed between the two sources can be off by a factor of 40 (1 IU vitamin D ≈ 0.025 µg).

**Why it happens:**
Developers import one source first, get it working, then import the second source assuming the schema already handles the units. The XML/CSV headers look similar enough that column mapping is done by name rather than by verifying units.

**How to avoid:**
Define a canonical unit for every nutrient in the `nutrients` table before any import runs (`column: nutrient_id, canonical_unit`). Each seeder must declare the source unit and apply a conversion factor. Write a seeder test that cross-checks the same food in both sources (e.g. chicken breast, raw) and asserts the nutrients are within a reasonable range — a 10× discrepancy is a unit bug. Document CIQUAL's energy expression quirk (both kcal and kJ columns exist; pick one and ignore the other).

**Warning signs:**
- `nutrients` table has no `unit` column — units are assumed.
- Seeders import column values directly without a declared unit conversion step.
- Vitamin/mineral values for common foods are wildly high or low after import.
- No cross-source sanity check in the seeder test suite.

**Phase to address:**
Ingredient library seed phase (CIQUAL import + USDA backfill).

---

### Pitfall 5: ODbL Share-Alike Triggered by Publishing a Combined Database

**What goes wrong:**
The project stores Open Food Facts data locally — which is permitted for internal use. The ODbL share-alike clause activates when you **publicly use an adapted version of the database**. If the app ever exposes an endpoint that returns combined OFF + CIQUAL + USDA data as a downloadable dataset or as an openly-queryable API with no terms restriction, the ODbL requires releasing that combined dataset under ODbL. This means the app's proprietary enrichments (curated conversion data, override tables, moderation notes) must also be published.

**Why it happens:**
Teams read "internal storage is fine" and build a public-facing ingredient search API without legal review. The share-alike risk is easy to miss because it only materializes when a combined DB is published, not when individual records are displayed in the app UI.

**How to avoid:**
Confirm with legal counsel that in-app display of ingredient records (served per HTTP request, one record at a time, to authenticated users) does not constitute "public use" of an adapted database under ODbL 1.0. If a public ingredient API is ever added (unauthenticated, bulk-queryable), scope legal review first. Keep OFF-sourced rows clearly tagged with `source = 'open_food_facts'` in the DB so they can be excluded from any future export product if needed. Never expose a bulk-download endpoint for the ingredient library without legal sign-off.

**Warning signs:**
- No `source` column on ingredient records.
- A public `/api/ingredients` endpoint with no authentication or rate-limiting.
- No legal review documented before adding a bulk-export feature.

**Phase to address:**
Ingredient library phase (data import). Also flag for legal review before any public ingredient search API is shipped.

---

### Pitfall 6: Allergen "May Contain" Propagation Through Sub-Recipes

**What goes wrong:**
EU Regulation 1169/2011 requires declaring both "contains" (intentional) and "may contain" / traces (precautionary, cross-contamination). When a sub-recipe has a "may contain gluten" trace flag on one of its ingredients, that trace should propagate up to the parent recipe's allergen summary. If the roll-up only propagates "contains" flags and ignores "may contain", the parent recipe shows as allergen-free when it should carry a precautionary warning. A chef relying on this for menu labelling could unknowingly serve an allergic customer.

**Why it happens:**
The "contains" / "may contain" distinction is treated as a display concern rather than a data model concern. The roll-up logic merges allergen sets without preserving the severity distinction. "May contain" is also sometimes confused with "produced in a facility that handles" — a different level of risk.

**How to avoid:**
Model allergen presence as a two-state enum (`contains` / `may_contain`) on both `ingredient_allergens` and propagate both states independently through the roll-up. The roll-up rule: if any ingredient or sub-recipe "contains" allergen X, the parent "contains" X; if any "may contain" X (and none "contain" X), the parent "may contain" X. Store the provenance (which ingredient triggered the flag) so the moderator UI and chef UI can show the chain. Note: the EU is expected to harmonize "may contain" rules via an implementing regulation in Q4 2027 — build the data model to accommodate a third state or stricter rules without a migration.

**Warning signs:**
- `ingredient_allergens` table has a boolean `present` column rather than an enum.
- Roll-up logic unions allergen sets without preserving the contains/may-contain distinction.
- Test suite has no case for a trace allergen propagating through a two-level sub-recipe.

**Phase to address:**
Metrics engine phase — allergen roll-up implementation.

---

### Pitfall 7: Working Draft State Diverging from Version History

**What goes wrong:**
The working draft is a mutable layer; the version history is immutable. The pitfall is leaking mutable draft state into version reads — e.g. a route that fetches "the current recipe" returns the draft instead of the committed version, or the metric computation uses the draft's ingredient list but the version's yield, producing metrics for a recipe that doesn't actually exist. A second failure mode: concurrent AI agent edits and user manual edits both write to the draft without conflict detection, causing silent overwrites.

**Why it happens:**
The three-layer model (saved versions / working draft / active version) is richer than standard "current record" patterns. Developers instinctively fetch "the latest" and don't enforce which layer they're reading. The AI agent path and the human edit path independently update the draft without a locking mechanism.

**How to avoid:**
Represent the three layers as distinct concepts in the codebase: a `RecipeVersion` model (immutable once created), a `RecipeDraft` model (a snapshot of pending changes + a sequential `edit_sequence` integer for optimistic locking), and a pointer on the recipe to the "active version" ID. The draft is never exposed as "the recipe" — every read specifies a layer explicitly. The `Recall` operation pops the last applied edit from an edit-log table (not from a diff of full snapshots) so the undo is O(1). The AI agent's write tool must validate and increment `edit_sequence` atomically (optimistic lock) so concurrent writes fail loudly rather than silently winning.

**Warning signs:**
- A single `recipes` table row holds both current metrics and draft ingredient list.
- No `edit_sequence` or version check on draft writes.
- Tests do not cover the state after Save then Recall then Save again.
- The AI agent tool can write to the draft with no concurrency guard.

**Phase to address:**
Recipe versioning + working draft phase. The data model for the three layers must be locked in before the AI agent is built on top of it.

---

### Pitfall 8: Baker's Percentage Breaking When Sub-Recipes Contain Flour

**What goes wrong:**
Baker's percentage expresses each ingredient as a percentage of the total flour weight in the recipe. When a recipe contains a sub-recipe (e.g. a poolish or levain) that itself contains flour, naive implementations either: (a) ignore the sub-recipe's flour and compute percentages against only the outer recipe's flour weight (undercounting), or (b) double-count the outer flour plus the sub-recipe flour in the denominator. The hydration ratio is equally affected. Both errors produce baker's percentages that professional bakers will immediately recognize as wrong.

**Why it happens:**
Baker's percentage is defined for a flat list of ingredients. Extending it to a recursive structure requires a decision about how to "unroll" sub-recipes: flatten the component ingredients to their gram equivalents, then compute overall baker's percentage on the flattened list. This is not obvious and is not documented in most baker's percentage references.

**How to avoid:**
Document the rule explicitly: baker's percentage is computed on the **fully flattened ingredient list** (sub-recipes expanded to their constituent ingredients at their scaled weights). Mark each flattened ingredient with its source (`top_level` / `from_sub_recipe: [name]`) so the display can annotate which flour came from the levain vs. the main dough. Test with a recipe that has a poolish containing flour and water and assert the hydration is mathematically correct against a hand calculation.

**Warning signs:**
- Baker's percentage is computed before sub-recipe roll-up.
- No test case for a recipe with a flourcontaining sub-recipe.
- No "flour weight denominator" provenance in the computation log.

**Phase to address:**
Metrics engine phase — professional extras (baker's percentages, hydration).

---

### Pitfall 9: AI Agent Writing Structurally Invalid Recipe Edits

**What goes wrong:**
An LLM asked to "reduce the butter by 20%" applies the change correctly most of the time but occasionally: outputs a negative quantity, sets a unit that doesn't exist in the unit table, references an ingredient ID from its context window that no longer exists in the DB, or produces a valid JSON tool call whose values violate business rules (e.g. yield = 0 servings). The error is applied to the working draft, the user accepts it without noticing, and Saves — the committed version is now invalid.

**Why it happens:**
LLMs produce structurally correct JSON most of the time. Developers test the happy path (correct edit applied) and don't build validation for the output of the tool call. The working draft layer's "Recall" undo is supposed to be the safety net, but it doesn't help if the user has already Saved.

**How to avoid:**
Every AI agent tool that writes to a recipe draft must pass its output through the same validation pipeline as a human UI form submission — field types, value ranges (quantity > 0, unit must exist, ingredient_id must exist), business rules (yield >= 1 serving, step text not empty). Return a structured error to the agent if validation fails so the model can self-correct and retry before the user sees it. Log every agent-applied edit with the full before/after diff and the agent's stated rationale. Never let an agent commit a version directly — it writes to the draft; a human must Save.

**Warning signs:**
- The AI agent tool calls the model's output directly into an `update()` without passing through form validation.
- No test of the agent path with malformed/out-of-range values.
- Agent writes can trigger a version Save without user confirmation.

**Phase to address:**
AI agent phase. The validation layer should be built in the recipe editing phase so the agent tool reuses it rather than duplicating it.

---

### Pitfall 10: User-Submitted Ingredient Polluting the Official Library Before Review

**What goes wrong:**
Users submit private ingredients for inclusion in the official library. If the promotion path has any shortcut — auto-approval for trusted users, a moderator batch-approve button without individual inspection, or a bug that promotes before the review is marked complete — nutritionally incorrect or duplicate ingredients enter the official library. Every recipe that subsequently uses those ingredients inherits the bad data silently.

**Why it happens:**
Moderation workflows are added as an afterthought, so the state machine has gaps: "pending", "approved", "rejected" exist in the model but the controller accepts a promotion request from any authenticated moderator without verifying the review steps are complete. Duplicates are not checked because the search before promotion is optional.

**How to avoid:**
Make the moderation state machine explicit: `draft` → `submitted` → `under_review` (moderator locked) → `approved | rejected`. Promotion to `approved` requires: (a) moderator confirms no duplicate in the official library (a required step, not optional), (b) nutrient values pass a range sanity check against the nearest category average (flag if calories per 100 g are more than 3× the category mean), (c) at least one allergen assertion has been made (cannot approve with all allergen fields null). Only the `Admin` role can bypass range checks with a documented reason.

**Warning signs:**
- No enum or state column on the moderation record — status is inferred from boolean flags.
- No duplicate-check step in the moderator UI flow.
- Moderators can call the promote endpoint directly without completing the review steps.
- No sanity-range test for nutrient values.

**Phase to address:**
Ingredient library phase — moderation pipeline.

---

### Pitfall 11: Cooking Loss Applied Inconsistently Across Metrics

**What goes wrong:**
A recipe lists "200 g chicken breast, raw". The nutrition database holds raw chicken values. After cooking, the chicken loses ~30% of its weight (moisture evaporation). If cooking loss is applied to the weight for cost/yield calculations but NOT to the nutrient computation, or vice versa, the metrics are internally inconsistent: the portion cost is calculated on 140 g cooked yield but the nutrition label is calculated on 200 g raw — a 43% discrepancy in protein grams per portion.

**Why it happens:**
Cooking loss is modelled as a single "yield factor" per ingredient line, but it has two independent effects: weight reduction (affects yield, portion size, cost per gram cooked) and nutrient concentration (raw values must be scaled by the inverse yield factor to represent cooked weight). Developers apply it to one calculation and forget the other.

**How to avoid:**
Model cooking loss as an explicit `yield_factor` (0–1) on the ingredient line. Define precisely when it applies: to cost calculation (cost of raw input), to gram output (affects portion weight), and to nutrition (raw DB values × (1 / yield_factor) to get cooked nutrient density). Enforce this in unit tests: a test with `yield_factor = 0.7` must produce protein_per_100g_cooked = protein_per_100g_raw / 0.7, and cost_for_portion = cost_per_gram_raw × raw_quantity_g. Surface the yield factor visibly in the ingredient line UI so chefs can correct it per ingredient.

**Warning signs:**
- `yield_factor` is stored but only read in one of the three calculation paths.
- Nutrition and cost disagree on the effective weight of a cooked ingredient.
- No test covers a recipe with a cooked-weight ingredient.

**Phase to address:**
Metrics engine phase — cooking loss / shrinkage.

---

### Pitfall 12: Multi-Language Ingredient Names Colliding on Search

**What goes wrong:**
The ingredient library has ingredient names in multiple languages. Search for "basilic" should return basil (French name); search for "βασιλικός" should return the same record (Greek name); but a naive full-text index on a single `name` column will miss cross-language matches or — worse — return unrelated items that match in one language but not another. A parallel problem: Open Food Facts Greek-language ingredient names include the same ingredient under multiple transliterations ("κοτόπουλο", "κοτοπουλο", "chicken") that must resolve to one canonical record, not create three.

**Why it happens:**
Multi-language support is added to the UI first (i18n strings) and to the data second. Ingredient name translation is not the same problem as UI translation — the canonical record is one, but it has many name aliases in many languages. Developers add a `translations` JSON column or a simple `ingredient_translations` table but don't build the search layer to query across all language columns simultaneously.

**How to avoid:**
Use a separate `ingredient_names` table (ingredient_id, language_code, name, is_canonical) with a full-text index across all rows. Search queries the `ingredient_names` table and returns the unique `ingredient_id` set. Deduplication during import uses phonetic normalization + moderator review: when importing OFF Greek data, auto-match against existing names using trigram similarity (pg_trgm in PostgreSQL) and flag close matches for moderator confirmation rather than creating new records. The canonical display name per locale is the `is_canonical = true` row for that `language_code`.

**Warning signs:**
- Ingredient names stored as a JSON column on the `ingredients` table rather than a related table.
- No full-text index on ingredient names at all.
- Import seeders create new ingredient records for names that differ only by transliteration.
- Search returns different result sets for the same ingredient name in different languages.

**Phase to address:**
Ingredient library phase — schema design and seeder logic. Cannot be retrofitted cheaply after data is imported.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Store nutrient values as `FLOAT` | Simpler migration | Float drift compounds across sub-recipe levels; nutrition label rounding failures | Never — `brick/math` + `DECIMAL` is the rule from day one |
| Skip the working draft model, use a single "current" recipe row | Simpler data model | No safe place for AI edits to accumulate; no step-by-step Recall; AI writes overwrite human edits | Never — versioning is the core differentiability feature |
| Hardcode density fallback = 1 g/ml (water) | Conversion always returns a number | Wrong weight for every non-liquid ingredient that lacks a conversion entry | Never — log and surface the gap instead |
| Auto-approve user-submitted ingredients | Faster ingredient library growth | Nutritionally wrong data enters official library; every dependent recipe is silently corrupted | Never |
| Treat "contains" and "may contain" allergens as the same flag | Simpler UI | Non-compliant with EU 1169/2011; potential liability for allergic-reaction incidents | Never for an EU product |
| Use PHP `array_sum` on float nutrient values for roll-up | Trivial to write | Accumulates float error across N ingredients × M sub-recipe levels | Never in the metrics engine |
| Skip cycle detection on sub-recipe insertion | Faster insert path | Stack overflow or infinite loop on first circular reference | Never once sub-recipe feature ships |
| Import CIQUAL and USDA nutrient values without a declared unit-per-column mapping | Faster seeder | Silent unit mismatches (IU vs µg) produce wildly wrong values for vitamins | Never |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|-----------------|
| CIQUAL XML import | Import kJ and kcal columns without picking one canonical energy unit | Declare `kcal/100g` as the canonical energy unit; ignore the kJ column or store as a derived display value only |
| CIQUAL XML import | Treat `-` (not-detected) and `traces` sentinel strings as null or zero | Map to explicit enum values (`not_detected`, `traces`, `value`, `not_available`) so the distinction is preserved for allergen reasoning |
| USDA FoodData Central `food_portion` import | Assume all foods have portion data | Track `has_portion_data` per ingredient; flag missing as needing a curated override |
| USDA FoodData Central | Conflate "Foundation Foods" (research-grade) with "Survey Foods" (estimated) | Import only Foundation Foods as primary source; tag SR Legacy as fallback |
| Open Food Facts bulk dump | Import all Greek-tagged products without deduplication | Deduplicate by barcode first, then by normalized name; OFF has many near-duplicate product entries |
| Open Food Facts | Treat `allergens` tags (ingredients intentionally present) the same as `traces` tags | Map `allergens` → `contains`, `traces` → `may_contain` in the allergen model |
| AI agent tool calls | Pass the full recipe JSON in context on every message | Store context server-side; pass only the recipe ID + a structured summary to the model; regenerate context server-side on each turn |
| AI agent tool calls | Let the agent call any write tool unconditionally | Require human approval for any tool that modifies the draft; log every modification with the agent's stated rationale |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Recursive metric roll-up with N+1 queries | Roll-up takes seconds for deeply nested recipes (4+ levels) | Eager-load the full ingredient tree in one query before traversal; memoize ingredient nutrient lookups by ID | At recipes with 5+ sub-recipe levels or 50+ unique ingredients |
| Recomputing all metrics on every page load | Ingredient-heavy recipe pages are slow; concurrent users multiply the load | Cache computed metrics in a `recipe_metrics` table; invalidate on draft change or ingredient update | At >10 concurrent users viewing complex recipes |
| Full ingredient library search without index | Search box is slow as library grows past a few thousand records | pg_trgm GIN index on the `ingredient_names` table from day one; test with the full CIQUAL + USDA import (~4,000+ rows at launch) | After first full seed import |
| Loading full version history for display | Version list page times out for recipes with many versions | Paginate version history; store a summary snapshot per version rather than the full ingredient diff | At 50+ versions per recipe |
| AI agent context window growing unboundedly | Agent loses early conversation turns; cost spikes | Keep a server-side conversation summary; send only the last N turns + a compressed summary to the model | After 20–30 turns in one recipe's chat |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| AI agent tool calls not scoped to the recipe owner | One user's agent can edit another user's recipe draft | All write tools validate `recipe.user_id === auth()->id()` before writing; this is not optional |
| Moderator can promote any user's submitted ingredient without ownership check | Moderation bypass — moderator promotes an ingredient to official that hasn't been submitted | Policy check: only `submitted` state ingredients can be promoted; log every promotion with moderator ID |
| User-submitted ingredient names not sanitized | XSS via ingredient name rendered in a recipe's ingredient list | Sanitize all string fields on write; rely on React's automatic escaping for display; never use `dangerouslySetInnerHTML` for ingredient data |
| Nutrition/cost data returned in API responses without auth check | Private recipe ingredient costs (supplier pricing) leaked to unauthenticated callers | Private recipe metrics are never exposed via public routes; all metric endpoints require authentication |
| Agent conversation log not access-controlled | A user reads another user's recipe chat history | `recipe_conversations` scoped to `recipe.user_id`; middleware check on every conversation read |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Metrics update on every keystroke while editing an ingredient quantity | UI flickers; metrics show inconsistent state mid-edit | Debounce metric recalculation; update metrics only after the user finishes typing (300–500 ms debounce or on blur) |
| No visible indicator that a conversion was estimated (fallback density) | Chef trusts wrong gram weight; nutrition/cost metrics are off | Surface a warning icon inline on the ingredient line: "Conversion estimated — enter gram weight to confirm" |
| "Recall" undo does not show what it will undo | Chef clicks Recall without knowing which edit will be rolled back | Show the last N edits in the draft history panel with their description (agent-applied or user-applied) before Recall |
| Allergen summary shows only "contains" without "may contain" | Chef creates a menu label missing precautionary traces; potential allergen incident | Allergen summary table always shows both columns; never omit the "may contain" column even if empty |
| Nutrition values shown per "portion" without showing the portion weight | Chef cannot verify the number makes sense | Always show (per X g) alongside per-portion values; let the chef set the reference portion weight |
| Baker's percentage visible on non-baking recipes | Confuses non-baker chefs; empty/zero values look broken | Show baker's percentage section only when at least one ingredient is categorized as a flour-type (`ingredient_class = 'flour'`) |
| Version history listed in insertion order, not semantic order | Chef cannot find a previous version quickly | Sort versions by `created_at` descending with a relative timestamp ("3 hours ago"); allow adding a version note at Save time |

---

## "Looks Done But Isn't" Checklist

- [ ] **Unit conversion:** Conversion table shows a value for every ingredient in a test recipe — verify what happens when a NEW ingredient with no `ingredient_conversions` row is added (does the fallback path surface a warning, or silently use water density?).
- [ ] **Allergen roll-up:** Recipe displays "allergen-free" — verify by adding a sub-recipe with a "may contain gluten" ingredient and confirming the parent recipe now shows the trace warning.
- [ ] **Working draft:** "Save" button is wired up — verify that Saving creates a new immutable version and the draft is reset, not that it overwrites the previous version's data in place.
- [ ] **Recall:** "Recall" button undoes the last edit — verify Recall after an AI-applied edit, then Recall after a user-manual edit, then Recall with no draft edits (should show a "nothing to undo" state, not crash).
- [ ] **Circular sub-recipe:** Sub-recipe picker shows a list of recipes — verify that the recipe currently being edited does not appear in its own picker, and that ancestors of the current recipe are excluded.
- [ ] **Nutrient seeder:** CIQUAL import shows nutrition values — verify vitamin D values against ANSES source (µg range 0–50 for typical foods); a value of 400+ signals an IU/µg unit mix-up.
- [ ] **Baker's percentage:** Recipe with a poolish sub-recipe shows 60% hydration — verify the poolish's water and flour are included in the denominator calculation, not double-counted.
- [ ] **ODbL compliance:** Ingredient records are displayed in the UI — verify that no unauthenticated endpoint returns bulk ingredient data, only per-record lookups for authenticated users.
- [ ] **AI agent writes:** Agent applies "reduce butter by 20g" — verify the resulting quantity is stored as a `DECIMAL`, not a PHP float, and that the value passes validation (must be > 0).
- [ ] **Moderation:** Moderator clicks "Approve" — verify the approval is blocked if the allergen fields are all null (unanswered), not just if they are all false.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Float columns used for nutrients (discovered post-launch) | HIGH | Migrate columns to `DECIMAL`; recompute all stored metrics from raw ingredient data; notify users that metrics were recalculated |
| Circular reference causes production crash | MEDIUM | Add cycle guard as a hotfix; run a DB query to find and break existing circular references; no data loss if discovered before the version is Saved |
| Nutrient unit mismatch (IU vs µg) discovered after seeding | MEDIUM | Identify affected nutrient column; apply conversion factor update to all rows from that source; re-run affected recipe metrics; add a seeder regression test |
| ODbL violation (bulk OFF data exposed publicly) | HIGH | Remove the public endpoint immediately; review what data was exposed and for how long; consult legal on notification obligations; add authentication |
| AI agent applied and user Saved an invalid recipe version | LOW | Versions are immutable — the bad version exists but the user can activate a prior good version; add validation that prevents the bad state from being Saved again |
| User-submitted ingredient with wrong nutrition promoted to official | MEDIUM | Soft-delete the promoted ingredient; revert to draft state; notify affected recipe owners that their metrics will update; add moderator sanity checks |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Float arithmetic in metrics | Ingredient schema + metrics engine (Phase: Metrics) | BigDecimal-only unit test; comparison against hand-calculated spreadsheet |
| Circular sub-recipe references | Sub-recipe data model (Phase: Recipes) | Test: assert inserting a circular reference is rejected at DB layer |
| Unit conversion fallback to wrong density | Unit conversion service (Phase: Ingredient Library) | Test: add ingredient with no conversion row; assert warning is returned, not wrong value |
| Nutrient unit mismatch (CIQUAL + USDA) | Data seeder (Phase: Ingredient Library) | Cross-source sanity test: chicken breast nutrients from both sources within 15% |
| ODbL share-alike violation | Ingredient library data model + routing (Phase: Ingredient Library) | Legal review documented; no unauthenticated bulk endpoint |
| Allergen may-contain propagation | Allergen roll-up in metrics engine (Phase: Metrics) | Test: sub-recipe trace allergen propagates to parent as "may contain" |
| Working draft state divergence | Recipe versioning data model (Phase: Recipes) | Test: Save → Recall → Save → assert version count and draft state |
| Baker's percentage with sub-recipe flour | Metrics engine — professional extras (Phase: Metrics) | Test: poolish recipe asserts correct overall hydration |
| AI agent invalid edit writes | AI agent tool layer (Phase: AI Agent) | Test: agent tool call with quantity = -10g is rejected before hitting the DB |
| Moderation pipeline gaps | Ingredient submission workflow (Phase: Ingredient Library) | Test: promotion blocked when allergen fields are null |
| Cooking loss inconsistency | Metrics engine — yield/shrinkage (Phase: Metrics) | Test: yield_factor = 0.7 produces consistent weight, cost, and nutrition |
| Multi-language search collision | Ingredient name schema (Phase: Ingredient Library) | Test: searching "basilic" and "βασιλικός" both return the same ingredient_id |

---

## Sources

- EU Regulation 1169/2011, Annex II — 14 mandatory allergens definition (official EU legislation)
- Bird & Bird, "EU to harmonise May Contain allergen labels — new rules expected Q4 2027" (2026) — upcoming regulatory change
- GS1 EU, GDSN Implementation Guidelines for EU Regulation 1169/2011, v2.7 — structured allergen field standards
- ANSES CIQUAL 2025 database documentation — nutrient units and expression conventions
- USDA FoodData Central Foundation Foods Documentation — food_portion data coverage and limitations
- Open Database License (ODbL) 1.0, SPDX — share-alike trigger conditions
- Open Food Facts Knowledge Base — API and data reuse conditions
- FDA Guidance for Industry: Guide for Developing and Using Databases for Nutrition Labeling — rounding rule precision requirements
- SweetWARE nutraCoster, "Limitations of Nutritional Facts Labels" — rounding accumulation error example
- FAO/INFOODS, "Guidelines for Converting Units, Denominators and Expressions" — canonical unit conventions for food composition data
- Baker's Percentage, Wikipedia and King Arthur Baking — preferment/poolish flour attribution rules
- Galley Solutions Help Center, "How to account for meat shrinkage" — cooking loss in nutrition vs. cost calculation
- Recipal, "Accounting for Moisture Loss in Nutrition Analysis" — yield factor application to nutrient concentration
- Arcade.dev, "How to Build SQL Tools for AI Agents" — write-tool scoping and validation for LLM agents
- Open Food Facts Wiki, "Internationalization/Multilingual products" — multi-language ingredient name challenges
- Mealie GitHub Discussion #4219, "Predefined multi-lingual names for ingredients" — collision and deduplication patterns

---
*Pitfalls research for: twosides — professional recipe management platform*
*Researched: 2026-05-16*
