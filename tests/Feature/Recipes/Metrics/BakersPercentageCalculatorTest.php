<?php

use App\Support\Recipes\BakersPercentageCalculator;

/**
 * Covers METRIC-06.
 *
 * RED until Plan 03-02 ships App\Support\Recipes\BakersPercentageCalculator.
 */
test("baker's percentage computed against a single flour line", function () {
    $calculator = app(BakersPercentageCalculator::class);

    /**
     * @var array<int, array{quantity_g: string, is_flour_base: bool, ingredient_name: string}> $lines
     */
    $lines = [
        ['quantity_g' => '500.000000', 'is_flour_base' => true, 'ingredient_name' => 'Bread Flour'],
        ['quantity_g' => '350.000000', 'is_flour_base' => false, 'ingredient_name' => 'Water'],
        ['quantity_g' => '10.000000', 'is_flour_base' => false, 'ingredient_name' => 'Salt'],
        ['quantity_g' => '5.000000', 'is_flour_base' => false, 'ingredient_name' => 'Yeast'],
    ];

    $result = $calculator->compute($lines);

    // Flour base: 500g
    // Water: (350/500) * 100 = 70.00%
    // Salt: (10/500) * 100 = 2.00%
    // Yeast: (5/500) * 100 = 1.00%
    expect($result['flour_base_g'])->toBe('500.000000');
    expect($result['percentages']['Water'])->toBe('70.00');
    expect($result['percentages']['Salt'])->toBe('2.00');
    expect($result['percentages']['Yeast'])->toBe('1.00');
});

test("baker's percentage computed against multiple flour lines summed", function () {
    $calculator = app(BakersPercentageCalculator::class);

    $lines = [
        ['quantity_g' => '300.000000', 'is_flour_base' => true, 'ingredient_name' => 'Bread Flour'],
        ['quantity_g' => '200.000000', 'is_flour_base' => true, 'ingredient_name' => 'Whole Wheat Flour'],
        ['quantity_g' => '350.000000', 'is_flour_base' => false, 'ingredient_name' => 'Water'],
        ['quantity_g' => '10.000000', 'is_flour_base' => false, 'ingredient_name' => 'Salt'],
    ];

    $result = $calculator->compute($lines);

    // Flour base: 300 + 200 = 500g
    // Water: (350/500) * 100 = 70.00%
    // Salt: (10/500) * 100 = 2.00%
    expect($result['flour_base_g'])->toBe('500.000000');
    expect($result['percentages']['Water'])->toBe('70.00');
    expect($result['percentages']['Salt'])->toBe('2.00');
});

test('hydration ratio is computed as total water weight over total flour weight times 100', function () {
    $calculator = app(BakersPercentageCalculator::class);

    $lines = [
        ['quantity_g' => '1000.000000', 'is_flour_base' => true, 'ingredient_name' => 'Flour'],
        ['quantity_g' => '680.000000', 'is_flour_base' => false, 'ingredient_name' => 'Water', 'is_water' => true],
        ['quantity_g' => '20.000000', 'is_flour_base' => false, 'ingredient_name' => 'Salt'],
    ];

    $result = $calculator->compute($lines);

    // Hydration: (680 / 1000) * 100 = 68.00%
    expect($result['hydration_pct'])->toBe('68.00');
});

test("baker's percentage returns null when no flour lines are marked", function () {
    $calculator = app(BakersPercentageCalculator::class);

    $lines = [
        ['quantity_g' => '200.000000', 'is_flour_base' => false, 'ingredient_name' => 'Sugar'],
        ['quantity_g' => '100.000000', 'is_flour_base' => false, 'ingredient_name' => 'Butter'],
    ];

    $result = $calculator->compute($lines);

    expect($result)->toBeNull();
});
