import { useTranslations } from '@/hooks/use-translations';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ScalingControlsProps {
    /** Current draft portion count (the saved value). */
    draftPortions: number | null;
    /**
     * Called when the user clicks "Apply to Draft".
     * Receives integer numerator/denominator so the server applies exact rational scaling,
     * not pre-rounded floats (per RESEARCH Pitfall 1).
     */
    onApplyScale: (params: {
        scale_numerator: number;
        scale_denominator: number;
        portions: number;
    }) => void;
}

export function ScalingControls({ draftPortions, onApplyScale }: ScalingControlsProps) {
    const { t } = useTranslations();

    /** Scale factor entered by the user (stored as a string for controlled input). */
    const [scaleInput, setScaleInput] = useState('1');
    /** Portion count entered by the user. */
    const [portionsInput, setPortionsInput] = useState(
        draftPortions !== null ? String(draftPortions) : '1',
    );

    const scaleFactor = parseFloat(scaleInput);
    const portions = parseInt(portionsInput, 10);

    const draftPortionsNum = draftPortions ?? 1;

    const scaleChanged = isFinite(scaleFactor) && scaleFactor !== 1;
    const portionsChanged = isFinite(portions) && portions !== draftPortionsNum;
    const isDirty = scaleChanged || portionsChanged;

    function handleApplyScale() {
        if (!isDirty) { return; }

        const effectiveScale = isFinite(scaleFactor) && scaleFactor > 0 ? scaleFactor : 1;
        const effectivePortions = isFinite(portions) && portions > 0 ? portions : draftPortionsNum;

        /**
         * Convert decimal scale to rational form to avoid pre-rounded float drift.
         * We use denominator 1000 as a common base (handles most practical multipliers
         * like 0.5, 1.5, 2.0, 2.5 exactly).
         */
        const denominator = 1000;
        const numerator = Math.round(effectiveScale * denominator);

        onApplyScale({
            scale_numerator: numerator,
            scale_denominator: denominator,
            portions: effectivePortions,
        });

        setScaleInput('1');
        setPortionsInput(String(effectivePortions));
    }

    return (
        <section className="space-y-3">
            <h3 className="text-base font-semibold">{t('app.recipes.metrics_scaling')}</h3>

            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label htmlFor="scale-factor" className="text-sm text-muted-foreground">
                        {t('app.recipes.metrics_scale_factor')}
                    </Label>
                    <Input
                        id="scale-factor"
                        type="number"
                        min="0.1"
                        step="0.1"
                        value={scaleInput}
                        onChange={(e) => setScaleInput(e.target.value)}
                        aria-label={t('app.recipes.metrics_scale_factor')}
                    />
                </div>

                <div className="space-y-1">
                    <Label htmlFor="scale-portions" className="text-sm text-muted-foreground">
                        {t('app.recipes.metrics_portions')}
                    </Label>
                    <Input
                        id="scale-portions"
                        type="number"
                        min="1"
                        step="1"
                        value={portionsInput}
                        onChange={(e) => setPortionsInput(e.target.value)}
                        aria-label={t('app.recipes.metrics_portions')}
                    />
                </div>
            </div>

            {isDirty && (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    className="w-full"
                    onClick={handleApplyScale}
                >
                    {t('app.recipes.metrics_apply_draft')}
                </Button>
            )}
        </section>
    );
}
