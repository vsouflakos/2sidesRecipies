<?php

namespace App\Support\Recipes;

use App\Concerns\RecipeValidationRules;
use App\Models\Recipe;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\RecipeVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SuggestionApplier
{
    use RecipeValidationRules;

    public function __construct(
        private readonly RecipeDraftManager $draftManager,
        private readonly CircularReferenceDetector $circularReferenceDetector,
        private readonly DraftActionApplier $actionApplier,
    ) {}

    /**
     * Validate and apply an agent edit proposal to the recipe's working draft.
     *
     * Handles only proposals of kind 'edit'. Variant proposals are handled by
     * the controller's variant action.
     *
     * The proposal `data` is the action-specific delta. It is applied ON TOP of
     * the CURRENT draft by DraftActionApplier, which always produces a complete
     * draft — the draft is never replaced wholesale with the delta object
     * (the bug this fix corrects). The resulting full draft then runs through
     * the same recipeMetadataRules() validation a manual builder save passes
     * (AI-07) before any persistence occurs.
     *
     * On success: proposal_state['status'] = 'applied', edit_sequence += 1
     * (exactly one applyEdit call — one Recall step per CONTEXT.md).
     * On failure: proposal_state['status'] = 'failed' with a reason, draft untouched.
     *
     * @throws \InvalidArgumentException If the proposal is not pending, or is a variant.
     * @throws ValidationException If the action cannot be applied or the result is invalid.
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

        $action = (string) data_get($message->proposal_state, 'action', '');
        $delta = data_get($message->proposal_state, 'data', []);
        $delta = is_array($delta) ? $delta : [];

        // Ensure a draft exists — mirror RecipeDraftController::update default-draft creation
        $draft = $recipe->draft ?? RecipeDraft::create([
            'recipe_id' => $recipe->id,
            'user_id' => auth()->id(),
            'data' => [],
            'edit_sequence' => 0,
        ]);

        $currentDraft = is_array($draft->data) ? $draft->data : [];

        // CIRCULAR-REF GUARD: reject sub-recipe additions that would create a cycle
        // BEFORE building the new draft (the draft must stay untouched on rejection).
        if ($action === 'add_sub_recipe' || $action === 'attach_sub_recipe') {
            $candidateVersionId = data_get($delta, 'sub_recipe_version_id')
                ?? data_get($delta, 'candidate_recipe_id');

            if ($candidateVersionId !== null) {
                $candidateRecipeId = RecipeVersion::find((int) $candidateVersionId)?->recipe_id
                    ?? (int) $candidateVersionId;

                if ($this->circularReferenceDetector->wouldCreateCycle($recipe->id, $candidateRecipeId)) {
                    $this->failProposal($message, 'Cannot add this recipe — it would create a circular reference.');
                }
            }
        }

        // BUILD: apply the action delta on top of the CURRENT draft. The applier
        // always returns a complete draft; it throws on unknown actions,
        // unresolvable references, or missing targets — never corrupting the draft.
        try {
            $newDraft = $this->actionApplier->apply($currentDraft, $action, $delta);
        } catch (DraftActionException $e) {
            $this->failProposal($message, $e->getMessage());
        }

        // VALIDATE THE RESULT (AI-07): the new full draft must pass the same
        // metadata rules a manual builder save passes. The 'required' modifier
        // is kept on `name` — a valid draft always has a name.
        $this->validateResultingDraft($message, $newDraft);

        // APPLY: exactly ONE applyEdit call — one Recall step per CONTEXT.md.
        $this->draftManager->applyEdit($draft, $action, $newDraft);

        // Mark proposal as applied
        $proposalState = $message->proposal_state ?? [];
        $proposalState['status'] = 'applied';
        $message->proposal_state = $proposalState;
        $message->save();
    }

    /**
     * Validate the resulting full draft against the recipe metadata rules.
     *
     * @param  array<string, mixed>  $newDraft
     *
     * @throws ValidationException On failure (proposal marked failed first).
     */
    private function validateResultingDraft(RecipeConversationMessage $message, array $newDraft): void
    {
        $rules = [];
        foreach ($this->recipeMetadataRules() as $field => $fieldRules) {
            $rules['data.'.$field] = $fieldRules;
        }
        // Nested section/line shape — defensive structural rules.
        $rules['data.sections'] = ['nullable', 'array'];
        $rules['data.sections.*.lines'] = ['nullable', 'array'];
        $rules['data.sections.*.lines.*.quantity'] = ['nullable', 'numeric'];
        $rules['data.sections.*.lines.*.yield_pct'] = ['nullable', 'numeric'];

        $validator = Validator::make(['data' => $newDraft], $rules);

        if ($validator->fails()) {
            $this->failProposal($message, $validator->errors()->first());
        }
    }

    /**
     * Mark the proposal as failed and surface the failure through the existing
     * ValidationException path (the controller turns it into a 422 the UI shows).
     *
     * @throws ValidationException Always.
     */
    private function failProposal(RecipeConversationMessage $message, string $reason): never
    {
        $proposalState = $message->proposal_state ?? [];
        $proposalState['status'] = 'failed';
        $proposalState['failure_reason'] = $reason;
        $message->proposal_state = $proposalState;
        $message->save();

        throw ValidationException::withMessages([
            'proposal' => [$reason],
        ]);
    }
}
