import { useCallback, useRef, useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { PlusIcon } from 'lucide-react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import type { ComponentSearchResult } from '@/types/recipe';

interface IngredientSearchComboboxProps {
    /** Called when user selects an ingredient. */
    onSelectIngredient: (result: ComponentSearchResult) => void;
    /** Called when user selects a sub-recipe. */
    onSelectSubRecipe: (result: ComponentSearchResult) => void;
    /** Called when user triggers quick-create with the typed query. */
    onQuickCreate: (query: string) => void;
    /** Optional inline circular reference error (ingredient id that caused the error). */
    circularRefError?: string | null;
}

/**
 * Unified inline search combobox for selecting ingredients and sub-recipes.
 * Debounces 300 ms, groups results under "Ingredients" then "My Recipes".
 * On no-match shows a "Create '{query}' as private ingredient" action.
 */
export function IngredientSearchCombobox({
    onSelectIngredient,
    onSelectSubRecipe,
    onQuickCreate,
    circularRefError,
}: IngredientSearchComboboxProps) {
    const { t } = useLaravelReactI18n();

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ComponentSearchResult[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchResults = useCallback((searchQuery: string) => {
        if (!searchQuery.trim()) {
            setResults([]);
            setIsLoading(false);
            return;
        }

        setIsLoading(true);

        fetch(`/search/components?q=${encodeURIComponent(searchQuery)}`)
            .then((res) => res.json())
            .then((data: ComponentSearchResult[]) => {
                setResults(data);
                setIsLoading(false);
            })
            .catch(() => {
                setResults([]);
                setIsLoading(false);
            });
    }, []);

    function handleQueryChange(value: string) {
        setQuery(value);

        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        // 300 ms debounce per UI-SPEC
        debounceTimerRef.current = setTimeout(() => {
            fetchResults(value);
        }, 300);
    }

    function handleSelect(result: ComponentSearchResult) {
        if (result.type === 'ingredient') {
            onSelectIngredient(result);
        } else {
            onSelectSubRecipe(result);
        }
        setQuery('');
        setResults([]);
        setOpen(false);
    }

    function handleQuickCreate() {
        onQuickCreate(query);
        setQuery('');
        setResults([]);
        setOpen(false);
    }

    const ingredientResults = results.filter((r) => r.type === 'ingredient');
    const recipeResults = results.filter((r) => r.type === 'recipe');
    const hasResults = results.length > 0;
    const showQuickCreate = query.trim().length > 0 && !isLoading;

    return (
        <div className="space-y-1">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1 text-muted-foreground hover:text-foreground"
                    >
                        <PlusIcon className="size-4" />
                        {t('app.recipes.picker_placeholder')}
                    </Button>
                </PopoverTrigger>

                <PopoverContent className="w-80 p-0" align="start">
                    <Command shouldFilter={false}>
                        <CommandInput
                            aria-label="Search ingredients or recipes"
                            placeholder={t('app.recipes.picker_placeholder')}
                            value={query}
                            onValueChange={handleQueryChange}
                        />

                        <CommandList>
                            {!hasResults && !showQuickCreate && !isLoading && query.trim() === '' && (
                                <CommandEmpty>
                                    {t('app.recipes.picker_placeholder')}
                                </CommandEmpty>
                            )}

                            {!hasResults && !isLoading && query.trim().length > 0 && (
                                <CommandEmpty>
                                    {t('app.recipes.picker_no_results', { query })}
                                </CommandEmpty>
                            )}

                            {ingredientResults.length > 0 && (
                                <CommandGroup heading={t('app.recipes.picker_group_ingredients')}>
                                    {ingredientResults.map((result) => (
                                        <CommandItem
                                            key={`ingredient-${result.id}`}
                                            value={`ingredient-${result.id}-${result.name}`}
                                            onSelect={() => handleSelect(result)}
                                            className="min-h-[44px]"
                                        >
                                            <span className="flex-1">{result.name}</span>
                                            {result.unit_hint && (
                                                <span className="text-xs text-muted-foreground">
                                                    {result.unit_hint}
                                                </span>
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}

                            {ingredientResults.length > 0 && recipeResults.length > 0 && (
                                <CommandSeparator />
                            )}

                            {recipeResults.length > 0 && (
                                <CommandGroup heading={t('app.recipes.picker_group_recipes')}>
                                    {recipeResults.map((result) => (
                                        <CommandItem
                                            key={`recipe-${result.id}`}
                                            value={`recipe-${result.id}-${result.name}`}
                                            onSelect={() => handleSelect(result)}
                                            className="min-h-[44px]"
                                        >
                                            <span className="flex-1">{result.name}</span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}

                            {showQuickCreate && (
                                <>
                                    {hasResults && <CommandSeparator />}
                                    <CommandGroup>
                                        <CommandItem
                                            value={`quick-create-${query}`}
                                            onSelect={handleQuickCreate}
                                            className="min-h-[44px] text-muted-foreground"
                                        >
                                            <PlusIcon className="size-4" />
                                            {t('app.recipes.picker_quick_create', { query })}
                                        </CommandItem>
                                    </CommandGroup>
                                </>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {circularRefError && (
                <p className="text-sm text-destructive">
                    {t('app.recipes.circular_ref_error', { name: circularRefError })}
                </p>
            )}
        </div>
    );
}
