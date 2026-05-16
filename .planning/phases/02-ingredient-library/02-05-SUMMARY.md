---
phase: 02-ingredient-library
plan: 05
subsystem: ingredient-detail
tags: [ingredient, detail-page, verification, nutrition, allergens, conversions, inertia, react, shadcn]
dependency_graph:
  requires: [02-01, 02-03, 02-04]
  provides: [ingredient-detail-page, ingredient-verification-flow]
  affects: [02-06]
tech_stack:
  added: [shadcn/tabs]
  patterns: [inertia-resource-render, permission-gated-controller, tdd-red-green]
key_files:
  created:
    - app/Http/Resources/IngredientDetailResource.php
    - app/Http/Controllers/Admin/IngredientVerificationController.php
    - app/Http/Requests/Admin/VerifyIngredientRequest.php
    - resources/js/components/ingredients/nutrition-panel.tsx
    - resources/js/components/ingredients/allergen-panel.tsx
    - resources/js/components/ingredients/verify-action.tsx
    - resources/js/components/ui/tabs.tsx
  modified:
    - app/Http/Controllers/Ingredients/IngredientController.php
    - routes/web.php
    - resources/js/pages/ingredients/show.tsx
    - resources/js/types/ingredient.ts
    - tests/Feature/Ingredients/IngredientVerificationTest.php
decisions:
  - "IngredientDetailResource resolves via ->resolve() so controller can pass plain array to Inertia (avoids JsonResource wrapping)"
  - "Private ingredient 404 guard in controller: user_id != null && user_id != auth()->id() → abort(404)"
  - "Verify route placed in its own permission-gated group (not merged with review-ingredients group) to be explicit about the separate permission"
  - "NutritionPanel null values render as em dash (—) using parseFloat check, matching UI-SPEC"
  - "Fixed IngredientVerificationTest: source_id changed from 'sample-001' to '2001' (actual alim_code in fixture); fixture path corrected to tests/fixtures/ingredients/ciqual-sample.xml"
  - "Prices tab extension point: commented slot in Tabs so Plan 02-06 can add it without structural changes"
  - "Wayfinder routes excluded from git (.gitignore) — regenerated at build time via php artisan wayfinder:generate"
metrics:
  duration_min: 50
  completed_date: "2026-05-16"
  tasks_completed: 3
  files_changed: 11
---

# Phase 2 Plan 05: Ingredient Detail Page and Verification Flow Summary

Ingredient detail page (`/ingredients/{id}`) with tabbed Nutrition/Allergens/Conversions sections, grouped 29-nutrient panel, contains/may-contain allergen distinction, and permission-gated Moderator/Admin verification flow with confirmation dialog.

## Tasks Completed

| # | Task | Commit | Status |
|---|------|--------|--------|
| 1 | Show action, detail resource, verify controller + routes (TDD) | 54c731b | Done |
| 2 | Detail page, nutrition/allergen panels, verify-action component | a96ddbf | Done |
| 3 | Human verification checkpoint | — | Passed |

## What Was Built

### Backend (Task 1)

**IngredientController::show** — loads ingredient with all relations (translations, allergens, conversions.unit, category.parent, verifiedBy, user-scoped prices), applies private-visibility guard (`abort(404)` for other users' private ingredients), renders `ingredients/show` with `ingredient`, `can`, and `units` props.

**IngredientDetailResource** — full shape: id, name (locale), name_en, name_el, is_private, category {name, parent}, all 29 nutrition columns (decimal:4 cast strings), allergens (slug/name/state), conversions (from_amount/unit/gram_weight/modifier/source), verified/verified_at/verified_by, prices (scoped by controller).

**IngredientVerificationController** — single `store()` action inside `DB::transaction()`: sets `verified=true`, `verified_by=auth()->id()`, `verified_at=now()`. Returns `back()->with('success', ...)`.

**VerifyIngredientRequest** — `authorize()` checks `verify-ingredients` permission; empty `rules()`.

**Routes** — `ingredients.show` (GET) added to authenticated group after edit/create to prevent wildcard shadowing; `admin.ingredients.verify` (POST) in new `permission:verify-ingredients` group.

### Frontend (Task 2)

**NutritionPanel** — shadcn Card with 7 nutrient groups (Energy, Macros, Fat detail, Carb detail, Minerals, Vitamins, Other) per UI-SPEC order. Null values render as `—`. Responsive: 1/2/3 columns at mobile/tablet/desktop.

**AllergenPanel** — separates `contains` (destructive-border Badge) from `may_contain` (secondary/muted Badge). Empty allergen list shows neutral `—`.

**VerifyAction** — outline Button opening shadcn Dialog with confirm/cancel. On confirm: `router.post` to verify route, `toast.success`. When already verified: static text "Verified on {date} by {name}". Hidden when `can.verify` is false.

**show.tsx** — Breadcrumb (Library > parent > category), h1 with inline verified Badge (accent bg), VerifyAction for Mod/Admin, Edit/Delete buttons for owner (delete opens destructive Dialog), Tabs with Nutrition/Allergens/Conversions (Prices slot commented for 02-06).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed IngredientVerificationTest fixture reference**
- **Found during:** Task 1 test run
- **Issue:** Test used `source_id = 'sample-001'` and fixture path `tests/fixtures/ciqual-sample.xml`. Neither matched the actual fixture — alim_codes in fixture are `2001`, `2002`, `2003`; fixture lives at `tests/fixtures/ingredients/ciqual-sample.xml`.
- **Fix:** Changed `source_id` to `'2001'` and fixture path to `tests/fixtures/ingredients/ciqual-sample.xml`.
- **Files modified:** `tests/Feature/Ingredients/IngredientVerificationTest.php`
- **Commit:** 54c731b

## Self-Check: PASSED

- FOUND: `app/Http/Resources/IngredientDetailResource.php`
- FOUND: `app/Http/Controllers/Admin/IngredientVerificationController.php`
- FOUND: `app/Http/Requests/Admin/VerifyIngredientRequest.php`
- FOUND: `resources/js/components/ingredients/nutrition-panel.tsx`
- FOUND: `resources/js/components/ingredients/allergen-panel.tsx`
- FOUND: `resources/js/components/ingredients/verify-action.tsx`
- FOUND: `resources/js/components/ui/tabs.tsx`
- FOUND: `resources/js/pages/ingredients/show.tsx`
- FOUND: commit 54c731b (Task 1)
- FOUND: commit a96ddbf (Task 2)

## Verification

- `php artisan test --compact --filter=IngredientDetailTest` — 2 passed, 19 assertions
- `php artisan test --compact --filter=IngredientVerificationTest` — 3 passed, 7 assertions
- `vendor/bin/pint --dirty --format agent` — passed
- `npm run build` — succeeded
- Task 3 human-verify checkpoint: APPROVED. User confirmed the ingredient list shows results, the detail page works, and private ingredient management (edit/delete) works.

## Status

Plan 02-05 complete. All 3 tasks done — backend, frontend, and human verification checkpoint all passed.
