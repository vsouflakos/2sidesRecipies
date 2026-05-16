<?php

use App\Models\Ingredient;
use App\Models\IngredientConversion;
use Database\Seeders\IngredientCategorySeeder;
use Database\Seeders\UnitSeeder;

/**
 * Covers INGR-02 (USDA) and INGR-05 — idempotent USDA FoodData Central import command.
 *
 * The command accepts individual --*-file options so the suite can run against
 * small bundled fixtures instead of the full USDA download.
 */
beforeEach(function () {
    $this->seed(IngredientCategorySeeder::class);
    $this->seed(UnitSeeder::class);
});

test('the usda import command creates new ingredient rows from fixtures', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ])->assertExitCode(0);

    // The fixture has 5 real foods plus one sub_sample_food provenance row.
    expect(Ingredient::where('source', 'usda')->count())->toBe(5);
    expect(Ingredient::where('source', 'usda')->where('source_id', '900001')->exists())
        ->toBeFalse();
});

test('re-running the usda import is idempotent', function () {
    $dir = base_path('tests/fixtures/ingredients');
    $args = [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ];

    $this->artisan('ingredients:import-usda', $args);
    $countAfterFirst = Ingredient::where('source', 'usda')->count();

    $this->artisan('ingredients:import-usda', $args);
    $countAfterSecond = Ingredient::where('source', 'usda')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('usda import creates ingredient_conversions rows from food_portion data', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ]);

    expect(IngredientConversion::where('source', 'usda')->count())->toBeGreaterThan(0);
});

test('newly imported usda ingredients are unverified', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ]);

    $verifiedCount = Ingredient::where('source', 'usda')->where('verified', true)->count();

    expect($verifiedCount)->toBe(0);
});
