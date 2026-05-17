---
phase: 5
slug: ai-agent
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-05-17
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.x / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter={filter}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30-60 seconds (recipe feature suite) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={filter}`
- **After every plan wave:** Run `php artisan test --compact tests/Feature/Recipes/`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** ~60 seconds (per-filter run)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-01 T1 | 05-01 | 1 | AI-06 | env/config | `php -r "exit(class_exists('Prism\\Prism\\Prism') ? 0 : 1);" && php artisan config:show ai.provider && php artisan config:show ai.model` | created here | ⬜ pending |
| 05-01 T2 | 05-01 | 1 | AI-06 | migration/schema | `php artisan migrate --no-interaction && php artisan test --compact --filter=RecipeConversation` | created here | ⬜ pending |
| 05-01 T3 | 05-01 | 1 | AI-01..AI-07 | feature+unit (RED scaffold) | `php artisan test --compact --filter=RecipeConversation; php artisan test --compact --filter=AgentContextBuilder` | created here (W0) | ⬜ pending |
| 05-02 T1 | 05-02 | 2 | AI-02 | unit | `php artisan test --compact --filter=AgentContextBuilder` | ❌ W0 (05-01 T3) | ⬜ pending |
| 05-02 T2 | 05-02 | 2 | AI-01, AI-03 | reflection check | `php -r "require 'vendor/autoload.php'; \$a=new ReflectionClass('App\\Support\\Recipes\\AgentOrchestrator'); exit(\$a->hasMethod('buildTools') && \$a->hasMethod('buildStream') ? 0 : 1);"` | created here | ⬜ pending |
| 05-02 T3 | 05-02 | 2 | AI-01, AI-03, AI-06 | feature | `php artisan test --compact --filter="streams an assistant response\|hides the AI feature\|exposes the AI feature\|forbids accessing another user"` | ❌ W0 (05-01 T3) | ⬜ pending |
| 05-03 T1 | 05-03 | 3 | AI-04, AI-07 | feature | `php artisan test --compact --filter="applies an accepted suggestion\|rejects an invalid suggestion"` | ❌ W0 (05-01 T3) | ⬜ pending |
| 05-03 T2 | 05-03 | 3 | AI-04, AI-05, AI-07 | feature | `php artisan test --compact --filter=RecipeConversation` | ❌ W0 (05-01 T3) | ⬜ pending |
| 05-04 T1 | 05-04 | 4 | AI-01, AI-03, AI-04, AI-05 | build | `npm run build` | n/a (frontend) | ⬜ pending |
| 05-04 T2 | 05-04 | 4 | AI-01, AI-03, AI-04, AI-05 | build | `npm run build` | n/a (frontend) | ⬜ pending |
| 05-04 T3 | 05-04 | 4 | AI-01 | build | `npm run build` | n/a (frontend) | ⬜ pending |
| 05-04 T4 | 05-04 | 4 | AI-01, AI-03, AI-04, AI-05 | manual (checkpoint) | human-verify — see Manual-Only Verifications | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Note: 05-01 Task 3 creates the Wave 0 RED test suite. Every test referenced in this map
(`RecipeConversationTest`, `AgentContextBuilderTest`) is authored RED in that task with real
assertions, then driven GREEN by the implementing tasks in plans 05-02 and 05-03.*

---

## Wave 0 Requirements

Created by **05-01 Task 3** — the RED test scaffold with real assertions (no `skip()` /
`markTestIncomplete`), giving plans 05-02..05-04 concrete GREEN targets.

| Wave 0 Artifact | Covers | Tests / Setup |
|-----------------|--------|---------------|
| `tests/Feature/Recipes/RecipeConversationTest.php` | AI-01, AI-03 | `it('streams an assistant response to a chat message')` |
| `tests/Feature/Recipes/RecipeConversationTest.php` | AI-06 | `it('hides the AI feature when no provider is configured')`, `it('exposes the AI feature when a provider is configured')` |
| `tests/Feature/Recipes/RecipeConversationTest.php` | AI-04 | `it('applies an accepted suggestion to the working draft')` |
| `tests/Feature/Recipes/RecipeConversationTest.php` | AI-07 | `it('rejects an invalid suggestion and records a failed proposal state')` |
| `tests/Feature/Recipes/RecipeConversationTest.php` | AI-05 | `it('creates a recipe variant as a new independent recipe')` |
| `tests/Feature/Recipes/RecipeConversationTest.php` | ACCESS (owner scope) | `it('forbids accessing another user conversation')` |
| `tests/Unit/Support/Recipes/AgentContextBuilderTest.php` | AI-02 | `it('includes the recipe draft, chef notes, and all test feedback in the system prompt')`, `it('rebuilds messages from persisted conversation history')` |
| `Prism::fake()` setup | all AI tests | `beforeEach` sets `config(['ai.provider' => 'anthropic', 'ai.model' => '...'])` + `Prism::fake([TextResponseFake...])` — no live API calls in the suite |
| `RecipeConversationFactory` + `RecipeConversationMessageFactory` (incl. `->proposal()` state) | AI-04, AI-05 | created in 05-01 Task 2 — shared fixtures for conversation tests |

- [x] Test stubs for AI-01 through AI-07 — `RecipeConversationTest.php` + `AgentContextBuilderTest.php` (05-01 T3)
- [x] Shared fixtures / factories for `recipe_conversations` + `recipe_conversation_messages` (05-01 T2)
- [x] `Prism::fake()` setup for AI provider tests (05-01 T3 `beforeEach`)

All Wave 0 MISSING references in the plans resolve to the two test files above. No task in
plans 05-01..05-04 carries a `MISSING` automated verify — each `<automated>` command either
runs against the Wave 0 suite or against an in-task artifact (config, reflection, build).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Token-by-token streaming visibly renders in the chat UI | AI-01, AI-03 | SSE rendering / typing indicator is a perceptual front-end behavior; automated tests assert the stream response is OK and the assistant message persists, but not the visual token-by-token paint | 05-04 Task 4 checkpoint, steps 3-5 — open the chat sheet, send a message, confirm the reply streams in progressively with a typing indicator |
| Builder updates live behind the sheet after Apply | AI-04 | Cross-component live refresh (Inertia partial reload landing in the builder while the sheet overlays it) is a visual integration not covered by feature tests | 05-04 Task 4 checkpoint, steps 6-8 — apply a proposal, confirm the success toast, the locked "Applied" card, and the changed quantity in the builder; then Recall undoes it in one step |
| Variant proposal opens the new recipe via the card link | AI-05 | Navigation from the proposal card link to the created variant recipe is a UI flow | 05-04 Task 4 checkpoint, step 10 — apply a variant proposal, confirm a new recipe is created and the "Open variant →" link navigates to it |
| EN/EL chat chrome translation | I18N (chrome) | Visual confirmation that all chat UI strings render in the selected language | 05-04 Task 4 checkpoint, step 11 — switch UI language to Greek, confirm button/titles/Apply/Dismiss/starter prompts are translated |

*The backend behavior for every requirement (AI-01..AI-07) is automated via the Wave 0 suite
with `Prism::fake()`. The manual checkpoint (05-04 Task 4) covers only the perceptual /
navigational front-end surfaces that cannot be asserted programmatically.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved
