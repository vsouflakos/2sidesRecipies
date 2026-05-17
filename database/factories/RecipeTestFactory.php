<?php

namespace Database\Factories;

use App\Enums\TestType;
use App\Enums\TestVerdict;
use App\Models\Recipe;
use App\Models\RecipeTest;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeTest>
 */
class RecipeTestFactory extends Factory
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
            'recipe_version_id' => RecipeVersion::factory(),
            'user_id' => User::factory(),
            'type' => TestType::Trial,
            'tested_at' => now(),
            'tasting_notes' => $this->faker->sentence(),
            'overall_rating' => $this->faker->numberBetween(1, 10),
            'ratings' => [['dimension' => 'Taste', 'score' => 7, 'is_custom' => false]],
            'hypothesis' => null,
            'outcome_narrative' => null,
            'verdict' => null,
            'change_rows' => null,
        ];
    }

    /**
     * Indicate the test is an experiment.
     */
    public function experiment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TestType::Experiment,
            'hypothesis' => $this->faker->sentence(),
            'outcome_narrative' => $this->faker->sentence(),
            'verdict' => TestVerdict::Worked,
            'change_rows' => [['what_changed' => 'More salt', 'expected_effect' => 'Saltier', 'actual_effect' => 'Saltier']],
        ]);
    }
}
