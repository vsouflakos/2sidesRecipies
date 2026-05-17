import { useState } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { ChevronDownIcon, ChevronRightIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { CuisineOption, RecipeDraft, TagOption, UnitOption } from '@/types/recipe';

interface RecipeMetadataBlockProps {
    draft: RecipeDraft;
    cuisines: CuisineOption[];
    units: UnitOption[];
    availableTags: TagOption[];
    /** Called when any metadata field changes — triggers auto-save. */
    onChange: (updated: Partial<RecipeDraft>) => void;
    className?: string;
}

/**
 * Collapsible metadata block containing cuisine, difficulty, tags, yield, portions,
 * prep/cook time, and chef notes fields.
 */
export function RecipeMetadataBlock({
    draft,
    cuisines,
    units,
    availableTags,
    onChange,
    className,
}: RecipeMetadataBlockProps) {
    const { t } = useTranslations();
    const [isOpen, setIsOpen] = useState(false);
    const [tagsOpen, setTagsOpen] = useState(false);

    const difficultyOptions = [
        { value: 'easy', label: t('app.recipes.difficulty_easy') },
        { value: 'medium', label: t('app.recipes.difficulty_medium') },
        { value: 'hard', label: t('app.recipes.difficulty_hard') },
        { value: 'expert', label: t('app.recipes.difficulty_expert') },
    ];

    function handleTagSelect(tag: TagOption) {
        const currentTags = draft.tags ?? [];
        const exists = currentTags.some((t) => t.id === tag.id);

        if (exists) {
            onChange({ tags: currentTags.filter((t) => t.id !== tag.id) });
        } else {
            onChange({ tags: [...currentTags, tag] });
        }
    }

    const selectedTags = draft.tags ?? [];

    return (
        <Collapsible
            open={isOpen}
            onOpenChange={setIsOpen}
            className={cn('rounded-lg border border-border', className)}
        >
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="flex w-full items-center justify-between rounded-lg px-4 py-3 text-left"
                >
                    <span className="text-sm font-medium">
                        {t('app.recipes.metadata_toggle')}
                    </span>
                    {isOpen ? (
                        <ChevronDownIcon className="size-4 text-muted-foreground" />
                    ) : (
                        <ChevronRightIcon className="size-4 text-muted-foreground" />
                    )}
                </Button>
            </CollapsibleTrigger>

            <CollapsibleContent className="px-4 pb-4">
                <div className="grid gap-4 pt-2 sm:grid-cols-2">
                    {/* Cuisine */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_cuisine')}</Label>
                        <Select
                            value={draft.cuisine_id !== null ? String(draft.cuisine_id) : '__none__'}
                            onValueChange={(val) =>
                                onChange({ cuisine_id: val && val !== '__none__' ? Number(val) : null })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('app.recipes.metadata_cuisine')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('app.recipes.metadata_cuisine')}</SelectItem>
                                {cuisines.map((cuisine) => (
                                    <SelectItem key={cuisine.id} value={String(cuisine.id)}>
                                        {cuisine.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Difficulty */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_difficulty')}</Label>
                        <Select
                            value={draft.difficulty ?? '__none__'}
                            onValueChange={(val) =>
                                onChange({
                                    difficulty: (val && val !== '__none__' ? val : null) as RecipeDraft['difficulty'] ?? null,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('app.recipes.metadata_difficulty')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('app.recipes.metadata_difficulty')}</SelectItem>
                                {difficultyOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Tags — multi-select combobox */}
                    <div className="space-y-2 sm:col-span-2">
                        <Label className="text-sm">{t('app.recipes.metadata_tags')}</Label>
                        <Popover open={tagsOpen} onOpenChange={setTagsOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full justify-start gap-1 font-normal"
                                >
                                    {selectedTags.length > 0 ? (
                                        <div className="flex flex-wrap gap-1">
                                            {selectedTags.map((tag) => (
                                                <Badge
                                                    key={tag.id}
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {tag.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {t('app.recipes.metadata_tags')}
                                        </span>
                                    )}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-64 p-0" align="start">
                                <Command>
                                    <CommandInput
                                        placeholder={t('app.recipes.metadata_tags')}
                                    />
                                    <CommandList>
                                        <CommandEmpty>
                                            {t('app.recipes.picker_no_results', {
                                                query: '',
                                            })}
                                        </CommandEmpty>
                                        <CommandGroup>
                                            {availableTags.map((tag) => {
                                                const selected = selectedTags.some(
                                                    (s) => s.id === tag.id,
                                                );
                                                return (
                                                    <CommandItem
                                                        key={tag.id}
                                                        value={tag.name}
                                                        onSelect={() => handleTagSelect(tag)}
                                                        className="min-h-[44px]"
                                                    >
                                                        <span className="flex-1">{tag.name}</span>
                                                        {selected && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-xs"
                                                            >
                                                                ✓
                                                            </Badge>
                                                        )}
                                                    </CommandItem>
                                                );
                                            })}
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>

                    {/* Yield amount + unit */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_yield')}</Label>
                        <div className="flex gap-2">
                            <Input
                                type="number"
                                value={draft.yield_amount ?? ''}
                                onChange={(e) =>
                                    onChange({ yield_amount: e.target.value || null })
                                }
                                placeholder="0"
                                min="0"
                                className="flex-1"
                            />
                            <Select
                                value={
                                    draft.yield_unit_id !== null
                                        ? String(draft.yield_unit_id)
                                        : '__none__'
                                }
                                onValueChange={(val) =>
                                    onChange({ yield_unit_id: val && val !== '__none__' ? Number(val) : null })
                                }
                            >
                                <SelectTrigger className="w-[100px]">
                                    <SelectValue placeholder="Unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">Unit</SelectItem>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={String(unit.id)}>
                                            {unit.symbol}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Portions */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_portions')}</Label>
                        <Input
                            type="number"
                            value={draft.portions ?? ''}
                            onChange={(e) =>
                                onChange({
                                    portions: e.target.value ? Number(e.target.value) : null,
                                })
                            }
                            placeholder="0"
                            min="0"
                        />
                    </div>

                    {/* Prep time */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_prep_time')}</Label>
                        <Input
                            type="number"
                            value={draft.prep_time_minutes ?? ''}
                            onChange={(e) =>
                                onChange({
                                    prep_time_minutes: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                            placeholder="0"
                            min="0"
                        />
                    </div>

                    {/* Cook time */}
                    <div className="space-y-2">
                        <Label className="text-sm">{t('app.recipes.metadata_cook_time')}</Label>
                        <Input
                            type="number"
                            value={draft.cook_time_minutes ?? ''}
                            onChange={(e) =>
                                onChange({
                                    cook_time_minutes: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                            placeholder="0"
                            min="0"
                        />
                    </div>

                    {/* Chef notes */}
                    <div className="space-y-2 sm:col-span-2">
                        <Label className="text-sm">Notes</Label>
                        <Textarea
                            value={draft.chef_notes ?? ''}
                            onChange={(e) =>
                                onChange({ chef_notes: e.target.value || null })
                            }
                            placeholder={t('app.recipes.builder_chef_notes_placeholder')}
                            rows={3}
                        />
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
