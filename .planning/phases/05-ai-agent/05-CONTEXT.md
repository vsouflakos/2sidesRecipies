# Phase 5: AI Agent - Context

**Gathered:** 2026-05-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 5 delivers a **per-recipe conversational AI agent**. A chef opens a chat
attached to a recipe; the agent reads the recipe's current working draft, the
chef's notes, and all test feedback, then answers questions, suggests tests and
improvements, applies accepted edits to the working draft (through the same
validation path as a manual edit), and can spin up a recipe variant.

It delivers:

- **Per-recipe chat** — a conversational AI session attached to a recipe
  (AI-01).
- **Recipe-aware context** — the agent reads the current draft, chef notes, and
  all test feedback (AI-02).
- **Suggestions** — the agent proposes tests/experiments and recipe
  improvements in natural language (AI-03).
- **Apply-to-draft** — the user can accept a suggested edit, which lands in the
  recipe's working draft via the same validation path as a manual edit
  (AI-04, AI-07).
- **Variant creation** — the agent can create a recipe variant (e.g. ingredient
  swaps) as a new recipe the user reviews (AI-05).
- **Provider-agnostic adapter** — the AI provider is configurable without
  touching agent code (AI-06).

It does NOT build: publishing or the public library (Phase 6), or ingredient
moderation (Phase 7). It does not add usage limits / billing guardrails, does
not auto-create test records (test suggestions are prose only — recording a
test stays a Phase 4 manual action), and does not expose recipe tools as an
external MCP server — the in-app chat is itself the LLM client.

</domain>

<decisions>
## Implementation Decisions

### Chat surface & threads

- **Slide-over sheet on the recipe builder.** An "Ask AI" trigger on
  `recipes/show.tsx` opens a slide-over panel (shadcn `Sheet`) over the builder.
  Chat and recipe editing share one screen — when the agent applies an edit, the
  builder behind the sheet updates live so the chef sees the change land.
- **On mobile / narrow screens the sheet becomes a full-screen overlay** with a
  back/close control; on desktop it stays a side slide-over.
- **One ongoing, persistent thread per recipe.** Each recipe has a single
  conversation; reopening the sheet resumes it. No multi-thread management. The
  thread is the recipe's working log with the agent.
- **Streaming responses (token-by-token).** The agent's reply streams in as it
  is generated. This requires a streaming endpoint (SSE / chunked) **separate
  from the normal Inertia visit cycle** — Inertia mutations redirect; chat
  streaming uses a plain fetch/XHR route (precedent: Phase 1's silent `fetch()`
  for locale persistence).
- **Read-only conversation history.** Past messages cannot be edited, deleted,
  or bulk-cleared. The thread is an honest, complete log — which also keeps the
  agent's context consistent (no gaps).
