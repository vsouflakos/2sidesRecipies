import { router, useForm } from '@inertiajs/react';
import { ChevronDownIcon, ChevronUpIcon } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { toast } from 'sonner';
import { AllergenChecklist } from '@/components/ingredients/allergen-checklist';
import { ConversionRows } from '@/components/ingredients/conversion-rows';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    AllergenFormEntry,
    AllergenOption,
    CategoryNode,
    ConversionFormEntry,
    IngredientDetail,
    IngredientFormData,
    NutritionData,
    UnitOption,
} from '@/types/ingredient';

interface IngredientFormProps {
    categories: CategoryNode[];
    allergens: AllergenOption[];
    units: UnitOption[];
    ingredient?: IngredientDetail;
    duplicate?: IngredientDetail;
    isEdit?: boolean;
}

const NUTRITION_GROUPS = [
    { label: 'Energy', fields: ['energy_kcal'] },
    { label: 'Macros', fields: ['protein_g', 'fat_g', 'carbs_g'] },
    { label: 'Fat detail', fields: ['saturated_fat_g', 'monounsaturated_fat_g', 'polyunsaturated_fat_g'] },
    { label: 'Carb detail', fields: ['sugars_g', 'starch_g', 'fibre_g'] },
    {
        label: 'Minerals',
        fields: ['sodium_mg', 'calcium_mg', 'iron_mg', 'magnesium_mg', 'phosphorus_mg', 'potassium_mg', 'zinc_mg'],
    },
    {
        label: 'Vitamins',
        fields: [
            'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg', 'vitamin_b3_mg',
            'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug', 'vitamin_c_mg',
            'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug',
        ],
    },
    { label: 'Other', fields: ['cholesterol_mg'] },
] as const;

function buildInitialData(
    ingredient?: IngredientDetail,
    duplicate?: IngredientDetail,
): IngredientFormData {
    const source = ingredient ?? duplicate;

    if (!source) {
        return {
            name_en: '',
            name_el: '',
            category_id: '',
            allergens: [],
            conversions: [],
        };
    }

    const nameEn = source.translations.find((t) => t.locale === 'en')?.name ?? '';
    const nameEl = source.translations.find((t) => t.locale === 'el')?.name ?? '';

    const nutrition: NutritionData = {};
    const nutritionKeys: (keyof NutritionData)[] = [
        'energy_kcal', 'protein_g', 'fat_g', 'saturated_fat_g', 'monounsaturated_fat_g',
        'polyunsaturated_fat_g', 'carbs_g', 'sugars_g', 'starch_g', 'fibre_g',
        'sodium_mg', 'calcium_mg', 'iron_mg', 'magnesium_mg', 'phosphorus_mg',
        'potassium_mg', 'zinc_mg', 'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg',
        'vitamin_b3_mg', 'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug',
        'vitamin_c_mg', 'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug', 'cholesterol_mg',
    ];

    for (const key of nutritionKeys) {
        const val = source[key];
        if (val !== null && val !== undefined) {
            nutrition[key] = String(val);
        }
    }

    const allergens: AllergenFormEntry[] = source.allergens.map((a) => ({
        allergen_id: a.id,
        state: a.pivot.state,
    }));

    const conversions: ConversionFormEntry[] = source.conversions.map((c) => ({
        from_amount: String(c.from_amount),
        from_unit_id: c.from_unit_id,
        gram_weight: String(c.gram_weight),
        modifier: c.modifier ?? '',
    }));

    return {
        name_en: nameEn,
        name_el: nameEl,
        category_id: source.category_id,
        ...nutrition,
        allergens,
        conversions,
    };
}

