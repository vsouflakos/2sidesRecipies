import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { toast } from 'sonner';
import { SubmissionStatusBadge } from '@/components/ingredients/submission-status-badge';
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
import {
    duplicateCheck as ingredientDuplicateCheck,
    submit as ingredientSubmit,
    withdraw as ingredientWithdraw,
} from '@/routes/ingredients';

interface DuplicateMatch {
    id: number;
    name: string;
}

interface SubmitActionProps {
    ingredient: {
        id: number;
        name: string;
        submission_status?: 'private' | 'submitted' | 'approved' | 'rejected' | null;
        contributed_by?: string | null;
    };
    can: {
        submit?: boolean;
        withdraw?: boolean;
    };
}

export function SubmitAction({ ingredient, can }: SubmitActionProps) {
    const { t } = useLaravelReactI18n();
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [duplicatesDialogOpen, setDuplicatesDialogOpen] = useState(false);
    const [withdrawDialogOpen, setWithdrawDialogOpen] = useState(false);
    const [duplicateMatches, setDuplicateMatches] = useState<DuplicateMatch[]>([]);
    const [isChecking, setIsChecking] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const status = ingredient.submission_status;

    async function handleSubmitClick() {
        setIsChecking(true);
        try {
            const response = await fetch(
                ingredientDuplicateCheck({ ingredient: ingredient.id }).url,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const data = await response.json();
            const matches: DuplicateMatch[] = data.matches ?? [];

            if (matches.length > 0) {
                setDuplicateMatches(matches);
                setDuplicatesDialogOpen(true);
            } else {
                setSubmitDialogOpen(true);
            }
        } catch {
            setSubmitDialogOpen(true);
        } finally {
            setIsChecking(false);
        }
    }

    function handleConfirmSubmit() {
        setIsSubmitting(true);
        router.post(
            ingredientSubmit({ ingredient: ingredient.id }).url,
            {},
            {
                onSuccess: () => {
                    toast.success(t('app.ingredients.submit_toast'));
                    setSubmitDialogOpen(false);
                    setDuplicatesDialogOpen(false);
                },
                onError: () => {
                    toast.error(t('app.ingredients.submit_error'));
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    }

    function handleWithdraw() {
        setIsSubmitting(true);
        router.delete(ingredientWithdraw({ ingredient: ingredient.id }).url, {
            onSuccess: () => {
                toast.success(t('app.ingredients.withdraw_toast'));
                setWithdrawDialogOpen(false);
            },
            onError: () => {
                toast.error(t('app.ingredients.withdraw_error'));
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    }

    // Can submit (private or rejected status)
    if (
        (status === 'private' || status === 'rejected' || status == null) &&
        can.submit
    ) {
        return (
            <>
                <Button
                    variant="default"
                    onClick={handleSubmitClick}
                    disabled={isChecking}
                    className="min-h-11"
                >
                    {t('app.ingredients.submit_cta')}
                </Button>

                {/* Duplicates dialog */}
                <Dialog open={duplicatesDialogOpen} onOpenChange={setDuplicatesDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('app.ingredients.submit_dialog_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('app.ingredients.submit_dialog_body_duplicates')}
                            </DialogDescription>
                        </DialogHeader>
                        <ul className="flex flex-col gap-1 text-sm">
                            {duplicateMatches.slice(0, 5).map((match) => (
                                <li key={match.id} className="text-muted-foreground">
                                    {match.name}
                                </li>
                            ))}
                        </ul>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">
                                    {t('app.ingredients.submit_dismiss')}
                                </Button>
                            </DialogClose>
                            <Button
                                variant="default"
                                onClick={handleConfirmSubmit}
                                disabled={isSubmitting}
                            >
                                {t('app.ingredients.submit_anyway')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Plain confirmation dialog */}
                <Dialog open={submitDialogOpen} onOpenChange={setSubmitDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('app.ingredients.submit_dialog_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('app.ingredients.submit_dialog_body', {
                                    name: ingredient.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">
                                    {t('app.ingredients.submit_dismiss')}
                                </Button>
                            </DialogClose>
                            <Button
                                variant="default"
                                onClick={handleConfirmSubmit}
                                disabled={isSubmitting}
                            >
                                {t('app.ingredients.submit_confirm')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </>
        );
    }

    // Submitted — show badge + withdraw button
    if (status === 'submitted') {
        return (
            <>
                <SubmissionStatusBadge status="submitted" />

                {can.withdraw && (
                    <Button
                        variant="outline"
                        onClick={() => setWithdrawDialogOpen(true)}
                        className="min-h-11"
                    >
                        {t('app.ingredients.withdraw_cta')}
                    </Button>
                )}

                <Dialog open={withdrawDialogOpen} onOpenChange={setWithdrawDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('app.ingredients.withdraw_dialog_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('app.ingredients.withdraw_dialog_body', {
                                    name: ingredient.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">
                                    {t('app.ingredients.withdraw_dismiss')}
                                </Button>
                            </DialogClose>
                            <Button
                                variant="default"
                                onClick={handleWithdraw}
                                disabled={isSubmitting}
                            >
                                {t('app.ingredients.withdraw_confirm')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </>
        );
    }

    // Approved — show badge only
    if (status === 'approved') {
        return <SubmissionStatusBadge status="approved" />;
    }

    return null;
}
