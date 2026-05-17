import { useState } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { Trash2Icon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
import type { RecipeIngredientLine } from '@/types/recipe';

interface SubRecipeLineRowProps {
    line: RecipeIngredientLine;
    /** Called when gram quantity changes — triggers auto-save. */
    onChange: (updated: Partial<RecipeIngredientLine>) => void;
    /** Called when the delete button is pressed. */
    onDelete: () => void;
    /**
     * Called when the chef confirms updating the sub-recipe pin to the latest version.
     * The builder wires this to a draft applyEdit — one logical, Recall-able edit.
     */
    onUpdatePin: (line: RecipeIngredientLine) => void;
    /** Inline circular-reference error for this specific row. */
    circularError?: string | null;
    className?: string;
}

/**
 * Sub-recipe line variant: name + pinned version badge + gram qty input +
 * "Update available" accent badge (when newer version exists) + delete.
 *
 * The pin is NEVER updated automatically — updating is always an explicit
 * chef action confirmed via the update dialog.
 */
export function SubRecipeLineRow({
    line,
    onChange,
    onDelete,
    onUpdatePin,
    circularError,
    className,
}: SubRecipeLineRowProps) {
    const { t } = useTranslations();
    const [updateDialogOpen, setUpdateDialogOpen] = useState(false);

    const subRecipe = line.sub_recipe;
    const pinnedVersion = subRecipe?.version_number ?? 0;
    const latestVersion = subRecipe?.latest_version_number ?? 0;
    const hasUpdateAvailable = latestVersion > pinnedVersion;
    const subRecipeName = subRecipe?.name ?? line.name ?? '';

    function handleConfirmUpdate() {
        onUpdatePin(line);
        setUpdateDialogOpen(false);
    }

    return (
        <>
            <div
                className={cn(
                    'flex min-h-[48px] flex-col border-b border-border',
                    className,
                )}
            >
                <div className="flex items-center gap-2 px-2 py-1">
                    {/* Sub-recipe name */}
                    <span className="flex-1 text-sm">{subRecipeName}</span>

                    {/* Pinned version badge */}
                    <Badge variant="secondary" className="shrink-0 text-xs">
                        v{pinnedVersion}
                    </Badge>

                    {/* Update available accent badge — clicking opens confirm dialog */}
                    {hasUpdateAvailable && (
                        <button
                            type="button"
                            onClick={() => setUpdateDialogOpen(true)}
                            className="shrink-0 cursor-pointer"
                            aria-label={t('app.recipes.sub_recipe_update_badge')}
                        >
                            <Badge className="bg-accent text-accent-foreground text-xs hover:opacity-80">
                                {t('app.recipes.sub_recipe_update_badge')}
                            </Badge>
                        </button>
                    )}

                    {/* Gram qty input */}
                    <Input
                        type="number"
                        value={line.quantity}
                        onChange={(e) => onChange({ quantity: e.target.value })}
                        className="w-[100px] shrink-0"
                        placeholder="g"
                        min="0"
                    />

                    {/* Delete button */}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="shrink-0 min-h-[44px] text-muted-foreground hover:text-destructive"
                        onClick={onDelete}
                        aria-label="Remove sub-recipe line"
                    >
                        <Trash2Icon className="size-4" />
                    </Button>
                </div>

                {/* Inline circular reference error — e.g. "Cannot add '{name}' — it would create a circular reference." */}
                {circularError && (
                    <p className="px-2 pb-1 text-xs text-destructive">
                        {circularError}
                    </p>
                )}
            </div>

            {/* Update sub-recipe pin confirmation dialog */}
            <Dialog open={updateDialogOpen} onOpenChange={setUpdateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('app.recipes.sub_recipe_update_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('app.recipes.sub_recipe_update_body', {
                                name: subRecipeName,
                                vOld: `v${pinnedVersion}`,
                                vNew: `v${latestVersion}`,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setUpdateDialogOpen(false)}
                        >
                            {t('app.recipes.sub_recipe_update_cancel', {
                                vOld: `v${pinnedVersion}`,
                            })}
                        </Button>
                        <Button
                            type="button"
                            variant="default"
                            onClick={handleConfirmUpdate}
                        >
                            {t('app.recipes.sub_recipe_update_confirm', {
                                vNew: `v${latestVersion}`,
                            })}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
