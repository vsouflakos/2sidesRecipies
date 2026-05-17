import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { destroy as publishDestroy } from '@/actions/App/Http/Controllers/Recipes/PublishRecipeController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface UnpublishRecipeDialogProps {
    recipeId: number;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}

/**
 * Unpublish confirmation dialog.
 * Uses variant="destructive" for the confirm button — destructive color is exclusive to this action.
 */
export function UnpublishRecipeDialog({
    recipeId,
    open,
    onOpenChange,
}: UnpublishRecipeDialogProps) {
    const { t } = useTranslations();
    const [isUnpublishing, setIsUnpublishing] = useState(false);

    function handleUnpublish() {
        if (isUnpublishing) {
            return;
        }

        setIsUnpublishing(true);

        router.delete(publishDestroy({ recipe: recipeId }).url, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
            onFinish: () => {
                setIsUnpublishing(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('app.recipes.unpublish_dialog_title')}</DialogTitle>
                    <DialogDescription>
                        {t('app.recipes.unpublish_dialog_body')}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={isUnpublishing}
                    >
                        {t('app.recipes.unpublish_dialog_dismiss')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleUnpublish}
                        disabled={isUnpublishing}
                    >
                        {t('app.recipes.unpublish_dialog_confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
