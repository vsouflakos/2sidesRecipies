<?php

use App\Models\Ingredient;
use App\Models\IngredientTranslation;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\Unit;
use App\Models\User;
use App\Support\Recipes\SuggestionApplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Validation\ValidationException;

/**
 * Covers AI-04 / AI-07: an agent edit proposal must be applied as a targeted
 * MUTATION of the working draft — never a full replacement with the AI's
 * partial delta object (the data-corruption bug fixed in plan 05-04).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(UnitSeeder::class);
});

/**
 * Build a realistic two-section full draft, matching the recipe builder shape.
 *
 * @return array<string, mixed>
 */
function realisticDraft(): array
{
    return [
        'name' => 'Lemon Tart',
        'yield_amount' => '1000.0000',
        'portions' => 8,
        'prep_time_minutes' => 30,
        'cook_time_minutes' => 25,
        'difficulty' => 'medium',
        'cuisine_id' => null,
        'notes' => 'Chill before serving.',
        'selling_price' => null,
        'sections' => [
            [
                'id' => -1,
                'name' => 'Pastry',
                'order' => 1,
                'lines' => [
                    [
                        'id' => -1, 'ingredient_id' => null, 'sub_recipe_version_id' => null,
                        'name' => 'Flour', 'quantity' => '250.000000', 'unit_id' => 1,
                        'prep_note' => null, 'yield_pct' => '100', 'is_flour_base' => true, 'order' => 0,
                    ],
                    [
                        'id' => -2, 'ingredient_id' => null, 'sub_recipe_version_id' => null,
                        'name' => 'Butter', 'quantity' => '125.000000', 'unit_id' => 1,
                        'prep_note' => 'cold', 'yield_pct' => '100', 'is_flour_base' => false, 'order' => 1,
                    ],
                ],
                'steps' => [
                    ['id' => -1, 'instruction' => 'Rub butter into flour.', 'order' => 0, 'step_image_path' => null],
                ],
            ],
            [
                'id' => -2,
                'name' => 'Filling',
                'order' => 2,
                'lines' => [
                    [
                        'id' => -3, 'ingredient_id' => null, 'sub_recipe_version_id' => null,
                        'name' => 'Salt', 'quantity' => '10.000000', 'unit_id' => 1,
                        'prep_note' => null, 'yield_pct' => '100', 'is_flour_base' => false, 'order' => 0,
                    ],
                ],
                'steps' => [],
            ],
        ],
    ];
}

/**
 * Create an owner + recipe + draft + a pending edit proposal in one step.
 *
 * @param  array<string, mixed>  $proposalState
 * @return array{0: User, 1: Recipe, 2: RecipeDraft, 3: RecipeConversationMessage}
 */
function makeEditProposal(array $proposalState): array
{
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $draft = RecipeDraft::factory()->for($recipe)->for($owner, 'user')->create([
        'data' => realisticDraft(),
        'edit_sequence' => 5,
    ]);

    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $message = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->proposal()
        ->create(['proposal_state' => array_merge([
            'kind' => 'edit',
            'status' => 'pending',
            'summary' => 'test change',
        ], $proposalState)]);

    return [$owner, $recipe, $draft, $message];
}

it('update_ingredient_line mutates only the targeted line', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_ingredient_line',
        'data' => ['id' => -3, 'quantity' => 5],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    // Targeted line changed.
    expect($data['sections'][1]['lines'][0]['quantity'])->toBe('5');
    // Everything else intact.
    expect($data['name'])->toBe('Lemon Tart')
        ->and($data['sections'])->toHaveCount(2)
        ->and($data['sections'][0]['lines'])->toHaveCount(2)
        ->and($data['sections'][0]['lines'][0]['quantity'])->toBe('250.000000')
        ->and($data['sections'][0]['lines'][1]['name'])->toBe('Butter')
        ->and($data['sections'][1]['lines'][0]['name'])->toBe('Salt');

    expect($draft->fresh()->edit_sequence)->toBe(6);
    expect($message->fresh()->proposal_state['status'])->toBe('applied');
});

it('update_ingredient_line accepts a line_id alias', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_ingredient_line',
        'data' => ['line_id' => -1, 'quantity' => '300'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    expect($draft->fresh()->data['sections'][0]['lines'][0]['quantity'])->toBe('300');
});

it('add_ingredient_line appends to the matched section without touching others', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'add_ingredient_line',
        'data' => ['section_name' => 'filling', 'name' => 'Lemon Juice', 'quantity' => 60, 'unit' => 'ml'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;
    $fillingLines = $data['sections'][1]['lines'];

    expect($fillingLines)->toHaveCount(2)
        ->and($fillingLines[1]['name'])->toBe('Lemon Juice')
        ->and($fillingLines[1]['quantity'])->toBe('60')
        ->and($fillingLines[1]['unit_id'])->toBe(Unit::where('symbol', 'ml')->value('id'))
        ->and($fillingLines[1]['id'])->toBeLessThan(0);

    // Pastry section untouched.
    expect($data['sections'][0]['lines'])->toHaveCount(2);
    expect($draft->fresh()->edit_sequence)->toBe(6);
});

it('remove_ingredient_line drops only the targeted line', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'remove_ingredient_line',
        'data' => ['id' => -2],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    expect($data['sections'][0]['lines'])->toHaveCount(1)
        ->and($data['sections'][0]['lines'][0]['name'])->toBe('Flour')
        ->and($data['sections'][1]['lines'])->toHaveCount(1);
});

