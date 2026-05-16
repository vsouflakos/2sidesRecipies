<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->nameFor($locale),
            'name_en' => $this->nameFor('en'),
            'name_el' => $this->nameFor('el'),
            'is_private' => $this->user_id !== null,
            'category' => $this->category ? [
                'name' => $this->category->name,
                'parent' => $this->category->parent?->name,
            ] : null,
            // Nutrition columns
            'energy_kcal' => $this->energy_kcal,
            'protein_g' => $this->protein_g,
            'fat_g' => $this->fat_g,
            'saturated_fat_g' => $this->saturated_fat_g,
            'monounsaturated_fat_g' => $this->monounsaturated_fat_g,
            'polyunsaturated_fat_g' => $this->polyunsaturated_fat_g,
            'carbs_g' => $this->carbs_g,
            'sugars_g' => $this->sugars_g,
            'starch_g' => $this->starch_g,
            'fibre_g' => $this->fibre_g,
            'sodium_mg' => $this->sodium_mg,
            'calcium_mg' => $this->calcium_mg,
            'iron_mg' => $this->iron_mg,
            'magnesium_mg' => $this->magnesium_mg,
            'phosphorus_mg' => $this->phosphorus_mg,
            'potassium_mg' => $this->potassium_mg,
            'zinc_mg' => $this->zinc_mg,
            'vitamin_a_ug' => $this->vitamin_a_ug,
            'vitamin_b1_mg' => $this->vitamin_b1_mg,
            'vitamin_b2_mg' => $this->vitamin_b2_mg,
            'vitamin_b3_mg' => $this->vitamin_b3_mg,
            'vitamin_b6_mg' => $this->vitamin_b6_mg,
            'vitamin_b9_ug' => $this->vitamin_b9_ug,
            'vitamin_b12_ug' => $this->vitamin_b12_ug,
            'vitamin_c_mg' => $this->vitamin_c_mg,
            'vitamin_d_ug' => $this->vitamin_d_ug,
            'vitamin_e_mg' => $this->vitamin_e_mg,
            'vitamin_k_ug' => $this->vitamin_k_ug,
            'cholesterol_mg' => $this->cholesterol_mg,
            // Allergens
            'allergens' => $this->allergens->map(fn ($a) => [
                'slug' => $a->slug,
                'name' => $a->name,
                'state' => $a->pivot->state,
            ]),
            // Conversions
            'conversions' => $this->conversions->map(fn ($c) => [
                'from_amount' => $c->from_amount,
                'unit' => $c->unit ? [
                    'name' => $c->unit->name,
                    'symbol' => $c->unit->symbol,
                ] : null,
                'gram_weight' => $c->gram_weight,
                'modifier' => $c->modifier,
                'source' => $c->source,
            ]),
            // Verification
            'verified' => (bool) $this->verified,
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->verifiedBy?->name,
            // Prices (scoped to current user in controller)
            'prices' => $this->prices->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'quantity' => $p->quantity,
                'unit' => $p->unit,
                'per_gram_cost' => $p->per_gram_cost,
                'recorded_at' => $p->recorded_at,
                'notes' => $p->notes,
            ]),
        ];
    }
}
