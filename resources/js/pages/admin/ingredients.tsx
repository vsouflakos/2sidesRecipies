import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { SubmissionCompleteness } from '@/components/ingredients/submission-completeness';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { show as adminIngredientSubmissionsShow } from '@/routes/admin/ingredient-submissions';

interface SubmissionRow {
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
    prior_rejections: Array<{
        notes: string;
        reviewed_at: string;
        reviewer: string;
    }>;
}

interface IngredientsQueueProps {
    submissions: SubmissionRow[];
}

function relativeDate(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Today';
    }

    if (diffDays === 1) {
        return '1 day ago';
    }

    if (diffDays < 7) {
        return `${diffDays} days ago`;
    }

    if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        return weeks === 1 ? '1 week ago' : `${weeks} weeks ago`;
    }

    const months = Math.floor(diffDays / 30);
    return months === 1 ? '1 month ago' : `${months} months ago`;
}

export default function IngredientsQueue({ submissions }: IngredientsQueueProps) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('app.ingredients.queue_heading')} />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">
                    {t('app.ingredients.queue_heading')}
                </h1>

                {submissions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-lg font-medium text-muted-foreground">
                            {t('app.ingredients.queue_empty_heading')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('app.ingredients.queue_empty_body')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ingredient Name</TableHead>
                                    <TableHead className="w-40">Submitter</TableHead>
                                    <TableHead className="w-36">Category</TableHead>
                                    <TableHead className="w-30">Completeness</TableHead>
                                    <TableHead className="w-30">Submitted</TableHead>
                                    <TableHead className="w-20">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {submissions.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <span>{row.ingredient.name}</span>
                                                {row.submission_number > 1 && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge variant="secondary">
                                                                {t('app.ingredients.resubmit_badge', {
                                                                    n: row.submission_number,
                                                                })}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>Previously rejected — see rejection history on the review page.</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>{row.submitter.name}</TableCell>
                                        <TableCell>{row.ingredient.category.name}</TableCell>
                                        <TableCell>
                                            <SubmissionCompleteness completeness={row.completeness} />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {relativeDate(row.submitted_at)}
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={adminIngredientSubmissionsShow({ submission: row.id }).url}>
                                                    Review
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

IngredientsQueue.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Review Queue',
            href: '/admin/ingredients',
        },
    ],
};
