import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { IngredientDetail } from '@/types/ingredient';

interface AllergenPanelProps {
    allergens: IngredientDetail['allergens'];
}

export function AllergenPanel({ allergens }: AllergenPanelProps) {
    const { t } = useLaravelReactI18n();

    if (allergens.length === 0) {
        return (
            <p className="text-[14px] text-muted-foreground">
                {t('app.ingredients.allergen_none')}
            </p>
        );
    }

    const containsAllergens = allergens.filter((a) => a.state === 'contains');
    const mayContainAllergens = allergens.filter((a) => a.state === 'may_contain');

    return (
        <div className="space-y-6">
            {containsAllergens.length > 0 && (
                <div>
                    <h3 className="mb-3 text-[14px] font-semibold text-muted-foreground uppercase tracking-wide">
                        {t('app.ingredients.allergen_contains')}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {containsAllergens.map((allergen) => (
                            <Badge
                                key={allergen.slug}
                                variant="outline"
                                className={cn(
                                    'border-destructive text-destructive text-[14px]',
                                )}
                            >
                                {allergen.name}
                            </Badge>
                        ))}
                    </div>
                </div>
            )}

            {mayContainAllergens.length > 0 && (
                <div>
                    <h3 className="mb-3 text-[14px] font-semibold text-muted-foreground uppercase tracking-wide">
                        {t('app.ingredients.allergen_may_contain')}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {mayContainAllergens.map((allergen) => (
                            <Badge
                                key={allergen.slug}
                                variant="secondary"
                                className="text-muted-foreground text-[14px]"
                            >
                                {allergen.name}
                            </Badge>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
