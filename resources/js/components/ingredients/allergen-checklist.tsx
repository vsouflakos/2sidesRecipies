import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AllergenFormEntry, AllergenOption } from '@/types/ingredient';

interface AllergenChecklistProps {
    allergens: AllergenOption[];
    value: AllergenFormEntry[];
    onChange: (value: AllergenFormEntry[]) => void;
}

type AllergenState = 'none' | 'contains' | 'may_contain';

export function AllergenChecklist({ allergens, value, onChange }: AllergenChecklistProps) {
    const { t } = useLaravelReactI18n();

    function getState(allergenId: number): AllergenState {
        const entry = value.find((e) => e.allergen_id === allergenId);
        return entry ? entry.state : 'none';
    }

    function handleStateChange(allergenId: number, state: AllergenState) {
        if (state === 'none') {
            onChange(value.filter((e) => e.allergen_id !== allergenId));
        } else {
            const existing = value.find((e) => e.allergen_id === allergenId);
            if (existing) {
                onChange(value.map((e) => (e.allergen_id === allergenId ? { ...e, state } : e)));
            } else {
                onChange([...value, { allergen_id: allergenId, state }]);
            }
        }
    }

    return (
        <div className="space-y-3">
            {allergens.map((allergen) => {
                const state = getState(allergen.id);
                return (
                    <div key={allergen.id} className="flex items-center justify-between gap-4">
                        <span className="text-sm">{allergen.name}</span>
                        <Select
                            value={state}
                            onValueChange={(v) => handleStateChange(allergen.id, v as AllergenState)}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {t('app.ingredients.allergen_none')}
                                </SelectItem>
                                <SelectItem value="contains">
                                    {t('app.ingredients.allergen_contains')}
                                </SelectItem>
                                <SelectItem value="may_contain">
                                    {t('app.ingredients.allergen_may_contain')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                );
            })}
        </div>
    );
}
