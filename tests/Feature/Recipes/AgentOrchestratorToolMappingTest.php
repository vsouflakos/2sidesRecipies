<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\User;
use App\Support\Recipes\AgentOrchestrator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Prism\Prism\ValueObjects\ToolError;

/**
 * Regression tests for AgentOrchestrator::buildTools() parameter name alignment.
 *
 * Prism spreads an associative array keyed by schema parameter names as named
 * arguments into the closure. If a schema name does not match the closure's
 * variable name exactly (case-sensitive), PHP throws "Unknown named parameter $x"
 * which Prism catches and returns as a ToolError. This causes the model to fall
 * back to prose instead of creating a tool_proposal message.
 *
 * These tests call handle() with the real named arguments so that Prism::fake()
 * cannot mask the mismatch — only the real closures are exercised.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('propose_recipe_edit tool creates a tool_proposal message and returns a string', function () {
    $owner = User::factory()->create();
    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $tools = app(AgentOrchestrator::class)->buildTools($recipe, $conversation);

    // Index 0 is propose_recipe_edit
    $editTool = $tools[0];
    expect($editTool->name())->toBe('propose_recipe_edit');

    $result = $editTool->handle(
        action: 'add_ingredient_line',
        summary: 'Add 100g olive oil',
        dataJson: '{"ingredient":"olive oil","amount":100,"unit":"g"}',
    );

    // Must return a string, not a ToolError
    expect($result)->toBeString()
        ->and($result)->not->toBeInstanceOf(ToolError::class);

    // A tool_proposal message must have been persisted
    $proposal = $conversation->messages()->where('role', 'tool_proposal')->first();
    expect($proposal)->not->toBeNull();

    expect($proposal)->toBeInstanceOf(RecipeConversationMessage::class);
    expect($proposal->proposal_state)->toMatchArray([
        'kind' => 'edit',
        'action' => 'add_ingredient_line',
        'status' => 'pending',
    ]);
});

it('propose_recipe_variant tool creates a tool_proposal message and returns a string', function () {
    $owner = User::factory()->create();
    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $tools = app(AgentOrchestrator::class)->buildTools($recipe, $conversation);

    // Index 1 is propose_recipe_variant
    $variantTool = $tools[1];
    expect($variantTool->name())->toBe('propose_recipe_variant');

    $result = $variantTool->handle(
        summary: 'Vegan version: replace butter with coconut oil',
        changesJson: '[{"action":"update_ingredient_line","data":{"from":"butter","to":"coconut oil"}}]',
    );

    // Must return a string, not a ToolError
    expect($result)->toBeString()
        ->and($result)->not->toBeInstanceOf(ToolError::class);

    // A tool_proposal message must have been persisted
    $proposal = $conversation->messages()->where('role', 'tool_proposal')->first();
    expect($proposal)->not->toBeNull();

    expect($proposal)->toBeInstanceOf(RecipeConversationMessage::class);
    expect($proposal->proposal_state)->toMatchArray([
        'kind' => 'variant',
        'status' => 'pending',
    ]);
    expect($proposal->proposal_state['changes'])->not->toBeEmpty();
});

it('search_ingredients tool returns catalog matches within the owner visibility scope', function () {
    $owner = User::factory()->create();
    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $conversation = RecipeConversation::factory()->for($recipe)->create();

    // Official ingredient — visible to every chef.
    $official = Ingredient::factory()->create([
        'user_id' => null,
        'name_cache' => 'Extra Virgin Olive Oil',
    ]);
    // Another chef's private ingredient — must NOT leak into results.
    $stranger = User::factory()->create();
    Ingredient::factory()->create([
        'user_id' => $stranger->id,
        'name_cache' => 'Stranger Olive Oil',
    ]);

    $tools = app(AgentOrchestrator::class)->buildTools($recipe, $conversation);

    $searchTool = collect($tools)->first(fn ($t) => $t->name() === 'search_ingredients');
    expect($searchTool)->not->toBeNull();

    $result = $searchTool->handle(query: 'olive oil');

    expect($result)->toBeString()
        ->and($result)->not->toBeInstanceOf(ToolError::class);

    $matches = collect(json_decode($result, true)['matches']);

    expect($matches->pluck('name'))->toContain('Extra Virgin Olive Oil')
        ->and($matches->pluck('name'))->not->toContain('Stranger Olive Oil')
        ->and($matches->firstWhere('name', 'Extra Virgin Olive Oil')['id'])->toBe($official->id);
});
