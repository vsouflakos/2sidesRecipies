<?php

use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;

/**
 * Wave 0 RED test suite covering AI-01..AI-07.
 * Tests reference routes/services built in plans 05-02..05-04 — intentionally RED until then.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'ai.provider' => 'anthropic',
        'ai.model' => 'claude-3-5-sonnet-20241022',
    ]);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Consider reducing the butter by 20%.')
            ->withFinishReason(FinishReason::Stop),
    ]);
});

it('streams an assistant response to a chat message', function () {
    // AI-01, AI-03 — POST to stream route, assistant message persisted
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $response = $this->actingAs($owner)
        ->post(route('recipes.conversation.stream', $recipe), [
            'message' => 'How do I improve this?',
        ]);

    $response->assertOk();

    $conversation = RecipeConversation::where('recipe_id', $recipe->id)->first();
    expect($conversation)->not->toBeNull();
    expect(
        $conversation->messages()->where('role', 'assistant')->count()
    )->toBe(1);
});

it('hides the AI feature when no provider is configured', function () {
    // AI-06 — ai_enabled prop is false when provider is empty
    config(['ai.provider' => '']);

    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ai_enabled', false));
});

it('exposes the AI feature when a provider is configured', function () {
    // AI-06 — ai_enabled prop is true when both provider and model are set
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ai_enabled', true));
});

it('applies an accepted suggestion to the working draft', function () {
    // AI-04 — applying a proposal increments edit_sequence by 1
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $draft = RecipeDraft::factory()->for($recipe)->for($owner, 'user')->create([
        'edit_sequence' => 0,
    ]);

    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $message = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->proposal()
        ->create();

    $this->actingAs($owner)
        ->post(route('recipes.conversation.apply', [$recipe, $message]))
        ->assertOk();

    expect($draft->fresh()->edit_sequence)->toBe(1);
});

it('rejects an invalid suggestion and records a failed proposal state', function () {
    // AI-07 — invalid proposal data leaves draft unchanged and marks proposal as failed
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $draft = RecipeDraft::factory()->for($recipe)->for($owner, 'user')->create([
        'edit_sequence' => 0,
    ]);

    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $message = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->proposal()
        ->create([
            'proposal_state' => [
                'action' => 'update_metadata',
                'data' => ['yield_amount' => 'not-a-number'],
                'status' => 'pending',
                'summary' => 'Invalid yield',
                'kind' => 'edit',
            ],
        ]);

    $this->actingAs($owner)
        ->post(route('recipes.conversation.apply', [$recipe, $message]))
        ->assertUnprocessable();

    expect($draft->fresh()->edit_sequence)->toBe(0);
    expect($message->fresh()->proposal_state['status'])->toBe('failed');
});

it('creates a recipe variant as a new independent recipe', function () {
    // AI-05 — variant proposal creates a new Recipe row for the same user
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $message = RecipeConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->proposal()
        ->create([
            'proposal_state' => [
                'action' => 'create_variant',
                'data' => ['name' => 'Low-sugar variant'],
                'status' => 'pending',
                'summary' => 'Reduced sugar variant',
                'kind' => 'variant',
            ],
        ]);

    $recipeCountBefore = Recipe::where('user_id', $owner->id)->count();

    $this->actingAs($owner)
        ->post(route('recipes.conversation.variant', [$recipe, $message]))
        ->assertOk();

    expect(Recipe::where('user_id', $owner->id)->count())->toBe($recipeCountBefore + 1);
});

it('forbids accessing another user conversation', function () {
    // ACCESS — non-owner gets 403
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $other = User::factory()->create();
    $other->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $this->actingAs($other)
        ->post(route('recipes.conversation.stream', $recipe), [
            'message' => 'Can I access this?',
        ])
        ->assertForbidden();
});
