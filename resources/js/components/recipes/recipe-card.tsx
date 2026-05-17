import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { GlobeIcon, UtensilsCrossedIcon, EllipsisVerticalIcon } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { Badge } from '@/components/ui/badge';
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
import { PublishRecipeDialog } from '@/components/recipes/publish-recipe-dialog';
import { UnpublishRecipeDialog } from '@/components/recipes/unpublish-recipe-dialog';
import { cn } from '@/lib/utils';
import { show as recipesShow } from '@/routes/recipes';
import type { RecipeCardData, RecipeVersion } from '@/types/recipe';

type RecipeCardProps = {
    recipe: RecipeCardData;
    /** Committed versions — needed for the publish dialog version picker. */
    versions?: RecipeVersion[];
    className?: string;
};

export function RecipeCard({ recipe, versions = [], className }: RecipeCardProps) {
    const { t } = useTranslations();
    const [publishOpen, setPublishOpen] = useState(false);
    const [unpublishOpen, setUnpublishOpen] = useState(false);

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
        <>
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
                        {/* Card header row: name + actions menu */}
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="line-clamp-1 text-[20px] font-semibold leading-tight group-hover:underline flex-1">
                                {recipe.name}
                            </h3>

                            {/* Card actions menu */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7 shrink-0"
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

                        {/* Published badge */}
                        {recipe.is_published && (
                            <Badge variant="secondary" className="flex w-fit items-center gap-1 whitespace-nowrap text-[14px]">
                                <GlobeIcon className="size-3" />
                                {t('app.recipes.published_badge')}
                            </Badge>
                        )}

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

                        {/* Metrics row: cost + calories (kcal per portion) */}
                        <div className="flex items-center gap-3 text-[14px] text-muted-foreground">
                            {recipe.cost_per_portion !== null && (
                                <span>
                                    {t('app.recipes.card_cost', {
                                        currency: '€',
                                        amount: recipe.cost_per_portion ?? '',
                                    })}
                                </span>
                            )}
                            {recipe.calories_per_portion !== null && (
                                <span>
                                    {t('app.recipes.card_calories', {
                                        n: recipe.calories_per_portion ?? '',
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
