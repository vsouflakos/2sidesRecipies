<?php

use App\Models\Ingredient;
use App\Models\User;

/**
 * Covers INGR-03 — dedicated ingredient detail page.
 *
 * RED until Plan 02-05 ships the IngredientController@show action and the
 * ingredients/show Inertia page.
 */
test('the ingredient detail page renders with nutrition props', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create([
        'energy_kcal' => 52,
        'protein_g' => 0.3,
    ]);
    $ingredient->translations()->create(['locale' => 'en', 'name' => 'Apple']);

    $this->actingAs($user)
        ->get("/ingredients/{$ingredient->id}")
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/show')
            ->where('ingredient.energy_kcal', '52.0000')
        );
});

test('the ingredient detail page exposes allergen, conversion and category props', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $ingredient->translations()->create(['locale' => 'en', 'name' => 'Apple']);

    $this->actingAs($user)
        ->get("/ingredients/{$ingredient->id}")
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/show')
            ->has('ingredient.allergens')
            ->has('ingredient.conversions')
            ->has('ingredient.category')
        );
});
