---
phase: 06-publishing-public-library
plan: 02
subsystem: api
tags: [laravel, inertia, pest, form-request, api-resource, routing, wayfinder]

# Dependency graph
requires:
  - phase: 06-publishing-public-library/06-01
    provides: is_published/published_version_id/published_at columns, publishedVersion() relation, RecipePolicy with nullable ?User, Wave 0 RED test scaffold

provides:
  - PublishRecipeController (store: publish, destroy: unpublish) with FormRequest authorization
  - PublishRecipeRequest with version_id validation and sub-recipe-published prerequisite
  - LibraryController (index: filterable paginated library, show: slug-based public show with 404)
  - PublicRecipeListResource (author_name added, cost_per_portion omitted, reads publishedVersion)
  - PublicRecipeResource (nutrition/allergens from cached columns; no cost/notes/tests/conversation)
  - Guest-accessible library.index and library.show routes (outside auth group)
  - Auth-gated recipes.publish (POST) and recipes.unpublish (DELETE) routes
  - Wayfinder @/routes/library module and publish action helpers

affects:
  - 06-03 (library UI pages use these routes and resource shapes)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - HttpResponseException with JSON 422 for sub-recipe publish validation — testable regardless of request content-type
    - withValidator after-hook on FormRequest for post-rules cross-model checks
    - Guest library controller: where('is_published', true) IS the authorization (no Gate::authorize call)
    - PublicResource pattern: reads only frozen cached columns, never re-resolves ingredient_id live

key-files:
  created:
    - app/Http/Requests/Recipes/PublishRecipeRequest.php
    - app/Http/Controllers/Recipes/PublishRecipeController.php
    - app/Http/Controllers/Library/LibraryController.php
    - app/Http/Resources/PublicRecipeListResource.php
    - app/Http/Resources/PublicRecipeResource.php
  modified:
    - routes/web.php
    - tests/Feature/Library/PublishRecipeTest.php

key-decisions:
  - "Sub-recipe validation throws HttpResponseException with JSON 422 instead of redirecting back — ensures assertStatus(422) test passes regardless of request content-type, matches admin FormRequest pattern"
  - "Wave 0 test assertions fixed: assertSuccessful()->orStatus(302) replaced with assertRedirect() — publish/unpublish return 302 Inertia-style redirects, not 2xx responses"
  - "LibraryController has no Gate::authorize call — where('is_published', true) scope is the authorization per RESEARCH Pattern 4"
  - "PublicRecipeResource and PublicRecipeListResource read only publishedVersion cached columns — never re-resolve ingredient_id against live ingredients table per RESEARCH Anti-Patterns"

patterns-established:
  - "Guest-scope controller: no auth middleware, no Gate, just a base query scope that IS the authorization"
  - "HttpResponseException JSON 422 for critical FormRequest business-rule rejections (established in admin, now used in publish flow)"

requirements-completed: [PUB-02, PUB-03, PUB-04]

# Metrics
duration: 16min
completed: 2026-05-18
---

# Phase 06 Plan 02: Publish/Unpublish Backend Summary

**Publish lifecycle controller (POST/DELETE), guest library controller (index+show by slug), two public resources stripping cost/notes/tests, and route declarations turning all 17 Wave 0 RED tests GREEN**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-05-17T23:31:49Z
- **Completed:** 2026-05-17T23:47:51Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- `PublishRecipeRequest` validates version_id scoped to the recipe, walks snapshot for sub-recipe references, rejects publish with JSON 422 when any sub-recipe is unpublished
- `PublishRecipeController::store` sets is_published/published_version_id/published_at; `destroy` clears them; authorization via FormRequest (store) and Gate::authorize (destroy)
- `LibraryController::index` serves published-only filterable paginated library (6 filters: search, tag, cuisine, allergen, difficulty, max_total_time); `show` resolves slug to published recipe or 404
- `PublicRecipeListResource` and `PublicRecipeResource` carry nutrition/allergens from frozen cached columns; omit all cost/selling_price/notes/tests/conversation fields
- Routes declared correctly: library.index/show OUTSIDE auth group (guest-accessible), recipes.publish/unpublish INSIDE auth+verified group
- Wayfinder regenerated — `@/routes/library` module and `PublishRecipeController.ts` action helpers generated
- All 17 Library tests GREEN; full suite 245 passed, 0 failures

## Task Commits

Each task was committed atomically:

1. **Task 1: PublishRecipeRequest and PublishRecipeController** - `aeb91ef` (feat)
2. **Task 2: LibraryController, PublicRecipeListResource, PublicRecipeResource** - `81c5efb` (feat)
3. **Task 3: Route declarations, Wave 0 test fixes, Wayfinder regeneration** - `95383a1` (feat)

