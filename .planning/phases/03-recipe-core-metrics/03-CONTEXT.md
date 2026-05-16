# Phase 3: Recipe Core & Metrics - Context

**Gathered:** 2026-05-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 3 delivers structured, versioned recipes and the full metrics engine —
the project's core value. It provides:

- **Structured recipes** — ingredient lines in any unit (weight, volume, count),
  ordered preparation steps, yield, portion size, time, difficulty, cuisine,
  tags, a hero image and step images, and free-text chef notes. Ingredient lines
  and steps are organized into named sections.
- **Per-line prep data** — each ingredient line can carry a prep action and a
  yield/loss percentage (RECIPE-13).
- **Nested sub-recipes** — a recipe can include another recipe as a component
  pinned to a specific version; circular references are rejected.
- **Versioning + working-draft layer** — edits accumulate in a mutable working
  draft; Save commits an immutable version; Recall undoes the last applied edit;
  past versions can be viewed and compared.
- **The metrics engine** — nutrition (per portion / per 100 g), cost per portion
  and total, food cost %, yield & scaling, cooking loss/shrinkage, baker's
  percentages + hydration, calorie/nutrient density — all rolled up correctly
  through nested sub-recipes, computed with exact decimal math.
- **Allergen derivation** — a recipe's allergens derived from its ingredients
  using the EU 14-allergen model, preserving contains / may-contain, rolled up
  through sub-recipes.
- **Recipe list with search & filter** — by tag, cuisine, allergen, ingredient,
  difficulty, and time.

It does NOT build: recipe tests / trial runs (Phase 4), the AI agent (Phase 5),
publishing or the public library (Phase 6 — recipes are private in Phase 3), or
ingredient moderation (Phase 7). The metrics catalogue is the MVP set only — the
frozen-dessert (gelato/sorbet PAC/POD/overrun) engine is explicitly post-MVP,
though the ingredient schema already reserves its fields.

</domain>

<decisions>
## Implementation Decisions

### Recipe builder & content entry

- **Single-page builder** — ingredient lines, preparation steps, recipe
  metadata, and the live metrics panel all live on one editing screen with
  collapsible sections. The recipe is one living document; metrics update as the
  chef edits.
- **Ingredient lines via inline search-as-you-type** — an empty line row where
  the chef types to search the Phase 2 ingredient library (reusing the existing
  live debounced search), picks a result, and sets quantity + unit inline.
- **Quick-create for missing ingredients** — if the ingredient isn't found, a
  quick-create shortcut creates a private ingredient without leaving the recipe
  builder.
- **Preparation steps are ordered text blocks grouped into named sections** —
  e.g. "Dough", "Filling", "Assembly"; each section is an ordered list of steps.
- **Ingredient lines are grouped into the same named sections as steps** — the
  "Dough" section has its own ingredient lines and its own steps. This makes
  per-section sub-totals possible and matches how professional recipes are
  written.
- **Per-line prep & loss** — each ingredient line carries a **free-text prep
  note** (e.g. "peeled & diced") and an **optional numeric yield/loss %**. The
  numeric loss % is the structured field that drives the cooking-loss metric.
- **Images** — a single **optional hero image** for the recipe, plus an
  **optional image attached to each individual step**.
- **Cuisine** is chosen from a **seeded, expandable list** (Greek, Italian,
  French, …) so it stays filterable. **Tags are free-form** with autocomplete
  suggesting existing tags.
- **Difficulty is a named-tier enum** — Easy / Medium / Hard / Expert.

### Versioning & working draft

- **Edits auto-persist to the working draft** — the builder screen *is* the
  working draft; every change is saved to the draft as the chef edits
  (debounced). No explicit "apply" or "save draft" step. Phase 5 AI edits will
  land in the draft the same way.
- **Recall undoes one logical action at a time** — a logical action is a
  discrete edit (add/remove a line, change a quantity, edit a step, reorder a
  section), not a single field keystroke and not a full discard. This matches
  how a chef — and the future AI agent — think about an "edit".
- **Version comparison is a side-by-side diff of two chosen versions** — the
  chef picks any two versions and sees what changed across ingredient lines,
  steps, metadata, and metrics.
- **Versions are auto-numbered (v1, v2, v3 …)**; each Save can carry an
  **optional short "what changed" note**.
