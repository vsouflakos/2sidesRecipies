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
