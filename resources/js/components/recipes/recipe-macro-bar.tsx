import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type RecipeMacroBarProps = {
    /** Per-portion macronutrient grams as raw strings from the API (or null). */
    protein: string | null;
    carbs: string | null;
    fat: string | null;
    className?: string;
};

/** Macro segments — order drives both the stacked bar and the legend. */
const MACROS = [
    { key: 'protein', color: 'bg-sky-500', labelKey: 'app.recipes.macro_protein' },
    { key: 'carbs', color: 'bg-amber-500', labelKey: 'app.recipes.macro_carbs' },
    { key: 'fat', color: 'bg-rose-500', labelKey: 'app.recipes.macro_fat' },
] as const;

/** Coerce an API numeric string into a non-negative number. */
function toGrams(value: string | null): number {
    const parsed = Number.parseFloat(value ?? '');

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/**
 * Compact per-portion macro breakdown: a proportional stacked bar plus a
 * colour-coded gram legend. Renders nothing when every macro is zero/absent.
 */
export function RecipeMacroBar({ protein, carbs, fat, className }: RecipeMacroBarProps) {
    const { t } = useTranslations();

    const grams: Record<(typeof MACROS)[number]['key'], number> = {
        protein: toGrams(protein),
        carbs: toGrams(carbs),
        fat: toGrams(fat),
    };

    const total = grams.protein + grams.carbs + grams.fat;

    if (total <= 0) {
        return null;
    }

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            {/* Proportional stacked bar */}
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                {MACROS.map((macro) => {
                    const pct = (grams[macro.key] / total) * 100;

                    if (pct <= 0) {
                        return null;
                    }

                    return (
                        <div
                            key={macro.key}
                            className={macro.color}
                            style={{ width: `${pct}%` }}
                        />
                    );
                })}
            </div>

            {/* Gram legend */}
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-muted-foreground">
                {MACROS.map((macro) => (
                    <span key={macro.key} className="inline-flex items-center gap-1.5">
                        <span className={cn('size-2 rounded-full', macro.color)} />
                        <span className="font-semibold text-foreground">
                            {Math.round(grams[macro.key])}g
                        </span>
                        {t(macro.labelKey)}
                    </span>
                ))}
            </div>
        </div>
    );
}
