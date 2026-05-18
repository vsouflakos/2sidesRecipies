---
phase: 07-ingredient-moderation
plan: 02
subsystem: backend
tags: [controllers, form-requests, eloquent-resources, notifications, inertia, routes, pest]

# Dependency graph
requires:
  - phase: 07-01
    provides: SubmissionStatus enum, IngredientSubmission model, IngredientPolicy submit/withdraw, 19-test RED suite
  - phase: 02-ingredient-library
    provides: Ingredient model, IngredientPolicy, Gate::authorize pattern, IngredientDetailResource

provides:
  - IngredientSubmissionController (owner): duplicateCheck (JSON advisory), store (submit + freeze), destroy (withdraw + unfreeze)
  - SubmitIngredientRequest: authorize via can('submit'); confirmed_duplicates nullable boolean
  - Admin/IngredientSubmissionController: approve (convert-in-place + notify) + reject (revert + notify AFTER transaction)
  - ApproveIngredientRequest: notes nullable, authorize via can('review-ingredients')
  - RejectIngredientRequest: notes required, failedValidation() throws HttpResponseException 422
  - IngredientSubmissionResource: queue row + per-submission review payload (completeness, prior_rejections)
  - IngredientReviewController: expanded with FIFO index() + full review show()
  - IngredientDecisionNotification: dispatched after DB::transaction commits (approve + reject)
  - HandleInertiaRequests: pendingIngredientReviewCount (moderators only) + ingredientNotifications (5 unread, all users)
  - Owner routes: ingredients.submit, ingredients.withdraw, ingredients.duplicate-check
  - Admin routes: admin.ingredient-submissions.show/approve/reject

affects:
  - 07-03 (frontend UI — consume submission_status, shared props, and resource shapes)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - HttpResponseException 422 in failedValidation() — consistent JSON error regardless of request type (matches Phase 6 PublishRecipeRequest pattern)
    - notify() after DB::transaction closes — prevents notification without committed data
    - convert-in-place approval: user_id=null + verified=true in single ingredient.update()
    - IngredientSubmissionResource dual-mode: serves both queue list and per-submission review screen
    - Contracts\Validation\Validator interface type hint in failedValidation() — PHP fatal if you use the concrete class instead

key-files:
  created:
    - app/Http/Requests/Ingredients/SubmitIngredientRequest.php
    - app/Http/Controllers/Ingredients/IngredientSubmissionController.php
    - app/Http/Requests/Admin/ApproveIngredientRequest.php
    - app/Http/Requests/Admin/RejectIngredientRequest.php
    - app/Http/Resources/IngredientSubmissionResource.php
    - app/Http/Controllers/Admin/IngredientSubmissionController.php
  modified:
    - app/Http/Controllers/Admin/IngredientReviewController.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - routes/web.php

key-decisions:
  - "RejectIngredientRequest.failedValidation() throws HttpResponseException 422 — required because test calls route as plain HTTP POST (no Inertia headers); standard FormRequest would redirect 302, but assertStatus(422) would fail"
  - "Contracts\\Validation\\Validator interface used in failedValidation() type hint — using concrete Illuminate\\Validation\\Validator causes PHP Fatal Error (method signature incompatible with parent)"
  - "notify() dispatched AFTER DB::transaction closes — inside the transaction, the submission row may not yet be committed; notifying inside risks sending notification that gets rolled back"
  - "Admin IngredientSubmissionController aliased as AdminIngredientSubmissionController in routes — class name collision with Ingredients-namespace controller from Task 1"

# Metrics
duration: 16min
completed: 2026-05-18
---

# Phase 7 Plan 02: Moderation Backend Summary

**Three controllers, three FormRequests, one resource, shared Inertia props, and admin/owner routes turning the 19-test RED suite GREEN for INGR-09, INGR-10, INGR-11**

## Performance

- **Duration:** 16 min
- **Started:** 2026-05-18T13:20:34Z
- **Completed:** 2026-05-18T13:36:08Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments

- Owner submit/withdraw: IngredientSubmissionController with `store()` (numbered submission row + freeze ingredient), `destroy()` (withdraw + unfreeze), and `duplicateCheck()` (JSON advisory for up to 5 official matches); IngredientSubmissionTest 7/7 GREEN
- Moderator backend: ApproveIngredientRequest + RejectIngredientRequest FormRequests, IngredientSubmissionResource for queue/review payloads, IngredientReviewController FIFO queue + full review show, Admin/IngredientSubmissionController approve (convert-in-place + notify) + reject (revert + notify); IngredientModerationTest 6/6 + IngredientPromotionTest 6/6 GREEN
- Shared Inertia props: `pendingIngredientReviewCount` (moderator-gated count query) and `ingredientNotifications` (up to 5 unread decision notifications); full Ingredients suite 75/75 GREEN

