import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDownIcon, ChevronUpIcon, ImagePlusIcon, Trash2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { RecipeStep } from '@/types/recipe';

interface StepRowProps {
    step: RecipeStep;
    isFirst: boolean;
    isLast: boolean;
    /** Called when the instruction text changes. */
    onChange: (updated: Partial<RecipeStep>) => void;
    /** Called to move step up. */
    onMoveUp: () => void;
    /** Called to move step down. */
    onMoveDown: () => void;
    /** Called to delete this step. */
    onDelete: () => void;
    className?: string;
}

/**
 * Single editable step row with a Textarea for instruction, optional image upload,
 * reorder buttons, and delete.
 */
export function StepRow({
    step,
    isFirst,
    isLast,
    onChange,
    onMoveUp,
    onMoveDown,
    onDelete,
    className,
}: StepRowProps) {
    const { t } = useLaravelReactI18n();

    return (
        <div
            className={cn(
                'flex min-h-[44px] items-start gap-2 border-b border-border px-2 py-2',
                className,
            )}
        >
            {/* Step number indicator */}
            <span className="mt-2 shrink-0 text-xs text-muted-foreground tabular-nums select-none">
                {step.order}.
            </span>

            {/* Instruction textarea */}
            <Textarea
                value={step.instruction}
                onChange={(e) => onChange({ instruction: e.target.value })}
                placeholder={t('app.recipes.builder_step_placeholder')}
                className="min-h-[44px] flex-1 resize-none border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
                rows={2}
            />

            {/* Actions */}
            <div className="flex shrink-0 flex-col items-center gap-1">
                {/* Step image upload tooltip */}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="min-h-[44px] text-muted-foreground"
                            aria-label={t('app.recipes.builder_step_image_tooltip')}
                        >
                            <ImagePlusIcon className="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {t('app.recipes.builder_step_image_tooltip')}
                    </TooltipContent>
                </Tooltip>

                {/* Move up */}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="min-h-[44px] text-muted-foreground"
                    onClick={onMoveUp}
                    disabled={isFirst}
                    aria-label="Move step up"
                >
                    <ChevronUpIcon className="size-4" />
                </Button>

                {/* Move down */}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="min-h-[44px] text-muted-foreground"
                    onClick={onMoveDown}
                    disabled={isLast}
                    aria-label="Move step down"
                >
                    <ChevronDownIcon className="size-4" />
                </Button>

                {/* Delete step */}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="min-h-[44px] text-muted-foreground hover:text-destructive"
                    onClick={onDelete}
                    aria-label="Delete step"
                >
                    <Trash2Icon className="size-4" />
                </Button>
            </div>
        </div>
    );
}
