import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { toast } from 'sonner';
import { AllergenPanel } from '@/components/ingredients/allergen-panel';
import { NutritionPanel } from '@/components/ingredients/nutrition-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    approve as adminIngredientSubmissionsApprove,
    reject as adminIngredientSubmissionsReject,
} from '@/routes/admin/ingredient-submissions';
import { index as adminIngredientsIndex } from '@/routes/admin/ingredients';
import type { IngredientDetail } from '@/types/ingredient';

interface PriorRejection {
    notes: string;
    reviewed_at: string;
    reviewer: string;
}

interface SubmissionDetail {
    id: number;
    submission_number: number;
    status: string;
    submitted_at: string;
    submitter: { name: string };
    ingredient: {
        id: number;
        name: string;
        category: { name: string; parent: string | null };
    };
    completeness: {
        nutrition_filled: boolean;
        allergens_set: boolean;
        conversions_added: boolean;
    };
    prior_rejections: PriorRejection[];
}

interface IngredientSubmissionShowProps {
    submission: SubmissionDetail;
    ingredient: IngredientDetail;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function IngredientSubmissionShow({
    submission,
    ingredient,
}: IngredientSubmissionShowProps) {
    const { t } = useLaravelReactI18n();
    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [approveNotes, setApproveNotes] = useState('');
    const [rejectNotes, setRejectNotes] = useState('');
    const [rejectError, setRejectError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    function handleApprove() {
        setIsSubmitting(true);
        router.post(
            adminIngredientSubmissionsApprove({ submission: submission.id }).url,
            { notes: approveNotes || null },
            {
                onSuccess: () => {
                    toast.success(t('app.ingredients.approve_toast'));
                    setApproveOpen(false);
                },
                onError: () => {
                    toast.error(t('app.ingredients.approve_error'));
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    }

    function handleReject() {
        if (!rejectNotes.trim()) {
            setRejectError(t('app.ingredients.reject_note_error'));
            return;
        }

        setRejectError('');
        setIsSubmitting(true);
        router.post(
            adminIngredientSubmissionsReject({ submission: submission.id }).url,
            { notes: rejectNotes },
            {
                onSuccess: () => {
                    toast.success(t('app.ingredients.reject_toast'));
                    setRejectOpen(false);
                },
                onError: (errors) => {
                    if (errors.notes) {
                        setRejectError(errors.notes as string);
                    } else {
                        toast.error(t('app.ingredients.reject_error'));
                    }
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    }

    const showPriorHistory =
        submission.submission_number > 1 || submission.prior_rejections.length > 0;

    return (
        <>
            <Head title={`Review: ${ingredient.name}`} />

            <div className="grid gap-6 p-4 lg:grid-cols-[1fr_320px]">
                {/* LEFT: Ingredient data */}
                <div className="flex flex-col gap-6">
                    <div>
                        <h1 className="text-[28px] font-semibold leading-[1.2]">
                            {ingredient.name}
                        </h1>
                        {ingredient.category && (
                            <p className="text-sm text-muted-foreground mt-1">
                                {ingredient.category.parent
                                    ? `${ingredient.category.parent} › ${ingredient.category.name}`
                                    : ingredient.category.name}
                            </p>
                        )}
                    </div>

                    <Tabs defaultValue="nutrition">
                        <TabsList className="w-full flex-wrap sm:w-auto">
                            <TabsTrigger value="nutrition">
                                {t('app.ingredients.tab_nutrition')}
                            </TabsTrigger>
                            <TabsTrigger value="allergens">
                                {t('app.ingredients.tab_allergens')}
                            </TabsTrigger>
                            <TabsTrigger value="conversions">
                                {t('app.ingredients.tab_conversions')}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="nutrition" className="mt-6">
                            <NutritionPanel ingredient={ingredient} />
                        </TabsContent>

                        <TabsContent value="allergens" className="mt-6">
                            <AllergenPanel allergens={ingredient.allergens} />
                        </TabsContent>

                        <TabsContent value="conversions" className="mt-6">
                            {ingredient.conversions.length === 0 ? (
                                <p className="text-[14px] text-muted-foreground">
                                    {t('app.ingredients.conversions_empty')}
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('app.ingredients.price_amount_label')}</TableHead>
                                            <TableHead>{t('app.ingredients.price_unit_label')}</TableHead>
                                            <TableHead>Gram weight</TableHead>
                                            <TableHead>Source</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {ingredient.conversions.map((conversion, index) => (
                                            <TableRow key={index}>
                                                <TableCell>{conversion.from_amount}</TableCell>
                                                <TableCell>
                                                    {conversion.unit
                                                        ? `${conversion.unit.name} (${conversion.unit.symbol})`
                                                        : '—'}
                                                </TableCell>
                                                <TableCell>{conversion.gram_weight} g</TableCell>
                                                <TableCell>{conversion.source}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>

                {/* RIGHT: Moderation panel */}
                <div className="flex flex-col gap-4 lg:sticky lg:top-16 lg:self-start">
                    {/* Submission metadata */}
                    <p className="text-sm text-muted-foreground">
                        Submitted by{' '}
                        <span className="font-medium text-foreground">
                            {submission.submitter.name}
                        </span>{' '}
                        on {formatDate(submission.submitted_at)}
                    </p>

                    {/* Resubmission badge */}
                    {submission.submission_number > 1 && (
                        <Badge variant="secondary" className="w-fit">
                            {t('app.ingredients.resubmit_badge', {
                                n: submission.submission_number,
                            })}
                        </Badge>
                    )}

                    {/* Prior rejection history */}
                    {showPriorHistory && submission.prior_rejections.length > 0 && (
                        <div className="flex flex-col gap-2">
                            {submission.prior_rejections.map((rejection, index) => (
                                <Alert key={index} variant="default">
                                    <AlertDescription>
                                        Rejected on {formatDate(rejection.reviewed_at)} by{' '}
                                        {rejection.reviewer}: {rejection.notes}
                                    </AlertDescription>
                                </Alert>
                            ))}
                        </div>
                    )}

                    {/* Approve button */}
                    <Button
                        variant="default"
                        className="w-full"
                        onClick={() => setApproveOpen(true)}
                    >
                        {t('app.ingredients.approve_cta')}
                    </Button>

                    {/* Reject button */}
                    <Button
                        variant="destructive"
                        className="w-full"
                        onClick={() => setRejectOpen(true)}
                    >
                        {t('app.ingredients.reject_cta')}
                    </Button>
                </div>
            </div>

            {/* Approve Dialog */}
            <Dialog open={approveOpen} onOpenChange={setApproveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('app.ingredients.approve_dialog_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('app.ingredients.approve_dialog_body')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-2">
                        <label className="text-sm font-medium">
                            {t('app.ingredients.approve_note_label')}
                        </label>
                        <Textarea
                            value={approveNotes}
                            onChange={(e) => setApproveNotes(e.target.value)}
                            placeholder=""
                            className="min-h-[80px]"
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">
                                {t('app.ingredients.approve_dismiss')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="default"
                            onClick={handleApprove}
                            disabled={isSubmitting}
                        >
                            {t('app.ingredients.approve_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog
                open={rejectOpen}
                onOpenChange={(open) => {
                    setRejectOpen(open);
                    if (!open) {
                        setRejectError('');
                        setRejectNotes('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('app.ingredients.reject_dialog_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('app.ingredients.reject_dialog_body')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-2">
                        <label className="text-sm font-medium">
                            {t('app.ingredients.reject_note_label')}
                        </label>
                        <Textarea
                            value={rejectNotes}
                            onChange={(e) => {
                                setRejectNotes(e.target.value);
                                if (rejectError) {
                                    setRejectError('');
                                }
                            }}
                            placeholder=""
                            className="min-h-[80px]"
                        />
                        {rejectError && (
                            <p className="text-sm text-destructive">{rejectError}</p>
                        )}
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">
                                {t('app.ingredients.reject_dismiss')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={isSubmitting}
                        >
                            {t('app.ingredients.reject_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

IngredientSubmissionShow.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Review Queue',
            href: adminIngredientsIndex().url,
        },
        {
            title: 'Review Submission',
            href: '#',
        },
    ],
};
