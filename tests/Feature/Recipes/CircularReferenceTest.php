<?php

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers RECIPE-06.
 *
 * RED until Plan 03-03 ships the circular reference detection service.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('adding recipe B as a sub-recipe of A then A as a sub-recipe of B is rejected with 422', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipeA = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recipe A']);
    $recipeAVersion = RecipeVersion::factory()->create([
        'recipe_id' => $recipeA->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);
    $recipeA->update(['current_version_id' => $recipeAVersion->id]);

    $recipeB = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recipe B']);
    $recipeBVersion = RecipeVersion::factory()->create([
        'recipe_id' => $recipeB->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);
    $recipeB->update(['current_version_id' => $recipeBVersion->id]);

    // A uses B as a sub-recipe — this should succeed
    $responseAB = $this->actingAs($user)->put("/recipes/{$recipeA->id}/draft", [
        'action' => 'add_sub_recipe',
        'sub_recipe_version_id' => $recipeBVersion->id,
        'quantity' => 200,
    ]);
    $responseAB->assertSuccessful();

    // B tries to use A as a sub-recipe — this creates a cycle and must be rejected
    $responseBA = $this->actingAs($user)->put("/recipes/{$recipeB->id}/draft", [
        'action' => 'add_sub_recipe',
        'sub_recipe_version_id' => $recipeAVersion->id,
        'quantity' => 200,
    ]);
    $responseBA->assertStatus(422);
    $responseBA->assertJsonValidationErrors(['sub_recipe_version_id']);
});

test('a three-node cycle A->B->C->A is rejected with 422', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipeA = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recipe A']);
    $recipeAVersion = RecipeVersion::factory()->create([
        'recipe_id' => $recipeA->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);
    $recipeA->update(['current_version_id' => $recipeAVersion->id]);

    $recipeB = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recipe B']);
    $recipeBVersion = RecipeVersion::factory()->create([
        'recipe_id' => $recipeB->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);
    $recipeB->update(['current_version_id' => $recipeBVersion->id]);

    $recipeC = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Recipe C']);
    $recipeCVersion = RecipeVersion::factory()->create([
        'recipe_id' => $recipeC->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);
    $recipeC->update(['current_version_id' => $recipeCVersion->id]);

    // A -> B
    $this->actingAs($user)->put("/recipes/{$recipeA->id}/draft", [
        'action' => 'add_sub_recipe',
        'sub_recipe_version_id' => $recipeBVersion->id,
        'quantity' => 200,
    ])->assertSuccessful();

    // B -> C
    $this->actingAs($user)->put("/recipes/{$recipeB->id}/draft", [
        'action' => 'add_sub_recipe',
        'sub_recipe_version_id' => $recipeCVersion->id,
        'quantity' => 200,
    ])->assertSuccessful();

    // C -> A — closes the 3-node cycle, must be rejected
    $response = $this->actingAs($user)->put("/recipes/{$recipeC->id}/draft", [
        'action' => 'add_sub_recipe',
        'sub_recipe_version_id' => $recipeAVersion->id,
        'quantity' => 200,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['sub_recipe_version_id']);
});
