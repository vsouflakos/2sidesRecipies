<?php

namespace Database\Factories;

use App\Models\RecipeConversation;
use App\Models\RecipeConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeConversationMessage>
 */
class RecipeConversationMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_conversation_id' => RecipeConversation::factory(),
            'role' => 'user',
            'content' => $this->faker->sentence(),
            'proposal_state' => null,
        ];
    }

    /**
     * Indicate the message is a tool proposal.
     */
    public function proposal(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tool_proposal',
            'proposal_state' => [
                'action' => 'update_metadata',
                'data' => [],
                'status' => 'pending',
                'summary' => 'Reduce sugar',
                'kind' => 'edit',
            ],
        ]);
    }
}
