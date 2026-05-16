import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { IngredientListItem } from '@/types/ingredient';

type IngredientRowProps = {
    ingredient: IngredientListItem;
    className?: string;
};

export function IngredientRow({ ingredient, className }: IngredientRowProps) {
    const { t } = useLaravelReactI18n();

    return (
        <Link
            href={`/ingredients/${ingredient.id}`}
            className={cn(
                'flex min-h-12 items-center gap-4 border-b border-border px-2 py-2 hover:bg-muted transition-colors',
                className,
            )}
        >
            {/* Left: names */}
            <div className="flex flex-1 flex-col">
                <span className="text-base font-normal leading-normal">{ingredient.name}</span>
                {ingredient.secondary_name && ingredient.secondary_name !== '—' && (
                    <span className="text-sm font-normal leading-snug text-muted-foreground">
                        {ingredient.secondary_name}
                    </span>
                )}
            </div>

            {/* Middle: calories */}
            <div className="w-20 shrink-0 text-right">
                <span className="text-sm font-normal text-muted-foreground">
                    {ingredient.energy_kcal !== null ? `${ingredient.energy_kcal} kcal` : '—'}
                </span>
            </div>

            {/* Right: allergen icons */}
            <div className="flex shrink-0 items-center">
                <AllergenIcons allergens={ingredient.allergens} />
            </div>

            {/* Rightmost: verified badge */}
            {ingredient.verified && (
                <div className="shrink-0">
                    <Badge className="bg-accent text-accent-foreground">
                        {t('app.ingredients.badge_verified')}
                    </Badge>
                </div>
            )}
        </Link>
    );
}
