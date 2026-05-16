<?php

namespace App\Concerns;

trait IngredientValidationRules
{
    /**
     * Get the validation rules used to validate ingredient data.
     *
     * Minimum required to save: at least one locale name (name_en or name_el) + category_id.
     * The `name` field is accepted as a convenience alias for name_en.
     *
     * @return array<string, array<int, string>>
     */
    protected function ingredientRules(): array
    {
        return [
            // Names — at least one locale is required; `name` is accepted as alias for name_en
            'name' => ['sometimes', 'nullable', 'string', 'max:500'],
            'name_en' => ['required_without_all:name_el,name', 'nullable', 'string', 'max:500'],
            'name_el' => ['nullable', 'string', 'max:500'],

            // Category
            'category_id' => ['required', 'exists:ingredient_categories,id'],

            // Nutrition — all optional numeric values per 100g
            'energy_kcal' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'saturated_fat_g' => ['nullable', 'numeric', 'min:0'],
            'monounsaturated_fat_g' => ['nullable', 'numeric', 'min:0'],
            'polyunsaturated_fat_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'sugars_g' => ['nullable', 'numeric', 'min:0'],
            'starch_g' => ['nullable', 'numeric', 'min:0'],
            'fibre_g' => ['nullable', 'numeric', 'min:0'],
            'sodium_mg' => ['nullable', 'numeric', 'min:0'],
            'calcium_mg' => ['nullable', 'numeric', 'min:0'],
            'iron_mg' => ['nullable', 'numeric', 'min:0'],
            'magnesium_mg' => ['nullable', 'numeric', 'min:0'],
            'phosphorus_mg' => ['nullable', 'numeric', 'min:0'],
            'potassium_mg' => ['nullable', 'numeric', 'min:0'],
            'zinc_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_a_ug' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b1_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b2_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b3_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b6_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b9_ug' => ['nullable', 'numeric', 'min:0'],
            'vitamin_b12_ug' => ['nullable', 'numeric', 'min:0'],
            'vitamin_c_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_d_ug' => ['nullable', 'numeric', 'min:0'],
            'vitamin_e_mg' => ['nullable', 'numeric', 'min:0'],
            'vitamin_k_ug' => ['nullable', 'numeric', 'min:0'],
            'cholesterol_mg' => ['nullable', 'numeric', 'min:0'],

            // Allergens pivot — optional array of allergen states
            'allergens' => ['nullable', 'array'],
            'allergens.*.allergen_id' => ['required_with:allergens', 'exists:allergens,id'],
            'allergens.*.state' => ['required_with:allergens', 'in:contains,may_contain'],

            // Unit conversions — optional repeatable rows
            'conversions' => ['nullable', 'array'],
            'conversions.*.from_amount' => ['required_with:conversions', 'numeric', 'gt:0'],
            'conversions.*.from_unit_id' => ['required_with:conversions', 'exists:units,id'],
            'conversions.*.gram_weight' => ['required_with:conversions', 'numeric', 'gt:0'],
            'conversions.*.modifier' => ['nullable', 'string', 'max:100'],
        ];
    }
}
