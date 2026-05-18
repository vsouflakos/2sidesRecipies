# twosides

## Current State

**Shipped:** v1.0 MVP — 2026-05-18 (7 phases, 34 plans)

The full product vision shipped in the first milestone: a professional
recipe-management platform with a ~3,900-ingredient library, structured
versioned recipes, a complete metrics engine, recipe tests, a conversational AI
agent, a public library, and an ingredient-moderation workflow. All 67 v1
requirements satisfied; milestone audit passed.

**Next milestone:** Not yet defined — run `/gsd:new-milestone` to scope v1.1.

## What This Is

`twosides` is a recipe platform for professional chefs — and approachable for
amateurs. Recipes are structured, versioned data: measured for nutrition, cost,
yield and other professional metrics, validated through real-kitchen tests, and
continuously improved with an integrated, conversational AI agent. It is a
responsive web app, built Europe-first (the project is based in Greece).

## Core Value

A chef can build a structured, versioned recipe and trust the professional
metrics computed from its ingredients (nutrition, cost, yield, allergens) — the
quantitative insight into a recipe is the one thing that must work.

## Requirements

### Validated

<!-- Shipped and confirmed — pre-existing starter-kit features plus v1.0 milestone. -->

**Pre-existing (Laravel starter kit)**
- ✓ User can register, log in, and log out — existing (Laravel Fortify)
- ✓ User can reset a forgotten password via email — existing (Fortify)
- ✓ User can enable two-factor authentication with recovery codes — existing
- ✓ User can edit profile and account settings, and delete their account — existing
- ✓ User can switch between light / dark / system appearance — existing
- ✓ Inertia + React SPA shell with server-shared auth state — existing

**Roles & access**
- ✓ User / Moderator / Admin roles with enforced permissions — v1.0

**Ingredient library**
- ✓ Official library seeded from CIQUAL, USDA FDC, and Open Food Facts (idempotent Artisan imports) — v1.0
- ✓ Each ingredient carries nutrition values, EU 14-allergen flags, and unit-conversion data — v1.0
- ✓ Multi-language ingredient names (Greek / English) — v1.0
- ✓ Users can create private ingredients — v1.0
- ✓ Users can submit a private ingredient; moderators review and promote it — v1.0

**Recipes**
- ✓ Structured recipes — ingredient lines (any unit) plus ordered preparation steps — v1.0
- ✓ Nested sub-recipes with circular-reference detection — v1.0
- ✓ Recipe versioning with immutable history — v1.0
- ✓ Working-draft layer — Save commits a version, Recall undoes the last edit — v1.0
- ✓ Recipes private by default; user can publish to a searchable public library — v1.0

**Metrics engine**
- ✓ Nutrition, cost per portion, food cost %, yield & scaling, allergens — v1.0
- ✓ Professional extras — cooking loss / shrinkage, baker's percentages, calorie density — v1.0
- ✓ All metrics roll up correctly through nested sub-recipes — v1.0
- ✓ Unit conversion normalizes every ingredient line to grams (exact `brick/math`) — v1.0

**Recipe tests**
- ✓ Trial runs and structured experiments tied to a recipe version — v1.0
- ✓ Test feedback — tasting notes, photos, structured ratings, what-changed-vs-expected — v1.0

**AI agent**
- ✓ Per-recipe AI chat, provider-agnostic via an adapter — v1.0
- ✓ Agent reads the recipe, chef notes, and test feedback — v1.0
- ✓ Agent suggests improvements; accepted edits apply to the working draft via the manual-edit validation path — v1.0
- ✓ Agent can create a recipe variant as a working draft — v1.0

**Localization & design**
- ✓ Translatable UI with a live EN/EL language switcher — v1.0
- ✓ Warm-minimal design on shadcn/ui + Tailwind v4, light & dark themes — v1.0

### Active

<!-- Next-milestone scope — define with /gsd:new-milestone. -->

No active milestone. Candidates carried from the v1 backlog (deferred, not yet
scheduled):

