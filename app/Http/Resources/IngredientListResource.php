<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->nameFor(app()->getLocale()),
            'secondary_name' => $this->nameFor(app()->getLocale() === 'el' ? 'en' : 'el'),
            'energy_kcal' => $this->energy_kcal !== null ? (float) $this->energy_kcal : null,
            'verified' => (bool) $this->verified,
            'is_private' => $this->user_id !== null,
            'allergens' => $this->allergens->map(fn ($a) => [
                'slug' => $a->slug,
                'name' => $a->name,
                'state' => $a->pivot->state,
            ]),
        ];
    }
}