it('update_metadata merges only the provided fields', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_metadata',
        'data' => ['portions' => 12, 'notes' => 'Updated note'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    expect($data['portions'])->toBe(12)
        ->and($data['notes'])->toBe('Updated note')
        ->and($data['name'])->toBe('Lemon Tart')
        ->and($data['sections'])->toHaveCount(2);
});

it('add_step appends a step to the matched section', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'add_step',
        'data' => ['section_name' => 'Filling', 'instruction' => 'Whisk the filling.'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    expect($data['sections'][1]['steps'])->toHaveCount(1)
        ->and($data['sections'][1]['steps'][0]['instruction'])->toBe('Whisk the filling.')
        ->and($data['sections'][0]['steps'])->toHaveCount(1);
});

it('update_step rewrites only the targeted step instruction', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_step',
        'data' => ['step_id' => -1, 'instruction' => 'Rub cold butter into the flour quickly.'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    expect($draft->fresh()->data['sections'][0]['steps'][0]['instruction'])
        ->toBe('Rub cold butter into the flour quickly.');
});

it('update_section renames only the matched section', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_section',
        'data' => ['section_id' => -2, 'name' => 'Lemon Filling'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    expect($data['sections'][1]['name'])->toBe('Lemon Filling')
        ->and($data['sections'][0]['name'])->toBe('Pastry');
});

it('apply_scale multiplies every line quantity by the factor', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'apply_scale',
        'data' => ['factor' => 2],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    expect($data['sections'][0]['lines'][0]['quantity'])->toBe('500.000000')
        ->and($data['sections'][0]['lines'][1]['quantity'])->toBe('250.000000')
        ->and($data['sections'][1]['lines'][0]['quantity'])->toBe('20.000000')
        ->and($data['name'])->toBe('Lemon Tart');
});

it('resolves an ingredient_name to its ingredient_id when one exists', function () {
    $ingredient = Ingredient::factory()->create(['name_cache' => 'Caster Sugar']);
    IngredientTranslation::create([
        'ingredient_id' => $ingredient->id,
        'locale' => 'en',
        'name' => 'Caster Sugar',
    ]);

    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'add_ingredient_line',
        'data' => ['section_name' => 'Filling', 'ingredient_name' => 'Caster Sugar', 'quantity' => 100, 'unit' => 'g'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $newLine = $draft->fresh()->data['sections'][1]['lines'][1];

    expect($newLine['ingredient_id'])->toBe($ingredient->id)
        ->and($newLine['name'])->toBe('Caster Sugar');
});

it('fails gracefully when the targeted line id does not exist', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_ingredient_line',
        'data' => ['id' => -999, 'quantity' => 1],
    ]);

    $original = $draft->data;

    try {
        app(SuggestionApplier::class)->apply($recipe->fresh(), $message);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        // expected
    }

    expect($draft->fresh()->data)->toEqual($original)
        ->and($draft->fresh()->edit_sequence)->toBe(5)
        ->and($message->fresh()->proposal_state['status'])->toBe('failed');
    expect($message->fresh()->proposal_state['failure_reason'])->toContain('-999');
});

it('fails gracefully when the section name cannot be matched', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'add_ingredient_line',
        'data' => ['section_name' => 'Nonexistent', 'name' => 'X', 'quantity' => 1, 'unit' => 'g'],
    ]);

    $original = $draft->data;

    try {
        app(SuggestionApplier::class)->apply($recipe->fresh(), $message);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        // expected
    }

    expect($draft->fresh()->data)->toEqual($original)
        ->and($draft->fresh()->edit_sequence)->toBe(5)
        ->and($message->fresh()->proposal_state['status'])->toBe('failed');
});

it('fails gracefully on an unknown action and leaves the draft untouched', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'demolish_recipe',
        'data' => ['id' => -1],
    ]);

    $original = $draft->data;

    try {
        app(SuggestionApplier::class)->apply($recipe->fresh(), $message);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        // expected
    }

    expect($draft->fresh()->data)->toEqual($original)
        ->and($draft->fresh()->edit_sequence)->toBe(5)
        ->and($message->fresh()->proposal_state['status'])->toBe('failed');
});

it('fails gracefully when a unit symbol cannot be resolved', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'add_ingredient_line',
        'data' => ['section_name' => 'Filling', 'name' => 'Mystery', 'quantity' => 1, 'unit' => 'furlongs'],
    ]);

    $original = $draft->data;

    try {
        app(SuggestionApplier::class)->apply($recipe->fresh(), $message);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        // expected
    }

    expect($draft->fresh()->data)->toEqual($original)
        ->and($message->fresh()->proposal_state['status'])->toBe('failed');
});

it('does NOT replace the draft with the partial delta object (regression)', function () {
    // This is the exact corruption scenario: an update_ingredient_line delta
    // of {id, quantity} must never become the whole draft.
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_ingredient_line',
        'data' => ['id' => -1, 'quantity' => '999'],
    ]);

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    $data = $draft->fresh()->data;

    // The draft must still be a full draft, not the 2-key delta.
    expect($data)->toHaveKeys(['name', 'sections', 'portions'])
        ->and(array_keys($data))->not->toBe(['id', 'quantity']);
});

it('records exactly one draft edit per successful apply', function () {
    [$owner, $recipe, $draft, $message] = makeEditProposal([
        'action' => 'update_metadata',
        'data' => ['portions' => 10],
    ]);

    $editsBefore = $draft->edits()->count();

    app(SuggestionApplier::class)->apply($recipe->fresh(), $message);

    expect($draft->edits()->count())->toBe($editsBefore + 1)
        ->and($draft->fresh()->edit_sequence)->toBe(6);
});
