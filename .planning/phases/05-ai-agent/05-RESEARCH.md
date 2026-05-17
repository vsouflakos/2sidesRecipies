# Phase 5: AI Agent - Research

**Researched:** 2026-05-17
**Domain:** LLM integration (Prism PHP), SSE streaming, AI chat UI, agent tool calling
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Chat surface & threads**
- Slide-over sheet on `recipes/show.tsx` (shadcn `Sheet`) — not a separate page
- On mobile/narrow screens the sheet becomes a full-screen overlay
- One ongoing, persistent thread per recipe — reopening resumes it
- Streaming responses (token-by-token) via a dedicated SSE/chunked route (not an Inertia visit)
- Read-only conversation history — no edit, delete, or bulk-clear
- Empty state shows 3–4 tappable starter prompts; no auto-greeting
- Minimal per-message chrome — user-vs-agent styling plus applied-edit marker; no timestamps
- Agent re-reads the live working draft on every message (no stale snapshot)

**Suggestion → Apply flow**
- Structured proposal card inside the agent's message with Apply/Dismiss actions
- Apply commits directly to the working draft — no separate preview step
- Recall undoes it (same as any draft edit)
- One Apply = one Recall step (even for bundles of changes)
- Validation at apply-time through `UpdateRecipeDraftRequest` / circular-reference detection (AI-07)
- Failed apply: error bubble in chat + agent receives failure feedback — draft untouched
- After apply, card locks to non-interactive "Applied" state

**Agent capabilities & variants**
- Full builder-parity editing tool surface — every action maps to an existing `RecipeDraftManager` action
- Variant (AI-05) reuses Phase 3's Duplicate path — new independent recipe, own v1 history, no lineage link
- Variant review via a linked proposal card; on Apply it creates the recipe and card becomes a link
- Test suggestions (AI-03) are prose only — no pre-created test records
- Context supplied in full, up front, on every message (draft + chef notes + all test feedback)

**AI provider & adapter**
- `prism-php/prism` backs the provider-agnostic adapter (approved new dependency)
- No baked-in default provider/model — deployer must configure
- No provider configured → "Ask AI" entry point is hidden/disabled
- AI call failure → error bubble + Retry; partial/streamed output discarded; draft never touched

### Claude's Discretion
- Exact wording and count of the starter prompts
- Adapter interface shape, config file structure (`config/ai.php` / `config/prism.php` / `services.php`)
- System-prompt design and tool schemas exposed to the model
- Conversation/message data model (tables, proposal card persistence)
- Streaming transport details (SSE vs chunked) and typing/loading indicator design
- Token/context-window management — truncation or summarization strategy
- Language of agent prose responses vs translatable UI chrome (i18n strategy)
- Visual separation of reasoning prose from proposal card
- Empty-state and applied-edit-marker visual design

### Deferred Ideas (OUT OF SCOPE)
- Usage limits / cost guardrails / per-user rate limiting
- Suggestion card that pre-fills Phase 4's record-test modal
- Multiple named conversation threads per recipe
- Exposing recipe-editing tools as an external MCP server
- Publishing / sharing AI conversations
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AI-01 | User can chat with an AI agent attached to a recipe | Prism PHP streaming + SSE route + Sheet component + conversation model |
| AI-02 | The agent reads the recipe, chef notes, and test feedback as context | Context builder service serializing draft + notes + RecipeTest records into system prompt |
| AI-03 | The agent can suggest tests/experiments and recipe improvements | Prose response (no tool call needed); agent instructed via system prompt to format suggestions clearly |
| AI-04 | User can accept an agent suggestion, applying the edit to the working draft | Proposal card Apply action → dedicated apply route → RecipeDraftManager.applyEdit() |
| AI-05 | The agent can create a recipe variant as a working draft | Proposal card variant action → invoke RecipeDuplicateController path then apply swaps |
| AI-06 | The AI provider is configurable via a provider-agnostic adapter | Prism PHP config/prism.php + app-level config/ai.php wrapping provider/model selection |
| AI-07 | Agent edits pass through the same validation as user edits before touching the draft | Apply route reuses UpdateRecipeDraftRequest + CircularReferenceDetector before calling RecipeDraftManager |
</phase_requirements>