- **Empty state shows suggested starter prompts.** Before any messages: a short
  intro plus 3–4 tappable, recipe-grounded starter prompts (e.g. "Suggest a test
  for the current version", "How can I lower the cost?", "Make a vegan
  variant"). No auto-greeting (would burn an API call on every open).
- **Minimal per-message chrome.** Messages show user-vs-agent styling and a
  clear **applied-edit marker** on any message that resulted in a draft edit
  (the thread doubles as an edit log). No timestamps, no token/version metadata.
- **The agent is re-fed the live working draft on every message** — it never
  works from a stale snapshot. A suggestion always reflects edits made (by the
  chef or the agent) since the conversation started.

### Suggestion → Apply flow

- **Structured proposal card.** When the agent proposes a recipe edit, it
  appears as a distinct card inside the agent's message — a human-readable
  summary of the change (e.g. "Reduce sugar 200 g → 150 g in Dough") with
  **Apply / Dismiss** actions. The agent's reasoning is the surrounding prose;
  the card is the unambiguous, reviewable action.
- **Apply commits directly to the working draft — no separate preview step.**
  Clicking Apply writes the change straight to the draft; the builder behind the
  sheet updates live so the user sees the result; **Recall** undoes it like any
  other edit. The proposal card already describes the change and the builder
  shows the outcome — a preview/diff/confirm step would be redundant friction.
- **One Apply = one Recall step.** A suggestion may bundle several changes (e.g.
  "make it vegan" = swap butter, milk, eggs). The whole bundle applies and
  undoes as a single logical action — one Recall reverts the entire suggestion.
  This matches Phase 3's "one logical action = one Recall step" model and how
  the user thinks ("undo what the agent just did").
- **Validation at apply-time; failures feed back to the agent.** Agent edits run
  through the same validation as manual edits (AI-07 —
  `UpdateRecipeDraftRequest` / circular-reference detection). If a proposed edit
  fails on Apply, the change is rejected with a clear error in the chat, and the
  **agent receives the failure as feedback** so it can correct and re-propose —
  rather than dead-ending the user.
- **After apply, the proposal card locks to a non-interactive "Applied" state.**
  Apply/Dismiss are removed; the card remains as a record of what the agent
  changed, consistent with the message-level applied-edit marker.

### Agent capabilities & variants

- **Full builder-parity editing tool surface.** The agent can make any edit the
  chef can make in the builder — add / remove / change ingredient lines, edit
  preparation steps, rename / reorder sections, adjust quantities, edit recipe
  metadata, add sub-recipes. Each agent edit maps to an existing
  `RecipeDraftManager` action. (Restricting the surface would force "do it
  yourself" on half the agent's advice.)
- **A variant (AI-05) is a new, independent recipe.** The agent creates a
  variant by reusing **Phase 3's Duplicate path** — a brand-new recipe seeded
  from the current one, with its own history starting at v1 and **no lineage
  link** back to the source (consistent with the Phase 3 Duplicate decision).
  The agent then applies the requested swaps to the new recipe's draft.
- **Variant review via a linked proposal card.** The agent posts a proposal card
  describing the variant; on Apply it creates the new recipe and the card
  becomes a link/button to open it. The chef stays in the current recipe and
  visits the variant when ready — no forced navigation.
- **Test suggestions (AI-03) are prose only.** The agent describes a test/
  experiment to run in natural language; it does **not** pre-create or pre-fill
  test records. The chef records the test on the Phase 4 tests page when they
  have actually cooked it — a test documents a real-kitchen outcome and should
  not exist before then.
- **Context (AI-02) is supplied in full, up front, on every message.** Each
  message ships the agent the complete current working draft, the chef notes,
  and **all** test feedback (verdicts, structured change rows, ratings,
  tasting notes). A recipe + its tests is a bounded, modest payload; full
  context gives better advice and avoids multi-round tool-call latency in a
  streaming chat.

### AI provider & adapter

- **Prism PHP (`prism-php/prism`) backs the provider-agnostic adapter.** It
  provides a unified multi-provider API (OpenAI, Anthropic, Gemini, Mistral,
  Ollama, …) with native tool/function calling, streaming, and structured
  output. Swapping provider is a config change. *(New dependency — approved
  during discussion.)*
- **No baked-in default provider/model.** The adapter ships with no default; the
  deployer must choose and configure a provider in config. The app does not
  assume a particular vendor.
- **No provider/key configured → the "Ask AI" entry point is hidden/disabled.**
  When no provider is configured the chat is simply not offered (button hidden,
  or disabled with an explanatory tooltip). The app never shows a chat that
  cannot answer.
- **AI call failure → error bubble + retry, draft untouched.** When a configured
  AI call fails (provider error, timeout, rate limit, network), the failed turn
  shows an error bubble with a **Retry** button and the user's message is
  preserved for resend. Any partial/streamed output is discarded. A failed turn
  **never touches the working draft** — a half-applied edit is impossible.

### Claude's Discretion

- Exact wording and count of the starter prompts.
- The adapter interface shape, the config file structure (`config/ai.php` /
  `config/prism.php` / a `services.php` entry), and where the abstraction lives.
- System-prompt design and the tool schemas exposed to the model.
- The conversation / message data model (tables, how a proposal card and its
  applied/failed state are persisted and serialized).
- Streaming transport details (SSE vs chunked response) and the typing/loading
  indicator design.
- Token / context-window management given full-context-up-front — truncation or
  summarization strategy if a recipe + its tests is unusually large.
- Language of the agent's prose responses vs the translatable UI chrome
  (i18n strategy for agent output).
- Visual separation of the agent's reasoning prose from the proposal card.
- Empty-state and applied-edit-marker visual design within the warm-minimal
  system.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — the AI agent
- `Project.md` (repo root) — **§4 The AI Agent**: the authoritative interaction
  flow (ask → answer → Apply → working draft → Recall/Save; variant creation),
  what the agent does, and the provider-agnostic requirement. **§3.3 Working
  Draft vs Saved Versions**: the Save/Recall/draft semantics agent edits must
  obey. **§3.4 Recipe Tests**: test feedback feeds the agent. **§9 Key
  Decisions**: the "AI provider" and "AI editing" rows.
  - **Note:** §6 lists "Laravel MCP" as the AI tooling. That line is
    **superseded** — Phase 5 uses **Prism PHP** for the in-app agent (the app is
    the LLM client and uses Prism's tool calling). Laravel MCP exposes tools to
    *external* MCP clients, which is not what AI-01's in-app chat needs.
- `.planning/REQUIREMENTS.md` — definitions for this phase's requirements:
  **AI-01 … AI-07** (see the "AI Agent" section).
- `.planning/ROADMAP.md` §"Phase 5: AI Agent" — goal, the 4 success criteria,
  dependency on Phase 4.

### Phase 3 foundations this phase builds on
- `.planning/phases/03-recipe-core-metrics/03-CONTEXT.md` — the working-draft /
  versioning model, "one logical action = one Recall step" (chosen partly so AI
  edits are individually undoable), and the **Duplicate** decision (a fresh
  independent recipe, own v1 history, no lineage link) that the AI variant
  reuses.

### Phase 4 foundations this phase builds on
- `.planning/phases/04-recipe-tests/04-CONTEXT.md` — the `RecipeTest` model: the
  **verdict** (Worked / Didn't work / Inconclusive) and **structured change
  rows** were deliberately shaped to be machine-readable agent input; the agent
  reads these plus tasting notes, ratings, and photos metadata as context.

### Phase 1 foundations
- `.planning/phases/01-foundation/01-CONTEXT.md` — owner-scoped role/permission
  model (the chat and its conversation are owner-scoped to the recipe owner),
  localization infra (agent UI chrome is translatable EN/EL), warm-minimal
  design system.

### Codebase maps — existing patterns to follow
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, `FormRequest` per
  action, shared rules in `app/Concerns/` traits, thin feature-grouped
  controllers returning `Inertia::render()` or redirects, shadcn/ui composition,
  `cn()` helper, Wayfinder routes (never hand-edit `@/actions/` `@/routes/`).
- `.planning/codebase/STRUCTURE.md` — directory layout (models, feature-grouped
  controllers under `app/Http/Controllers/Recipes/`, Inertia pages under
  `resources/js/pages/`, service classes under `app/Support/<Feature>/`).
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `INTEGRATIONS.md`,
  `TESTING.md`, `CONCERNS.md` — the Inertia v3 bridge and shared props, the
  database queue, the Pest testing approach (every change programmatically
  tested — agent tool calls and the adapter need provider-mocked tests).

### External — Prism PHP (no project file yet)
- **Prism PHP** docs (`prism-php/prism`) — the multi-provider LLM package
  chosen to back the adapter: tool/function calling, streaming, structured
  output, provider configuration. The researcher should pull current Prism docs
  for the installed version before planning the adapter and tool layer.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`RecipeDraftManager`** (`app/Support/Recipes/RecipeDraftManager.php`) —
  `applyEdit(RecipeDraft $draft, string $action, array $newData): void` and
  `recall(RecipeDraft $draft, int $expectedSequence): array`. This is the exact
  path agent edits MUST use (AI-04, AI-07) — one `applyEdit` call per logical
  action, recorded as one Recall step.
- **`RecipeDraftController`** + routes `PUT recipes/{recipe}/draft`
  (`recipes.draft.update`) and `POST recipes/{recipe}/draft/recall` — the
  existing draft-mutation surface, including circular-reference validation via
  `UpdateRecipeDraftRequest`. Agent edits run through the same validation.
- **`RecipeDuplicateController`** + route `POST recipes/{recipe}/duplicate`
  (`recipes.duplicate`) — the Duplicate path the AI **variant** feature reuses
  to spin up a new independent recipe.
- **`RecipeDraft` / `RecipeDraftEdit` models** — the working draft and its
  per-edit sequence (the Recall stack); agent edits append here.
- **`recipes/show.tsx`** — the single-page recipe builder; the "Ask AI" trigger
  and the chat `Sheet` mount here. The page already loads draft, metrics,
  versions, and `test_summary` sibling props.
- **`Recipe`, `RecipeVersion`, `RecipeTest`, `RecipeTestPhoto` models** — the
  source of the agent's context payload (draft, chef notes, all test feedback).
- **shadcn/ui `Sheet`** (`resources/js/components/ui/`) — the chat slide-over
  surface; `dialog`, `card`, `button`, `badge`, `skeleton`, `sonner`,
  `textarea` compose the chat, proposal card, and states.
- **`HandleInertiaRequests`** shares the user's `locale` — agent UI chrome
  renders in the selected language with no new plumbing.
- **Feature-grouped controllers** — `app/Http/Controllers/Recipes/` already
  holds six recipe controllers; a new AI/chat controller follows the precedent.
- **Service classes** live in `app/Support/<Feature>/` — the Prism adapter,
  agent orchestration, and context-builder services follow this pattern.

### Established Patterns
- **Inertia mutations redirect, not JSON** — but **chat streaming cannot be an
  Inertia visit.** It needs a dedicated streaming route hit by `fetch`/XHR
  (precedent: Phase 1's silent `fetch()` for locale persistence; an SSE/chunked
  response is new ground for this codebase).
- **Models** via `php artisan make:model` with factories + seeders; Pest feature
  tests mirror `app/`. The AI provider must be **mocked** in tests — no live API
  calls in the suite.
- **Validation** — a `FormRequest` per action; shared rules into `app/Concerns/`
  traits. Agent edits reuse `UpdateRecipeDraftRequest`'s rules (AI-07).
- **Routing** — server-driven Inertia, named routes, Wayfinder regeneration;
  static route segments declared before the `{recipe}` wildcard (see the
  `routes/web.php` recipe block).
- **No AI dependency exists yet** — `composer.json` has no LLM SDK and
  `config/services.php` has no AI entry. This phase introduces the first AI
  integration (`prism-php/prism` + provider config).

### Integration Points
- **`composer.json`** — add `prism-php/prism` (approved).
- **`config/`** — new AI/provider configuration (Prism config + a recipe-agent
  config); `config/services.php` may gain provider key entries.
- **`routes/web.php`** — new recipe-scoped AI routes (open/load conversation,
  send message / stream, apply suggestion, create variant); declare static
  segments before the `recipes/{recipe}` wildcard.
- **`RecipeDraftManager` / `RecipeDraftController`** — the agent's apply-edit
  path; AI-07 requires the same validation.
- **`RecipeDuplicateController`** — invoked for AI variant creation.
- **`recipes/show.tsx`** — gains the "Ask AI" entry point and the chat `Sheet`.
- **New** — conversation + message tables and models (one thread per recipe,
  read-only, applied-edit markers), the AI/chat controller, the Prism adapter +
  agent orchestration + context-builder services under `app/Support/`, and new
  React components under `resources/js/components/recipes/` (chat sheet, message
  list, proposal card, starter-prompt empty state, streaming/error states).
- **Database queue** is configured — relevant if any agent work is offloaded,
  though streaming chat is request-synchronous.

</code_context>

<specifics>
## Specific Ideas

- The chat is **"attached to the recipe"** in the most literal sense — a
  slide-over over the builder, one persistent thread per recipe, the agent
  always re-reading the live draft. The chef edits and converses on one screen
  and watches agent edits land behind the sheet.
- **The proposal card is the contract** between conversation and action: prose
  is advice, the card is the reviewable, undoable change. "Apply directly +
  Recall as the safety net" works precisely because Phase 3 already built a
  trustworthy step-by-step undo — the agent inherits it.
- **One Apply = one Recall step** is the same principle Phase 3 chose so that AI
  edits would be individually undoable — Phase 5 cashes in that decision.
- A **variant is a separate recipe, not a version** — the chef develops it on
  its own track. Reusing the tested Duplicate path avoids inventing a parallel
  mechanism.
- **Test suggestions stay prose** — the kitchen log (Phase 4) records what was
  actually cooked; the agent must not populate it with experiments that were
  only imagined.
- **Provider-agnostic is real, not aspirational** — Prism gives genuine
  multi-provider support, and shipping *no* default forces the abstraction to be
  exercised from day one rather than hard-wired to one vendor.

</specifics>

<deferred>
## Deferred Ideas

- **Usage limits / cost guardrails / per-user rate limiting** — not part of
  AI-01…07; a sensible future addition once the agent ships and real API cost is
  observed. Note for the backlog.
- **Suggestion card that pre-fills Phase 4's record-test modal** — considered
  and rejected for v1 (test suggestions are prose only); revisit if chefs want a
  faster hand-off from "suggested experiment" to "recorded test".
- **Multiple named conversation threads per recipe** — considered; v1 ships one
  thread per recipe. A future enhancement if power users want topic-separated
  conversations.
- **Exposing recipe-editing tools as an external MCP server** (the literal
  reading of `Project.md` §6's "Laravel MCP" line) — not needed for the in-app
  agent; a possible future capability for third-party AI clients.
- **Publishing / sharing AI conversations** — out of scope; conversations are
  owner-private. Relates to Phase 6.

</deferred>

---

*Phase: 05-ai-agent*
*Context gathered: 2026-05-17*
