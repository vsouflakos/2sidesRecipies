---
phase: 06-publishing-public-library
plan: 01
subsystem: database
tags: [laravel, migration, policy, pest, react, inertia, tdd]

# Dependency graph
requires:
  - phase: 03-recipe-core-metrics
    provides: Recipe model, RecipeVersion model, RecipePolicy base, recipe slug
  - phase: 04-recipe-tests
    provides: Established Wave 0 TDD pattern and stub-page convention

provides:
  - is_published, published_version_id, published_at columns on recipes table
  - publishedVersion() BelongsTo relation on Recipe model
  - RecipePolicy with nullable ?User view (guest-accessible published recipes) and publish-blocked delete
  - Wave 0 RED test scaffold for PUB-01/02/03 (PublishRecipeTest.php, 7 tests)
  - Wave 0 RED test scaffold for PUB-04 (LibraryBrowseTest.php, 10 tests)
  - library/index.tsx and library/show.tsx stub Inertia pages

affects:
  - 06-02 (PublishRecipeController and LibraryController need is_published column + policy)
  - 06-03 (library UI pages replace stubs, depend on publishedVersion relation)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Wave 0 TDD RED test scaffold — real assertions referencing routes that do not yet exist
    - Deferred-FK convention — published_version_id declared as plain unsignedBigInteger (no FK constraint)
    - Nullable policy user type — ?User $user in view() enables guest access without middleware changes

key-files:
  created:
    - database/migrations/2026_05_18_000001_add_publish_columns_to_recipes_table.php
    - tests/Feature/Library/PublishRecipeTest.php
    - tests/Feature/Library/LibraryBrowseTest.php
    - resources/js/pages/library/index.tsx
    - resources/js/pages/library/show.tsx
  modified:
    - app/Models/Recipe.php
    - app/Policies/RecipePolicy.php
    - database/factories/RecipeFactory.php

key-decisions:
  - "RecipePolicy::view uses ?User (nullable) so Laravel invokes the policy for guests and allows published recipe access — non-nullable User causes Laravel to skip policy for unauthenticated requests"
  - "published_version_id declared as plain unsignedBigInteger without FK constraint — follows Phase 3 deferred-FK pattern for nullable circular FKs"
  - "RecipeFactory default includes is_published => false so in-memory model reflects DB default immediately — avoids null vs false comparison failures in tests that do not call ->fresh()"
  - "Library stub pages (index.tsx, show.tsx) created before routes to prevent Vite manifest errors when Plan 02 routes are tested — established Phase 4 lesson"

patterns-established:
  - "Wave 0 RED scaffold: 17 tests execute without parse errors; 4 pass (schema/model/policy), 13 RED because routes/controllers not yet built"
  - "Deferred-FK nullable column: is_published logic lives on Recipe, not RecipeVersion"

requirements-completed: [PUB-01, PUB-02, PUB-03, PUB-04]

# Metrics
duration: 25min
completed: 2026-05-18
---

# Phase 06 Plan 01: Publish-State Foundation Summary

**Publish-state columns on recipes table with nullable-user RecipePolicy and a 17-test Wave 0 RED scaffold for all four PUB requirements**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-05-17T23:01:00Z
- **Completed:** 2026-05-17T23:26:38Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments
- Migration adds `is_published` (bool, default false), `published_version_id` (nullable bigint), `published_at` (nullable timestamp, indexed) to `recipes` table
- `RecipePolicy::view` extended to `?User` — published recipes are now visible to guests; private recipes remain owner-only
- `RecipePolicy::delete` blocks deletion while `is_published = true` — owner must unpublish first
- `PublishRecipeTest.php` (7 tests, 205 lines) and `LibraryBrowseTest.php` (10 tests, 265 lines) provide the Wave 0 RED scaffold for Plan 02
- Two stub Inertia pages (`library/index.tsx`, `library/show.tsx`) prevent Vite manifest errors when Plan 02 routes are tested

## Task Commits

Each task was committed atomically:

1. **Task 1: Add publish columns migration and extend Recipe model** - `db2ee3a` (feat)
2. **Task 2: Extend RecipePolicy for guest viewing and publish-blocked deletion** - `9787dc5` (feat)
3. **Task 3: Wave 0 RED test scaffold and library stub pages** - `e092901` (test)

## Files Created/Modified
- `database/migrations/2026_05_18_000001_add_publish_columns_to_recipes_table.php` - Adds three publish columns + published_at index; down() drops index then columns
- `app/Models/Recipe.php` - Added is_published/published_version_id/published_at to fillable and casts; added publishedVersion() BelongsTo relation
- `app/Policies/RecipePolicy.php` - view() uses ?User for guest access; delete() blocks while is_published = true
- `database/factories/RecipeFactory.php` - Added is_published/published_version_id/published_at defaults (false/null/null)
- `tests/Feature/Library/PublishRecipeTest.php` - 7 RED tests covering PUB-01/02/03
- `tests/Feature/Library/LibraryBrowseTest.php` - 10 RED tests covering PUB-04 (all 6 filters + 4 behavior assertions)
- `resources/js/pages/library/index.tsx` - Stub page (default-export component, Plan 03 replaces)
- `resources/js/pages/library/show.tsx` - Stub page (default-export component, Plan 03 replaces)

## Decisions Made
- **RecipeFactory gets explicit `is_published => false`**: the factory definition did not include the new column; the in-memory model had `null` instead of `false` causing the "is_published = false" test to fail. Adding the default to the factory is the correct approach (mirrors the DB default explicitly).
- **Nullable `?User` in RecipePolicy::view**: Laravel skips policy methods for unauthenticated requests when the type hint is non-nullable. The nullable type hint is the established Laravel 13 pattern for guest-accessible resources.
- **No FK constraint on published_version_id**: follows the Phase 3 deferred-FK convention; a nullable column pointing to recipe_versions never needs a hard constraint since recipe_versions rows are retained for historical tracking.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] RecipeFactory missing is_published default**
- **Found during:** Task 3 (Wave 0 RED test scaffold)
- **Issue:** `Recipe::factory()->create()` returned a model with `is_published = null` because the factory definition predated the migration. The `is_published = false` test failed.
- **Fix:** Added `'is_published' => false`, `'published_version_id' => null`, `'published_at' => null` to `RecipeFactory::definition()`
- **Files modified:** `database/factories/RecipeFactory.php`
- **Verification:** `php artisan test --compact --filter="a freshly created recipe"` passes
- **Committed in:** `e092901` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug in factory default)
**Impact on plan:** Factory fix is necessary for test correctness. No scope creep.

## Issues Encountered
- None — migration, policy, and stubs executed cleanly; only the factory default gap required an inline fix.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 02 can now build `PublishRecipeController` (POST publish, DELETE unpublish) and `LibraryController` (index + show) with `is_published` column and policy in place
- 17-test RED scaffold gives Plan 02 a concrete GREEN target for all PUB requirements
- Library stub pages in the Vite manifest prevent manifest errors when Plan 02 route tests run
- The `publishedVersion()` relation on Recipe is ready for use by LibraryController queries

---
*Phase: 06-publishing-public-library*
*Completed: 2026-05-18*
