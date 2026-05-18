---
phase: 07-ingredient-moderation
plan: 01
subsystem: database
tags: [eloquent, enum, notifications, migrations, policy, pest]

# Dependency graph
requires:
  - phase: 02-ingredient-library
    provides: Ingredient model with user_id, verified_by/verified_at, source/source_id unique index, IngredientPolicy
  - phase: 01-foundation
    provides: User model with Notifiable trait, RolesAndPermissionsSeeder, review-ingredients permission

provides:
  - SubmissionStatus backed enum (private|submitted|approved|rejected|withdrawn)
  - ingredient_submissions table with FIFO indexes and audit columns
  - submission_status column on ingredients table (default private)
  - notifications table (Laravel database channel ready)
  - IngredientSubmission model with casts, relations, HasFactory
  - Ingredient model updated with submission_status cast, submissions/latestSubmission relations, isPendingReview() helper
  - IngredientPolicy extended with submit/withdraw abilities and frozen-while-pending guard on update/delete
  - IngredientSubmissionFactory and submitted()/rejected() states on IngredientFactory
  - Wave 0 RED test suite (19 tests, 17 RED for correct reason: missing routes/controllers)
  - IngredientDecisionNotification stub for test reference
  - admin/ingredients/show.tsx stub for Vite manifest resolution

affects:
  - 07-02 (backend controllers and routes — GREEN target for this RED suite)
  - 07-03 (frontend UI components — references SubmissionStatus and submission_status column)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - PHP backed enum (SubmissionStatus) + Eloquent cast for ingredient moderation state
    - isPendingReview() helper on model + frozen-while-pending in Policy methods
    - IngredientSubmission history table pattern (immutable rows for audit trail)
    - Notification::fake() + assertSentTo() for testing database channel notifications
    - Wave 0 RED tests: real assertions failing due to absent routes, not schema/parse errors

key-files:
  created:
    - app/Enums/SubmissionStatus.php
    - app/Models/IngredientSubmission.php
    - app/Notifications/IngredientDecisionNotification.php
    - database/factories/IngredientSubmissionFactory.php
    - database/migrations/2026_05_18_000002_create_notifications_table.php
    - database/migrations/2026_05_18_000003_create_ingredient_submissions_table.php
    - database/migrations/2026_05_18_000004_add_submission_status_to_ingredients_table.php
    - tests/Feature/Ingredients/IngredientSubmissionTest.php
    - tests/Feature/Ingredients/IngredientModerationTest.php
    - tests/Feature/Ingredients/IngredientPromotionTest.php
    - resources/js/pages/admin/ingredients/show.tsx
  modified:
    - app/Models/Ingredient.php
    - app/Policies/IngredientPolicy.php
    - database/factories/IngredientFactory.php

key-decisions:
  - "SubmissionStatus enum includes Withdrawn case so ingredient_submissions table can record withdrawal events without soft-deleting the row — preserves full audit history"
  - "submit() policy uses explicit ownership check + in_array([Private, Rejected]) with strict=true to avoid the operator-precedence bug in the RESEARCH draft"
  - "IngredientDecisionNotification created as a stub now (not in 07-02) so test files can reference the class without parse errors — avoids the Phase 4 Vite manifest lesson"
  - "admin/ingredients/show.tsx stub created now so assertInertia() can resolve it during test runs"

patterns-established:
  - "isPendingReview() model helper + frozen-while-pending guard in Policy update/delete methods — consistent with RecipePolicy::delete frozen-while-published pattern"
  - "ingredient_submissions history table: immutable rows per submission attempt, reviewed_by/reviewed_at on the submission row for audit trail"
  - "submission_status column on ingredients as cheap denormalized cache — avoids correlated subquery on every list query"

requirements-completed: [INGR-09, INGR-10, INGR-11]

# Metrics
duration: 40min
completed: 2026-05-18
---

# Phase 7 Plan 01: Schema and RED Test Foundation Summary

**SubmissionStatus enum, ingredient_submissions history table, submission_status column, frozen-while-pending IngredientPolicy, and 19-test Wave 0 RED suite covering INGR-09/10/11**

## Performance

- **Duration:** 40 min
- **Started:** 2026-05-18T13:01:00Z
- **Completed:** 2026-05-18T13:41:00Z
- **Tasks:** 3
- **Files modified:** 14

## Accomplishments

- Schema foundation: SubmissionStatus backed enum, ingredient_submissions history table (with FIFO indexes and audit columns), submission_status column on ingredients, and notifications table all migrated cleanly via `migrate:fresh --seed`
- IngredientPolicy extended with submit/withdraw abilities and frozen-while-pending guard — explicit in_array check with strict=true fixes the precedence bug in the RESEARCH draft
- Wave 0 RED test suite: 19 tests across 3 files; 17 fail for correct reason (routes/controllers absent); 0 parse errors; 0 schema errors; Notification::fake() + assertSentTo patterns in place

