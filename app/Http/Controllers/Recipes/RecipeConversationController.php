<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\SendConversationMessageRequest;
use App\Jobs\GenerateAgentReplyJob;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\RecipeSection;
use App\Support\Recipes\RecipeDraftManager;
use App\Support\Recipes\RecipeVersionService;
use App\Support\Recipes\SuggestionApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecipeConversationController extends Controller
{
    public function __construct(
        private readonly SuggestionApplier $suggestionApplier,
        private readonly RecipeVersionService $versionService,
        private readonly RecipeDraftManager $draftManager,
    ) {}

    /**
     * Return the conversation history and agent-turn status for a recipe as JSON.
     *
     * The frontend polls this endpoint while a turn is in progress: each call
     * returns the full message list (including any `tool_proposal` messages the
     * agent's tools have persisted so far) plus `agent_status` so the client
     * knows when the turn has finished (idle) or failed.
     */
    public function show(Recipe $recipe): JsonResponse
    {
        Gate::authorize('view', $recipe);

        $conversation = $recipe->conversation;

        if ($conversation === null) {
            return response()->json([
                'messages' => [],
                'agent_status' => 'idle',
                'agent_error' => null,
            ]);
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

        return response()->json([
            'messages' => $messages,
            'agent_status' => $conversation->agent_status,
            'agent_error' => $conversation->agent_error,
        ]);
    }

    /**
     * Queue an AI response to the user's chat message.
     *
     * The agentic turn runs in {@see GenerateAgentReplyJob} on the queue rather
     * than on this request: a multi-step turn can run for minutes, and holding
     * the HTTP connection open for that long has it severed by Herd's nginx
     * proxy timeout. This action returns immediately; the frontend polls
     * {@see self::show()} for progress.
     *
     * One turn per conversation: a second message posted while a turn is still
     * generating is rejected with 409 so two jobs cannot race the same thread.
     */
    public function store(SendConversationMessageRequest $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('view', $recipe);

        $conversation = $recipe->conversation
            ?? RecipeConversation::create(['recipe_id' => $recipe->id]);

        if ($conversation->agent_status === 'generating') {
            return response()->json([
                'status' => 'busy',
                'message' => 'A reply is already being generated for this conversation.',
            ], 409);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->string('message')->toString(),
        ]);

        // Mark the turn in progress before dispatch so the guard above and the
        // polling client both see 'generating' immediately — without waiting
        // for the queue worker to pick the job up.
        $conversation->update([
            'agent_status' => 'generating',
            'agent_error' => null,
            'agent_started_at' => now(),
        ]);

        GenerateAgentReplyJob::dispatch($recipe, $conversation);

        return response()->json(['status' => 'queued'], 202);
    }

    /**
     * Apply an accepted agent edit proposal to the recipe's working draft.
     *
     * The edit is validated through the same rules as UpdateRecipeDraftRequest
     * before touching the draft (AI-07). On failure the proposal_state is marked
     * 'failed' so the agent can receive the feedback on the next message.
     *
     * Returns 200 on success, 422 on validation failure, 409 for already-applied
     * or variant proposals.
     */
    public function apply(Request $request, Recipe $recipe, RecipeConversationMessage $message): JsonResponse
    {
        Gate::authorize('update', $recipe);

        // Scope guard: ensure the message belongs to this recipe's conversation
        abort_unless($message->conversation->recipe_id === $recipe->id, 404);

        try {
            $this->suggestionApplier->apply($recipe, $message);

            return response()->json(['status' => 'applied']);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Apply an accepted variant proposal: duplicate the recipe and apply the
     * proposed changes to the new recipe's draft.
     *
     * The duplication reuses the same logic as RecipeDuplicateController::store —
     * a new independent Recipe with its own v1 history and no lineage FK.
     *
     * Returns 200 with the variant recipe ID and URL on success.
     */
    public function variant(Request $request, Recipe $recipe, RecipeConversationMessage $message): JsonResponse
    {
        Gate::authorize('view', $recipe);

        // Scope guard: ensure the message belongs to this recipe's conversation
        abort_unless($message->conversation->recipe_id === $recipe->id, 404);

        if (
            data_get($message->proposal_state, 'kind') !== 'variant' ||
            data_get($message->proposal_state, 'status') !== 'pending'
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending variant proposals can be applied.',
            ], 409);
        }

        $recipe->load(['currentVersion', 'draft']);

        $variant = DB::transaction(function () use ($recipe, $message) {
            // Duplicate the recipe — same logic as RecipeDuplicateController::store
            $sourceSnapshot = $recipe->currentVersion?->snapshot ?? ($recipe->draft?->data ?? []);

            $variantName = ($recipe->name ?? 'Recipe').' (variant)';

            /** @var Recipe $variant */
            $variant = Recipe::create([
                'user_id' => auth()->id(),
                'name' => $variantName,
                'slug' => Str::slug($variantName).'-'.Str::random(6),
                'yield_amount' => $recipe->yield_amount,
                'yield_unit_id' => $recipe->yield_unit_id,
                'portions' => $recipe->portions,
                'prep_time_minutes' => $recipe->prep_time_minutes,
                'cook_time_minutes' => $recipe->cook_time_minutes,
                'difficulty' => $recipe->difficulty,
                'cuisine_id' => $recipe->cuisine_id,
                'notes' => $recipe->notes,
                'selling_price' => $recipe->selling_price,
            ]);

            // Create a default first section for the variant
            RecipeSection::create([
                'recipe_id' => $variant->id,
                'name' => 'Main',
                'order' => 1,
            ]);

            // Build initial draft data from the source snapshot
            $draftData = array_merge($sourceSnapshot, [
                'name' => $variantName,
            ]);

            // Create the draft for the variant
            $variantDraft = RecipeDraft::create([
                'recipe_id' => $variant->id,
                'user_id' => auth()->id(),
                'data' => $draftData,
                'edit_sequence' => 0,
            ]);

            // Load the draft relation so versionService can access it
            $variant->load('draft');

            // Commit v1 for the variant — its own independent history starting at v1
            $this->versionService->commit($variant, null, auth()->id());

            // Apply the proposed changes to the variant's draft
            $changes = data_get($message->proposal_state, 'changes', []);

            if (! empty($changes)) {
                $variantDraft->refresh();

                foreach ($changes as $change) {
                    $changeAction = $change['action'] ?? 'update';
                    $changeData = $change['data'] ?? [];
                    $this->draftManager->applyEdit($variantDraft, $changeAction, $changeData);
                    $variantDraft->refresh();
                }
            }

            return $variant;
        });

        // Mark the proposal as applied on the original message
        $proposalState = $message->proposal_state ?? [];
        $proposalState['status'] = 'applied';
        $proposalState['variant_recipe_id'] = $variant->id;
        $message->proposal_state = $proposalState;
        $message->save();

        return response()->json([
            'status' => 'applied',
            'variant_recipe_id' => $variant->id,
            'variant_url' => route('recipes.show', $variant),
        ]);
    }
}
