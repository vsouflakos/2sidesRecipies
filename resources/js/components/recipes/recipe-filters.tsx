import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
// onFiltersChange triggers router.reload({ only: ['recipes'], preserveState: true, replace: true }) in the parent
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { AllergenOption } from '@/types/ingredient';
import type { CuisineOption, TagOption } from '@/types/recipe';

export interface RecipeFiltersState {
    search: string;
    tag: number | null;
    cuisine: number | null;
    allergen: string | null;
    ingredient: number | null;
    difficulty: string | null;
    max_total_time: number | null;
}

type RecipeFiltersProps = {
    filters: RecipeFiltersState;
    cuisines: CuisineOption[];
    allergens: AllergenOption[];
    tags: TagOption[];
    onFiltersChange: (updated: Partial<RecipeFiltersState>) => void;
};

type FiltersBodyProps = RecipeFiltersProps & {
    className?: string;
};

function countActiveFilters(filters: RecipeFiltersState): number {
    let count = 0;
    if (filters.tag) { count++; }
    if (filters.cuisine) { count++; }
    if (filters.allergen) { count++; }
    if (filters.ingredient) { count++; }
    if (filters.difficulty) { count++; }
    if (filters.max_total_time) { count++; }
    return count;
}

function FiltersBody({ filters, cuisines, allergens, tags, onFiltersChange, className }: FiltersBodyProps) {
    const { t } = useLaravelReactI18n();
    const [pendingAllergen, setPendingAllergen] = useState<string | null>(filters.allergen);
    const [allergenPopoverOpen, setAllergenPopoverOpen] = useState(false);
    const [tagCommandOpen, setTagCommandOpen] = useState(false);

    function handleAllergenPopoverOpenChange(open: boolean) {
        setAllergenPopoverOpen(open);
        if (!open) {
            onFiltersChange({ allergen: pendingAllergen });
        }
    }

    function toggleAllergen(slug: string, checked: boolean) {
        setPendingAllergen(checked ? slug : null);
    }

    return (
        <div className={cn('flex flex-wrap gap-4', className)}>
            {/* Tags filter — Command multi-select */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.recipes.filter_tags')}
                </Label>
                <Popover open={tagCommandOpen} onOpenChange={setTagCommandOpen}>
                    <PopoverTrigger asChild>
                        <Button variant="outline" className="w-44 justify-between">
                            {filters.tag
                                ? tags.find((t) => t.id === filters.tag)?.name ?? t('app.recipes.filter_tags')
                                : t('app.recipes.filter_tags')}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-56 p-0" align="start">
                        <Command>
                            <CommandInput placeholder={t('app.recipes.filter_tags')} />
                            <CommandList>
                                <CommandEmpty>{t('app.recipes.filter_tags')}</CommandEmpty>
                                <CommandGroup>
                                    {tags.map((tag) => (
                                        <CommandItem
                                            key={tag.id}
                                            value={tag.name}
                                            onSelect={() => {
                                                onFiltersChange({ tag: filters.tag === tag.id ? null : tag.id });
                                                setTagCommandOpen(false);
                                            }}
                                        >
                                            <span className={cn(filters.tag === tag.id && 'font-medium')}>
                                                {tag.name}
                                            </span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>

            {/* Cuisine filter */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.recipes.filter_cuisine')}
                </Label>
                <Select
                    value={filters.cuisine ? String(filters.cuisine) : ''}
                    onValueChange={(value) =>
                        onFiltersChange({ cuisine: value ? Number(value) : null })
                    }
                >
                    <SelectTrigger className="w-44">
                        <SelectValue placeholder={t('app.recipes.filter_cuisine')} />
                    </SelectTrigger>
                    <SelectContent>
                        {cuisines.map((cuisine) => (
                            <SelectItem key={cuisine.id} value={String(cuisine.id)}>
                                {cuisine.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Allergen-free filter — Popover + Checkbox, reload on close */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.recipes.filter_allergen')}
                </Label>
                <Popover open={allergenPopoverOpen} onOpenChange={handleAllergenPopoverOpenChange}>
                    <PopoverTrigger asChild>
                        <Button variant="outline" className="w-44 justify-between">
                            {pendingAllergen
                                ? allergens.find((a) => a.slug === pendingAllergen)?.name ?? t('app.recipes.filter_allergen')
                                : t('app.recipes.filter_allergen')}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-56" align="start">
                        <div className="flex flex-col gap-2">
                            {allergens.map((allergen) => (
                                <div key={allergen.slug} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`recipe-allergen-${allergen.slug}`}
                                        checked={pendingAllergen === allergen.slug}
                                        onCheckedChange={(checked) =>
                                            toggleAllergen(allergen.slug, Boolean(checked))
                                        }
                                    />
                                    <Label
                                        htmlFor={`recipe-allergen-${allergen.slug}`}
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

            {/* Difficulty filter */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.recipes.filter_difficulty')}
                </Label>
                <Select
                    value={filters.difficulty ?? ''}
                    onValueChange={(value) =>
                        onFiltersChange({ difficulty: value || null })
                    }
                >
                    <SelectTrigger className="w-44">
                        <SelectValue placeholder={t('app.recipes.filter_difficulty')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="easy">{t('app.recipes.difficulty_easy')}</SelectItem>
                        <SelectItem value="medium">{t('app.recipes.difficulty_medium')}</SelectItem>
                        <SelectItem value="hard">{t('app.recipes.difficulty_hard')}</SelectItem>
                        <SelectItem value="expert">{t('app.recipes.difficulty_expert')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {/* Time range filter */}
            <div className="flex flex-col gap-1.5">
                <Label className="text-sm text-muted-foreground">
                    {t('app.recipes.filter_time')}
                </Label>
                <Select
                    value={filters.max_total_time ? String(filters.max_total_time) : ''}
                    onValueChange={(value) =>
                        onFiltersChange({ max_total_time: value ? Number(value) : null })
                    }
                >
                    <SelectTrigger className="w-44">
                        <SelectValue placeholder={t('app.recipes.filter_time')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="30">{t('app.recipes.time_under_30')}</SelectItem>
                        <SelectItem value="60">{t('app.recipes.time_30_60')}</SelectItem>
                        <SelectItem value="120">{t('app.recipes.time_1_2h')}</SelectItem>
                        <SelectItem value="9999">{t('app.recipes.time_over_2h')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}

export function RecipeFilters({ filters, cuisines, allergens, tags, onFiltersChange }: RecipeFiltersProps) {
    const { t } = useLaravelReactI18n();
    const [isOpen, setIsOpen] = useState(false);
    const activeCount = countActiveFilters(filters);

    return (
        <>
            {/* Desktop: Collapsible filter panel */}
            <div className="hidden md:block">
                <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" className="gap-2">
                            <SlidersHorizontal className="size-4" />
                            {t('app.recipes.filter_btn')}
                            {activeCount > 0 && (
                                <Badge className="ml-1 size-5 rounded-full p-0 text-xs">
                                    {activeCount}
                                </Badge>
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-4">
                        <FiltersBody
                            filters={filters}
                            cuisines={cuisines}
                            allergens={allergens}
                            tags={tags}
                            onFiltersChange={onFiltersChange}
                        />
                    </CollapsibleContent>
                </Collapsible>
            </div>

            {/* Mobile: Sheet */}
            <div className="md:hidden">
                <Sheet>
                    <SheetTrigger asChild>
                        <Button variant="outline" className="gap-2">
                            <SlidersHorizontal className="size-4" />
                            {t('app.recipes.filter_btn')}
                            {activeCount > 0 && (
                                <Badge className="ml-1 size-5 rounded-full p-0 text-xs">
                                    {activeCount}
                                </Badge>
                            )}
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="max-h-[80vh]">
                        <SheetHeader>
                            <SheetTitle>{t('app.recipes.filter_btn')}</SheetTitle>
                        </SheetHeader>
                        <FiltersBody
                            filters={filters}
                            cuisines={cuisines}
                            allergens={allergens}
                            tags={tags}
                            onFiltersChange={onFiltersChange}
                            className="mt-4"
                        />
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
