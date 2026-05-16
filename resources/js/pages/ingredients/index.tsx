import { Head, router } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { IngredientFilters } from '@/components/ingredients/ingredient-filters';
import { IngredientRow } from '@/components/ingredients/ingredient-row';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { index as ingredientsIndex } from '@/routes/ingredients';
import type { AllergenOption, IngredientFilters as IIngredientFilters, PaginatedIngredients } from '@/types/ingredient';

interface IngredientIndexProps {
    ingredients: PaginatedIngredients;
    filters: IIngredientFilters;
    allergens: AllergenOption[];
}

const SKELETON_COUNT = 8;

export default function IngredientIndex({ ingredients, filters, allergens }: IngredientIndexProps) {
    const { t } = useLaravelReactI18n();

    const [search, setSearch] = useState(filters.search ?? '');
    const [source, setSource] = useState(filters.source ?? 'all');
    const [verifiedOnly, setVerifiedOnly] = useState(filters.verified_only ?? false);
    const [allergenFree, setAllergenFree] = useState<string[]>(filters.allergen_free ?? []);
    const [isLoading, setIsLoading] = useState(false);

    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    function reloadWithParams(params: {
        search?: string;
        source?: string;
        verified_only?: boolean;
        allergen_free?: string[];
    }) {
        setIsLoading(true);
        router.reload({
            data: {
                search: params.search ?? search,
                source: params.source ?? source,
                verified_only: params.verified_only ?? verifiedOnly,
                allergen_free: params.allergen_free ?? allergenFree,
            },
            only: ['ingredients'],
            preserveState: true,
            replace: true,
            onFinish: () => setIsLoading(false),
        });
    }

    const handleSearchChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const value = e.target.value;
            setSearch(value);

            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }

            debounceTimerRef.current = setTimeout(() => {
                reloadWithParams({ search: value });
            }, 300);
        },
        [source, verifiedOnly, allergenFree],
    );

    function handleFiltersChange(updatedFilters: Partial<IIngredientFilters>) {
        const newSource = updatedFilters.source ?? source;
        const newVerifiedOnly = updatedFilters.verified_only ?? verifiedOnly;
        const newAllergenFree = updatedFilters.allergen_free ?? allergenFree;

        setSource(newSource);
        setVerifiedOnly(newVerifiedOnly);
        setAllergenFree(newAllergenFree);

        reloadWithParams({
            source: newSource,
            verified_only: newVerifiedOnly,
            allergen_free: newAllergenFree,
        });
    }

    function navigateToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(
            url,
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    const showPagination = ingredients.last_page > 1;
    const hasResults = ingredients.data.length > 0;
    const hasSearch = search.trim().length > 0;

    return (
        <>
            <Head title={t('app.ingredients.page_title')} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Page header */}
                <div className="flex items-center justify-between">
                    <h1 className="text-[28px] leading-tight font-semibold">
                        {t('app.ingredients.page_title')}
                    </h1>
                    <Button asChild>
                        <a href="/ingredients/create">
                            <PlusIcon className="size-4" />
                            {t('app.ingredients.cta_add')}
                        </a>
                    </Button>
                </div>

                {/* Focal search input */}
                <div className="relative w-full">
                    <SearchIcon className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        aria-label={t('app.ingredients.search_placeholder')}
                        placeholder={t('app.ingredients.search_placeholder')}
                        value={search}
                        onChange={handleSearchChange}
                        className="w-full pl-9"
                    />
                </div>

                {/* Filters */}
                <IngredientFilters
                    filters={{ search, source, verified_only: verifiedOnly, allergen_free: allergenFree }}
                    allergens={allergens}
                    onFiltersChange={handleFiltersChange}
                />

                {/* Results list */}
                <ScrollArea className="flex-1">
                    <div
                        aria-busy={isLoading}
                        aria-label={t('app.ingredients.page_title')}
                    >
                        {isLoading ? (
                            /* Skeleton loading state */
                            <div className="flex flex-col">
                                {Array.from({ length: SKELETON_COUNT }).map((_, i) => (
                                    <div key={i} className="flex min-h-12 items-center gap-4 border-b border-border px-2 py-2">
                                        <div className="flex flex-1 flex-col gap-1">
                                            <Skeleton className="h-4 w-48" />
                                            <Skeleton className="h-3 w-32" />
                                        </div>
                                        <Skeleton className="h-4 w-16" />
                                        <Skeleton className="h-4 w-12" />
                                    </div>
                                ))}
                            </div>
                        ) : hasResults ? (
                            <div className="flex flex-col">
                                {ingredients.data.map((ingredient) => (
                                    <IngredientRow key={ingredient.id} ingredient={ingredient} />
                                ))}
                            </div>
                        ) : (
                            /* Empty state */
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                {hasSearch ? (
                                    <>
                                        <h2 className="text-xl font-semibold">
                                            {t('app.ingredients.empty_search_heading', { query: search })}
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {t('app.ingredients.empty_search_body')}
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <h2 className="text-xl font-semibold">
                                            {t('app.ingredients.empty_heading')}
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {t('app.ingredients.empty_body')}
                                        </p>
                                    </>
                                )}
                                <Button asChild className="mt-6">
                                    <a href="/ingredients/create">{t('app.ingredients.cta_add')}</a>
                                </Button>
                            </div>
                        )}
                    </div>
                </ScrollArea>

                {/* Pagination */}
                {!isLoading && showPagination && (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const prev = ingredients.links.find(
                                            (l) => l.label === '&laquo; Previous',
                                        );
                                        navigateToPage(prev?.url ?? null);
                                    }}
                                    aria-disabled={ingredients.current_page === 1}
                                    className={
                                        ingredients.current_page === 1
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                />
                            </PaginationItem>

                            {ingredients.links
                                .filter(
                                    (link) =>
                                        link.label !== '&laquo; Previous' &&
                                        link.label !== 'Next &raquo;',
                                )
                                .map((link, i) => (
                                    <PaginationItem key={i}>
                                        {link.label === '...' ? (
                                            <PaginationEllipsis />
                                        ) : (
                                            <PaginationLink
                                                href="#"
                                                isActive={link.active}
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    navigateToPage(link.url);
                                                }}
                                            >
                                                {link.label}
                                            </PaginationLink>
                                        )}
                                    </PaginationItem>
                                ))}

                            <PaginationItem>
                                <PaginationNext
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const next = ingredients.links.find(
                                            (l) => l.label === 'Next &raquo;',
                                        );
                                        navigateToPage(next?.url ?? null);
                                    }}
                                    aria-disabled={
                                        ingredients.current_page === ingredients.last_page
                                    }
                                    className={
                                        ingredients.current_page === ingredients.last_page
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                )}
            </div>
        </>
    );
}

IngredientIndex.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Library',
            href: ingredientsIndex().url,
        },
    ],
};
