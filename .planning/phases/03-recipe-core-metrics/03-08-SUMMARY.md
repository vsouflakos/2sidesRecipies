---
phase: 03-recipe-core-metrics
plan: "08"
subsystem: ui
tags: [react, inertia, recipe, metrics, filters, sidebar]

# Dependency graph
requires:
  - phase: 03-recipe-core-metrics (plans 01-07)
    provides: recipe model, builder, metrics engine, versioning, sub-recipes, draft auto-save
provides:
  - Recipe list page — responsive card grid with hero image, metrics row, allergen icons
  - RecipeCard component — visual card with 16:9 hero, cuisine badge, nutrition/cost metrics
  - RecipeFilters panel — collapsible six-filter panel (Tags, Cuisine, Allergen, Ingredient, Difficulty, Time)
  - Recipes sidebar nav entry
  - Corrected MetricsAggregator — per-portion nutrition, cost, baker's %, allergens, gap detection
  - DraftMetricsTest — draft-state metrics computation test coverage
affects: [phase-04-recipe-advanced, phase-06-publishing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Recipe list grid uses same debounced router.reload + skeleton pattern as ingredient list
    - Allergen filter fires on Popover close (not per-tick) — consistent with Phase 2 contract
    - MetricsAggregator reworked to return authoritative per-portion arrays + gap map

key-files:
  created:
    - resources/js/components/recipes/recipe-card.tsx
    - resources/js/components/recipes/recipe-filters.tsx
    - tests/Feature/Recipes/Metrics/DraftMetricsTest.php
  modified:
    - resources/js/pages/recipes/index.tsx
    - resources/js/pages/recipes/show.tsx
    - resources/js/components/app-sidebar.tsx
    - resources/js/types/recipe.ts
    - app/Support/Recipes/MetricsAggregator.php
    - app/Support/Recipes/RecipeMetricsService.php
    - app/Http/Controllers/Recipes/RecipeDraftController.php
    - tests/Feature/Recipes/CircularReferenceTest.php
    - tests/Feature/Recipes/RecipeDraftTest.php
    - lang/en.json
    - lang/el.json

key-decisions:
  - "MetricsAggregator reworked post-Task-2: PHP service output shape corrected to match TS types — per-portion not per-100g as default, allergens as slug arrays, bakers as percentages map, missing_data as string[]"
  - "Draft metrics exposed via RecipeDraftController save response — no extra round-trip needed for live metrics panel update"
  - "show.tsx builder fully null-safe via sanitizing translation wrapper — all i18n calls guarded against null section/ingredient names"

patterns-established:
  - "Recipe card hero: aspect-video container, bg-muted placeholder with centered UtensilsCrossedIcon when no image"
  - "Six-filter panel: Collapsible on desktop, Sheet on mobile; allergen filter reloads on Popover close"
  - "Builder null-safety: sanitize all t() replacements through safeStr() wrapper before use"

requirements-completed: [RECIPE-12]

# Metrics
duration: ~180min (includes multiple fix iterations)
completed: "2026-05-17"
---

# Phase 03 Plan 08: Recipe List + End-to-End Phase Verification Summary

**Visual recipe card grid with live search, six-filter panel, Recipes sidebar nav, and corrected metrics aggregator — completing Phase 3 UI surface with 181-test green suite**

## Performance

- **Duration:** ~180 min (includes 8 builder fix iterations post Task 1/2)
- **Started:** 2026-05-16 (Tasks 1+2 in prior session)
- **Completed:** 2026-05-17
- **Tasks:** 2 completed (Task 3 is human-verify checkpoint — pending)
- **Files modified:** 13

## Accomplishments
- Recipe card grid page with hero image, cuisine badge, nutrition/cost row, allergen icon strip
- Collapsible six-filter panel (Tags Command, Cuisine Select, Allergen Popover-close, Ingredient Command, Difficulty Select, Time Select) + 300ms debounced search
- Recipes entry in app sidebar alongside Ingredients
- Full i18n null-safety via sanitizing translation wrapper throughout the builder
- MetricsAggregator reworked to correctly compute per-portion nutrition, baker's %, allergens, gap detection
- DraftMetricsTest added for draft-state metrics computation coverage
- All 181 tests pass (178 pass, 3 skip)

## Task Commits

1. **Task 1: recipe-card component + Recipes sidebar nav entry** - `6f483b3` (feat)
2. **Task 2: recipe-filters panel + recipe list index page** - `6d028b0` (feat)
3. **Deviation: default yield_amount on recipe create** - `fec5911` (fix)
4. **Deviation: render recipe builder for empty new recipe** - `15fc889` (fix)
5. **Deviation: normalize allergen_slugs to array on recipe list** - `10b0473` (fix)
6. **Deviation: pass undefined not empty string to Radix Select when value is null** - `1c19ca4` (fix)
7. **Deviation: resolve component search 500, allergen list crash, and builder draft-mutation crash** - `721c20b` (fix)
8. **Deviation: resolve recipe builder page render crash — null section name in t() call** - `b217388` (fix)
9. **Deviation: harden all recipe i18n calls against null replacement values** - `92eafba` (fix)
10. **Deviation: structurally null-safe recipe i18n via sanitizing translation wrapper** - `56e504c` (fix)
11. **Deviation: metrics aggregator rework and draft-metrics coverage** - `09c65d5` (fix)

**Task 3 (human-verify checkpoint):** Pending human approval — no code changes in this task.

## Files Created/Modified
- `resources/js/components/recipes/recipe-card.tsx` - Visual recipe card with hero image/placeholder, cuisine badge, time+difficulty, cost+calories, allergen icon strip
- `resources/js/components/recipes/recipe-filters.tsx` - Collapsible six-filter panel (Tags, Cuisine, Allergen, Ingredient, Difficulty, Time)
- `resources/js/pages/recipes/index.tsx` - Recipe list grid page with debounced search, skeleton loading, pagination, empty states
- `resources/js/pages/recipes/show.tsx` - Builder: null-safe i18n, corrected metrics consumption from controller
- `resources/js/components/app-sidebar.tsx` - Recipes nav entry added alongside Ingredients
- `resources/js/types/recipe.ts` - RecipeCardData type added
- `app/Support/Recipes/MetricsAggregator.php` - Full rework: per-portion nutrition, baker's %, allergen slug arrays, gap detection
- `app/Support/Recipes/RecipeMetricsService.php` - Wired into reworked aggregator
- `app/Http/Controllers/Recipes/RecipeDraftController.php` - Exposes metrics in draft save response
- `tests/Feature/Recipes/Metrics/DraftMetricsTest.php` - New: draft-state metrics computation tests
- `tests/Feature/Recipes/CircularReferenceTest.php` - Updated for corrected contracts
- `tests/Feature/Recipes/RecipeDraftTest.php` - Updated for corrected contracts
- `lang/en.json` / `lang/el.json` - Recipe list i18n strings

## Decisions Made
- MetricsAggregator reworked to match PHP service output shape with TS types: per-portion not per-100g as default, allergens as slug arrays, bakers as percentages map, missing_data as string[]
- Draft metrics exposed via RecipeDraftController save response — avoids extra round-trip for live panel update
- show.tsx builder fully null-safe via safeStr() sanitizing wrapper — all t() calls guard against null section/ingredient names

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] yield_amount not defaulted on recipe create**
- **Found during:** Post Task 2 browser testing
- **Issue:** New recipe creation crashed due to missing yield_amount default
- **Fix:** Added default yield_amount value on recipe create endpoint
- **Files modified:** (controller/draft controller)
- **Committed in:** `fec5911`

