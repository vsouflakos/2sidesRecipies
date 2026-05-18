<?php

namespace App\Http\Controllers\Ingredients;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ingredients\SubmitIngredientRequest;
use App\Models\Ingredient;
use App\Models\IngredientSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class IngredientSubmissionController extends Controller
{
    /**
     * Check for official ingredients with similar names (advisory duplicate warning).
     *
     * Returns up to 5 official ingredients whose name matches the given ingredient's
     * name_cache. This is a fetch-only advisory check and legitimately returns JSON.
     */
    public function duplicateCheck(Ingredient $ingredient): JsonResponse
    {
        Gate::authorize('submit', $ingredient);

        $search = $ingredient->name_cache;

        $matches = Ingredient::query()
            ->with('translations')
            ->whereNull('user_id')
            ->where('id', '!=', $ingredient->id)
            ->whereHas('translations', fn ($t) => $t->when(
                DB::getDriverName() !== 'sqlite',
                fn ($w) => $w->whereFullText('name', $search),
                fn ($w) => $w->where('name', 'like', "%{$search}%"),
            ))
            ->limit(5)
            ->get();

        return response()->json([
            'matches' => $matches->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->nameFor(app()->getLocale()),
            ]),
        ]);
    }

    /**
     * Submit a private ingredient for moderator inclusion review.
     *
     * Creates a numbered IngredientSubmission history row and freezes the ingredient
     * by setting its submission_status to Submitted.
     */
    public function store(SubmitIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        DB::transaction(function () use ($ingredient) {
            $submissionNumber = IngredientSubmission::where('ingredient_id', $ingredient->id)->count() + 1;

            IngredientSubmission::create([
                'ingredient_id' => $ingredient->id,
                'submitted_by' => auth()->id(),
                'status' => SubmissionStatus::Submitted->value,
                'submission_number' => $submissionNumber,
                'submitted_at' => now(),
            ]);

            $ingredient->update(['submission_status' => SubmissionStatus::Submitted]);
        });

        return redirect()->route('ingredients.show', $ingredient)
            ->with('success', __('app.ingredients.submit_toast'));
    }

    /**
     * Withdraw a pending ingredient submission.
     *
     * Updates the open Submitted row to Withdrawn and reverts the ingredient's
     * submission_status back to Private so the owner can edit or resubmit.
     */
    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        Gate::authorize('withdraw', $ingredient);

        DB::transaction(function () use ($ingredient) {
            IngredientSubmission::where('ingredient_id', $ingredient->id)
                ->where('status', SubmissionStatus::Submitted->value)
                ->latest()
                ->first()
                ?->update([
                    'status' => SubmissionStatus::Withdrawn->value,
                    'reviewed_at' => now(),
                ]);

            $ingredient->update(['submission_status' => SubmissionStatus::Private]);
        });

        return redirect()->route('ingredients.show', $ingredient)
            ->with('success', __('app.ingredients.withdraw_toast'));
    }
}
