import { AlertTriangleIcon } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/hooks/use-translations';
import type { ChatStatus, ConversationMessage } from '@/types/ai-chat';
import { MessageBubble } from './message-bubble';
import { ProposalCard } from './proposal-card';
import { StarterPrompts } from './starter-prompts';
import { StreamingIndicator } from './streaming-indicator';

interface MessageListProps {
    messages: ConversationMessage[];
    status: ChatStatus;
    /** Failure reason from the agent turn, shown in the error bubble. */
    error: string | null;
    onSelectPrompt: (text: string) => void;
    onApply: (messageId: number) => Promise<boolean>;
    onDismiss: (messageId: number) => void;
    onApplyVariant: (messageId: number) => Promise<boolean>;
    onRetry: () => void;
}

/**
 * Scrollable message list that renders the full conversation thread.
 * Shows StarterPrompts when empty, an animated StreamingIndicator while the
 * agent turn is in progress, and an error bubble on failure.
 */
export function MessageList({
    messages,
    status,
    error,
    onSelectPrompt,
    onApply,
    onDismiss,
    onApplyVariant,
    onRetry,
}: MessageListProps) {
    const { t } = useTranslations();
    const bottomRef = useRef<HTMLDivElement>(null);

    /** Auto-scroll to bottom whenever messages or status changes. */
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, status]);

    if (status === 'loading-history') {
        return (
            <div
                className="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-5"
                aria-label={t('app.ai.loading_history')}
            >
                <Skeleton className="h-14 w-4/5 rounded-2xl" />
                <Skeleton className="ml-auto h-11 w-3/5 rounded-2xl" />
                <Skeleton className="h-16 w-4/5 rounded-2xl" />
            </div>
        );
    }

    /**
     * Show the typing indicator for the whole turn. There is no token-by-token
     * streaming anymore — proposal cards and the final reply pop in as polling
     * picks them up, and the indicator sits below them until the turn ends.
     */
    const showTyping = status === 'generating';

    const isEmpty = messages.length === 0 && status !== 'generating';

    return (
        <div
            className="flex flex-1 flex-col overflow-y-auto px-4 py-5"
            role="log"
            aria-live="polite"
        >
            {isEmpty ? (
                <StarterPrompts onSelectPrompt={onSelectPrompt} />
            ) : (
                /*
                 * Message stack: an auto-height, non-shrinking wrapper. Keeping
                 * `shrink-0` here stops flexbox from squishing the rows to fit
                 * the bounded scroll container — the container scrolls instead.
                 */
                <div className="flex shrink-0 flex-col gap-4">
                    {messages.map((message, index) => {
                        /** Skip an empty assistant message (agent finished with only tool calls). */
                        if (
                            message.role === 'assistant' &&
                            message.content.length === 0 &&
                            !message.proposal_state
                        ) {
                            return null;
                        }

                        const previousMessage =
                            index > 0 ? messages[index - 1] : null;
                        const showRoleLabel =
                            !previousMessage ||
                            previousMessage.role !== message.role;

                        if (message.role === 'tool_proposal') {
                            return (
                                <ProposalCard
                                    key={message.id}
                                    message={message}
                                    onApply={onApply}
                                    onDismiss={onDismiss}
                                    onApplyVariant={onApplyVariant}
                                />
                            );
                        }

                        return (
                            <div key={message.id}>
                                <MessageBubble
                                    message={message}
                                    showRoleLabel={showRoleLabel}
                                />
                                {message.role === 'assistant' &&
                                    message.proposal_state && (
                                        <ProposalCard
                                            message={message}
                                            onApply={onApply}
                                            onDismiss={onDismiss}
                                            onApplyVariant={onApplyVariant}
                                        />
                                    )}
                            </div>
                        );
                    })}

                    <AnimatePresence>
                        {showTyping && <StreamingIndicator key="typing" />}
                    </AnimatePresence>

                    <AnimatePresence>
                        {status === 'error' && (
                            <motion.div
                                key="error"
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0 }}
                                className="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-[14px] text-destructive"
                                role="alert"
                            >
                                <AlertTriangleIcon className="mt-0.5 size-4 shrink-0" />
                                <div>
                                    {error && error.trim()
                                        ? error
                                        : t('app.ai.stream_error')}{' '}
                                    <Button
                                        type="button"
                                        variant="link"
                                        className="h-auto p-0 text-[14px] text-destructive underline"
                                        onClick={onRetry}
                                    >
                                        {t('app.ai.retry')}
                                    </Button>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    <div ref={bottomRef} />
                </div>
            )}
        </div>
    );
}
