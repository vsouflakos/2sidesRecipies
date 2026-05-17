import { useTranslations } from '@/hooks/use-translations';

/**
 * Animated three-dot typing indicator shown while the AI agent is streaming a response.
 * Rendered inside an agent-styled bubble shell.
 */
export function StreamingIndicator() {
    const { t } = useTranslations();

    return (
        <div className="flex flex-col gap-1">
            <span className="text-[14px] text-muted-foreground">{t('app.ai.role_agent')}</span>
            <div
                className="inline-flex w-fit items-center gap-1 rounded-lg border border-border bg-card px-4 py-2"
                aria-label={t('app.ai.typing')}
            >
                <span
                    className="size-1.5 rounded-full bg-muted-foreground animate-pulse"
                    style={{ animationDelay: '0ms' }}
                />
                <span
                    className="size-1.5 rounded-full bg-muted-foreground animate-pulse"
                    style={{ animationDelay: '300ms' }}
                />
                <span
                    className="size-1.5 rounded-full bg-muted-foreground animate-pulse"
                    style={{ animationDelay: '600ms' }}
                />
            </div>
        </div>
    );
}
