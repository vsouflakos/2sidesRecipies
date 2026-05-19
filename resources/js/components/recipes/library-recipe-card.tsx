import { Link } from '@inertiajs/react';
import { ChefHatIcon, ClockIcon, FlameIcon, UsersIcon, UtensilsCrossedIcon } from 'lucide-react';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { RecipeMacroBar } from '@/components/recipes/recipe-macro-bar';
import { Card } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { show as libraryShow } from '@/routes/library';
import type { PublicRecipeCardData } from '@/types/recipe';

type LibraryRecipeCardProps = {
    recipe: PublicRecipeCardData;
    className?: string;
};

/** Difficulty-coded dot colours for the hero meta row. */
const DIFFICULTY_DOT: Record<NonNullable<PublicRecipeCardData['difficulty']>, string> = {
    easy: 'bg-emerald-400',
    medium: 'bg-amber-400',
    hard: 'bg-orange-500',
    expert: 'bg-rose-500',
};

/** Format a total minute count into a compact "1h 20m" / "45 min" label. */
function formatTime(totalTime: number | null): string | null {
    if (!totalTime || totalTime <= 0) {
        return null;
    }

    if (totalTime >= 60) {
        const remainder = totalTime % 60;

        return `${Math.floor(totalTime / 60)}h ${remainder > 0 ? `${remainder}m` : ''}`.trim();
    }

    return `${totalTime} min`;
}

/** Parse an API numeric string into a non-negative number. */
function toNumber(value: string | null): number {
    const parsed = Number.parseFloat(value ?? '');

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/**
 * Card variant for the public library grid.
 * Links to library.show (not recipes.show). Never renders cost data.
 * Adds author attribution below the metric chips.
 */
export function LibraryRecipeCard({ recipe, className }: LibraryRecipeCardProps) {
    const { t } = useTranslations();

    const formattedTime = formatTime(recipe.total_time);
    const servings = recipe.portions !== null && recipe.portions > 0 ? recipe.portions : null;

    const allergenItems = (Array.isArray(recipe.allergen_slugs) ? recipe.allergen_slugs : []).map((slug) => ({
        slug,
        name: slug,
        state: 'contains' as const,
    }));

    const hasCalories = recipe.calories_per_portion !== null;
    const hasMacros =
        toNumber(recipe.protein_per_portion) +
            toNumber(recipe.carbs_per_portion) +
            toNumber(recipe.fat_per_portion) >
        0;

    return (
        <Link href={libraryShow({ slug: recipe.slug }).url} className={cn('group block', className)}>
            <Card className="gap-0 overflow-hidden rounded-2xl border-border/70 py-0 shadow-sm transition-all duration-300 group-hover:-translate-y-1 group-hover:border-amber-300/70 group-hover:shadow-xl">
                {/* Hero image with overlaid title + meta */}
                <div className="relative aspect-[4/3] w-full overflow-hidden">
                    {recipe.hero_image_path ? (
                        <img
                            src={recipe.hero_image_path}
                            alt={recipe.name}
                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.06]"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-amber-100 via-orange-100 to-rose-100 dark:from-amber-950/60 dark:via-orange-950/50 dark:to-rose-950/60">
                            <UtensilsCrossedIcon
                                className="size-12 text-orange-400/70 dark:text-orange-700/70"
                                aria-label={t('app.recipes.card_no_image')}
                            />
                        </div>
                    )}

                    {/* Legibility gradient */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/5" />

                    {/* Top: cuisine pill */}
                    {recipe.cuisine && (
                        <div className="absolute inset-x-0 top-0 p-3">
                            <span className="rounded-full bg-black/55 px-2.5 py-1 text-[12px] font-medium text-white backdrop-blur-sm">
                                {recipe.cuisine}
                            </span>
                        </div>
                    )}

                    {/* Bottom: title + time/difficulty/servings meta */}
                    <div className="absolute inset-x-0 bottom-0 p-4">
                        <h3 className="line-clamp-2 text-[19px] font-bold leading-snug text-white drop-shadow-md">
                            {recipe.name}
                        </h3>

                        {(formattedTime || recipe.difficulty || servings !== null) && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] font-medium text-white/90">
                                {formattedTime && (
                                    <span className="inline-flex items-center gap-1">
                                        <ClockIcon className="size-3.5" />
                                        {formattedTime}
                                    </span>
                                )}
                                {recipe.difficulty && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className={cn('size-2 rounded-full', DIFFICULTY_DOT[recipe.difficulty])} />
                                        {t(`app.recipes.difficulty_${recipe.difficulty}`)}
                                    </span>
                                )}
                                {servings !== null && (
                                    <span className="inline-flex items-center gap-1">
                                        <UsersIcon className="size-3.5" />
                                        {t('app.recipes.card_serves', { n: servings })}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Card body: macro breakdown + calories chip + author + allergens */}
                <div className="flex flex-col gap-3 p-4">
                    {hasMacros && (
                        <RecipeMacroBar
                            protein={recipe.protein_per_portion}
                            carbs={recipe.carbs_per_portion}
                            fat={recipe.fat_per_portion}
                        />
                    )}

                    {/* Calories (NO cost — public page never shows cost) */}
                    {hasCalories && (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1.5 text-[13px] font-medium">
                                <FlameIcon className="size-3.5 text-orange-500" />
                                {t('app.recipes.card_calories', {
                                    n: recipe.calories_per_portion ?? '',
                                })}
                            </span>
                        </div>
                    )}

                    {/* Author attribution */}
                    <div className="flex items-center gap-1.5 text-[13px] text-muted-foreground">
                        <ChefHatIcon className="size-3.5 shrink-0" />
                        <span className="truncate">
                            {t('app.library.by', { name: recipe.author_name })}
                        </span>
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
