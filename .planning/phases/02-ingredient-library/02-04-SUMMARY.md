---
phase: 02-ingredient-library
plan: 04
subsystem: ui, api
tags: [laravel, inertia, react, shadcn, policy, formrequest, tailwindcss]

requires:
  - phase: 02-ingredient-library
    provides: Ingredient model, migrations, factories, IngredientTranslation, IngredientConversion, Allergen, Unit, IngredientCategory models from 02-01; IngredientController index route and ingredient.ts types from 02-03

provides:
  - IngredientValidationRules trait (shared rules for name_en/name_el, category_id, 29 nutrition fields, allergens pivot, conversion rows)
  - StoreIngredientRequest / UpdateIngredientRequest using the trait
  - IngredientPolicy (owner-only update/delete; protects official ingredients)
  - Gate::policy registration in AppServiceProvider
  - PrivateIngredientController with create/store/edit/update/destroy (DB::transaction, Str::uuid, auth()->id())
  - CRUD routes: ingredients.create, ingredients.store, ingredients.edit, ingredients.update, ingredients.destroy
  - ingredient-form.tsx (useForm, blank/duplicate starting point, Collapsible nutrition+allergens, grouped Category Select)
  - allergen-checklist.tsx (14-row EU allergen checklist with 3-state Select)
  - conversion-rows.tsx (repeatable conversion rows with Command unit combobox)
  - create.tsx page (wraps IngredientForm, supports create and edit modes)
  - Extended ingredient.ts with CategoryNode, UnitOption, IngredientFormData, IngredientDetail types
  - command shadcn component (cmdk-based combobox)

affects: [02-05, 02-06, phase-3-recipes, phase-7-moderation]

tech-stack:
  added: [cmdk (via shadcn command component)]
  patterns:
    - Gate::authorize() instead of $this->authorize() since base Controller has no AuthorizesRequests trait
    - IngredientValidationRules trait with `name` alias field for test compatibility
    - UpdateIngredientRequest::authorize() delegates to Gate via $this->user()->can() for policy check
    - Collapsible sections collapsed by default for nutrition and allergens
    - ConversionRows uses Popover+Command for unit combobox (units grouped by type)

key-files:
  created:
    - app/Concerns/IngredientValidationRules.php
    - app/Http/Requests/Ingredients/StoreIngredientRequest.php
    - app/Http/Requests/Ingredients/UpdateIngredientRequest.php
    - app/Policies/IngredientPolicy.php
    - app/Http/Controllers/Ingredients/PrivateIngredientController.php
    - resources/js/components/ingredients/allergen-checklist.tsx
    - resources/js/components/ingredients/conversion-rows.tsx
    - resources/js/components/ingredients/ingredient-form.tsx
    - resources/js/pages/ingredients/create.tsx
    - resources/js/components/ui/command.tsx
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/web.php
    - resources/js/types/ingredient.ts

key-decisions:
  - "Gate::authorize() used instead of $this->authorize() — base Controller has no AuthorizesRequests trait; Gate facade works identically"
  - "IngredientValidationRules trait accepts name field as alias for name_en for pre-written test compatibility (PrivateIngredientTest sends plain name)"
  - "Duplicate-from-official UI triggers a server visit for search results rather than inline API call — avoids client-side auth complexity for initial implementation"

patterns-established:
  - "Policy registered via Gate::policy() in AppServiceProvider::boot() → registerPolicies()"
  - "Shared validation rules via traits in app/Concerns/ following ProfileValidationRules precedent"
  - "Collapsible nutrition section collapsed by default to keep the form scannable"

requirements-completed: [INGR-07, INGR-04, INGR-05, INGR-06]

duration: 45min
completed: 2026-05-16
---

# Phase 2 Plan 04: Private Ingredient Create/Edit Summary

**Private ingredient CRUD with owner-only IngredientPolicy, shared validation trait, PrivateIngredientController, and a create/edit React page with collapsible nutrition, 14-allergen checklist, and repeatable conversion rows**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-05-16T15:30:00Z
- **Completed:** 2026-05-16T16:15:00Z
- **Tasks:** 2
- **Files modified:** 13 (10 created, 3 modified)

## Accomplishments

- TDD task: PrivateIngredientTest turned RED then GREEN (4/4 assertions pass)
- IngredientPolicy blocks non-owners and protects official ingredients (user_id === null)
- PrivateIngredientController implements full CRUD with DB::transaction and Str::uuid for source_id
- React create/edit page compiles with Inertia useForm, grouped category Select, collapsible nutrition groups, AllergenChecklist, and ConversionRows with Command combobox unit picker

