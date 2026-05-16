<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\IngredientTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientTranslation>
 */
class IngredientTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'locale' => 'en',
            'name' => fake()->words(2, true),
            'description' => null,
        ];
    }
}