## Task Commits

Each task was committed atomically:

1. **Task 1: Owner submit/withdraw — FormRequest, controller, duplicate-check, routes** - `f738b46` (feat)
2. **Task 2: Moderator queue + review screen + approve/reject + notification** - `b0086b3` (feat)
3. **Task 3: Shared Inertia props — pending review count + submitter notifications** - `ab1aba5` (feat)

**Plan metadata:** (docs commit after SUMMARY)

## Files Created/Modified

- `app/Http/Requests/Ingredients/SubmitIngredientRequest.php` — authorize via can('submit', ingredient); confirmed_duplicates nullable boolean rule
- `app/Http/Controllers/Ingredients/IngredientSubmissionController.php` — duplicateCheck() JSON advisory, store() numbered submission + freeze, destroy() withdraw + unfreeze
- `app/Http/Requests/Admin/ApproveIngredientRequest.php` — authorize via can('review-ingredients'); notes nullable string max:1000
- `app/Http/Requests/Admin/RejectIngredientRequest.php` — notes required; failedValidation() throws HttpResponseException 422 for consistent test behavior
- `app/Http/Resources/IngredientSubmissionResource.php` — dual-mode: queue rows + full review payload with completeness signal and prior_rejections
- `app/Http/Controllers/Admin/IngredientReviewController.php` — index() FIFO queue via orderBy('submitted_at'), show() with full eager-loads
- `app/Http/Controllers/Admin/IngredientSubmissionController.php` — approve() convert-in-place (user_id=null + verified=true) + notify after transaction; reject() revert status + notify after transaction
- `app/Http/Middleware/HandleInertiaRequests.php` — pendingIngredientReviewCount + ingredientNotifications shared props added
- `routes/web.php` — owner routes (submit/withdraw/duplicate-check before wildcard show); admin routes (ingredient-submissions.show/approve/reject with aliased import)

## Decisions Made

- **RejectIngredientRequest 422 via failedValidation():** Test asserts `assertStatus(422)` on a plain HTTP POST. Standard FormRequest validation failure returns 302 redirect in non-Inertia context. Added `failedValidation()` override that throws `HttpResponseException` with a 422 JSON body — consistent with the `PublishRecipeRequest` pattern from Phase 6.
- **Contracts\Validation\Validator interface type hint:** The parent `FormRequest::failedValidation()` signature uses the interface, not the concrete class. Using the concrete `Illuminate\Validation\Validator` causes a PHP Fatal Error ("must be compatible with parent"). Using the interface resolves the incompatibility.
- **notify() after transaction closes:** The `submittedBy->notify()` call is placed after the `DB::transaction()` closure. Inside a transaction, the submission row may not yet be visible to concurrent reads and a rollback would leave a notification with no corresponding data.
- **Admin controller alias:** `use App\Http\Controllers\Admin\IngredientSubmissionController as AdminIngredientSubmissionController` in routes/web.php — avoids class name collision with `App\Http\Controllers\Ingredients\IngredientSubmissionController` from Task 1.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed type mismatch in RejectIngredientRequest::failedValidation()**
- **Found during:** Task 2 — test crashed with PHP Fatal Error: method signature incompatible with parent
- **Issue:** Used `Illuminate\Validation\Validator` (concrete class) as type hint; parent method declares `Illuminate\Contracts\Validation\Validator` (interface). PHP fatal error.
- **Fix:** Changed import to `use Illuminate\Contracts\Validation\Validator;`
- **Files modified:** `app/Http/Requests/Admin/RejectIngredientRequest.php`
- **Commit:** `b0086b3`

## Issues Encountered

- `stream_filter_remove(): Unable to flush filter, not removing` — Pest PAO captures stdout via stream filter; a PHP fatal error during test load (from the type mismatch) causes the filter to be in an unflushable state. Error was a symptom of the fatal error, not a Pest internal bug. Fixed by correcting the type hint.
- `assertStatus(422)` test failing with "Call to a member function all() on array" — Laravel's `TestResponseAssert::injectResponseContext()` crashed because the response was a 302 redirect (not 422); when trying to display error context, it called `->all()` on a plain array from Inertia's session. Fixed by adding `failedValidation()` override to return 422 JSON directly.

## User Setup Required

None.

## Next Phase Readiness

- INGR-09, INGR-10, INGR-11 backend complete — full Ingredients suite 75/75 GREEN
- Shared props (`pendingIngredientReviewCount`, `ingredientNotifications`) ready for 07-03 frontend consumption
- IngredientSubmissionResource shape is the API contract for 07-03 queue and review screen components

---
*Phase: 07-ingredient-moderation*
*Completed: 2026-05-18*
