import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import type { ConversationMessage } from '@/types/ai-chat';

interface MessageBubbleProps {
    message: ConversationMessage;
    /** Whether to show the role label above the bubble (false for consecutive same-role messages). */
    showRoleLabel?: boolean;
}

/**
 * Renders a single chat message bubble.
 * User messages align right; agent messages align left.
 * Does NOT render proposal cards — those are handled by the parent.
 */
export function MessageBubble({ message, showRoleLabel = true }: MessageBubbleProps) {
    const { t } = useTranslations();

    const isUser = message.role === 'user';
    const isApplied = message.proposal_state?.status === 'applied';

    if (isUser) {
        return (
            <div className="flex flex-col items-end gap-1">
                {showRoleLabel && (
                    <span className="text-[14px] text-muted-foreground">
                        {t('app.ai.role_user')}
                    </span>
                )}
                <div className="max-w-[80%] rounded-lg bg-secondary px-4 py-2 text-[16px] leading-[1.5]">
                    {message.content}
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-start gap-1">
            {showRoleLabel && (
                <span className="text-[14px] text-muted-foreground">
                    {t('app.ai.role_agent')}
                </span>
            )}
            <div className="max-w-[90%] rounded-lg border border-border bg-card px-4 py-2 text-[16px] leading-[1.5]">
                {message.content}
            </div>
            {isApplied && (
                <Badge
                    variant="outline"
                    className="text-accent-foreground border-accent/30 text-xs"
                >
                    {t('app.ai.applied_marker')}
                </Badge>
            )}
        </div>
    );
}
