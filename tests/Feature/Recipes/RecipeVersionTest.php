<?php

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers VERSION-01, VERSION-05, RECIPE-08.
 *
 * RED until Plan 03-04 ships the version controller.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('versions are append-only and never updated', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'v1 snapshot'],
    ]);

    $originalUpdatedAt = $version->updated_at;

    // A second version is a new row, not an update to the existing one
    RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 2,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'v2 snapshot'],
    ]);

    $version->refresh();

    expect($version->version_number)->toBe(1);
    expect($version->snapshot['name'])->toBe('v1 snapshot');
    expect(RecipeVersion::where('recipe_id', $recipe->id)->count())->toBe(2);
});

test('two versions return different snapshot data', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $v1 = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['portions' => 8, 'name' => 'Original'],
    ]);

    $v2 = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 2,
        'committed_by' => $user->id,
        'snapshot' => ['portions' => 12, 'name' => 'Scaled up'],
    ]);

    expect($v1->snapshot['portions'])->toBe(8);
    expect($v2->snapshot['portions'])->toBe(12);
    expect($v1->snapshot['name'])->not->toBe($v2->snapshot['name']);
});

test('changing portion count in a view request does not create a new version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['portions' => 8],
    ]);

    $recipe->update(['current_version_id' => $version->id]);

    // GET request with a portions scale parameter — view only, no version bump
    $response = $this->actingAs($user)->get("/recipes/{$recipe->id}?portions=16");

    $response->assertSuccessful();

    $versionCount = RecipeVersion::where('recipe_id', $recipe->id)->count();
    expect($versionCount)->toBe(1);
});

test('GET /recipes/{id}/versions/{version} returns a specific version snapshot', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $v1 = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['portions' => 4, 'name' => 'Small batch'],
    ]);

    $recipe->update(['current_version_id' => $v1->id]);

    $response = $this->actingAs($user)->get("/recipes/{$recipe->id}/versions/{$v1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('version.version_number', 1)
    );
});
