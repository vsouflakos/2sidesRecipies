<?php

namespace App\Policies;

use App\Models\RecipeTest;
use App\Models\User;

class RecipeTestPolicy
{
    /**
     * Determine whether the user can view the test.
     *
     * Ownership flows through the parent recipe — only the recipe owner may view tests.
     */
    public function view(User $user, RecipeTest $test): bool
    {
        return $test->recipe->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the test.
     *
     * Only the recipe owner may update a test.
     */
    public function update(User $user, RecipeTest $test): bool
    {
        return $test->recipe->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the test.
     *
     * Only the recipe owner may delete a test.
     */
    public function delete(User $user, RecipeTest $test): bool
    {
        return $test->recipe->user_id === $user->id;
    }
}
