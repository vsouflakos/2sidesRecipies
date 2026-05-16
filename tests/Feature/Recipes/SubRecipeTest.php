<?php

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers RECIPE-05, VERSION-06.
 *
 * RED until Plan 03-04 ships the sub-recipe attachment logic.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a sub-recipe line attaches to a specific recipe_version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $component = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Tomato Sauce']);
    $componentV1 = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'Tomato Sauce', 'yield_g' => 500],
        'yield_g' => 500,
    ]);
    $component->update(['current_version_id' => $componentV1->id]);

    $parent = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Pasta']);

    $line = RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentV1->id,
        'quantity' => '250.000000',
        'unit_id' => null,
    ]);

    expect($line->isSubRecipe())->toBeTrue();
    expect($line->sub_recipe_version_id)->toBe($componentV1->id);
    expect($line->subRecipeVersion->version_number)->toBe(1);
});

test('the version pin stays on the pinned version after the component gets a newer version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $component = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Béchamel']);
    $componentV1 = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'Béchamel v1'],
        'yield_g' => 400,
    ]);
    $component->update(['current_version_id' => $componentV1->id]);

    $parent = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Moussaka']);

    $line = RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentV1->id,
        'quantity' => '200.000000',
    ]);

    // Component gets a newer version
    $componentV2 = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 2,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'Béchamel v2 — richer'],
        'yield_g' => 450,
    ]);
    $component->update(['current_version_id' => $componentV2->id]);

    // The parent's line still pins to v1
    $line->refresh();
    expect($line->sub_recipe_version_id)->toBe($componentV1->id);
    expect($line->subRecipeVersion->version_number)->toBe(1);
});

test('a sub-recipe line has no ingredient_id and ingredient() returns null', function () {
    $user = User::factory()->create();
    $component = Recipe::factory()->create(['user_id' => $user->id]);
    $componentVersion = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);

    $parent = Recipe::factory()->create(['user_id' => $user->id]);

    $line = RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentVersion->id,
    ]);

    expect($line->ingredient_id)->toBeNull();
    expect($line->ingredient)->toBeNull();
    expect($line->isSubRecipe())->toBeTrue();
});
