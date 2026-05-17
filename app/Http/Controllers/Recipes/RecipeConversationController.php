<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\SendConversationMessageRequest;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Support\Recipes\AgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeConversationController extends Controller
{
    public function __construct(
        private readonly AgentOrchestrator $orchestrator,
    ) {}

    /**
     * Return the conversation history for a recipe as JSON.
     */
    public function show(Recipe $recipe): JsonResponse
    {
        Gate::authorize('view', $recipe);

        $conversation = $recipe->conversation;

        if ($conversation === null) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'proposal_state' => $m->proposal_state,
            ])
            ->values()
            ->toArray();

        return response()->json(['messages' => $messages]);
    }

    /**
     * Stream an AI response to the user's chat message via Server-Sent Events.
     *
     * Collects the full AI response synchronously, persists both the user message
     * and the assistant message, then returns a StreamedResponse that replays
     * the content as SSE tokens. If the AI request fails, no assistant message
     * is persisted (CONTEXT.md: failed turns discard partial output).
     *
     * NOTE: Collecting synchronously before streaming ensures the assistant
     * message is persisted within the HTTP request lifecycle. For Prism::fake()
     * in tests, this is effectively instant. In production, the first SSE token
     * is delayed until the provider response is complete; this is an acceptable
     * trade-off given the CONTEXT.md requirement that failed turns never persist
     * partial output.
     */
    public function stream(SendConversationMessageRequest $request, Recipe $recipe): StreamedResponse
    {
        Gate::authorize('view', $recipe);

        $conversation = $recipe->conversation
            ?? RecipeConversation::create(['recipe_id' => $recipe->id]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->string('message')->toString(),
        ]);

        // Collect the full response from Prism synchronously so the assistant
        // message can be persisted before the HTTP response is sent.
        $chunks = [];
        $errorMessage = null;

        try {
            foreach ($this->orchestrator->buildStream($recipe, $conversation) as $event) {
                if ($event instanceof TextDeltaEvent) {
                    $chunks[] = $event->delta;
                }
            }

            $fullContent = implode('', $chunks);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $fullContent,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        return response()->stream(function () use ($chunks, $errorMessage) {
            // Disable PHP output buffering so tokens stream immediately (Pitfall 3)
            @ini_set('output_buffering', 'off');
            ob_implicit_flush(true);

            if ($errorMessage !== null) {
                echo 'event: error'.PHP_EOL;
                echo 'data: '.json_encode(['message' => $errorMessage]).PHP_EOL.PHP_EOL;
                flush();

                return;
            }

            foreach ($chunks as $chunk) {
                echo 'event: token'.PHP_EOL;
                echo 'data: '.json_encode(['text' => $chunk]).PHP_EOL.PHP_EOL;
                flush();
            }

            echo 'event: done'.PHP_EOL;
            echo 'data: '.json_encode(['finished' => true]).PHP_EOL.PHP_EOL;
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