## Task Commits

Each task was committed atomically:

1. **Task 1: SubmissionStatus enum, three migrations, IngredientSubmission model + factory states** - `28112ae` (feat)
2. **Task 2: IngredientPolicy submit/withdraw abilities + frozen-while-pending guard** - `af8e93c` (feat)
3. **Task 3: Wave 0 RED test suite + admin review stub page** - `99a1086` (test)

**Plan metadata:** (docs commit after SUMMARY)

## Files Created/Modified

- `app/Enums/SubmissionStatus.php` — Five-case backed enum: Private, Submitted, Approved, Rejected, Withdrawn
- `app/Models/IngredientSubmission.php` — Eloquent model with casts, fillable, BelongsTo relations (ingredient, submittedBy, reviewedBy)
- `app/Notifications/IngredientDecisionNotification.php` — Database channel notification stub with toDatabase() returning ingredient_id/name/decision/notes
- `app/Models/Ingredient.php` — Added submission_status to fillable + casts, submissions/latestSubmission relations, isPendingReview() helper
- `app/Policies/IngredientPolicy.php` — Added submit/withdraw; modified update/delete with isPendingReview() guard
- `database/factories/IngredientFactory.php` — Added submitted() and rejected() states with SubmissionStatus enum
- `database/factories/IngredientSubmissionFactory.php` — New factory: default Submitted status, ingredient_id, submitted_by, submission_number=1
- `database/migrations/2026_05_18_000002_create_notifications_table.php` — Laravel standard notifications table
- `database/migrations/2026_05_18_000003_create_ingredient_submissions_table.php` — ingredient_submissions with cascadeOnDelete, nullOnDelete FKs, dual FIFO indexes
- `database/migrations/2026_05_18_000004_add_submission_status_to_ingredients_table.php` — submission_status column, default 'private', after verified_at
- `tests/Feature/Ingredients/IngredientSubmissionTest.php` — INGR-09: 6 tests (submit, freeze, withdraw, resubmission)
- `tests/Feature/Ingredients/IngredientModerationTest.php` — INGR-10: 5 tests (queue view, FIFO ordering, approve/reject, permission gate)
- `tests/Feature/Ingredients/IngredientPromotionTest.php` — INGR-11: 6 tests (convert-in-place, visibility, id stability, rejection revert, notifications)
- `resources/js/pages/admin/ingredients/show.tsx` — Minimal stub for Vite manifest resolution

## Decisions Made

- **Withdrawn case in SubmissionStatus:** Added `Withdrawn = 'withdrawn'` so the submissions table can record withdrawal events as a status (not soft-delete), preserving full audit history. The ingredient's `submission_status` reverts to `Private` on withdrawal.
- **submit() policy explicit ownership check:** Used `in_array([Private, Rejected], strict: true)` with a separate early-return ownership guard rather than chaining with `&&`/`||`, avoiding the operator-precedence bug present in the RESEARCH draft code.
- **IngredientDecisionNotification created in Task 3 scope:** Test files reference this class; creating it prevents parse/fatal errors in the RED suite. The stub uses primitive constructor args (not Eloquent models) per RESEARCH anti-pattern guidance.
- **admin/ingredients/show.tsx stub created pre-routes:** Follows the Phase 4/6 lesson — assertInertia() triggers Vite manifest lookup; the page must exist before tests hit the route.

## Deviations from Plan

None — plan executed exactly as written. The RESEARCH noted a precedence bug in the draft `submit()` policy; the plan's action block already specified the correct fix, so this was planned work, not a deviation.

## Issues Encountered

- PHP not in PATH in the Bash shell; resolved by using the full path `/c/Users/VasilisSouflakos/.config/herd/bin/php83/php.exe` for all Artisan commands.
- Migration files generated with timestamp-based names; renamed to the plan-specified `2026_05_18_000002/000003/000004` ordering before editing.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Schema foundation complete; `migrate:fresh --seed` succeeds cleanly
- IngredientPolicy contracts (submit, withdraw, frozen-while-pending) are in place for 07-02 controllers
- RED test suite (17/19 failing) gives 07-02 concrete GREEN targets
- Notification class stub is in place; 07-02 will wire the real notification dispatch after DB::transaction()
- admin/ingredients/show.tsx stub is in place; 07-03 will build the real review UI

---
*Phase: 07-ingredient-moderation*
*Completed: 2026-05-18*
