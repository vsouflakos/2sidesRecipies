import { Head, setLayoutProps } from '@inertiajs/react';
import { ChevronLeftIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { TestCard } from '@/components/recipes/test-card';
import { TestRecordModal } from '@/components/recipes/test-record-modal';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { show as recipesShow } from '@/routes/recipes';
import { index as testsIndex } from '@/routes/recipes/tests';
import type { RecipeTest, RecipeTestsIndexProps } from '@/types/recipe-test';

export default function RecipeTestsIndex({ recipe, tests, versions }: RecipeTestsIndexProps) {
    const { t } = useTranslations();

    setLayoutProps({
        breadcrumbs: [
            { title: 'My Recipes', href: '/recipes' },
            { title: recipe.name, href: recipesShow({ recipe: recipe.id }).url },
            { title: 'Tests', href: testsIndex({ recipe: recipe.id }).url },
        ],
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editingTest, setEditingTest] = useState<RecipeTest | null>(null);

    function openNewModal() {
        setEditingTest(null);
        setModalOpen(true);
    }

    function openEditModal(test: RecipeTest) {
        setEditingTest(test);
        setModalOpen(true);
    }

    function handleClose() {
        setModalOpen(false);
        setEditingTest(null);
    }

    const latestTest = tests.length > 0 ? tests[0] : null;

    return (
        <>
            <Head title={`Tests for ${recipe.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Page header */}
                <div className="flex items-start justify-between gap-4">
                    <h1 className="text-[28px] font-semibold leading-tight">
                        {t('app.tests.page_heading', { name: recipe.name })}
                    </h1>
                    <Button
                        variant="ghost"
                        asChild
                        className="shrink-0"
                    >
                        <a href={recipesShow({ recipe: recipe.id }).url}>
                            <ChevronLeftIcon className="size-4" />
                            {t('app.tests.back_link')}
                        </a>
                    </Button>
                </div>

                {/* Stats bar — only shown when tests exist */}
                {tests.length > 0 && latestTest && (
                    <p className="text-[14px] text-muted-foreground">
                        {t('app.tests.stats_bar', {
                            count: String(tests.length),
                            score: String(latestTest.overall_rating),
                        })}
                    </p>
                )}

                {/* Primary CTA */}
                <div>
                    <Button onClick={openNewModal}>
                        <PlusIcon className="size-4" />
                        {t('app.tests.record_cta')}
                    </Button>
                </div>

                {/* Tests list or empty state */}
                {tests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <h2 className="text-[20px] font-semibold leading-tight">
                            {t('app.tests.empty_heading')}
                        </h2>
                        <p className="mt-2 text-[16px] text-muted-foreground">
                            {t('app.tests.empty_body')}
                        </p>
                        <Button className="mt-6" onClick={openNewModal}>
                            {t('app.tests.empty_cta')}
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        {tests.map((test) => (
                            <TestCard
                                key={test.id}
                                test={test}
                                recipeId={recipe.id}
                                onEdit={openEditModal}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Single modal instance, controlled at page level */}
            <TestRecordModal
                open={modalOpen}
                onClose={handleClose}
                recipeId={recipe.id}
                versions={versions}
                currentVersionId={recipe.current_version_id}
                editingTest={editingTest}
            />
        </>
    );
}

