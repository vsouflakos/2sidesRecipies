<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\IngredientSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientSubmission>
 */
class IngredientSubmissionFactory extends Factory
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
            'submitted_by' => User::factory(),
            'reviewed_by' => null,
            'status' => SubmissionStatus::Submitted->value,
            'notes' => null,
            'submission_number' => 1,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
