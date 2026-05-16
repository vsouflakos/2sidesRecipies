import { useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { toast } from 'sonner';
import { store as storePrices } from '@/actions/App/Http/Controllers/Ingredients/IngredientPriceController';
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
import type { PriceFormData, UnitOption } from '@/types/ingredient';

interface PriceFormProps {
    ingredientId: number;
    units: UnitOption[];
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function UnitCombobox({
    units,
    value,
    onSelect,
    error,
}: {
    units: UnitOption[];
    value: number | '';
    onSelect: (id: number) => void;
    error?: string;
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
        <div className="space-y-1">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className="w-full justify-start font-normal"
                        type="button"
                    >
                        {selected ? `${selected.name} (${selected.symbol})` : 'Select unit…'}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-64 p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Search units…" />
                        <CommandList>
                            <CommandEmpty>No units found.</CommandEmpty>
                            {Object.entries(groupedUnits).map(([type, groupUnits]) => (
                                <CommandGroup
                                    key={type}
                                    heading={type.charAt(0).toUpperCase() + type.slice(1)}
                                >
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
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export function PriceForm({ ingredientId, units }: PriceFormProps) {
    const { t } = useLaravelReactI18n();

    const { data, setData, post, processing, errors, reset } = useForm<PriceFormData>({
        amount: '',
        quantity: '',
        unit_id: '',
        currency: 'EUR',
        recorded_at: today(),
        notes: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        post(storePrices(ingredientId).url, {
            preserveScroll: true,
            only: ['ingredient'],
            onSuccess: () => {
                reset();
                setData('recorded_at', today());
                setData('currency', 'EUR');
                toast.success(t('app.ingredients.price_toast'));
            },
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {/* Amount */}
                <div className="space-y-1">
                    <Label htmlFor="price-amount">
                        {t('app.ingredients.price_amount_label')}
                    </Label>
                    <Input
                        id="price-amount"
                        type="number"
                        min="0"
                        step="any"
                        placeholder="4.50"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                    />
                    {errors.amount && (
                        <p className="text-xs text-destructive">{errors.amount}</p>
                    )}
                </div>

                {/* Quantity */}
                <div className="space-y-1">
                    <Label htmlFor="price-quantity">
                        {t('app.ingredients.price_quantity_label')}
                    </Label>
                    <Input
                        id="price-quantity"
                        type="number"
                        min="0"
                        step="any"
                        placeholder="500"
                        value={data.quantity}
                        onChange={(e) => setData('quantity', e.target.value)}
                    />
                    {errors.quantity && (
                        <p className="text-xs text-destructive">{errors.quantity}</p>
                    )}
                </div>

                {/* Unit */}
                <div className="space-y-1">
                    <Label>{t('app.ingredients.price_unit_label')}</Label>
                    <UnitCombobox
                        units={units}
                        value={data.unit_id}
                        onSelect={(id) => setData('unit_id', id)}
                        error={errors.unit_id}
                    />
                </div>

                {/* Currency */}
                <div className="space-y-1">
                    <Label htmlFor="price-currency">
                        {t('app.ingredients.price_currency_label')}
                    </Label>
                    <Input
                        id="price-currency"
                        type="text"
                        maxLength={3}
                        placeholder="EUR"
                        value={data.currency}
                        onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                    />
                    {errors.currency && (
                        <p className="text-xs text-destructive">{errors.currency}</p>
                    )}
                </div>

                {/* Date */}
                <div className="space-y-1">
                    <Label htmlFor="price-date">
                        {t('app.ingredients.price_date_label')}
                    </Label>
                    <Input
                        id="price-date"
                        type="date"
                        value={data.recorded_at}
                        onChange={(e) => setData('recorded_at', e.target.value)}
                    />
                    {errors.recorded_at && (
                        <p className="text-xs text-destructive">{errors.recorded_at}</p>
                    )}
                </div>

                {/* Notes */}
                <div className="space-y-1">
                    <Label htmlFor="price-notes">
                        {t('app.ingredients.price_notes_label')}
                    </Label>
                    <Input
                        id="price-notes"
                        type="text"
                        placeholder=""
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    {errors.notes && (
                        <p className="text-xs text-destructive">{errors.notes}</p>
                    )}
                </div>
            </div>

            <Button type="submit" variant="default" disabled={processing}>
                {t('app.ingredients.price_cta')}
            </Button>
        </form>
    );
}
