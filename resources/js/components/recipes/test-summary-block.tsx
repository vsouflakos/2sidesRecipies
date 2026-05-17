import { Link } from '@inertiajs/react';
import { index as recipeTestsIndex } from '@/actions/App/Http/Controllers/Recipes/RecipeTestController';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { TestSummary } from '@/types/recipe';

interface TestSummaryBlockProps {
    recipeId: number;
    summary: TestSummary;
}

/**
 * Compact horizontal strip showing test count + latest score on the recipe builder.
 *
 * Placed below the MetricsPanel in the right-side builder column (desktop) and at
 * the bottom of the left builder column on mobile/tablet (where the right panel is hidden).
 */
export function TestSummaryBlock({ recipeId, summary }: TestSummaryBlockProps) {
    const { t } = useTranslations();

    const testsUrl = recipeTestsIndex({ recipe: recipeId }).url;

    return (
        <div
            className={cn(
                'flex items-center justify-between border-t border-border p-4',
            )}
        >
            {summary.count > 0 ? (
                <p className="text-base text-foreground">
                    {t('app.tests.summary_has_tests', {
                        count: summary.count,
                        score: summary.latest_score ?? '—',
                    })}
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('app.tests.summary_no_tests')}
                </p>
            )}

            <Button asChild variant="ghost" size="sm">
                <Link href={testsUrl}>
                    {summary.count > 0
                        ? t('app.tests.summary_link')
                        : t('app.tests.summary_link_empty')}
                </Link>
            </Button>
        </div>
    );
}
