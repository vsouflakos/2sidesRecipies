<?php

use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\RecipeTest;
use App\Models\RecipeVersion;
use App\Models\User;
use App\Support\Recipes\AgentContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Wave 0 RED unit tests for AgentContextBuilder (AI-02).
 * References AgentContextBuilder built in plan 05-02 — intentionally RED until then.
 */
it('includes the recipe draft, chef notes, and all test feedback in the system prompt', function () {
    // AI-02 — system prompt serializes draft, notes, and all test verdicts
    $owner = User::factory()->create();

    $recipe = Recipe::factory()->for($owner, 'user')->create([
        'notes' => 'Use room-temperature butter for better texture.',
    ]);

    $version = RecipeVersion::factory()->for($recipe)->create([
        'version_number' => 1,
        'committed_by' => $owner->id,
    ]);

    RecipeDraft::factory()->for($recipe)->for($owner, 'user')->create();

    RecipeTest::factory()->for($recipe)->for($version, 'recipeVersion')->for($owner, 'user')->create([
        'verdict' => 'worked',
        'tasting_notes' => 'Excellent crumb structure.',
    ]);

    RecipeTest::factory()->for($recipe)->for($version, 'recipeVersion')->for($owner, 'user')->create([
        'verdict' => 'didnt_work',
        'tasting_notes' => 'Too dense, needs more leavening.',
    ]);

    $systemPrompt = app(AgentContextBuilder::class)->buildSystemPrompt($recipe);

    expect($systemPrompt)->toContain('room-temperature butter for better texture');
    expect($systemPrompt)->toContain('worked');
    expect($systemPrompt)->toContain('didnt_work');
});

it('rebuilds messages from persisted conversation history', function () {
    // AI-02 — buildMessages() returns one entry per user/assistant message in order
    $owner = User::factory()->create();

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $first = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->create(['role' => 'user', 'content' => 'How can I improve the dough?']);

    $second = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->create(['role' => 'assistant', 'content' => 'Consider reducing butter by 20%.']);

    $messages = app(AgentContextBuilder::class)->buildMessages($recipe, $conversation);

    expect($messages)->toHaveCount(2);
    expect($messages[0])->toMatchArray(['role' => 'user']);
    expect($messages[1])->toMatchArray(['role' => 'assistant']);
});
