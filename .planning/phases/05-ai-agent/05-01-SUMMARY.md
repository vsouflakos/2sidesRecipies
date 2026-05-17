---
phase: 05-ai-agent
plan: "01"
subsystem: ai-foundation
tags: [prism, ai, conversation, wave-0, tdd]
dependency_graph:
  requires: []
  provides: [prism-installed, config-ai, recipe-conversations-schema, prism-adapter, wave-0-tests]
  affects: [05-02, 05-03, 05-04]
tech_stack:
  added: [prism-php/prism@0.100.1]
  patterns: [provider-agnostic-ai-config, wave-0-red-test-scaffold]
key_files:
  created:
    - config/ai.php
    - config/prism.php
    - app/Models/RecipeConversation.php
    - app/Models/RecipeConversationMessage.php
    - app/Support/Recipes/PrismAdapter.php
    - database/migrations/2026_05_17_161826_create_recipe_conversations_table.php
    - database/migrations/2026_05_17_161836_create_recipe_conversation_messages_table.php
    - database/factories/RecipeConversationFactory.php
    - database/factories/RecipeConversationMessageFactory.php
    - tests/Feature/Recipes/RecipeConversationTest.php
    - tests/Unit/Support/Recipes/AgentContextBuilderTest.php
  modified:
    - composer.json
    - composer.lock
    - .env.example
    - app/Models/Recipe.php
decisions:
  - "No default AI provider baked in — AI_PROVIDER env must be set by deployer; empty string hides the AI feature (ai_enabled prop)"
  - "proposal_state JSON column stores {action, data, status, summary, kind} — status is one of pending|applied|dismissed|failed, kind is edit|variant"
  - "Wave 0 test file uses valid TestVerdict enum values (worked/didnt_work) — 'failed' is not a valid backing value"
  - "AgentContextBuilderTest uses TestCase+RefreshDatabase via uses() — Unit tests that use factories/IoC need Laravel app context"
metrics:
  duration_minutes: 17
  completed_date: "2026-05-17"
  tasks_completed: 3
  files_changed: 14
---

# Phase 05 Plan 01: Prism Install + Config + Data Model + Wave 0 Tests Summary

**One-liner:** Provider-agnostic Prism PHP installed with empty-default AI config, recipe conversation schema, and Wave 0 RED test suite targeting AI-01..AI-07.

## What Was Built

### Task 1: Install Prism, publish config, add app-level AI config (753894b)
- `composer require prism-php/prism` (v0.100.1)
- Published `config/prism.php` with 11 provider entries (anthropic, openai, ollama, mistral, etc.)
- Created `config/ai.php` with `provider`/`model`/`max_tokens`/`context_budget_chars` — all empty by default
- Appended `AI_PROVIDER`, `AI_MODEL`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY` to `.env.example`

### Task 2: Conversation data model (debb70c)
- Migration `recipe_conversations`: `recipe_id` FK with `unique('recipe_id')` — one conversation per recipe
- Migration `recipe_conversation_messages`: `role`, `content`, `text`, `proposal_state` JSON, composite index on `(recipe_conversation_id, created_at)`
- `RecipeConversation` model: `recipe()`, `messages()->orderBy('created_at')`, `HasFactory`
- `RecipeConversationMessage` model: `proposal_state` cast to array, `conversation()` BelongsTo
- `Recipe::conversation()` hasOne relation added alongside existing `draft()`/`tests()`
- Factories: `RecipeConversationFactory`, `RecipeConversationMessageFactory` with `->proposal()` state

### Task 3: PrismAdapter + Wave 0 RED test suite (1d439f9)
- `PrismAdapter`: thin wrapper reading `config/ai.php`, `isConfigured()` returns true only when both provider and model are set
- `RecipeConversationTest` (7 tests, all RED): stream, hide/expose AI feature, apply proposal, reject invalid, create variant, forbid non-owner access
- `AgentContextBuilderTest` (2 tests, all RED): system prompt serialization, message history rebuild

## Test Results

```
RecipeConversation: 7 tests — 0 passed, 5 errors (route not defined), 2 failures (ai_enabled prop missing) — CORRECT Wave 0 RED state
AgentContextBuilder: 2 tests — 0 passed, 2 errors (AgentContextBuilder class does not exist) — CORRECT Wave 0 RED state
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] TestVerdict enum value corrected**
- **Found during:** Task 3
- **Issue:** AgentContextBuilderTest used `'failed'` as a TestVerdict value, but the enum only has `Worked`, `DidntWork`, `Inconclusive`
- **Fix:** Changed `'failed'` to `'didnt_work'` (the backing value for `TestVerdict::DidntWork`)
- **Files modified:** `tests/Unit/Support/Recipes/AgentContextBuilderTest.php`
- **Commit:** 1d439f9

**2. [Rule 2 - Missing] AgentContextBuilderTest needs Laravel app context**
- **Found during:** Task 3
- **Issue:** Unit tests that use Eloquent factories and `app()` require `TestCase::class` — Pest.php only applies it to `Feature/` directory
- **Fix:** Added `uses(TestCase::class, RefreshDatabase::class)` to the Unit test file directly
- **Files modified:** `tests/Unit/Support/Recipes/AgentContextBuilderTest.php`
- **Commit:** 1d439f9

## Self-Check: PASSED

All key files verified:
- FOUND: config/ai.php
- FOUND: config/prism.php
- FOUND: app/Models/RecipeConversation.php
- FOUND: app/Models/RecipeConversationMessage.php
- FOUND: app/Support/Recipes/PrismAdapter.php
- FOUND: tests/Feature/Recipes/RecipeConversationTest.php
- FOUND: tests/Unit/Support/Recipes/AgentContextBuilderTest.php

All commits verified:
- 753894b: feat(05-01): install prism-php/prism and add provider-agnostic AI config
- debb70c: feat(05-01): add conversation data model with migrations, models, and factories
- 1d439f9: feat(05-01): add PrismAdapter and Wave 0 RED test suite (AI-01..AI-07)
