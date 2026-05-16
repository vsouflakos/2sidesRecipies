<?php

namespace App\Concerns;

use App\Enums\Difficulty;
use Illuminate\Validation\Rules\Enum;

trait RecipeValidationRules
{
    /**
     * Core recipe metadata validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function recipeMetadataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'yield_amount' => ['nullable', 'numeric', 'min:0'],
            'portions' => ['nullable', 'integer', 'min:1'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0'],
            'cook_time_minutes' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', new Enum(Difficulty::class)],
            'cuisine_id' => ['nullable', 'exists:cuisines,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'notes' => ['nullable', 'string'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Draft data JSON-structure validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function recipeDraftDataRules(): array
    {
        return [
            'action' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'expected_sequence' => ['nullable', 'integer', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
