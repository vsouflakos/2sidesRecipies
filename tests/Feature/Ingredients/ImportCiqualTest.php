<?php

use App\Models\Ingredient;
use App\Models\IngredientTranslation;
use Database\Seeders\IngredientCategorySeeder;

/**
 * Covers INGR-02 (CIQUAL) — idempotent CIQUAL import command.
 *
 * The command accepts --alim-file and --compo-file arguments so the suite
 * can run against small bundled fixtures in the official ANSES two-file
 * format instead of the full multi-megabyte exports.
 */
beforeEach(function () {
    $this->seed(IngredientCategorySeeder::class);
});

/**
 * @return array{--alim-file: string, --compo-file: string}
 */
function ciqualFixtureArgs(): array
{
    $dir = base_path('tests/fixtures/ingredients');

    return [
        '--alim-file' => $dir.'/ciqual-alim-sample.xml',
        '--compo-file' => $dir.'/ciqual-compo-sample.xml',
    ];
}

test('the ciqual import command creates ingredient rows from a fixture', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs())
        ->assertExitCode(0);

    expect(Ingredient::where('source', 'ciqual')->count())->toBe(3);
});

test('re-running the ciqual import does not duplicate ingredients', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());
    $countAfterFirst = Ingredient::where('source', 'ciqual')->count();

    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());
    $countAfterSecond = Ingredient::where('source', 'ciqual')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('ciqual import populates nutrition columns and parses comma decimals', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());

    $wheat = Ingredient::where('source', 'ciqual')->where('source_id', '2001')->first();

    expect($wheat)->not->toBeNull()
        ->and((float) $wheat->energy_kcal)->toBe(340.0)
        // teneur "13,2" — French decimal comma must parse to 13.2.
        ->and((float) $wheat->protein_g)->toBe(13.2)
        ->and((float) $wheat->fibre_g)->toBe(9.7);
});

test('ciqual import treats the "< N" trace marker as zero', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());

    $tomato = Ingredient::where('source', 'ciqual')->where('source_id', '2003')->first();

    // Vitamin C is recorded as "< 0,5" in the fixture — a trace-level value.
    expect((float) $tomato->vitamin_c_mg)->toBe(0.0);
});

test('ciqual import stores an english translation for each ingredient', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());

    $translationCount = IngredientTranslation::where('locale', 'en')
        ->whereIn('ingredient_id', Ingredient::where('source', 'ciqual')->pluck('id'))
        ->count();

    expect($translationCount)->toBe(3);
});

test('newly imported ciqual ingredients are unverified', function () {
    $this->artisan('ingredients:import-ciqual', ciqualFixtureArgs());

    $verifiedCount = Ingredient::where('source', 'ciqual')->where('verified', true)->count();

    expect($verifiedCount)->toBe(0);
});
