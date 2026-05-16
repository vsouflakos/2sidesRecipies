import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Trash2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SubRecipeLineRow } from '@/components/recipes/recipe-builder/sub-recipe-line-row';
import { cn } from '@/lib/utils';
import type { RecipeIngredientLine, UnitOption } from '@/types/recipe';

interface IngredientLineRowProps {
    line: RecipeIngredientLine;
    units: UnitOption[];
    /** Called when any field changes — triggers auto-save. */
    onChange: (updated: Partial<RecipeIngredientLine>) => void;
    /** Called when the delete button is pressed. */
    onDelete: () => void;
    /**
     * Called when the chef confirms updating a sub-recipe pin.
     * Only relevant when line.sub_recipe_version_id is set.
     */
    onUpdatePin?: (line: RecipeIngredientLine) => void;
    /** Inline circular-reference error for a sub-recipe row. */
    circularError?: string | null;
    className?: string;
}

/**
 * Collapsed ingredient line row with qty, unit, name, prep note, yield%, flour-base toggle, and delete.
 * Sub-recipe lines delegate to SubRecipeLineRow for the full version-pin + update-cue behavior.
 */
export function IngredientLineRow({
    line,
    units,
    onChange,
    onDelete,
    onUpdatePin,
    circularError,
    className,
}: IngredientLineRowProps) {
    const { t } = useLaravelReactI18n();
    const [hovered, setHovered] = useState(false);

    const isSubRecipe = line.sub_recipe_version_id !== null && line.sub_recipe !== undefined;

    if (isSubRecipe) {
        /** Delegate to the dedicated sub-recipe line component. */
        return (
            <SubRecipeLineRow
                line={line}
                onChange={onChange}
                onDelete={onDelete}
                onUpdatePin={onUpdatePin ?? (() => {})}
                circularError={circularError}
                className={className}
            />
        );
    }

    /** Standard ingredient line */
    return (
        <div
            className={cn(
                'flex min-h-[48px] flex-wrap items-center gap-2 border-b border-border px-2 py-1',
                className,
            )}
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
        >
            {/* Quantity */}
            <Input
                type="number"
                value={line.quantity}
                onChange={(e) => onChange({ quantity: e.target.value })}
                className="w-[60px] shrink-0"
                placeholder={t('app.recipes.builder_qty_placeholder')}
                min="0"
            />

            {/* Unit */}
            <Select
                value={line.unit_id !== null ? String(line.unit_id) : ''}
                onValueChange={(val) => onChange({ unit_id: val ? Number(val) : null })}
            >
                <SelectTrigger className="w-[100px] shrink-0">
                    <SelectValue placeholder="Unit" />
                </SelectTrigger>
                <SelectContent>
                    {units.map((unit) => (
                        <SelectItem key={unit.id} value={String(unit.id)}>
                            {unit.symbol}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Ingredient name (fills) */}
            <span className="flex-1 text-sm">{line.name}</span>

            {/* Prep note */}
            <Input
                type="text"
                value={line.prep_note ?? ''}
                onChange={(e) => onChange({ prep_note: e.target.value })}
                className="w-[120px] shrink-0"
                placeholder={t('app.recipes.builder_prep_note_placeholder')}
            />

            {/* Yield % */}
            <Input
                type="number"
                value={line.yield_pct}
                onChange={(e) => onChange({ yield_pct: e.target.value })}
                className="w-[60px] shrink-0"
                placeholder={t('app.recipes.builder_yield_pct_placeholder')}
                min="0"
                max="100"
            />

            {/* Delete button */}
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="shrink-0 min-h-[44px] text-muted-foreground hover:text-destructive"
                onClick={onDelete}
                aria-label="Remove ingredient line"
            >
                <Trash2Icon className="size-4" />
            </Button>

            {/* Flour base checkbox — visible on hover */}
            {hovered && (
                <div className="flex w-full items-center gap-2 pb-1 pl-0">
                    <Checkbox
                        id={`flour-base-${line.id}`}
                        checked={line.is_flour_base}
                        onCheckedChange={(checked) =>
                            onChange({ is_flour_base: checked === true })
                        }
                        className="size-3"
                    />
                    <Label
                        htmlFor={`flour-base-${line.id}`}
                        className="cursor-pointer text-xs text-muted-foreground"
                    >
                        {t('app.recipes.builder_flour_base')}
                    </Label>
                </div>
            )}
        </div>
    );
}
