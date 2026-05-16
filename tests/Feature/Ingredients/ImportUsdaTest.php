<?php

use App\Models\Ingredient;

/**
 * Covers INGR-02 (USDA) — idempotent USDA FoodData Central import command.
 *
 * RED until Plan 02-02 ships the `ingredients:import-usda` command.
 */
test('the usda import command creates new ingredient rows from a fixture', function () {
    $fixture = base_path('tests/fixtures/usda-sample');

    $this->artisan('ingredients:import-usda', ['--source-file' => $fixture])
        ->assertExitCode(0);

    expect(Ingredient::where('source', 'usda')->count())->toBeGreaterThan(0);
});

test('re-running the usda import is idempotent', function () {
    $fixture = base_path('tests/fixtures/usda-sample');

    $this->artisan('ingredients:import-usda', ['--source-file' => $fixture]);
    $countAfterFirst = Ingredient::where('source', 'usda')->count();

    $this->artisan('ingredients:import-usda', ['--source-file' => $fixture]);
    $countAfterSecond = Ingredient::where('source', 'usda')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
