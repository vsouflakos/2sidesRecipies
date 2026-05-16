import { router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { verify as verifyRoute } from '@/routes/admin/ingredients';
import type { CanFlags, IngredientDetail } from '@/types/ingredient';

interface VerifyActionProps {
    ingredient: IngredientDetail;
    can: CanFlags;
}

export function VerifyAction({ ingredient, can }: VerifyActionProps) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    // If already verified, show static verification info
    if (ingredient.verified) {
        if (!can.verify) {
            return null;
        }

        const verifiedDate = ingredient.verified_at
            ? new Date(ingredient.verified_at).toLocaleDateString()
            : '';

        return (
            <p className="text-[14px] text-muted-foreground">
                {t('app.ingredients.verify_already', {
                    date: verifiedDate,
                    name: ingredient.verified_by ?? '',
                })}
            </p>
        );
    }

    // Not yet verified — show button if user has permission
    if (!can.verify) {
        return null;
    }

    function handleConfirm() {
        router.post(
            verifyRoute(ingredient.id).url,
            {},
            {
                onSuccess: () => {
                    setOpen(false);
                    toast.success(t('app.ingredients.verify_toast'));
                },
            },
        );
    }

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                {t('app.ingredients.verify_btn')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('app.ingredients.verify_btn')}</DialogTitle>
                        <DialogDescription>
                            {t('app.ingredients.verify_dialog_body')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">
                                {t('app.ingredients.verify_dialog_cancel')}
                            </Button>
                        </DialogClose>
                        <Button onClick={handleConfirm}>
                            {t('app.ingredients.verify_dialog_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
