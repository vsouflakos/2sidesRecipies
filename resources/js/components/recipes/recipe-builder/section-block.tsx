import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDownIcon, ChevronUpIcon, PlusIcon, Trash2Icon } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import { IngredientLineRow } from '@/components/recipes/recipe-builder/ingredient-line-row';
import { IngredientSearchCombobox } from '@/components/recipes/recipe-builder/ingredient-search-combobox';
import { StepRow } from '@/components/recipes/recipe-builder/step-row';
import type {
    ComponentSearchResult,
    RecipeIngredientLine,
    RecipeSection,
    RecipeStep,
    UnitOption,
} from '@/types/recipe';

interface SectionBlockProps {
    section: RecipeSection;
    units: UnitOption[];
    isFirst: boolean;
    isLast: boolean;
    /** Called when section name changes. */
    onNameChange: (name: string) => void;
    /** Called when an ingredient line field changes. */
    onLineChange: (lineId: number, updated: Partial<RecipeIngredientLine>) => void;
    /** Called when an ingredient is selected from the combobox. */
    onAddIngredient: (result: ComponentSearchResult) => void;
    /** Called when a sub-recipe is selected from the combobox. */
    onAddSubRecipe: (result: ComponentSearchResult) => void;
    /** Called to open the quick-create modal with the given query. */
    onQuickCreate: (query: string) => void;
    /** Called to delete an ingredient line. */
    onDeleteLine: (lineId: number) => void;
    /** Called when a step instruction changes. */
    onStepChange: (stepId: number, updated: Partial<RecipeStep>) => void;
    /** Called to add a new empty step. */
    onAddStep: () => void;
    /** Called to reorder steps. */
    onMoveStep: (stepId: number, direction: 'up' | 'down') => void;
    /** Called to delete a step. */
    onDeleteStep: (stepId: number) => void;
    /** Called to move this section up. */
    onMoveUp: () => void;
    /** Called to move this section down. */
    onMoveDown: () => void;
    /** Called to delete this section. */
    onDelete: () => void;
    className?: string;
}

/**
 * Named section container grouping ingredient lines and steps.
 * Supports inline section rename, up/down reorder, and delete with confirmation.
 */
export function SectionBlock({
    section,
    units,
    isFirst,
    isLast,
    onNameChange,
    onLineChange,
    onAddIngredient,
    onAddSubRecipe,
    onQuickCreate,
    onDeleteLine,
    onStepChange,
    onAddStep,
    onMoveStep,
    onDeleteStep,
    onMoveUp,
    onMoveDown,
    onDelete,
    className,
}: SectionBlockProps) {
    const { t } = useLaravelReactI18n();
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [headerHovered, setHeaderHovered] = useState(false);

    const steps = section.steps ?? [];
    const lines = section.lines ?? [];
    const hasContent = lines.length > 0 || steps.length > 0;

    function handleDeleteRequest() {
        if (hasContent) {
            setShowDeleteDialog(true);
        } else {
            onDelete();
        }
    }

    function confirmDelete() {
        setShowDeleteDialog(false);
        onDelete();
    }

    return (
        <>
            <div
                className={cn(
                    'rounded-lg border border-border bg-card',
                    className,
                )}
            >
                {/* Section header */}
                <div
                    className="flex items-center gap-2 rounded-t-lg bg-muted/50 px-3 py-2"
                    onMouseEnter={() => setHeaderHovered(true)}
                    onMouseLeave={() => setHeaderHovered(false)}
                >
                    {/* Editable section name */}
                    <Input
                        type="text"
                        value={section.name}
                        onChange={(e) => onNameChange(e.target.value)}
                        placeholder={t('app.recipes.builder_section_name_placeholder')}
                        className="h-auto flex-1 border-0 bg-transparent p-0 text-[20px] font-semibold leading-[1.2] shadow-none focus-visible:ring-0"
                    />

                    {/* Reorder / delete buttons — shown on hover */}
                    {headerHovered && (
                        <div className="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="min-h-[44px] text-muted-foreground"
                                onClick={onMoveUp}
                                disabled={isFirst}
                                aria-label="Move section up"
                            >
                                <ChevronUpIcon className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="min-h-[44px] text-muted-foreground"
                                onClick={onMoveDown}
                                disabled={isLast}
                                aria-label="Move section down"
                            >
                                <ChevronDownIcon className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="min-h-[44px] text-muted-foreground hover:text-destructive"
                                onClick={handleDeleteRequest}
                                aria-label="Delete section"
                            >
                                <Trash2Icon className="size-4" />
                            </Button>
                        </div>
                    )}
                </div>

                {/* Ingredient lines */}
                <div className="px-3">
                    {lines.map((line) => (
                        <IngredientLineRow
                            key={line.id}
                            line={line}
                            units={units}
                            onChange={(updated) => onLineChange(line.id, updated)}
                            onDelete={() => onDeleteLine(line.id)}
                        />
                    ))}

                    {/* Add ingredient via combobox */}
                    <div className="py-1">
                        <IngredientSearchCombobox
                            onSelectIngredient={onAddIngredient}
                            onSelectSubRecipe={onAddSubRecipe}
                            onQuickCreate={onQuickCreate}
                        />
                    </div>
                </div>

                {/* Steps */}
                {steps.length > 0 && (
                    <div className="border-t border-border px-3">
                        {steps.map((step, idx) => (
                            <StepRow
                                key={step.id}
                                step={step}
                                isFirst={idx === 0}
                                isLast={idx === steps.length - 1}
                                onChange={(updated) => onStepChange(step.id, updated)}
                                onMoveUp={() => onMoveStep(step.id, 'up')}
                                onMoveDown={() => onMoveStep(step.id, 'down')}
                                onDelete={() => onDeleteStep(step.id)}
                            />
                        ))}
                    </div>
                )}

                {/* Add step button */}
                <div className="border-t border-border px-3 py-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1 text-muted-foreground hover:text-foreground"
                        onClick={onAddStep}
                    >
                        <PlusIcon className="size-4" />
                        {t('app.recipes.builder_add_step')}
                    </Button>
                </div>
            </div>

            {/* Delete confirmation dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('app.recipes.section_delete_confirm')}</DialogTitle>
                        <DialogDescription>
                            {t('app.recipes.section_delete_body', { name: section.name })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setShowDeleteDialog(false)}
                        >
                            {t('app.recipes.section_delete_cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            {t('app.recipes.section_delete_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
