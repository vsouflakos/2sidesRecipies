<?php

use App\Models\Ingredient;
use App\Models\Unit;

test('an ingredient stores a conversion row normalised to grams', function () {
    $ingredient = Ingredient::factory()->create();
    $cup = Unit::firstOrCreate(
        ['name' => 'cup'],
        ['symbol' => 'cup', 'type' => 'volume', 'base_factor' => 236.588]
    );

    $ingredient->conversions()->create([
        'from_amount' => 1,
        'from_unit_id' => $cup->id,
        'gram_weight' => 240,
        'source' => 'curated',
    ]);

    $conversion = $ingredient->conversions()->first();

    expect($conversion->gram_weight)->toBe('240.0000');
    expect($conversion->from_amount)->toBe('1.0000');
});

test('a conversion resolves its unit relationship', function () {
    $ingredient = Ingredient::factory()->create();
    $cup = Unit::firstOrCreate(
        ['name' => 'cup'],
        ['symbol' => 'cup', 'type' => 'volume', 'base_factor' => 236.588]
    );

    $ingredient->conversions()->create([
        'from_amount' => 1,
        'from_unit_id' => $cup->id,
        'gram_weight' => 240,
        'source' => 'usda',
    ]);

    $conversion = $ingredient->conversions()->first();

    expect($conversion->unit)->not->toBeNull();
    expect($conversion->unit->name)->toBe('cup');
});