- **Creating a recipe commits v1 immediately** — a brand-new recipe is committed
  as v1 on creation; every later Save adds v2, v3, …. (Note: this differs from a
  "draft-only until first Save" model — creation itself produces v1.)
- **Duplicate (RECIPE-09) creates a fresh, independent recipe** seeded from the
  source recipe's current version. The duplicate has its **own history starting
  at its own v1**, does **not** carry over the source's version history, and
  keeps **no lineage link** back to the source.

### Metrics engine — presentation & behavior

- **Sticky side panel** — the metrics panel sits alongside the builder, always
  visible on desktop, recomputing live as the chef edits. On mobile it collapses
  to an expandable summary.
- **Nutrition: a toggle switches between per-portion and per-100 g** (METRIC-01
  requires both); one mental model is shown at a time to keep the panel
  uncluttered.
- **Food cost %** — a **selling-price-per-portion field sits inline in the
  metrics panel** next to food cost %, which recomputes live as it is typed.
- **Missing data → partial metric + gap flag** — when an ingredient line lacks
  needed data (no recorded price, no nutrition values), the metric is computed
  from what is available and a clear "incomplete" indicator lists which
  ingredient lines are missing data. Metrics are never silently wrong and never
  fully hidden.
- **Baker's percentages auto-offered** — when the recipe contains a
  flour-category ingredient, the panel offers the baker's % / hydration section;
  the chef confirms which line(s) are the 100% flour base.
- **Multiple flour lines can sum to the 100% base** — the chef may mark several
  ingredient lines as flour (bread flour + whole wheat + rye); their combined
  weight is the 100% baker's-percentage base.
- All metric arithmetic uses **`brick/math` + `DECIMAL` columns** (METRIC-09);
  every ingredient line quantity is **normalized to grams** via the Phase 2
  unit-conversion infrastructure (METRIC-10).

### Sub-recipes & scaling

- **Sub-recipes are added through the same inline search row** that finds
  ingredients — the search also surfaces the chef's own recipes; picking one
  adds it as a sub-recipe line within a section. One unified "add a component"
  gesture.
- **A sub-recipe line's quantity is a weight drawn from the component's yield** —
  the chef enters a gram weight; that portion's metrics scale proportionally
  from the sub-recipe's rolled-up metrics (everything already normalizes to
  grams).
- **Version pin holds; an "update available" cue is shown** — a sub-recipe
  reference stays on its pinned version (VERSION-06); when a newer version of
  the component exists, the parent shows an update cue and the chef updates the
  pin manually. A sub-recipe change never silently alters the parent's metrics.
- **Scaling and portion-count changes are view-only what-ifs by default** —
  adjusting scale or portion count instantly recomputes quantities and metrics
  on screen but does **not** alter the saved recipe (RECIPE-08 requires portion
  changes without a new version). An **explicit action** lets the chef commit a
  scaled version as a draft edit.

### Recipe list & search

- **Visual cards with hero image** — the recipe list is a grid of recipe cards,
  not dense rows. Recipes are visual and fewer than ingredients, and
  presentable/share-friendly presentation is wanted from the start.
- **Each card shows rich at-a-glance info** — hero image, name, cuisine, total
  time, difficulty, cost per portion, calories per portion, and allergen icons.
- **Live name search + a collapsible filter panel** — live as-you-type name
  search is always visible; the six mandated filters (tag, cuisine, allergen,
  ingredient, difficulty, time) live in a collapsible panel/sheet with an
  active-filter count.
- **Default sort: recently updated first** — a working chef returns to recipes
  they are actively developing.

### Claude's Discretion

- The **circular-reference rejection** error message wording and exactly where
  it surfaces (RECIPE-06) — clear, actionable error.
- **Allergen roll-up display** in the metrics panel — how contains vs may-contain
  states are shown (ALLG-01/02/03).
- **Calorie / nutrient-density** metric presentation (METRIC-07).
- The **seeded cuisine starter list** — exact cuisine names and initial set.
- **Cooking-loss / shrinkage** metric details beyond deriving from per-line
  yield/loss % (METRIC-05) — e.g. whether a recipe-level finished-weight override
  is offered.
- **Debounce timings**, search ranking, list pagination/virtualization.
- The **quick-create-ingredient** UX detail (modal vs inline expansion) inside
  the recipe builder.