export function IngredientForm({
    categories,
    allergens,
    units,
    ingredient,
    duplicate,
    isEdit = false,
}: IngredientFormProps) {
    const { t } = useLaravelReactI18n();

    const { data, setData, post, put, processing, errors } = useForm<IngredientFormData>(
        buildInitialData(ingredient, duplicate),
    );

    const [startMode, setStartMode] = useState<'blank' | 'duplicate'>(
        duplicate ? 'duplicate' : 'blank',
    );
    const [duplicateOpen, setDuplicateOpen] = useState(false);
    const [duplicateSearch, setDuplicateSearch] = useState('');
    const [nutritionOpen, setNutritionOpen] = useState(false);
    const [allergensOpen, setAllergensOpen] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit && ingredient) {
            put(`/ingredients/${ingredient.id}`, { preserveScroll: true });
        } else {
            post('/ingredients', { preserveScroll: true });
        }
    }

    function handleDuplicatePick(source: IngredientDetail) {
        const filled = buildInitialData(undefined, source);
        Object.keys(filled).forEach((key) => {
            setData(key as keyof IngredientFormData, (filled as Record<string, unknown>)[key] as IngredientFormData[keyof IngredientFormData]);
        });

        const name = source.translations.find((t) => t.locale === 'en')?.name ?? source.name_cache;
        toast.success(
            t('app.ingredients.create_duplicate_toast', { name }),
        );
        setDuplicateOpen(false);
    }

    const categoryValue = data.category_id !== '' ? String(data.category_id) : undefined;

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Name fields */}
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="name_en">
                        {t('app.ingredients.create_name_en')}
                        <span className="ml-1 text-destructive">*</span>
                    </Label>
                    <Input
                        id="name_en"
                        value={data.name_en}
                        onChange={(e) => setData('name_en', e.target.value)}
                        placeholder="e.g. Olive Oil"
                    />
                    <InputError message={errors.name_en} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="name_el">
                        {t('app.ingredients.create_name_el')}
                    </Label>
                    <Input
                        id="name_el"
                        value={data.name_el}
                        onChange={(e) => setData('name_el', e.target.value)}
                        placeholder="π.χ. Ελαιόλαδο"
                    />
                    <InputError message={errors.name_el} />
                </div>
            </div>

            {/* Category */}
            <div className="space-y-2">
                <Label htmlFor="category_id">
                    {t('app.ingredients.create_category')}
                    <span className="ml-1 text-destructive">*</span>
                </Label>
                <Select
                    value={categoryValue}
                    onValueChange={(v) => setData('category_id', Number(v))}
                >
                    <SelectTrigger id="category_id" className="w-full">
                        <SelectValue placeholder="Select a category" />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((parent) => {
                            const hasChildren = parent.children && parent.children.length > 0;

                            if (hasChildren) {
                                return (
                                    <SelectGroup key={parent.id}>
                                        <SelectLabel>{parent.name}</SelectLabel>
                                        {parent.children!.map((child) => (
                                            <SelectItem key={child.id} value={String(child.id)}>
                                                {child.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                );
                            }

                            return (
                                <SelectItem key={parent.id} value={String(parent.id)}>
                                    {parent.name}
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>
                <InputError message={errors.category_id} />
            </div>

            {/* Starting point (create mode only) */}
            {!isEdit && (
                <div className="space-y-3">
                    <Label>Starting point</Label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={startMode}
                        onValueChange={(v) => {
                            if (v) setStartMode(v as 'blank' | 'duplicate');
                        }}
                    >
                        <ToggleGroupItem value="blank" className="px-4">
                            {t('app.ingredients.create_blank')}
                        </ToggleGroupItem>
                        <ToggleGroupItem value="duplicate" className="px-4">
                            {t('app.ingredients.create_duplicate')}
                        </ToggleGroupItem>
                    </ToggleGroup>

                    {startMode === 'duplicate' && (
                        <Popover open={duplicateOpen} onOpenChange={setDuplicateOpen}>
                            <PopoverTrigger asChild>
                                <Button variant="outline" type="button" className="w-full justify-start font-normal">
                                    {t('app.ingredients.create_duplicate_placeholder')}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-96 p-0" align="start">
                                <Command>
                                    <CommandInput
                                        placeholder={t('app.ingredients.create_duplicate_placeholder')}
                                        value={duplicateSearch}
                                        onValueChange={setDuplicateSearch}
                                    />
                                    <CommandList>
                                        <CommandEmpty>No official ingredients found.</CommandEmpty>
                                        <CommandGroup>
                                            {/* Search results come from server — link to duplicate flow */}
                                            <CommandItem
                                                onSelect={() => {
                                                    router.visit(`/ingredients/create?search=${duplicateSearch}`, {
                                                        replace: true,
                                                    });
                                                }}
                                            >
                                                Search the official library for "{duplicateSearch}"…
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    )}
                </div>
            )}

            {/* Nutrition section */}
            <Collapsible open={nutritionOpen} onOpenChange={setNutritionOpen}>
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="flex w-full items-center justify-between rounded-md border px-4 py-3 text-sm font-medium hover:bg-muted"
                    >
                        <span>Nutrition (per 100g)</span>
                        {nutritionOpen ? (
                            <ChevronUpIcon className="size-4 text-muted-foreground" />
                        ) : (
                            <ChevronDownIcon className="size-4 text-muted-foreground" />
                        )}
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent className="mt-4 space-y-6">
                    {NUTRITION_GROUPS.map((group) => (
                        <div key={group.label}>
                            <h4 className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {group.label}
                            </h4>
                            <div className={cn('grid gap-4', group.fields.length > 1 ? 'sm:grid-cols-2 md:grid-cols-3' : 'sm:grid-cols-1')}>
                                {group.fields.map((field) => (
                                    <div key={field} className="space-y-1">
                                        <Label htmlFor={field} className="text-xs">
                                            {field}
                                        </Label>
                                        <Input
                                            id={field}
                                            type="number"
                                            placeholder="—"
                                            min="0"
                                            step="any"
                                            value={(data as Record<string, unknown>)[field] as string ?? ''}
                                            onChange={(e) =>
                                                setData(
                                                    field as keyof IngredientFormData,
                                                    e.target.value as IngredientFormData[keyof IngredientFormData],
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </CollapsibleContent>
            </Collapsible>

            {/* Allergen section */}
            <Collapsible open={allergensOpen} onOpenChange={setAllergensOpen}>
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="flex w-full items-center justify-between rounded-md border px-4 py-3 text-sm font-medium hover:bg-muted"
                    >
                        <span>Allergens</span>
                        {allergensOpen ? (
                            <ChevronUpIcon className="size-4 text-muted-foreground" />
                        ) : (
                            <ChevronDownIcon className="size-4 text-muted-foreground" />
                        )}
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent className="mt-4">
                    <AllergenChecklist
                        allergens={allergens}
                        value={data.allergens}
                        onChange={(v) => setData('allergens', v)}
                    />
                </CollapsibleContent>
            </Collapsible>

            {/* Conversions section */}
            <div className="space-y-3">
                <h3 className="text-sm font-medium">Unit Conversions</h3>
                <ConversionRows
                    units={units}
                    value={data.conversions}
                    onChange={(v) => setData('conversions', v)}
                    errors={errors as Record<string, string>}
                />
            </div>

            {/* Actions */}
            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {isEdit
                        ? t('app.ingredients.edit_submit')
                        : t('app.ingredients.create_submit')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => router.visit('/ingredients')}
                >
                    {t('app.ingredients.create_cancel')}
                </Button>
            </div>
        </form>
    );
}
