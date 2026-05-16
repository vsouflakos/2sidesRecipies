import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { IngredientDetail } from '@/types/ingredient';

interface NutritionPanelProps {
    ingredient: IngredientDetail;
}

interface NutrientRow {
    key: keyof IngredientDetail;
    label: string;
    unit: string;
}

interface NutrientGroup {
    heading: string;
    nutrients: NutrientRow[];
}

const NUTRIENT_GROUPS: NutrientGroup[] = [
    {
        heading: 'Energy',
        nutrients: [{ key: 'energy_kcal', label: 'Energy', unit: 'kcal' }],
    },
    {
        heading: 'Macros',
        nutrients: [
            { key: 'protein_g', label: 'Protein', unit: 'g' },
            { key: 'fat_g', label: 'Fat', unit: 'g' },
            { key: 'carbs_g', label: 'Carbohydrates', unit: 'g' },
        ],
    },
    {
        heading: 'Fat detail',
        nutrients: [
            { key: 'saturated_fat_g', label: 'Saturated fat', unit: 'g' },
            { key: 'monounsaturated_fat_g', label: 'Monounsaturated fat', unit: 'g' },
            { key: 'polyunsaturated_fat_g', label: 'Polyunsaturated fat', unit: 'g' },
        ],
    },
    {
        heading: 'Carbohydrate detail',
        nutrients: [
            { key: 'sugars_g', label: 'Sugars', unit: 'g' },
            { key: 'starch_g', label: 'Starch', unit: 'g' },
            { key: 'fibre_g', label: 'Dietary fibre', unit: 'g' },
        ],
    },
    {
        heading: 'Minerals',
        nutrients: [
            { key: 'sodium_mg', label: 'Sodium', unit: 'mg' },
            { key: 'calcium_mg', label: 'Calcium', unit: 'mg' },
            { key: 'iron_mg', label: 'Iron', unit: 'mg' },
            { key: 'magnesium_mg', label: 'Magnesium', unit: 'mg' },
            { key: 'phosphorus_mg', label: 'Phosphorus', unit: 'mg' },
            { key: 'potassium_mg', label: 'Potassium', unit: 'mg' },
            { key: 'zinc_mg', label: 'Zinc', unit: 'mg' },
        ],
    },
    {
        heading: 'Vitamins',
        nutrients: [
            { key: 'vitamin_a_ug', label: 'Vitamin A', unit: 'µg' },
            { key: 'vitamin_b1_mg', label: 'Vitamin B1 (Thiamine)', unit: 'mg' },
            { key: 'vitamin_b2_mg', label: 'Vitamin B2 (Riboflavin)', unit: 'mg' },
            { key: 'vitamin_b3_mg', label: 'Vitamin B3 (Niacin)', unit: 'mg' },
            { key: 'vitamin_b6_mg', label: 'Vitamin B6', unit: 'mg' },
            { key: 'vitamin_b9_ug', label: 'Vitamin B9 (Folate)', unit: 'µg' },
            { key: 'vitamin_b12_ug', label: 'Vitamin B12', unit: 'µg' },
            { key: 'vitamin_c_mg', label: 'Vitamin C', unit: 'mg' },
            { key: 'vitamin_d_ug', label: 'Vitamin D', unit: 'µg' },
            { key: 'vitamin_e_mg', label: 'Vitamin E', unit: 'mg' },
            { key: 'vitamin_k_ug', label: 'Vitamin K', unit: 'µg' },
        ],
    },
    {
        heading: 'Other',
        nutrients: [{ key: 'cholesterol_mg', label: 'Cholesterol', unit: 'mg' }],
    },
];

function formatValue(value: string | null | undefined, unit: string): string {
    if (value === null || value === undefined) {
        return '—';
    }
    const num = parseFloat(value);
    if (isNaN(num)) {
        return '—';
    }
    return `${num} ${unit}`;
}

export function NutritionPanel({ ingredient }: NutritionPanelProps) {
    const { t } = useLaravelReactI18n();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-[20px]">{t('app.ingredients.tab_nutrition')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {NUTRIENT_GROUPS.map((group) => (
                    <div key={group.heading}>
                        <h3 className="mb-2 text-[14px] font-semibold text-muted-foreground uppercase tracking-wide">
                            {group.heading}
                        </h3>
                        <div
                            className={cn(
                                'grid gap-x-8 gap-y-1',
                                'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                            )}
                        >
                            {group.nutrients.map((nutrient) => (
                                <div
                                    key={nutrient.key as string}
                                    className="flex items-baseline justify-between py-1 border-b border-border last:border-0"
                                >
                                    <span className="text-[14px] text-muted-foreground">
                                        {nutrient.label}
                                    </span>
                                    <span className="text-[16px] font-medium tabular-nums">
                                        {formatValue(
                                            ingredient[nutrient.key] as string | null,
                                            nutrient.unit,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
