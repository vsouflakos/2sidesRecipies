import { useEffect, useRef, useState } from 'react';
import { MessageCircleIcon, SendIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
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
import { MessageList } from './message-list';

interface AiChatSheetProps {
    recipeId: number;
    /** Resync the recipe builder from the server after a proposal is applied. */
    onDraftRefresh: () => void;
}

/**
 * AI chat slide-over sheet for the recipe builder.
 * Opens on the right (desktop) or full-screen (mobile).
 * Loads conversation history on open, supports streaming agent replies,
 * starter prompts, proposal apply/dismiss, and builder refresh on apply.
 */
export function AiChatSheet({ recipeId, onDraftRefresh }: AiChatSheetProps) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const [inputText, setInputText] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const { messages, status, sendMessage, applyProposal, applyVariant, dismissProposal, retry, loadHistory } =
        useAiChat(recipeId);

    const isStreaming = status === 'streaming';
    const isLoading = status === 'loading-history';

    /** Load history when the sheet opens. */
    useEffect(() => {
        if (open) {
            void loadHistory();
        }
    }, [open, loadHistory]);

    async function handleSend() {
        const text = inputText.trim();

        if (!text || isStreaming) {
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
                <Button type="button" variant="outline" size="sm" className="gap-2">
                    <MessageCircleIcon className="size-4" />
                    {t('app.ai.trigger')}
                </Button>
            </SheetTrigger>

            {/* Desktop: 400px side panel; Mobile: full-screen bottom sheet */}
            <SheetContent
                side="right"
                className="flex w-full flex-col p-0 sm:w-[400px] sm:max-w-[400px]"
            >
                <SheetHeader className="px-4 pt-4 pb-2">
                    <SheetTitle>{t('app.ai.sheet_title')}</SheetTitle>
                </SheetHeader>

                <Separator />

                {/* Message list — fills available space */}
                <div className="flex min-h-0 flex-1 flex-col">
                    <MessageList
                        messages={messages}
                        status={isLoading ? 'loading-history' : status}
                        onSelectPrompt={handleSelectPrompt}
                        onApply={handleApplyProposal}
                        onDismiss={dismissProposal}
                        onApplyVariant={handleApplyVariant}
                        onRetry={retry}
                    />
                </div>

                {/* Fixed bottom input area */}
                <div className="flex items-end gap-2 border-t border-border p-4">
                    <Textarea
                        ref={textareaRef}
                        value={inputText}
                        onChange={(e) => setInputText(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={t('app.ai.input_placeholder')}
                        disabled={isStreaming || isLoading}
                        className="min-h-[44px] max-h-[120px] resize-none flex-1"
                        rows={1}
                    />
                    <Button
                        type="button"
                        variant="default"
                        size="icon"
                        className="size-11 shrink-0"
                        aria-label={t('app.ai.send')}
                        disabled={!inputText.trim() || isStreaming || isLoading}
                        onClick={() => void handleSend()}
                    >
                        {isStreaming ? (
                            <Spinner />
                        ) : (
                            <SendIcon className="size-4" />
                        )}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
