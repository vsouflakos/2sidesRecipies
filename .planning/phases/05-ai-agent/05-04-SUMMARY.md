---
phase: 05-ai-agent
plan: 04
subsystem: ai
tags: [react, inertia, ai-agent, sse, streaming, chat-ui]

requires:
  - phase: 05-ai-agent plan 02
    provides: RecipeConversationController show+stream, AgentOrchestrator + tool surface, ai_enabled prop
  - phase: 05-ai-agent plan 03
    provides: SuggestionApplier, RecipeConversationController apply+variant actions, named routes

provides:
  - useAiChat hook — SSE streaming chat state, message history, apply/variant/dismiss/retry
  - ai-chat component suite — sheet, message list, bubbles, proposal card, starter prompts, streaming indicator
  - "Ask AI" trigger integrated into the recipe builder toolbar, gated on ai_enabled
  - search_ingredients agent tool — catalog lookup so proposals reference real ingredients
  - EN/EL translations for the full chat surface

affects: [05-ai-agent]

tech-stack:
  added: []
  patterns:
    - "SSE chat: fetch() + ReadableStream.getReader(), CRLF-tolerant frame splitting, X-XSRF-TOKEN from cookie"
    - "Builder resync: external draft mutations (AI apply, Recall) call setDraft from the reload onSuccess — useState snapshots a prop only at mount"
    - "Agent edits applied as deltas on the current draft (DraftActionApplier), never a full replace"
    - "search_ingredients tool: tokenized name match within owner-visibility scope; agent must search before add_ingredient_line"

key-files:
  created:
    - resources/js/types/ai-chat.ts
    - resources/js/hooks/use-ai-chat.ts
    - resources/js/components/recipes/ai-chat/ai-chat-sheet.tsx
    - resources/js/components/recipes/ai-chat/message-list.tsx
    - resources/js/components/recipes/ai-chat/message-bubble.tsx
    - resources/js/components/recipes/ai-chat/proposal-card.tsx
    - resources/js/components/recipes/ai-chat/starter-prompts.tsx
    - resources/js/components/recipes/ai-chat/streaming-indicator.tsx
    - app/Support/Recipes/DraftActionApplier.php
    - app/Support/Recipes/DraftActionException.php
  modified:
    - resources/js/pages/recipes/show.tsx
    - app/Support/Recipes/AgentOrchestrator.php
    - app/Support/Recipes/SuggestionApplier.php
    - app/Http/Controllers/Recipes/RecipeConversationController.php
    - resources/views/prompts/recipe-agent-system.blade.php
    - lang/en/app.php
    - lang/el/app.php

key-decisions:
  - "show.tsx holds the draft in local React state; external mutations must resync it explicitly via the reload onSuccess — a partial Inertia reload alone does not reach a useState-seeded value"
  - "Agent edit proposals apply as deltas via DraftActionApplier, not as a full-draft replace — protects unrelated fields and keeps Recall to one step"
  - "Agent gets a search_ingredients tool with tokenized matching and a mandatory-search instruction so it links real catalog ingredients instead of inventing free-text duplicates"
  - "Valid unit symbols embedded in the propose_recipe_edit tool contract so the agent cannot invent unresolvable units"

patterns-established:
  - "Derived-from-prop React state must be resynced explicitly on external change — never rely on a useState initializer re-running"
  - "Agent tools that write structured data should expose a read/search tool so the model resolves real ids rather than guessing"

requirements-completed: [AI-01, AI-03, AI-04, AI-05]

duration: 2 sessions (extensive human-verify checkpoint debugging)
completed: 2026-05-18
---

# Phase 05 Plan 04: AI Chat UI Summary

**A per-recipe "Ask AI" slide-over with token-streaming chat, structured proposal cards that apply edits to the working draft, and an agent that searches the ingredient catalog — verified end-to-end at the human checkpoint.**

## Performance

- **Tasks:** 4 (3 build tasks + 1 human-verify checkpoint)
- **Build tasks:** completed 2026-05-17
- **Checkpoint:** opened 2026-05-17, approved 2026-05-18 after 10 corrective commits
- **Files:** 8 created (frontend), 2 created (backend), 7 modified

## Accomplishments

- `useAiChat` hook: SSE streaming via `fetch()` + `ReadableStream`, token-by-token assistant rendering, history load, apply/variant/dismiss/retry
- Six chat components built to the UI-SPEC Surface Contracts: slide-over sheet, message list, message bubbles, proposal card (pending/applied/dismissed/failed states), starter prompts, streaming indicator
- "Ask AI" trigger integrated into the recipe builder toolbar, rendered only when `ai_enabled` is true
- Full EN/EL translations for the chat surface
- Agent gained a `search_ingredients` tool so proposed ingredients link to real catalog entries
- End-to-end human-verify checkpoint approved: chat streams, proposals apply to the draft, the builder reflects the change, Recall undoes it, and the agent uses the ingredient catalog

