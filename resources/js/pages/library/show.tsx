import { Head } from '@inertiajs/react';
import { UtensilsCrossedIcon } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { AllergenIcons } from '@/components/ingredients/allergen-icons';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import GuestPublicLayout from '@/layouts/guest-public-layout';
import type { PublicRecipeData } from '@/types/recipe';

interface LibraryShowProps {
    recipe: PublicRecipeData;
}

type NutritionRow = {
    label: string;
    key: string;
    unit: string;
};

const NUTRITION_ROWS: NutritionRow[] = [
    { label: 'Energy', key: 'energy_kcal', unit: 'kcal' },
    { label: 'Protein', key: 'protein_g', unit: 'g' },
    { label: 'Fat', key: 'fat_g', unit: 'g' },
    { label: 'Saturated fat', key: 'saturated_fat_g', unit: 'g' },
    { label: 'Carbohydrates', key: 'carbs_g', unit: 'g' },
    { label: 'Fibre', key: 'fibre_g', unit: 'g' },
    { label: 'Sodium', key: 'sodium_mg', unit: 'mg' },
];

function formatPublishedDate(isoDate: string | null): string {
    if (!isoDate) {
        return '';
    }

    return new Date(isoDate).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function NutritionTable({ data }: { data: Record<string, unknown> }) {
    return (
        <table className="w-full text-[14px]">
            <tbody>
                {NUTRITION_ROWS.map((row) => {
                    const val = data[row.key];
                    const display = val !== null && val !== undefined ? `${val} ${row.unit}` : '—';

                    return (
                        <tr key={row.key} className="border-b last:border-0">
                            <td className="py-2 text-muted-foreground">{row.label}</td>
                            <td className="py-2 text-right">{display}</td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

export default function LibraryShow({ recipe }: LibraryShowProps) {
    const { t } = useTranslations();

    const allergenItems = (Array.isArray(recipe.allergen_slugs) ? recipe.allergen_slugs : []).map((slug) => ({
        slug,
        name: slug,
        state: 'contains' as const,
    }));

    const nutritionPerPortion = recipe.nutrition
        ? ((recipe.nutrition as Record<string, unknown>).per_portion as Record<string, unknown> | null | undefined) ?? null
        : null;

    const nutritionPer100g = recipe.nutrition
        ? ((recipe.nutrition as Record<string, unknown>).per_100g as Record<string, unknown> | null | undefined) ?? null
        : null;

    const publishedDate = formatPublishedDate(recipe.published_at);

    return (
        <>
            <Head title={recipe.name} />

            <div className="pb-12">
                {/* 1. Hero image */}
                <div className="aspect-video w-full overflow-hidden bg-muted">
                    {recipe.hero_image_path ? (
                        <img
                            src={recipe.hero_image_path}
                            alt={recipe.name}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center bg-muted">
                            <UtensilsCrossedIcon className="size-12 text-muted-foreground" />
                        </div>
                    )}
                </div>

                {/* 2. Recipe header block */}
                <div className="py-8 px-4 md:px-8">
                    <h1 className="text-[28px] font-semibold leading-tight">
                        {recipe.name}
                    </h1>
                    <p className="mt-1 text-[14px] text-muted-foreground">
                        {t('app.library.by', { name: recipe.author_name })}
                    </p>
                    {publishedDate && (
                        <p className="text-[14px] text-muted-foreground">
                            {t('app.library.published_label', { date: publishedDate })}
                        </p>
                    )}

                    {/* Badges row */}
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {recipe.cuisine && (
                            <Badge variant="secondary">{recipe.cuisine}</Badge>
                        )}
                        {recipe.difficulty && (
                            <Badge variant="outline">
                                {t(`app.recipes.difficulty_${recipe.difficulty}`)}
                            </Badge>
                        )}
                        {recipe.total_time !== null && recipe.total_time !== undefined && recipe.total_time > 0 && (
                            <span className="text-[14px] text-muted-foreground">
                                {recipe.total_time >= 60
                                    ? `${Math.floor(recipe.total_time / 60)}h ${recipe.total_time % 60 > 0 ? `${recipe.total_time % 60}m` : ''}`.trim()
                                    : `${recipe.total_time} min`}
                            </span>
                        )}
                    </div>
                </div>

                {/* 3. Metrics strip */}
                <div className="border-y py-4 px-4 md:px-8 flex flex-wrap gap-6">
                    {nutritionPerPortion?.energy_kcal !== undefined && nutritionPerPortion?.energy_kcal !== null && (
                        <span className="text-[14px] text-muted-foreground">
                            {nutritionPerPortion.energy_kcal as string} kcal / portion
                        </span>
                    )}
                    {allergenItems.length > 0 && (
                        <div className="flex flex-wrap items-center gap-1">
                            <AllergenIcons allergens={allergenItems.slice(0, 14)} />
                        </div>
                    )}
                </div>

                {/* 4. Sections + ingredient lines */}
                {recipe.sections.length > 0 && (
                    <div className="px-4 md:px-8 py-6 flex flex-col gap-8">
                        {recipe.sections.map((section, sectionIdx) => (
                            <div key={sectionIdx}>
                                {section.name && (
                                    <h2 className="text-[20px] font-semibold mb-3">
                                        {section.name}
                                    </h2>
                                )}

                                {section.lines.length > 0 && (
                                    <ul className="flex flex-col gap-1 mb-4">
                                        {section.lines.map((line, lineIdx) => (
                                            <li key={lineIdx} className="flex gap-2 text-[16px]">
                                                {line.quantity && <span>{line.quantity}</span>}
                                                {line.unit && <span>{line.unit}</span>}
                                                {line.name && <span>{line.name}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {section.steps.length > 0 && (
                                    <ol className="flex flex-col gap-3">
                                        {section.steps.map((step) => (
                                            <li key={step.order} className="flex gap-3">
                                                <span className="text-[20px] font-semibold shrink-0 w-7">
                                                    {step.order}.
                                                </span>
                                                <span className="text-[16px] leading-relaxed">
                                                    {step.instruction}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* 5. Standalone steps not in sections (fallback) */}

                {/* Separator before nutrition */}
                {recipe.nutrition && <Separator />}

                {/* 6. Nutrition panel */}
                {recipe.nutrition && (
                    <div className="px-4 md:px-8 py-6">
                        <Tabs defaultValue="per_portion">
                            <TabsList>
                                <TabsTrigger value="per_portion">
                                    {t('app.recipes.metrics_per_portion')}
                                </TabsTrigger>
                                <TabsTrigger value="per_100g">
                                    {t('app.recipes.metrics_per_100g')}
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="per_portion" className="mt-4">
                                {nutritionPerPortion ? (
                                    <NutritionTable data={nutritionPerPortion} />
                                ) : (
                                    <p className="text-[14px] text-muted-foreground">—</p>
                                )}
                            </TabsContent>

                            <TabsContent value="per_100g" className="mt-4">
                                {nutritionPer100g ? (
                                    <NutritionTable data={nutritionPer100g} />
                                ) : (
                                    <p className="text-[14px] text-muted-foreground">—</p>
                                )}
                            </TabsContent>
                        </Tabs>
                    </div>
                )}
            </div>
        </>
    );
}

LibraryShow.layout = (page: React.ReactNode) => <GuestPublicLayout>{page}</GuestPublicLayout>;
