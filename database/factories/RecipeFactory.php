<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'hero_image_path' => null,
            'yield_amount' => 1000,
            'yield_unit_id' => null,
            'portions' => 8,
            'portion_size_g' => null,
            'prep_time_minutes' => null,
            'cook_time_minutes' => null,
            'difficulty' => Difficulty::Medium,
            'cuisine_id' => null,
            'notes' => null,
            'current_version_id' => null,
            'selling_price' => null,
            'is_published' => false,
            'published_version_id' => null,
            'published_at' => null,
        ];
    }
}
