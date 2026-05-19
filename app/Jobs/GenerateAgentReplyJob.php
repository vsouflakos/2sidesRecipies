<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Support\Recipes\AgentOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Throwable;

/**
 * Runs one agentic recipe-chat turn off the HTTP request.
 *
 * The agent's multi-step tool use can run for minutes; holding an HTTP
 * connection open for that long is severed by Herd's nginx proxy timeout
 * (the bug this job exists to fix). Here the turn runs on the queue and the
 * frontend polls the conversation endpoint for progress instead.
 *
 * The orchestrator's tools persist `tool_proposal` messages themselves as they
 * run mid-iteration — unchanged. This job only accumulates the assistant's
 * free text and persists it as the final assistant message on success.
 */
class GenerateAgentReplyJob implements ShouldQueue
{
    use Queueable;

    /**
     * A full 16-step agentic turn against Ollama Cloud can run for minutes.
     */
    public int $timeout = 600;

    /**
     * Never auto-retry an AI turn — generation is non-idempotent and costly,
     * and a retry would duplicate any tool_proposal messages already persisted.
     */
    public int $tries = 1;

    public function __construct(
        public readonly Recipe $recipe,
        public readonly RecipeConversation $conversation,
    ) {}

    /**
     * Consume the agent stream to completion, persisting the assistant reply.
     *
     * On success the accumulated assistant text is persisted and the
     * conversation returns to 'idle'. On any failure the conversation is
     * marked 'failed' with the reason and NO partial assistant message is
     * persisted — the chat UI surfaces agent_error instead.
     */
    public function handle(AgentOrchestrator $orchestrator): void
    {
        $this->conversation->update([
            'agent_status' => 'generating',
            'agent_error' => null,
            'agent_started_at' => now(),
        ]);

        $full = '';

        try {
            foreach ($orchestrator->buildStream($this->recipe, $this->conversation) as $event) {
                if ($event instanceof TextDeltaEvent) {
                    $full .= $event->delta;
                }
            }

            // Persist the assistant message only after a successful iteration.
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => $full,
            ]);

            $this->conversation->update(['agent_status' => 'idle']);
        } catch (Throwable $e) {
            Log::error('AI agent reply job failed for recipe '.$this->recipe->id, [
                'exception' => $e,
            ]);

            $this->conversation->update([
                'agent_status' => 'failed',
                'agent_error' => $e->getMessage(),
            ]);
        }
    }
}
