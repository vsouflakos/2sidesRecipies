import { CheckCircle2Icon } from 'lucide-react';
import { motion } from 'motion/react';
import { useTranslations } from '@/hooks/use-translations';
import type { ConversationMessage } from '@/types/ai-chat';
import { AiAvatar } from './ai-avatar';

interface MessageBubbleProps {
    message: ConversationMessage;
    /** Whether to show the role label above the bubble (false for consecutive same-role messages). */
    showRoleLabel?: boolean;
}

/** Spring entrance shared by both bubble variants. */
const ENTRANCE = {
    initial: { opacity: 0, y: 12, scale: 0.96 },
    animate: { opacity: 1, y: 0, scale: 1 },
    transition: { type: 'spring' as const, stiffness: 420, damping: 32 },
};

/**
 * Renders a single chat message bubble.
 * User messages align right in a solid bubble; agent messages align left
 * beside the AI avatar. Does NOT render proposal cards — the parent handles those.
 */
export function MessageBubble({
    message,
    showRoleLabel = true,
}: MessageBubbleProps) {
    const { t } = useTranslations();

    const isUser = message.role === 'user';
    const isApplied = message.proposal_state?.status === 'applied';

    if (isUser) {
        return (
            <motion.div {...ENTRANCE} className="flex flex-col items-end gap-1">
                <div className="max-w-[82%] rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-[15px] leading-[1.55] text-primary-foreground shadow-sm">
                    {message.content}
                </div>
            </motion.div>
        );
    }

    return (
        <motion.div {...ENTRANCE} className="flex items-start gap-2.5">
            <AiAvatar size="sm" className="mt-4" />
            <div className="flex min-w-0 flex-col items-start gap-1">
                {showRoleLabel && (
                    <span className="px-1 text-[12px] font-medium text-muted-foreground">
                        {t('app.ai.role_agent')}
                    </span>
                )}
                <div className="max-w-full rounded-2xl rounded-tl-md border border-border bg-card px-4 py-2.5 text-[15px] leading-[1.55] whitespace-pre-wrap shadow-sm">
                    {message.content}
                </div>
                {isApplied && (
                    <span className="inline-flex items-center gap-1 px-1 text-[12px] font-medium text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2Icon className="size-3.5" />
                        {t('app.ai.applied_marker')}
                    </span>
                )}
            </div>
        </motion.div>
    );
}
