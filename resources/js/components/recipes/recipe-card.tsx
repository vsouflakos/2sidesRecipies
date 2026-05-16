import { Link } from '@inertiajs/react';
import { UtensilsCrossedIcon } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { show as recipesShow } from '@/routes/recipes';
import type { RecipeCardData } from '@/types/recipe';

type RecipeCardProps = {
    recipe: RecipeCardData;
    className?: string;
};

export function RecipeCard({ recipe, className }: RecipeCardProps) {
    const { t } = useLaravelReactI18n();

    const totalTime =
        (recipe.prep_time_minutes ?? 0) + (recipe.cook_time_minutes ?? 0);

    const formattedTime =
        totalTime > 0
            ? totalTime >= 60
                ? `${Math.floor(totalTime / 60)}h ${totalTime % 60 > 0 ? `${totalTime % 60}m` : ''}`.trim()
                : `${totalTime} min`
            : null;

    const allergenItems = recipe.allergen_slugs.map((slug) => ({
        slug,
        name: slug,
        state: 'contains' as const,
    }));

    return (
        <Link href={recipesShow(recipe).url} className={cn('group block', className)}>
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

                    {/* Metrics row: cost + calories */}
                    <div className="flex items-center gap-3 text-[14px] text-muted-foreground">
                        {recipe.cost_per_portion !== null && (
                            <span>
                                {t('app.recipes.card_cost', {
                                    currency: '€',
                                    amount: recipe.cost_per_portion,
                                })}
                            </span>
                        )}
                        {recipe.calories_per_portion !== null && (
                            <span>
                                {t('app.recipes.card_calories', {
                                    n: recipe.calories_per_portion,
                                })}
                            </span>
                        )}
                    </div>

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
