# Phase 2: Ingredient Library - Context

**Gathered:** 2026-05-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 2 delivers the ingredient library that every recipe in Phase 3 will draw
from. It provides:

- An **official ingredient library** seeded by three independent, idempotent
  Artisan import commands — CIQUAL 2025 (primary), USDA FoodData Central
  (backfill), and Open Food Facts (Greek products + enrichment).
- Each ingredient carrying **nutrition values**, **EU 14-allergen** data,
  **unit-conversion data** (portion → grams), **multi-language names**
  (Greek + English minimum), and an **internal category**.
- **Search** of the library by name, returning results in Greek and English.
- A **dedicated detail page** per ingredient.
- **Private ingredient creation** — a user adds their own ingredient when the
  official library lacks one; it is visible only to its creator.
- **Per-user price recording** on any ingredient (official or private).
- An **ingredient verification** flag — a moderator/admin confirms an
  ingredient's stored data is correct.

It does NOT build: recipes, ingredient *lines*, the metrics engine, or recipe
allergen roll-up (all Phase 3); and it does NOT build the private-ingredient
**submission → moderator review → promotion** workflow (Phase 7). Phase 2
produces the verified flag and the moderator-verification action, but the
submit-for-inclusion queue is Phase 7.

</domain>

<decisions>
## Implementation Decisions

### Ingredient data model & taxonomy

- **One `ingredients` table** holds both official and private ingredients,
  distinguished by ownership: official ingredients have no owner; private
  ingredients belong to a `user_id`. Private ingredients are visible **only to
  their creator** (ROADMAP success criterion 4).
- **Category is required on every ingredient** — official imports included.
  Categories form a **nested 2-level tree** (category → subcategory), e.g.
  *Vegetables → Leafy greens*. The tree is **seeded with a sensible default
  set** and is **expandable later** (more categories added without a schema
  change). Claude defines the starter set during planning.
- **Nutrition, allergens, conversions** follow the model already decided in
  `Project.md` §6 — see Canonical References. Allergens reuse the **Phase 1
  `allergens` lookup table** (EU Reg. 1169/2011, 14 allergens) with two states
  per ingredient: **`contains`** and **`may contain`**. Unit conversions use a
  per-ingredient `ingredient_conversions`-style table layered on the **Phase 1
  `units` lookup table**; everything normalizes to **grams**.
- **Multi-language names** — every ingredient stores names in Greek and English
  at minimum; the schema must not hard-limit to two languages.
- **Reserved frozen-dessert fields** — the ingredient schema reserves the
  gelato/sorbet fields listed in `Project.md` §5 so the post-MVP frozen-dessert
  engine ships without a migration. They are unused in this phase.
- **Numeric precision** — all nutrition, conversion, and price values use
  `DECIMAL` columns and `brick/math`, never floats.

### Search & browse experience

- **Live as-you-type search** — results update as the user types (debounced).
- Search matches **both Greek and English** names; results are returned in both
  languages (ROADMAP success criterion 1).
- **Compact list rows** for results — each row shows the name (EL/EN), calories,
  and **allergen icons**. Density over visual cards — the library is large.
- **Filters at launch (three):**
  1. **Source** — official / my private ingredients
  2. **Allergen-free** — exclude ingredients containing a chosen allergen
  3. **Verified only** — show only verified ingredients
- No rich faceted/taxonomy filtering in this phase beyond the three above.

### Ingredient detail page

- Each ingredient has its **own dedicated route / detail page** (shareable, room
  to grow).
- The detail page shows the **full nutrition values**, **allergen data**
  (contains / may contain), **unit-conversion data**, the **category**, the
  **verified badge**, and the **Prices section** (see Pricing).

### Private ingredient creation

- The create flow offers **two starting points**: a **blank form**, or
  **duplicate an official ingredient** as a pre-filled template the user then
  edits (e.g. "my version of olive oil").