- [ ] EU-format nutrition-label export for a recipe (NUTR-01)
- [ ] Ingredient cost data beyond manual entry — supplier integration / regional benchmarks (COST-01)
- [ ] Collaborative comments / annotations on a recipe (COLLAB-01)
- [ ] Carbon-footprint metric per recipe (SUST-01)
- [ ] FoodEx2 classification codes on ingredients (TAX-01)
- [ ] Printable prep / production sheet scaled to batch size (PROD-01)

### Out of Scope

<!-- Explicit boundaries, with reasoning. Reviewed at v1.0 completion — all still valid. -->

- **Frozen-dessert balancing** (gelato / ice cream / sorbet PAC/POD/overrun engine) — a distinct discipline; deferred to a post-MVP milestone. The ingredient schema reserves its fields so it ships later without a migration.
- **Social platform** (public community feed, sharing/forwarding recipes to external networks) — future milestone; v1 shipped only the private/publish foundation.
- **HelTH (Hellenic Food Thesaurus) integration** — no open license; pending a possible data-sharing agreement with the Agricultural University of Athens.
- **FatSecret paid API** — deferred; revisited only if CIQUAL+USDA+OFF coverage proves insufficient (it has not).
- **Native mobile apps** — responsive web app only.
- **Monetization / paid tiers** — not defined for v1; a future business decision.
- **Meal planning, shopping lists, inventory, supplier/POS integration, URL recipe scraping, real-time collaborative editing** — separate domains or low value for professional users; see `milestones/v1.0-REQUIREMENTS.md` for full reasoning.

## Context

- **Brownfield.** Built on the Laravel 13 React starter kit: Inertia v3, Fortify
  auth, Tailwind v4, Radix UI. Auth, profile/settings, and appearance handling
  were already scaffolded and carried through unchanged.
- **Codebase after v1.0** — 803 files changed, +131,755 LOC across the milestone
  (231 commits, 2026-05-16 → 2026-05-18). Stack: Laravel 13 / PHP 8.3,
  Inertia v3 + React 19, Tailwind v4, `brick/math` for precision,
  `spatie/laravel-permission` for RBAC, Prism for the AI adapter.
- **Ingredient data** — ~3,900 ingredients live in the dev DB from the three
  import sources; the bundled CIQUAL subset is a 60-food representative sample
  (full dataset obtained by running the import against the live ANSES download).
- **Test coverage** — every phase passed full VERIFICATION with green Pest
  suites; 4 phases human-approved at end-to-end checkpoints.
- **Detailed spec.** A fuller product spec lives in the repo-root `Project.md`.

## Known Tech Debt

<!-- Carried from the v1.0 milestone audit — non-critical, no functional impact. -->

- **Unenforced permissions** (Phase 1) — `create-recipes` and
  `manage-own-ingredients` are seeded and granted to the User role but never
  checked; recipe/private-ingredient access is gated by `auth`+`verified`
  middleware instead. Wire them into FormRequests/policies or document as
  reserved for future granular gating.
- **Ingredient FK on hard-delete** (Phase 7) — `recipe_ingredient_lines.ingredient_id`
  uses `nullOnDelete()`; a hypothetical hard-delete of a promoted ingredient
  would degrade dependent metrics to a graceful data-gap banner. Out of scope —
  official ingredients use `SoftDeletes`.
- **Nyquist validation** — phases 1, 2, 4, 6, 7 have a VALIDATION.md strategy
  doc but were not formally marked compliant (phases 3 and 5 are). Run
  `/gsd:validate-phase {N}` to close coverage.

## Constraints

