<?php

use App\Models\Ingredient;
use App\Models\IngredientTranslation;
use Database\Seeders\IngredientCategorySeeder;

/**
 * Covers INGR-02 (CIQUAL) — idempotent CIQUAL import command.
 *
 * The command accepts a `--source-file` argument so the suite can run
 * against a small bundled fixture instead of the full bundled XML.
 */
beforeEach(function () {
    $this->seed(IngredientCategorySeeder::class);
});

test('the ciqual import command creates ingredient rows from a fixture', function () {
    $fixture = base_path('tests/fixtures/ingredients/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture])
        ->assertExitCode(0);

    expect(Ingredient::where('source', 'ciqual')->count())->toBeGreaterThan(0);
});

test('re-running the ciqual import does not duplicate ingredients', function () {
    $fixture = base_path('tests/fixtures/ingredients/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);
    $countAfterFirst = Ingredient::where('source', 'ciqual')->count();

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);
    $countAfterSecond = Ingredient::where('source', 'ciqual')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('ciqual import populates nutrition columns', function () {
    $fixture = base_path('tests/fixtures/ingredients/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);

    $ingredient = Ingredient::where('source', 'ciqual')->whereNotNull('energy_kcal')->first();

    expect($ingredient)->not->toBeNull()
        ->and($ingredient->energy_kcal)->not->toBeNull();
});

test('ciqual import stores an english translation for each ingredient', function () {
    $fixture = base_path('tests/fixtures/ingredients/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);

    $translationCount = IngredientTranslation::where('locale', 'en')
        ->whereIn('ingredient_id', Ingredient::where('source', 'ciqual')->pluck('id'))
        ->count();

    expect($translationCount)->toBeGreaterThan(0);
});

test('newly imported ciqual ingredients are unverified', function () {
    $fixture = base_path('tests/fixtures/ingredients/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);

    $verifiedCount = Ingredient::where('source', 'ciqual')->where('verified', true)->count();

    expect($verifiedCount)->toBe(0);
});
