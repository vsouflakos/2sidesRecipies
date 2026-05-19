import { FlameIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { NutritionMetrics } from '@/types/recipe';

interface NutritionSectionProps {
    nutrition: NutritionMetrics;
}

/** Per-100g nutrition slice keys shared by both view modes. */
type NutritionSlice = NutritionMetrics['per_portion'];

/**
 * Macro definitions — `kcalPerG` drives the energy-share donut (Atwater factors),
 * `dot`/`stroke` keep the legend and the ring chart colour-matched.
 */
const MACROS = [
    {
        key: 'protein_g',
        kcalPerG: 4,
        labelKey: 'app.recipes.macro_protein',
        dot: 'bg-sky-500',
        stroke: 'stroke-sky-500',
    },
    {
        key: 'carbs_g',
        kcalPerG: 4,
        labelKey: 'app.recipes.macro_carbs',
        dot: 'bg-amber-500',
        stroke: 'stroke-amber-500',
    },
    {
        key: 'fat_g',
        kcalPerG: 9,
        labelKey: 'app.recipes.macro_fat',
        dot: 'bg-rose-500',
        stroke: 'stroke-rose-500',
    },
] as const;

/**
 * Secondary nutrients shown with a reference-intake bar.
 * `ref` values are EU adult guideline daily amounts (saturated fat 20 g,
 * fibre 25 g, sodium 2400 mg ≈ 6 g salt).
 */
const NUTRIENTS = [
    {
        key: 'saturated_fat_g',
        ref: 20,
        unit: 'g',
        labelKey: 'app.recipes.metrics_saturated_fat',
        fill: 'bg-rose-400',
    },
    {
        key: 'fibre_g',
        ref: 25,
        unit: 'g',
        labelKey: 'app.recipes.metrics_fibre',
        fill: 'bg-emerald-500',
    },
    {
        key: 'sodium_mg',
        ref: 2400,
        unit: 'mg',
        labelKey: 'app.recipes.metrics_sodium',
        fill: 'bg-violet-500',
    },
] as const;

/** Coerce an API numeric string into a finite, non-negative number. */
function toNumber(value: string | null): number {
    const parsed = Number.parseFloat(value ?? '');

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/** Format a gram/mg amount: whole numbers above 10, one decimal below. */
function formatAmount(value: number): string {
    if (value >= 10 || Number.isInteger(value)) {
        return String(Math.round(value));
    }

    return value.toFixed(1);
}

interface DonutSegment {
    key: string;
    fraction: number;
    stroke: string;
}

/** SVG ring chart of the macro energy split, with a value in the hub. */
function MacroDonut({
    segments,
    centerValue,
    centerLabel,
}: {
    segments: DonutSegment[];
    centerValue: string;
    centerLabel: string;
}) {
    const size = 140;
    const strokeWidth = 16;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const drawn = segments.filter((segment) => segment.fraction > 0);
    /** Visible breathing room between adjacent arcs (skipped when only one). */
    const gap = drawn.length > 1 ? 3 : 0;

    /**
     * Precompute each arc's dash length and start offset. Cumulative offsets are
     * derived per-arc (no render-time mutation) so the React Compiler stays happy.
     */
    const arcs = drawn.map((segment, index) => {
        const start = drawn
            .slice(0, index)
            .reduce((sum, prev) => sum + prev.fraction * circumference, 0);
        const length = segment.fraction * circumference;

        return {
            segment,
            dash: Math.max(length - gap, 0.0001),
            dashOffset: -start,
        };
    });

    return (
        <div className="relative shrink-0" style={{ width: size, height: size }}>
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                className="-rotate-90"
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={strokeWidth}
                    className="stroke-muted"
                />
                {arcs.map(({ segment, dash, dashOffset }) => (
                    <circle
                        key={segment.key}
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        strokeWidth={strokeWidth}
                        strokeLinecap="round"
                        className={cn(segment.stroke, 'transition-[stroke-dasharray] duration-500')}
                        strokeDasharray={`${dash} ${circumference - dash}`}
                        strokeDashoffset={dashOffset}
                    />
                ))}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-3xl font-bold leading-none tabular-nums">
                    {centerValue}
                </span>
                <span className="mt-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {centerLabel}
                </span>
            </div>
        </div>
    );
}

export function NutritionSection({ nutrition }: NutritionSectionProps) {
    const { t } = useTranslations();
    const [perPortion, setPerPortion] = useState(true);

    const slice: NutritionSlice = perPortion ? nutrition.per_portion : nutrition.per_100g;

    const macroGrams = MACROS.map((macro) => ({
        ...macro,
        grams: toNumber(slice[macro.key as keyof NutritionSlice]),
    }));

    const macroEnergyTotal = macroGrams.reduce(
        (sum, macro) => sum + macro.grams * macro.kcalPerG,
        0,
    );

    const segments: DonutSegment[] = macroGrams.map((macro) => ({
        key: macro.key,
        stroke: macro.stroke,
        fraction:
            macroEnergyTotal > 0
                ? (macro.grams * macro.kcalPerG) / macroEnergyTotal
                : 0,
    }));

    const energy = toNumber(slice.energy_kcal);
    const centerValue =
        slice.energy_kcal !== null
            ? String(Math.round(energy))
            : macroEnergyTotal > 0
              ? String(Math.round(macroEnergyTotal))
              : '—';

    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-sm">
            {/* Header — icon, title, and a segmented per-portion / per-100g switch */}
            <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-gradient-to-r from-orange-50 to-rose-50 px-4 py-3 dark:from-orange-950/40 dark:to-rose-950/30">
                <div className="flex items-center gap-2">
                    <span className="flex size-7 items-center justify-center rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400">
                        <FlameIcon className="size-4" />
                    </span>
                    <h3 className="text-base font-semibold">
                        {t('app.recipes.metrics_nutrition')}
                    </h3>
                </div>

                <div className="inline-flex rounded-lg bg-background/70 p-0.5 text-xs font-medium shadow-sm">
                    <button
                        type="button"
                        onClick={() => setPerPortion(true)}
                        className={cn(
                            'rounded-md px-2.5 py-1 transition-colors',
                            perPortion
                                ? 'bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t('app.recipes.metrics_per_portion')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setPerPortion(false)}
                        className={cn(
                            'rounded-md px-2.5 py-1 transition-colors',
                            !perPortion
                                ? 'bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t('app.recipes.metrics_per_100g')}
                    </button>
                </div>
            </div>

            {/* Donut + macro legend */}
            <div className="flex items-center gap-4 px-4 py-4">
                <MacroDonut
                    segments={segments}
                    centerValue={centerValue}
                    centerLabel="kcal"
                />

                <div className="flex flex-1 flex-col gap-2.5">
                    {macroGrams.map((macro) => {
                        const pct =
                            macroEnergyTotal > 0
                                ? Math.round(
                                      (macro.grams * macro.kcalPerG * 100) /
                                          macroEnergyTotal,
                                  )
                                : 0;

                        return (
                            <div
                                key={macro.key}
                                className="flex items-center gap-2.5"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 shrink-0 rounded-full',
                                        macro.dot,
                                    )}
                                />
                                <span className="flex-1 text-sm text-muted-foreground">
                                    {t(macro.labelKey)}
                                </span>
                                <span className="text-sm font-semibold tabular-nums">
                                    {formatAmount(macro.grams)} g
                                </span>
                                <span className="w-10 text-right text-xs tabular-nums text-muted-foreground">
                                    {pct}%
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Secondary nutrients with reference-intake bars */}
            <div className="space-y-3 border-t border-border/60 px-4 py-4">
                {NUTRIENTS.map((nutrient) => {
                    const raw = slice[nutrient.key as keyof NutritionSlice];
                    const value = toNumber(raw);
                    const pct = Math.round((value / nutrient.ref) * 100);
                    const barWidth = Math.min(pct, 100);

                    return (
                        <div key={nutrient.key}>
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="text-sm text-muted-foreground">
                                    {t(nutrient.labelKey)}
                                </span>
                                <span className="text-sm font-semibold tabular-nums">
                                    {raw !== null
                                        ? `${formatAmount(value)} ${nutrient.unit}`
                                        : '—'}
                                </span>
                            </div>
                            <div className="mt-1.5 flex items-center gap-2">
                                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className={cn(
                                            'h-full rounded-full transition-[width] duration-500',
                                            nutrient.fill,
                                        )}
                                        style={{ width: `${barWidth}%` }}
                                    />
                                </div>
                                <span className="w-14 text-right text-[11px] tabular-nums text-muted-foreground">
                                    {raw !== null
                                        ? `${pct}% ${t('app.recipes.metrics_ri')}`
                                        : '—'}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
