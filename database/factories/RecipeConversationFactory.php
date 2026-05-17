<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeConversation>
 */
class RecipeConversationFactory extends Factory
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
        ];
    }
}
