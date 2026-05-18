import { Head, router } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { LibraryRecipeCard } from '@/components/recipes/library-recipe-card';
import { RecipeFilters } from '@/components/recipes/recipe-filters';
import type { RecipeFiltersState } from '@/components/recipes/recipe-filters';
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
import { Skeleton } from '@/components/ui/skeleton';
import GuestPublicLayout from '@/layouts/guest-public-layout';
import type { AllergenOption } from '@/types/ingredient';
import type { CuisineOption, PublicRecipeCardData, TagOption } from '@/types/recipe';

interface PaginatedRecipes<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface LibraryIndexProps {
    recipes: PaginatedRecipes<PublicRecipeCardData>;
    filters: {
        search: string;
        tag: number | null;
        cuisine: number | null;
        allergen: string | null;
        difficulty: string | null;
        max_total_time: number | null;
    };
    cuisines: CuisineOption[];
    allergens: AllergenOption[];
    tags: TagOption[];
}

const SKELETON_COUNT = 6;

export default function LibraryIndex({ recipes, filters, cuisines, allergens, tags }: LibraryIndexProps) {
    const { t } = useTranslations();

    const [search, setSearch] = useState(filters.search ?? '');
    const [tag, setTag] = useState<number | null>(filters.tag ?? null);
    const [cuisine, setCuisine] = useState<number | null>(filters.cuisine ?? null);
    const [allergen, setAllergen] = useState<string | null>(filters.allergen ?? null);
    const [difficulty, setDifficulty] = useState<string | null>(filters.difficulty ?? null);
    const [maxTotalTime, setMaxTotalTime] = useState<number | null>(filters.max_total_time ?? null);
    const [isLoading, setIsLoading] = useState(false);

    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    function reloadWithParams(params: Partial<Omit<RecipeFiltersState, 'ingredient'> & { search?: string }>) {
        setIsLoading(true);
        router.reload({
            data: {
                search: params.search ?? search,
                tag: params.tag !== undefined ? params.tag : tag,
                cuisine: params.cuisine !== undefined ? params.cuisine : cuisine,
                allergen: params.allergen !== undefined ? params.allergen : allergen,
                difficulty: params.difficulty !== undefined ? params.difficulty : difficulty,
                max_total_time: params.max_total_time !== undefined ? params.max_total_time : maxTotalTime,
            },
            only: ['recipes'],
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
        [tag, cuisine, allergen, difficulty, maxTotalTime],
    );

    function handleFiltersChange(updated: Partial<RecipeFiltersState>) {
        const newTag = updated.tag !== undefined ? updated.tag : tag;
        const newCuisine = updated.cuisine !== undefined ? updated.cuisine : cuisine;
        const newAllergen = updated.allergen !== undefined ? updated.allergen : allergen;
        const newDifficulty = updated.difficulty !== undefined ? updated.difficulty : difficulty;
        const newMaxTotalTime = updated.max_total_time !== undefined ? updated.max_total_time : maxTotalTime;

        setTag(newTag);
        setCuisine(newCuisine);
        setAllergen(newAllergen);
        setDifficulty(newDifficulty);
        setMaxTotalTime(newMaxTotalTime);

        reloadWithParams({
            tag: newTag,
            cuisine: newCuisine,
            allergen: newAllergen,
            difficulty: newDifficulty,
            max_total_time: newMaxTotalTime,
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

    const showPagination = recipes.last_page > 1;
    const hasResults = recipes.data.length > 0;
    const hasSearch =
        search.trim().length > 0 ||
        tag !== null ||
        cuisine !== null ||
        allergen !== null ||
        difficulty !== null ||
        maxTotalTime !== null;

    const currentFilters: RecipeFiltersState = {
        search,
        tag,
        cuisine,
        allergen,
        ingredient: null,
        difficulty,
        max_total_time: maxTotalTime,
    };

    return (
        <>
            <Head title={t('app.library.page_title')} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Page header */}
                <div className="flex items-center justify-between">
                    <h1 className="text-[28px] font-semibold leading-tight">
                        {t('app.library.page_title')}
                    </h1>
                </div>

                {/* Search input */}
                <div className="relative w-full">
                    <SearchIcon className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        aria-label={t('app.recipes.search_placeholder')}
                        placeholder={t('app.recipes.search_placeholder')}
                        value={search}
                        onChange={handleSearchChange}
                        className="w-full pl-9"
                    />
                </div>

                {/* Filters */}
                <RecipeFilters
                    filters={currentFilters}
                    cuisines={cuisines}
                    allergens={allergens}
                    tags={tags}
                    onFiltersChange={handleFiltersChange}
                />

                {/* Recipe grid */}
                <div
                    aria-busy={isLoading}
                    aria-label={t('app.library.page_title')}
                    className="flex-1"
                >
                    {isLoading ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {Array.from({ length: SKELETON_COUNT }).map((_, i) => (
                                <div key={i} className="flex flex-col gap-2 rounded-xl border p-0 overflow-hidden">
                                    <Skeleton className="aspect-video w-full rounded-none" />
                                    <div className="flex flex-col gap-2 p-4">
                                        <Skeleton className="h-5 w-3/4" />
                                        <Skeleton className="h-4 w-1/3" />
                                        <Skeleton className="h-4 w-1/2" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : hasResults ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {recipes.data.map((recipe) => (
                                <LibraryRecipeCard key={recipe.id} recipe={recipe} />
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            {hasSearch ? (
                                <>
                                    <h2 className="text-xl font-semibold">
                                        {t('app.library.empty_search_heading')}
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {t('app.library.empty_search_body')}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <h2 className="text-xl font-semibold">
                                        {t('app.library.empty_heading')}
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {t('app.library.empty_body')}
                                    </p>
                                </>
                            )}
                            {/* No CTA — guests cannot publish */}
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {!isLoading && showPagination && (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const prev = recipes.links.find(
                                            (l) => l.label === '&laquo; Previous',
                                        );
                                        navigateToPage(prev?.url ?? null);
                                    }}
                                    aria-disabled={recipes.current_page === 1}
                                    className={
                                        recipes.current_page === 1
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                />
                            </PaginationItem>

                            {recipes.links
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
                                        const next = recipes.links.find(
                                            (l) => l.label === 'Next &raquo;',
                                        );
                                        navigateToPage(next?.url ?? null);
                                    }}
                                    aria-disabled={recipes.current_page === recipes.last_page}
                                    className={
                                        recipes.current_page === recipes.last_page
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

LibraryIndex.layout = (page: React.ReactNode) => <GuestPublicLayout>{page}</GuestPublicLayout>;
