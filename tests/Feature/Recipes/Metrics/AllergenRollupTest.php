<?php

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use App\Models\User;
use App\Support\Recipes\AllergenRollupService;
use Database\Seeders\AllergenSeeder;

/**
 * Covers ALLG-01, ALLG-02, ALLG-03.
 *
 * RED until Plan 03-03 ships App\Support\Recipes\AllergenRollupService.
 */
beforeEach(function () {
    $this->seed(AllergenSeeder::class);
});

test('recipe allergens are derived from its ingredient allergens', function () {
    $service = app(AllergenRollupService::class);

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $gluten = Allergen::where('slug', 'gluten')->first();
    $milk = Allergen::where('slug', 'milk')->first();

    $flour = Ingredient::factory()->create();
    $flour->allergens()->attach($gluten->id, ['state' => 'contains']);

    $butter = Ingredient::factory()->create();
    $butter->allergens()->attach($milk->id, ['state' => 'contains']);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $flour->id,
        'quantity_g' => '200.000000',
    ]);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $butter->id,
        'quantity_g' => '100.000000',
    ]);

    $result = $service->compute($recipe);

    expect(array_column($result['contains'], 'slug'))->toContain('gluten');
    expect(array_column($result['contains'], 'slug'))->toContain('milk');
});

test('contains and may_contain allergens are distinguished', function () {
    $service = app(AllergenRollupService::class);

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $gluten = Allergen::where('slug', 'gluten')->first();
    $nuts = Allergen::where('slug', 'tree-nuts')->first();

    $certainIngredient = Ingredient::factory()->create();
    $certainIngredient->allergens()->attach($gluten->id, ['state' => 'contains']);

    $traceIngredient = Ingredient::factory()->create();
    $traceIngredient->allergens()->attach($nuts->id, ['state' => 'may_contain']);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $certainIngredient->id,
        'quantity_g' => '100.000000',
    ]);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $traceIngredient->id,
        'quantity_g' => '50.000000',
    ]);

    $result = $service->compute($recipe);

    expect(array_column($result['contains'], 'slug'))->toContain('gluten');
    expect(array_column($result['may_contain'], 'slug'))->toContain('tree-nuts');
    expect(array_column($result['contains'], 'slug'))->not->toContain('tree-nuts');
});

test('allergens roll up through a sub-recipe with contains beating may_contain', function () {
    $service = app(AllergenRollupService::class);

    $user = User::factory()->create();

    // Component recipe with a gluten "may_contain" ingredient
    $component = Recipe::factory()->create(['user_id' => $user->id]);
    $glutenIngredient = Ingredient::factory()->create();
    $gluten = Allergen::where('slug', 'gluten')->first();
    $glutenIngredient->allergens()->attach($gluten->id, ['state' => 'may_contain']);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $component->id,
        'ingredient_id' => $glutenIngredient->id,
        'quantity_g' => '200.000000',
    ]);

    $componentVersion = RecipeVersion::factory()->create([
        'recipe_id' => $component->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
        'cached_allergen_slugs' => ['may_contain' => ['gluten'], 'contains' => []],
    ]);

    // Parent recipe with gluten ingredient at "contains" level
    $parent = Recipe::factory()->create(['user_id' => $user->id]);
    $wheatIngredient = Ingredient::factory()->create();
    $wheatIngredient->allergens()->attach($gluten->id, ['state' => 'contains']);

    RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => $wheatIngredient->id,
        'quantity_g' => '300.000000',
    ]);

    // Sub-recipe line (may_contain gluten from component)
    RecipeIngredientLine::factory()->create([
        'recipe_id' => $parent->id,
        'ingredient_id' => null,
        'sub_recipe_version_id' => $componentVersion->id,
        'quantity_g' => '100.000000',
    ]);

    $result = $service->compute($parent);

    // "contains" from the direct wheat ingredient should beat "may_contain" from sub-recipe
    expect(array_column($result['contains'], 'slug'))->toContain('gluten');
    expect(array_column($result['may_contain'], 'slug'))->not->toContain('gluten');
});
