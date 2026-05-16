import { PlusIcon, XIcon } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ConversionFormEntry, UnitOption } from '@/types/ingredient';

interface ConversionRowsProps {
    units: UnitOption[];
    value: ConversionFormEntry[];
    onChange: (value: ConversionFormEntry[]) => void;
    errors?: Record<string, string>;
}

function emptyRow(): ConversionFormEntry {
    return { from_amount: '', from_unit_id: '', gram_weight: '', modifier: '' };
}

function UnitCombobox({
    units,
    value,
    onSelect,
}: {
    units: UnitOption[];
    value: number | '';
    onSelect: (id: number) => void;
}) {
    const [open, setOpen] = useState(false);
    const selected = units.find((u) => u.id === value);

    const groupedUnits = units.reduce<Record<string, UnitOption[]>>((acc, unit) => {
        if (!acc[unit.type]) {
            acc[unit.type] = [];
        }
        acc[unit.type].push(unit);
        return acc;
    }, {});

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" className="w-full justify-start font-normal" type="button">
                    {selected ? `${selected.name} (${selected.symbol})` : 'Select unit…'}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Search units…" />
                    <CommandList>
                        <CommandEmpty>No units found.</CommandEmpty>
                        {Object.entries(groupedUnits).map(([type, groupUnits]) => (
                            <CommandGroup key={type} heading={type.charAt(0).toUpperCase() + type.slice(1)}>
                                {groupUnits.map((unit) => (
                                    <CommandItem
                                        key={unit.id}
                                        value={`${unit.name} ${unit.symbol}`}
                                        onSelect={() => {
                                            onSelect(unit.id);
                                            setOpen(false);
                                        }}
                                    >
                                        {unit.name} ({unit.symbol})
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

export function ConversionRows({ units, value, onChange, errors = {} }: ConversionRowsProps) {
    const { t } = useLaravelReactI18n();

    function addRow() {
        onChange([...value, emptyRow()]);
    }

    function removeRow(index: number) {
        onChange(value.filter((_, i) => i !== index));
    }

    function updateRow(index: number, patch: Partial<ConversionFormEntry>) {
        onChange(value.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    return (
        <div className="space-y-4">
            {value.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('app.ingredients.conversions_empty')}
                </p>
            )}

            {value.map((row, index) => (
                <div key={index} className="grid grid-cols-[1fr_1fr_1fr_1fr_auto] gap-2 items-end">
                    <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Amount</Label>
                        <Input
                            type="number"
                            placeholder="1"
                            min="0"
                            step="any"
                            value={row.from_amount}
                            onChange={(e) => updateRow(index, { from_amount: e.target.value })}
                        />
                        {errors[`conversions.${index}.from_amount`] && (
                            <p className="text-xs text-destructive">
                                {errors[`conversions.${index}.from_amount`]}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Unit</Label>
                        <UnitCombobox
                            units={units}
                            value={row.from_unit_id}
                            onSelect={(id) => updateRow(index, { from_unit_id: id })}
                        />
                        {errors[`conversions.${index}.from_unit_id`] && (
                            <p className="text-xs text-destructive">
                                {errors[`conversions.${index}.from_unit_id`]}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Gram weight</Label>
                        <Input
                            type="number"
                            placeholder="100"
                            min="0"
                            step="any"
                            value={row.gram_weight}
                            onChange={(e) => updateRow(index, { gram_weight: e.target.value })}
                        />
                        {errors[`conversions.${index}.gram_weight`] && (
                            <p className="text-xs text-destructive">
                                {errors[`conversions.${index}.gram_weight`]}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Modifier (optional)</Label>
                        <Input
                            type="text"
                            placeholder="e.g. sliced"
                            value={row.modifier}
                            onChange={(e) => updateRow(index, { modifier: e.target.value })}
                        />
                    </div>

                    <button
                        type="button"
                        aria-label="Remove row"
                        className="mb-0.5 flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:text-destructive"
                        onClick={() => removeRow(index)}
                    >
                        <XIcon className="size-4" />
                    </button>
                </div>
            ))}

            <Button type="button" variant="outline" size="sm" onClick={addRow}>
                <PlusIcon className="mr-2 size-4" />
                Add Row
            </Button>
        </div>
    );
}
