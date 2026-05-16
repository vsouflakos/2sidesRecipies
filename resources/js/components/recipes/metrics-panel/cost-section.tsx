import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CostMetrics } from '@/types/recipe';

interface CostSectionProps {
    cost: CostMetrics;
    /** Initial selling price from the draft (string or null). */
    draftSellingPrice: string | null;
    /** Called when the selling price changes; triggers auto-save. */
    onSellingPriceChange: (value: string) => void;
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

/** Compute live food cost % from selling price and cost per portion. */
function computeFoodCostPct(costPerPortion: string | null, sellingPrice: string): string | null {
    const cost = parseFloat(costPerPortion ?? '');
    const selling = parseFloat(sellingPrice);

    if (!isFinite(cost) || !isFinite(selling) || selling <= 0) {
        return null;
    }

    return ((cost / selling) * 100).toFixed(1) + '%';
}

export function CostSection({ cost, draftSellingPrice, onSellingPriceChange }: CostSectionProps) {
    const { t } = useLaravelReactI18n();
    const [localSellingPrice, setLocalSellingPrice] = useState(draftSellingPrice ?? '');

    const liveFoodCostPct =
        localSellingPrice !== ''
            ? computeFoodCostPct(cost.cost_per_portion, localSellingPrice)
            : cost.food_cost_pct;

    function handleSellingPriceChange(e: React.ChangeEvent<HTMLInputElement>) {
        const value = e.target.value;
        setLocalSellingPrice(value);
        onSellingPriceChange(value);
    }

    return (
        <section className="space-y-2">
            <h3 className="text-base font-semibold">{t('app.recipes.metrics_cost')}</h3>

            <div className="divide-y divide-border">
                <MetricRow
                    label={t('app.recipes.metrics_cost_per_portion')}
                    value={cost.cost_per_portion !== null ? `€${cost.cost_per_portion}` : null}
                />
                <MetricRow
                    label={t('app.recipes.metrics_total_cost')}
                    value={cost.total_cost !== null ? `€${cost.total_cost}` : null}
                />
                <div className="flex items-baseline justify-between gap-2 py-0.5">
                    <span className="text-sm text-muted-foreground">
                        {t('app.recipes.metrics_food_cost_pct')}
                    </span>
                    <span className="text-base font-normal tabular-nums">
                        {liveFoodCostPct ?? '—'}
                    </span>
                </div>
            </div>

            <div className="space-y-1 pt-1">
                <Label htmlFor="selling-price" className="text-sm text-muted-foreground">
                    {t('app.recipes.metrics_selling_price_placeholder')}
                </Label>
                <div className="relative">
                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                        €
                    </span>
                    <Input
                        id="selling-price"
                        type="number"
                        min="0"
                        step="0.01"
                        value={localSellingPrice}
                        onChange={handleSellingPriceChange}
                        placeholder={t('app.recipes.metrics_selling_price_placeholder')}
                        className="pl-6"
                    />
                </div>
            </div>
        </section>
    );
}