- **Minimum required to save:** **name** (at least one language) **+ category**.
  Everything else — nutrition, allergens, conversions, price — is **optional and
  enrichable later**. A chef can quickly stub an ingredient.
- **Allergens** are entered via the **full 14-allergen checklist**, each
  settable to **contains / may-contain / none** (same model as official
  ingredients). Optional.
- **Unit conversions** are entered as **optional conversion rows** — e.g.
  `1 cup → 240 g`, `1 piece → 30 g`. If none are added, only weight units
  (g/kg) work for that ingredient.
- A private ingredient is **editable and deletable** by its owner after
  creation.

### Ingredient pricing

- Price recording lives in a **Prices section on the ingredient detail page**
  (not a separate "My Prices" area).
- Prices are **per-user / private** — each user records and sees **only their
  own** prices, even on official ingredients. Prices are inherently personal and
  regional.
- **Full dated price history** — every recorded price is kept with its date.
  The most recent price feeds cost metrics (Phase 3); the user can see how a
  price moved over time.
- A price is expressed as an **amount for a quantity + unit** — e.g.
  `€4.20 for 500 g`, `€1.50 for 1 piece` — and the app normalizes it to a
  **per-gram cost** via the unit converter.
- **Currency** — defaults to **EUR**; other currencies are selectable per
  entry. Each price is stored in its recorded currency; **no automatic FX
  conversion** in this phase.

### Ingredient verification

- Ingredients carry a **global `verified` state** — verified once, for everyone
  (not per-user). The record stores **who verified it and when**.
