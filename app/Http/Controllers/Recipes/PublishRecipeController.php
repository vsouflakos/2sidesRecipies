<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\PublishRecipeRequest;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublishRecipeController extends Controller
{
    /**
     * Publish a recipe by locking a specific version as the public snapshot.
     *
     * Authorization is handled by PublishRecipeRequest::authorize().
     * Sets is_published, published_version_id, and published_at on the recipe.
     */
    public function store(PublishRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $recipe->update([
            'is_published' => true,
            'published_version_id' => $request->validated()['version_id'],
            'published_at' => now(),
        ]);

        return redirect()->route('recipes.show', $recipe);
    }

    /**
     * Unpublish a recipe by clearing all publish columns.
     *
     * Uses Gate::authorize directly (established pattern — base Controller has no
     * AuthorizesRequests trait). Clears is_published, published_version_id, and
     * published_at so the recipe returns to private draft state.
     */
    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('update', $recipe);

        $recipe->update([
            'is_published' => false,
            'published_version_id' => null,
            'published_at' => null,
        ]);

        return redirect()->route('recipes.show', $recipe);
    }
}
