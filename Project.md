# Project: twosides

> A recipe platform built for professional chefs — and approachable for amateurs.
> Recipes are living documents: versioned, measured, tested, and continuously
> improved with the help of an integrated AI agent.

**Status:** Greenfield (brand new) · **Date started:** 2026-05-16

---

## 1. Vision

`twosides` is a web application for creating, measuring, testing, and refining
recipes. It treats a recipe not as static text but as structured, versioned data
that can be analyzed for nutrition, cost, yield, and other professional metrics.

An integrated, conversational AI agent works alongside the chef — answering
questions about a recipe, proposing improvements, suggesting and reading test
results, and (with the user's approval) editing the recipe directly through
Laravel tools.

The product is specialized for **professional cooking and pastry chefs**, while
remaining usable by **amateur home cooks**.

---

## 2. Target Users

- **Primary:** Professional chefs and pastry chefs who need deep, accurate
  insight into their recipes (cost, yield, nutrition, consistency).
- **Secondary:** Amateur / home cooks who want the same tools in a friendlier form.
- **Access:** Responsive web app — one app that works well on desktop and mobile
  browsers.

### Roles

| Role        | Capabilities |
|-------------|--------------|
| **User**    | Create/manage recipes, ingredients, tests; use the AI agent; publish recipes. |
| **Moderator** | Review ingredient submissions; search/verify and promote them to the official library. |
| **Admin**   | Full administration, user management, and all moderator capabilities. |

---

## 3. Core Concepts (Domain Model)

### 3.1 Ingredients

- An **official ingredient library**, seeded from a curated, vetted dataset and
  enriched from an external food/nutrition API (hybrid approach).
- Each user can add their own **private ingredients** when one doesn't exist.
- A user may **submit a private ingredient for inclusion** in the official
  library → it goes through **moderation and verification** (search + review by a
  moderator) before being promoted.
- Ingredients support **both raw and prepared/processed** forms.
- Every ingredient carries **nutrition values** and any additional data needed
  to compute recipe metrics (allergens, cost reference, density for unit
  conversion, etc.).
- **Multi-language:** ingredient names carry translations / international and
  native-language variants from the start. The library includes everyday
  ingredients as well as those used in professional cuisines worldwide.

### 3.2 Recipes

- A recipe is **structured data**: ingredient lines (with quantities/units),
  preparation steps, yield, and metadata.
- **Nested sub-recipes:** a recipe can include another recipe as a component
  (e.g. a stock, sauce, dough, or pastry cream). Metrics **roll up** from
  sub-recipe to parent recipe.
- **Flexible units per ingredient line:** each line can use whatever unit suits
  it (weight, volume, count); the app converts as needed to compute metrics.
- **Versioning:** every recipe keeps a full version history. Versions can be
  created by the user manually or by the AI agent.
- **Visibility:** recipes are **private by default**; a user can choose to
  **publish** specific recipes to a public library.

### 3.3 Working Draft vs. Saved Versions

A recipe has three layers of state:

1. **Saved version history** — the committed, immutable timeline of versions.
2. **Working draft** — a live, editable layer where changes (from the user or
   the AI) accumulate before being committed.
3. The **current/active version** the user cooks from.

- **Save** → commits the working draft as a new version in the history.
- **Recall** → **undo** the last applied edit on the working draft (step-by-step
  undo, not a full discard).

### 3.4 Recipe Tests (Trials & Experiments)

A "test" is how a chef validates a recipe version in the real kitchen. The app
supports **both**:

- **Trial runs** — free-form: the chef cooks a recipe version and logs the result.
- **Structured experiments** — A/B-style variations with a defined hypothesis
  (e.g. change one ingredient or technique) and a recorded outcome.

Each test captures feedback:

- **Tasting notes** (free-form text)
- **Photos** of the process / finished dish
- **Structured ratings** (scored dimensions — taste, texture, appearance, etc.)
- **What changed vs. expected** — recorded deviations: what the chef altered and
  the actual vs. expected outcome.

Test feedback feeds back into the AI agent so it can suggest improvements.

---

## 4. The AI Agent

The AI agent is a **conversational chat attached to a recipe**. It can read the
recipe, the user's notes on it, and the feedback from its tests.

### What the agent does

- Answers questions about a recipe (e.g. *"can I lower the sugar in this?"*).
- Suggests **tests/experiments** to run.
- Reads **test feedback** and suggests **improvements**.
- With user approval, **edits the recipe** through Laravel tools.

### Interaction flow

1. The user asks a question in the recipe's AI chat.
2. The agent answers and may offer an **Apply** action.
3. If the user accepts, the agent **applies the edit** → the change lands in the
   recipe's **working draft**.
4. The user then either:
   - **Recall** → undo the last applied edit, or
   - **Save** → commit the working draft as a new recipe **version**.
5. The agent can also be asked to **create a variant** — e.g. *"make a version
   with butter swapped for olive oil."* The variant also lands as a **working
   draft** that the user must Save to keep.

### Provider

- The AI layer is **provider-agnostic / configurable** — the underlying model
  provider can be swapped via an adapter abstraction.

---

## 5. Recipe Metrics

The app calculates metrics so chefs gain **deep, quantitative understanding** of
each recipe. The list is **extensible** — the goal is "as many useful metrics as
we can produce."

**Confirmed for MVP:**

- **Nutrition** — calories, macros, sugars, sodium, fiber, micronutrients
  (per portion and per standard weight).
- **Cost per portion** — ingredient cost → total recipe cost and cost/serving.
- **Yield & scaling** — servings/yield, portion size, scale up/down.
- **Allergens & dietary tags** — allergen flags and classifications
  (vegan, vegetarian, gluten-free, halal, kosher, etc.).
- **Time & difficulty** — prep, cook, total time, and difficulty rating.

**Additional professional metrics to support (chef & pastry oriented):**

- Food cost percentage (cost vs. selling price).
- Cooking loss / shrinkage (raw vs. cooked / finished weight).
- Baker's percentages and hydration ratio (pastry/baking).
- Calorie / nutrient density.
- Batch scaling for service.

> All metrics roll up correctly through nested sub-recipes.

### Frozen Desserts — Gelato / Ice Cream / Sorbet (planned specialization, post-MVP)

Frozen desserts are a distinct discipline with their own **mix-balancing
science**. This is **documented now but scheduled for a later milestone** — not
part of the MVP. It is recorded here so the data model can accommodate it
without a painful migration later.

**Specialized metrics to compute** (each shown against an editable per-type
target window — gelato / ice cream / sorbet / sherbet / soft serve):

- **Composition** — total solids %, fat %, sugars %, MSNF % (milk solids
  non-fat), other solids %, water %. Weighted sums across ingredients.
- **PAC** (anti-freezing power / freezing-point depression) — `Σ(solids ×
  pac_coefficient)`, normalized per 100 g of mix. Drives hardness/scoopability.
  Targets ≈ 22–28 (gelato), 24–30 (ice cream), 28–36 (sorbet).
- **POD** (sweetening power) — `Σ(sugar × pod_coefficient)` per 100 g mix. Lets
  the maker tune perceived sweetness independently of hardness.
- **Predicted serving temperature** ≈ `−PAC/2` (dairy) or `−PAC/2.5` (sorbet),
  with a **scoopability verdict** (too hard / balanced / too soft).
- **Overrun** — stored as a per-recipe *target* (whipped-in air %); used for
  finished-volume yield and cost-per-scoop. A measured value can be entered.
- **Brix** — dissolved-solids %, primarily for sorbet sugar balancing.
- **Sandiness warning** — flag when MSNF exceeds ~12% (lactose crystallization).

**Implied ingredient-level data fields** (the schema should reserve these so the
feature ships later without a migration):

- `total_solids_%`, `fat_%`, `msnf_%`, `sugar_%`, `other_solids_%`, `water_%`
- `pac_coefficient`, `pod_coefficient` — per sugar/solute (sucrose = 100 baseline)
- `de_value` — Dextrose Equivalent, for glucose syrups / maltodextrins
- `brix` — for fruits and purées
- `ingredient_class` enum — `sugar` · `dairy` · `fat` · `fruit` · `stabilizer` ·
  `emulsifier` · `cocoa` · `alcohol` · `egg` — determines which metrics apply

A seeded **sugar reference table** holds the PAC/POD coefficients (sucrose
100/100, dextrose 190/70, fructose 190/170, invert 190/130, glucose syrups by
DE, lactose 100/15, etc.). Coefficients are **editable defaults** — professional
sources (Corvitto, Caviezel, Boiron) disagree slightly, so chefs can adjust them.
A **per-product-type target table** drives the balance flags.

---

## 6. Technical Stack

The project is built on the Laravel ecosystem already scaffolded in the repo:

- **Backend:** PHP 8.3, Laravel 13
- **Frontend:** Inertia v3 + React 19, Tailwind CSS v4
- **Auth:** Laravel Fortify
- **Routing/TS:** Laravel Wayfinder
- **AI tooling:** Laravel MCP (the AI agent edits recipes via Laravel tools)
- **Testing:** Pest v4 / PHPUnit v12
- **Tooling:** Vite, Pint, ESLint, Prettier
- **Local environment:** Laravel Herd (served at `https://twosides.test`)
- **Precision math:** `brick/math` — `BigDecimal` / `BigRational` for all
  quantity, scaling, and metric arithmetic (never PHP `float`).

### Data Sources (researched & decided)

The hybrid ingredient model is built on data we can legally store in our own DB.
The project is **Greece-based**, so the strategy is **Europe-first**.

- **CIQUAL 2025 (ANSES, France) — primary seed.** Dual-licensed CC-BY 4.0 /
  Etalab 2.0: storage, caching, and commercial use permitted with attribution
  (`"Anses. 2025. Ciqual French food composition table 2025"`). 3,484 foods ×
  74 nutrients, strong Mediterranean coverage, clean XML for a Laravel seeder.
  *Gap:* food names are French → a **Greek-name translation layer** is required
  (nutrient data itself is language-neutral).
- **USDA FoodData Central — backfill.** Public domain (CC0). Fills ingredient
  gaps CIQUAL lacks; also the source of `food_portion` gram-weight data for unit
  conversion. Secondary to CIQUAL, not the primary seed.
- **Open Food Facts — enrichment & Greek products.** Free, no key, ODbL-licensed.
  ~8,500 Greece-tagged products and ~2,000 Greek brands, **Greek-language
  ingredient names**, and **EU allergen tags** (`allergens` / `traces`). *Caveat:*
  ODbL share-alike applies only if we publish an enhanced database as a product —
  internal storage and in-app display are fine.
- **HelTH (Hellenic Food Thesaurus, AUA) — stretch goal.** ~4,000 Greek-market
  products with allergen data, but academic-only with no open license. Action:
  email Agricultural University of Athens about a data-sharing agreement; do not
  block on it.
- **FatSecret Premier (paid) — optional later upgrade.** Best global coverage;
  requires an explicit storage clause in the contract. Deferred.
- **Rejected:** EuroFIR (paid membership), BLS Germany (paid license),
  Spoonacular / Edamam / Nutritionix API / CalorieNinjas (terms forbid building a
  persistent local copy — breaks the seeded-library model).

### Allergens & Taxonomy (EU compliance)

- **Allergens** are modelled on **EU Regulation 1169/2011, Annex II — the 14
  mandatory allergens** (cereals containing gluten, crustaceans, eggs, fish,
  peanuts, soybeans, milk, tree nuts, celery, mustard, sesame, sulphites, lupin,
  molluscs). Stored as a fixed lookup table, with two relationship states per
  ingredient/recipe: **"contains"** and **"may contain"** (precautionary/traces).
  Tree nuts are a parent category with specific nuts as children. This makes the
  app EU-compliant by construction and maps onto Open Food Facts' tags.
- **Ingredient taxonomy** is a simple internal tree (FoodEx2 is too heavyweight
  as the primary tree). Each ingredient carries an optional **`foodex2_code`**
  attribute for future EU data interoperability.

### Unit Conversion Approach (researched & decided)

- **Density / portion data:** USDA FoodData Central `food_portion` data (gram
  weight per household measure per food — e.g. *1 cup flour, sifted → 120 g*).
  Same source as the nutrition seed. A hand-curated **override table** layers on
  top (~80% USDA coverage is the realistic baseline).
- **No general unit-converter package.** Standard-unit math (g↔kg, ml↔l) is
  trivial; the hard part — ingredient-specific volume/count↔weight — is data, not
  code. We build a **small custom converter service**.
- **Conversion data model:** a `units` table (with `type` + intra-type base
  factor) + a per-ingredient `ingredient_conversions` table (`ingredient_id`,
  `from_amount`, `from_unit`, `gram_weight`, `modifier`, `source`) — USDA rows map
  1:1 — + category-level density fallback. **Everything normalizes to grams**, so
  sub-recipe roll-up and baker's percentages compose for free.

---

## 7. Design & UX

High-level design direction. Pixel-level visual spec (exact palette, spacing,
component anatomy) is deliberately deferred to a dedicated UI spec per frontend
phase — this section captures the *direction*, not the detailed design.

### Visual aesthetic

- **Warm minimal hybrid** — a clean, minimal, content-first structure paired with
  **warm, food-forward photography** and a single accent color.
- The tone is **serious and professional** — it must feel like a credible tool
  for working chefs, not a casual hobby app — while staying inviting and
  appetizing through imagery and warmth.
- The design must not paint itself into a corner: `twosides` is intended to grow
  into a **social platform** (see below), so layouts, recipe pages, and imagery
  should be presentable and share-friendly from the start.

### Component approach

- **shadcn/ui on Tailwind CSS v4** — copy-in, Radix-based, accessible components,
  customized to the warm-minimal aesthetic. No heavyweight component framework.
- Consistent use of design tokens so theming and future restyling stay cheap.

### Color mode

- **Light and dark themes**, with a **user-toggleable** preference that persists.
- Build on design tokens / CSS variables from day one so both modes are
  first-class rather than retrofitted.

### UX principles

- **Content-first** — recipes, ingredients, and metrics are the focus; chrome
  stays out of the way.
- **Metrics must be legible** — numbers, target ranges, and balance flags are
  presented clearly (the app's value is quantitative insight).
- **Responsive** — one app that works well on desktop and mobile browsers.
- **Accessible** — leverage shadcn/ui's Radix accessibility; meet sensible
  contrast and keyboard-navigation standards in both themes.

### Future direction — social platform

A later milestone (not MVP) will grow `twosides` into a **social platform**:
publishing public recipes, a community feed, and **sharing/forwarding recipes to
external social networks** (e.g. Instagram). The MVP only needs the
private/publish foundation (see §3.2); the design should keep public recipe
pages clean and shareable so this evolution is natural.

---

## 8. MVP Scope

The first milestone targets the **full vision, including the AI agent**:

1. **Accounts & roles** — auth (Fortify) with User / Moderator / Admin roles.
2. **Ingredient library** — official (seed + external API), private user
   ingredients, and the submission → moderation → inclusion workflow.
3. **Recipes** — structured recipes with flexible units, nested sub-recipes,
   versioning, working drafts (Save / Recall), and private/publish visibility.
4. **Metrics engine** — nutrition, cost, yield/scaling, allergens/dietary,
   time/difficulty, with roll-up through sub-recipes.
5. **Recipe tests** — trials and structured experiments with text notes, photos,
   structured ratings, and "what changed vs. expected."
6. **AI agent** — per-recipe chat that reads recipes/notes/test feedback,
   suggests tests and improvements, and applies/creates edits via Laravel tools.
7. **Multi-language** — translatable UI and ingredient names from the start.
8. **Design system** — shadcn/ui on Tailwind v4, warm-minimal aesthetic, light +
   dark themes (see §7).

**Explicitly out of MVP** (documented for later milestones):

- **Frozen-dessert balancing** — the gelato / ice cream / sorbet PAC/POD/overrun
  engine (see §5). The ingredient schema reserves its fields so it ships later
  without a migration.
- **Social platform** — public community feed and sharing/forwarding recipes to
  external social networks (see §7). MVP ships only the private/publish base.

---

## 9. Key Decisions

| Topic | Decision |
|-------|----------|
| Platform | Responsive web app |
| Audience | Pro chefs (primary) + amateurs (secondary); consumer-facing |
| Region focus | **Europe-first** (project is Greece-based) |
| Ingredient sourcing | Hybrid: **CIQUAL 2025** seed + **USDA FDC** backfill + **Open Food Facts** (Greek products); plus private user ingredients |
| Allergens | **EU Reg. 1169/2011** — 14 mandatory allergens; `contains` / `may contain` states |
| Ingredient taxonomy | Simple internal tree + optional `foodex2_code` per ingredient |
| Ingredient promotion | User submission → moderation/search → official library |
| Recipe structure | Structured data; **nested sub-recipes** with metric roll-up |
| Units | **Flexible per ingredient line**; custom converter normalizes to grams |
| Conversion data | USDA `food_portion` data + curated override table; `units` + `ingredient_conversions` model |
| Numeric precision | `brick/math` (`BigDecimal`/`BigRational`); `DECIMAL` columns; never `float` |
| Versioning | Full history; **working draft** layer; Save = commit version, Recall = undo last edit |
| Recipe visibility | **Private by default**, can publish to a public library |
| AI provider | **Provider-agnostic / configurable** |
| AI editing | Chat per recipe → Apply → working draft → Save / Recall |
| Roles | User · Moderator · Admin |
| Languages | **Multi-language from the start** (UI + ingredient names) |
| UI aesthetic | **Warm minimal hybrid** — clean structure, food-forward photography, professional tone |
| Components | **shadcn/ui** on Tailwind v4; design tokens |
| Color mode | **Light + dark**, user-toggleable |
| Frozen desserts | Gelato / ice cream / sorbet balancing (PAC/POD/overrun) — documented, **post-MVP** |
| Social platform | Public sharing + external social (Instagram) forwarding — **future milestone** |
| MVP | Full vision in one milestone, AI agent included |

---

## 10. Open Questions / To Decide Later

- **AI provider(s)** — concrete model/provider behind the configurable adapter.
- **Cost data** — source of ingredient pricing for cost metrics (user-entered,
  regional defaults, supplier integration?).
- **Public library** — discovery, search, and any moderation of published recipes.
- **ODbL compliance** — confirm with legal that Open Food Facts share-alike only
  applies if a combined database is published as a product (not for internal use).
- **FatSecret** — revisit as a paid upgrade if world/professional cuisine coverage
  proves insufficient; requires a negotiated data-storage clause.
- **HelTH access** — email Agricultural University of Athens about a data-sharing
  agreement for the Hellenic Food Thesaurus; do not block development on it.
- **Greek-name translation layer** — approach for mapping CIQUAL/USDA food names
  to Greek (manual curation + Open Food Facts cross-reference).
- **Monetization** — free vs. paid tiers (not defined yet).

### Resolved by research

- ~~External food API~~ → Europe-first hybrid: **CIQUAL 2025** seed +
  **USDA FDC** backfill + **Open Food Facts** for Greek products; see §6.
- ~~Unit conversion data~~ → USDA `food_portion` data + curated override table,
  custom converter service; see §6 Unit Conversion Approach.
