<?php

namespace App\Support\Recipes;

use App\Concerns\RecipeValidationRules;
use App\Models\Recipe;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SuggestionApplier
{
    use RecipeValidationRules;

    public function __construct(
        private readonly RecipeDraftManager $draftManager,
        private readonly CircularReferenceDetector $circularReferenceDetector,
    ) {}

    /**
     * Validate and apply an agent edit proposal to the recipe's working draft.
     *
     * Handles only proposals of kind 'edit'. Variant proposals are handled by
     * the controller's variant action.
     *
     * The proposal data runs through the same recipeDraftDataRules() validation
     * as UpdateRecipeDraftRequest before any draft mutation occurs (AI-07).
     *
     * On success: proposal_state['status'] = 'applied', edit_sequence += 1.
     * On failure: proposal_state['status'] = 'failed' with a reason, draft untouched.
     *
     * @throws \InvalidArgumentException If the proposal is not pending, or is a variant.
     * @throws ValidationException If the proposal data fails validation.
     */
    public function apply(Recipe $recipe, RecipeConversationMessage $message): void
    {
        // Guard: only pending proposals can be applied
        if (
            $message->role !== 'tool_proposal' ||
            data_get($message->proposal_state, 'status') !== 'pending'
        ) {
            throw new \InvalidArgumentException(
                'Only pending tool_proposal messages can be applied.'
            );
        }

        // Guard: only 'edit' kind — variants are handled by the variant action
        if (data_get($message->proposal_state, 'kind') !== 'edit') {
            throw new \InvalidArgumentException(
                'Variant proposals must be applied via the variant action.'
            );
        }

        $action = data_get($message->proposal_state, 'action', '');
        $proposalData = data_get($message->proposal_state, 'data', []);

        // Ensure a draft exists — mirror RecipeDraftController::update default-draft creation
        $draft = $recipe->draft ?? RecipeDraft::create([
            'recipe_id' => $recipe->id,
            'user_id' => auth()->id(),
            'data' => [],
            'edit_sequence' => 0,
        ]);

        // Resolve new draft data: field/value delta or full replacement
        if (is_array($proposalData) && array_key_exists('field', $proposalData) && array_key_exists('value', $proposalData)) {
            $newData = $draft->data ?? [];
            data_set($newData, $proposalData['field'], $proposalData['value']);
        } else {
            $newData = is_array($proposalData) ? $proposalData : ($draft->data ?? []);
        }

        // VALIDATION (AI-07): run through the same rules as UpdateRecipeDraftRequest,
        // then also validate data content against recipe metadata rules (prefixed with 'data.').
        // The 'required' modifier is stripped from nested rules — partial updates are allowed.
        $wrapperRules = $this->recipeDraftDataRules();

        $metadataRules = [];
        foreach ($this->recipeMetadataRules() as $field => $rules) {
            $prefixedRules = array_filter(
                (array) $rules,
                fn ($rule) => $rule !== 'required'
            );
            array_unshift($prefixedRules, 'sometimes');
            $metadataRules['data.'.$field] = array_values($prefixedRules);
        }

        $allRules = array_merge($wrapperRules, $metadataRules);

        $validator = Validator::make(
            ['action' => $action, 'data' => $newData],
            $allRules
        );

        if ($validator->fails()) {
            $failureReason = $validator->errors()->first();

            $proposalState = $message->proposal_state ?? [];
            $proposalState['status'] = 'failed';
            $proposalState['failure_reason'] = $failureReason;
            $message->proposal_state = $proposalState;
            $message->save();

            throw ValidationException::withMessages([
                'proposal' => [$failureReason],
            ]);
        }

        // CIRCULAR-REF GUARD: reject sub-recipe additions that would create a cycle
        if ($action === 'add_sub_recipe' || $action === 'attach_sub_recipe') {
            $candidateRecipeId = data_get($newData, 'candidate_recipe_id')
                ?? data_get($newData, 'sub_recipe_version_id');

            if ($candidateRecipeId !== null) {
                if ($this->circularReferenceDetector->wouldCreateCycle($recipe->id, (int) $candidateRecipeId)) {
                    $proposalState = $message->proposal_state ?? [];
                    $proposalState['status'] = 'failed';
                    $proposalState['failure_reason'] = 'would create a circular reference';
                    $message->proposal_state = $proposalState;
                    $message->save();

                    throw ValidationException::withMessages([
                        'proposal' => ['Cannot add this recipe — it would create a circular reference.'],
                    ]);
                }
            }
        }

        // APPLY: exactly ONE applyEdit call — one Recall step per CONTEXT.md
        $this->draftManager->applyEdit($draft, $action, $newData);

        // Mark proposal as applied
        $proposalState = $message->proposal_state ?? [];
        $proposalState['status'] = 'applied';
        $message->proposal_state = $proposalState;
        $message->save();
    }
}
