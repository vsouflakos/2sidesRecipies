import { Link } from '@inertiajs/react';
import { UtensilsCrossedIcon } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { show as libraryShow } from '@/routes/library';
import type { PublicRecipeCardData } from '@/types/recipe';

type LibraryRecipeCardProps = {
    recipe: PublicRecipeCardData;
    className?: string;
};

/**
 * Card variant for the public library grid.
 * Links to library.show (not recipes.show). Never renders cost data.
 * Adds author attribution below the cuisine badge.
 */
export function LibraryRecipeCard({ recipe, className }: LibraryRecipeCardProps) {
    const { t } = useTranslations();

    const totalTime =
        (recipe.prep_time_minutes ?? 0) + (recipe.cook_time_minutes ?? 0);

    const formattedTime =
        totalTime > 0
            ? totalTime >= 60
                ? `${Math.floor(totalTime / 60)}h ${totalTime % 60 > 0 ? `${totalTime % 60}m` : ''}`.trim()
                : `${totalTime} min`
            : null;

    const allergenItems = (Array.isArray(recipe.allergen_slugs) ? recipe.allergen_slugs : []).map((slug) => ({
        slug,
        name: slug,
        state: 'contains' as const,
    }));

    return (
        <Link href={libraryShow({ slug: recipe.slug }).url} className={cn('group block', className)}>
            <Card className="overflow-hidden gap-0 py-0 transition-shadow hover:shadow-md">
                {/* Hero image */}
                <div className="aspect-video w-full overflow-hidden bg-muted">
                    {recipe.hero_image_path ? (
                        <img
                            src={recipe.hero_image_path}
                            alt={recipe.name}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center bg-muted">
                            <UtensilsCrossedIcon
                                className="size-8 text-muted-foreground"
                                aria-label={t('app.recipes.card_no_image')}
                            />
                        </div>
                    )}
                </div>

                {/* Card body */}
                <div className="flex flex-col gap-2 p-4">
                    {/* Recipe name */}
                    <h3 className="line-clamp-1 text-[20px] font-semibold leading-tight group-hover:underline">
                        {recipe.name}
                    </h3>

                    {/* Cuisine badge */}
                    {recipe.cuisine && (
                        <Badge variant="secondary" className="w-fit text-[14px]">
                            {recipe.cuisine}
                        </Badge>
                    )}

                    {/* Author attribution */}
                    <span className="text-[14px] text-muted-foreground">
                        {t('app.library.by', { name: recipe.author_name })}
                    </span>

                    {/* Metadata row: time + difficulty */}
                    <div className="flex items-center gap-2 text-[14px] text-muted-foreground">
                        {formattedTime && <span>{formattedTime}</span>}
                        {formattedTime && recipe.difficulty && (
                            <span aria-hidden="true">·</span>
                        )}
                        {recipe.difficulty && (
                            <Badge variant="outline" className="text-[14px] font-normal text-muted-foreground">
                                {t(`app.recipes.difficulty_${recipe.difficulty}`)}
                            </Badge>
                        )}
                    </div>

                    {/* Calories (NO cost — public page never shows cost) */}
                    {recipe.calories_per_portion !== null && (
                        <div className="flex items-center gap-3 text-[14px] text-muted-foreground">
                            <span>
                                {t('app.recipes.card_calories', {
                                    n: recipe.calories_per_portion ?? '',
                                })}
                            </span>
                        </div>
                    )}

                    {/* Allergen icons */}
                    {allergenItems.length > 0 && (
                        <div className="flex flex-wrap items-center gap-1">
                            <AllergenIcons allergens={allergenItems.slice(0, 14)} />
                        </div>
                    )}
                </div>
            </Card>
        </Link>
    );
}