- Verification applies to **both official and private ingredients**.
- Only **moderators and admins** can mark an ingredient verified. This reuses
  the role/permission system from Phase 1 (a `verify-ingredients` permission, or
  the existing `review-ingredients` permission — Claude's call during planning).
- A **re-import that changes an ingredient's stored data resets it to
  unverified** — verification asserts the *current* data is correct.
- In the UI, verified ingredients show a **badge** in search results and on the
  detail page, and the search offers a **"verified only" filter**.
- Scope note: this is a data-correctness check on existing ingredients. It is
  **distinct from Phase 7** (private-ingredient submission → moderator review →
  promotion to official). Phase 2 ships only the verified flag and the
  moderator verify action.

### Import pipeline (CIQUAL / USDA FDC / Open Food Facts)

- **Three separate Artisan commands**, one per source, each runnable
  **independently and idempotently** (ROADMAP success criterion 3) — re-running
  a command updates rather than duplicates.
- **Source order of authority** (from `Project.md` §6): **CIQUAL is the primary
  seed**, **USDA FDC backfills** ingredients/data CIQUAL lacks (and supplies
  `food_portion` gram-weight conversion data), **Open Food Facts enriches**
  (Greek names, EU allergen tags) and adds Greek-market products.
- **Source data acquisition — mixed:** CIQUAL's small XML is **bundled in the
  repo**; the large USDA and OFF datasets are **fetched/downloaded on demand**
  by their commands.
- **Seed scope — fully populated library:** this phase **runs all three full
  imports** so the official library ships complete (thousands of ingredients),
  not just a sample. Phase 3 develops against a real, populated library.
- **Greek-name strategy:** Greek names come from **Open Food Facts
  cross-reference** where a product matches; ingredients with no Greek match
  **keep their English name as a fallback** and can be translated later. No
  machine-translation dependency.
- **Incomplete records are imported, not skipped** — an ingredient missing some
  nutrients, a Greek name, or conversion data is still imported. Completeness
  improves over time; the library is bigger and useful sooner.
- Newly imported ingredients are **unverified by default**.

### Claude's Discretion

- The **starter category tree** — exact category and subcategory names and the
  initial set (within the "nested 2-level, default set, expandable" decision).
- Which **nutrient set** to store and display — CIQUAL exposes ~74 nutrients;
  Claude decides the stored schema and the curated panel shown on the detail
  page, ensuring it covers INGR-03 (calories, macros, sugars, sodium, fiber,
  micronutrients).
- The exact **permission name** for verification (`verify-ingredients` vs reuse
  `review-ingredients`).
- **Cross-source matching strategy** — how USDA backfill and OFF enrichment
  match against existing CIQUAL ingredients to avoid duplicates while staying
  idempotent.
- **Debounce timing**, search ranking, and pagination/virtualization for the
  results list.
- Import-command ergonomics — progress reporting, batching, transaction
  handling, and how on-demand USDA/OFF downloads are triggered and cached.
- The **duplicate-from-official** UX (which fields copy, how the new private
  ingredient is linked, if at all, to its source).
- Detail-page layout and the price-history presentation (table vs chart).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — ingredient domain, data sources, compliance
- `Project.md` (repo root) — **§3.1 Ingredients** (official library, private
  ingredients, raw/prepared forms, multi-language), **§5 Recipe Metrics** (the
  reserved frozen-dessert ingredient fields — `total_solids_%`, `fat_%`,
  `msnf_%`, `pac_coefficient`, `pod_coefficient`, `de_value`, `brix`,
  `ingredient_class`, etc.), **§6 Data Sources / Allergens & Taxonomy / Unit
  Conversion Approach** (the authoritative source for CIQUAL/USDA/OFF licensing
  and roles, the `ingredient_conversions` model, the FoodEx2 hook), **§9 Key
  Decisions**.
- `.planning/PROJECT.md` — GSD context summary; Key Decisions table (ingredient
  sourcing, allergens, taxonomy, conversion data, precision).
- `.planning/REQUIREMENTS.md` — definitions for this phase's requirements:
  **INGR-01 … INGR-08**. (INGR-09/10/11 — submission/moderation — are Phase 7.)

### Roadmap
- `.planning/ROADMAP.md` §"Phase 2: Ingredient Library" — goal, the 5 success
  criteria, dependency on Phase 1.

### Phase 1 foundations this phase builds on
- `.planning/phases/01-foundation/01-CONTEXT.md` — role/permission model
  (spatie, named permissions incl. `review-ingredients`), the unit + allergen
  lookup tables, localization infra, the `/admin` section, the warm-minimal
  design system.

### Codebase maps — existing patterns to follow
- `.planning/codebase/STRUCTURE.md` — directory layout, where models /
  controllers / Inertia pages / commands / seeders live.
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, FormRequest per
  action, traits in `app/Concerns/`, shadcn/ui composition, `cn()` helper,
  Wayfinder routes.
- `.planning/codebase/ARCHITECTURE.md` — Inertia v3 bridge, Fortify auth,
  `HandleInertiaRequests` shared props (locale is already shared).
- `.planning/codebase/INTEGRATIONS.md`, `.planning/codebase/STACK.md`,
  `.planning/codebase/TESTING.md`, `.planning/codebase/CONCERNS.md` — supporting
  detail for stack, external integrations, and the Pest testing approach.

### Compliance (external, no project file)
- **EU Regulation 1169/2011, Annex II** — the 14 mandatory food allergens; the
  rows of the Phase 1 `allergens` lookup table this phase links ingredients to.
- **Data licensing** — CIQUAL CC-BY 4.0 / Etalab 2.0 (attribution required:
  *"Anses. 2025. Ciqual French food composition table 2025"*), USDA FDC CC0,
  Open Food Facts ODbL. See `Project.md` §6 for the full licensing notes.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`Unit` model + `units` table** (`app/Models/Unit.php`, migration
  `2026_05_16_024902`) — columns `name, symbol, type, base_factor`
  (`decimal:6`). The base for intra-type unit math; ingredient conversions layer
  on top of it.
- **`Allergen` model + `allergens` table** (`app/Models/Allergen.php`, migration
  `2026_05_16_024914`) — columns `name, slug, note`; `slug` is the unique
  canonical key. Ingredients attach to these via a contains / may-contain pivot.
- **spatie/laravel-permission** — roles (User/Moderator/Admin) and named
  permissions are installed and seeded; the `review-ingredients` permission
  already exists for moderators. Gate the verify action on a permission.
- **Idempotent seeder pattern** — `AllergenSeeder` / `UnitSeeder` use
  `firstOrCreate` on a stable key. The import commands should follow the same
  upsert-on-stable-key approach for idempotency.
- **shadcn/ui primitives** (`resources/js/components/ui/`) — table, input,
  badge, select, dialog, skeleton, sonner, tooltip, etc. — search list, filters,
  the create form, and the detail page compose from these.
- **`brick/math`** — already a dependency; use `BigDecimal` for all nutrition,
  conversion, and price arithmetic.
- **`HandleInertiaRequests`** already shares the user's `locale` — the frontend
  can render ingredient names in the selected language without new plumbing.

### Established Patterns
- **Models** created via `php artisan make:model` with factories + seeders.
- **Validation** — a `FormRequest` per action; shared rules into
  `app/Concerns/` traits.
- **Controllers** — thin; group by feature in a subdirectory; return
  `Inertia::render(...)` or a redirect (Inertia mutations redirect, not JSON —
  see Phase 1 STATE decisions).
- **Routing** — server-driven Inertia; named routes + `route()`; Wayfinder
  regenerates `@/actions/` and `@/routes/` — never hand-edit.
- **Artisan commands** — none exist yet (`app/Console/Commands/` is empty);
  create via `php artisan make:command`.
- **Testing** — Pest feature tests mirror `app/` structure; every change is
  programmatically tested (project rule). Import commands need tests that prove
  idempotency.

### Integration Points
- **`units` / `allergens` tables** — new ingredient tables reference these.
- **`User` model** — gains a relationship to private ingredients and to price
  records.
- **`database/seeders/DatabaseSeeder.php`** — seeds the new category tree
  (default set). The bulk source imports run as commands, not seeders.
- **`routes/web.php`** — new routes for ingredient search, the detail page, and
  private-ingredient CRUD; the verify action behind a permission gate.
- **`resources/js/pages/`** — new Inertia pages for the library/search and the
  ingredient detail page.
- **Nav** — an "Ingredients" entry is needed in the app shell.

</code_context>

<specifics>
## Specific Ideas

- The library must feel like a **reference tool used constantly while building
  recipes** — hence live search and dense list rows over decorative cards.
- "Duplicate an official ingredient" exists because chefs often want a slight
  variant of a known ingredient (a specific brand of olive oil, a house blend)
  rather than entering everything from scratch.
- The **verified** flag is a trust signal: imported data is best-effort and
  partial; a moderator vouching that "all the stored data is correct" turns a
  raw imported row into a trusted one. It must reset when a re-import changes
  the data.

</specifics>

<deferred>
## Deferred Ideas

- **Private-ingredient submission → moderator review → promotion** to the
  official library (INGR-09, INGR-10, INGR-11) — this is **Phase 7: Ingredient
  Moderation**, which already depends on Phase 2. Verification (Phase 2) and the
  submission/review queue (Phase 7) are deliberately separate.
- **A dedicated "My Prices" overview page** — considered for pricing; deferred.
  Phase 2 records prices on the ingredient detail page only.
- **Rich faceted filtering** (by taxonomy category, dietary tags) beyond the
  three launch filters — a later enhancement.
- **Machine-translation of ingredient names** to Greek and broader manual Greek
  curation — Phase 2 uses OFF cross-reference with English fallback only.
- **Automatic currency / FX conversion** of prices — prices store their recorded
  currency as-is.
- **FoodEx2 classification codes** (TAX-01) — a v2 requirement; the schema keeps
  an optional `foodex2_code` hook but no FoodEx2 work happens here.

</deferred>

---

*Phase: 02-ingredient-library*
*Context gathered: 2026-05-16*