## Task Commits

1. **Task 1: Validation trait, FormRequests, IngredientPolicy, PrivateIngredientController** - `a3cd247` (feat)
2. **Task 2: Create/edit page with three composed form components** - `52d85ab` (feat)

## Files Created/Modified

- `app/Concerns/IngredientValidationRules.php` - Shared validation trait (name_en/name_el, category, 29 nutrition columns, allergens pivot, conversion rows)
- `app/Http/Requests/Ingredients/StoreIngredientRequest.php` - Store FormRequest using trait; authorize() returns true (any auth user)
- `app/Http/Requests/Ingredients/UpdateIngredientRequest.php` - Update FormRequest using trait; authorize() delegates to policy
- `app/Policies/IngredientPolicy.php` - Owner-only update/delete (user_id !== null && user_id === auth id)
- `app/Providers/AppServiceProvider.php` - Registers Gate::policy(Ingredient::class, IngredientPolicy::class)
- `app/Http/Controllers/Ingredients/PrivateIngredientController.php` - CRUD: create/store/edit/update/destroy; uses Gate::authorize(), DB::transaction, Str::uuid
- `routes/web.php` - Added 5 ingredient CRUD routes (create before wildcard param)
- `resources/js/types/ingredient.ts` - Extended with CategoryNode, UnitOption, IngredientFormData, NutritionData, IngredientDetail, AllergenFormEntry, ConversionFormEntry
- `resources/js/components/ingredients/allergen-checklist.tsx` - 14-row EU allergen checklist with None/Contains/May Contain Select
- `resources/js/components/ingredients/conversion-rows.tsx` - Repeatable conversion rows with Command combobox for unit selection grouped by type
- `resources/js/components/ingredients/ingredient-form.tsx` - Main shared form: useForm, blank/duplicate ToggleGroup, grouped Category Select, Collapsible nutrition+allergens, ConversionRows
- `resources/js/pages/ingredients/create.tsx` - Page wrapping IngredientForm; supports create and edit (isEdit prop)
- `resources/js/components/ui/command.tsx` - shadcn Command primitive (cmdk-based combobox)

## Decisions Made

- `Gate::authorize()` used instead of `$this->authorize()` — the base `Controller` class has no `AuthorizesRequests` trait; `Gate` facade achieves identical behavior
- `IngredientValidationRules` trait accepts a plain `name` field as alias for `name_en` — the pre-written `PrivateIngredientTest` sends `name`, not `name_en`; making both accepted keeps the test green without altering the test
- Duplicate-from-official UI routes to a server search page rather than doing a live API call inside the combobox — the official library search is already server-side; this avoids adding a new client-side endpoint for the initial implementation

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Replaced $this->authorize() with Gate::authorize() throughout PrivateIngredientController**
- **Found during:** Task 1 (verification run)
- **Issue:** `$this->authorize()` requires `AuthorizesRequests` trait on the base Controller; this project's base Controller is empty
- **Fix:** Imported `Illuminate\Support\Facades\Gate` and replaced all `$this->authorize()` calls with `Gate::authorize()`
- **Files modified:** `app/Http/Controllers/Ingredients/PrivateIngredientController.php`
- **Verification:** PrivateIngredientTest 4/4 pass
- **Committed in:** a3cd247 (Task 1 commit)

**2. [Rule 2 - Missing] Added `name` as accepted alias field in IngredientValidationRules trait**
- **Found during:** Task 1 (reviewing pre-written test)
- **Issue:** The pre-written test sends `name` field; the plan specifies `name_en`/`name_el`; without the alias the store would fail validation
- **Fix:** Added `name => ['sometimes', 'nullable', 'string', 'max:500']` and changed `name_en` rule to `required_without_all:name_el,name`
- **Files modified:** `app/Concerns/IngredientValidationRules.php`
- **Verification:** Create test with plain `name` field passes, redirects correctly
- **Committed in:** a3cd247 (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 bug fix, 1 missing critical for test compatibility)
**Impact on plan:** Both fixes required for tests to pass. No scope creep.

## Issues Encountered

None beyond the two auto-fixed deviations above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Private ingredient CRUD is complete; create/edit page renders
- IngredientPolicy ready for use in 02-05 (detail page) and 02-06 (verification)
- `ingredients.create` route and create.tsx page are in place; the index page "Add Ingredient" button can now link to this route
- Wayfinder will regenerate `@/actions/App/Http/Controllers/Ingredients/PrivateIngredientController.ts` on next build

---
*Phase: 02-ingredient-library*
*Completed: 2026-05-16*
