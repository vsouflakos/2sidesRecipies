<?php

use App\Support\Recipes\CostCalculator;

/**
 * Covers METRIC-02, METRIC-03.
 *
 * RED until Plan 03-02 ships App\Support\Recipes\CostCalculator.
 */
test('cost per portion computed correctly for known values', function () {
    $calculator = app(CostCalculator::class);

    /**
     * @var array<int, array{quantity_g: string, cost_per_gram: string}> $lines
     */
    $lines = [
        ['quantity_g' => '200.000000', 'cost_per_gram' => '0.005000'],   // 1.00
        ['quantity_g' => '500.000000', 'cost_per_gram' => '0.002000'],   // 1.00
        ['quantity_g' => '100.000000', 'cost_per_gram' => '0.010000'],   // 1.00
    ];

    $result = $calculator->compute($lines, portions: '4.0000');

    // Total cost: 1.00 + 1.00 + 1.00 = 3.00
    // Per portion: 3.00 / 4 = 0.75
    expect($result['total_cost'])->toBe('3.00');
    expect($result['cost_per_portion'])->toBe('0.75');
});

test('total recipe cost sums all lines correctly', function () {
    $calculator = app(CostCalculator::class);

    $lines = [
        ['quantity_g' => '1000.000000', 'cost_per_gram' => '0.003000'],  // 3.00
        ['quantity_g' => '250.000000', 'cost_per_gram' => '0.008000'],   // 2.00
    ];

    $result = $calculator->compute($lines, portions: '1.0000');

    expect($result['total_cost'])->toBe('5.00');
    expect($result['cost_per_portion'])->toBe('5.00');
});

test('food cost percentage equals cost divided by selling price times 100', function () {
    $calculator = app(CostCalculator::class);

    $lines = [
        ['quantity_g' => '400.000000', 'cost_per_gram' => '0.005000'],  // 2.00 total
    ];

    $result = $calculator->compute($lines, portions: '2.0000', sellingPricePerPortion: '4.0000');

    // Cost per portion: 2.00 / 2 = 1.00
    // Selling price: 4.00
    // Food cost %: (1.00 / 4.00) * 100 = 25.00%
    expect($result['cost_per_portion'])->toBe('1.00');
    expect($result['food_cost_pct'])->toBe('25.00');
});

test('food cost percentage is null when selling price is not provided', function () {
    $calculator = app(CostCalculator::class);

    $lines = [
        ['quantity_g' => '200.000000', 'cost_per_gram' => '0.010000'],
    ];

    $result = $calculator->compute($lines, portions: '4.0000', sellingPricePerPortion: null);

    expect($result['food_cost_pct'])->toBeNull();
});
