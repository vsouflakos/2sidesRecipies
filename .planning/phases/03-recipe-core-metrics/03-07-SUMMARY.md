---
phase: 03-recipe-core-metrics
plan: "07"
subsystem: recipe-versioning-ui
tags: [react, inertia, versioning, sub-recipe, recipe-builder]
dependency_graph:
  requires: ["03-05", "03-06"]
  provides: ["sub-recipe-line-row", "save-version-dialog", "version-history-sheet", "version-compare", "recall-wiring"]
  affects: ["recipes/show.tsx", "recipe-builder components"]
tech_stack:
  added: []
  patterns: ["aria-disabled for disabled-but-focusable Recall button", "explicit pin update via Dialog", "sequence-guarded Recall with 409 handling"]
key_files:
  created:
    - resources/js/components/recipes/recipe-builder/sub-recipe-line-row.tsx
    - resources/js/components/recipes/recipe-builder/save-version-dialog.tsx
    - resources/js/components/recipes/recipe-builder/version-history-sheet.tsx
    - resources/js/components/recipes/version-compare.tsx
  modified:
    - resources/js/components/recipes/recipe-builder/ingredient-line-row.tsx
    - resources/js/pages/recipes/show.tsx
    - resources/js/pages/recipes/versions/compare.tsx
    - resources/js/types/recipe.ts
decisions:
  - "Sub-recipe update badge is a clickable <button> wrapping a Badge rather than a plain Badge — enables keyboard accessibility and proper click target without the `disabled` attribute workaround"
  - "edit_sequence added to RecipeBuilderData TS type to support Recall sequence guard client-side"
  - "Version history Sheet uses internal selectedVersionIds state for two-version compare selection — navigates automatically when two are chosen"
metrics:
  duration_minutes: 13
  completed_date: "2026-05-16"
  tasks_completed: 3
  files_modified: 8
---

# Phase 03 Plan 07: Versioning and Sub-recipe UI Summary

Sub-recipe lines, Save Version dialog, Recall button, version-history Sheet, and side-by-side version compare page — all wired into the recipe builder.

## Tasks Completed

| # | Task | Commit | Key Files |
|---|------|--------|-----------|
| 1 | sub-recipe-line-row with version pin + update cue | ba252ec | sub-recipe-line-row.tsx, ingredient-line-row.tsx |
| 2 | save-version-dialog, version-history-sheet, Recall wiring | 7b040fb | save-version-dialog.tsx, version-history-sheet.tsx, show.tsx |
| 3 | version-compare component + compare page | a7ccff8 | version-compare.tsx, versions/compare.tsx |

## What Was Built

**SubRecipeLineRow** (`sub-recipe-line-row.tsx`): Full sub-recipe line variant — name, pinned `Badge v{N}` (secondary), gram qty Input, accent "Update available" Badge (when `latest_version_number > version_number`). Clicking the badge opens a Dialog with "Update sub-recipe pin?" title, versioned body copy, and "Update to v{N}" / "Keep v{N}" buttons. Pin never updates automatically — explicit chef action only. Inline `circularError` prop renders below the row. `IngredientLineRow` now delegates to `SubRecipeLineRow` when `sub_recipe_version_id` is set.

**SaveVersionDialog** (`save-version-dialog.tsx`): Dialog with optional ≤140-char Textarea for change note. "Save Version" (primary) and "Save without note" (ghost) both POST to `recipes.versions.store` with partial reload `only: ['versions', 'draft', 'metrics']`. Success → sonner toast "Version v{N} saved." Failure → sonner error, draft preserved.

**VersionHistorySheet** (`version-history-sheet.tsx`): Sheet side="right" 320px. Lists all versions formatted "v{N} · {date} · {note or —}" with a "Current" Badge. Per-row "Compare" Button. Selecting two versions navigates to `recipes.versions.compare?a={id}&b={id}` automatically.

**Recall wiring** (`show.tsx`): Recall `Button variant="outline"` in builder header. No confirmation. Sends `expected_sequence: draft.edit_sequence`. On 409 → sonner conflict toast + `recallDisabled = true`. When `edit_sequence === 0`, button renders with `aria-disabled="true"` (not `disabled`) and a Tooltip "Nothing to undo" (accessibility contract).

**VersionCompare** (`version-compare.tsx`): Side-by-side two-column diff. Changed rows highlighted `bg-yellow-50` / `dark:bg-yellow-900/20` AND get a leading `ArrowRightIcon` (color not sole signal — accessibility). Column headers show version number and committed date.

**Compare page** (`recipes/versions/compare.tsx`): Inertia page replacing the empty stub. Props `versionA`, `versionB`, `diff`. Head title "Comparing v{A} → v{B}". Back link to recipe builder.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

All 5 created/modified files exist on disk. All 3 task commits found in git log (ba252ec, 7b040fb, a7ccff8). Build clean. RecipeVersionTest: 4/4 pass. RecipeDraftTest: 4/4 pass. Full recipes test suite: 50/53 pass (3 pre-existing skips).
