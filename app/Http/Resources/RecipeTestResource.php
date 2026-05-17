<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeTestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Serializes the full test shape including nested photos with resolved URLs.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_id' => $this->recipe_id,
            'recipe_version_id' => $this->recipe_version_id,
            'version_number' => $this->recipeVersion?->version_number,
            'type' => $this->type->value,
            'tested_at' => $this->tested_at?->toDateString(),
            'tasting_notes' => $this->tasting_notes,
            'overall_rating' => $this->overall_rating,
            'ratings' => $this->ratings,
            'hypothesis' => $this->hypothesis,
            'outcome_narrative' => $this->outcome_narrative,
            'verdict' => $this->verdict?->value,
            'change_rows' => $this->change_rows,
            'photos' => $this->photos->map(fn ($p) => ['id' => $p->id, 'path' => $p->path, 'url' => $p->url, 'order' => $p->order])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
