<?php

use App\Support\Recipes\ShrinkageCalculator;

/**
 * Covers METRIC-05.
 *
 * RED until Plan 03-02 ships App\Support\Recipes\ShrinkageCalculator.
 */
test('cooking loss derived from per-line yield_pct', function () {
    $calculator = app(ShrinkageCalculator::class);

    /**
     * @var array<int, array{quantity_g: string, yield_pct: string|null}> $lines
     */
    $lines = [
        ['quantity_g' => '200.000000', 'yield_pct' => '80.0000'],   // 40g lost
        ['quantity_g' => '300.000000', 'yield_pct' => '100.0000'],  // 0g lost
        ['quantity_g' => '100.000000', 'yield_pct' => '70.0000'],   // 30g lost
    ];

    $result = $calculator->compute($lines);

    // Raw weight: 200 + 300 + 100 = 600g
    // After yield: (200 * 0.80) + (300 * 1.00) + (100 * 0.70) = 160 + 300 + 70 = 530g
    // Loss: 600 - 530 = 70g
    // Shrinkage %: (70 / 600) * 100 = 11.6667%
    expect($result['raw_weight_g'])->toBe('600.000000');
    expect($result['finished_weight_g'])->toBe('530.000000');
    expect($result['loss_g'])->toBe('70.000000');
    expect($result['shrinkage_pct'])->toBe('11.6667');
});

test('lines with null yield_pct are treated as 100 percent yield (no loss)', function () {
    $calculator = app(ShrinkageCalculator::class);

    $lines = [
        ['quantity_g' => '500.000000', 'yield_pct' => null],        // no loss
        ['quantity_g' => '200.000000', 'yield_pct' => '90.0000'],   // 20g lost
    ];

    $result = $calculator->compute($lines);

    // Raw: 700g; After: 500 + 180 = 680g; Loss: 20g
    expect($result['raw_weight_g'])->toBe('700.000000');
    expect($result['finished_weight_g'])->toBe('680.000000');
    expect($result['loss_g'])->toBe('20.000000');
});

test('zero shrinkage when all lines have 100 percent yield', function () {
    $calculator = app(ShrinkageCalculator::class);

    $lines = [
        ['quantity_g' => '300.000000', 'yield_pct' => '100.0000'],
        ['quantity_g' => '200.000000', 'yield_pct' => '100.0000'],
    ];

    $result = $calculator->compute($lines);

    expect($result['loss_g'])->toBe('0.000000');
    expect($result['shrinkage_pct'])->toBe('0.0000');
});
