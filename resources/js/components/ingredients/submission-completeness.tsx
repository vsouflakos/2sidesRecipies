import { useLaravelReactI18n } from 'laravel-react-i18n';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

interface CompletenessData {
    nutrition_filled: boolean;
    allergens_set: boolean;
    conversions_added: boolean;
}

interface SubmissionCompletenessProps {
    completeness: CompletenessData;
}

export function SubmissionCompleteness({ completeness }: SubmissionCompletenessProps) {
    const { t } = useLaravelReactI18n();

    const dots = [
        {
            filled: completeness.nutrition_filled,
            label: t('app.ingredients.completeness_nutrition'),
        },
        {
            filled: completeness.allergens_set,
            label: t('app.ingredients.completeness_allergens'),
        },
        {
            filled: completeness.conversions_added,
            label: t('app.ingredients.completeness_conversions'),
        },
    ];

    return (
        <div className="flex items-center gap-1">
            {dots.map((dot, index) => (
                <Tooltip key={index}>
                    <TooltipTrigger asChild>
                        <span
                            className={cn(
                                'inline-block size-4 rounded-full',
                                dot.filled ? 'bg-green-500' : 'bg-muted',
                            )}
                        />
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{dot.label}</p>
                    </TooltipContent>
                </Tooltip>
            ))}
        </div>
    );
}
