import { useForm } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { store as recipeTestStore, update as recipeTestUpdate } from '@/actions/App/Http/Controllers/Recipes/RecipeTestController';
import { RatingDimensionRow } from '@/components/recipes/rating-dimension-row';
import { TestPhotoGrid } from '@/components/recipes/test-photo-grid';
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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import {
    DEFAULT_RATING_DIMENSIONS,
    MAX_TEST_PHOTOS,
    type ChangeRow,
    type RatingDimension,
    type RecipeTest,
    type TestVersionOption,
} from '@/types/recipe-test';

interface TestRecordModalProps {
    open: boolean;
    onClose: () => void;
    recipeId: number;
    versions: TestVersionOption[];
    currentVersionId: number | null;
    editingTest?: RecipeTest | null;
}

function todayString(): string {
    return new Date().toISOString().split('T')[0];
}

interface FormData {
    type: 'trial' | 'experiment';
    recipe_version_id: number | null;
    tested_at: string;
    overall_rating: number;
    tasting_notes: string;
    ratings: RatingDimension[];
    photos: File[];
    hypothesis: string;
    outcome_narrative: string;
    verdict: 'worked' | 'didnt_work' | 'inconclusive' | null;
    change_rows: ChangeRow[];
    deleted_photo_ids: number[];
    [key: string]: unknown;
}

function buildInitialData(
    currentVersionId: number | null,
    editingTest?: RecipeTest | null,
): FormData {
    if (editingTest) {
        return {
            type: editingTest.type,
            recipe_version_id: editingTest.recipe_version_id,
            tested_at: editingTest.tested_at,
            overall_rating: editingTest.overall_rating,
            tasting_notes: editingTest.tasting_notes ?? '',
            ratings: editingTest.ratings ?? DEFAULT_RATING_DIMENSIONS.map((d) => ({ dimension: d, score: null, is_custom: false })),
            photos: [],
            hypothesis: editingTest.hypothesis ?? '',
            outcome_narrative: editingTest.outcome_narrative ?? '',
            verdict: editingTest.verdict ?? null,
            change_rows: editingTest.change_rows ?? [],
            deleted_photo_ids: [],
        };
    }

    return {
        type: 'trial',
        recipe_version_id: currentVersionId,
        tested_at: todayString(),
        overall_rating: 5,
        tasting_notes: '',
        ratings: DEFAULT_RATING_DIMENSIONS.map((d) => ({ dimension: d, score: null, is_custom: false })),
        photos: [] as File[],
        hypothesis: '',
        outcome_narrative: '',
        verdict: null,
        change_rows: [] as ChangeRow[],
        deleted_photo_ids: [],
    };
}

const MAX_CHANGE_ROWS = 20;

/**
 * Full record/edit dialog for recipe tests.
 * Mode-switches between trial and experiment form sections.
 * Submits multipart with forceFormData: true.
 */
