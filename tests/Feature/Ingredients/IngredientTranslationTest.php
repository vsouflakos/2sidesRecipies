<?php

use App\Models\Ingredient;

test('nameFor returns the translation in the requested locale', function () {
    $ingredient = Ingredient::factory()->create();

    $ingredient->translations()->create(['locale' => 'en', 'name' => 'Apple']);
    $ingredient->translations()->create(['locale' => 'el', 'name' => 'Μήλο']);

    $ingredient->load('translations');

    expect($ingredient->nameFor('el'))->toBe('Μήλο');
    expect($ingredient->nameFor('en'))->toBe('Apple');
});

test('nameFor falls back to English when the locale is missing', function () {
    $ingredient = Ingredient::factory()->create();

    $ingredient->translations()->create(['locale' => 'en', 'name' => 'Apple']);

    $ingredient->load('translations');

    expect($ingredient->nameFor('fr'))->toBe('Apple');
});

test('nameFor returns a dash when no translations exist', function () {
    $ingredient = Ingredient::factory()->create();

    $ingredient->load('translations');

    expect($ingredient->nameFor('el'))->toBe('—');
});