- Builder section management UX (adding/renaming/reordering sections), drag
  interactions, per-step image upload mechanics.
- Data model / schema design for recipes, versions, working drafts, lines,
  steps, sections, and sub-recipe references — the planner and researcher decide
  table structure within the decisions above.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — recipe domain, versioning, metrics
- `Project.md` (repo root) — **§3.2 Recipes** (structured data, nested
  sub-recipes with roll-up, flexible units, versioning, visibility), **§3.3
  Working Draft vs Saved Versions** (the authoritative Save / Recall / draft
  semantics — Save commits a version, Recall is step-by-step undo not full
  discard), **§5 Recipe Metrics** (the MVP metrics catalogue: nutrition, cost
  per portion, yield & scaling, allergens/dietary, time/difficulty, food cost %,
  cooking loss/shrinkage, baker's percentages + hydration, nutrient density,
  batch scaling — and the explicitly post-MVP frozen-dessert section), **§6
  Technical Stack / Unit Conversion Approach** (`brick/math`, DECIMAL columns,
  normalize-to-grams converter), **§9 Key Decisions**.
- `.planning/PROJECT.md` — GSD context summary; Key Decisions table (recipe
  structure, nested sub-recipes, versioning/working-draft, units, precision).
- `.planning/REQUIREMENTS.md` — definitions for this phase's requirements:
  **RECIPE-01 … RECIPE-13**, **VERSION-01 … VERSION-06**, **METRIC-01 …
  METRIC-10**, **ALLG-01 … ALLG-03**.

### Roadmap
- `.planning/ROADMAP.md` §"Phase 3: Recipe Core & Metrics" — goal, the 6 success
  criteria, dependency on Phase 2. Note the roadmap rationale: recipe core and
  the metrics engine are deliberately bundled because metrics depend on the
  draft/version structures.

### Phase 2 foundations this phase builds on
- `.planning/phases/02-ingredient-library/02-CONTEXT.md` — the ingredient model
  (official + private ingredients, nutrition columns, allergens contains /
  may-contain, `ingredient_conversions`, per-user prices, verified flag), the
  live debounced ingredient search this phase's line picker reuses, and the
  compact-row + collapsible-filter library patterns.

### Phase 1 foundations
- `.planning/phases/01-foundation/01-CONTEXT.md` — role/permission model,
  units + allergens lookup tables, localization infra, warm-minimal design
  system.

### Codebase maps — existing patterns to follow
- `.planning/codebase/STRUCTURE.md` — directory layout (models, controllers
  grouped by feature, Inertia pages under `resources/js/pages/`, `app/Support/`
  for service classes).
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, FormRequest per
  action, shared rules in `app/Concerns/` traits, thin controllers returning
  `Inertia::render()` or redirects, shadcn/ui composition, `cn()` helper,
  Wayfinder routes (never hand-edit `@/actions/` `@/routes/`).
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `INTEGRATIONS.md`,
  `TESTING.md`, `CONCERNS.md` — Inertia v3 bridge, shared props, the Pest
  testing approach (every change programmatically tested).

### Compliance (external, no project file)
- **EU Regulation 1169/2011, Annex II** — the 14 mandatory allergens; recipe
  allergen derivation rolls these up from ingredients preserving the contains /
  may-contain states.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`Ingredient` model + ingredient tables** (`app/Models/Ingredient.php` and
  siblings) — nutrition columns (`energy_kcal`, `protein_g`, …, all
  `decimal:4`), `IngredientTranslation` (multi-language names with
  `nameFor(locale)`), `IngredientConversion` (per-ingredient unit→gram rows),
  `IngredientPrice` (per-user dated prices), `allergens()` belongsToMany with a
  `state` pivot (contains / may-contain). Recipe ingredient lines reference these.
- **`Unit` model + `units` table** — `name, symbol, type, base_factor`
  (`decimal:6`); intra-type unit math. Recipe lines pick units from here.
- **`PerGramCostCalculator`** (`app/Support/Ingredients/PerGramCostCalculator.php`)
  — existing stateless calculator normalizing a price to per-gram cost; the cost
  metric builds on this approach. New metric services belong in `app/Support/`.
- **Phase 2 live ingredient search** — the debounced as-you-type ingredient
  search (`IngredientController`) is reused directly by the recipe builder's
  inline line picker; it must also surface recipes for sub-recipe selection.
