---
phase: 04-recipe-tests
plan: 03
subsystem: frontend
tags: [react, inertia, typescript, shadcn, i18n, photo-upload, lightbox, recipe-tests]

# Dependency graph
requires:
  - phase: 04-recipe-tests
    plan: 02
    provides: RecipeTestController routes, RecipeTestResource, Wayfinder-generated actions

provides:
  - TypeScript contracts for all test shapes (recipe-test.ts)
  - RatingDimensionRow component with accessible score input
  - TestPhotoGrid with upload drop zone, object URL management, lightbox dialog
  - TestRecordModal — full trial/experiment form, forceFormData multipart submit
  - TestCard — type/verdict badges, color-coded score, tasting notes preview, delete dialog
  - recipes/tests/index Inertia page with empty state, stats bar, breadcrumb
  - EN/EL i18n strings for all test UI copy

affects: [04-04-recipe-tests-navigation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - TestPhotoGrid dual-mode (upload vs display) via discriminated union props
    - useEffect cleanup for URL.revokeObjectURL prevents memory leaks on staged file previews
    - forceFormData: true on useForm.post/put for multipart even when no files staged
    - form.transform() adds _method: PUT for edit submissions (Inertia method spoofing)
    - Separate deletedPhotoIds state array (not in form) tracks existing photos to remove on edit
    - Verdict badge uses inline className overrides (bg-accent) rather than a new variant — avoids extending shadcn Badge unnecessarily

key-files:
  created:
    - resources/js/types/recipe-test.ts
    - resources/js/components/recipes/rating-dimension-row.tsx
    - resources/js/components/recipes/test-photo-grid.tsx
    - resources/js/components/recipes/test-record-modal.tsx
    - resources/js/components/recipes/test-card.tsx
  modified:
    - resources/js/pages/recipes/tests/index.tsx
    - lang/en/app.php
    - lang/el/app.php

key-decisions:
  - "Separate deletedPhotoIds state (not in form.data) — avoids serialization issues and keeps form.transform() clean for the _method: PUT injection"
  - "TestPhotoGrid dual-mode via discriminated union (mode: upload | display) — single component, two distinct behaviors, no prop collision"
  - "Verdict Worked badge uses bg-accent text-accent-foreground inline (not a new Badge variant) per UI-SPEC reservation of accent for positive signal only"
  - "layout property on RecipeTestsIndex uses breadcrumbs function that accepts props for dynamic recipe name"

# Metrics
duration: 10min
completed: 2026-05-17
---

# Phase 4 Plan 03: Recipe Tests Frontend Summary

**Full test recording UI — TypeScript contracts, RatingDimensionRow, TestPhotoGrid with lightbox, TestRecordModal (trial/experiment), TestCard with delete dialog, tests index page with empty state, and complete EN/EL i18n**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-05-17T11:18:45Z
- **Completed:** 2026-05-17T11:28:56Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments

- Created `resources/js/types/recipe-test.ts` with full TypeScript contracts: `RecipeTest`, `RecipeTestsIndexProps`, `RatingDimension`, `ChangeRow`, `RecipeTestPhoto`, `TestVersionOption`, `DEFAULT_RATING_DIMENSIONS`, `MAX_TEST_PHOTOS`
- Created `RatingDimensionRow` — 44px tall row, editable for custom dimensions, `aria-label` on score input, remove button for custom only
- Created `TestPhotoGrid` — upload mode with dashed drop zone (drag-and-drop + click), file validation (mime + size), `URL.createObjectURL` previews, `useEffect` cleanup via `URL.revokeObjectURL`, photo limit tooltip. Display mode with 4-thumbnail strip and lightbox dialog with prev/next arrows wrapped in `Tooltip`
- Created `TestRecordModal` — `ToggleGroup` type selector (Trial/Experiment), version `Select`, date input, overall rating, tasting notes, per-dimension `RatingDimensionRow` list with Add dimension, conditional Experiment section (hypothesis, outcome, verdict, change rows), `TestPhotoGrid` in upload mode. Submit via `useForm` with `forceFormData: true`, `form.transform()` adds `_method: PUT` for edits
- Created `TestCard` — type badge, version label, tested date, `DropdownMenu` actions (Edit/Delete) with `Tooltip`, overall score color-coded by range, default dimension scores (hidden on mobile), verdict badge with UI-SPEC color mapping, `line-clamp-2` tasting notes, `TestPhotoGrid` display mode, delete `Dialog` with focus on dismiss button per WCAG destructive pattern
- Replaced stub `resources/js/pages/recipes/tests/index.tsx` with full Inertia page: breadcrumb, page heading, back link, stats bar, Record test CTA, tests list mapping to `TestCard`, empty state with heading/body/CTA, `TestRecordModal` at page level
- Added `tests` key group to `lang/en/app.php` and `lang/el/app.php` — all 50 keys from the Copywriting Contract, Greek translations with placeholders intact

## Task Commits

1. **Task 1: TypeScript contracts + RatingDimensionRow + TestPhotoGrid** - `d70aa33`
2. **Task 2: TestRecordModal — mode-switched record/edit dialog** - `3e90732`
3. **Task 3: TestCard, tests index page, i18n strings** - `46049e9`

## Files Created/Modified

- `resources/js/types/recipe-test.ts` — TypeScript contracts for all test shapes
- `resources/js/components/recipes/rating-dimension-row.tsx` — Single rating dimension row with score input
- `resources/js/components/recipes/test-photo-grid.tsx` — Dual-mode photo grid with upload + lightbox
- `resources/js/components/recipes/test-record-modal.tsx` — Full record/edit dialog with multipart submit
- `resources/js/components/recipes/test-card.tsx` — Test display card with actions and delete dialog
- `resources/js/pages/recipes/tests/index.tsx` — Full Inertia page (replaced stub)
- `lang/en/app.php` — Added `tests` key group (50 EN strings)
- `lang/el/app.php` — Added `tests` key group (50 EL strings)

## Decisions Made

- Separate `deletedPhotoIds` state (not in `form.data`) — avoids serialization issues and keeps `form.transform()` clean for the `_method: PUT` injection on edit
- `TestPhotoGrid` dual-mode via discriminated union (`mode: 'upload' | 'display'`) — single component, two distinct behaviors, no prop collision
- Verdict "Worked" badge uses `bg-accent text-accent-foreground` inline class override (not a new Badge variant) per UI-SPEC accent reservation for positive signal only
- Breadcrumb on `RecipeTestsIndex` uses function form `layout.breadcrumbs = (props) => [...]` for dynamic recipe name interpolation

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `resources/js/types/recipe-test.ts` — FOUND
- `resources/js/components/recipes/rating-dimension-row.tsx` — FOUND
- `resources/js/components/recipes/test-photo-grid.tsx` — FOUND
- `resources/js/components/recipes/test-record-modal.tsx` — FOUND
- `resources/js/components/recipes/test-card.tsx` — FOUND
- `resources/js/pages/recipes/tests/index.tsx` — FOUND (full page, not stub)
- Commit `d70aa33` — FOUND
- Commit `3e90732` — FOUND
- Commit `46049e9` — FOUND
- `npm run build` — EXIT 0
- `RecipeTestTest` 13/13 — GREEN
