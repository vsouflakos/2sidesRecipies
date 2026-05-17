import { useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as ingredientsStore } from '@/actions/App/Http/Controllers/Ingredients/PrivateIngredientController';
import type { CategoryNode } from '@/types/ingredient';
import type { ComponentSearchResult } from '@/types/recipe';

interface QuickCreateIngredientModalProps {
    /** Whether the dialog is open. */
    open: boolean;
    /** Called when dialog should close. */
    onOpenChange: (open: boolean) => void;
    /** Pre-filled name from the search query. */
    initialName: string;
    /** Available categories for selection. */
    categories: CategoryNode[];
    /** Called when the ingredient is created and should be added to the recipe. */
    onSuccess: (result: ComponentSearchResult) => void;
}

interface QuickCreateFormData {
    name_en: string;
    category_id: number | '';
}

/**
 * Minimal dialog for quick-creating a private ingredient from within the recipe builder.
 * Pre-fills name from the combobox search query and calls back on success.
 */
export function QuickCreateIngredientModal({
    open,
    onOpenChange,
    initialName,
    categories,
    onSuccess,
}: QuickCreateIngredientModalProps) {
    const { t } = useLaravelReactI18n();

    const { data, setData, post, processing, errors, reset } = useForm<QuickCreateFormData>({
        name_en: initialName,
        category_id: '',
    });

    // Sync initialName when it changes (new quick-create triggers)
    if (data.name_en !== initialName && !processing) {
        setData('name_en', initialName);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        post(ingredientsStore().url, {
            preserveState: true,
            onSuccess: (page) => {
                /** Extract the newly created ingredient from the flash or redirect. */
                const flash = (page.props as Record<string, unknown>).flash as
                    | { quick_created?: ComponentSearchResult }
                    | undefined;

                if (flash?.quick_created) {
                    onSuccess(flash.quick_created);
                } else {
                    /** Fallback: close modal and let the builder refresh ingredients */
                    onSuccess({ type: 'ingredient', id: 0, name: data.name_en, unit_hint: null });
                }

                reset();
                onOpenChange(false);
            },
        });
    }

    /** Flatten categories for the select dropdown. */
    function flattenCategories(nodes: CategoryNode[]): CategoryNode[] {
        const flat: CategoryNode[] = [];
        for (const node of nodes) {
            flat.push(node);
            if (node.children) {
                flat.push(...flattenCategories(node.children));
            }
        }
        return flat;
    }

    const flatCategories = flattenCategories(categories);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('app.recipes.quick_create_title')}</DialogTitle>
                    <DialogDescription>
                        {t('app.ingredients.not_found')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="quick-create-name">
                            {t('app.ingredients.create_name_en')}
                        </Label>
                        <Input
                            id="quick-create-name"
                            type="text"
                            value={data.name_en}
                            onChange={(e) => setData('name_en', e.target.value)}
                            aria-invalid={!!errors.name_en}
                        />
                        {errors.name_en && (
                            <p className="text-sm text-destructive">{errors.name_en}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="quick-create-category">
                            {t('app.ingredients.create_category')}
                        </Label>
                        <Select
                            value={data.category_id !== '' ? String(data.category_id) : undefined}
                            onValueChange={(val) => setData('category_id', Number(val))}
                        >
                            <SelectTrigger id="quick-create-category" aria-invalid={!!errors.category_id}>
                                <SelectValue placeholder={t('app.ingredients.create_category')} />
                            </SelectTrigger>
                            <SelectContent>
                                {flatCategories.map((cat) => (
                                    <SelectItem key={cat.id} value={String(cat.id)}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.category_id && (
                            <p className="text-sm text-destructive">{errors.category_id}</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('app.recipes.delete_cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.name_en.trim()}
                        >
                            {t('app.recipes.quick_create_cta')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
