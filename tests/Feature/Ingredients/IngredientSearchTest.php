<?php

use App\Models\Ingredient;
use App\Models\User;

/**
 * Covers INGR-01 — live ingredient search.
 *
 * RED until Plan 02-03 (search + browse UI) ships the IngredientController
 * and the ingredients/index Inertia page.
 */
test('the ingredient index page renders for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ingredients')
        ->assertOk();
});

test('searching by english name returns the matching ingredient', function () {
    $user = User::factory()->create();

    $apple = Ingredient::factory()->create(['name_cache' => 'Apple']);
    $apple->translations()->create(['locale' => 'en', 'name' => 'Apple']);

    $banana = Ingredient::factory()->create(['name_cache' => 'Banana']);
    $banana->translations()->create(['locale' => 'en', 'name' => 'Banana']);

    $this->actingAs($user)
        ->get('/ingredients?search=Apple')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->where('ingredients.data.0.name_cache', 'Apple')
        );
});

test('searching by greek name returns the matching ingredient', function () {
    $user = User::factory()->create();

    $apple = Ingredient::factory()->create(['name_cache' => 'Apple']);
    $apple->translations()->create(['locale' => 'en', 'name' => 'Apple']);
    $apple->translations()->create(['locale' => 'el', 'name' => 'Μήλο']);

    $this->actingAs($user)
        ->get('/ingredients?search=Μήλο')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 1)
        );
});

test('an empty search returns the paginated full list', function () {
    $user = User::factory()->create();

    Ingredient::factory()->count(3)->create();

    $this->actingAs($user)
        ->get('/ingredients')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 3)
        );
});
