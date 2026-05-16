import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';

interface AllergenSectionProps {
    /** Allergen slugs that the recipe contains. */
    contains: string[];
    /** Allergen slugs that may be present (cross-contamination). */
    mayContain: string[];
}

/** Format a slug into a display name: "tree-nuts" → "Tree Nuts". */
function slugToName(slug: string): string {
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function AllergenSection({ contains, mayContain }: AllergenSectionProps) {
    const { t } = useLaravelReactI18n();
    const hasAllergens = contains.length > 0 || mayContain.length > 0;

    if (!hasAllergens) {
        return (
            <section className="space-y-2">
                <h3 className="text-base font-semibold">{t('app.recipes.metrics_allergens')}</h3>
                <p className="text-sm text-muted-foreground">
                    {t('app.recipes.metrics_allergens_none')}
                </p>
            </section>
        );
    }

    return (
        <section className="space-y-3">
            <h3 className="text-base font-semibold">{t('app.recipes.metrics_allergens')}</h3>

            {contains.length > 0 && (
                <div className="space-y-1.5">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {t('app.recipes.metrics_contains')}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {contains.map((slug) => (
                            <Badge
                                key={slug}
                                variant="outline"
                                className="text-destructive border-destructive/30"
                            >
                                {slugToName(slug)}
                            </Badge>
                        ))}
                    </div>
                </div>
            )}

            {mayContain.length > 0 && (
                <div className="space-y-1.5">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {t('app.recipes.metrics_may_contain')}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {mayContain.map((slug) => (
                            <Badge
                                key={slug}
                                variant="outline"
                                className="text-muted-foreground"
                            >
                                {slugToName(slug)}
                            </Badge>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}
