<?php

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\User;
use Database\Seeders\IngredientCategorySeeder;

/**
 * Covers INGR-07 — private ingredient CRUD.
 *
 * RED until Plan 02-04 ships the PrivateIngredientController and routes.
 */
beforeEach(function () {
    $this->seed(IngredientCategorySeeder::class);
});

test('a user can create a private ingredient with a name and category', function () {
    $user = User::factory()->create();
    $category = IngredientCategory::whereNotNull('parent_id')->first();

    $this->actingAs($user)
        ->post('/ingredients', [
            'name' => 'My Secret Sauce',
            'category_id' => $category->id,
        ])
        ->assertRedirect();

    $ingredient = Ingredient::where('name_cache', 'My Secret Sauce')->first();

    expect($ingredient)->not->toBeNull();
    expect($ingredient->user_id)->toBe($user->id);
});

test('a private ingredient is not visible to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = IngredientCategory::whereNotNull('parent_id')->first();

    $private = Ingredient::factory()->private($owner)->create(['category_id' => $category->id]);

    $this->actingAs($other)
        ->get('/ingredients?source=private')
        ->assertInertia(fn ($page) => $page
            ->where('ingredients.data', fn ($data) => collect($data)->doesntContain('id', $private->id))
        );
});

test('the owner can update and delete their private ingredient', function () {
    $owner = User::factory()->create();
    $category = IngredientCategory::whereNotNull('parent_id')->first();
    $private = Ingredient::factory()->private($owner)->create(['category_id' => $category->id]);

    $this->actingAs($owner)
        ->put("/ingredients/{$private->id}", [
            'name' => 'Updated Name',
            'category_id' => $category->id,
        ])
        ->assertRedirect();

    expect($private->fresh()->name_cache)->toBe('Updated Name');

    $this->actingAs($owner)
        ->delete("/ingredients/{$private->id}")
        ->assertRedirect();

    expect(Ingredient::find($private->id))->toBeNull();
});

test('a non-owner gets 403 when updating or deleting a private ingredient', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = IngredientCategory::whereNotNull('parent_id')->first();
    $private = Ingredient::factory()->private($owner)->create(['category_id' => $category->id]);

    $this->actingAs($other)
        ->put("/ingredients/{$private->id}", [
            'name' => 'Hijacked',
            'category_id' => $category->id,
        ])
        ->assertForbidden();

    $this->actingAs($other)
        ->delete("/ingredients/{$private->id}")
        ->assertForbidden();
});
