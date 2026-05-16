<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

class IngredientPolicy
{
    /**
     * Determine whether the user can update the ingredient.
     *
     * Only the owner of a private ingredient may update it.
     * Official ingredients (user_id null) are never updatable via this policy.
     */
    public function update(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the ingredient.
     *
     * Only the owner of a private ingredient may delete it.
     * Official ingredients (user_id null) are never deletable via this policy.
     */
    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
    }
}
