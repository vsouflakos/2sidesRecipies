import { useTranslations } from '@/hooks/use-translations';
import { useState } from 'react';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { NutritionMetrics } from '@/types/recipe';

interface NutritionSectionProps {
    nutrition: NutritionMetrics;
}

interface MetricRowProps {
    label: string;
    value: string | null;
}

function MetricRow({ label, value }: MetricRowProps) {
    return (
        <div className="flex items-baseline justify-between gap-2 py-0.5">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-base font-normal tabular-nums">{value ?? '—'}</span>
        </div>
    );
}

export function NutritionSection({ nutrition }: NutritionSectionProps) {
    const { t } = useTranslations();
    const [perPortion, setPerPortion] = useState(true);

    const slice = perPortion ? nutrition.per_portion : nutrition.per_100g;

    return (
        <section className="space-y-2">
            <div className="flex items-center justify-between">
                <h3 className="text-base font-semibold">{t('app.recipes.metrics_nutrition')}</h3>
                <div className="flex items-center gap-2">
                    <Label
                        htmlFor="nutrition-toggle"
                        className="cursor-pointer text-sm text-muted-foreground"
                    >
                        {perPortion
                            ? t('app.recipes.metrics_per_portion')
                            : t('app.recipes.metrics_per_100g')}
                    </Label>
                    <Switch
                        id="nutrition-toggle"
                        checked={!perPortion}
                        onCheckedChange={(checked) => setPerPortion(!checked)}
                        aria-label={t('app.recipes.metrics_per_100g')}
                    />
                </div>
            </div>

            <div className="divide-y divide-border">
                <MetricRow
                    label={
                        perPortion
                            ? t('app.recipes.metrics_per_portion')
                            : t('app.recipes.metrics_per_100g')
                    }
                    value={slice.energy_kcal !== null ? `${slice.energy_kcal} kcal` : null}
                />
                <MetricRow label="Protein" value={slice.protein_g !== null ? `${slice.protein_g} g` : null} />
                <MetricRow label="Fat" value={slice.fat_g !== null ? `${slice.fat_g} g` : null} />
                <MetricRow
                    label="Saturated fat"
                    value={slice.saturated_fat_g !== null ? `${slice.saturated_fat_g} g` : null}
                />
                <MetricRow
                    label="Carbohydrates"
                    value={slice.carbs_g !== null ? `${slice.carbs_g} g` : null}
                />
                <MetricRow label="Fibre" value={slice.fibre_g !== null ? `${slice.fibre_g} g` : null} />
                <MetricRow
                    label="Sodium"
                    value={slice.sodium_mg !== null ? `${slice.sodium_mg} mg` : null}
                />
            </div>
        </section>
    );
}
