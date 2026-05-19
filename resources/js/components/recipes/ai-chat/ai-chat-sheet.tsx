import { SendIcon, SparklesIcon } from 'lucide-react';
import { motion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAiChat } from '@/hooks/use-ai-chat';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { AiAvatar } from './ai-avatar';
import { MessageList } from './message-list';

interface AiChatSheetProps {
    recipeId: number;
    /** Resync the recipe builder from the server after a proposal is applied. */
    onDraftRefresh: () => void;
}

/**
 * AI chat slide-over sheet for the recipe builder.
 * Opens on the right (desktop) or full-screen (mobile).
 * Loads conversation history on open, polls a queued agent turn for progress,
 * starter prompts, proposal apply/dismiss, and builder refresh on apply.
 */
export function AiChatSheet({ recipeId, onDraftRefresh }: AiChatSheetProps) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const [inputText, setInputText] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const {
        messages,
        status,
        error,
        sendMessage,
        applyProposal,
        applyVariant,
        dismissProposal,
        retry,
        loadHistory,
    } = useAiChat(recipeId);

    const isGenerating = status === 'generating';
    const isLoading = status === 'loading-history';
    const canSend = inputText.trim().length > 0 && !isGenerating && !isLoading;

    /** Load history when the sheet opens. */
    useEffect(() => {
        if (open) {
            void loadHistory();
        }
    }, [open, loadHistory]);

    async function handleSend() {
        const text = inputText.trim();

        if (!text || isGenerating) {
            return;
        }

        setInputText('');
        await sendMessage(text);
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void handleSend();
        }
    }

    function handleSelectPrompt(text: string) {
        setInputText(text);
        textareaRef.current?.focus();
    }

    async function handleApplyProposal(messageId: number): Promise<boolean> {
        const success = await applyProposal(messageId);

        if (success) {
            /** Resync the builder behind the sheet so it reflects the applied edit. */
            onDraftRefresh();
        }

        return success;
    }

    async function handleApplyVariant(messageId: number): Promise<boolean> {
        const success = await applyVariant(messageId);

        if (success) {
            onDraftRefresh();
        }

        return success;
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="gap-2 border-violet-300/70 text-foreground hover:border-violet-400 hover:bg-violet-50/70 dark:border-violet-800/70 dark:hover:bg-violet-950/30"
                >
                    <SparklesIcon className="size-4 text-violet-500" />
                    {t('app.ai.trigger')}
                </Button>
            </SheetTrigger>

            {/* Desktop: 400px side panel; Mobile: full-screen sheet */}
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 p-0 sm:w-[400px] sm:max-w-[400px]"
            >
                {/* Branded header */}
                <SheetHeader className="flex-row items-center gap-3 border-b border-border bg-gradient-to-r from-violet-50/80 to-card px-4 py-3.5 dark:from-violet-950/30">
                    <AiAvatar size="md" active={isGenerating} />
                    <div className="flex min-w-0 flex-col">
                        <SheetTitle className="text-[15px] leading-tight">
                            {t('app.ai.sheet_title')}
                        </SheetTitle>
                        <span className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
                            <span
                                className={cn(
                                    'size-1.5 rounded-full bg-emerald-500',
                                    isGenerating && 'animate-pulse',
                                )}
                            />
                            {isGenerating
                                ? t('app.ai.typing')
                                : t('app.ai.subtitle')}
                        </span>
                    </div>
                </SheetHeader>

                {/* Message list — fills available space */}
                <div className="flex min-h-0 flex-1 flex-col bg-gradient-to-b from-background to-muted/30">
                    <MessageList
                        messages={messages}
                        status={isLoading ? 'loading-history' : status}
                        error={error}
                        onSelectPrompt={handleSelectPrompt}
                        onApply={handleApplyProposal}
                        onDismiss={dismissProposal}
                        onApplyVariant={handleApplyVariant}
                        onRetry={retry}
                    />
                </div>

                {/* Fixed bottom input area */}
                <div className="border-t border-border bg-background p-3">
                    <div className="flex items-end gap-2 rounded-2xl border border-input bg-card p-1.5 shadow-sm transition-[box-shadow,border-color] focus-within:border-violet-400/70 focus-within:shadow-md focus-within:ring-2 focus-within:ring-violet-400/20">
                        <Textarea
                            ref={textareaRef}
                            value={inputText}
                            onChange={(e) => setInputText(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={t('app.ai.input_placeholder')}
                            disabled={isGenerating || isLoading}
                            className="max-h-[120px] min-h-[40px] flex-1 resize-none self-center border-0 bg-transparent px-2.5 py-2 shadow-none focus-visible:border-0 focus-visible:ring-0"
                            rows={1}
                        />
                        <motion.button
                            type="button"
                            whileHover={canSend ? { scale: 1.06 } : undefined}
                            whileTap={canSend ? { scale: 0.9 } : undefined}
                            transition={{
                                type: 'spring',
                                stiffness: 500,
                                damping: 22,
                            }}
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-xl text-white transition-colors',
                                canSend
                                    ? 'bg-gradient-to-br from-indigo-500 to-violet-500 shadow-sm shadow-violet-500/30'
                                    : 'cursor-not-allowed bg-muted text-muted-foreground',
                            )}
                            aria-label={t('app.ai.send')}
                            disabled={!canSend}
                            onClick={() => void handleSend()}
                        >
                            {isGenerating ? (
                                <Spinner className="size-4" />
                            ) : (
                                <SendIcon className="size-4" />
                            )}
                        </motion.button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