---

## Summary

Phase 5 introduces the first AI dependency in the project: `prism-php/prism` v0.100.1, a Laravel-native unified LLM package. The primary integration challenge is the streaming chat route — unlike all existing Inertia mutations (which redirect), a streaming chat requires a plain `response()->stream()` endpoint returning `text/event-stream` data consumed by client-side `fetch()` + manual event parsing. This is new ground for this codebase but the pattern is well-established in Laravel.

The data model is modest: two new tables (`recipe_conversations`, `recipe_conversation_messages`) to store one thread per recipe and its messages with role, content, and applied-edit status. The proposal card state (applied/dismissed) is persisted as a JSON field on the message row — not a separate table — since proposals are tied to a single message and are never queried independently.

Tool calling in Prism PHP (via `Tool::as()`) enables the agent to make structured recipe edits. However, the architectural decision to supply full context up front on every message (AI-02) means the core interaction is a text generation call with a rich system prompt. Tool calls are used for the "Apply to draft" path: the agent emits structured edit proposals that the backend converts to `RecipeDraftManager.applyEdit()` calls.

**Primary recommendation:** Use `Prism::text()->asEventStreamResponse()` for the chat stream route; persist conversation history server-side and rebuild the `withMessages()` array on each request; use Prism's `Tool::as()` to define the recipe-editing surface; mock provider in all tests via `Prism::fake()`.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| prism-php/prism | ^0.100.1 | Unified LLM provider adapter | Approved in CONTEXT.md; native Laravel, supports streaming + tool calling + multi-provider |
| PHP 8.3 | 8.3 | Runtime | Already in use |
| Laravel 13 | 13.x | Framework | Already in use |

### Supporting (already installed, used for new features)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| shadcn/ui Sheet | existing | Chat slide-over panel | Already used for VersionHistorySheet — exact same pattern |
| shadcn/ui Card | existing | Proposal card container | Structured visual card within a message |
| shadcn/ui Button | existing | Apply/Dismiss actions | Standard interactive elements |
| shadcn/ui Skeleton | existing | Streaming/loading state | Animated placeholder while AI generates |
| shadcn/ui Textarea | existing | Message input | Existing primitive |
| shadcn/ui Badge | existing | Applied-edit marker | Status labeling |
| Sonner | existing | Error/success toasts | Flash messages |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `response()->stream()` SSE | WebSockets / Pusher | SSE is simpler, unidirectional, no extra infra needed for this use case |
| `response()->stream()` SSE | `asDataStreamResponse()` (Vercel AI SDK protocol) | Vercel protocol requires `@ai-sdk/react` on the frontend; plain SSE + fetch is lighter and fully under our control |
| Manual SSE client (fetch + ReadableStream) | Browser `EventSource` API | EventSource cannot send POST requests or custom headers; `fetch()` with `ReadableStream` is required for authenticated POST streaming |

**Installation:**
```bash
composer require prism-php/prism
php artisan vendor:publish --tag=prism-config
```

**Version verification:** `prism-php/prism` is at v0.100.1 (released 2026-03-20, confirmed via Packagist). PHP requirement is ^8.2 — project runs 8.3, compatible.

---

## Architecture Patterns

### Recommended Project Structure (additions for Phase 5)

