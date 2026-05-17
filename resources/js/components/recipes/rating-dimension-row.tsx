import { XIcon } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface RatingDimensionRowProps {
    dimension: string;
    score: number | null;
    isCustom: boolean;
    onScoreChange: (n: number | null) => void;
    onDimensionChange?: (s: string) => void;
    onRemove?: () => void;
}

/**
 * A single rating dimension row — 44px tall, accessible.
 * Custom dimensions have an editable name input and a remove button.
 * Default dimensions show the dimension name as a label-styled span.
 */
export function RatingDimensionRow({
    dimension,
    score,
    isCustom,
    onScoreChange,
    onDimensionChange,
    onRemove,
}: RatingDimensionRowProps) {
    const { t } = useTranslations();

    return (
        <div className={cn('flex min-h-[44px] items-center gap-2')}>
            {/* Dimension name — editable for custom, label for default */}
            <div className="flex-1">
                {isCustom ? (
                    <Input
                        type="text"
                        value={dimension}
                        onChange={(e) => onDimensionChange?.(e.target.value)}
                        placeholder={t('app.tests.add_dimension')}
                        className="h-9 text-sm"
                    />
                ) : (
                    <span className="text-[14px] leading-[1.4] text-foreground">{dimension}</span>
                )}
            </div>

            {/* Score input */}
            <Input
                type="number"
                min={1}
                max={10}
                value={score ?? ''}
                onChange={(e) => {
                    const val = e.target.value;
                    if (val === '') {
                        onScoreChange(null);
                    } else {
                        const num = Number(val);
                        if (!isNaN(num) && num >= 1 && num <= 10) {
                            onScoreChange(num);
                        }
                    }
                }}
                aria-label={`${dimension} rating`}
                placeholder="1–10"
                className="h-9 w-20 text-sm"
            />

            {/* Remove button for custom dimensions */}
            {isCustom && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onRemove}
                    aria-label={`Remove ${dimension} dimension`}
                    className="size-9 shrink-0"
                >
                    <XIcon className="size-4" />
                </Button>
            )}
        </div>
    );
}
