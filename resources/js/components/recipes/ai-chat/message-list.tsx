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
    onSelectPrompt: (text: string) => void;
    onApply: (messageId: number) => Promise<boolean>;
    onDismiss: (messageId: number) => void;
    onApplyVariant: (messageId: number) => Promise<boolean>;
    onRetry: () => void;
}

/**
 * Scrollable message list that renders the full conversation thread.
 * Shows StarterPrompts when empty, StreamingIndicator while streaming,
 * and an error bubble on failure.
 */
export function MessageList({
    messages,
    status,
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
                className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
                aria-label={t('app.ai.loading_history')}
            >
                <Skeleton className="h-12 w-4/5 rounded-lg" />
                <Skeleton className="ml-auto h-10 w-3/5 rounded-lg" />
                <Skeleton className="h-14 w-4/5 rounded-lg" />
            </div>
        );
    }

    return (
        <div
            className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
            role="log"
            aria-live="polite"
        >
            {messages.length === 0 && status !== 'streaming' ? (
                <StarterPrompts onSelectPrompt={onSelectPrompt} />
            ) : (
                messages.map((message, index) => {
                    const previousMessage = index > 0 ? messages[index - 1] : null;
                    const showRoleLabel = !previousMessage || previousMessage.role !== message.role;

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
                            {message.role === 'assistant' && message.proposal_state && (
                                <ProposalCard
                                    message={message}
                                    onApply={onApply}
                                    onDismiss={onDismiss}
                                    onApplyVariant={onApplyVariant}
                                />
                            )}
                        </div>
                    );
                })
            )}

            {status === 'streaming' && <StreamingIndicator />}

            {status === 'error' && (
                <div
                    className="rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-2 text-[16px] text-destructive-foreground"
                    role="alert"
                >
                    {t('app.ai.stream_error')}{' '}
                    <Button
                        type="button"
                        variant="link"
                        className="h-auto p-0 text-[16px] text-destructive-foreground underline"
                        onClick={onRetry}
                    >
                        {t('app.ai.retry')}
                    </Button>
                </div>
            )}

            <div ref={bottomRef} />
        </div>
    );
}
