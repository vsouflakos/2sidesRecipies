<?php

use App\Jobs\GenerateAgentReplyJob;
use App\Models\Recipe;
use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Support\Recipes\AgentOrchestrator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;

/**
 * Feature suite for the recipe AI chat (AI-01..AI-07).
 *
 * The agentic turn runs in {@see GenerateAgentReplyJob} on the queue rather
 * than on the HTTP request — these tests cover dispatch, job execution, the
 * failure path, and the polling status surfaced by the conversation endpoint.
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

it('queues an agent reply job when a chat message is posted', function () {
    // AI-01 — posting a message persists the user message and dispatches the job.
    Queue::fake();

    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $response = $this->actingAs($owner)
        ->post(route('recipes.conversation.store', $recipe), [
            'message' => 'How do I improve this?',
        ]);

    $response->assertAccepted()
        ->assertJson(['status' => 'queued']);

    Queue::assertPushed(GenerateAgentReplyJob::class);

    $conversation = RecipeConversation::where('recipe_id', $recipe->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->agent_status)->toBe('generating');
    expect($conversation->messages()->where('role', 'user')->count())->toBe(1);
});

it('persists the assistant message and returns to idle when the job runs', function () {
    // AI-03 — running the job consumes the agent stream and persists the reply.
    // tool_proposal persistence is exercised in AgentOrchestratorToolMappingTest;
    // the faked Prism provider emits text only, so this asserts the text path.
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $conversation = RecipeConversation::factory()->for($recipe)->create();
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'How do I improve this?',
    ]);

    (new GenerateAgentReplyJob($recipe, $conversation))
        ->handle(app(AgentOrchestrator::class));

    $conversation->refresh();

    expect($conversation->agent_status)->toBe('idle');
    expect($conversation->agent_error)->toBeNull();

    $assistant = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistant)->toHaveCount(1);
    expect($assistant->first()->content)->toBe('Consider reducing the butter by 20%.');
});

it('completes a queued chat turn end to end', function () {
    // The queue connection is `sync` under test, so dispatching runs the job
    // inline — proving the route, controller, and job are wired together.
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->post(route('recipes.conversation.store', $recipe), [
            'message' => 'How do I improve this?',
        ])
        ->assertAccepted();

    $conversation = RecipeConversation::where('recipe_id', $recipe->id)->first();
    expect($conversation->agent_status)->toBe('idle');
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(1);
});

it('marks the conversation failed when the agent job throws', function () {
    // Error path: a failing turn sets agent_status='failed' with the reason and
    // never persists a partial assistant message.
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $conversation = RecipeConversation::factory()->for($recipe)->create();

    $this->mock(AgentOrchestrator::class, function ($mock) {
        $mock->shouldReceive('buildStream')
            ->andThrow(new RuntimeException('Provider unavailable'));
    });

    (new GenerateAgentReplyJob($recipe, $conversation))
        ->handle(app(AgentOrchestrator::class));

    $conversation->refresh();

    expect($conversation->agent_status)->toBe('failed');
    expect($conversation->agent_error)->toContain('Provider unavailable');
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(0);
});

it('rejects a second chat message while a turn is in progress', function () {
    // One turn per conversation — a message posted while generating is rejected.
    Queue::fake();

    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    $conversation = RecipeConversation::factory()->for($recipe)->create([
        'agent_status' => 'generating',
    ]);

    $this->actingAs($owner)
        ->post(route('recipes.conversation.store', $recipe), [
            'message' => 'Are you still there?',
        ])
        ->assertStatus(409);

    Queue::assertNotPushed(GenerateAgentReplyJob::class);
    expect($conversation->messages()->count())->toBe(0);
});

it('returns the agent status from the conversation endpoint', function () {
    // The polling client reads agent_status/agent_error from show().
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $recipe = Recipe::factory()->for($owner, 'user')->create();
    RecipeConversation::factory()->for($recipe)->create([
        'agent_status' => 'failed',
        'agent_error' => 'Provider unavailable',
    ]);

    $this->actingAs($owner)
        ->get(route('recipes.conversation.show', $recipe))
        ->assertOk()
        ->assertJson([
            'agent_status' => 'failed',
            'agent_error' => 'Provider unavailable',
        ]);
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
        ->post(route('recipes.conversation.store', $recipe), [
            'message' => 'Can I access this?',
        ])
        ->assertForbidden();
});
