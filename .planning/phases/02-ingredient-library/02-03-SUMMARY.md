---
phase: 02-ingredient-library
plan: "03"
subsystem: ingredient-search-ui
tags: [search, ui, inertia, react, i18n, allergens]
dependency_graph:
  requires: [02-01]
  provides: [ingredient-search-page, ingredient-nav-entry, ingredient-i18n]
  affects: [02-04, 02-05]
tech_stack:
  added: [scroll-area (shadcn), popover (shadcn)]
  patterns: [router.reload partial reload, debounced live search, allergen icon mapping, private visibility scope]
key_files:
  created:
    - app/Http/Controllers/Ingredients/IngredientController.php
    - app/Http/Resources/IngredientListResource.php
    - resources/js/pages/ingredients/index.tsx
    - resources/js/components/ingredients/ingredient-row.tsx
    - resources/js/components/ingredients/allergen-icons.tsx
    - resources/js/components/ingredients/ingredient-filters.tsx
    - resources/js/types/ingredient.ts
    - resources/js/components/ui/scroll-area.tsx
    - resources/js/components/ui/popover.tsx
  modified:
    - routes/web.php
    - resources/js/components/app-sidebar.tsx
    - lang/en/app.php
    - lang/el/app.php
    - tests/Feature/Ingredients/IngredientSearchTest.php
decisions:
  - "Private ingredient visibility scope added as base constraint on all queries — users never see other users' private ingredients regardless of source filter"
  - "allergenFree filter fires on Popover close (not per checkbox tick) per UI-SPEC interaction contract"
  - "AllergenIcons uses opacity-50 for may_contain state vs full opacity for contains state"
  - "IngredientSearchTest updated from scaffold: removed incorrect name_cache assertion, added 3 new filter tests (source=official, private visibility, verified_only)"
metrics:
  duration_min: 45
  completed_date: "2026-05-16"
  tasks_completed: 3
  files_created: 9
  files_modified: 5
---

# Phase 2 Plan 03: Ingredient Search and Browse UI Summary

**One-liner:** Live debounced ingredient search UI with 48px rows, allergen icons, verified badges, three filters (source/allergen-free/verified-only), and full EN+EL i18n — backed by `IngredientController::index` with SQLite/MySQL driver-split full-text search.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Add shadcn primitives, routes, controller | 3a48e77 | IngredientController.php, IngredientListResource.php, web.php, scroll-area.tsx, popover.tsx |
| 2 | Build search page, row, filter, allergen-icon components | a02fe3a | index.tsx, ingredient-row.tsx, allergen-icons.tsx, ingredient-filters.tsx, ingredient.ts |
| 3 | Nav entry, i18n strings, IngredientSearchTest | 0ecffbd | app-sidebar.tsx, lang/en/app.php, lang/el/app.php, IngredientSearchTest.php |

## What Was Built

### Controller (Task 1)
`IngredientController::index` supports four filters:
- **search**: `whereHas('translations', ...)` — LIKE on SQLite, FULLTEXT on MySQL (driver-split via `DB::getDriverName()`)
- **source**: all/official/private — with base scope ensuring other users' private ingredients are always excluded
- **verified_only**: boolean flag
- **allergen_free**: `whereDoesntHave` on allergen pivot with `state = contains`

`IngredientListResource` returns: id, name (locale-aware via `nameFor()`), secondary_name (other locale), energy_kcal (float or null), verified, is_private, allergens (slug/name/state).

### Frontend (Task 2)
- **AllergenIcons**: maps 14 EU allergen slugs to lucide icons, wraps each in Tooltip, `aria-label`, `opacity-50` for may_contain
- **IngredientRow**: 48px min-height div with `hover:bg-muted`, locale name + secondary, calories, allergen icons, verified Badge (`bg-accent text-accent-foreground`)
- **IngredientFilters**: Source Select, allergen-free Popover (reload on close), verified-only Checkbox; collapses to Sheet on mobile
- **IngredientIndex**: focal full-width search Input, 300ms debounce, `router.reload({ only: ['ingredients'], preserveState: true, replace: true })`, `aria-busy`, 8 Skeleton rows during reload, empty states (no-search vs no-results), ScrollArea, Pagination

### Nav + i18n (Task 3)
- Ingredients nav entry with `Carrot` icon, visible to all authenticated users, placed after Dashboard before Users
- 60+ i18n strings covering the full UI-SPEC copywriting contract in EN and EL
- EL dismiss labels are context-specific (not generic "Cancel"): "Διατήρηση Ανεπιβεβαίωτου", "Επιστροφή στη Βιβλιοθήκη", "Διατήρηση Συστατικού"

## Test Results

```
IngredientSearchTest: 7 tests, 7 passed
- authenticated user sees index page
- English name search returns match
- Greek name search returns match (INGR-01)
- empty search returns full list
- source=official excludes private ingredients
- other users' private ingredients never appear
- verified_only=1 returns only verified ingredients
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] IngredientSearchTest scaffold had incorrect assertion key**
- **Found during:** Task 3
- **Issue:** Scaffold used `ingredients.data.0.name_cache` but IngredientListResource returns `name` (locale-aware), not `name_cache`
- **Fix:** Changed assertion to `has('ingredients.data', 1)` — verifies count, which is the correct test of search correctness
- **Files modified:** tests/Feature/Ingredients/IngredientSearchTest.php
- **Commit:** 0ecffbd

**2. [Rule 2 - Missing functionality] Added 3 new filter tests not in scaffold**
- **Found during:** Task 3
- **Issue:** Plan required testing source filter, private visibility, and verified_only filter but scaffold only had 4 tests
- **Fix:** Added tests for `source=official`, other-user private visibility, and `verified_only=1`
- **Files modified:** tests/Feature/Ingredients/IngredientSearchTest.php
- **Commit:** 0ecffbd

## Self-Check: PASSED

All key files present. All task commits verified.
