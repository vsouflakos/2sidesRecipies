<?php

use App\Models\Ingredient;

/**
 * Covers INGR-02 (OFF) — idempotent Open Food Facts enrichment import command.
 *
 * RED until Plan 02-02 ships the `ingredients:import-off` command.
 */
test('the off import command creates ingredient rows from a fixture', function () {
    $fixture = base_path('tests/fixtures/off-sample.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture])
        ->assertExitCode(0);

    expect(Ingredient::count())->toBeGreaterThan(0);
});

test('re-running the off import does not duplicate ingredients', function () {
    $fixture = base_path('tests/fixtures/off-sample.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterFirst = Ingredient::count();

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterSecond = Ingredient::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('the off import adds a greek translation to a matched ingredient', function () {
    $fixture = base_path('tests/fixtures/off-sample.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);

    expect(Ingredient::query()
        ->whereHas('translations', fn ($t) => $t->where('locale', 'el'))
        ->count()
    )->toBeGreaterThan(0);
});
