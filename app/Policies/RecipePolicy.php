<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    /**
     * Determine whether the viewer can see the recipe.
     *
     * A published recipe is visible to anyone, including guests. A private
     * recipe is visible only to its owner.
     */
    public function view(?User $user, Recipe $recipe): bool
    {
        if ($recipe->is_published) {
            return true;
        }

        return $user !== null && $recipe->user_id === $user->id;
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
     * Deletion is refused while a recipe is published — the owner must
     * unpublish it first.
     */
    public function delete(User $user, Recipe $recipe): bool
    {
        if ($recipe->is_published) {
            return false;
        }

        return $recipe->user_id === $user->id;
    }
}
