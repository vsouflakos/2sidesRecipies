<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\RecipeVersion;
use App\Models\Unit;
use App\Models\User;
use App\Support\Recipes\RecipeDraftManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitSeeder;

/**
 * Regression: recipe list cards showed a stale committed-version nutrition
 * snapshot instead of the live draft. The draft now carries its own cached
 * metrics, refreshed on every edit, and the card reads that first.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(UnitSeeder::class);
});

/**
 * Build a draft data payload with a single ingredient line.
 *
 * @return array<string, mixed>
 */
function draftDataWithLine(int $ingredientId, int $unitId): array
{
    return [
        'name' => 'Cached Recipe',
        'portions' => 1,
        'selling_price' => null,
        'sections' => [
            [
                'id' => -1,
                'name' => 'Main',
                'order' => 1,
                'steps' => [],
                'lines' => [
                    [
                        'id' => -1,
                        'ingredient_id' => $ingredientId,
                        'sub_recipe_version_id' => null,
                        'quantity' => '100',
                        'unit_id' => $unitId,
                        'yield_pct' => '100',
                        'is_flour_base' => false,
                    ],
                ],
            ],
        ],
    ];
}

test('applying a draft edit refreshes the draft cached metrics', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $gram = Unit::where('name', 'gram')->firstOrFail();
    $ingredient = Ingredient::factory()->create([
        'energy_kcal' => 200,
        'protein_g' => 10,
        'carbs_g' => 30,
        'fat_g' => 5,
    ]);

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => ['name' => 'Cached Recipe', 'portions' => 1, 'sections' => []],
        'edit_sequence' => 0,
    ]);

    app(RecipeDraftManager::class)->applyEdit(
        $draft,
        'update',
        draftDataWithLine($ingredient->id, $gram->id),
    );

    $draft->refresh();

    // 200 kcal/100 g × 100 g ÷ 1 portion = 200 per portion.
    expect($draft->cached_nutrition_json)->not->toBeNull()
        ->and($draft->cached_nutrition_json['per_portion']['energy_kcal'])->toBe('200.0000')
        ->and($draft->cached_nutrition_json['per_portion']['protein_g'])->toBe('10.0000');
});

test('recipe list card shows live draft nutrition even when the committed version cache is stale', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $gram = Unit::where('name', 'gram')->firstOrFail();
    $ingredient = Ingredient::factory()->create([
        'energy_kcal' => 200,
        'protein_g' => 10,
        'carbs_g' => 30,
        'fat_g' => 5,
    ]);

    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'portions' => 1]);

    // Stale committed version — frozen with all-zero nutrition (the bug condition).
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'committed_by' => $user->id,
        'cached_nutrition_json' => [
            'per_portion' => [
                'energy_kcal' => '0.0000',
                'protein_g' => '0.0000',
                'carbs_g' => '0.0000',
                'fat_g' => '0.0000',
            ],
        ],
    ]);
    $recipe->current_version_id = $version->id;
    $recipe->save();

    // Live draft with a real ingredient line.
    $draft = RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => draftDataWithLine($ingredient->id, $gram->id),
        'edit_sequence' => 0,
    ]);
    app(RecipeDraftManager::class)->refreshMetricsCache($draft);

    $response = $this->actingAs($user)->get('/recipes');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.calories_per_portion', '200.0000')
        ->where('recipes.data.0.protein_per_portion', '10.0000')
        ->where('recipes.data.0.carbs_per_portion', '30.0000')
        ->where('recipes.data.0.fat_per_portion', '5.0000')
    );
});
