<?php

use App\Enums\Difficulty;
use App\Models\Allergen;
use App\Models\Cuisine;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers RECIPE-12.
 *
 * RED until Plan 03-08 ships the recipe search/list controller and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('GET /recipes filters by tag', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $tag = Tag::factory()->create(['name' => 'gluten-free']);
    $taggedRecipe = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Tagged Recipe']);
    $taggedRecipe->tags()->attach($tag);

    $otherRecipe = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Other Recipe']);

    $response = $this->actingAs($user)->get('/recipes?tag='.$tag->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Tagged Recipe')
    );
});

test('GET /recipes filters by cuisine', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $greek = Cuisine::factory()->create(['name' => 'Greek', 'slug' => 'greek']);
    $italian = Cuisine::factory()->create(['name' => 'Italian', 'slug' => 'italian']);

    $greekRecipe = Recipe::factory()->create(['user_id' => $user->id, 'cuisine_id' => $greek->id, 'name' => 'Greek Salad']);
    $italianRecipe = Recipe::factory()->create(['user_id' => $user->id, 'cuisine_id' => $italian->id, 'name' => 'Pasta']);

    $response = $this->actingAs($user)->get('/recipes?cuisine='.$greek->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Greek Salad')
    );
});

test('GET /recipes filters by allergen', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $allergen = Allergen::where('slug', 'gluten')->first()
        ?? Allergen::factory()->create(['slug' => 'gluten', 'name' => 'Gluten']);

    $response = $this->actingAs($user)->get('/recipes?allergen=gluten');

    $response->assertSuccessful();
});

test('GET /recipes filters by ingredient', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $ingredient = Ingredient::factory()->create();

    $response = $this->actingAs($user)->get('/recipes?ingredient='.$ingredient->id);

    $response->assertSuccessful();
});

test('GET /recipes filters by difficulty', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $easyRecipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'difficulty' => Difficulty::Easy,
        'name' => 'Easy Dish',
    ]);

    $hardRecipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'difficulty' => Difficulty::Hard,
        'name' => 'Hard Dish',
    ]);

    $response = $this->actingAs($user)->get('/recipes?difficulty=easy');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Easy Dish')
    );
});

test('GET /recipes returns allergen_slugs as a flat array for recipes without a version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    // Recipe with no current version (current_version_id is null)
    Recipe::factory()->create(['user_id' => $user->id, 'name' => 'No Version Recipe']);

    $response = $this->actingAs($user)->get('/recipes');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.allergen_slugs', [])
    );
});

test('GET /recipes returns allergen_slugs as a flat array for recipes with a structured version', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Allergen Recipe']);

    // Create a version with the {contains, may_contain} structure stored in DB
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'committed_by' => $user->id,
        'cached_allergen_slugs' => ['contains' => ['gluten', 'eggs'], 'may_contain' => ['nuts']],
    ]);

    $recipe->current_version_id = $version->id;
    $recipe->save();

    $response = $this->actingAs($user)->get('/recipes');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->has('recipes.data.0.allergen_slugs', 3)
    );
});

// --- Bug A regression: GET /search/components must return 200 JSON array even when no ingredients match ---

test('GET /search/components returns 200 JSON array when query matches only a recipe (zero ingredients)', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    // Create a recipe whose name will match but no ingredients will match the query
    Recipe::factory()->create(['user_id' => $user->id, 'name' => 'Zucchini Fritters']);

    $response = $this->actingAs($user)->getJson('/search/components?q=Zucchini');

    $response->assertOk();
    $response->assertJsonIsArray();
    // At least the recipe should be in results
    $data = $response->json();
    expect($data)->toBeArray();
    $types = array_column($data, 'type');
    expect(in_array('recipe', $types, true))->toBeTrue();
});

test('GET /search/components returns 200 JSON array when query matches nothing at all', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($user)->getJson('/search/components?q=zzznomatchzzz');

    $response->assertOk();
    $response->assertJsonIsArray();
    expect($response->json())->toBeArray()->toBeEmpty();
});

test('GET /search/components returns 200 JSON array for empty query', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($user)->getJson('/search/components?q=');

    $response->assertOk();
    $response->assertJsonIsArray();
});

// --- Bug B regression: allergen_slugs on index page is always an array ---

test('GET /recipes index returns allergen_slugs as a flat array regardless of cached_allergen_slugs format', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    // Recipe with null current_version → allergen_slugs must be []
    Recipe::factory()->create(['user_id' => $user->id, 'name' => 'No Version']);

    $response = $this->actingAs($user)->get('/recipes');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.allergen_slugs', [])
    );
});

test('GET /recipes filters by time range', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $quickRecipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'prep_time_minutes' => 10,
        'cook_time_minutes' => 20,
        'name' => 'Quick Dish',
    ]);

    $slowRecipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'prep_time_minutes' => 60,
        'cook_time_minutes' => 120,
        'name' => 'Slow Dish',
    ]);

    $response = $this->actingAs($user)->get('/recipes?max_total_time=60');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Quick Dish')
    );
});

// Recipe list cards expose per-portion nutrition + serving metrics.
test('GET /recipes index exposes per-portion nutrition and serving metrics on each card', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'name' => 'Macro Recipe',
        'portions' => 4,
        'prep_time_minutes' => 15,
        'cook_time_minutes' => 30,
    ]);

    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'committed_by' => $user->id,
        'cached_nutrition_json' => [
            'per_portion' => [
                'energy_kcal' => '320.0000',
                'protein_g' => '12.0000',
                'carbs_g' => '34.0000',
                'fat_g' => '8.0000',
            ],
        ],
    ]);

    $recipe->current_version_id = $version->id;
    $recipe->save();

    $response = $this->actingAs($user)->get('/recipes');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.total_time', 45)
        ->where('recipes.data.0.portions', 4)
        ->where('recipes.data.0.calories_per_portion', '320.0000')
        ->where('recipes.data.0.protein_per_portion', '12.0000')
        ->where('recipes.data.0.carbs_per_portion', '34.0000')
        ->where('recipes.data.0.fat_per_portion', '8.0000')
    );
});
