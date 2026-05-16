<?php

use App\Models\Ingredient;

/**
 * Covers INGR-02 (CIQUAL) — idempotent CIQUAL import command.
 *
 * RED until Plan 02-02 ships the `ingredients:import-ciqual` command.
 * The command accepts a `--source-file` argument so the suite can run
 * against a small bundled fixture instead of the full bundled XML.
 */
test('the ciqual import command creates ingredient rows from a fixture', function () {
    $fixture = base_path('tests/fixtures/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture])
        ->assertExitCode(0);

    expect(Ingredient::where('source', 'ciqual')->count())->toBeGreaterThan(0);
});

test('re-running the ciqual import does not duplicate ingredients', function () {
    $fixture = base_path('tests/fixtures/ciqual-sample.xml');

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);
    $countAfterFirst = Ingredient::where('source', 'ciqual')->count();

    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);
    $countAfterSecond = Ingredient::where('source', 'ciqual')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