```
app/
├── Http/
│   ├── Controllers/Recipes/
│   │   └── RecipeConversationController.php   # show + store (stream) + apply + variant
│   └── Requests/Recipes/
│       └── SendConversationMessageRequest.php
│       └── ApplyConversationSuggestionRequest.php
├── Models/
│   ├── RecipeConversation.php   # one per recipe, hasMany messages
│   └── RecipeConversationMessage.php  # role, content, proposal_state JSON
├── Support/Recipes/
│   ├── AgentContextBuilder.php  # serializes draft + notes + tests → system prompt payload
│   ├── AgentOrchestrator.php    # builds Prism request, handles tool resolution
│   └── PrismAdapter.php         # wraps Prism::text(), reads config/ai.php
config/
└── ai.php   # provider, model, max_tokens — reads from config/prism.php for API keys
database/migrations/
└── XXXXXX_create_recipe_conversations_table.php
└── XXXXXX_create_recipe_conversation_messages_table.php
resources/js/
├── components/recipes/ai-chat/
│   ├── ai-chat-sheet.tsx           # Sheet wrapper, open/close state
│   ├── message-list.tsx            # scrollable message log
│   ├── message-bubble.tsx          # user vs agent styling
│   ├── proposal-card.tsx           # structured edit proposal + Apply/Dismiss
│   ├── starter-prompts.tsx         # empty state suggested prompts
│   └── streaming-indicator.tsx     # animated typing indicator
└── hooks/
    └── use-ai-chat.ts              # chat state, fetch stream, message management
tests/Feature/Recipes/
└── RecipeConversationTest.php
```

### Pattern 1: SSE Chat Stream (PHP side)

**What:** The chat message POST returns an SSE stream via `response()->stream()`. Prism's `asEventStreamResponse()` is used internally; the controller wraps it to persist messages after the stream completes.

**When to use:** Any time a chat message needs a streaming AI response.

**Example:**
```php
// Source: https://prismphp.com/core-concepts/streaming-output/
// Source: https://www.d4b.dev/blog/2026-03-30-streaming-ai-responses-to-the-browser-with-server-sent-events

public function stream(SendConversationMessageRequest $request, Recipe $recipe): StreamedResponse
{
    Gate::authorize('view', $recipe);

    $conversation = $recipe->conversation ?? RecipeConversation::create(['recipe_id' => $recipe->id]);
    $userMessage = $conversation->messages()->create([
        'role' => 'user',
        'content' => $request->string('message')->toString(),
    ]);

    $messages = $this->agentContextBuilder->buildMessages($recipe, $conversation);
    $tools    = $this->agentOrchestrator->buildTools($recipe);

    return response()->stream(function () use ($messages, $tools, $conversation, $recipe) {
        $fullContent = '';

        $stream = Prism::text()
            ->using($this->prismAdapter->provider(), $this->prismAdapter->model())
            ->withSystemPrompt($this->agentContextBuilder->buildSystemPrompt($recipe))
            ->withMessages($messages)
            ->withTools($tools)
            ->withMaxSteps(5)
            ->asStream();

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $fullContent .= $event->delta;
                echo 'event: token' . PHP_EOL;
                echo 'data: ' . json_encode(['text' => $event->delta]) . PHP_EOL . PHP_EOL;
                flush();
            }
        }

        // Persist assistant message after stream completes
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $fullContent,
        ]);

        echo 'event: done' . PHP_EOL;
        echo 'data: ' . json_encode(['finished' => true]) . PHP_EOL . PHP_EOL;
        flush();
    }, 200, [
        'Content-Type'      => 'text/event-stream; charset=utf-8',
        'Cache-Control'     => 'no-cache, no-transform',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

### Pattern 2: SSE Chat Stream (React side)

**What:** Client uses `fetch()` (not `EventSource`) because the request is POST with authentication. Reads the `ReadableStream` body manually.

**When to use:** Initiating the streaming chat request from React.

**Example:**
```typescript
// Manual fetch + ReadableStream consumption (EventSource cannot do POST)
async function sendMessage(recipeId: number, message: string, onToken: (t: string) => void) {
    const response = await fetch(`/recipes/${recipeId}/conversation/stream`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content,
        },
        body: JSON.stringify({ message }),
    });

    const reader = response.body!.getReader();
    const decoder = new TextDecoder();

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        const chunk = decoder.decode(value, { stream: true });
        // Parse SSE lines: "event: token\ndata: {...}\n\n"
        for (const line of chunk.split('\n')) {
            if (line.startsWith('data: ')) {
                try {
                    const payload = JSON.parse(line.slice(6));
                    if (payload.text) onToken(payload.text);
                } catch {}
            }
        }
    }
}
```

### Pattern 3: Prism Tool Definition (Recipe Edit Surface)

**What:** Each `RecipeDraftManager` action maps to a Prism tool. The agent calls the tool with action + data; the backend records the call as a proposal card pending Apply.

**When to use:** Building the agent's editing capability surface.

**Example:**
```php
// Source: https://prismphp.com/core-concepts/tools-function-calling/
use Prism\Prism\Facades\Tool;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ArraySchema;

