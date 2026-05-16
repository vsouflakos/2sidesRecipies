<?php

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientTranslation;
use Database\Seeders\IngredientCategorySeeder;

/**
 * Covers INGR-02 (OFF) and INGR-04, INGR-06 — idempotent Open Food Facts enrichment import command.
 *
 * The command accepts a `--source-file` argument so the suite can run
 * against a small bundled fixture instead of the full OFF CSV download.
 */
beforeEach(function () {
    $this->seed(IngredientCategorySeeder::class);
});

test('the off import command creates ingredient rows from a fixture', function () {
    $fixture = base_path('tests/fixtures/ingredients/off-products.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture])
        ->assertExitCode(0);

    expect(Ingredient::count())->toBeGreaterThan(0);
});

test('re-running the off import does not duplicate ingredients', function () {
    $fixture = base_path('tests/fixtures/ingredients/off-products.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterFirst = Ingredient::count();

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterSecond = Ingredient::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('the off import adds a greek translation to a matched ingredient', function () {
    $fixture = base_path('tests/fixtures/ingredients/off-products.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);

    expect(Ingredient::query()
        ->whereHas('translations', fn ($t) => $t->where('locale', 'el'))
        ->count()
    )->toBeGreaterThan(0);
});

test('the off import stores allergen pivot rows for products with allergens', function () {
    // Seed the allergens so the import can resolve slugs
    Allergen::firstOrCreate(['slug' => 'gluten'], ['name' => 'Gluten', 'note' => null]);
    Allergen::firstOrCreate(['slug' => 'eggs'], ['name' => 'Eggs', 'note' => null]);
    Allergen::firstOrCreate(['slug' => 'milk'], ['name' => 'Milk', 'note' => null]);
    Allergen::firstOrCreate(['slug' => 'celery'], ['name' => 'Celery', 'note' => null]);

    $fixture = base_path('tests/fixtures/ingredients/off-products.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);

    $ingredientWithAllergens = Ingredient::whereHas('allergens')->first();

    expect($ingredientWithAllergens)->not->toBeNull();
});

test('re-running the off import is idempotent for allergen pivot rows', function () {
    Allergen::firstOrCreate(['slug' => 'gluten'], ['name' => 'Gluten', 'note' => null]);
    Allergen::firstOrCreate(['slug' => 'eggs'], ['name' => 'Eggs', 'note' => null]);
    Allergen::firstOrCreate(['slug' => 'milk'], ['name' => 'Milk', 'note' => null]);

    $fixture = base_path('tests/fixtures/ingredients/off-products.csv');

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterFirst = IngredientTranslation::count();

    $this->artisan('ingredients:import-off', ['--source-file' => $fixture]);
    $countAfterSecond = IngredientTranslation::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
