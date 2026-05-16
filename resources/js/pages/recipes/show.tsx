import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useCallback, useState } from 'react';
import {
    EllipsisVerticalIcon,
    ImageIcon,
    PlusIcon,
    XIcon,
} from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { SectionBlock } from '@/components/recipes/recipe-builder/section-block';
import { RecipeMetadataBlock } from '@/components/recipes/recipe-builder/recipe-metadata-block';
import { QuickCreateIngredientModal } from '@/components/recipes/recipe-builder/quick-create-ingredient-modal';
import { SaveVersionDialog } from '@/components/recipes/recipe-builder/save-version-dialog';
import { VersionHistorySheet } from '@/components/recipes/recipe-builder/version-history-sheet';
import { MetricsPanel } from '@/components/recipes/metrics-panel/metrics-panel';
import { useRecipeAutosave } from '@/hooks/use-recipe-autosave';
import { destroy as destroyRecipe } from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import { store as duplicateRecipe } from '@/actions/App/Http/Controllers/Recipes/RecipeDuplicateController';
import { recall as recallDraft } from '@/actions/App/Http/Controllers/Recipes/RecipeDraftController';
import type {
    CategoryNode,
} from '@/types/ingredient';
import type {
    ComponentSearchResult,
    CuisineOption,
    RecipeDraft,
    RecipeIngredientLine,
    RecipeMetrics,
    RecipeSection,
    RecipeShowProps,
    RecipeStep,
    RecipeVersion,
    TagOption,
    UnitOption,
} from '@/types/recipe';

/** Inertia page props for the builder. */
interface ShowPageProps extends RecipeShowProps {
    /** Categories for quick-create ingredient modal. */
    categories: CategoryNode[];
}

let nextTempId = -1;

function getNextTempId(): number {
    return nextTempId--;
}