Tool::as('propose_recipe_edit')
    ->for('Propose a structured edit to the recipe working draft. Returns a proposal ID for the user to Accept or Dismiss.')
    ->withStringParameter('action', 'The draft action name (e.g. update_section, add_ingredient_line, update_metadata)')
    ->withStringParameter('summary', 'Human-readable summary of the proposed change shown to the user')
    ->withObjectParameter('data', 'The full new draft data or field delta to apply', [
        // Deliberately loose - agent fills based on context
    ], requiredFields: [])
    ->using(function (string $action, string $summary, array $data) use ($recipe, $conversation): string {
        // Store the proposal in the DB; return proposal ID for reference in prose
        $proposal = $conversation->messages()->create([
            'role'           => 'tool_proposal',
            'content'        => $summary,
            'proposal_state' => ['action' => $action, 'data' => $data, 'status' => 'pending'],
        ]);
        return json_encode(['proposal_id' => $proposal->id, 'summary' => $summary]);
    });
```

### Pattern 4: Prism Fake in Tests

**What:** All tests use `Prism::fake()` — no live API calls.

**When to use:** Every feature test touching the conversation or stream routes.

**Example:**
```php
// Source: https://prismphp.com/core-concepts/testing.html
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Enums\FinishReason;

Prism::fake([
    TextResponseFake::make()
        ->withText('Here is my suggestion for improving the dough...')
        ->withFinishReason(FinishReason::Stop),
]);

$response = $this->actingAs($user)
    ->post(route('recipes.conversation.stream', $recipe), ['message' => 'How can I improve this?']);

$response->assertOk();
Prism::assertCallCount(1);
```

### Pattern 5: Context Builder (AI-02)

**What:** `AgentContextBuilder` serializes the recipe's current draft, chef notes, and all test records into the system prompt.

**When to use:** On every chat message (full context up front).

```php
class AgentContextBuilder
{
    public function buildSystemPrompt(Recipe $recipe): string
    {
        $recipe->load(['draft', 'tests.photos', 'currentVersion']);

        $draft    = $recipe->draft?->data ?? [];
        $notes    = $recipe->notes ?? '';
        $tests    = $recipe->tests->map(fn($t) => [
            'version'  => $t->recipeVersion?->version_number,
            'verdict'  => $t->verdict?->value,
            'rating'   => $t->overall_rating,
            'notes'    => $t->tasting_notes,
            'changes'  => $t->change_rows,
        ])->toArray();

        return view('prompts.recipe-agent-system', compact('draft', 'notes', 'tests'))->render();
    }

