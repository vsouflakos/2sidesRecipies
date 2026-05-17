---
phase: 05-ai-agent
plan: 03
subsystem: ai
tags: [laravel, recipes, ai-agent, validation, duplicate]

requires:
  - phase: 05-ai-agent plan 02
    provides: RecipeConversationController show+stream, AgentOrchestrator, RecipeConversationMessage proposal_state schema
  - phase: 03-recipe-core-metrics
    provides: RecipeDraftManager applyEdit, CircularReferenceDetector, RecipeVersionService, RecipeDuplicateController

provides:
  - SuggestionApplier service: validates and applies agent edit proposals to the working draft
  - RecipeConversationController apply action: HTTP endpoint for accepting an edit proposal
  - RecipeConversationController variant action: HTTP endpoint for creating a variant recipe
  - Named routes recipes.conversation.apply and recipes.conversation.variant

affects: [05-ai-agent, frontend-ai-chat]

tech-stack:
  added: []
  patterns:
    - "SuggestionApplier: validates proposal data via recipeDraftDataRules + recipeMetadataRules (with 'required' stripped for partial updates) before applyEdit call"
    - "One applyEdit call per proposal = one Recall step — bundled proposals are atomic"
    - "Variant creation: duplicate-then-apply via RecipeDuplicateController logic + RecipeDraftManager"
    - "abort_unless scope guard on nested resource before Gate::authorize — 404 before ownership reveal"

key-files:
  created:
    - app/Support/Recipes/SuggestionApplier.php
  modified:
    - app/Http/Controllers/Recipes/RecipeConversationController.php
    - routes/web.php

key-decisions:
  - "SuggestionApplier merges recipeDraftDataRules and recipeMetadataRules for proposal validation — metadata rules prefixed with 'data.' and 'required' stripped to allow partial updates"
  - "Variant controller action duplicates source recipe then applies proposal changes list to variant's draft, inside a single DB::transaction for atomicity"

patterns-established:
  - "Proposal validation: strip 'required' from metadata rules when validating nested 'data' key — allows partial agent proposals to pass without requiring all metadata fields"

requirements-completed: [AI-04, AI-05, AI-07]

duration: 20min
completed: 2026-05-17
---

# Phase 05 Plan 03: Apply Path Summary

**SuggestionApplier validates agent proposals through UpdateRecipeDraftRequest rules + metadata rules and applies via single applyEdit call; variants duplicate the recipe atomically via RecipeDuplicateController logic**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-05-17T17:00:00Z
- **Completed:** 2026-05-17T17:20:00Z
- **Tasks:** 2
- **Files modified:** 3 (created 1)

## Accomplishments
- SuggestionApplier service validates agent edit proposals through the same rules as UpdateRecipeDraftRequest, enforces circular-reference guard, and applies as single applyEdit call
- RecipeConversationController gains apply and variant actions with proper scope guards (abort_unless) and error mapping (ValidationException → 422, InvalidArgumentException → 409)
- Variant action creates a new independent recipe via the duplicate path inside a DB::transaction, applying proposal changes to the variant's draft
- All 7 RecipeConversationTest Wave 0 tests GREEN; 82 recipe tests pass with no regressions

## Task Commits

Each task was committed atomically:

1. **Task 1: SuggestionApplier — validate + apply an agent proposal** - `293c6be` (feat)
2. **Task 2: apply + variant controller actions and routes** - `67f9844` (feat)

**Plan metadata:** (docs commit below)

## Files Created/Modified
- `app/Support/Recipes/SuggestionApplier.php` - Validates agent edit proposals and applies via RecipeDraftManager::applyEdit; handles variant guard and circular-ref check
- `app/Http/Controllers/Recipes/RecipeConversationController.php` - Added apply() and variant() actions with SuggestionApplier, RecipeVersionService, RecipeDraftManager injected
- `routes/web.php` - Added recipes.conversation.apply and recipes.conversation.variant named routes with {message} route-model binding

## Decisions Made
- SuggestionApplier uses both `recipeDraftDataRules()` and `recipeMetadataRules()` (prefixed as `data.*`) for validation; the `required` modifier is stripped from metadata rules so partial proposals pass without all fields present
- Variant action mirrors RecipeDuplicateController logic inline rather than extracting a separate RecipeDuplicator service — the plan allowed both approaches; inline keeps the dependency simpler given the limited reuse needed here

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Validation scope extended to include recipeMetadataRules**
- **Found during:** Task 1 (SuggestionApplier implementation)
- **Issue:** The test `rejects an invalid suggestion` sends `data: ['yield_amount' => 'not-a-number']`. `recipeDraftDataRules()` only validates the `data` key as `nullable|array`, so 'not-a-number' as yield_amount value would pass without also running `recipeMetadataRules()`
- **Fix:** Added `recipeMetadataRules()` prefixed with `data.` to the validator, stripping `required` modifiers to allow partial updates
- **Files modified:** app/Support/Recipes/SuggestionApplier.php
- **Verification:** `php artisan test --filter="rejects an invalid suggestion"` passes
- **Committed in:** 293c6be (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug in validation scope)
**Impact on plan:** Required to make the AI-07 validation actually catch invalid nested field values. No scope creep.

## Issues Encountered
None beyond the validation rule scope issue documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Apply path complete: SuggestionApplier + routes ready for frontend integration (plan 05-04)
- Full Wave 0 test suite GREEN: all 7 RecipeConversationTest assertions pass
- Wayfinder TypeScript routes regenerated for frontend consumption

---
*Phase: 05-ai-agent*
*Completed: 2026-05-17*
