import { Head } from '@inertiajs/react';

export default function Ingredients() {
    return (
        <>
            <Head title="Ingredient Review Queue" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <h1 className="text-2xl font-semibold">Ingredient Review Queue</h1>
                <p className="text-muted-foreground">The review queue arrives in Phase 7.</p>
            </div>
        </>
    );
}

Ingredients.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Review Queue',
            href: '/admin/ingredients',
        },
    ],
};
