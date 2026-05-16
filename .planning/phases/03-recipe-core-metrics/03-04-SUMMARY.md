---
phase: 03-recipe-core-metrics
plan: "04"
subsystem: recipe-backend
tags: [recipes, controllers, policy, form-requests, metrics-service, version-service, resources]
dependency_graph:
  requires: ["03-02", "03-03"]
  provides: ["server-contract", "recipe-routes", "recipe-metrics-prop"]
  affects: ["03-05", "03-06", "03-07", "03-08"]
tech_stack:
  added: []
  patterns:
    - Thin controller pattern (inject services, Gate::authorize, Inertia::render)
    - Append-only version rows (RecipeVersionService::commit never updates existing)
    - BigDecimal throughout — no float/floatval anywhere in metrics or cost paths
    - Draft-augmented circular reference detection (BFS over both committed lines + draft JSON)
    - Stub React pages to unblock Inertia-rendering tests before UI plans ship
key_files:
  created:
    - app/Policies/RecipePolicy.php
    - app/Concerns/RecipeValidationRules.php
    - app/Http/Requests/Recipes/StoreRecipeRequest.php
    - app/Http/Requests/Recipes/UpdateRecipeDraftRequest.php
    - app/Http/Requests/Recipes/StoreRecipeVersionRequest.php
    - app/Support/Recipes/RecipeMetricsService.php
    - app/Support/Recipes/RecipeVersionService.php
    - app/Http/Controllers/Recipes/RecipeController.php
    - app/Http/Controllers/Recipes/RecipeDraftController.php
    - app/Http/Controllers/Recipes/RecipeSearchController.php
    - app/Http/Controllers/Recipes/RecipeVersionController.php
    - app/Http/Controllers/Recipes/RecipeDuplicateController.php
    - app/Http/Resources/RecipeListResource.php
    - app/Http/Resources/RecipeBuilderResource.php
    - resources/js/pages/recipes/index.tsx
    - resources/js/pages/recipes/show.tsx
    - resources/js/pages/recipes/create.tsx
    - resources/js/pages/recipes/versions/compare.tsx
    - resources/js/pages/recipes/versions/show.tsx
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/web.php
decisions:
  - RecipeVersionController gains a `show` method and `recipes.versions.show` route — the Wave 0 test `GET /recipes/{id}/versions/{version}` references this route which was not in the plan spec but is required to make the pre-written tests green
  - Draft-augmented BFS cycle detection — circular reference detector traverses both committed recipe_ingredient_lines AND draft JSON sub_recipe_version_id fields, because the test flow stores the sub-recipe reference only in the draft before any relational rows exist
  - Circular reference validation returns JSON directly (not ValidationException) — the PUT request has no Accept: application/json header in tests, so ValidationException would redirect rather than returning 422 JSON with errors
  - Stub React pages created for all five recipe page routes — required so Inertia::render() calls don't throw ViteException during test execution; actual implementations in plans 05-08
  - recipes.versions.show route added as deviation from plan spec (plan only listed compare) — required to satisfy pre-written Wave 0 RecipeVersionTest
metrics:
  duration_min: 23
  completed_date: "2026-05-17"
  tasks: 3
  files_created: 19
  files_modified: 2
---

# Phase 03 Plan 04: Recipe Backend — Routes, Policy, Controllers, Services, Resources

Full server contract for recipes: five controllers, RecipePolicy, three FormRequests, RecipeMetricsService (BigDecimal-only metrics prop), RecipeVersionService (append-only commit with cached cost/nutrition/allergen), and two Inertia resources.

## Tasks Completed

| # | Task | Commit | Key Files |
|---|------|--------|-----------|
| 1 | RecipePolicy, routes, FormRequests, services | c273dfc | RecipePolicy, web.php, RecipeMetricsService, RecipeVersionService |
| 2 | RecipeController, RecipeDraftController, RecipeSearchController | 4116b44 | 3 controllers in app/Http/Controllers/Recipes/ |
| 3 | RecipeVersionController, RecipeDuplicateController, resources + stub pages | f4e7724 | 2 controllers, 2 resources, 5 React stub pages |

## Test Results

