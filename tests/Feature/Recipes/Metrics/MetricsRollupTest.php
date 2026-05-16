<?php

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use App\Models\User;
use App\Support\Recipes\MetricsRollupService;

/**
 * Covers METRIC-08.
 *
 * RED until Plan 03-03 ships App\Support\Recipes\MetricsRollupService.
 */
test('a sub-recipe contributing 250g of a 500g-yield component scales metrics by exactly 0.5', function () {
    $service = app(MetricsRollupService::class);

    $user = User::factory()->create();

    // Component recipe: 500g yield, 200 kcal/100g total → 1000 kcal
    $component = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Component Sauce']);

    $componentVersion = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
        'yield_g' => '500.0000',
        'cached_nutrition_json' => [
            'total' => [
                'energy_kcal' => '1000.0000',
                'protein_g' => '40.0000',
            ],
        ],
        'cached_cost_per_gram' => '0.00500000',
    ]);
    $component->update(['current_version_id' => $componentVersion->id]);

    // Parent recipe uses 250g of the component (scale factor = 250/500 = 0.5)
    $parent = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Main Dish']);

    $subLine = RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentVersion->id,
        'quantity_g' => '250.000000',
    ]);

    $result = $service->computeForLine($subLine, $componentVersion);

    // Scale: 250/500 = 0.5
    // Nutrition contribution: 1000 * 0.5 = 500 kcal
    expect($result['energy_kcal'])->toBe('500.0000');

    // Protein: 40 * 0.5 = 20g
    expect($result['protein_g'])->toBe('20.0000');

    // Cost: 250 * 0.005 = 1.25
    expect($result['cost'])->toBe('1.25000000');
});

test('scale factor is computed as exact decimal ratio with no float drift', function () {
    $service = app(MetricsRollupService::class);

    $user = User::factory()->create();
    $component = Recipe::factory()->create(['user_id' => $user->id]);

    $componentVersion = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
        'yield_g' => '300.0000',
        'cached_nutrition_json' => [
            'total' => ['energy_kcal' => '900.0000', 'protein_g' => '30.0000'],
        ],
        'cached_cost_per_gram' => '0.01000000',
    ]);

    $parent = Recipe::factory()->create(['user_id' => $user->id]);

    $subLine = RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentVersion->id,
        'quantity_g' => '100.000000',  // 100/300 = 1/3
    ]);

    $result = $service->computeForLine($subLine, $componentVersion);

    // 900 * (100/300) = 300 kcal exactly
    expect($result['energy_kcal'])->toBe('300.0000');

    // Cost: 100 * 0.01 = 1.0
    expect($result['cost'])->toBe('1.00000000');
});