    /** Rebuild conversation history as Prism message objects. */
    public function buildMessages(Recipe $recipe, RecipeConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => $m->role === 'user'
                ? new UserMessage($m->content)
                : new AssistantMessage($m->content))
            ->all();
    }
}
```

### Anti-Patterns to Avoid

- **Storing full context in the messages table:** Do not store the system prompt or draft snapshot in the DB per message. Rebuild it fresh on each request (AI-02 requires live draft).
- **Using `router.post()` (Inertia) for the stream route:** Inertia mutations follow redirects; streaming requires a raw `fetch()` with manual response handling.
- **Using `EventSource` API for POST:** `EventSource` is GET-only. Use `fetch()` with `ReadableStream` for authenticated POST streaming.
- **Nested `response()->stream()` with Telescope active:** Laravel Telescope can intercept and consume the stream prematurely in development. Document this known issue.
- **Returning validation errors as Inertia props from the stream route:** The stream route is not an Inertia endpoint — return JSON error responses that the React hook handles explicitly.
- **Using a single "apply" call for multi-field bundles:** The `RecipeDraftManager.applyEdit()` method records one logical action. For a variant (which requires duplicating then editing), wrap the sequence in a DB transaction so it appears as one Recall step.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Provider-agnostic LLM API | Custom HTTP clients per provider | `prism-php/prism` | Handles streaming, tool calling, retries, provider diffs across OpenAI/Anthropic/Gemini/Ollama |
| Streaming response chunking | Manual ob_start/ob_flush patterns | `response()->stream()` + `flush()` | Laravel built-in, well-tested with nginx/Apache buffering headers |
| Tool parameter schema validation | Custom schema validation | Prism's `Tool::as()->with*Parameter()` | Type-safe parameter descriptions the LLM understands |
| Test provider mocking | Custom HTTP mock for AI API | `Prism::fake()` | First-class testing support, assertions on call count and prompt content |
| SSE event formatting | Manual `data: ` string building | Standard SSE format (`event:\ndata:\n\n`) | Simple and standard; don't add a library for this |
| Conversation history reconstruction | Re-querying and manually building arrays | `AgentContextBuilder::buildMessages()` using Prism's `UserMessage`/`AssistantMessage` | Prism handles multi-turn message arrays natively |

**Key insight:** Prism PHP eliminates the need for any provider-specific SDK. The entire LLM integration surface is `Prism::text()`, `Tool::as()`, and `Prism::fake()`. Keep the adapter thin — it only reads config and delegates to Prism.

---

## Common Pitfalls

### Pitfall 1: Draft Touchpoints During Stream Failure

**What goes wrong:** If the stream errors mid-way (provider timeout, network drop), any draft mutations already triggered by tool calls in that stream may have applied.

**Why it happens:** Tool call handling happens synchronously during streaming; a tool could call `applyEdit()` before the stream is fully consumed.

**How to avoid:** Per CONTEXT.md decision — failed turns NEVER touch the working draft. Implement tool calls as "proposal recording" only (store in DB, return proposal ID). Draft mutations happen only when the user explicitly clicks Apply, in a separate HTTP request, through the normal apply route.

**Warning signs:** Any `RecipeDraftManager.applyEdit()` call inside a Prism tool handler.

### Pitfall 2: Stale Draft Context

**What goes wrong:** Agent suggests a change to "Reduce sugar to 150g" but the draft already has 150g because the chef applied a prior suggestion.

**Why it happens:** Context was built once at session start and not refreshed.

**How to avoid:** Per CONTEXT.md decision — rebuild the system prompt from the live draft on EVERY message. `AgentContextBuilder.buildSystemPrompt()` always re-reads `$recipe->draft->data` fresh.

**Warning signs:** Caching the system prompt in session or in the conversation row.

### Pitfall 3: Nginx/PHP-FPM Output Buffering

**What goes wrong:** SSE tokens appear all at once at the end instead of streaming.

**Why it happens:** Nginx buffers the response by default. PHP-FPM may also buffer.

**How to avoid:** Set `X-Accel-Buffering: no` header in the stream response. For PHP-FPM: set `fastcgi_buffering off` in nginx config, or use `@ini_set('output_buffering', 'off')` + `ob_implicit_flush(true)` inside the stream callback.

**Warning signs:** Stream works locally but not in production/staging behind nginx.

### Pitfall 4: Prism + Laravel Telescope Conflict

**What goes wrong:** Stream appears to complete instantly in development with Telescope active; all tokens arrive in one burst.

**Why it happens:** Telescope intercepts the HTTP client and may buffer the response.

**How to avoid:** Disable Telescope's HTTP client watcher when streaming, or exclude the stream route from Telescope monitoring. Document in `config/telescope.php`.

**Warning signs:** Streaming works fine on production but not locally when `TELESCOPE_ENABLED=true`.

### Pitfall 5: Token/Context Window Overflow

**What goes wrong:** A recipe with many tests and long tasting notes causes the context to exceed the model's context window.

**Why it happens:** Full-context-up-front (AI-02) with no truncation strategy.

**How to avoid:** Implement a context size budget. Measure the serialized context byte count before sending. If over a threshold (e.g., 80% of model's context window), truncate older test records first (keep most recent N tests). Document the strategy in `AgentContextBuilder`.

**Warning signs:** Prism throws a context-length error for recipes with many test records.

### Pitfall 6: Missing CSRF Token on Stream Route

**What goes wrong:** The streaming POST returns 419 (CSRF mismatch).

**Why it happens:** The `fetch()` call from React doesn't automatically include the CSRF token (unlike Inertia's `router` methods).

**How to avoid:** Read the CSRF token from the meta tag (`document.querySelector('meta[name="csrf-token"]').content`) and include it as `X-CSRF-TOKEN` header in the `fetch()` call. The `use-ai-chat.ts` hook must always set this header.

**Warning signs:** 419 responses in the browser network tab on the stream endpoint.

### Pitfall 7: Proposal Card State on Page Refresh

**What goes wrong:** After page refresh, pending proposals show as still "pending" even after being applied.

**Why it happens:** Proposal state is held only in React state, not persisted to the server.

**How to avoid:** Persist `proposal_state` (pending/applied/dismissed/failed) in the `recipe_conversation_messages` table as a JSON column. Load it when the conversation is fetched.

**Warning signs:** Proposal card Apply button is re-clickable after refresh.

---

## Code Examples

### Prism Basic Text with Multi-Turn Messages
```php
// Source: https://prismphp.com/core-concepts/text-generation/
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;

