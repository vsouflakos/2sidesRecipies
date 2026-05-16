<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredientLine>
 */
class RecipeIngredientLineFactory extends Factory
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
            'section_id' => null,
            'ingredient_id' => Ingredient::factory(),
            'sub_recipe_version_id' => null,
            'quantity' => fake()->randomFloat(4, 1, 500),
            'unit_id' => null,
            'quantity_g' => fake()->randomFloat(4, 1, 500),
            'prep_note' => null,
            'yield_pct' => null,
            'is_flour_base' => false,
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}
