<?php

use App\Models\Ingredient;
use App\Models\User;

/**
 * Covers INGR-01 — live ingredient search.
 */
test('the ingredient index page renders for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ingredients')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ingredients/index'));
});

test('searching by english name returns the matching ingredient', function () {
    $user = User::factory()->create();

    $olive = Ingredient::factory()->create(['name_cache' => 'Olive oil']);
    $olive->translations()->create(['locale' => 'en', 'name' => 'Olive oil']);
    $olive->translations()->create(['locale' => 'el', 'name' => 'Ελαιόλαδο']);

    $butter = Ingredient::factory()->create(['name_cache' => 'Butter']);
    $butter->translations()->create(['locale' => 'en', 'name' => 'Butter']);

    $this->actingAs($user)
        ->get('/ingredients?search=Olive')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 1)
        );
});

test('searching by greek name returns the matching ingredient', function () {
    $user = User::factory()->create();

    $olive = Ingredient::factory()->create(['name_cache' => 'Olive oil']);
    $olive->translations()->create(['locale' => 'en', 'name' => 'Olive oil']);
    $olive->translations()->create(['locale' => 'el', 'name' => 'Ελαιόλαδο']);

    $this->actingAs($user)
        ->get('/ingredients?search=Ελαιόλαδο')
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

test('source official filter excludes private ingredients', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $official = Ingredient::factory()->create(['user_id' => null, 'name_cache' => 'Official']);
    $official->translations()->create(['locale' => 'en', 'name' => 'Official']);

    $private = Ingredient::factory()->create(['user_id' => $otherUser->id, 'name_cache' => 'Private']);
    $private->translations()->create(['locale' => 'en', 'name' => 'Private']);

    $this->actingAs($user)
        ->get('/ingredients?source=official')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 1)
        );
});

test('private ingredients owned by another user never appear in results', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $official = Ingredient::factory()->create(['user_id' => null, 'name_cache' => 'Official']);
    $ownPrivate = Ingredient::factory()->create(['user_id' => $user->id, 'name_cache' => 'My Private']);
    $otherPrivate = Ingredient::factory()->create(['user_id' => $otherUser->id, 'name_cache' => 'Other Private']);

    $this->actingAs($user)
        ->get('/ingredients')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 2)
        );
});

test('verified only filter returns only verified ingredients', function () {
    $user = User::factory()->create();

    $verified = Ingredient::factory()->create(['verified' => true, 'name_cache' => 'Verified']);
    $unverified = Ingredient::factory()->create(['verified' => false, 'name_cache' => 'Unverified']);

    $this->actingAs($user)
        ->get('/ingredients?verified_only=1')
        ->assertInertia(fn ($page) => $page
            ->component('ingredients/index')
            ->has('ingredients.data', 1)
        );
});
