---
phase: 03-recipe-core-metrics
plan: 06
subsystem: ui
tags: [react, inertia, shadcn, tailwind, metrics, nutrition, allergens, bakers-percentage, scaling]

requires:
  - phase: 03-recipe-core-metrics-04
    provides: RecipeMetricsService computing nutrition, cost, allergens, bakers%, missing_data
  - phase: 03-recipe-core-metrics-05
    provides: show.tsx builder shell with metrics-panel-mount placeholder and useRecipeAutosave hook

provides:
  - Sticky metrics panel with NutritionSection (per-portion/per-100g toggle via Switch)
  - CostSection with inline selling price and live food cost % computation
  - AllergenSection showing contains (destructive) vs may-contain (muted) named Badge chips
  - BakersSection conditional on bakers !== null (flour-base line present)
  - ScalingControls — view-only scale + portions inputs with Apply to Draft using integer rational form
  - DataGapBanner — Alert listing missing ingredient names, renders null when empty
  - MetricsPanel container — desktop sticky, mobile Sheet expansion from summary bar
  - MetricsPanel wired into show.tsx replacing placeholder div

affects: [Phase 3 show page, recipe builder UX, metrics display for all future recipes]

tech-stack:
  added: ["@radix-ui/react-switch", "shadcn switch primitive"]
  patterns:
    - "Section components render null when no data (bakers, data-gap-banner) rather than empty state"
    - "Selling price food cost % computed client-side instantly; server-authoritative value flows on next auto-save"
    - "Apply to Draft sends scale_numerator + scale_denominator (rational integer pair) not pre-rounded float"
    - "Allergen slugs resolved to display names via slugToName() util in allergen-section"

key-files:
  created:
    - resources/js/components/ui/switch.tsx
    - resources/js/components/recipes/metrics-panel/nutrition-section.tsx
    - resources/js/components/recipes/metrics-panel/cost-section.tsx
    - resources/js/components/recipes/metrics-panel/allergen-section.tsx
    - resources/js/components/recipes/metrics-panel/bakers-section.tsx
    - resources/js/components/recipes/metrics-panel/scaling-controls.tsx
    - resources/js/components/recipes/metrics-panel/data-gap-banner.tsx
    - resources/js/components/recipes/metrics-panel/metrics-panel.tsx
  modified:
    - resources/js/pages/recipes/show.tsx
    - resources/js/types/recipe.ts

key-decisions:
  - "RecipeMetrics.allergens typed as {contains: string[], may_contain: string[]} (slug arrays) to match PHP service output, not RecipeAllergen[] object arrays as originally typed"
  - "BakersMetrics.percentages typed as Record<string, string> (name→pct map) to match PHP BakersPercentageCalculator output; BakersEntry interface removed"
  - "MissingDataInfo interface removed; missing_data typed as string[] (flat name list) matching PHP service"
  - "selling_price added to RecipeBuilderData TypeScript type (was missing from type but present in RecipeBuilderResource PHP)"
  - "Allergen slug-to-name display resolved client-side via slugToName() utility — no extra server lookup needed"
  - "Apply to Draft uses denominator=1000 to convert decimal scale to rational numerator/denominator pair"

patterns-established:
  - "Metrics panel section components: self-contained, receive only the slice of RecipeMetrics they need"
  - "Mobile collapse: fixed bottom bar + Sheet trigger pattern for panel-heavy content"

requirements-completed: [METRIC-01, METRIC-02, METRIC-03, METRIC-04, METRIC-05, METRIC-06, METRIC-07, ALLG-01, ALLG-02, ALLG-03, RECIPE-07, RECIPE-08]

duration: 35min
completed: 2026-05-17
---

# Phase 03 Plan 06: Metrics Panel Summary

**Sticky metrics panel with nutrition toggle, live food-cost %, named allergen chips, conditional baker's percentages, integer-rational scaling, and explicit missing-data gap banner wired into the recipe builder**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-05-17T00:00:00Z
- **Completed:** 2026-05-17
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- 7 metrics panel components + switch primitive — all composable, independently renderable
- Desktop sticky panel + mobile bottom-summary-bar / Sheet pattern implemented
- RecipeMetrics TypeScript types corrected to match actual PHP service output shapes
- All 53 Feature/Recipes tests green after wiring (50 pass, 3 skip — same baseline)

