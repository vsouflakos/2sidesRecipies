<?php

namespace App\Support\Recipes;

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;

class AllergenRollupService
{
    /**
     * Merge allergen states from multiple sources.
     * 'contains' always wins over 'may_contain'.
     *
     * @param  array<array{slug: string, state: string}>  ...$allergenSets
     * @return array<string, string> slug => state
     */
    public function merge(array ...$allergenSets): array
    {
        $merged = [];

        foreach ($allergenSets as $set) {
            foreach ($set as $allergen) {
                $slug = $allergen['slug'];
                $state = $allergen['state'];

                if (! isset($merged[$slug]) || $state === 'contains') {
                    $merged[$slug] = $state;
                }
            }
        }

        return $merged;
    }

    /**
     * Roll up allergens for each ingredient line in the given iterable.
     * Ingredient lines contribute their ingredient's allergens (with pivot state).
     * Sub-recipe lines contribute cached_allergen_slugs from the pinned RecipeVersion.
     *
     * @param  iterable<RecipeIngredientLine>  $lines
     * @return array<string, string> slug => state
     */
    public function forRecipeLines(iterable $lines): array
    {
        $allSets = [];

        foreach ($lines as $line) {
            if ($line->isSubRecipe()) {
                $version = $line->subRecipeVersion;

                if ($version === null) {
                    continue;
                }

                $cachedSlugs = $version->cached_allergen_slugs ?? [];
                $set = [];

                foreach ($cachedSlugs['contains'] ?? [] as $slug) {
                    $set[] = ['slug' => $slug, 'state' => 'contains'];
                }

                foreach ($cachedSlugs['may_contain'] ?? [] as $slug) {
                    $set[] = ['slug' => $slug, 'state' => 'may_contain'];
                }

                $allSets[] = $set;
            } else {
                $ingredient = $line->ingredient;

                if ($ingredient === null) {
                    continue;
                }

                $set = [];

                foreach ($ingredient->allergens as $allergen) {
                    $set[] = [
                        'slug' => $allergen->slug,
                        'state' => $allergen->pivot->state,
                    ];
                }

                $allSets[] = $set;
            }
        }

        return $this->merge(...$allSets);
    }

    /**
     * Compute the allergen roll-up for an entire recipe's ingredient lines.
     * Returns ['contains' => [{slug},...], 'may_contain' => [{slug},...]] for test compatibility.
     *
     * @return array{contains: array<array{slug: string}>, may_contain: array<array{slug: string}>}
     */
    public function compute(Recipe $recipe): array
    {
        $lines = $recipe->ingredientLines()->with(['ingredient.allergens', 'subRecipeVersion'])->get();

        $merged = $this->forRecipeLines($lines);

        $contains = [];
        $mayContain = [];

        foreach ($merged as $slug => $state) {
            if ($state === 'contains') {
                $contains[] = ['slug' => $slug];
            } else {
                $mayContain[] = ['slug' => $slug];
            }
        }

        return [
            'contains' => $contains,
            'may_contain' => $mayContain,
        ];
    }
}
