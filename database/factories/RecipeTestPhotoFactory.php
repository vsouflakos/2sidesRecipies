<?php

namespace Database\Factories;

use App\Models\RecipeTest;
use App\Models\RecipeTestPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeTestPhoto>
 */
class RecipeTestPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_test_id' => RecipeTest::factory(),
            'path' => 'recipe-tests/1/'.$this->faker->uuid().'.jpg',
            'order' => 0,
        ];
    }
}
