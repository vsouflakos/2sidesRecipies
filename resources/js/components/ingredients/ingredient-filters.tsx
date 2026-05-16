import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { AllergenOption, IngredientFilters } from '@/types/ingredient';

type IngredientFiltersProps = {
    filters: IngredientFilters;
    allergens: AllergenOption[];
    onFiltersChange: (filters: Partial<IngredientFilters>) => void;
};

type FiltersBodyProps = {
    filters: IngredientFilters;
    allergens: AllergenOption[];
    onFiltersChange: (filters: Partial<IngredientFilters>) => void;
    className?: string;
};

function FiltersBody({ filters, allergens, onFiltersChange, className }: FiltersBodyProps) {
    const { t } = useLaravelReactI18n();
    const [pendingAllergenFree, setPendingAllergenFree] = useState<string[]>(filters.allergen_free);
    const [allergenPopoverOpen, setAllergenPopoverOpen] = useState(false);

    function handleAllergenPopoverOpenChange(open: boolean) {
        setAllergenPopoverOpen(open);
        if (!open) {
            // Fire reload on popover close per UI-SPEC interaction contract
            onFiltersChange({ allergen_free: pendingAllergenFree });
        }
    }

    function toggleAllergen(slug: string, checked: boolean) {
        setPendingAllergenFree((prev) =>
            checked ? [...prev, slug] : prev.filter((s) => s !== slug),
        );
    }

    return (
        <div className={cn('flex flex-col gap-4 md:flex-row md:items-center', className)}>
            {/* Source filter */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.ingredients.filter_source')}
                </Label>
                <Select
                    value={filters.source}
                    onValueChange={(value) => onFiltersChange({ source: value as IngredientFilters['source'] })}
                >
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{t('app.ingredients.filter_source_all')}</SelectItem>
                        <SelectItem value="official">{t('app.ingredients.filter_source_official')}</SelectItem>
                        <SelectItem value="private">{t('app.ingredients.filter_source_private')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {/* Allergen-free filter */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.ingredients.filter_allergen')}
                </Label>
                <Popover open={allergenPopoverOpen} onOpenChange={handleAllergenPopoverOpenChange}>
                    <PopoverTrigger asChild>
                        <Button variant="outline" className="w-44 justify-between">
                            {pendingAllergenFree.length > 0
                                ? `${pendingAllergenFree.length} selected`
                                : t('app.ingredients.filter_allergen')}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-56" align="start">
                        <div className="flex flex-col gap-2">
                            {allergens.map((allergen) => (
                                <div key={allergen.slug} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`allergen-${allergen.slug}`}
                                        checked={pendingAllergenFree.includes(allergen.slug)}
                                        onCheckedChange={(checked) =>
                                            toggleAllergen(allergen.slug, Boolean(checked))
                                        }
                                    />
                                    <Label
                                        htmlFor={`allergen-${allergen.slug}`}
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        {allergen.name}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            </div>

            {/* Verified only filter */}
            <div className="flex items-center gap-2 pt-5 md:pt-0">
                <Checkbox
                    id="verified-only"
                    checked={filters.verified_only}
                    onCheckedChange={(checked) => onFiltersChange({ verified_only: Boolean(checked) })}
                />
                <Label htmlFor="verified-only" className="cursor-pointer text-sm font-normal">
                    {t('app.ingredients.filter_verified')}
                </Label>
            </div>
        </div>
    );
}

export function IngredientFilters({ filters, allergens, onFiltersChange }: IngredientFiltersProps) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            {/* Desktop: inline filter bar */}
            <div className="hidden md:block">
                <FiltersBody
                    filters={filters}
                    allergens={allergens}
                    onFiltersChange={onFiltersChange}
                />
            </div>

            {/* Mobile: filters in a Sheet */}
            <div className="md:hidden">
                <Sheet>
                    <SheetTrigger asChild>
                        <Button variant="outline" className="gap-2">
                            <SlidersHorizontal className="size-4" />
                            Filters
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="max-h-[80vh]">
                        <SheetHeader>
                            <SheetTitle>Filters</SheetTitle>
                        </SheetHeader>
                        <FiltersBody
                            filters={filters}
                            allergens={allergens}
                            onFiltersChange={onFiltersChange}
                            className="mt-4"
                        />
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
