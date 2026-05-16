<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeBuilderResource extends JsonResource
{
    /**
     * Transform the resource into the full builder state array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hero_image_path' => $this->hero_image_path,
            'yield_amount' => $this->yield_amount !== null ? (string) $this->yield_amount : null,
            'portions' => $this->portions !== null ? (string) $this->portions : null,
            'prep_time_minutes' => $this->prep_time_minutes,
            'cook_time_minutes' => $this->cook_time_minutes,
            'difficulty' => $this->difficulty?->value,
            'cuisine_id' => $this->cuisine_id,
            'cuisine' => $this->cuisine?->name,
            'notes' => $this->notes,
            'selling_price' => $this->selling_price !== null ? (string) $this->selling_price : null,
            'current_version_number' => $this->currentVersion?->version_number,
            'edit_sequence' => $this->draft?->edit_sequence,
            'tags' => $this->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name]),
            'sections' => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->name,
                'order' => $section->order,
                'ingredient_lines' => $section->ingredientLines->map(fn ($line) => [
                    'id' => $line->id,
                    'ingredient_id' => $line->ingredient_id,
                    'sub_recipe_version_id' => $line->sub_recipe_version_id,
                    'name' => $line->ingredient_id
                        ? ($line->ingredient?->nameFor($locale) ?? $line->ingredient?->name_cache)
                        : null,
                    'quantity' => $line->quantity !== null ? (string) $line->quantity : null,
                    'unit_id' => $line->unit_id,
                    'prep_note' => $line->prep_note,
                    'yield_pct' => $line->yield_pct !== null ? (string) $line->yield_pct : null,
                    'is_flour_base' => (bool) $line->is_flour_base,
                    'order' => $line->order,
                ]),
            ]),
            'steps' => $this->steps->map(fn ($step) => [
                'id' => $step->id,
                'section_id' => $step->section_id,
                'instruction' => $step->instruction,
                'order' => $step->order,
                'step_image_path' => $step->step_image_path ?? null,
            ]),
        ];
    }
}
