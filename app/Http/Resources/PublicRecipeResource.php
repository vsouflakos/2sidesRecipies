<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRecipeResource extends JsonResource
{
    /**
     * Flatten the cached_allergen_slugs structure into a flat list of slug strings.
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
     * Map a snapshot section to its public shape.
     *
     * Extracts only the name, lines (with quantity/unit/name), and steps
     * (with order and instruction). Never includes cost fields.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function mapSection(array $section): array
    {
        $lines = array_map(function (array $line): array {
            return [
                'quantity' => $line['quantity'] ?? null,
                'unit' => $line['unit'] ?? null,
                'name' => $line['name'] ?? null,
            ];
        }, $section['lines'] ?? []);

        $steps = array_map(function (array $step): array {
            return [
                'order' => $step['order'] ?? null,
                'instruction' => $step['instruction'] ?? null,
            ];
        }, $section['steps'] ?? []);

        return [
            'name' => $section['name'] ?? null,
            'lines' => $lines,
            'steps' => $steps,
        ];
    }

    /**
     * Transform the resource into an array for the public recipe show page.
     *
     * Reads nutrition and allergens from the publishedVersion cached columns only.
     * NEVER includes cost_per_portion, cached_cost_*, selling_price, notes, tests,
     * or conversation data — these are private chef fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->publishedVersion;

        $totalTime = null;
        $prep = $this->prep_time_minutes;
        $cook = $this->cook_time_minutes;

        if ($prep !== null || $cook !== null) {
            $totalTime = ((int) ($prep ?? 0)) + ((int) ($cook ?? 0));
        }

        $snapshot = $version?->snapshot ?? [];
        $sections = array_map(
            fn (array $s) => $this->mapSection($s),
            $snapshot['sections'] ?? []
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hero_image_path' => $this->hero_image_path,
            'cuisine' => $this->cuisine?->name,
            'difficulty' => $this->difficulty?->value,
            'total_time' => $totalTime,
            'author_name' => $this->user?->name,
            'published_at' => $this->published_at?->toIso8601String(),
            'sections' => $sections,
            'nutrition' => $version?->cached_nutrition_json,
            'allergen_slugs' => $this->flattenAllergenSlugs($version?->cached_allergen_slugs),
        ];
    }
}
