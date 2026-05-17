import { Head, useForm } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store as recipesStore } from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import { index as recipesIndex } from '@/actions/App/Http/Controllers/Recipes/RecipeController';

interface RecipeCreateFormData {
    name: string;
}

export default function RecipeCreate() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<RecipeCreateFormData>({
        name: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(recipesStore().url);
    }

    return (
        <>
            <Head title={t('app.recipes.cta_new')} />

            <div className="mx-auto max-w-xl space-y-8 px-4 py-8 md:px-6">
                <div>
                    <h1 className="text-[28px] font-semibold leading-[1.2]">
                        {t('app.recipes.cta_new')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('app.recipes.create_subtitle')}
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-2">
                        <Input
                            autoFocus
                            type="text"
                            placeholder={t('app.recipes.builder_name_placeholder')}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="text-[28px] font-semibold leading-[1.2] h-auto py-3"
                            aria-invalid={!!errors.name}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">{errors.name}</p>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing || !data.name.trim()}>
                            {t('app.recipes.create_submit')}
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <a href={recipesIndex().url}>{t('app.recipes.create_cancel')}</a>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
