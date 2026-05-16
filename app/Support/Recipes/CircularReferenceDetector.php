<?php

namespace App\Support\Recipes;

use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;

class CircularReferenceDetector
{
    /**
     * Returns true if adding $candidateRecipeId as a sub-recipe of $parentRecipeId
     * would create a transitive cycle in the sub-recipe graph.
     *
     * Uses BFS from $candidateRecipeId, resolving each recipe's sub-recipe references
     * via RecipeIngredientLine → RecipeVersion. If $parentRecipeId is reachable,
     * a cycle would be created.
     *
     * Pitfall 2: a direct-self check is not sufficient — 3-node cycles (A→B→C→A)
     * must also be caught via the full graph traversal.
     */
    public function wouldCreateCycle(int $parentRecipeId, int $candidateRecipeId): bool
    {
        if ($parentRecipeId === $candidateRecipeId) {
            return true;
        }

        $visited = [];
        $queue = [$candidateRecipeId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $parentRecipeId) {
                return true;
            }

            // Find sub-recipe version IDs referenced by lines belonging to this recipe,
            // then resolve each to its parent recipe_id.
            $subRecipeIds = RecipeIngredientLine::query()
                ->where('recipe_id', $current)
                ->whereNotNull('sub_recipe_version_id')
                ->join('recipe_versions', 'recipe_versions.id', '=', 'recipe_ingredient_lines.sub_recipe_version_id')
                ->pluck('recipe_versions.recipe_id')
                ->all();

            foreach ($subRecipeIds as $subRecipeId) {
                if (! isset($visited[$subRecipeId])) {
                    $queue[] = $subRecipeId;
                }
            }
        }

        return false;
    }
}
