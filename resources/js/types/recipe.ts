import type { UnitOption } from '@/types/ingredient';

export type { UnitOption };

/** Cuisine option from seeded list. */
export interface CuisineOption {
    id: number;
    name: string;
    slug: string;
}

/** Tag option. */
export interface TagOption {
    id: number;
    name: string;
    slug: string;
}

/** A single ingredient line within a recipe section. */
export interface RecipeIngredientLine {
    id: number;
    ingredient_id: number | null;
    sub_recipe_version_id: number | null;
    name: string;
    quantity: string;
    unit_id: number | null;
    prep_note: string | null;
    yield_pct: string;
    is_flour_base: boolean;
    order: number;
    /** Populated when the line is a sub-recipe reference. */
    sub_recipe?: {
        id: number;
        name: string;
        version_number: number;
        latest_version_number: number;
    } | null;
}

/** A single step within a recipe section. */
export interface RecipeStep {
    id: number;
    instruction: string;
    order: number;
    step_image_path: string | null;
}

/** A named section grouping ingredient lines and steps. */
export interface RecipeSection {
    id: number;
    name: string;
    order: number;
    lines: RecipeIngredientLine[];
    steps: RecipeStep[];
}

/** Full builder data shape from RecipeBuilderResource. */
export interface RecipeBuilderData {
    id: number;
    name: string;
    slug: string;
    hero_image_path: string | null;
    cuisine_id: number | null;
    difficulty: 'easy' | 'medium' | 'hard' | 'expert' | null;
    yield_amount: string | null;
    yield_unit_id: number | null;
    portions: number | null;
    prep_time_minutes: number | null;
    cook_time_minutes: number | null;
    chef_notes: string | null;
    sections: RecipeSection[];
    tags: TagOption[];
}

/** Working draft shape — mirrors RecipeBuilderData. */
export type RecipeDraft = RecipeBuilderData;

/** Allergen entry for metrics. */
export interface RecipeAllergen {
    slug: string;
    name: string;
    state: 'contains' | 'may_contain';
}

/** Per-ingredient-line nutrition contribution. */
export interface LineNutrition {
    ingredient_line_id: number;
    energy_kcal: string | null;
    protein_g: string | null;
    fat_g: string | null;
    saturated_fat_g: string | null;
    carbs_g: string | null;
    fibre_g: string | null;
    sodium_mg: string | null;
}

/** Nutrition totals per portion and per 100 g. */
export interface NutritionMetrics {
    per_portion: {
        energy_kcal: string | null;
        protein_g: string | null;
        fat_g: string | null;
        saturated_fat_g: string | null;
        carbs_g: string | null;
        fibre_g: string | null;
        sodium_mg: string | null;
    };
    per_100g: {
        energy_kcal: string | null;
        protein_g: string | null;
        fat_g: string | null;
        saturated_fat_g: string | null;
        carbs_g: string | null;
        fibre_g: string | null;
        sodium_mg: string | null;
    };
}

/** Cost metrics for the recipe. */
export interface CostMetrics {
    total_cost: string | null;
    cost_per_portion: string | null;
    food_cost_pct: string | null;
}

/** Shrinkage / yield metrics. */
export interface ShrinkageMetrics {
    yield_weight_g: string | null;
    shrinkage_pct: string | null;
}

/** Baker's percentage entry. */
export interface BakersEntry {
    ingredient_line_id: number;
    name: string;
    pct: string;
}

/** Baker's percentage metrics (only present when flour base exists). */
export interface BakersMetrics {
    flour_base_g: string;
    hydration_pct: string | null;
    entries: BakersEntry[];
}

/** Ingredient lines missing price or nutrition data. */
export interface MissingDataInfo {
    missing_price: string[];
    missing_nutrition: string[];
}

/** Full metrics output from RecipeMetricsService. */
export interface RecipeMetrics {
    nutrition: NutritionMetrics;
    cost: CostMetrics;
    shrinkage: ShrinkageMetrics;
    allergens: {
        contains: RecipeAllergen[];
        may_contain: RecipeAllergen[];
    };
    bakers: BakersMetrics | null;
    missing_data: MissingDataInfo;
}

/** Recipe version summary for version history. */
export interface RecipeVersion {
    id: number;
    version_number: number;
    change_note: string | null;
    created_at: string;
    is_current: boolean;
}

/** Unified search result from GET /search/components. */
export interface ComponentSearchResult {
    type: 'ingredient' | 'recipe';
    id: number;
    name: string;
    unit_hint: string | null;
}

/** Can flags passed from RecipeController. */
export interface RecipeCanFlags {
    update: boolean;
    delete: boolean;
    duplicate: boolean;
}

/** Full prop shape for the show (builder) page. */
export interface RecipeShowProps {
    recipe: RecipeBuilderData;
    draft: RecipeDraft;
    metrics: RecipeMetrics;
    versions: RecipeVersion[];
    cuisines: CuisineOption[];
    units: UnitOption[];
    tags: TagOption[];
    can: RecipeCanFlags;
}
