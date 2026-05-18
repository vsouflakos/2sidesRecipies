# Milestones

## v1.0 MVP (Shipped: 2026-05-18)

**Delivered:** A professional recipe-management platform — chefs build structured, versioned recipes from a 3,900-ingredient library and trust the nutrition, cost, yield, and allergen metrics computed from them.

**Scope:** 7 phases, 34 plans · 803 files changed, +131,755 LOC · 231 commits · 2026-05-16 → 2026-05-18 (3 days)

**Key accomplishments:**
- **Professional ingredient library** — ~3,900 ingredients seeded from CIQUAL, USDA FDC, and Open Food Facts via idempotent Artisan import commands, each carrying nutrition values, EU 14-allergen flags, and unit-conversion data; users can also create private ingredients.
- **Structured, versioned recipes** — ingredient lines in any unit, ordered prep steps, nested sub-recipes with circular-reference detection, an immutable version history, and a working-draft layer with Save / Recall.
- **Full metrics engine** — nutrition per portion and per 100 g, cost and food cost %, yield and scaling, cooking loss / shrinkage, baker's percentages, and allergen roll-up — all correct through nested sub-recipes, computed with exact `brick/math` decimal arithmetic.
- **Conversational AI agent** — a per-recipe chat backed by a provider-agnostic adapter that reads the recipe, chef notes, and test feedback, applies accepted edits through the same validation path as manual edits, and can spin off recipe variants.
- **Recipe tests, publishing, and moderation** — structured trial runs and experiments tied to recipe versions; publish/unpublish to a searchable public library; and a user-submission → moderator-review → promotion pipeline for ingredients.
- **Foundation** — User / Moderator / Admin role system, EN/EL localization with a live language switcher, and a warm-minimal shadcn/ui design system across light and dark themes.

**Known tech debt (non-blocking):**
- Phase 1 — `create-recipes` and `manage-own-ingredients` permissions are seeded and granted but not enforced anywhere; recipe/private-ingredient access is gated by `auth`+`verified` middleware instead. Wire them or document as reserved.
- Phase 7 — `recipe_ingredient_lines.ingredient_id` uses `nullOnDelete()`; a hypothetical hard-delete of a promoted ingredient degrades dependent metrics to a graceful data-gap banner. Out of v1.0 scope (official ingredients use `SoftDeletes`).

**Validation note:** Phases 3 and 5 are Nyquist-compliant; phases 1, 2, 4, 6, 7 have a VALIDATION.md strategy doc but were not formally marked compliant. All 7 phases independently passed full VERIFICATION with green test suites (4 human-approved at end-to-end checkpoints). Run `/gsd:validate-phase {N}` for 1, 2, 4, 6, 7 to formally close validation coverage.

**Archived:** `milestones/v1.0-ROADMAP.md`, `milestones/v1.0-REQUIREMENTS.md`, `milestones/v1.0-MILESTONE-AUDIT.md`

---
