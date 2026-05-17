<?php

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PUB-01, PUB-02, PUB-03.
 *
 * RED until Plan 06-02 ships PublishRecipeController and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// PUB-01: A freshly created recipe is private by default
test('a freshly created recipe has is_published = false', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    expect($recipe->is_published)->toBeFalse();
    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'is_published' => false,
    ]);
});

// PUB-01: A guest cannot access a private recipe via the owner route
test('a guest GET to the owner recipe show route for a private recipe is refused', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'is_published' => false]);

    $response = $this->get("/recipes/{$recipe->id}");

    // Guest is either redirected to login (302) or forbidden (403) — never 200
    expect($response->status())->not->toBe(200);
});

// PUB-02: Owner can publish a recipe by POSTing to recipes.publish with a version_id
test('owner can publish a recipe and publish columns are set', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['sections' => []],
    ]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($user)->post(route('recipes.publish', $recipe), [
        'version_id' => $version->id,
    ]);

    $response->assertSuccessful()->orStatus(302);

    $recipe->refresh();
    expect($recipe->is_published)->toBeTrue();
    expect((int) $recipe->published_version_id)->toBe($version->id);
    expect($recipe->published_at)->not->toBeNull();
});

// PUB-02: Publish is rejected when a sub-recipe in the version snapshot is not published
test('publish is rejected when a sub-recipe referenced in the version snapshot is not published', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    // Create an unpublished sub-recipe
    $subRecipe = Recipe::factory()->create(['user_id' => $user->id, 'is_published' => false]);
    $subVersion = RecipeVersion::factory()->create([
        'recipe_id' => $subRecipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);

    // The parent recipe's version snapshot references the unpublished sub-recipe version
    $parentRecipe = Recipe::factory()->create(['user_id' => $user->id]);
    $parentVersion = RecipeVersion::factory()->create([
        'recipe_id' => $parentRecipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [
            'sections' => [
                [
                    'lines' => [
                        [
                            'ingredient_id' => null,
                            'sub_recipe_version_id' => $subVersion->id,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->post(route('recipes.publish', $parentRecipe), [
        'version_id' => $parentVersion->id,
    ]);

    // Should fail validation — sub-recipe is not published
    $response->assertStatus(422);
});

// PUB-02: Committing a new version after publishing does NOT change published_version_id
test('committing a new version after publish does not change published_version_id', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $v1 = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['sections' => []],
    ]);
    $recipe->update([
        'current_version_id' => $v1->id,
        'is_published' => true,
        'published_version_id' => $v1->id,
        'published_at' => now(),
    ]);

    // Simulate committing a new version
    $v2 = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 2,
        'committed_by' => $user->id,
        'snapshot' => ['sections' => [['name' => 'New section']]],
    ]);
    $recipe->update(['current_version_id' => $v2->id]);

    $recipe->refresh();
    // published_version_id must still point to v1
    expect((int) $recipe->published_version_id)->toBe($v1->id);
});

// PUB-03: Owner can unpublish a recipe via DELETE on recipes.unpublish
test('owner can unpublish a recipe and publish columns are cleared', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['sections' => []],
    ]);
    $recipe->update([
        'current_version_id' => $version->id,
        'is_published' => true,
        'published_version_id' => $version->id,
        'published_at' => now(),
    ]);

    $response = $this->actingAs($user)->delete(route('recipes.unpublish', $recipe));

    $response->assertSuccessful()->orStatus(302);

    $recipe->refresh();
    expect($recipe->is_published)->toBeFalse();
    expect($recipe->published_version_id)->toBeNull();
    expect($recipe->published_at)->toBeNull();
});

// PUB-03: DELETE on recipes.destroy while published is refused (403) and recipe is not soft-deleted
test('deleting a published recipe is refused with 403 and the recipe is not soft-deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['sections' => []],
    ]);
    $recipe->update([
        'current_version_id' => $version->id,
        'is_published' => true,
        'published_version_id' => $version->id,
        'published_at' => now(),
    ]);

    $response = $this->actingAs($user)->delete(route('recipes.destroy', $recipe));

    $response->assertForbidden();

    // Recipe must NOT be soft-deleted
    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'deleted_at' => null,
    ]);
});