- `php artisan test --compact tests/Feature/Recipes/` — 53 tests, 50 passing, 3 skipped (todo)
- All CircularReferenceTest, RecipeCrudTest, RecipeDraftTest, RecipeVersionTest, SubRecipeTest, RecipeSearchTest pass

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Vite manifest missing recipe pages blocked Inertia-rendering tests**
- **Found during:** Task 3 test run
- **Issue:** RecipeVersionTest and RecipeSearchTest hit GET routes that call `Inertia::render()` for pages that don't exist yet in the Vite manifest
- **Fix:** Created five minimal stub React pages (index, show, create, versions/show, versions/compare) and ran `npm run build`
- **Files modified:** resources/js/pages/recipes/{index,show,create}.tsx, resources/js/pages/recipes/versions/{show,compare}.tsx
- **Commit:** f4e7724

**2. [Rule 2 - Missing Route] RecipeVersionController needed a single-version show route**
- **Found during:** Task 3 test run
- **Issue:** Wave 0 test `GET /recipes/{id}/versions/{version}` references a route not in the plan spec; returned 404
- **Fix:** Added `recipes.versions.show` route and `show()` method to RecipeVersionController
- **Files modified:** routes/web.php, app/Http/Controllers/Recipes/RecipeVersionController.php
- **Commit:** f4e7724

**3. [Rule 1 - Bug] Circular reference detection needed to traverse draft JSON, not just committed lines**
- **Found during:** Task 2 CircularReferenceTest
- **Issue:** `CircularReferenceDetector.wouldCreateCycle` only checks `recipe_ingredient_lines`; sub-recipe additions are stored in draft JSON before any relational rows exist, so no cycle was detected
- **Fix:** Implemented `wouldCreateCycleIncludingDrafts` BFS in `RecipeDraftController` that also loads and traverses `RecipeDraft.data` sub-recipe references
- **Files modified:** app/Http/Controllers/Recipes/RecipeDraftController.php
- **Commit:** 4116b44

**4. [Rule 1 - Bug] ValidationException throws redirect not JSON for non-AJAX draft requests**
- **Found during:** CircularReferenceTest assertJsonValidationErrors failing
- **Issue:** `ValidationException::withMessages()` from inside the controller redirected back with session errors when no Accept: application/json header; tests expect JSON 422
- **Fix:** Return explicit `response()->json(['errors' => [...]], 422)` instead of throwing ValidationException
- **Files modified:** app/Http/Controllers/Recipes/RecipeDraftController.php
- **Commit:** 4116b44

**5. [Rule 1 - Bug] Allergen filter in RecipeController caused ambiguous column error**
- **Found during:** RecipeSearchTest allergen filter test
- **Issue:** `whereJsonContains('current_version.cached_allergen_slugs->contains', ...)` is not a valid Eloquent column expression
- **Fix:** Changed to `whereHas('currentVersion', fn($v) => $v->whereJsonContains('cached_allergen_slugs->contains', $allergen))`
- **Files modified:** app/Http/Controllers/Recipes/RecipeController.php
- **Commit:** 4116b44

## Self-Check

### Created files exist

- app/Policies/RecipePolicy.php — FOUND
- app/Support/Recipes/RecipeMetricsService.php — FOUND
- app/Support/Recipes/RecipeVersionService.php — FOUND
- app/Http/Controllers/Recipes/RecipeController.php — FOUND
- app/Http/Controllers/Recipes/RecipeDraftController.php — FOUND
- app/Http/Controllers/Recipes/RecipeVersionController.php — FOUND
- app/Http/Controllers/Recipes/RecipeDuplicateController.php — FOUND
- app/Http/Controllers/Recipes/RecipeSearchController.php — FOUND
- app/Http/Resources/RecipeListResource.php — FOUND
- app/Http/Resources/RecipeBuilderResource.php — FOUND

### Commits exist

- c273dfc — feat(03-04): RecipePolicy, routes, FormRequests, RecipeMetricsService, RecipeVersionService
- 4116b44 — feat(03-04): RecipeController, RecipeDraftController, RecipeSearchController
- f4e7724 — feat(03-04): RecipeVersionController, RecipeDuplicateController, resources, stub React pages

## Self-Check: PASSED
