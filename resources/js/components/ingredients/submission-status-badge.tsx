import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';

type SubmissionStatus = 'private' | 'submitted' | 'approved' | 'rejected';

interface SubmissionStatusBadgeProps {
    status: SubmissionStatus;
}

export function SubmissionStatusBadge({ status }: SubmissionStatusBadgeProps) {
    const { t } = useLaravelReactI18n();

    if (status === 'private') {
        return null;
    }

    if (status === 'submitted') {
        return (
            <Badge variant="secondary">
                {t('app.ingredients.status_submitted')}
            </Badge>
        );
    }

    if (status === 'rejected') {
        return (
            <Badge variant="destructive">
                {t('app.ingredients.status_rejected')}
            </Badge>
        );
    }

    // approved
    return (
        <Badge variant="default">
            {t('app.ingredients.status_approved')}
        </Badge>
    );
}
