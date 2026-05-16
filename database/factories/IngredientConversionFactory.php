<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\IngredientConversion;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientConversion>
 */
class IngredientConversionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Unit::firstOrCreate(
            ['name' => 'gram'],
            ['symbol' => 'g', 'type' => 'weight', 'base_factor' => 1.0]
        );

        return [
            'ingredient_id' => Ingredient::factory(),
            'from_amount' => 1,
            'from_unit_id' => $unit->id,
            'gram_weight' => fake()->randomFloat(4, 1, 500),
            'modifier' => null,
            'source' => 'curated',
            'source_ref' => null,
        ];
    }
}
