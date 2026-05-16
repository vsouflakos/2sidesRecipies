<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    /**
     * Determine whether the user can view the recipe.
     *
     * Recipes are private — only the owner may view in Phase 3.
     */
    public function view(User $user, Recipe $recipe): bool
    {
        return $recipe->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the recipe or its draft.
     *
     * Only the owner may update a recipe.
     */
    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the recipe.
     *
     * Only the owner may delete a recipe.
     */
    public function delete(User $user, Recipe $recipe): bool
    {
        return $recipe->user_id === $user->id;
    }
}