export function TestRecordModal({
    open,
    onClose,
    recipeId,
    versions,
    currentVersionId,
    editingTest,
}: TestRecordModalProps) {
    const { t } = useTranslations();
    const isEditing = !!editingTest;

    const form = useForm<FormData>(buildInitialData(currentVersionId, editingTest));

    // Track existing photos being deleted (edit mode only)
    const [deletedPhotoIds, setDeletedPhotoIds] = useState<number[]>([]);

    // Sort versions newest first
    const sortedVersions = [...versions].sort((a, b) => b.version_number - a.version_number);

    function handleTypeChange(value: string) {
        if (value === 'trial' || value === 'experiment') {
            form.setData('type', value);
        }
    }

    function handleVersionChange(value: string) {
        form.setData('recipe_version_id', value ? Number(value) : null);
    }

    function handleScoreChange(index: number, score: number | null) {
        const updated = form.data.ratings.map((r, i) => (i === index ? { ...r, score } : r));
        form.setData('ratings', updated);
    }

    function handleDimensionChange(index: number, dimension: string) {
        const updated = form.data.ratings.map((r, i) => (i === index ? { ...r, dimension } : r));
        form.setData('ratings', updated);
    }

    function handleRemoveDimension(index: number) {
        const updated = form.data.ratings.filter((_, i) => i !== index);
        form.setData('ratings', updated);
    }

    function handleAddDimension() {
        form.setData('ratings', [
            ...form.data.ratings,
            { dimension: '', score: null, is_custom: true },
        ]);
    }

    // Change rows
    function handleAddChangeRow() {
        if (form.data.change_rows.length >= MAX_CHANGE_ROWS) {
            return;
        }
        form.setData('change_rows', [
            ...form.data.change_rows,
            { what_changed: '', expected_effect: null, actual_effect: null },
        ]);
    }

    function handleRemoveChangeRow(index: number) {
        form.setData(
            'change_rows',
            form.data.change_rows.filter((_, i) => i !== index),
        );
    }

    function handleChangeRowField(index: number, field: keyof ChangeRow, value: string) {
        const updated = form.data.change_rows.map((row, i) =>
            i === index ? { ...row, [field]: value || null } : row,
        );
        form.setData('change_rows', updated);
    }

    // Photo handlers
    function handleAddFiles(files: File[]) {
        const combined = [...form.data.photos, ...files].slice(0, MAX_TEST_PHOTOS - (editingTest?.photos.length ?? 0));
        form.setData('photos', combined);
    }

    function handleRemoveStaged(index: number) {
        form.setData(
            'photos',
            form.data.photos.filter((_, i) => i !== index),
        );
    }

    function handleRemoveExisting(id: number) {
        setDeletedPhotoIds((prev) => [...prev, id]);
    }

    const existingPhotos = isEditing
        ? (editingTest?.photos ?? []).filter((p) => !deletedPhotoIds.includes(p.id))
        : [];

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (isEditing && editingTest) {
            form.transform((data) => ({
                ...data,
                deleted_photo_ids: deletedPhotoIds,
                _method: 'PUT',
            }));

            form.post(recipeTestUpdate({ recipe: recipeId, test: editingTest.id }).url, {
                forceFormData: true,
                onSuccess: () => {
                    toast.success(t('app.tests.toast_updated'));
                    setDeletedPhotoIds([]);
                    onClose();
                },
                onError: () => toast.error(t('app.tests.toast_server_error')),
            });
        } else {
            form.post(recipeTestStore({ recipe: recipeId }).url, {
                forceFormData: true,
                onSuccess: () => {
                    toast.success(t('app.tests.toast_saved'));
                    onClose();
                },
                onError: () => toast.error(t('app.tests.toast_server_error')),
            });
        }
    }

    function handleClose() {
        form.reset();
        setDeletedPhotoIds([]);
        onClose();
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => { if (!isOpen) { handleClose(); } }}>
            <DialogContent className={cn('w-full sm:max-w-2xl', form.processing && 'pointer-events-none opacity-70')}>
                <DialogHeader>
                    <DialogTitle className="text-[28px] font-semibold leading-tight">
                        {isEditing ? t('app.tests.modal_title_edit') : t('app.tests.modal_title_new')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('app.tests.modal_description')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 overflow-y-auto max-h-[70vh] pr-1">
                    {/* Section 1 — Type & Version */}
                    <div className="flex flex-col gap-4">
                        {/* Type toggle */}
                        <div className="flex flex-col gap-2">
                            <Label>{t('app.tests.modal_title_new')}</Label>
                            <ToggleGroup
                                type="single"
                                value={form.data.type}
                                onValueChange={handleTypeChange}
                                role="group"
                                aria-label="Test type"
                                className="w-fit"
                            >
                                <ToggleGroupItem
                                    value="trial"
                                    className={cn(
                                        form.data.type === 'trial' && 'bg-accent text-accent-foreground font-semibold',
                                    )}
                                >
                                    {t('app.tests.type_trial')}
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="experiment"
                                    className={cn(
                                        form.data.type === 'experiment' && 'bg-accent text-accent-foreground font-semibold',
                                    )}
                                >
                                    {t('app.tests.type_experiment')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                            {form.errors.type && (
                                <p className="text-sm text-destructive">{form.errors.type}</p>
                            )}
                        </div>

                        {/* Version picker */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="version-picker">{t('app.tests.version_placeholder')}</Label>
                            <Select
                                value={form.data.recipe_version_id ? String(form.data.recipe_version_id) : ''}
                                onValueChange={handleVersionChange}
                            >
                                <SelectTrigger id="version-picker" aria-label="Recipe version for this test" className="w-full">
                                    <SelectValue placeholder={t('app.tests.version_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {sortedVersions.map((v) => (
                                        <SelectItem key={v.id} value={String(v.id)}>
                                            v{v.version_number} — {v.committed_at}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.recipe_version_id && (
                                <p className="text-sm text-destructive">{form.errors.recipe_version_id}</p>
                            )}
                        </div>

                        {/* Tested on date */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="tested-at">Tested on</Label>
                            <Input
                                id="tested-at"
                                type="date"
                                required
                                value={form.data.tested_at}
                                onChange={(e) => form.setData('tested_at', e.target.value)}
                                aria-invalid={!!form.errors.tested_at}
                            />
                            {form.errors.tested_at && (
                                <p className="text-sm text-destructive">{form.errors.tested_at}</p>
                            )}
                        </div>
                    </div>

                    <Separator />

                    {/* Section 2 — Feedback */}
                    <div className="flex flex-col gap-4">
                        {/* Overall rating */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="overall-rating">{t('app.tests.overall_rating_label')}</Label>
                            <Input
                                id="overall-rating"
                                type="number"
                                min={1}
                                max={10}
                                required
                                value={form.data.overall_rating}
                                onChange={(e) => form.setData('overall_rating', Number(e.target.value))}
                                aria-invalid={!!form.errors.overall_rating}
                                className="w-24"
                            />
                            {form.errors.overall_rating && (
                                <p className="text-sm text-destructive">{form.errors.overall_rating}</p>
                            )}
                        </div>

                        {/* Tasting notes */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="tasting-notes">Tasting notes</Label>
                            <Textarea
                                id="tasting-notes"
                                rows={3}
                                value={form.data.tasting_notes}
                                onChange={(e) => form.setData('tasting_notes', e.target.value)}
                                placeholder={t('app.tests.tasting_notes_placeholder')}
                                aria-invalid={!!form.errors.tasting_notes}
                            />
                            {form.errors.tasting_notes && (
                                <p className="text-sm text-destructive">{form.errors.tasting_notes}</p>
                            )}
                        </div>
                    </div>

                    <Separator />

                    {/* Section 3 — Ratings */}
                    <div className="flex flex-col gap-3">
                        <h3 className="text-[20px] font-semibold leading-tight">
                            {t('app.tests.ratings_heading')}
                        </h3>
                        <div className="flex flex-col gap-1">
                            {form.data.ratings.map((rating, index) => (
                                <RatingDimensionRow
                                    key={index}
                                    dimension={rating.dimension}
                                    score={rating.score}
                                    isCustom={rating.is_custom}
                                    onScoreChange={(score) => handleScoreChange(index, score)}
                                    onDimensionChange={(dim) => handleDimensionChange(index, dim)}
                                    onRemove={() => handleRemoveDimension(index)}
                                />
                            ))}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleAddDimension}
                            className="w-fit gap-1"
                        >
                            <PlusIcon className="size-4" />
                            {t('app.tests.add_dimension')}
                        </Button>
                    </div>

                    {/* Section 4 — Experiment (conditional) */}
                    {form.data.type === 'experiment' && (
                        <>
                            <Separator />
                            <div className="flex flex-col gap-4">
                                <h3 className="text-[20px] font-semibold leading-tight">
                                    {t('app.tests.experiment_heading')}
                                </h3>

                                {/* Hypothesis */}
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="hypothesis">{t('app.tests.hypothesis_label')}</Label>
                                    <Textarea
                                        id="hypothesis"
                                        rows={2}
                                        required
                                        value={form.data.hypothesis}
                                        onChange={(e) => form.setData('hypothesis', e.target.value)}
                                        placeholder={t('app.tests.hypothesis_placeholder')}
                                        aria-invalid={!!form.errors.hypothesis}
                                    />
                                    {form.errors.hypothesis && (
                                        <p className="text-sm text-destructive">{form.errors.hypothesis}</p>
                                    )}
                                </div>

                                {/* Outcome */}
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="outcome">{t('app.tests.outcome_label')}</Label>
                                    <Textarea
                                        id="outcome"
                                        rows={2}
                                        value={form.data.outcome_narrative}
                                        onChange={(e) => form.setData('outcome_narrative', e.target.value)}
                                        placeholder={t('app.tests.outcome_placeholder')}
                                        aria-invalid={!!form.errors.outcome_narrative}
                                    />
                                    {form.errors.outcome_narrative && (
                                        <p className="text-sm text-destructive">{form.errors.outcome_narrative}</p>
                                    )}
                                </div>

                                {/* Verdict */}
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="verdict">{t('app.tests.verdict_label')}</Label>
                                    <Select
                                        value={form.data.verdict ?? ''}
                                        onValueChange={(val) =>
                                            form.setData(
                                                'verdict',
                                                (val as 'worked' | 'didnt_work' | 'inconclusive') || null,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="verdict" className="w-full">
                                            <SelectValue placeholder={t('app.tests.verdict_label')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="worked">{t('app.tests.verdict_worked')}</SelectItem>
                                            <SelectItem value="didnt_work">{t('app.tests.verdict_didnt_work')}</SelectItem>
                                            <SelectItem value="inconclusive">{t('app.tests.verdict_inconclusive')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.verdict && (
                                        <p className="text-sm text-destructive">{form.errors.verdict}</p>
                                    )}
                                </div>

                                {/* Change rows */}
                                <div className="flex flex-col gap-2">
                                    {form.data.change_rows.map((row, index) => (
                                        <div key={index} className="flex gap-2 items-start">
                                            <Input
                                                type="text"
                                                placeholder={t('app.tests.change_what')}
                                                value={row.what_changed}
                                                onChange={(e) => handleChangeRowField(index, 'what_changed', e.target.value)}
                                                className="text-sm flex-1"
                                            />
                                            <Input
                                                type="text"
                                                placeholder={t('app.tests.change_expected')}
                                                value={row.expected_effect ?? ''}
                                                onChange={(e) => handleChangeRowField(index, 'expected_effect', e.target.value)}
                                                className="text-sm flex-1"
                                            />
                                            <Input
                                                type="text"
                                                placeholder={t('app.tests.change_actual')}
                                                value={row.actual_effect ?? ''}
                                                onChange={(e) => handleChangeRowField(index, 'actual_effect', e.target.value)}
                                                className="text-sm flex-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveChangeRow(index)}
                                                aria-label={`Remove change row ${index + 1}`}
                                                className="size-9 shrink-0"
                                            >
                                                ×
                                            </Button>
                                        </div>
                                    ))}
                                    {form.data.change_rows.length < MAX_CHANGE_ROWS && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleAddChangeRow}
                                            className="w-fit gap-1"
                                        >
                                            <PlusIcon className="size-4" />
                                            {t('app.tests.add_change')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </>
                    )}

                    <Separator />

                    {/* Section 5 — Photos */}
                    <div className="flex flex-col gap-3">
                        <h3 className="text-[20px] font-semibold leading-tight">
                            {t('app.tests.photos_heading')}
                        </h3>
                        <TestPhotoGrid
                            mode="upload"
                            stagedFiles={form.data.photos}
                            existingPhotos={existingPhotos}
                            onAddFiles={handleAddFiles}
                            onRemoveStaged={handleRemoveStaged}
                            onRemoveExisting={handleRemoveExisting}
                        />
                    </div>
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={handleClose}
                        disabled={form.processing}
                    >
                        {t('app.tests.discard')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={form.processing}
                        onClick={handleSubmit}
                    >
                        {form.processing ? (
                            <>
                                <span className="mr-2 inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                {t('app.tests.save_loading')}
                            </>
                        ) : (
                            t('app.tests.save_test')
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