## Files Created/Modified
- `app/Http/Requests/Recipes/PublishRecipeRequest.php` - authorize via update policy, version_id validation with scoped Rule::exists, withValidator sub-recipe prerequisite check
- `app/Http/Controllers/Recipes/PublishRecipeController.php` - store (publish 3 columns) and destroy (clear 3 columns); RedirectResponse to recipes.show
- `app/Http/Controllers/Library/LibraryController.php` - index (6-filter chain, publishedVersion eager-load, orderByDesc published_at) and show (slug+is_published, 404 on miss)
- `app/Http/Resources/PublicRecipeListResource.php` - mirrors RecipeListResource but reads publishedVersion, omits cost_per_portion, adds author_name
- `app/Http/Resources/PublicRecipeResource.php` - public show payload: sections/nutrition/allergens from cached columns only; no cost/notes/tests fields
- `routes/web.php` - 4 new routes (library.index, library.show outside auth; recipes.publish, recipes.unpublish inside auth)
- `tests/Feature/Library/PublishRecipeTest.php` - fixed Wave 0 test assertions to match actual Inertia response behavior

## Decisions Made
- **HttpResponseException JSON 422 for sub-recipe validation**: the Wave 0 test asserts `assertStatus(422)`. Standard FormRequest validation on non-JSON Inertia requests returns 302 redirect back. Throwing `HttpResponseException` with a JSON 422 body matches the admin `AssignRoleRequest` pattern and makes the assertion pass correctly.
- **Wave 0 test assertion fixes**: `assertSuccessful()->orStatus(302)` is not a valid TestResponse chain — `assertSuccessful()` throws before `orStatus` runs. Changed to `assertRedirect(route('recipes.show', $recipe))` which correctly tests the 302 redirect behavior of Inertia mutation endpoints.
- **No Gate::authorize in LibraryController**: the `where('is_published', true)` query scope IS the access control — adding Gate would introduce false 403s for guests without accounts.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Wave 0 test assertions incompatible with Inertia redirect behavior**
- **Found during:** Task 3 (route declarations and test run)
- **Issue:** `assertSuccessful()->orStatus(302)` — `assertSuccessful()` asserts 2xx and throws immediately when it gets 302; `orStatus()` is never reached. Publish/unpublish correctly return 302 redirects for Inertia forms.
- **Fix:** Changed assertions to `assertRedirect(route('recipes.show', $recipe))` in both the publish and unpublish tests
- **Files modified:** `tests/Feature/Library/PublishRecipeTest.php`
- **Verification:** `php artisan test --compact --filter=PublishRecipeTest` — 7/7 pass
- **Committed in:** `95383a1` (Task 3 commit)

**2. [Rule 1 - Bug] Sub-recipe validation returned 302 redirect instead of 422**
- **Found during:** Task 3 (test run)
- **Issue:** Standard FormRequest `withValidator` adds errors to MessageBag and returns 302 redirect back for non-JSON requests. The Wave 0 test asserts `assertStatus(422)`. Additionally, the session error display code caused "Call to a member function all() on array" when assertion failed.
- **Fix:** Added `HttpResponseException` throw with JSON 422 in the `withValidator` after-hook when unpublished sub-recipes are found — same pattern as `AssignRoleRequest` in the admin system
- **Files modified:** `app/Http/Requests/Recipes/PublishRecipeRequest.php`
- **Verification:** `php artisan test --compact --filter="publish is rejected"` — 1/1 pass
- **Committed in:** `95383a1` (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - bugs in Wave 0 test assertions and validation response type)
**Impact on plan:** Both fixes necessary for test correctness; the HttpResponseException pattern is consistent with the established admin pattern and correctly models the API contract for the publish validation endpoint.

## Issues Encountered
- Wave 0 test used non-existent `orStatus()` method chain causing assertion cascade failure — fixed inline
- FormRequest `withValidator` + standard Inertia redirect pattern incompatible with `assertStatus(422)` test expectation — resolved by using `HttpResponseException` pattern consistent with admin form requests

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 03 (library UI) can now build against stable library.index, library.show, recipes.publish, and recipes.unpublish routes
- `PublicRecipeListResource` and `PublicRecipeResource` define the exact prop shapes Plan 03 components consume
- `@/routes/library` Wayfinder module and `PublishRecipeController.ts` action helpers are ready for import in Plan 03 React pages
- All 17 PUB-02/03/04 backend tests GREEN — Plan 03 adds UI, not more backend

---
*Phase: 06-publishing-public-library*
*Completed: 2026-05-18*
