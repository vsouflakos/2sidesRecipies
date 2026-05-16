import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { BakersMetrics } from '@/types/recipe';

interface BakersSectionProps {
    bakers: BakersMetrics | null;
}

export function BakersSection({ bakers }: BakersSectionProps) {
    const { t } = useLaravelReactI18n();

    if (!bakers) {
        return null;
    }

    const entries = Object.entries(bakers.percentages);

    return (
        <section className="space-y-2">
            <h3 className="text-base font-semibold">{t('app.recipes.metrics_bakers')}</h3>

            <div className="divide-y divide-border">
                {entries.map(([name, pct]) => (
                    <div key={name} className="flex items-baseline justify-between gap-2 py-0.5">
                        <span className="text-sm text-muted-foreground">{name}</span>
                        <span className="text-base font-normal tabular-nums">{pct}%</span>
                    </div>
                ))}

                {bakers.hydration_pct !== null && (
                    <div className="flex items-baseline justify-between gap-2 py-0.5 font-medium">
                        <span className="text-sm">{t('app.recipes.metrics_hydration')}</span>
                        <span className="text-base tabular-nums">{bakers.hydration_pct}%</span>
                    </div>
                )}
            </div>
        </section>
    );
}
