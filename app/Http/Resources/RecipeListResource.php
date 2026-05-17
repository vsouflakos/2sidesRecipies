<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeListResource extends JsonResource
{
    /**
     * Flatten the cached_allergen_slugs structure into a flat list of slug strings.
     *
     * cached_allergen_slugs is stored as {contains: string[], may_contain: string[]}.
     * The list page card only needs the slugs (to render icons), not the state.
     *
     * @return list<string>
     */
    private function flattenAllergenSlugs(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        // Flat array of plain slug strings — return as-is.
        if (isset($raw[0]) || empty($raw)) {
            return array_values($raw);
        }

        // Structured {contains: [...], may_contain: [...]} — merge into a flat unique list.
        $contains = is_array($raw['contains'] ?? null) ? $raw['contains'] : [];
        $mayContain = is_array($raw['may_contain'] ?? null) ? $raw['may_contain'] : [];

        return array_values(array_unique(array_merge($contains, $mayContain)));
    }

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
            'allergen_slugs' => $this->flattenAllergenSlugs($version?->cached_allergen_slugs),
            'is_published' => (bool) $this->is_published,
        ];
    }
}
