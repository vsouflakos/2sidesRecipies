import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { AllergenSection } from '@/components/recipes/metrics-panel/allergen-section';
import { BakersSection } from '@/components/recipes/metrics-panel/bakers-section';
import { CostSection } from '@/components/recipes/metrics-panel/cost-section';
import { DataGapBanner } from '@/components/recipes/metrics-panel/data-gap-banner';
import { NutritionSection } from '@/components/recipes/metrics-panel/nutrition-section';
import { ScalingControls } from '@/components/recipes/metrics-panel/scaling-controls';
import type { RecipeMetrics } from '@/types/recipe';

interface MetricsPanelProps {
    metrics: RecipeMetrics | null;
    draftSellingPrice: string | null;
    draftPortions: number | null;
    onSellingPriceChange: (value: string) => void;
    onApplyScale: (params: {
        scale_numerator: number;
        scale_denominator: number;
        portions: number;
    }) => void;
}

/** The full panel content — composed from all six section components. */
function PanelContent({
    metrics,
    draftSellingPrice,
    draftPortions,
    onSellingPriceChange,
    onApplyScale,
}: MetricsPanelProps) {
    const { t } = useLaravelReactI18n();

    if (!metrics) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('app.recipes.metrics_data_gap')}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <NutritionSection nutrition={metrics.nutrition} />
            <div className="border-t border-border" />
            <CostSection
                cost={metrics.cost}
                draftSellingPrice={draftSellingPrice}
                onSellingPriceChange={onSellingPriceChange}
            />
            <div className="border-t border-border" />
            <AllergenSection
                contains={metrics.allergens.contains}
                mayContain={metrics.allergens.may_contain}
            />
            {metrics.bakers !== null && (
                <>
                    <div className="border-t border-border" />
                    <BakersSection bakers={metrics.bakers} />
                </>
            )}
            <div className="border-t border-border" />
            <ScalingControls
                draftPortions={draftPortions}
                onApplyScale={onApplyScale}
            />
            {(Array.isArray(metrics.missing_data) ? metrics.missing_data : []).length > 0 && (
                <>
                    <div className="border-t border-border" />
                    <DataGapBanner missingData={metrics.missing_data} />
                </>
            )}
        </div>
    );
}

/**
 * Sticky metrics panel for the recipe builder right column.
 *
 * On desktop: sticky div scrolling independently of the builder column.
 * On mobile (<768px): collapses to a pinned summary bar; tapping opens the full panel as a Sheet.
 */
export function MetricsPanel({
    metrics,
    draftSellingPrice,
    draftPortions,
    onSellingPriceChange,
    onApplyScale,
}: MetricsPanelProps) {
    const { t } = useLaravelReactI18n();
    const [mobileSheetOpen, setMobileSheetOpen] = useState(false);

    const kcalPerPortion = metrics?.nutrition.per_portion.energy_kcal;
    const costPerPortion = metrics?.cost.cost_per_portion;

    return (
        <>
            {/* Desktop: sticky panel */}
            <div className="hidden h-full overflow-y-auto p-4 lg:block">
                <div className="sticky top-0 rounded-lg bg-muted p-4">
                    <PanelContent
                        metrics={metrics}
                        draftSellingPrice={draftSellingPrice}
                        draftPortions={draftPortions}
                        onSellingPriceChange={onSellingPriceChange}
                        onApplyScale={onApplyScale}
                    />
                </div>
            </div>

            {/* Mobile: pinned summary bar + Sheet */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background px-4 py-2 lg:hidden">
                <Sheet open={mobileSheetOpen} onOpenChange={setMobileSheetOpen}>
                    <SheetTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            className="flex w-full items-center justify-between gap-4"
                        >
                            <span className="text-sm text-muted-foreground">
                                {costPerPortion !== null && costPerPortion !== undefined
                                    ? `€${costPerPortion} / ${t('app.recipes.metrics_portions').toLowerCase()}`
                                    : '—'}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {kcalPerPortion !== null && kcalPerPortion !== undefined
                                    ? `${kcalPerPortion} kcal`
                                    : '—'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('app.recipes.metrics_nutrition')} ↑
                            </span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>{t('app.recipes.metrics_nutrition')}</SheetTitle>
                        </SheetHeader>
                        <div className="px-4 pb-6">
                            <PanelContent
                                metrics={metrics}
                                draftSellingPrice={draftSellingPrice}
                                draftPortions={draftPortions}
                                onSellingPriceChange={onSellingPriceChange}
                                onApplyScale={onApplyScale}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