$response = Prism::text()
    ->using('anthropic', 'claude-3-5-sonnet-20241022')
    ->withSystemPrompt('You are a culinary recipe expert...')
    ->withMessages([
        new UserMessage('How can I reduce the fat in this brioche?'),
        new AssistantMessage('You could substitute some butter with Greek yogurt...'),
        new UserMessage('What ratio should I use?'),
    ])
    ->asText();

echo $response->text;
```

### Prism SSE Streaming
```php
// Source: https://prismphp.com/core-concepts/streaming-output/
return Prism::text()
    ->using('anthropic', 'claude-3-5-sonnet-20241022')
    ->withSystemPrompt($systemPrompt)
    ->withMessages($messages)
    ->asEventStreamResponse(function ($request, $events) use ($conversation) {
        // Called after stream completes — persist assistant message
        $fullText = $events
            ->filter(fn($e) => $e instanceof TextDelta)
            ->map(fn($e) => $e->delta)
            ->implode('');
        $conversation->messages()->create(['role' => 'assistant', 'content' => $fullText]);
    });
```

### Prism Config (config/prism.php)
```php
// Source: https://prismphp.com/getting-started/configuration/
return [
    'prism_server' => ['enabled' => env('PRISM_SERVER_ENABLED', false)],
    'request_timeout' => env('PRISM_REQUEST_TIMEOUT', 30),
    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
        ],
    ],
];
```

### App-Level AI Config (config/ai.php — new file)
```php
// Claude's Discretion: this is the recommended shape
return [
    // Provider name must match a key in config/prism.php providers array
    'provider' => env('AI_PROVIDER', ''),  // '' means not configured → hide "Ask AI"
    'model'    => env('AI_MODEL', ''),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),
    'context_budget_chars' => (int) env('AI_CONTEXT_BUDGET_CHARS', 80000),
];
```

### Checking Provider Configured (hide "Ask AI")
```php
// In HandleInertiaRequests::share() or RecipeController::show()
'ai_enabled' => filled(config('ai.provider')) && filled(config('ai.model')),
```

```typescript
// In show.tsx — only render "Ask AI" trigger when enabled
{aiEnabled && <AiChatTrigger recipeId={recipe.id} />}
```

### Tool Definition Pattern
```php
// Source: https://prismphp.com/core-concepts/tools-function-calling/
Tool::as('propose_edit')
    ->for('Propose a recipe edit. The user can Accept or Dismiss it.')
    ->withStringParameter('action', 'RecipeDraftManager action name')
    ->withStringParameter('summary', 'Human-readable change description')
    ->withStringParameter('data_json', 'JSON-encoded new data for the action')
    ->withMaxSteps(5)   // Note: withMaxSteps is on the PendingRequest, not the Tool
    ->using(function (string $action, string $summary, string $dataJson) use ($pendingProposals): string {
        $pendingProposals[] = ['action' => $action, 'summary' => $summary, 'data' => json_decode($dataJson, true)];
        return "Proposal recorded. Summary: {$summary}";
    });
