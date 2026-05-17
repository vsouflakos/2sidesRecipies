<?php

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientPrice;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitSeeder;

/**
 * Covers METRIC-01..04: the builder metrics panel must come alive from a draft.
 *
 * Regression test for the dead metrics panel — MetricsAggregator previously read
 * denormalized fields the draft never stores, so every metric resolved to zero
 * and the data-gap banner showed for any ingredient added.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(UnitSeeder::class);
});

test('builder metrics resolve nutrition, cost and allergens from a draft ingredient line', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $gram = Unit::where('name', 'gram')->firstOrFail();

    // 200 kcal / 100 g, priced at 0.02 per gram.
    $ingredient = Ingredient::factory()->create(['energy_kcal' => 200]);
    IngredientPrice::factory()->create([
        'ingredient_id' => $ingredient->id,
        'per_gram_cost' => '0.02000000',
        'unit_id' => $gram->id,
    ]);

    $gluten = Allergen::factory()->create(['slug' => 'gluten']);
    $ingredient->allergens()->attach($gluten->id, ['state' => 'contains']);

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => [
            'name' => 'Metrics Recipe',
            'portions' => 5,
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
                            'ingredient_id' => $ingredient->id,
                            'sub_recipe_version_id' => null,
                            'quantity' => '250',
                            'unit_id' => $gram->id,
                            'yield_pct' => '100',
                            'is_flour_base' => false,
                        ],
                    ],
                ],
            ],
        ],
        'edit_sequence' => 0,
    ]);

    $response = $this->actingAs($user)->get("/recipes/{$recipe->id}");

    $response->assertOk();
    $response->assertInertia(function ($page) {
        // 200 kcal/100g × 250 g = 500 kcal total ÷ 5 portions = 100 per portion.
        $page->where('metrics.nutrition.per_portion.energy_kcal', '100.0000');

        // 250 g × 0.02 = 5.00 total ÷ 5 portions = 1.00 per portion.
        $page->where('metrics.cost.total_cost', '5.00');
        $page->where('metrics.cost.cost_per_portion', '1.00');

        // The ingredient's allergen rolls up to the recipe.
        $page->where('metrics.allergens.contains', ['gluten']);

        // Fully-resolved line → no data-gap entries.
        $page->where('metrics.missing_data', []);
    });
});

test('a draft ingredient line with no nutrition or price data flags a data gap', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $gram = Unit::where('name', 'gram')->firstOrFail();

    // No price, no energy value — the line is unresolvable.
    $ingredient = Ingredient::factory()->create([
        'name_cache' => 'Mystery Powder',
        'energy_kcal' => null,
    ]);

    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    RecipeDraft::factory()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'data' => [
            'name' => 'Gap Recipe',
            'portions' => 2,
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
                            'ingredient_id' => $ingredient->id,
                            'sub_recipe_version_id' => null,
                            'name' => 'Mystery Powder',
                            'quantity' => '100',
                            'unit_id' => $gram->id,
                            'yield_pct' => '100',
                            'is_flour_base' => false,
                        ],
                    ],
                ],
            ],
        ],
        'edit_sequence' => 0,
    ]);

    $response = $this->actingAs($user)->get("/recipes/{$recipe->id}");

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->where('metrics.missing_data', ['Mystery Powder']);
    });
});
