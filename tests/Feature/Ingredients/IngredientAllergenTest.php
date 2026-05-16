<?php

use App\Models\Allergen;
use App\Models\Ingredient;

test('an ingredient stores contains and may_contain allergen pivot states', function () {
    $ingredient = Ingredient::factory()->create();
    $gluten = Allergen::factory()->create(['name' => 'Gluten', 'slug' => 'gluten']);
    $milk = Allergen::factory()->create(['name' => 'Milk', 'slug' => 'milk']);

    $ingredient->allergens()->sync([
        $gluten->id => ['state' => 'contains'],
        $milk->id => ['state' => 'may_contain'],
    ]);

    $ingredient->load('allergens');

    expect($ingredient->allergens)->toHaveCount(2);
    expect($ingredient->allergens->firstWhere('id', $gluten->id)->pivot->state)->toBe('contains');
    expect($ingredient->allergens->firstWhere('id', $milk->id)->pivot->state)->toBe('may_contain');
});

test('syncing allergens replaces the previous pivot set', function () {
    $ingredient = Ingredient::factory()->create();
    $gluten = Allergen::factory()->create(['name' => 'Gluten', 'slug' => 'gluten']);
    $eggs = Allergen::factory()->create(['name' => 'Eggs', 'slug' => 'eggs']);

    $ingredient->allergens()->sync([$gluten->id => ['state' => 'contains']]);
    $ingredient->allergens()->sync([$eggs->id => ['state' => 'contains']]);

    $ingredient->load('allergens');

    expect($ingredient->allergens)->toHaveCount(1);
    expect($ingredient->allergens->first()->id)->toBe($eggs->id);
});
