<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeListResource extends JsonResource
{
    /**
     * Transform the resource into an array for the recipe list card.
     *
     * Cost data reads from the current_version's cached_cost_per_portion column —
     * never recomputed in the resource (Warning 4: authoritative cached value only).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        // Safely extract calories_per_portion from the cached nutrition JSON
        $nutritionJson = $version?->cached_nutrition_json ?? [];
        $caloriesPerPortion = $nutritionJson['per_portion']['energy_kcal']
            ?? $nutritionJson['totals']['energy_kcal']
            ?? null;

        $totalTime = null;
        $prep = $this->prep_time_minutes;
        $cook = $this->cook_time_minutes;

        if ($prep !== null || $cook !== null) {
            $totalTime = ((int) ($prep ?? 0)) + ((int) ($cook ?? 0));
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hero_image_path' => $this->hero_image_path,
            'cuisine' => $this->cuisine?->name,
            'total_time' => $totalTime,
            'difficulty' => $this->difficulty?->value,
            'cost_per_portion' => $version?->cached_cost_per_portion !== null
                ? (string) $version->cached_cost_per_portion
                : null,
            'calories_per_portion' => $caloriesPerPortion !== null
                ? (string) $caloriesPerPortion
                : null,
            'allergen_slugs' => $version?->cached_allergen_slugs ?? [],
        ];
    }
}
