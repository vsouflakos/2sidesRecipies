<?php

use App\Models\Ingredient;
use App\Models\Unit;
use App\Support\Recipes\NutritionCalculator;

/**
 * Covers METRIC-01, METRIC-04, METRIC-07, METRIC-09, METRIC-10.
 *
 * RED until Plan 03-02 ships App\Support\Recipes\NutritionCalculator.
 */
test('nutrition per portion computed correctly for known values', function () {
    $calculator = app(NutritionCalculator::class);

    /** @var array<int, array{quantity_g: string, energy_kcal: string, protein_g: string}> $lines */
    $lines = [
        ['quantity_g' => '100.000000', 'energy_kcal' => '350.0000', 'protein_g' => '10.0000'],
        ['quantity_g' => '200.000000', 'energy_kcal' => '50.0000', 'protein_g' => '2.0000'],
    ];

    $result = $calculator->compute($lines, portions: 2);

    // Total: (100/100 * 350) + (200/100 * 50) = 350 + 100 = 450 kcal
    // Per portion: 450 / 2 = 225 kcal
    expect($result['per_portion']['energy_kcal'])->toBe('225.0000');

    // Protein: (100/100 * 10) + (200/100 * 2) = 10 + 4 = 14 g
    // Per portion: 14 / 2 = 7 g
    expect($result['per_portion']['protein_g'])->toBe('7.0000');
});

test('nutrition per 100g computed correctly for known values', function () {
    $calculator = app(NutritionCalculator::class);

    $lines = [
        ['quantity_g' => '500.000000', 'energy_kcal' => '200.0000', 'protein_g' => '20.0000'],
    ];

    $result = $calculator->compute($lines, portions: 4, totalYieldG: '500.000000');

    // Total energy: (500/100 * 200) = 1000 kcal
    // Per 100g: (1000 / 500) * 100 = 200 kcal/100g
    expect($result['per_100g']['energy_kcal'])->toBe('200.0000');
});

test('weight units normalize to grams via base_factor', function () {
    $calculator = app(NutritionCalculator::class);

    // 1 kg = 1000g; ingredient has energy 200 kcal/100g
    // 1 kg of ingredient = 200 * 10 = 2000 kcal total
    $lines = [
        [
            'quantity' => '1.000000',
            'unit_type' => 'weight',
            'base_factor' => '1000.000000', // 1 unit = 1000g
            'quantity_g' => '1000.000000',
            'energy_kcal' => '200.0000',
            'protein_g' => '10.0000',
        ],
    ];

    $result = $calculator->compute($lines, portions: 1, totalYieldG: '1000.000000');

    expect($result['per_portion']['energy_kcal'])->toBe('2000.0000');
});

test('no float drift across 20 ingredient lines — exact decimal string comparison', function () {
    $calculator = app(NutritionCalculator::class);

    // 20 lines each 50g, 123.4567 kcal/100g
    // Each line contributes: (50/100) * 123.4567 = 61.72835 kcal
    // Total: 20 * 61.72835 = 1234.5670 kcal
    // Per portion (1): 1234.5670 kcal
    $lines = array_fill(0, 20, [
        'quantity_g' => '50.000000',
        'energy_kcal' => '123.4567',
        'protein_g' => '0.0000',
    ]);

    $result = $calculator->compute($lines, portions: 1);

    expect($result['per_portion']['energy_kcal'])->toBe('1234.5670');
});

test('calorie density is available as kcal per portion and per 100g', function () {
    $calculator = app(NutritionCalculator::class);

    $lines = [
        ['quantity_g' => '400.000000', 'energy_kcal' => '250.0000', 'protein_g' => '5.0000'],
    ];

    $result = $calculator->compute($lines, portions: 4, totalYieldG: '400.000000');

    // Per portion: (400/100 * 250) / 4 = 1000/4 = 250 kcal
    expect($result['per_portion']['energy_kcal'])->toBe('250.0000');

    // Per 100g: (1000 / 400) * 100 = 250 kcal/100g
    expect($result['per_100g']['energy_kcal'])->toBe('250.0000');
});
