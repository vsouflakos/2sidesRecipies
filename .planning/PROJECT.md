# twosides

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

<!-- Shipped and confirmed valuable — inferred from the existing starter kit. -->

- ✓ User can register, log in, and log out — existing (Laravel Fortify)
- ✓ User can reset a forgotten password via email — existing (Fortify)
- ✓ User can enable two-factor authentication with recovery codes — existing
- ✓ User can edit profile and account settings, and delete their account — existing
- ✓ User can switch between light / dark / system appearance — existing
- ✓ Inertia + React SPA shell with server-shared auth state — existing

### Active

<!-- v1 scope — the MVP. Full product vision, AI agent included. Hypotheses until shipped. -->

**Roles & access**
- [ ] User / Moderator / Admin roles with appropriate permissions

**Ingredient library**
- [ ] Official ingredient library seeded from CIQUAL, backfilled from USDA FDC
- [ ] Ingredients enriched from Open Food Facts (Greek products, allergen tags)
- [ ] Each ingredient carries nutrition values, allergens, and conversion data
- [ ] Multi-language ingredient names
- [ ] Users can add their own private ingredients
- [ ] Users can submit a private ingredient for inclusion; moderators review and promote it

**Recipes**
- [ ] Structured recipes — ingredient lines (flexible units) plus preparation steps
- [ ] Nested sub-recipes (a recipe usable as a component of another)
- [ ] Recipe versioning with full history
- [ ] Working-draft layer — Save commits a version, Recall undoes the last applied edit
- [ ] Recipes private by default; user can publish to a public library

**Metrics engine**
- [ ] Nutrition, cost per portion, yield & scaling, allergens / dietary tags, time & difficulty
- [ ] Professional extras — food cost %, cooking loss / shrinkage, baker's percentages
- [ ] All metrics roll up correctly through nested sub-recipes
- [ ] Unit conversion normalizes every ingredient line to grams

**Recipe tests**
- [ ] Trial runs and structured experiments against a recipe version
- [ ] Test feedback — tasting notes, photos, structured ratings, what-changed-vs-expected

**AI agent**
- [ ] Per-recipe AI chat, provider-agnostic via an adapter
- [ ] Agent reads the recipe, user notes, and test feedback
- [ ] Agent suggests tests/experiments and improvements
- [ ] Agent applies an accepted edit to the recipe's working draft
- [ ] Agent can create a recipe variant (e.g. ingredient swaps) as a working draft

**Localization & design**
- [ ] Translatable UI from the start
- [ ] Warm-minimal design built on shadcn/ui + Tailwind v4, light & dark themes

### Out of Scope

<!-- Explicit boundaries, with reasoning. -->

- **Frozen-dessert balancing** (gelato / ice cream / sorbet PAC/POD/overrun engine) — a distinct discipline; deferred to a post-MVP milestone. The ingredient schema will reserve its fields so it ships later without a migration.
- **Social platform** (public community feed, sharing/forwarding recipes to external networks like Instagram) — future milestone; v1 ships only the private/publish foundation.
- **HelTH (Hellenic Food Thesaurus) integration** — no open license; pending a possible data-sharing agreement with the Agricultural University of Athens.
- **FatSecret paid API** — deferred; only revisited if world/professional cuisine coverage proves insufficient.
- **Native mobile apps** — responsive web app only.
- **Monetization / paid tiers** — not defined for v1.

## Context

- **Brownfield.** Built on the Laravel 13 React starter kit: Inertia v3, Fortify
  auth, Tailwind v4, Radix UI. Authentication, profile/settings, and light/dark
  appearance handling are already scaffolded (see `.planning/codebase/`).
- **Foundations already present.** `brick/math` is already a dependency (the
  chosen precision library). Radix UI + CVA + `clsx` + `tailwind-merge` are
  installed — the shadcn/ui foundation is in place.
- **Detailed spec.** A fuller product spec lives in the repo-root `Project.md`
  (vision, domain model, AI interaction flow, the full metrics catalogue, data
  sources, design direction). This file is the working GSD context summary.
- **Research completed** (this session) on: nutrition data providers, European /
  Greek / EU data sources, ingredient unit conversion, and gelato/ice-cream
  metrics. Conclusions are folded into Key Decisions and `Project.md`.
- **Europe-first**, Greece-based — drives data-source and compliance choices.

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
| Build on the existing Laravel 13 React starter kit | Auth, settings, theming already scaffolded; matches project conventions | — Pending |
| Full vision (incl. AI agent) is the MVP | User wants the complete product in the first milestone | — Pending |
| Europe-first, Greece-based focus | Drives data sourcing, language, and EU compliance | — Pending |
| Ingredient data: CIQUAL seed + USDA FDC backfill + Open Food Facts | Best legally-storable European coverage; USDA fills gaps; OFF adds Greek products & allergens | — Pending |
| Allergens modelled on EU Reg. 1169/2011 (14 allergens) | EU-compliant by construction; maps onto OFF tags | — Pending |
| Ingredient taxonomy: simple internal tree + optional `foodex2_code` | FoodEx2 too heavyweight as primary tree; keep interop hook | — Pending |
| Flexible units per ingredient line; custom converter normalizes to grams | General converters don't solve ingredient-specific volume/count→weight | — Pending |
| Conversion data: USDA `food_portion` + curated override table | Authoritative gram-weight portions; ~80% coverage, override for the rest | — Pending |
| Precision via `brick/math` (`BigDecimal`/`BigRational`), `DECIMAL` columns | Avoid float drift across scaling and sub-recipe roll-up | — Pending |
| Recipe versioning with a working-draft layer (Save / Recall) | Lets AI and user edits accumulate before committing a version | — Pending |
| Nested sub-recipes with metric roll-up | Professional recipes reuse components (stocks, doughs, sauces) | — Pending |
| Recipes private by default, publishable to a public library | Matches chef workflow; sets up future social platform | — Pending |
| AI agent is provider-agnostic via an adapter | Avoid lock-in; concrete provider chosen later | — Pending |
| Multi-language from the start (UI + ingredient names) | International ingredients; Greek market | — Pending |
| UI: shadcn/ui on Tailwind v4, warm-minimal aesthetic, light + dark | shadcn foundation already installed; professional yet inviting tone | — Pending |
| Frozen-dessert balancing & social platform deferred post-MVP | Keep v1 focused; reserve schema fields to avoid later migration | — Pending |

---
*Last updated: 2026-05-16 after initialization*
