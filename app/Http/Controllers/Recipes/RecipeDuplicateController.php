<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\RecipeSection;
use App\Support\Recipes\RecipeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RecipeDuplicateController extends Controller
{
    public function __construct(private readonly RecipeVersionService $versionService) {}

    /**
     * Clone the source recipe's current version snapshot into a new independent Recipe.
     *
     * The duplicate is owned by the authenticated user, named "{source name} (copy)",
     * has its own fresh RecipeDraft and its own history starting at its own v1.
     * There is NO back-reference FK to the source recipe.
     */
    public function store(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('view', $recipe);

        $recipe->load(['currentVersion', 'draft']);

        $duplicate = DB::transaction(function () use ($recipe, $request) {
            // Use the current version's snapshot as the base data for the duplicate
            $sourceSnapshot = $recipe->currentVersion?->snapshot ?? ($recipe->draft?->data ?? []);

            $duplicateName = ($recipe->name ?? 'Recipe').' (copy)';

            /** @var Recipe $duplicate */
            $duplicate = Recipe::create([
                'user_id' => auth()->id(),
                'name' => $duplicateName,
                'slug' => Str::slug($duplicateName).'-'.Str::random(6),
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

            // Create a default first section for the duplicate
            RecipeSection::create([
                'recipe_id' => $duplicate->id,
                'name' => 'Main',
                'order' => 1,
            ]);

            // Build initial draft data from the source snapshot, updating the name
            $draftData = array_merge($sourceSnapshot, [
                'name' => $duplicateName,
            ]);

            // Create the draft for the duplicate
            RecipeDraft::create([
                'recipe_id' => $duplicate->id,
                'user_id' => auth()->id(),
                'data' => $draftData,
                'edit_sequence' => 0,
            ]);

            // Load the draft relation so versionService can access it
            $duplicate->load('draft');

            // Commit v1 for the duplicate — its own independent history starting at v1
            $this->versionService->commit($duplicate, null, $request->user()->id);

            return $duplicate;
        });

        return redirect()->route('recipes.show', $duplicate);
    }
}
