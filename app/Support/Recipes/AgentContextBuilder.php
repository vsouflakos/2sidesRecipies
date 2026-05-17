<?php

namespace App\Support\Recipes;

use App\Models\Recipe;
use App\Models\RecipeConversation;

class AgentContextBuilder
{
    /**
     * Build the system prompt for the AI agent, incorporating the live recipe draft,
     * chef notes, and all test feedback. Context is rebuilt fresh on every call (AI-02).
     *
     * When the serialized context exceeds config('ai.context_budget_chars'), the oldest
     * tests are dropped first. The draft and chef notes are always kept in full (Pitfall 5).
     */
    public function buildSystemPrompt(Recipe $recipe): string
    {
        $recipe->load(['draft', 'tests.recipeVersion', 'currentVersion']);

        $draft = $recipe->draft?->data ?? [];
        $notes = $recipe->notes ?? '';

        /** @var array<int, array{version: int|null, verdict: string|null, rating: int|null, notes: string|null, hypothesis: string|null, outcome: string|null, changes: array<mixed>|null}> $tests */
        $tests = $recipe->tests
            ->sortByDesc('tested_at')
            ->map(fn ($t) => [
                'version' => $t->recipeVersion?->version_number,
                'verdict' => $t->verdict?->value,
                'rating' => $t->overall_rating,
                'notes' => $t->tasting_notes,
                'hypothesis' => $t->hypothesis,
                'outcome' => $t->outcome_narrative,
                'changes' => $t->change_rows,
            ])
            ->values()
            ->toArray();

        $budget = (int) config('ai.context_budget_chars', 80000);

        // Render with all tests first; if over budget, drop oldest tests one at a time.
        $rendered = view('prompts.recipe-agent-system', compact('draft', 'notes', 'tests'))->render();

        while (strlen($rendered) > $budget && ! empty($tests)) {
            array_pop($tests);
            $rendered = view('prompts.recipe-agent-system', compact('draft', 'notes', 'tests'))->render();
        }

        return $rendered;
    }

    /**
     * Rebuild the conversation history as plain message arrays compatible with Prism.
     *
     * Returns one entry per user/assistant message in created_at order.
     * Tool-proposal rows are excluded.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function buildMessages(Recipe $recipe, RecipeConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();
    }
}