## Task Commits

1. **Task 1: chat types, SSE hook, EN/EL translations** — `b83fc25` (feat)
2. **Task 2: chat components — sheet, messages, proposals, starters, streaming** — `84b8a42` (feat)
3. **Task 3: integrate AiChatSheet into the builder toolbar** — `48668d6` (feat)
4. **Task 4: human-verify checkpoint** — approved after the corrective commits below

### Checkpoint corrective commits

The human-verify checkpoint surfaced ten issues across the full live flow (the Wave 0 suite uses `Prism::fake()`, so live streaming, CSRF, and apply behaviour were exercised for the first time here):

1. `def1671` — chat CSRF: use the `XSRF-TOKEN` cookie, not a meta tag (419 fix)
2. `66c0114` — surface agent error messages in the chat UI and log server-side
3. `cb6ee16` — emit SSE frames with LF endings, not `PHP_EOL` (CRLF broke parsing)
4. `caaa442` — stream the response in real time; lift the 30s execution cap
5. `7cea530` — align agent tool parameter names with closure params
6. `95785e3` — apply agent edits as draft deltas (DraftActionApplier), not full replace
7. `54f9c66` — resync the builder's local draft state after AI Apply (and Recall)
8. `828bec8` — give the agent a `search_ingredients` catalog tool
9. `eca3cc3` — name ingredient lines from the catalog when only an id is given
10. `caedc80` — tokenized catalog search, raised agent max-steps, embedded valid units

## Files Created/Modified

- `resources/js/hooks/use-ai-chat.ts` — SSE streaming chat hook
- `resources/js/components/recipes/ai-chat/*` — six chat UI components
- `resources/js/types/ai-chat.ts` — `ConversationMessage`, `ProposalState`, `ChatStatus`
- `resources/js/pages/recipes/show.tsx` — "Ask AI" trigger + `syncDraftFromServer` for post-apply/Recall resync
- `app/Support/Recipes/DraftActionApplier.php` — applies an agent edit action as a delta on the current draft
- `app/Support/Recipes/DraftActionException.php` — graceful action-applier failure
- `app/Support/Recipes/AgentOrchestrator.php` — `search_ingredients` tool, unit contract, raised max-steps
- `app/Support/Recipes/SuggestionApplier.php` — delegates draft mutation to DraftActionApplier
- `app/Http/Controllers/Recipes/RecipeConversationController.php` — real-time SSE streaming, LF frames, error surfacing
- `resources/views/prompts/recipe-agent-system.blade.php` — mandatory-search guidance
- `lang/en/app.php`, `lang/el/app.php` — `ai` translation namespace

## Decisions Made

- The builder holds the draft in local React state; external mutations resync it explicitly through the partial-reload `onSuccess`. A `useState` initializer runs only at mount, so a partial reload alone leaves the builder stale.
- Agent edits apply as deltas (`DraftActionApplier`), never a full-draft replace — unrelated fields are preserved and each Apply is one Recall step.
- The agent must search the catalog (`search_ingredients`) before adding an ingredient; the tool uses tokenized name matching so differently-phrased catalog names ("Cheese, feta, whole milk, crumbled") still match common usage ("feta cheese").
- Valid unit symbols are embedded in the `propose_recipe_edit` tool contract so the agent cannot propose an unresolvable unit.

## Deviations from Plan

The plan's three build tasks landed as specified. Task 4 (human-verify) was the deviation surface: ten corrective commits were required before approval because the live AI path — CSRF, SSE framing, real streaming, tool-call shape, draft mutation, builder refresh, and ingredient resolution — was only first exercised against a live provider at the checkpoint. All ten are bug fixes within plan scope (no scope creep); each is committed atomically and covered by unit/feature tests (`DraftActionApplierTest`, `AgentOrchestratorToolMappingTest`, `RecipeConversationTest`).

## Issues Encountered

All resolved. The notable class of issue: the Wave 0 suite mocks the provider with `Prism::fake()`, which cannot exercise SSE line endings, the XSRF cookie, real token streaming, or the agent's tool-call ergonomics — these only failed under a live provider at the human checkpoint. Tests were added for the corrected backend behaviour.

## User Setup Required

A live AI provider must be configured (`AI_PROVIDER`, `AI_MODEL`, and the matching credentials) for the chat to function. When `AI_PROVIDER` is empty the feature is hidden via the `ai_enabled` prop.

## Next Phase Readiness

- Phase 5 (AI Agent) is feature-complete: all four plans delivered, end-to-end flow approved.
- No blockers for Phase 6 (Publishing) — it depends on Phase 3, not Phase 5.

---
*Phase: 05-ai-agent*
*Completed: 2026-05-18*
