<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeVersion>
 */
class RecipeVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'version_number' => 1,
            'committed_by' => User::factory(),
            'committed_at' => now(),
            'change_note' => null,
            'snapshot' => [],
            'yield_g' => 1000,
            'cached_nutrition_json' => null,
            'cached_cost_per_gram' => null,
            'cached_cost_per_portion' => null,
            'cached_allergen_slugs' => null,
            'cached_selling_price' => null,
        ];
    }
}
