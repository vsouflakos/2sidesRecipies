export type TestType = 'trial' | 'experiment';
export type TestVerdict = 'worked' | 'didnt_work' | 'inconclusive';

export interface RatingDimension {
    dimension: string;
    score: number | null;
    is_custom: boolean;
}

export interface ChangeRow {
    what_changed: string;
    expected_effect: string | null;
    actual_effect: string | null;
}

export interface RecipeTestPhoto {
    id: number;
    path: string;
    url: string;
    order: number;
}

export interface RecipeTest {
    id: number;
    recipe_id: number;
    recipe_version_id: number;
    version_number: number;
    type: TestType;
    tested_at: string;
    tasting_notes: string | null;
    overall_rating: number;
    ratings: RatingDimension[] | null;
    hypothesis: string | null;
    outcome_narrative: string | null;
    verdict: TestVerdict | null;
    change_rows: ChangeRow[] | null;
    photos: RecipeTestPhoto[];
    created_at: string;
    updated_at: string;
}

export interface TestVersionOption {
    id: number;
    version_number: number;
    committed_at: string;
}

export interface RecipeTestsIndexProps {
    recipe: { id: number; name: string; current_version_id: number | null };
    tests: RecipeTest[];
    versions: TestVersionOption[];
}

export const DEFAULT_RATING_DIMENSIONS = ['Taste', 'Texture', 'Appearance', 'Aroma'] as const;
export const MAX_TEST_PHOTOS = 8;
