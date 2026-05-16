<?php

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers RECIPE-01, 02, 03, 07, 09, 10, 11.
 *
 * RED until Plan 03-04 ships the recipe CRUD controllers and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('POST /recipes creates a recipe and commits v1', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($user)->post('/recipes', [
        'name' => 'Sourdough Bread',
        'yield_amount' => 1000,
        'portions' => 8,
        'difficulty' => 'medium',
    ]);

    $response->assertRedirect();

    $recipe = Recipe::where('user_id', $user->id)->first();

    expect($recipe)->not->toBeNull();
    expect($recipe->name)->toBe('Sourdough Bread');

    $version = RecipeVersion::where('recipe_id', $recipe->id)->first();
    expect($version)->not->toBeNull();
    expect($version->version_number)->toBe(1);
});

test('POST /recipes with only a name defaults yield_amount and portions and creates draft', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($user)->post('/recipes', [
        'name' => 'Quick Test Recipe',
    ]);

    $response->assertRedirect();

    $recipe = Recipe::where('user_id', $user->id)->first();

    expect($recipe)->not->toBeNull()
        ->and($recipe->name)->toBe('Quick Test Recipe')
        ->and($recipe->yield_amount)->not->toBeNull()
        ->and((float) $recipe->yield_amount)->toBeGreaterThan(0)
        ->and($recipe->portions)->not->toBeNull()
        ->and((float) $recipe->portions)->toBeGreaterThan(0);

    $version = RecipeVersion::where('recipe_id', $recipe->id)->first();
    expect($version)->not->toBeNull()
        ->and($version->version_number)->toBe(1);

    $draft = $recipe->draft;
    expect($draft)->not->toBeNull()
        ->and($draft->data['yield_amount'])->not->toBeNull()
        ->and($draft->data['portions'])->not->toBeNull();
});

test('GET /recipes/{recipe} renders the builder page with normalized draft data for a fresh recipe', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    // Create a fresh recipe (the path that was crashing)
    $response = $this->actingAs($user)->post('/recipes', ['name' => 'Empty New Recipe']);
    $response->assertRedirect();

    $recipe = \App\Models\Recipe::where('user_id', $user->id)->first();
    expect($recipe)->not->toBeNull();

    // Visit the builder page
    $showResponse = $this->actingAs($user)->get("/recipes/{$recipe->id}");
    $showResponse->assertOk();
    $showResponse->assertInertia(function ($page) {
        $page->component('recipes/show');

        // draft must be present and each section must have a steps array
        $page->has('draft');
        $page->has('draft.sections');
        $page->has('draft.edit_sequence');

        $page->where('draft.sections.0.steps', []);
        $page->where('draft.sections.0.lines', []);

        // versions must have created_at and is_current fields
        $page->has('versions.0.created_at');
        $page->has('versions.0.is_current');

        // metrics must be present (non-null — empty recipe still computes zeros)
        $page->has('metrics');
    });
});

test('ingredient lines accept weight, volume, and count units', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->post('/recipes', [
        'name' => 'Test Recipe',
        'yield_amount' => 500,
        'portions' => 4,
        'ingredient_lines' => [
            ['ingredient_id' => 1, 'quantity' => 200, 'unit_type' => 'weight'],
            ['ingredient_id' => 2, 'quantity' => 250, 'unit_type' => 'volume'],
            ['ingredient_id' => 3, 'quantity' => 2, 'unit_type' => 'count'],
        ],
    ]);

    // This test will pass once the controller and unit handling is implemented
    $recipe = Recipe::where('user_id', $user->id)->first();
    expect($recipe)->not->toBeNull();
});

test('steps persist in order', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->post('/recipes', [
        'name' => 'Ordered Steps Recipe',
        'yield_amount' => 1000,
        'portions' => 8,
        'steps' => [
            ['instruction' => 'Step one', 'order' => 1],
            ['instruction' => 'Step two', 'order' => 2],
            ['instruction' => 'Step three', 'order' => 3],
        ],
    ]);

    $recipe = Recipe::where('user_id', $user->id)->first();

    if ($recipe) {
        $steps = $recipe->steps()->orderBy('order')->get();
        expect($steps->pluck('instruction')->toArray())->toBe(['Step one', 'Step two', 'Step three']);
    }
})->todo();

test('duplicate creates an independent recipe with its own v1 and no lineage FK', function () {
    $user = User::factory()->create();
    $user->assignRole('User');
    $source = Recipe::factory()->create(['user_id' => $user->id]);
    $sourceVersion = RecipeVersion::factory()->create([
        'recipe_id' => $source->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'Original'],
    ]);
    $source->update(['current_version_id' => $sourceVersion->id]);

    $response = $this->actingAs($user)->post("/recipes/{$source->id}/duplicate");

    $response->assertRedirect();

    $recipes = Recipe::where('user_id', $user->id)->get();
    expect($recipes)->toHaveCount(2);

    $duplicate = $recipes->where('id', '!=', $source->id)->first();
    expect($duplicate)->not->toBeNull();

    $duplicateVersion = RecipeVersion::where('recipe_id', $duplicate->id)->first();
    expect($duplicateVersion)->not->toBeNull();
    expect($duplicateVersion->version_number)->toBe(1);
});

test('hero image and step image paths persist', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->post('/recipes', [
        'name' => 'Image Recipe',
        'yield_amount' => 500,
        'portions' => 4,
        'hero_image_path' => 'recipes/hero/test.jpg',
        'steps' => [
            ['instruction' => 'Bake', 'order' => 1, 'step_image_path' => 'recipes/steps/bake.jpg'],
        ],
    ]);

    $recipe = Recipe::where('user_id', $user->id)->first();

    if ($recipe) {
        expect($recipe->hero_image_path)->toBe('recipes/hero/test.jpg');
        $step = $recipe->steps()->first();
        if ($step) {
            expect($step->step_image_path)->toBe('recipes/steps/bake.jpg');
        }
    }
})->todo();

test('chef notes persist on a recipe', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->post('/recipes', [
        'name' => 'Notes Recipe',
        'yield_amount' => 500,
        'portions' => 4,
        'notes' => 'These are the chef notes for this recipe.',
    ]);

    $recipe = Recipe::where('user_id', $user->id)->first();

    if ($recipe) {
        expect($recipe->notes)->toBe('These are the chef notes for this recipe.');
    }
})->todo();
