<?php

namespace App\Http\Resources;

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Used for both queue rows (index) and the per-submission review payload (show).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        /** @var Ingredient $ingredient */
        $ingredient = $this->ingredient;

        return [
            'id' => $this->id,
            'submission_number' => $this->submission_number,
            'status' => $this->status instanceof SubmissionStatus
                ? $this->status->value
                : $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'submitter' => [
                'name' => $this->submittedBy?->name,
            ],
            'ingredient' => $ingredient ? [
                'id' => $ingredient->id,
                'name' => $ingredient->nameFor($locale),
                'category' => $ingredient->category ? [
                    'name' => $ingredient->category->name,
                    'parent' => $ingredient->category->parent?->name,
                ] : null,
            ] : null,
            'completeness' => $ingredient ? [
                'nutrition_filled' => $ingredient->energy_kcal !== null
                    && $ingredient->protein_g !== null
                    && $ingredient->fat_g !== null
                    && $ingredient->carbs_g !== null,
                'allergens_set' => $ingredient->allergens->isNotEmpty(),
                'conversions_added' => $ingredient->conversions->isNotEmpty(),
            ] : null,
            'prior_rejections' => $ingredient
                ? $ingredient->submissions
                    ->where('status', SubmissionStatus::Rejected)
                    ->sortBy('submitted_at')
                    ->map(fn ($s) => [
                        'notes' => $s->notes,
                        'reviewed_at' => $s->reviewed_at?->toISOString(),
                        'reviewer' => $s->reviewedBy?->name,
                    ])
                    ->values()
                : [],
        ];
    }
}
