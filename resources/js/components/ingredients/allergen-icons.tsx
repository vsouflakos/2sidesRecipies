import {
    AlertTriangle,
    Bean,
    Egg,
    Fish,
    FlaskConical,
    Leaf,
    Milk,
    Nut,
    Shell,
    Wheat,
} from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { IngredientAllergen } from '@/types/ingredient';

type AllergenIconProps = {
    allergens: IngredientAllergen[];
};

/**
 * Maps EU allergen slugs to lucide icons.
 */
const allergenIconMap: Record<string, React.ElementType> = {
    gluten: Wheat,
    crustaceans: Shell,
    eggs: Egg,
    fish: Fish,
    peanuts: Nut,
    soybeans: Bean,
    milk: Milk,
    nuts: Nut,
    celery: Leaf,
    mustard: Leaf,
    sesame: FlaskConical,
    sulphur_dioxide: FlaskConical,
    lupin: Bean,
    molluscs: Shell,
};

export function AllergenIcons({ allergens }: AllergenIconProps) {
    if (allergens.length === 0) {
        return null;
    }

    return (
        <TooltipProvider>
            <div className="flex items-center gap-1">
                {allergens.map((allergen) => {
                    const Icon = allergenIconMap[allergen.slug] ?? AlertTriangle;
                    const isContains = allergen.state === 'contains';

                    return (
                        <Tooltip key={allergen.slug}>
                            <TooltipTrigger asChild>
                                <span
                                    aria-label={allergen.name}
                                    className={cn(
                                        'inline-flex items-center justify-center',
                                        !isContains && 'opacity-50',
                                    )}
                                >
                                    <Icon
                                        className="text-destructive size-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{allergen.name}</p>
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </div>
        </TooltipProvider>
    );
}
