import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TriangleAlertIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface DataGapBannerProps {
    /** Names of ingredient lines that are missing price or nutrition data. */
    missingData: string[];
}

export function DataGapBanner({ missingData }: DataGapBannerProps) {
    const { t } = useLaravelReactI18n();

    if (missingData.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" className="border-destructive/50">
            <TriangleAlertIcon />
            <AlertTitle>{t('app.recipes.metrics_data_gap')}</AlertTitle>
            <AlertDescription>
                <p className="font-medium">{t('app.recipes.metrics_gap_items')}</p>
                <ul className="mt-1 list-inside list-disc space-y-0.5">
                    {missingData.map((name) => (
                        <li key={name}>{name}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
