---
phase: 04-recipe-tests
plan: 02
subsystem: backend
tags: [laravel, policy, form-requests, resources, controller, routes, inertia, pest, tdd]

# Dependency graph
requires:
  - phase: 04-recipe-tests
    plan: 01
    provides: RecipeTest model, RecipeTestPhoto model, TestType/TestVerdict enums, factories, Wave 0 RED test suite

provides:
  - RecipeTestPolicy with view/update/delete owner-scoped authorization
  - StoreRecipeTestRequest with full validation incl experiment hypothesis requirement
  - UpdateRecipeTestRequest with partial update (sometimes) rules + deleted_photo_ids
  - RecipeTestResource serializing full test shape with nested photo URLs
  - RecipeTestController with index/store/update/destroy (transactional photo upload with orphan cleanup)
  - Four recipes/{recipe}/tests routes declared before the {recipe} wildcard
  - Stub recipes/tests/index.tsx page for Inertia test compatibility
  - Wave 0 RecipeTestTest suite GREEN (13/13 tests pass)

affects: [04-03-recipe-tests-frontend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - RecipeTestPolicy mirrors RecipePolicy ownership pattern but chains through recipe.user_id (test has no direct owner column)
    - UpdateRecipeTestRequest uses sometimes modifier for required fields to allow partial updates
    - Transactional photo upload with try/catch orphan cleanup — stored paths tracked before transaction so rollback can delete committed files
    - Gate::authorize() used directly (no AuthorizesRequests trait on base Controller)
    - abort_unless($test->recipe_id === $recipe->id, 404) scope-checks nested resource before authorization

key-files:
  created:
    - app/Policies/RecipeTestPolicy.php
    - app/Http/Requests/Recipes/StoreRecipeTestRequest.php
    - app/Http/Requests/Recipes/UpdateRecipeTestRequest.php
    - app/Http/Resources/RecipeTestResource.php
    - app/Http/Controllers/Recipes/RecipeTestController.php
    - resources/js/pages/recipes/tests/index.tsx
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/web.php

key-decisions:
  - "UpdateRecipeTestRequest uses sometimes modifier for type/recipe_version_id/tested_at/overall_rating — the Wave 0 test only sends tasting_notes+overall_rating for update, so required without sometimes would fail validation"
  - "Stub recipes/tests/index.tsx created as deviation (Rule 3) — Inertia assertInertia() triggers Vite manifest lookup; page must exist in build for the GET index test to pass"
  - "abort_unless scope-check on nested resource ($test->recipe_id === $recipe->id) placed before Gate::authorize to return 404 before revealing ownership info"

patterns-established:
  - "UpdateRecipeTestRequest: use sometimes+required for fields that are required when present but optional for partial updates"
  - "Nested resource scope-check pattern: abort_unless($child->parent_id === $parent->id, 404) before policy check"

requirements-completed: [TEST-01, TEST-02, TEST-03, TEST-04]

# Metrics
duration: 8min
completed: 2026-05-17
---

# Phase 4 Plan 02: RecipeTest Backend Summary

**Owner-scoped RecipeTestPolicy, Store/Update FormRequests, RecipeTestResource, thin RecipeTestController with transactional photo upload, and four recipes/{recipe}/tests routes — turns 13-test RED suite GREEN**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-05-17T11:07:48Z
- **Completed:** 2026-05-17T11:15:48Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- Created `RecipeTestPolicy` with view/update/delete methods checking `$test->recipe->user_id === $user->id`; registered in `AppServiceProvider` via `Gate::policy`
- Created `StoreRecipeTestRequest` with full validation: `Rule::enum(TestType)`, `Rule::requiredIf(type=experiment)` for hypothesis, photo `mimes:jpg,jpeg,png,webp` constraint
- Created `UpdateRecipeTestRequest` with `sometimes` modifier on required fields (allowing partial updates) plus `deleted_photo_ids` with `exists:recipe_test_photos,id`
- Created `RecipeTestResource` serializing full test shape including `version_number`, `photos` with resolved URLs, all enum values, and ISO timestamps
- Created `RecipeTestController` with four thin actions using `Gate::authorize`, `DB::transaction`, transactional photo upload/cleanup, and `abort_unless` scope-check for nested resources
- Added four `recipes/{recipe}/tests` routes to `web.php` before the `{recipe}` wildcard (confirmed via `route:list`)
- Created stub `resources/js/pages/recipes/tests/index.tsx` (deviation) so Inertia's Vite manifest lookup succeeds in tests
- **RecipeTestTest suite: 13/13 GREEN** — full suite 194 tests with 0 regressions (191 pass, 3 skipped)

## Task Commits

Each task was committed atomically:

1. **Task 1: RecipeTestPolicy, FormRequests, and RecipeTestResource** - `d4ab994` (feat)
2. **Task 2: RecipeTestController + routes** - `7553dd6` (feat)

## Files Created/Modified

- `app/Policies/RecipeTestPolicy.php` - Owner-scoped policy checking recipe.user_id
- `app/Providers/AppServiceProvider.php` - Added Gate::policy(RecipeTest::class, RecipeTestPolicy::class)
- `app/Http/Requests/Recipes/StoreRecipeTestRequest.php` - Full validation with Rule::enum, Rule::requiredIf, photo mimes
- `app/Http/Requests/Recipes/UpdateRecipeTestRequest.php` - Partial update rules + deleted_photo_ids
- `app/Http/Resources/RecipeTestResource.php` - Serializes test with nested photos + URLs
- `app/Http/Controllers/Recipes/RecipeTestController.php` - Four thin actions with transactional photo upload
- `routes/web.php` - Four recipes.tests.{index,store,update,destroy} routes before {recipe} wildcard
- `resources/js/pages/recipes/tests/index.tsx` - Stub page for Inertia/Vite test compatibility

## Decisions Made

- `UpdateRecipeTestRequest` uses `sometimes` modifier — the Wave 0 test only sends a subset of fields for update, so `required` alone would fail validation on partial updates
- Stub `recipes/tests/index.tsx` created as deviation (Rule 3 — blocking issue) — Inertia's `assertInertia()` triggers Vite manifest lookup; page must exist in the build for the GET index test to pass
- `abort_unless($test->recipe_id === $recipe->id, 404)` scope-check on nested resource placed before policy check to return 404 before revealing ownership information

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Created stub frontend page for test compatibility**
- **Found during:** Task 2 verification
- **Issue:** `GET /recipes/{recipe}/tests renders the index page for the owner` test failed with 500/ViteException — `resources/js/pages/recipes/tests/index.tsx` did not exist in the Vite manifest, causing Inertia to throw `ViteException: Unable to locate file`
- **Fix:** Created stub `resources/js/pages/recipes/tests/index.tsx` (matching the pattern of `resources/js/pages/recipes/versions/show.tsx`) and ran `npm run build` to add it to the manifest
- **Files modified:** `resources/js/pages/recipes/tests/index.tsx`
- **Commit:** Included in `7553dd6`

**2. [Rule 1 - Bug] UpdateRecipeTestRequest uses sometimes modifier**
- **Found during:** Task 2 analysis of Wave 0 test (PUT test only sends tasting_notes + overall_rating)
- **Issue:** Plan spec says "Identical rules() to Store" but the update test sends only 2 of 4 required fields — strict required rules would fail validation on this test
- **Fix:** Added `sometimes` modifier to type/recipe_version_id/tested_at/overall_rating in UpdateRecipeTestRequest to allow partial updates while still validating when fields are present
- **Files modified:** `app/Http/Requests/Recipes/UpdateRecipeTestRequest.php`
- **Commit:** Included in `d4ab994`

## Issues Encountered

None beyond the two auto-fixed deviations above.

## User Setup Required

None.

## Next Phase Readiness

- All backend artifacts are in place for plan 04-03 (frontend test UI)
- `recipes/tests/index` Inertia page exists as a stub — plan 04-03 will replace it with the full UI
- Policy, FormRequests, Resource, and routes are all production-ready and fully tested