**2. [Rule 1 - Bug] Recipe builder blank for empty new recipes**
- **Found during:** Post Task 2 browser testing
- **Issue:** Builder page rendered blank when recipe had no sections/lines
- **Fix:** Added empty-state guards in show.tsx builder render path
- **Committed in:** `15fc889`

**3. [Rule 1 - Bug] allergen_slugs not always an array on recipe list**
- **Found during:** Post Task 2 browser testing
- **Issue:** allergen_slugs could arrive as null/string rather than array — RecipeCard crashed
- **Fix:** Normalize to array before rendering
- **Committed in:** `10b0473`

**4. [Rule 1 - Bug] Radix Select crashes when value is empty string**
- **Found during:** Post Task 2 browser testing
- **Issue:** Passing empty string "" to Radix Select instead of undefined caused component error
- **Fix:** Pass undefined when value is null/empty
- **Committed in:** `1c19ca4`

**5. [Rule 1 - Bug] Component search 500, allergen list crash, builder draft-mutation crash**
- **Found during:** Post Task 2 browser testing
- **Issue:** Three distinct crashes in ingredient component search, allergen display, and draft mutation handler
- **Fix:** Fixed query parameter handling, allergen null guard, and draft mutation argument order
- **Committed in:** `721c20b`

**6. [Rule 1 - Bug] Builder page render crash — null section name in t() call**
- **Found during:** Builder first render with real data
- **Issue:** t() called with null section.name as replacement value, causing crash
- **Fix:** Guard section name before passing to t()
- **Committed in:** `b217388`