- **Tech stack**: Laravel 13 / PHP 8.3, Inertia v3 + React 19, Tailwind v4 — fixed by the starter kit; follow existing conventions.
- **Data licensing**: only data sources that legally permit local storage may seed the ingredient library (CIQUAL CC-BY, USDA CC0, Open Food Facts ODbL) — hard legal constraint.
- **EU compliance**: the allergen model must follow EU Regulation 1169/2011 (the 14 mandatory allergens) — the product is Greece-based.
- **Numeric precision**: metric math uses `brick/math` with `DECIMAL` columns, never floats — correctness of nutrition/cost/yield depends on it.
- **Platform**: responsive web app only — no native mobile in scope.
- **Testing**: every change is programmatically tested (Pest) — project rule.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Build on the existing Laravel 13 React starter kit | Auth, settings, theming already scaffolded; matches project conventions | ✓ Good — carried through unchanged, zero rework |
| Full vision (incl. AI agent) is the MVP | User wants the complete product in the first milestone | ✓ Good — all 67 requirements shipped in 7 phases |
| Europe-first, Greece-based focus | Drives data sourcing, language, and EU compliance | ✓ Good — shaped CIQUAL/EU-allergen/EL-locale choices |
| Ingredient data: CIQUAL seed + USDA FDC backfill + Open Food Facts | Best legally-storable European coverage; USDA fills gaps; OFF adds Greek products & allergens | ✓ Good — ~3,900 ingredients seeded, idempotent imports |
| Allergens modelled on EU Reg. 1169/2011 (14 allergens) | EU-compliant by construction; maps onto OFF tags | ✓ Good — rolls up through sub-recipes with contains/may-contain |
| Ingredient taxonomy: simple internal tree + optional `foodex2_code` | FoodEx2 too heavyweight as primary tree; keep interop hook | ✓ Good — 50-node category tree seeded |
| Flexible units per ingredient line; custom converter normalizes to grams | General converters don't solve ingredient-specific volume/count→weight | ✓ Good — `GramNormalizer` normalizes every line |
| Conversion data: USDA `food_portion` + curated override table | Authoritative gram-weight portions; ~80% coverage, override for the rest | ✓ Good |
| Precision via `brick/math` (`BigDecimal`/`BigRational`), `DECIMAL` columns | Avoid float drift across scaling and sub-recipe roll-up | ✓ Good — METRIC-09 verified, no drift |
| Recipe versioning with a working-draft layer (Save / Recall) | Lets AI and user edits accumulate before committing a version | ✓ Good — draft edits apply as deltas, one Recall step each |
| Nested sub-recipes with metric roll-up | Professional recipes reuse components (stocks, doughs, sauces) | ✓ Good — METRIC-08 verified, draft-augmented cycle detection |
| Recipes private by default, publishable to a public library | Matches chef workflow; sets up future social platform | ✓ Good — published snapshots survive ingredient renames |
| AI agent is provider-agnostic via an adapter | Avoid lock-in; concrete provider chosen later | ✓ Good — `PrismAdapter`, 11 providers, empty default hides feature |
| Multi-language from the start (UI + ingredient names) | International ingredients; Greek market | ✓ Good — EN/EL structural parity maintained across all phases |
| UI: shadcn/ui on Tailwind v4, warm-minimal aesthetic, light + dark | shadcn foundation already installed; professional yet inviting tone | ✓ Good — 4 phases human-approved at UI checkpoints |
| Frozen-dessert balancing & social platform deferred post-MVP | Keep v1 focused; reserve schema fields to avoid later migration | ✓ Good — schema fields reserved, scope held |
| Permissions enforced via middleware, two named permissions left unwired | `auth`+`verified` is functionally equivalent for the v1 User population | ⚠️ Revisit — `create-recipes` / `manage-own-ingredients` are dead code; wire or document |

<details>
<summary>Phase-level implementation decisions (v1.0)</summary>

~80 phase-level decisions were logged during v1.0 execution — model/architecture
choices, bug fixes surfaced at human-verify checkpoints, and test-contract
adjustments. The full log lives in the archived phase summaries
(`.planning/phases/*/`) and `milestones/v1.0-ROADMAP.md`. Notable ones:

- Permissions resolved via `getAllPermissions()->pluck('name')` (not
  `getPermissionNames()`) — role-derived permissions were otherwise dropped.
- Circular FK columns declared without `constrained()`, deferred-FK migration
  added later — avoids chicken-and-egg ordering failure.
- Agent edits apply as deltas via `DraftActionApplier`, never a full-draft
  replace — preserves unrelated fields, keeps each Apply to one Recall step.
- Published recipes store ingredient names as snapshot strings — survive later
  ingredient renames/deletes.

</details>

---
*Last updated: 2026-05-18 after v1.0 milestone*
