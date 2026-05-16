<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeDraft>
 */
class RecipeDraftFactory extends Factory
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
            'user_id' => User::factory(),
            'data' => [
                'name' => fake()->words(3, true),
                'yield_amount' => 1000,
                'portions' => 8,
                'ingredient_lines' => [],
                'steps' => [],
                'selling_price' => null,
            ],
            'edit_sequence' => 0,
        ];
    }
}
