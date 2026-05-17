<?php

use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\RecipeDraftEdit;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers VERSION-02, VERSION-03, VERSION-04, RECIPE-07, METRIC-04.
 *
 * RED until Plan 03-04 ships the draft/version controllers.
 * The Apply-to-Draft scaling test (Blocker 3) is a first-class requirement here.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('PUT /recipes/{id}/draft updates the draft without creating a new version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => ['name' => 'Draft Name', 'portions' => 8, 'ingredient_lines' => []],
        'edit_sequence' => 0,
    ]);

    $versionCountBefore = RecipeVersion::where('recipe_id', $recipe->id)->count();

    $response = $this->actingAs($user)->put("/recipes/{$recipe->id}/draft", [
        'action' => 'update_field',
        'field' => 'notes',
        'value' => 'Updated notes in draft',
    ]);

    $response->assertRedirect();

    $versionCountAfter = RecipeVersion::where('recipe_id', $recipe->id)->count();
    expect($versionCountAfter)->toBe($versionCountBefore);

    $draft->refresh();
    expect($draft->data['notes'] ?? null)->toBe('Updated notes in draft');
});

test('POST /recipes/{id}/versions commits the draft as a new numbered version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => ['name' => 'Ready to commit', 'portions' => 10, 'ingredient_lines' => []],
        'edit_sequence' => 2,
    ]);

    $response = $this->actingAs($user)->post("/recipes/{$recipe->id}/versions", [
        'change_note' => 'Added extra portions',
    ]);

    $response->assertRedirect();

    $versions = RecipeVersion::where('recipe_id', $recipe->id)->orderBy('version_number')->get();
    expect($versions)->toHaveCount(1);
    expect($versions->first()->version_number)->toBe(1);
    expect($versions->first()->change_note)->toBe('Added extra portions');
});

test('POST /recipes/{id}/draft/recall removes the last edit and restores prior state', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => ['name' => 'After edit', 'portions' => 10, 'ingredient_lines' => []],
        'edit_sequence' => 1,
    ]);

    RecipeDraftEdit::create([
        'recipe_draft_id' => $draft->id,
        'sequence' => 1,
        'action' => 'update_field',
        'before_snapshot' => ['name' => 'Before edit', 'portions' => 8, 'ingredient_lines' => []],
    ]);

    $response = $this->actingAs($user)->post("/recipes/{$recipe->id}/draft/recall");

    $response->assertRedirect();

    $draft->refresh();
    expect($draft->data['name'])->toBe('Before edit');
    expect($draft->data['portions'])->toBe(8);
    expect(RecipeDraftEdit::where('recipe_draft_id', $draft->id)->count())->toBe(0);
});

/**
 * Apply-to-Draft scaling test (Blocker 3) — METRIC-04, RECIPE-07.
 *
 * Sends an apply_scale action with a rational factor (3/1) to the draft endpoint.
 * Asserts: every ingredient line quantity multiplied by 3/1 exactly (no float drift),
 * exactly ONE recipe_draft_edits row created, NO new recipe_versions row.
 *
 * RED until Plan 03-04 Task 2 implements the scale action in the draft controller.
 */
test('apply_scale action multiplies every ingredient line quantity by the exact rational factor with no float drift', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => [
            'name' => 'Scalable Recipe',
            'portions' => 4,
            'ingredient_lines' => [
                ['id' => 1, 'quantity' => '200.000000', 'ingredient_id' => 1],
                ['id' => 2, 'quantity' => '150.000000', 'ingredient_id' => 2],
                ['id' => 3, 'quantity' => '50.000000', 'ingredient_id' => 3],
            ],
            'selling_price' => null,
        ],
        'edit_sequence' => 0,
    ]);

    $versionCountBefore = RecipeVersion::where('recipe_id', $recipe->id)->count();

    $response = $this->actingAs($user)->put("/recipes/{$recipe->id}/draft", [
        'action' => 'apply_scale',
        'scale_numerator' => 3,
        'scale_denominator' => 1,
    ]);

    $response->assertRedirect();

    $draft->refresh();

    // Each quantity must be multiplied by 3/1 — compare as decimal strings, no float drift
    $lines = $draft->data['ingredient_lines'];
    expect((string) $lines[0]['quantity'])->toBe('600.000000');
    expect((string) $lines[1]['quantity'])->toBe('450.000000');
    expect((string) $lines[2]['quantity'])->toBe('150.000000');

    // Exactly ONE draft edit row produced (one logical Recall-able action)
    expect(RecipeDraftEdit::where('recipe_draft_id', $draft->id)->count())->toBe(1);

    // No new recipe_versions row was created
    $versionCountAfter = RecipeVersion::where('recipe_id', $recipe->id)->count();
    expect($versionCountAfter)->toBe($versionCountBefore);
});

/**
 * apply_scale scales section-nested lines (the shape the builder actually sends)
 * and persists an updated portion count when provided.
 */
test('apply_scale scales section lines and persists an updated portion count', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => [
            'name' => 'Portion Scale Recipe',
            'portions' => 4,
            'sections' => [
                [
                    'id' => -1,
                    'name' => 'Main',
                    'order' => 1,
                    'steps' => [],
                    'lines' => [
                        ['id' => -1, 'quantity' => '100.000000', 'ingredient_id' => 1],
                    ],
                ],
            ],
        ],
        'edit_sequence' => 0,
    ]);

    $response = $this->actingAs($user)->put("/recipes/{$recipe->id}/draft", [
        'action' => 'apply_scale',
        'scale_numerator' => 2,
        'scale_denominator' => 1,
        'portions' => 8,
    ]);

    $response->assertRedirect();

    $draft->refresh();

    expect((string) $draft->data['sections'][0]['lines'][0]['quantity'])->toBe('200.000000');
    expect($draft->data['portions'])->toBe(8);
});
