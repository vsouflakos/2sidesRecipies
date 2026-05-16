import { useLaravelReactI18n } from 'laravel-react-i18n';
import { cn } from '@/lib/utils';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { IngredientPrice } from '@/types/ingredient';

interface PriceHistoryProps {
    prices: IngredientPrice[];
}

export function PriceHistory({ prices }: PriceHistoryProps) {
    const { t } = useLaravelReactI18n();

    if (prices.length === 0) {
        return (
            <p className="text-[14px] text-muted-foreground">
                {t('app.ingredients.prices_empty')}
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="text-[14px]">
                        {t('app.ingredients.price_date_label')}
                    </TableHead>
                    <TableHead className="text-[14px]">
                        {t('app.ingredients.price_amount_label')}
                    </TableHead>
                    <TableHead className="text-[14px]">
                        {t('app.ingredients.price_unit_label')}
                    </TableHead>
                    <TableHead className="text-[14px]">
                        {t('app.ingredients.price_currency_label')}
                    </TableHead>
                    <TableHead className="text-[14px]">Per-gram cost</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {prices.map((price, index) => (
                    <TableRow
                        key={price.id}
                        className={cn(index === 0 && 'font-medium')}
                    >
                        <TableCell className="text-[14px]">{price.recorded_at}</TableCell>
                        <TableCell className="text-[14px]">
                            {price.amount} {price.currency}
                        </TableCell>
                        <TableCell className="text-[14px]">
                            {price.unit
                                ? `${price.unit.name} (${price.unit.symbol})`
                                : price.quantity
                                  ? price.quantity
                                  : '—'}
                        </TableCell>
                        <TableCell className="text-[14px]">{price.currency}</TableCell>
                        <TableCell className="text-[14px]">
                            {price.per_gram_cost !== null
                                ? `${price.per_gram_cost} ${price.currency}/g`
                                : '—'}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