**7. [Rule 2 - Missing Critical] Harden all recipe i18n calls against null replacement values**
- **Found during:** Continued builder testing
- **Issue:** Multiple t() calls throughout show.tsx could receive null replacements
- **Fix:** Systematically added null guards to all t() calls in builder
- **Committed in:** `92eafba`

**8. [Rule 2 - Missing Critical] Structural null-safety via sanitizing translation wrapper**
- **Found during:** Continued builder testing
- **Issue:** Piecemeal null guards insufficient — introduced safeStr() wrapper applied at all call sites
- **Fix:** safeStr() wrapper in show.tsx ensures all t() replacements are string-safe
- **Committed in:** `56e504c`

**9. [Rule 1 - Bug] MetricsAggregator output shape mismatch with TypeScript types**
- **Found during:** Metrics panel integration testing
- **Issue:** PHP aggregator returned per-100g values as default, allergens as objects not slug arrays, bakers/missing_data shapes didn't match TS contract
- **Fix:** Full MetricsAggregator rework; DraftMetricsTest added for coverage; RecipeDraftController and show.tsx updated
- **Files modified:** MetricsAggregator.php, RecipeMetricsService.php, RecipeDraftController.php, show.tsx, CircularReferenceTest.php, RecipeDraftTest.php, DraftMetricsTest.php (new)
- **Committed in:** `09c65d5`

---

**Total deviations:** 9 auto-fixed (7 Rule 1 bugs, 2 Rule 2 missing-critical)
**Impact on plan:** All fixes necessary for correct operation. The MetricsAggregator rework was the most substantial — the PHP output shape needed to match the TS type contract established in Plan 07. No scope creep.

## Issues Encountered
- The MetricsAggregator PHP output shape was misaligned with the TypeScript RecipeMetrics types defined in Plan 07 — required a systematic rework rather than incremental patches. DraftMetricsTest added to prevent regression.
- Multiple null-safety issues in the builder i18n layer required 3 successive fix commits before a structural solution (safeStr wrapper) was established.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- Phase 3 is functionally complete pending human-verify checkpoint approval (Task 3)
- The full phase 3 recipe system is built: builder, draft auto-save, metrics panel, versioning, sub-recipes, duplication, recipe list with search/filters
- Phase 4 (advanced recipe features) can begin once Task 3 is approved
- Test suite: 181 tests, 178 pass, 3 skip — green

---
*Phase: 03-recipe-core-metrics*
*Completed: 2026-05-17*