```

### Pest Test with Prism Fake
```php
// Source: https://prismphp.com/core-concepts/testing.html
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Enums\FinishReason;

test('agent responds to recipe question', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Consider reducing the butter by 20%.')
            ->withFinishReason(FinishReason::Stop),
    ]);

    config(['ai.provider' => 'anthropic', 'ai.model' => 'claude-3-5-sonnet-20241022']);

    $user   = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->post(route('recipes.conversation.stream', $recipe), ['message' => 'How can I improve the dough?']);

    $response->assertOk();
    Prism::assertCallCount(1);

    expect($recipe->conversation()->first()->messages()->where('role', 'assistant')->count())->toBe(1);
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-provider SDK (openai-php/client, etc.) | Prism PHP unified adapter | ~2024 | One package, all providers, same API surface |
| `EventSource` for streaming POST | `fetch()` + `ReadableStream` manual parsing | N/A (EventSource always GET-only) | Must use fetch for authenticated POST streams |
| Axios for HTTP | Native `fetch()` (Inertia v3 removed Axios) | Inertia v3 | Already consistent — use `fetch()` throughout |
| `Inertia::lazy()` / `LazyProp` | `Inertia::optional()` | Inertia v3 | Conversation load-on-open should use deferred props if needed |

**Deprecated/outdated:**
- `Inertia::lazy()`: Removed in v3 — use `Inertia::optional()` or `Inertia::defer()` for the conversation history prop
- `router.cancel()`: Replaced by `router.cancelAll()` in Inertia v3

---

## Open Questions

1. **Blade view vs. PHP string for system prompt**
   - What we know: System prompt needs to embed serialized recipe data (draft JSON, test records)
   - What's unclear: Whether a Blade `resources/views/prompts/` view is worth the overhead vs. a plain PHP heredoc in `AgentContextBuilder`
   - Recommendation: Use a Blade view file (`resources/views/prompts/recipe-agent-system.blade.php`) — keeps the prompt editable without touching PHP, testable via `view()->render()`

2. **Conversation history load strategy on sheet open**
   - What we know: The sheet opens via a trigger on `show.tsx`; history is server-side
   - What's unclear: Whether to load conversation via a separate fetch on open, or add it as an Inertia deferred prop on `recipes.show`
   - Recommendation: Deferred Inertia prop (`Inertia::defer()`) for `conversation` on `recipes.show` — consistent with how `test_summary` is already loaded; the sheet's empty/skeleton state plays during load

