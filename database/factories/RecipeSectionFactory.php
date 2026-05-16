<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeSection>
 */
class RecipeSectionFactory extends Factory
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
            'name' => fake()->words(2, true),
            'order' => fake()->numberBetween(0, 10),
        ];
    }
}
