<?php

use App\Models\Cuisine;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PUB-04.
 *
 * RED until Plan 06-02 ships LibraryController, routes, and the public resources.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// PUB-04: A guest can GET the library index without authentication (200)
test('a guest can GET the library index without authentication', function () {
    $response = $this->get(route('library.index'));

    $response->assertOk();
});

// PUB-04: Library index lists only published recipes — private recipes are excluded
test('the library index lists only published recipes', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $publishedRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Published Sourdough',
        'is_published' => true,
    ]);

    $privateRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Private Draft',
        'is_published' => false,
    ]);

    $response = $this->get(route('library.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) use ($publishedRecipe) {
        $page->component('library/index');
        $page->has('recipes');
        // Published recipe should be in the list
        $page->where('recipes.data.0.name', $publishedRecipe->name);
    });

    // Private recipe must not appear anywhere in the response content
    $response->assertDontSee($privateRecipe->name);
});

// PUB-04: Library filters by name search
test('library index filters recipes by name search', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Banana Bread',
        'is_published' => true,
    ]);
    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Chocolate Cake',
        'is_published' => true,
    ]);

    $response = $this->get(route('library.index', ['search' => 'Banana']));

    $response->assertOk();
    $response->assertSee('Banana Bread');
    $response->assertDontSee('Chocolate Cake');
});

// PUB-04: Library filters by tag
test('library index filters recipes by tag', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $tag = Tag::factory()->create(['name' => 'Vegan']);

    $taggedRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Vegan Bowl',
        'is_published' => true,
    ]);
    $taggedRecipe->tags()->attach($tag);

    $untaggedRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Meat Stew',
        'is_published' => true,
    ]);

    $response = $this->get(route('library.index', ['tag' => $tag->id]));

    $response->assertOk();
    $response->assertSee('Vegan Bowl');
    $response->assertDontSee('Meat Stew');
});

// PUB-04: Library filters by cuisine
test('library index filters recipes by cuisine', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $cuisine = Cuisine::factory()->create(['name' => 'Italian']);

    $italianRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Pasta Carbonara',
        'is_published' => true,
        'cuisine_id' => $cuisine->id,
    ]);

    $otherRecipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Sushi Roll',
        'is_published' => true,
        'cuisine_id' => null,
    ]);

    $response = $this->get(route('library.index', ['cuisine' => $cuisine->id]));

    $response->assertOk();
    $response->assertSee('Pasta Carbonara');
    $response->assertDontSee('Sushi Roll');
});

// PUB-04: Library filters by difficulty
test('library index filters recipes by difficulty', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Easy Salad',
        'is_published' => true,
        'difficulty' => 'easy',
    ]);
    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Hard Souffle',
        'is_published' => true,
        'difficulty' => 'hard',
    ]);

    $response = $this->get(route('library.index', ['difficulty' => 'easy']));

    $response->assertOk();
    $response->assertSee('Easy Salad');
    $response->assertDontSee('Hard Souffle');
});

// PUB-04: Library filters by max_total_time
test('library index filters recipes by max total time', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Quick Omelette',
        'is_published' => true,
        'prep_time_minutes' => 5,
        'cook_time_minutes' => 5,
    ]);
    Recipe::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Slow Braise',
        'is_published' => true,
        'prep_time_minutes' => 30,
        'cook_time_minutes' => 180,
    ]);

    $response = $this->get(route('library.index', ['max_total_time' => 30]));

    $response->assertOk();
    $response->assertSee('Quick Omelette');
    $response->assertDontSee('Slow Braise');
});

// PUB-04: A guest can view a published recipe page by slug (200)
test('a guest can GET the public recipe page by slug', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'slug' => 'sourdough-bread-abc123',
        'is_published' => true,
    ]);
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $owner->id,
        'snapshot' => ['sections' => []],
    ]);
    $recipe->update(['published_version_id' => $version->id]);

    $response = $this->get(route('library.show', $recipe->slug));

    $response->assertOk();
});

// PUB-04: Public recipe page Inertia props omit cost data, notes, tests, and conversation
test('public recipe page omits cost data and private fields from Inertia props', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'slug' => 'my-recipe-xyz789',
        'is_published' => true,
        'selling_price' => '12.50',
        'notes' => 'Secret chef notes',
    ]);
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $owner->id,
        'snapshot' => ['sections' => []],
        'cached_cost_per_portion' => '3.50',
        'cached_selling_price' => '12.50',
    ]);
    $recipe->update(['published_version_id' => $version->id]);

    $response = $this->get(route('library.show', $recipe->slug));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('library/show');
        $page->has('recipe');

        // Cost data and private fields must NOT be present in the public recipe props
        $page->missing('recipe.cost_per_portion');
        $page->missing('recipe.selling_price');
        $page->missing('recipe.notes');
        $page->missing('recipe.tests');
        $page->missing('recipe.conversation');
    });
});

// PUB-04: Public recipe page Inertia props include the section ingredient lines
test('public recipe page includes ingredient lines with name, quantity, and unit', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $gram = Unit::create([
        'name' => 'gram',
        'symbol' => 'g',
        'type' => 'weight',
        'base_factor' => '1',
    ]);

    $recipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'slug' => 'ingredient-recipe-abc',
        'is_published' => true,
    ]);

    // Snapshot mirrors the builder draft shape: lines carry unit_id, not unit.
    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $owner->id,
        'snapshot' => [
            'sections' => [
                [
                    'name' => 'Main',
                    'order' => 1,
                    'lines' => [
                        [
                            'id' => -1,
                            'ingredient_id' => 9,
                            'name' => 'Flour',
                            'quantity' => '500',
                            'unit_id' => $gram->id,
                            'order' => 0,
                        ],
                    ],
                    'steps' => [
                        ['id' => -2, 'instruction' => 'Mix the flour.', 'order' => 0],
                    ],
                ],
            ],
        ],
    ]);
    $recipe->update(['published_version_id' => $version->id]);

    $response = $this->get(route('library.show', $recipe->slug));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('library/show');
        // The section ingredient line must be present with its three public fields
        $page->where('recipe.sections.0.lines.0.name', 'Flour');
        $page->where('recipe.sections.0.lines.0.quantity', '500');
        $page->where('recipe.sections.0.lines.0.unit', 'g');
        // The section step must also be present
        $page->where('recipe.sections.0.steps.0.instruction', 'Mix the flour.');
    });
});

// PUB-04: GET the show route for an unpublished recipe slug returns 404
test('GET the public recipe page for an unpublished recipe returns 404', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'slug' => 'private-recipe-secret',
        'is_published' => false,
    ]);

    $response = $this->get(route('library.show', $recipe->slug));

    $response->assertNotFound();
});
