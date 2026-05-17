import { router } from '@inertiajs/react';
import { EllipsisIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { destroy as recipeTestDestroy } from '@/actions/App/Http/Controllers/Recipes/RecipeTestController';
import { TestPhotoGrid } from '@/components/recipes/test-photo-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { RecipeTest } from '@/types/recipe-test';

interface TestCardProps {
    test: RecipeTest;
    recipeId: number;
    onEdit: (test: RecipeTest) => void;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function ratingColorClass(score: number): string {
    if (score >= 7) {
        return 'text-accent';
    }
    if (score <= 3) {
        return 'text-destructive';
    }
    return 'text-foreground';
}

function VerdictBadge({ verdict, t }: { verdict: string; t: (key: string) => string }) {
    if (verdict === 'worked') {
        return (
            <Badge className="bg-accent text-accent-foreground">
                {t('app.tests.verdict_worked')}
            </Badge>
        );
    }
    if (verdict === 'didnt_work') {
        return (
            <Badge variant="outline" className="border-destructive text-destructive">
                {t('app.tests.verdict_didnt_work')}
            </Badge>
        );
    }
    return (
        <Badge variant="secondary">
            {t('app.tests.verdict_inconclusive')}
        </Badge>
    );
}

/**
 * Displays a single recipe test with type/verdict badges, overall score,
 * tasting notes preview, photo strip, and edit/delete actions.
 */
export function TestCard({ test, recipeId, onEdit }: TestCardProps) {
    const { t } = useTranslations();
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    // Default dimension scores to show (non-custom ratings)
    const defaultRatings = test.ratings
        ? test.ratings.filter((r) => !r.is_custom).slice(0, 4)
        : [];

    function handleDelete() {
        if (deleting) {
            return;
        }
        setDeleting(true);
        router.delete(recipeTestDestroy({ recipe: recipeId, test: test.id }).url, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('app.tests.toast_deleted'));
                setDeleteOpen(false);
            },
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Card className={cn('transition-colors duration-150 hover:bg-muted', deleting && 'pointer-events-none opacity-60')}>
                <CardHeader className="pb-0">
                    {/* Header row */}
                    <div className="flex items-center justify-between gap-2">
                        {/* Left: type badge + version */}
                        <div className="flex items-center gap-2">
                            {test.type === 'trial' ? (
                                <Badge variant="secondary">{t('app.tests.type_trial')}</Badge>
                            ) : (
                                <Badge variant="outline">{t('app.tests.type_experiment')}</Badge>
                            )}
                            <span className="text-[14px] text-muted-foreground">
                                v{test.version_number}
                            </span>
                        </div>

                        {/* Right: date + actions dropdown */}
                        <div className="flex items-center gap-2">
                            <span className="text-[14px] text-muted-foreground">
                                {formatDate(test.tested_at)}
                            </span>
                            <TooltipProvider>
                                <Tooltip>
                                    <DropdownMenu>
                                        <TooltipTrigger asChild>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('app.tests.test_actions')}
                                                    className="size-9 shrink-0"
                                                >
                                                    <EllipsisIcon className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                        </TooltipTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onClick={() => onEdit(test)}>
                                                <PencilIcon className="size-4" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() => setDeleteOpen(true)}
                                            >
                                                <Trash2Icon className="size-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <TooltipContent>
                                        <span>{t('app.tests.test_actions')}</span>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="flex flex-col gap-3 pt-3">
                    {/* Overall score */}
                    <div className="flex flex-col gap-1">
                        <span className={cn('text-[20px] font-semibold leading-tight', ratingColorClass(test.overall_rating))}>
                            {test.overall_rating}/10
                        </span>

                        {/* Default dimension scores (hidden on mobile) */}
                        {defaultRatings.length > 0 && (
                            <div className="hidden gap-3 sm:flex">
                                {defaultRatings.map((r) => (
                                    <span key={r.dimension} className="text-[14px] text-muted-foreground">
                                        {r.dimension}: {r.score ?? '—'}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Verdict row (experiment only) */}
                    {test.type === 'experiment' && test.verdict && (
                        <div className="flex items-center gap-2">
                            <VerdictBadge verdict={test.verdict} t={t} />
                            {test.hypothesis && (
                                <span className="text-[14px] text-muted-foreground">
                                    <span className="font-medium">Hypothesis:</span>{' '}
                                    <span className="line-clamp-1">{test.hypothesis}</span>
                                </span>
                            )}
                        </div>
                    )}

                    {/* Tasting notes preview */}
                    {test.tasting_notes && (
                        <p className="line-clamp-2 text-[16px] leading-[1.5] text-foreground">
                            {test.tasting_notes}
                        </p>
                    )}

                    {/* Photo strip */}
                    {test.photos.length > 0 && (
                        <TestPhotoGrid mode="display" photos={test.photos} />
                    )}
                </CardContent>
            </Card>

            {/* Delete confirmation dialog */}
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('app.tests.delete_title')}</DialogTitle>
                        <DialogDescription>{t('app.tests.delete_body')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        {/* Focus lands on Keep test (dismiss) per WCAG destructive action pattern */}
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setDeleteOpen(false)}
                            autoFocus
                        >
                            {t('app.tests.delete_keep')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            {deleting ? (
                                <>
                                    <span className="mr-2 inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    {t('app.tests.delete_confirm')}
                                </>
                            ) : (
                                t('app.tests.delete_confirm')
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
