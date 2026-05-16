<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\IngredientPrice;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientPrice>
 */
class IngredientPriceFactory extends Factory
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
            'user_id' => User::factory(),
            'ingredient_id' => Ingredient::factory(),
            'amount' => fake()->randomFloat(4, 0.5, 50),
            'currency' => 'EUR',
            'quantity' => fake()->randomFloat(4, 100, 1000),
            'unit_id' => $unit->id,
            'per_gram_cost' => null,
            'recorded_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
