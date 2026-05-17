<?php

use App\Models\Recipe;
use App\Models\RecipeTest;
use App\Models\RecipeTestPhoto;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Covers TEST-01, TEST-02, TEST-03, TEST-04. RED until plan 04-02 ships the routes + controller.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('POST /recipes/{recipe}/tests stores a trial test and redirects', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'trial',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 8,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recipe_tests', [
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $version->id,
        'type' => 'trial',
    ]);
});

test('GET /recipes/{recipe}/tests renders the index page for the owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->get("/recipes/{$recipe->id}/tests");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('recipes/tests/index'));
});

test('POST /recipes/{recipe}/tests with type=experiment stores hypothesis, outcome_narrative, and verdict', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'experiment',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 7,
        'hypothesis' => 'Adding more salt will improve taste.',
        'outcome_narrative' => 'The dish was indeed saltier.',
        'verdict' => 'worked',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recipe_tests', [
        'recipe_id' => $recipe->id,
        'type' => 'experiment',
        'hypothesis' => 'Adding more salt will improve taste.',
        'outcome_narrative' => 'The dish was indeed saltier.',
        'verdict' => 'worked',
    ]);
});

test('POST /recipes/{recipe}/tests with type=experiment and no hypothesis returns a 422 validation error', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'experiment',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 7,
    ]);

    $response->assertSessionHasErrors('hypothesis');
});

test('POST /recipes/{recipe}/tests stores tasting_notes and overall_rating', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'trial',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 9,
        'tasting_notes' => 'Rich and balanced flavour.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recipe_tests', [
        'recipe_id' => $recipe->id,
        'tasting_notes' => 'Rich and balanced flavour.',
        'overall_rating' => 9,
    ]);
});

test('POST /recipes/{recipe}/tests stores a ratings array with per-dimension scores', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'trial',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 8,
        'ratings' => [
            ['dimension' => 'Taste', 'score' => 8, 'is_custom' => false],
            ['dimension' => 'Texture', 'score' => 7, 'is_custom' => false],
        ],
    ]);

    $response->assertRedirect();

    $test = RecipeTest::where('recipe_id', $recipe->id)->first();
    expect($test)->not->toBeNull();
    expect($test->ratings)->toBeArray();
    expect($test->ratings[0])->toHaveKey('dimension');
    expect($test->ratings[0])->toHaveKey('score');
    expect($test->ratings[0])->toHaveKey('is_custom');
});

test('POST /recipes/{recipe}/tests stores photos and creates recipe_test_photos rows', function () {
    Storage::fake(config('filesystems.default', 'public'));

    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $photo = UploadedFile::fake()->image('shot.jpg', 800, 600);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'trial',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 8,
        'photos' => [$photo],
    ]);

    $response->assertRedirect();

    $test = RecipeTest::where('recipe_id', $recipe->id)->first();
    expect($test)->not->toBeNull();

    $photoRow = RecipeTestPhoto::where('recipe_test_id', $test->id)->first();
    expect($photoRow)->not->toBeNull();
    expect($photoRow->path)->not->toBeEmpty();
});

test('POST /recipes/{recipe}/tests with type=experiment stores change_rows array', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $changeRows = [
        ['what_changed' => 'More salt', 'expected_effect' => 'Saltier', 'actual_effect' => 'Much saltier'],
    ];

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'experiment',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 7,
        'hypothesis' => 'Salt experiment.',
        'outcome_narrative' => 'It worked.',
        'verdict' => 'worked',
        'change_rows' => $changeRows,
    ]);

    $response->assertRedirect();

    $test = RecipeTest::where('recipe_id', $recipe->id)->first();
    expect($test)->not->toBeNull();
    expect($test->change_rows)->toBeArray();
});

test('stored change_rows have the keys what_changed, expected_effect, actual_effect', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($owner)->post("/recipes/{$recipe->id}/tests", [
        'type' => 'experiment',
        'recipe_version_id' => $version->id,
        'tested_at' => now()->toDateTimeString(),
        'overall_rating' => 7,
        'hypothesis' => 'Salt experiment.',
        'outcome_narrative' => 'It worked.',
        'verdict' => 'worked',
        'change_rows' => [
            ['what_changed' => 'More salt', 'expected_effect' => 'Saltier', 'actual_effect' => 'Much saltier'],
        ],
    ]);

    $response->assertRedirect();

    $test = RecipeTest::where('recipe_id', $recipe->id)->first();
    expect($test)->not->toBeNull();
    expect($test->change_rows[0])->toHaveKey('what_changed');
    expect($test->change_rows[0])->toHaveKey('expected_effect');
    expect($test->change_rows[0])->toHaveKey('actual_effect');
});

test('non-owner cannot GET /recipes/{recipe}/tests for another users recipe', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $nonOwner = User::factory()->create();
    $nonOwner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $response = $this->actingAs($nonOwner)->get("/recipes/{$recipe->id}/tests");

    $response->assertStatus(403);
});

test('non-owner cannot DELETE another users test', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $nonOwner = User::factory()->create();
    $nonOwner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $test = RecipeTest::factory()->create([
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $version->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($nonOwner)->delete("/recipes/{$recipe->id}/tests/{$test->id}");

    $response->assertStatus(403);
});

test('DELETE /recipes/{recipe}/tests/{test} removes the test and cascade-deletes its photos', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $test = RecipeTest::factory()->create([
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $version->id,
        'user_id' => $owner->id,
    ]);

    $photo = RecipeTestPhoto::factory()->create(['recipe_test_id' => $test->id]);

    $response = $this->actingAs($owner)->delete("/recipes/{$recipe->id}/tests/{$test->id}");

    $response->assertRedirect();

    $this->assertDatabaseMissing('recipe_tests', ['id' => $test->id]);
    $this->assertDatabaseMissing('recipe_test_photos', ['id' => $photo->id]);
});

test('PUT /recipes/{recipe}/tests/{test} updates tasting_notes and overall_rating', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $version = RecipeVersion::factory()->for($recipe)->create(['version_number' => 1, 'committed_by' => $owner->id]);
    $recipe->update(['current_version_id' => $version->id]);

    $test = RecipeTest::factory()->create([
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $version->id,
        'user_id' => $owner->id,
        'tasting_notes' => 'Original notes.',
        'overall_rating' => 5,
    ]);

    $response = $this->actingAs($owner)->put("/recipes/{$recipe->id}/tests/{$test->id}", [
        'tasting_notes' => 'Updated notes.',
        'overall_rating' => 9,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recipe_tests', [
        'id' => $test->id,
        'tasting_notes' => 'Updated notes.',
        'overall_rating' => 9,
    ]);
});
