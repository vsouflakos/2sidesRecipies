<?php

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use Database\Seeders\IngredientCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('all six ingredient tables exist after migration', function () {
    expect(Schema::hasTable('ingredient_categories'))->toBeTrue();
    expect(Schema::hasTable('ingredients'))->toBeTrue();
    expect(Schema::hasTable('ingredient_translations'))->toBeTrue();
    expect(Schema::hasTable('ingredient_allergen'))->toBeTrue();
    expect(Schema::hasTable('ingredient_conversions'))->toBeTrue();
    expect(Schema::hasTable('ingredient_prices'))->toBeTrue();
});

test('ingredients table has a unique index on source and source_id', function () {
    $category = IngredientCategory::factory()->create();

    Ingredient::factory()->create([
        'category_id' => $category->id,
        'source' => 'ciqual',
        'source_id' => 'abc123',
    ]);

    expect(fn () => Ingredient::factory()->create([
        'category_id' => $category->id,
        'source' => 'ciqual',
        'source_id' => 'abc123',
    ]))->toThrow(QueryException::class);
});

test('ingredient_translations has a unique index on ingredient_id and locale', function () {
    $ingredient = Ingredient::factory()->create();

    $ingredient->translations()->create([
        'locale' => 'en',
        'name' => 'Apple',
    ]);

    expect(fn () => $ingredient->translations()->create([
        'locale' => 'en',
        'name' => 'Apple Duplicate',
    ]))->toThrow(QueryException::class);
});

test('ingredient_allergen has a unique index on ingredient_id and allergen_id', function () {
    $ingredient = Ingredient::factory()->create();
    $allergen = Allergen::first() ?? Allergen::factory()->create(['name' => 'Gluten', 'slug' => 'gluten']);

    $ingredient->allergens()->attach($allergen->id, ['state' => 'contains']);

    expect(fn () => $ingredient->allergens()->attach($allergen->id, ['state' => 'may_contain']))
        ->toThrow(QueryException::class);
});

test('an ingredient factory row persists with a category and source', function () {
    $ingredient = Ingredient::factory()->create();

    expect($ingredient->id)->not->toBeNull();
    expect($ingredient->category_id)->not->toBeNull();
    expect($ingredient->source)->not->toBeEmpty();
});

test('the ingredient category tree has 13 roots after seeding', function () {
    $this->seed(IngredientCategorySeeder::class);

    expect(IngredientCategory::whereNull('parent_id')->count())->toBe(13);
});
