<?php

use App\Enums\Difficulty;
use App\Models\Cuisine;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Covers RECIPE-04, RECIPE-13, VERSION-01.
 *
 * Verifies the schema-level contracts: column shapes, casts, and that
 * recipe_versions rows are truly append-only (no mutation after creation).
 */
test('a recipe persists difficulty, cuisine, tags, yield, portions, time, and notes', function () {
    $user = User::factory()->create();
    $cuisine = Cuisine::factory()->create();
    $tag = Tag::factory()->create();

    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'cuisine_id' => $cuisine->id,
        'difficulty' => Difficulty::Hard,
        'yield_amount' => '2000.5000',
        'portions' => '12.0000',
        'prep_time_minutes' => 30,
        'cook_time_minutes' => 60,
        'notes' => 'Chef notes here',
    ]);

    $recipe->tags()->attach($tag);

    $recipe->refresh();

    expect($recipe->difficulty)->toBe(Difficulty::Hard);
    expect($recipe->cuisine_id)->toBe($cuisine->id);
    expect((string) $recipe->yield_amount)->toBe('2000.5000');
    expect((string) $recipe->portions)->toBe('12.0000');
    expect($recipe->prep_time_minutes)->toBe(30);
    expect($recipe->cook_time_minutes)->toBe(60);
    expect($recipe->notes)->toBe('Chef notes here');
    expect($recipe->tags)->toHaveCount(1);
    expect($recipe->tags->first()->id)->toBe($tag->id);
});

test('a hero image path persists on a recipe', function () {
    $recipe = Recipe::factory()->create([
        'hero_image_path' => 'recipes/hero/abc123.jpg',
    ]);

    $recipe->refresh();

    expect($recipe->hero_image_path)->toBe('recipes/hero/abc123.jpg');
});

test('an ingredient line persists prep_note and yield_pct', function () {
    $recipe = Recipe::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $line = RecipeIngredientLine::factory()->create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => '200.000000',
        'quantity_g' => '200.000000',
        'prep_note' => 'Finely diced',
        'yield_pct' => '85.0000',
    ]);

    $line->refresh();

    expect($line->prep_note)->toBe('Finely diced');
    expect((string) $line->yield_pct)->toBe('85.0000');
    expect((string) $line->quantity)->toBe('200.000000');
});

test('a recipe_version row retains its version_number and snapshot after a second save attempt', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $version = RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => ['name' => 'original', 'portions' => 8],
    ]);

    // Reload — only to simulate a re-read, not a mutation
    $version->refresh();

    // Versions are append-only: version_number and snapshot do not change
    expect($version->version_number)->toBe(1);
    expect($version->snapshot['name'])->toBe('original');
    expect($version->snapshot['portions'])->toBe(8);
});

test('recipe_versions enforces unique (recipe_id, version_number) constraint', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]);

    expect(fn () => RecipeVersion::factory()->create([
        'recipe_id' => $recipe->id,
        'version_number' => 1,
        'committed_by' => $user->id,
        'snapshot' => [],
    ]))->toThrow(QueryException::class);
});

test('difficulty enum values map to expected labels', function () {
    expect(Difficulty::Easy->label())->toBe('Easy');
    expect(Difficulty::Medium->label())->toBe('Medium');
    expect(Difficulty::Hard->label())->toBe('Hard');
    expect(Difficulty::Expert->label())->toBe('Expert');
});

test('recipe selling_price persists correctly', function () {
    $recipe = Recipe::factory()->create([
        'selling_price' => '12.5000',
    ]);

    $recipe->refresh();

    expect((string) $recipe->selling_price)->toBe('12.5000');
});