3. **context-window budget enforcement for large recipes**
   - What we know: Full context is sent on every message; context budget chars from config
   - What's unclear: Exact character limits vary per provider/model; truncation strategy needs to preserve the most recent tests (most useful) over older ones
   - Recommendation: Serialize tests newest-first; stop adding tests when budget is exhausted; always include full draft and notes (these are small relative to test history)

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest v4 + PHPUnit v12 |
| Config file | `phpunit.xml` / `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=RecipeConversation` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AI-01 | User can open conversation and send a message | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |
| AI-02 | Agent context includes draft + notes + all test feedback | Unit | `php artisan test --compact --filter=AgentContextBuilderTest` | ❌ Wave 0 |
| AI-03 | Agent response is streamed and persisted | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |
| AI-04 | Apply suggestion writes to draft via RecipeDraftManager | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |
| AI-05 | Variant creation duplicates recipe and applies changes | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |
| AI-06 | Provider is configurable; empty provider hides "Ask AI" | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |
| AI-07 | Agent edits run through UpdateRecipeDraftRequest validation | Feature | `php artisan test --compact --filter=RecipeConversationTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=RecipeConversation`
- **Per wave merge:** `php artisan test --compact tests/Feature/Recipes/`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Recipes/RecipeConversationTest.php` — covers AI-01 through AI-07
- [ ] `tests/Unit/Support/Recipes/AgentContextBuilderTest.php` — covers AI-02 context serialization
- [ ] Prism fake setup via `Prism::fake()` in `tests/Pest.php` or `beforeEach()` for AI tests

---

## Sources

### Primary (HIGH confidence)
- `https://prismphp.com/getting-started/introduction/` — Prism PHP capabilities, providers, installation
- `https://prismphp.com/getting-started/configuration/` — Config file structure, provider API keys
- `https://prismphp.com/core-concepts/streaming-output/` — `asEventStreamResponse()`, event types, callback pattern
- `https://prismphp.com/core-concepts/text-generation/` — `withMessages()`, `UserMessage`, `AssistantMessage`, system prompts
- `https://prismphp.com/core-concepts/tools-function-calling/` — `Tool::as()`, `with*Parameter()`, multi-step, tool results
- `https://prismphp.com/core-concepts/testing.html` — `Prism::fake()`, `TextResponseFake`, `assertCallCount()`
- `https://packagist.org/packages/prism-php/prism` — Version v0.100.1, PHP ^8.2, Laravel ^11|^12|^13
- `https://www.d4b.dev/blog/2026-03-30-streaming-ai-responses-to-the-browser-with-server-sent-events` — Laravel SSE + React fetch pattern (March 2026)
- Codebase: `app/Support/Recipes/RecipeDraftManager.php` — `applyEdit()` / `recall()` exact signatures
- Codebase: `app/Http/Controllers/Recipes/RecipeDuplicateController.php` — Variant creation path
- Codebase: `app/Http/Controllers/Recipes/RecipeDraftController.php` — `UpdateRecipeDraftRequest` validation path
- Codebase: `resources/js/components/recipes/recipe-builder/version-history-sheet.tsx` — Existing Sheet pattern to follow

### Secondary (MEDIUM confidence)
- `https://laravel.com/blog/prism-makes-ai-feel-laravel-native-the-artisan-of-the-day-is-tj-miller` — Laravel official blog endorsement of Prism PHP
- `https://serversideup.net/blog/sending-server-sent-events-with-laravel/` — SSE headers pattern verified with official Laravel `response()->stream()`

### Tertiary (LOW confidence)
- `https://medium.com/@brice_hartmann/simple-llm-tool-calling-in-laravel-using-prism` — Tool calling example (needs verification against current Prism API)

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Prism v0.100.1 confirmed on Packagist; all other deps already installed
- Architecture: HIGH — patterns derived from existing codebase conventions + official Prism docs
- Pitfalls: HIGH — SSE buffering and CSRF pitfalls are well-documented; draft-safety is directly from CONTEXT.md decisions
- Streaming pattern: HIGH — verified against official Prism docs and a March 2026 Laravel SSE article

**Research date:** 2026-05-17
**Valid until:** 2026-06-17 (Prism is actively developed; check for breaking changes in minor versions before implementation)
