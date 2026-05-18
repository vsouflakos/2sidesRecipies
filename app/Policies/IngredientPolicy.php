<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\User;

class IngredientPolicy
{
    /**
     * Determine whether the user can update the ingredient.
     *
     * Only the owner of a private ingredient may update it.
     * Official ingredients (user_id null) are never updatable via this policy.
     * An ingredient frozen while pending review cannot be updated by anyone.
     */
    public function update(User $user, Ingredient $ingredient): bool
    {
        if ($ingredient->isPendingReview()) {
            return false;
        }

        return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the ingredient.
     *
     * Only the owner of a private ingredient may delete it.
     * Official ingredients (user_id null) are never deletable via this policy.
     * An ingredient frozen while pending review cannot be deleted by anyone.
     */
    public function delete(User $user, Ingredient $ingredient): bool
    {
        if ($ingredient->isPendingReview()) {
            return false;
        }

        return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
    }

    /**
     * Determine whether the user can submit the ingredient for inclusion.
     *
     * Only the owner of a private ingredient in private or rejected state
     * may submit or resubmit it.
     */
    public function submit(User $user, Ingredient $ingredient): bool
    {
        if ($ingredient->user_id === null || $ingredient->user_id !== $user->id) {
            return false;
        }

        return in_array(
            $ingredient->submission_status,
            [SubmissionStatus::Private, SubmissionStatus::Rejected],
            true,
        );
    }

    /**
     * Determine whether the user can withdraw the ingredient from review.
     *
     * Only the owner may withdraw, and only while the ingredient is pending review.
     */
    public function withdraw(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->user_id !== null
            && $ingredient->user_id === $user->id
            && $ingredient->isPendingReview();
    }
}
