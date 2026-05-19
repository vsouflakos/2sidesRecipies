<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRecipeListResource extends JsonResource
{
    /**
     * Flatten the cached_allergen_slugs structure into a flat list of slug strings.
     *
     * cached_allergen_slugs is stored as {contains: string[], may_contain: string[]}.
     * The library card only needs the slugs (to render icons), not the state.
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
     * Extract the per-portion nutrition block from cached nutrition JSON.
     *
     * Prefers the per_portion block; falls back to totals so a single-portion
     * recipe still surfaces values.
     *
     * @return array<string, mixed>
     */
    private function perPortionNutrition(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        if (is_array($raw['per_portion'] ?? null)) {
            return $raw['per_portion'];
        }

        return is_array($raw['totals'] ?? null) ? $raw['totals'] : [];
    }

    /**
     * Pull a single numeric nutrition value as a string, or null when absent.
     */
    private function nutritionValue(array $perPortion, string $key): ?string
    {
        $value = $perPortion[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }

    /**
     * Transform the resource into an array for the public library card.
     *
     * Reads from publishedVersion (not currentVersion). Omits cost_per_portion
     * entirely. Adds author_name for attribution.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->publishedVersion;

        $perPortion = $this->perPortionNutrition($version?->cached_nutrition_json);

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
            'portions' => $this->portions !== null ? (int) $this->portions : null,
            'difficulty' => $this->difficulty?->value,
            'calories_per_portion' => $this->nutritionValue($perPortion, 'energy_kcal'),
            'protein_per_portion' => $this->nutritionValue($perPortion, 'protein_g'),
            'carbs_per_portion' => $this->nutritionValue($perPortion, 'carbs_g'),
            'fat_per_portion' => $this->nutritionValue($perPortion, 'fat_g'),
            'allergen_slugs' => $this->flattenAllergenSlugs($version?->cached_allergen_slugs),
            'author_name' => $this->user?->name,
        ];
    }
}