## Task Commits

1. **Task 1: switch primitive, nutrition-section, cost-section, allergen-section** - `05b3fdf` (feat)
2. **Task 2: bakers-section, scaling-controls, data-gap-banner** - `990630e` (feat)
3. **Task 3: metrics-panel container + wire into show.tsx** - `7492d3d` (feat)

## Files Created/Modified

- `resources/js/components/ui/switch.tsx` - shadcn switch primitive (via npx shadcn add)
- `resources/js/components/recipes/metrics-panel/nutrition-section.tsx` - per-portion/per-100g toggle + nutrient rows
- `resources/js/components/recipes/metrics-panel/cost-section.tsx` - cost rows + selling price input + live food cost %
- `resources/js/components/recipes/metrics-panel/allergen-section.tsx` - contains (destructive) / may-contain (muted) named chips
- `resources/js/components/recipes/metrics-panel/bakers-section.tsx` - conditional baker's percentages + hydration row
- `resources/js/components/recipes/metrics-panel/scaling-controls.tsx` - view-only scale + portions; Apply to Draft with integer rational
- `resources/js/components/recipes/metrics-panel/data-gap-banner.tsx` - Alert listing missing ingredient names
- `resources/js/components/recipes/metrics-panel/metrics-panel.tsx` - container composing all 6 sections; desktop sticky + mobile Sheet
- `resources/js/pages/recipes/show.tsx` - replaced metrics-panel-mount placeholder with MetricsPanel, wired auto-save callbacks
- `resources/js/types/recipe.ts` - corrected RecipeMetrics, BakersMetrics, added selling_price to RecipeBuilderData

## Decisions Made

- **RecipeMetrics type corrections** (Rule 1 auto-fix): The TypeScript types did not match the actual PHP service output. `allergens` was typed as `RecipeAllergen[]` objects but the service returns `{contains: string[], may_contain: string[]}` (slug arrays). `BakersMetrics` had an `entries: BakersEntry[]` but PHP returns `percentages: Record<string, string>`. `missing_data` was typed as `MissingDataInfo` (object) but PHP returns a flat `string[]`. All corrected to match service reality.
- **selling_price in TypeScript type**: The `RecipeBuilderData` type was missing `selling_price`, which is present in `RecipeBuilderResource` and the draft data. Added as `string | null`.
- **Allergen display names**: Resolved client-side via `slugToName()` (slug → Title Case words) — no extra API call.
- **Integer rational scaling**: Apply to Draft sends `{scale_numerator, scale_denominator: 1000, portions}` not a float, per RESEARCH Pitfall 1.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Corrected RecipeMetrics TypeScript types to match PHP service output**
- **Found during:** Task 1 (reading types before writing components)
- **Issue:** `allergens` typed as `RecipeAllergen[]` objects; service returns `{contains: string[], may_contain: string[]}`. `BakersMetrics` had `entries` array; service returns `percentages` map. `missing_data` typed as `MissingDataInfo`; service returns `string[]`. `selling_price` missing from `RecipeBuilderData`.
- **Fix:** Updated `resources/js/types/recipe.ts` to match actual service shapes; removed `BakersEntry` and `MissingDataInfo` interfaces.
- **Files modified:** `resources/js/types/recipe.ts`
- **Verification:** `npm run build` clean, no TypeScript errors
- **Committed in:** `05b3fdf` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — type mismatch bug)
**Impact on plan:** Essential correction for type safety; no scope creep.

## Issues Encountered

None — all components built to spec. i18n strings for metrics panel were already present in both `lang/en/app.php` and `lang/el/app.php` from prior planning work (plan references to `lang/en.json` / `lang/el.json` referred to the PHP files by conceptual name).

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All METRIC-01..07, ALLG-01/02/03, RECIPE-07/08 requirements satisfied
- Metrics panel live in builder: auto-refreshes on auto-save via `only:['draft','metrics']` partial reload
- Phase 3 Plan 06 is the final plan in Phase 3 — phase complete
- Phase 4 (if applicable) can build on the established metrics + builder foundation

---
*Phase: 03-recipe-core-metrics*
*Completed: 2026-05-17*
