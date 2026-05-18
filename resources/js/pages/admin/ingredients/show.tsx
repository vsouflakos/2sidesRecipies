import { Head } from '@inertiajs/react';

export default function IngredientSubmissionShow() {
    return (
        <>
            <Head title="Review Ingredient Submission" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <h1 className="text-2xl font-semibold">Review Ingredient Submission</h1>
                <p className="text-muted-foreground">The submission review page arrives in Phase 7 Plan 03.</p>
            </div>
        </>
    );
}

IngredientSubmissionShow.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Review Queue',
            href: '/admin/ingredients',
        },
        {
            title: 'Review Submission',
            href: '#',
        },
    ],
};
