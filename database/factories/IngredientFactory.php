<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'category_id' => IngredientCategory::factory(),
            'source' => 'user',
            'source_id' => fake()->unique()->uuid(),
            'name_cache' => fake()->words(2, true),
            'verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'data_hash' => null,
            'foodex2_code' => null,
            'energy_kcal' => fake()->randomFloat(4, 10, 900),
            'protein_g' => fake()->randomFloat(4, 0, 40),
            'fat_g' => fake()->randomFloat(4, 0, 50),
            'carbs_g' => fake()->randomFloat(4, 0, 80),
        ];
    }

    /**
     * Mark the ingredient as private (user-owned).
     */
    public function private(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'source' => 'user',
        ]);
    }

    /**
     * Mark the ingredient as verified.
     */
    public function verified(User $verifier): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => true,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark the ingredient as submitted for review.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_status' => SubmissionStatus::Submitted->value,
        ]);
    }

    /**
     * Mark the ingredient as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_status' => SubmissionStatus::Rejected->value,
        ]);
    }
}
