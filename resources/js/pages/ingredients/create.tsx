import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { IngredientForm } from '@/components/ingredients/ingredient-form';
import type { AllergenOption, CategoryNode, IngredientDetail, UnitOption } from '@/types/ingredient';

interface IngredientCreateProps {
    categories: CategoryNode[];
    allergens: AllergenOption[];
    units: UnitOption[];
    ingredient?: IngredientDetail;
    duplicate?: IngredientDetail;
    isEdit?: boolean;
}

export default function IngredientCreate({
    categories,
    allergens,
    units,
    ingredient,
    duplicate,
    isEdit = false,
}: IngredientCreateProps) {
    const { t } = useLaravelReactI18n();

    const pageTitle = isEdit
        ? 'Edit Ingredient'
        : t('app.ingredients.cta_add');

    return (
        <>
            <Head title={pageTitle} />

            <div className="mx-auto max-w-3xl space-y-8 px-4 py-8 md:px-6">
                <div>
                    <h1 className="text-[28px] font-semibold leading-[1.2]">{pageTitle}</h1>
                    {!isEdit && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            Private ingredients are visible only to you.
                        </p>
                    )}
                </div>

                <IngredientForm
                    categories={categories}
                    allergens={allergens}
                    units={units}
                    ingredient={ingredient}
                    duplicate={duplicate}
                    isEdit={isEdit}
                />
            </div>
        </>
    );
}
