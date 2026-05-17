<?php

namespace App\Support\Recipes;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\Unit;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AgentOrchestrator
{
    public function __construct(
        private readonly PrismAdapter $prismAdapter,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    /**
     * Build the Prism tool surface for recipe editing.
     *
     * CRITICAL (Pitfall 1): tools ONLY record proposals in the DB;
     * they NEVER call RecipeDraftManager::applyEdit(). Draft mutation
     * happens only at explicit Apply (plan 03).
     *
     * @return array<int, \Prism\Prism\Tool>
     */
    public function buildTools(Recipe $recipe, RecipeConversation $conversation): array
    {
        // The valid `unit` symbols, embedded in the tool contract so the agent
        // never invents one (e.g. "ea") that the applier cannot resolve.
        $unitList = Unit::query()->orderBy('id')->pluck('symbol')->implode(', ');

        $proposeRecipeEdit = Tool::as('propose_recipe_edit')
            ->for('Propose a single structured edit to the recipe working draft. The user reviews it and clicks Apply or Dismiss. Does NOT change the draft directly. The edit is applied ON TOP of the current draft you see in context — never send a full new draft.')
            ->withStringParameter('action', 'The edit action — one of: update_metadata, add_ingredient_line, remove_ingredient_line, update_ingredient_line, update_section, add_step, update_step, apply_scale, add_sub_recipe')
            ->withStringParameter('summary', 'A short human-readable description of the change, e.g. "Reduce sugar 200 g to 150 g in Dough"')
            ->withStringParameter('dataJson', 'JSON object containing ONLY the action-specific delta fields — never the whole draft. Reference existing ids from the working-draft JSON in your context. '
                .'update_metadata: any of {name, yield_amount, portions, prep_time_minutes, cook_time_minutes, difficulty, cuisine_id, notes, selling_price}. '
                .'add_ingredient_line: {section_name OR section_id, ingredient_id, quantity, unit, prep_note?, yield_pct?, is_flour_base?} — ALWAYS call search_ingredients first and pass a returned ingredient_id; only when the search finds no suitable match, omit ingredient_id and pass a free-text ingredient_name instead. '
                .'remove_ingredient_line: {id} — the line id from the draft. '
                .'update_ingredient_line: {id} (the line id from the draft) plus only the fields to change, e.g. {quantity}, {unit}, {prep_note}, {yield_pct}, {is_flour_base}, {name}. '
                .'update_section: {section_id OR section_name, name?, order?}. '
                .'add_step: {section_name OR section_id, instruction}. '
                .'update_step: {step_id, instruction}. '
                .'apply_scale: {factor} or {scale_numerator, scale_denominator}. '
                .'add_sub_recipe: {section_name OR section_id, sub_recipe_version_id, quantity, unit}. '
                .'The `unit` field must be one of these exact symbols: '.$unitList.' — do NOT invent others (use "pc" for a countable piece).')
            ->using(function (string $action, string $summary, string $dataJson) use ($conversation): string {
                $conversation->messages()->create([
                    'role' => 'tool_proposal',
                    'content' => $summary,
                    'proposal_state' => [
                        'kind' => 'edit',
                        'action' => $action,
                        'data' => json_decode($dataJson, true) ?? [],
                        'status' => 'pending',
                    ],
                ]);

                return json_encode(['proposal_recorded' => true, 'summary' => $summary]);
            });

        $proposeRecipeVariant = Tool::as('propose_recipe_variant')
            ->for('Propose creating a new recipe variant (e.g. ingredient swaps) as a separate independent recipe. The user reviews and clicks Apply to create it.')
            ->withStringParameter('summary', 'Human-readable description of the variant, e.g. "Vegan version: butter to coconut oil, milk to oat milk"')
            ->withStringParameter('changesJson', 'JSON-encoded array of draft edits to apply to the new variant recipe after duplication')
            ->using(function (string $summary, string $changesJson) use ($conversation): string {
                $conversation->messages()->create([
                    'role' => 'tool_proposal',
                    'content' => $summary,
                    'proposal_state' => [
                        'kind' => 'variant',
                        'changes' => json_decode($changesJson, true) ?? [],
                        'status' => 'pending',
                    ],
                ]);

                return json_encode(['proposal_recorded' => true, 'summary' => $summary]);
            });

        $searchIngredients = Tool::as('search_ingredients')
            ->for('Search the ingredient catalog for ingredients matching a name. ALWAYS call this before proposing an add_ingredient_line edit, so the proposal references a real catalog ingredient by id instead of inventing a duplicate. Catalog names may be phrased differently from common usage (e.g. "feta cheese" is stored as "Cheese, feta, whole milk, crumbled"), so inspect every match. Returns up to 10 matches as a JSON array of {id, name}. An empty result means no such ingredient exists yet.')
            ->withStringParameter('query', 'The ingredient name or keywords to search for, e.g. "feta cheese" or "olive oil". Each word is matched independently, so word order and extra descriptive words do not matter.')
            ->using(function (string $query) use ($recipe): string {
                // Match each whitespace-separated term independently (AND) so a
                // multi-word query finds catalog names where the words are
                // present but not contiguous — "feta cheese" matches
                // "Cheese, feta, whole milk, crumbled".
                $terms = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                // Owner-visibility scope: official ingredients (user_id null) plus
                // the recipe owner's own private ingredients — mirrors RecipeSearchController.
                $matches = Ingredient::query()
                    ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $recipe->user_id))
                    ->where(function ($q) use ($terms): void {
                        foreach ($terms as $term) {
                            $q->where('name_cache', 'like', '%'.$term.'%');
                        }
                    })
                    ->orderBy('name_cache')
                    ->limit(10)
                    ->get(['id', 'name_cache'])
                    ->map(fn (Ingredient $i): array => ['id' => $i->id, 'name' => $i->name_cache])
                    ->all();

                return json_encode(['matches' => $matches]);
            });

        return [$proposeRecipeEdit, $proposeRecipeVariant, $searchIngredients];
    }

    /**
     * Build and return the Prism streaming request for the given recipe and conversation.
     *
     * Converts the plain-array message history from AgentContextBuilder into
     * Prism UserMessage/AssistantMessage objects before passing to withMessages().
     *
     * @return iterable<mixed>
     */
    public function buildStream(Recipe $recipe, RecipeConversation $conversation): iterable
    {
        $messageArrays = $this->contextBuilder->buildMessages($recipe, $conversation);

        $messages = array_map(
            fn (array $m) => $m['role'] === 'user'
                ? new UserMessage($m['content'])
                : new AssistantMessage($m['content']),
            $messageArrays,
        );

        return Prism::text()
            ->using($this->prismAdapter->provider(), $this->prismAdapter->model())
            ->withSystemPrompt($this->contextBuilder->buildSystemPrompt($recipe))
            ->withMessages($messages)
            ->withTools($this->buildTools($recipe, $conversation))
            // Finishing a recipe can mean searching the catalog for and proposing
            // many ingredients; 5 steps ran out mid-task. Allow room to search
            // every ingredient before proposing it.
            ->withMaxSteps(16)
            ->withMaxTokens($this->prismAdapter->maxTokens())
            ->asStream();
    }
}