- **shadcn/ui primitives** (`resources/js/components/ui/`) — table, input,
  badge, select, dialog, sheet, skeleton, sonner, tooltip, popover — the
  builder, metrics panel, version diff, and recipe list compose from these.
- **`brick/math`** — already a dependency; use `BigDecimal` for all metric,
  scaling, and roll-up arithmetic.
- **`HandleInertiaRequests`** shares the user's `locale` — recipe and ingredient
  names render in the selected language with no new plumbing.
- **`AccountStatus` enum** (`app/Enums/`) — the pattern for the new difficulty
  enum.
- **spatie/laravel-permission** — installed; recipes are owner-scoped (private
  in Phase 3) via policies (`app/Policies/` already exists).

### Established Patterns
- **Models** via `php artisan make:model` with factories + seeders.
- **Validation** — a `FormRequest` per action; shared rules into `app/Concerns/`
  traits (`IngredientValidationRules` is the precedent).
- **Controllers** — thin, grouped by feature in a subdirectory
  (`app/Http/Controllers/Ingredients/`); return `Inertia::render(...)` or a
  redirect (Inertia mutations redirect, not JSON).
- **Service/calculator classes** live in `app/Support/<Feature>/` — the metrics
  engine should follow (`app/Support/Recipes/` or `app/Support/Metrics/`).
- **Routing** — server-driven Inertia, named routes, Wayfinder regeneration.
- **Testing** — Pest feature tests mirror `app/`; every change is tested. Metric
  math and sub-recipe roll-up need precision tests proving no float drift.

### Integration Points
- **`Ingredient`, `Unit`, `Allergen`, `IngredientConversion`, `IngredientPrice`
  tables** — recipe ingredient lines and the metrics engine read from these.
- **`User` model** — gains a relationship to owned recipes (and recipe drafts).
- **`routes/web.php`** — new routes for recipe CRUD, the builder, version
  history/compare, scaling, and the recipe list/search.
- **`resources/js/pages/recipes/`** — new Inertia pages: recipe list, the
  single-page builder, version compare.
- **Nav** — a "Recipes" entry is needed in the app shell, alongside the
  Phase 2 "Ingredients" entry.
- **`database/seeders/`** — a seeded cuisine list.

</code_context>

<specifics>
## Specific Ideas

- The recipe is a **living document** — the single-page builder, auto-persisting
  draft, and always-visible live metrics panel all express this: the chef edits
  and sees the consequences immediately.
- **Ingredient lines and steps share the same named sections** ("Dough",
  "Filling", "Assembly") because that is how professional recipes are actually
  written and structured — a section is a coherent sub-component of the dish.
- **The metrics are the product's value** — they stay in view (sticky panel) and
  appear even on recipe list cards (cost/portion, calories, allergens). When data
  is incomplete the app says so plainly rather than hiding or faking a number.
- **A sub-recipe change must never silently alter a parent recipe** — hence
  version pinning with a manual "update available" cue, not auto-follow.
- **Scaling is exploratory** — a chef tries "what if I make 3× this" without it
  becoming a permanent edit; committing a scaled version is a deliberate,
  explicit act.
- The Phase 5 AI agent will apply edits through the **same working-draft path**
  as manual edits — the "one logical action = one Recall step" model is chosen
  partly so AI edits are individually undoable.

</specifics>

<deferred>
## Deferred Ideas

- **Recipe tests / trial runs and structured experiments** (TEST-01…04) — Phase 4.
- **The per-recipe AI agent** (AI-01…07) — Phase 5; its edits will reuse this
  phase's working-draft + Recall mechanics.
- **Publishing & the public library** (PUB-01…04) — Phase 6; recipes are private
  in Phase 3.
- **Frozen-dessert balancing** (gelato/sorbet PAC/POD/overrun metrics) — post-MVP
  milestone; the ingredient schema already reserves the fields, no work here.
- **EU-format nutrition label export** (NUTR-01) and **printable production
  sheet** (PROD-01) — v2 requirements.
- **Cost data beyond manual entry** (COST-01) — v2; Phase 3 cost metrics use the
  Phase 2 user-entered ingredient prices.
- **Collaborative comments / annotations** (COLLAB-01) — v2.

</deferred>

---

*Phase: 03-recipe-core-metrics*
*Context gathered: 2026-05-16*
