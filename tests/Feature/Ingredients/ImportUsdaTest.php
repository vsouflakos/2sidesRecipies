<?php

use App\Models\Ingredient;
use App\Models\IngredientConversion;
use App\Models\Nutrient;
use Database\Seeders\IngredientCategorySeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Support\Facades\DB;

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

test('usda import populates the nutrients reference table from nutrient.csv', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ]);

    // The fixture nutrient.csv lists 32 nutrient definitions.
    expect(Nutrient::count())->toBe(32);
    expect(Nutrient::where('usda_nutrient_id', 1051)->value('name'))->toBe('Water');
});

test('usda import captures every nutrient in the ingredient_nutrients pivot', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ]);

    $tomato = Ingredient::where('source', 'usda')->where('source_id', '169291')->firstOrFail();

    // Water (1051) has no flat column — it must still land in the pivot.
    $water = $tomato->nutrients()->where('usda_nutrient_id', 1051)->first();
    expect($water)->not->toBeNull();
    expect((float) $water->pivot->amount)->toBe(94.5);

    // Manganese (1101) likewise has no flat column.
    expect($tomato->nutrients()->where('usda_nutrient_id', 1101)->exists())->toBeTrue();
});

test('usda import maps starch and total sugars to the flat nutrition columns', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ]);

    $tomato = Ingredient::where('source', 'usda')->where('source_id', '169291')->firstOrFail();

    // Starch (id 1009) → starch_g.
    expect((float) $tomato->starch_g)->toBe(1.5);
    // Sugars, Total (id 1063 = 2.5) wins over "total including NLEA" (id 2000 = 2.63).
    expect((float) $tomato->sugars_g)->toBe(2.5);
});

test('re-running the usda import does not duplicate ingredient_nutrients rows', function () {
    $dir = base_path('tests/fixtures/ingredients');
    $args = [
        '--food-file' => $dir.'/usda-food.csv',
        '--nutrient-file' => $dir.'/usda-nutrient.csv',
        '--food-nutrient-file' => $dir.'/usda-food-nutrient.csv',
        '--portion-file' => $dir.'/usda-food-portion.csv',
        '--measure-unit-file' => $dir.'/usda-measure-unit.csv',
    ];

    $this->artisan('ingredients:import-usda', $args);
    $countAfterFirst = DB::table('ingredient_nutrients')->count();

    $this->artisan('ingredients:import-usda', $args);
    $countAfterSecond = DB::table('ingredient_nutrients')->count();

    expect($countAfterFirst)->toBeGreaterThan(0);
    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('usda import imports every dataset passed via --dir', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--dir' => [$dir.'/usda-set-a', $dir.'/usda-set-b'],
    ])->assertExitCode(0);

    // Set A contributes 5 real foods; set B contributes 2 sr_legacy foods.
    expect(Ingredient::where('source', 'usda')->count())->toBe(7);
    expect(Ingredient::where('source', 'usda')->where('source_id', '200002')->value('name_cache'))
        ->toBe('Carrots, raw');
    expect(Ingredient::where('source', 'usda')->where('source_id', '173168')->exists())
        ->toBeTrue();
});

test('usda import records sr legacy free-text portions as conversions', function () {
    $dir = base_path('tests/fixtures/ingredients');

    $this->artisan('ingredients:import-usda', [
        '--dir' => [$dir.'/usda-set-b'],
    ])->assertExitCode(0);

    // "cup" free text resolves to the cup unit.
    $rice = Ingredient::where('source', 'usda')->where('source_id', '200001')->firstOrFail();
    $riceConversion = $rice->conversions()->with('unit')->first();
    expect($riceConversion)->not->toBeNull();
    expect($riceConversion->unit->name)->toBe('cup');
    expect((float) $riceConversion->gram_weight)->toBe(185.0);

    // "large" is not a known unit — it falls back to the generic "piece" unit
    // with the original description preserved in the modifier.
    $carrots = Ingredient::where('source', 'usda')->where('source_id', '200002')->firstOrFail();
    $carrotsConversion = $carrots->conversions()->with('unit')->first();
    expect($carrotsConversion)->not->toBeNull();
    expect($carrotsConversion->unit->name)->toBe('piece');
    expect($carrotsConversion->modifier)->toBe('large');
});
