import { Link } from '@inertiajs/react';
import {
    ClockIcon,
    EllipsisVerticalIcon,
    FlameIcon,
    GlobeIcon,
    UsersIcon,
    UtensilsCrossedIcon,
    WalletIcon,
} from 'lucide-react';
import { useState } from 'react';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { PublishRecipeDialog } from '@/components/recipes/publish-recipe-dialog';
import { RecipeMacroBar } from '@/components/recipes/recipe-macro-bar';
import { UnpublishRecipeDialog } from '@/components/recipes/unpublish-recipe-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { show as recipesShow } from '@/routes/recipes';
import type { RecipeCardData, RecipeVersion } from '@/types/recipe';

type RecipeCardProps = {
    recipe: RecipeCardData;
    /** Committed versions — needed for the publish dialog version picker. */
    versions?: RecipeVersion[];
    className?: string;
};

/** Difficulty-coded dot colours for the hero meta row. */
const DIFFICULTY_DOT: Record<NonNullable<RecipeCardData['difficulty']>, string> = {
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

export function RecipeCard({ recipe, versions = [], className }: RecipeCardProps) {
    const { t } = useTranslations();
    const [publishOpen, setPublishOpen] = useState(false);
    const [unpublishOpen, setUnpublishOpen] = useState(false);

    const formattedTime = formatTime(recipe.total_time);
    const servings = recipe.portions !== null && recipe.portions > 0 ? recipe.portions : null;

    const allergenItems = (Array.isArray(recipe.allergen_slugs) ? recipe.allergen_slugs : []).map((slug) => ({
        slug,
        name: slug,
        state: 'contains' as const,
    }));

    const hasCost = recipe.cost_per_portion !== null;
    const hasCalories = recipe.calories_per_portion !== null;
    const hasMacros =
        toNumber(recipe.protein_per_portion) +
            toNumber(recipe.carbs_per_portion) +
            toNumber(recipe.fat_per_portion) >
        0;
    const hasBody = hasMacros || hasCost || hasCalories || allergenItems.length > 0;

    return (
        <>
            <Link href={recipesShow(recipe).url} className={cn('group block', className)}>
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

                        {/* Top row: cuisine + published pills (left), actions menu (right) */}
                        <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3">
                            <div className="flex flex-col items-start gap-1.5">
                                {recipe.cuisine && (
                                    <span className="rounded-full bg-black/55 px-2.5 py-1 text-[12px] font-medium text-white backdrop-blur-sm">
                                        {recipe.cuisine}
                                    </span>
                                )}
                                {recipe.is_published && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/95 px-2.5 py-1 text-[12px] font-medium text-white backdrop-blur-sm">
                                        <GlobeIcon className="size-3" />
                                        {t('app.recipes.published_badge')}
                                    </span>
                                )}
                            </div>

                            {/* Card actions menu */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 shrink-0 rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-black/65 hover:text-white"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                        }}
                                    >
                                        <EllipsisVerticalIcon className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" onClick={(e) => e.stopPropagation()}>
                                    {recipe.is_published ? (
                                        <DropdownMenuItem
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                setUnpublishOpen(true);
                                            }}
                                        >
                                            {t('app.recipes.unpublish_btn')}
                                        </DropdownMenuItem>
                                    ) : (
                                        <DropdownMenuItem
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                setPublishOpen(true);
                                            }}
                                        >
                                            {t('app.recipes.publish_btn')}
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <DropdownMenuItem
                                                    aria-disabled={recipe.is_published ? 'true' : undefined}
                                                    className={recipe.is_published ? 'cursor-not-allowed opacity-50 text-destructive focus:text-destructive' : 'text-destructive focus:text-destructive'}
                                                    onClick={(e) => {
                                                        if (recipe.is_published) {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                        }
                                                    }}
                                                >
                                                    {t('app.recipes.delete_menu')}
                                                </DropdownMenuItem>
                                            </TooltipTrigger>
                                            {recipe.is_published && (
                                                <TooltipContent>
                                                    <p>{t('app.recipes.delete_blocked_tooltip')}</p>
                                                </TooltipContent>
                                            )}
                                        </Tooltip>
                                    </TooltipProvider>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

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

                    {/* Card body: macro breakdown + metric chips + allergens */}
                    {hasBody && (
                        <div className="flex flex-col gap-3 p-4">
                            {hasMacros && (
                                <RecipeMacroBar
                                    protein={recipe.protein_per_portion}
                                    carbs={recipe.carbs_per_portion}
                                    fat={recipe.fat_per_portion}
                                />
                            )}

                            {(hasCost || hasCalories) && (
                                <div className="flex flex-wrap items-center gap-2">
                                    {hasCalories && (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1.5 text-[13px] font-medium">
                                            <FlameIcon className="size-3.5 text-orange-500" />
                                            {t('app.recipes.card_calories', {
                                                n: recipe.calories_per_portion ?? '',
                                            })}
                                        </span>
                                    )}
                                    {hasCost && (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1.5 text-[13px] font-medium">
                                            <WalletIcon className="size-3.5 text-amber-600" />
                                            {t('app.recipes.card_cost', {
                                                currency: '€',
                                                amount: recipe.cost_per_portion ?? '',
                                            })}
                                        </span>
                                    )}
                                </div>
                            )}

                            {allergenItems.length > 0 && (
                                <div className="flex flex-wrap items-center gap-1">
                                    <AllergenIcons allergens={allergenItems.slice(0, 14)} />
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </Link>

            {/* Publish dialog (rendered outside the Link so it does not trigger navigation) */}
            <PublishRecipeDialog
                recipeId={recipe.id}
                versions={versions}
                open={publishOpen}
                onOpenChange={setPublishOpen}
            />

            {/* Unpublish dialog */}
            <UnpublishRecipeDialog
                recipeId={recipe.id}
                open={unpublishOpen}
                onOpenChange={setUnpublishOpen}
            />
        </>
    );
}
