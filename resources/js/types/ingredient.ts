export interface IngredientAllergen {
    slug: string;
    name: string;
    state: 'contains' | 'may_contain';
}

export interface IngredientListItem {
    id: number;
    name: string;
    secondary_name: string;
    energy_kcal: number | null;
    verified: boolean;
    is_private: boolean;
    allergens: IngredientAllergen[];
}

export interface IngredientFilters {
    search: string;
    source: 'all' | 'official' | 'private';
    verified_only: boolean;
    allergen_free: string[];
}

export interface AllergenOption {
    id: number;
    name: string;
    slug: string;
}

export interface PaginatedIngredients {
    data: IngredientListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    prev_page_url: string | null;
    next_page_url: string | null;
}

// --- Private ingredient create/edit types ---

export interface CategoryNode {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    children?: CategoryNode[];
}

export interface UnitOption {
    id: number;
    name: string;
    symbol: string;
    type: 'weight' | 'volume' | 'count';
}

export interface NutritionData {
    energy_kcal?: string;
    protein_g?: string;
    fat_g?: string;
    saturated_fat_g?: string;
    monounsaturated_fat_g?: string;
    polyunsaturated_fat_g?: string;
    carbs_g?: string;
    sugars_g?: string;
    starch_g?: string;
    fibre_g?: string;
    sodium_mg?: string;
    calcium_mg?: string;
    iron_mg?: string;
    magnesium_mg?: string;
    phosphorus_mg?: string;
    potassium_mg?: string;
    zinc_mg?: string;
    vitamin_a_ug?: string;
    vitamin_b1_mg?: string;
    vitamin_b2_mg?: string;
    vitamin_b3_mg?: string;
    vitamin_b6_mg?: string;
    vitamin_b9_ug?: string;
    vitamin_b12_ug?: string;
    vitamin_c_mg?: string;
    vitamin_d_ug?: string;
    vitamin_e_mg?: string;
    vitamin_k_ug?: string;
    cholesterol_mg?: string;
}

export interface AllergenFormEntry {
    allergen_id: number;
    state: 'contains' | 'may_contain';
}

export interface ConversionFormEntry {
    from_amount: string;
    from_unit_id: number | '';
    gram_weight: string;
    modifier: string;
}

export interface IngredientFormData extends NutritionData {
    name_en: string;
    name_el: string;
    category_id: number | '';
    allergens: AllergenFormEntry[];
    conversions: ConversionFormEntry[];
}

export interface IngredientTranslation {
    locale: string;
    name: string;
}

export interface IngredientConversionDetail {
    id: number;
    from_amount: string;
    from_unit_id: number;
    gram_weight: string;
    modifier: string | null;
    source: string;
}

export interface IngredientAllergenDetail {
    id: number;
    name: string;
    slug: string;
    pivot: {
        state: 'contains' | 'may_contain';
    };
}

/** A single price entry scoped to the current user. */
export interface IngredientPrice {
    id: number;
    amount: string;
    currency: string;
    quantity: string;
    unit: { name: string; symbol: string } | null;
    per_gram_cost: string | null;
    recorded_at: string;
    notes: string | null;
}

/** Form data for recording a new ingredient price. */
export interface PriceFormData {
    amount: string;
    quantity: string;
    unit_id: number | '';
    currency: string;
    recorded_at: string;
    notes: string;
}

/** Full detail shape from IngredientDetailResource. */
export interface IngredientDetail {
    id: number;
    name: string;
    name_en: string;
    name_el: string;
    is_private: boolean;
    category: {
        name: string;
        parent: string | null;
    } | null;
    // Nutrition columns (decimal strings from Laravel cast)
    energy_kcal: string | null;
    protein_g: string | null;
    fat_g: string | null;
    saturated_fat_g: string | null;
    monounsaturated_fat_g: string | null;
    polyunsaturated_fat_g: string | null;
    carbs_g: string | null;
    sugars_g: string | null;
    starch_g: string | null;
    fibre_g: string | null;
    sodium_mg: string | null;
    calcium_mg: string | null;
    iron_mg: string | null;
    magnesium_mg: string | null;
    phosphorus_mg: string | null;
    potassium_mg: string | null;
    zinc_mg: string | null;
    vitamin_a_ug: string | null;
    vitamin_b1_mg: string | null;
    vitamin_b2_mg: string | null;
    vitamin_b3_mg: string | null;
    vitamin_b6_mg: string | null;
    vitamin_b9_ug: string | null;
    vitamin_b12_ug: string | null;
    vitamin_c_mg: string | null;
    vitamin_d_ug: string | null;
    vitamin_e_mg: string | null;
    vitamin_k_ug: string | null;
    cholesterol_mg: string | null;
    // Allergens
    allergens: Array<{
        slug: string;
        name: string;
        state: 'contains' | 'may_contain';
    }>;
    // Conversions
    conversions: Array<{
        from_amount: string;
        unit: { name: string; symbol: string } | null;
        gram_weight: string;
        modifier: string | null;
        source: string;
    }>;
    // Verification
    verified: boolean;
    verified_at: string | null;
    verified_by: string | null;
    // Prices (scoped to current user)
    prices: IngredientPrice[];
}

export interface CanFlags {
    verify: boolean;
    manage: boolean;
}