export default function RecipeShow({
    recipe,
    draft: initialDraft,
    metrics,
    versions,
    cuisines,
    units,
    tags: availableTags,
    can,
    categories,
}: ShowPageProps) {
    const { t } = useLaravelReactI18n();
    const { save, status } = useRecipeAutosave(recipe.id);

    const [draft, setDraft] = useState<RecipeDraft>(initialDraft);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [saveVersionOpen, setSaveVersionOpen] = useState(false);
    const [recallDisabled, setRecallDisabled] = useState(false);
    const [quickCreateOpen, setQuickCreateOpen] = useState(false);
    const [quickCreateQuery, setQuickCreateQuery] = useState('');
    const [quickCreateSectionId, setQuickCreateSectionId] = useState<number | null>(null);

    /** The edit log is considered empty when edit_sequence is 0. */
    const editLogEmpty = draft.edit_sequence === 0;

    /** Update draft state and trigger auto-save. */
    const updateDraft = useCallback(
        (action: string, updater: (prev: RecipeDraft) => RecipeDraft) => {
            setDraft((prev) => {
                const updated = updater(prev);
                save(action, updated as unknown as Record<string, unknown>);
                return updated;
            });
        },
        [save],
    );

    /** ---- Name ---- */
    function handleNameChange(name: string) {
        updateDraft('update_name', (prev) => ({ ...prev, name }));
    }

    /** ---- Metadata ---- */
    function handleMetadataChange(updated: Partial<RecipeDraft>) {
        updateDraft('update_metadata', (prev) => ({ ...prev, ...updated }));
    }

    /** ---- Sections ---- */
    function handleAddSection() {
        const newSection: RecipeSection = {
            id: getNextTempId(),
            name: '',
            order: (draft.sections?.length ?? 0) + 1,
            lines: [],
            steps: [],
        };
        updateDraft('add_section', (prev) => ({
            ...prev,
            sections: [...(prev.sections ?? []), newSection],
        }));
    }

    function handleSectionNameChange(sectionId: number, name: string) {
        updateDraft('update_section_name', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId ? { ...s, name } : s,
            ),
        }));
    }

    function handleMoveSection(sectionId: number, direction: 'up' | 'down') {
        updateDraft('reorder_section', (prev) => {
            const sections = [...(prev.sections ?? [])];
            const idx = sections.findIndex((s) => s.id === sectionId);
            if (idx < 0) { return prev; }
            const targetIdx = direction === 'up' ? idx - 1 : idx + 1;
            if (targetIdx < 0 || targetIdx >= sections.length) { return prev; }
            [sections[idx], sections[targetIdx]] = [sections[targetIdx], sections[idx]];
            return { ...prev, sections };
        });
    }

    function handleDeleteSection(sectionId: number) {
        updateDraft('delete_section', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).filter((s) => s.id !== sectionId),
        }));
    }

    /** ---- Ingredient Lines ---- */
    function handleLineChange(
        sectionId: number,
        lineId: number,
        updated: Partial<RecipeIngredientLine>,
    ) {
        updateDraft('update_line', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? {
                          ...s,
                          lines: s.lines.map((l) =>
                              l.id === lineId ? { ...l, ...updated } : l,
                          ),
                      }
                    : s,
            ),
        }));
    }

    function handleAddIngredient(sectionId: number, result: ComponentSearchResult) {
        const newLine: RecipeIngredientLine = {
            id: getNextTempId(),
            ingredient_id: result.id,
            sub_recipe_version_id: null,
            name: result.name,
            quantity: '',
            unit_id: null,
            prep_note: null,
            yield_pct: '100',
            is_flour_base: false,
            order: 0,
        };
        updateDraft('add_ingredient', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? { ...s, lines: [...s.lines, newLine] }
                    : s,
            ),
        }));
    }

    function handleAddSubRecipe(sectionId: number, result: ComponentSearchResult) {
        const newLine: RecipeIngredientLine = {
            id: getNextTempId(),
            ingredient_id: null,
            sub_recipe_version_id: result.id,
            name: result.name,
            quantity: '',
            unit_id: null,
            prep_note: null,
            yield_pct: '100',
            is_flour_base: false,
            order: 0,
        };
        updateDraft('add_sub_recipe', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? { ...s, lines: [...s.lines, newLine] }
                    : s,
            ),
        }));
    }

    function handleDeleteLine(sectionId: number, lineId: number) {
        updateDraft('delete_line', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? { ...s, lines: s.lines.filter((l) => l.id !== lineId) }
                    : s,
            ),
        }));
    }

    /** ---- Steps ---- */
    function handleStepChange(
        sectionId: number,
        stepId: number,
        updated: Partial<RecipeStep>,
    ) {
        updateDraft('update_step', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? {
                          ...s,
                          steps: s.steps.map((st) =>
                              st.id === stepId ? { ...st, ...updated } : st,
                          ),
                      }
                    : s,
            ),
        }));
    }

    function handleAddStep(sectionId: number) {
        const newStep: RecipeStep = {
            id: getNextTempId(),
            instruction: '',
            order: 0,
            step_image_path: null,
        };
        updateDraft('add_step', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? { ...s, steps: [...s.steps, newStep] }
                    : s,
            ),
        }));
    }

    function handleMoveStep(sectionId: number, stepId: number, direction: 'up' | 'down') {
        updateDraft('reorder_step', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) => {
                if (s.id !== sectionId) { return s; }
                const steps = [...s.steps];
                const idx = steps.findIndex((st) => st.id === stepId);
                if (idx < 0) { return s; }
                const targetIdx = direction === 'up' ? idx - 1 : idx + 1;
                if (targetIdx < 0 || targetIdx >= steps.length) { return s; }
                [steps[idx], steps[targetIdx]] = [steps[targetIdx], steps[idx]];
                return { ...s, steps };
            }),
        }));
    }

    function handleDeleteStep(sectionId: number, stepId: number) {
        updateDraft('delete_step', (prev) => ({
            ...prev,
            sections: (prev.sections ?? []).map((s) =>
                s.id === sectionId
                    ? { ...s, steps: s.steps.filter((st) => st.id !== stepId) }
                    : s,
            ),
        }));
    }

    /** ---- Quick-create ---- */
    function handleQuickCreate(sectionId: number, query: string) {
        setQuickCreateQuery(query);
        setQuickCreateSectionId(sectionId);
        setQuickCreateOpen(true);
    }

    function handleQuickCreateSuccess(result: ComponentSearchResult) {
        if (quickCreateSectionId !== null) {
            handleAddIngredient(quickCreateSectionId, result);
        }
    }

    /** ---- Recipe actions ---- */
    function handleDeleteRecipe() {
        router.delete(destroyRecipe({ recipe: recipe.id }).url, {
            onSuccess: () => {
                /** Redirect handled by the server */
            },
        });
    }

    function handleDuplicateRecipe() {
        router.post(duplicateRecipe({ recipe: recipe.id }).url, {}, {
            onSuccess: () => {
                toast.success(t('app.recipes.duplicate_toast'));
            },
        });
    }

    /** ---- Recall ---- */
    function handleRecall() {
        if (editLogEmpty || recallDisabled) {
            return;
        }

        router.post(
            recallDraft({ recipe: recipe.id }).url,
            { expected_sequence: draft.edit_sequence },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['draft', 'metrics'],
                onSuccess: () => {
                    toast.success(t('app.recipes.recall_toast'));
                },
                onError: (errors) => {
                    /** HTTP 409 — sequence mismatch from concurrent edit */
                    if (errors && Object.keys(errors).length > 0) {
                        toast.error(t('app.recipes.recall_conflict_toast'));
                        setRecallDisabled(true);
                    }
                },
            },
        );
    }

    const sections = draft.sections ?? [];

    return (
        <>
            <Head title={draft.name || t('app.recipes.builder_name_placeholder')} />

            <div className="flex h-full flex-1 flex-col">
                {/* Builder header */}
                <div className="flex items-center gap-4 border-b border-border px-4 py-3">
                    <div className="flex flex-1 items-center gap-3">
                        <Input
                            type="text"
                            value={draft.name}
                            onChange={(e) => handleNameChange(e.target.value)}
                            placeholder={t('app.recipes.builder_name_placeholder')}
                            className="h-auto flex-1 border-0 bg-transparent p-0 text-[28px] font-semibold leading-[1.2] shadow-none focus-visible:ring-0"
                        />

                        {/* Auto-save indicator */}
                        {status !== 'idle' && (
                            <span className="shrink-0 text-sm text-muted-foreground">
                                {status === 'saving'
                                    ? t('app.recipes.builder_saving')
                                    : t('app.recipes.builder_saved')}
                            </span>
                        )}
                    </div>

                    {/* Version history button */}
                    <VersionHistorySheet
                        recipeId={recipe.id}
                        versions={versions}
                        currentVersionNumber={
                            versions.find((v) => v.is_current)?.version_number ?? 1
                        }
                    />

                    {/* Save Version button */}
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        onClick={() => setSaveVersionOpen(true)}
                    >
                        {t('app.recipes.save_version_btn')}
                    </Button>

                    {/* Recall button — aria-disabled (not disabled) when edit log is empty */}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    aria-disabled={editLogEmpty || recallDisabled ? 'true' : undefined}
                                    onClick={handleRecall}
                                    className={
                                        editLogEmpty || recallDisabled
                                            ? 'pointer-events-none opacity-50'
                                            : undefined
                                    }
                                >
                                    {t('app.recipes.recall_btn')}
                                </Button>
                            </TooltipTrigger>
                            {(editLogEmpty || recallDisabled) && (
                                <TooltipContent>
                                    <p>Nothing to undo</p>
                                </TooltipContent>
                            )}
                        </Tooltip>
                    </TooltipProvider>

                    {/* Recipe actions dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" variant="ghost" size="icon">
                                <EllipsisVerticalIcon className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={handleDuplicateRecipe}>
                                {t('app.recipes.duplicate_menu')}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => setShowDeleteDialog(true)}
                            >
                                {t('app.recipes.delete_menu')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Two-column builder layout */}
                <div className="flex flex-1 overflow-auto lg:grid lg:grid-cols-[65%_35%]">
                    {/* LEFT: builder column */}
                    <div className="flex flex-col gap-6 overflow-y-auto p-4">
                        {/* Hero image upload */}
                        <div className="space-y-2">
                            {draft.hero_image_path ? (
                                <div className="relative">
                                    <img
                                        src={draft.hero_image_path}
                                        alt={draft.name}
                                        className="aspect-video w-full rounded-lg object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="absolute top-2 right-2 gap-1 bg-background/80 text-sm"
                                        onClick={() =>
                                            updateDraft('remove_hero', (prev) => ({
                                                ...prev,
                                                hero_image_path: null,
                                            }))
                                        }
                                    >
                                        <XIcon className="size-4" />
                                        {t('app.recipes.builder_remove_hero')}
                                    </Button>
                                </div>
                            ) : (
                                <Button type="button" variant="outline" className="gap-2">
                                    <ImageIcon className="size-4" />
                                    {t('app.recipes.builder_upload_hero')}
                                </Button>
                            )}
                        </div>

                        {/* Metadata block */}
                        <RecipeMetadataBlock
                            draft={draft}
                            cuisines={cuisines}
                            units={units}
                            availableTags={availableTags}
                            onChange={handleMetadataChange}
                        />

                        {/* Section blocks */}
                        <div className="flex flex-col gap-6">
                            {sections.map((section, idx) => (
                                <SectionBlock
                                    key={section.id}
                                    section={section}
                                    units={units}
                                    isFirst={idx === 0}
                                    isLast={idx === sections.length - 1}
                                    onNameChange={(name) =>
                                        handleSectionNameChange(section.id, name)
                                    }
                                    onLineChange={(lineId, updated) =>
                                        handleLineChange(section.id, lineId, updated)
                                    }
                                    onAddIngredient={(result) =>
                                        handleAddIngredient(section.id, result)
                                    }
                                    onAddSubRecipe={(result) =>
                                        handleAddSubRecipe(section.id, result)
                                    }
                                    onQuickCreate={(query) =>
                                        handleQuickCreate(section.id, query)
                                    }
                                    onDeleteLine={(lineId) =>
                                        handleDeleteLine(section.id, lineId)
                                    }
                                    onStepChange={(stepId, updated) =>
                                        handleStepChange(section.id, stepId, updated)
                                    }
                                    onAddStep={() => handleAddStep(section.id)}
                                    onMoveStep={(stepId, dir) =>
                                        handleMoveStep(section.id, stepId, dir)
                                    }
                                    onDeleteStep={(stepId) =>
                                        handleDeleteStep(section.id, stepId)
                                    }
                                    onMoveUp={() => handleMoveSection(section.id, 'up')}
                                    onMoveDown={() => handleMoveSection(section.id, 'down')}
                                    onDelete={() => handleDeleteSection(section.id)}
                                />
                            ))}
                        </div>

                        {/* Add section button */}
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full gap-2 border border-dashed border-border text-muted-foreground hover:text-foreground"
                            onClick={handleAddSection}
                        >
                            <PlusIcon className="size-4" />
                            {t('app.recipes.builder_add_section')}
                        </Button>
                    </div>

                    {/* RIGHT: metrics panel */}
                    <div className="hidden lg:block lg:overflow-y-auto lg:border-l lg:border-border">
                        <MetricsPanel
                            metrics={metrics}
                            draftSellingPrice={draft.selling_price ?? null}
                            draftPortions={draft.portions ?? null}
                            onSellingPriceChange={(value) =>
                                updateDraft('update_selling_price', (prev) => ({
                                    ...prev,
                                    selling_price: value,
                                }))
                            }
                            onApplyScale={({ scale_numerator, scale_denominator, portions }) =>
                                updateDraft('apply_scale', (prev) => ({
                                    ...prev,
                                    scale_numerator,
                                    scale_denominator,
                                    portions,
                                }))
                            }
                        />
                    </div>
                </div>
            </div>

            {/* Delete recipe dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('app.recipes.delete_menu')}</DialogTitle>
                        <DialogDescription>
                            {t('app.recipes.delete_body', { name: draft.name })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setShowDeleteDialog(false)}
                        >
                            {t('app.recipes.delete_cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeleteRecipe}
                        >
                            {t('app.recipes.delete_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Save Version dialog */}
            <SaveVersionDialog
                recipeId={recipe.id}
                open={saveVersionOpen}
                onOpenChange={setSaveVersionOpen}
            />

            {/* Quick-create ingredient modal */}
            <QuickCreateIngredientModal
                open={quickCreateOpen}
                onOpenChange={setQuickCreateOpen}
                initialName={quickCreateQuery}
                categories={categories ?? []}
                onSuccess={handleQuickCreateSuccess}
            />
        </>
    );
}
