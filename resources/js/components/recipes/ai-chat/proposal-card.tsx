import { CheckIcon, SparklesIcon, WandSparklesIcon } from 'lucide-react';
import { motion } from 'motion/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ConversationMessage } from '@/types/ai-chat';

interface ProposalCardProps {
    message: ConversationMessage;
    onApply: (messageId: number) => Promise<boolean>;
    onDismiss: (messageId: number) => void;
    onApplyVariant: (messageId: number) => Promise<boolean>;
}

/**
 * Structured proposal card rendered inside (or beside) an agent message.
 * Supports pending, applied, dismissed, and failed states with an animated
 * confirmation when the change is applied to the recipe.
 */
export function ProposalCard({
    message,
    onApply,
    onDismiss,
    onApplyVariant,
}: ProposalCardProps) {
    const { t } = useTranslations();
    const [isApplying, setIsApplying] = useState(false);

    const proposal = message.proposal_state;

    if (!proposal) {
        return null;
    }

    const isVariant = proposal.kind === 'variant';
    const status = proposal.status;
    const isApplied = status === 'applied';
    const isDismissed = status === 'dismissed';
    const isFailed = status === 'failed';

    async function handleApply() {
        setIsApplying(true);

        try {
            if (isVariant) {
                await onApplyVariant(message.id);
            } else {
                await onApply(message.id);
            }
        } finally {
            setIsApplying(false);
        }
    }

    function handleDismiss() {
        onDismiss(message.id);
    }

    return (
        <motion.div
            initial={{ opacity: 0, y: 12, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ type: 'spring', stiffness: 380, damping: 30 }}
            className={cn(
                'mt-2 ml-[2.375rem] shrink-0 overflow-hidden rounded-xl border shadow-sm',
                isApplied
                    ? 'border-emerald-500/40 bg-emerald-50/40 dark:bg-emerald-950/20'
                    : 'border-violet-400/45 bg-gradient-to-b from-violet-50/70 to-card dark:from-violet-950/25',
                isDismissed && 'opacity-55',
            )}
        >
            {/* Header */}
            <div className="flex items-center gap-2 border-b border-border/60 px-3.5 py-2.5">
                <div
                    className={cn(
                        'flex size-6 shrink-0 items-center justify-center rounded-md text-white shadow-sm',
                        isApplied
                            ? 'bg-emerald-500'
                            : 'bg-gradient-to-br from-indigo-500 to-violet-500',
                    )}
                >
                    {isVariant ? (
                        <WandSparklesIcon className="size-3.5" />
                    ) : (
                        <SparklesIcon className="size-3.5" />
                    )}
                </div>
                <span className="text-[12px] font-semibold tracking-wide text-foreground/80 uppercase">
                    {isVariant
                        ? t('app.ai.variant_heading')
                        : t('app.ai.proposal_heading')}
                </span>
            </div>

            {/* Body */}
            <div className="px-3.5 py-3">
                <p className="text-[14px] leading-[1.55] text-foreground/90">
                    {message.content}
                </p>

                {/* Action area */}
                <div className="mt-3">
                    {isApplied ? (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            className="flex items-center gap-2"
                        >
                            <motion.span
                                initial={{ scale: 0, rotate: -30 }}
                                animate={{ scale: 1, rotate: 0 }}
                                transition={{
                                    type: 'spring',
                                    stiffness: 500,
                                    damping: 16,
                                }}
                                className="flex size-5 items-center justify-center rounded-full bg-emerald-500 text-white"
                            >
                                <CheckIcon className="size-3" strokeWidth={3} />
                            </motion.span>
                            <span className="text-[13px] font-medium text-emerald-600 dark:text-emerald-400">
                                {isVariant
                                    ? t('app.ai.variant_applied_badge')
                                    : t('app.ai.applied_badge')}
                            </span>
                            {isVariant && proposal.variant_url && (
                                <a
                                    href={proposal.variant_url}
                                    className="ml-auto text-[13px] font-medium text-violet-600 underline-offset-2 hover:underline dark:text-violet-400"
                                >
                                    {t('app.ai.open_variant')}
                                </a>
                            )}
                        </motion.div>
                    ) : isDismissed ? (
                        <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-[12px] font-medium text-muted-foreground">
                            {t('app.ai.dismissed_badge')}
                        </span>
                    ) : (
                        <div className="flex flex-col gap-2">
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-[40px] w-full active:scale-[0.98] sm:w-auto"
                                    disabled={isApplying}
                                    onClick={handleDismiss}
                                >
                                    {t('app.ai.dismiss')}
                                </Button>
                                <Button
                                    type="button"
                                    className="min-h-[40px] w-full border-0 bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow-sm transition-[transform,box-shadow] hover:from-indigo-600 hover:to-violet-600 hover:text-white active:scale-[0.98] sm:w-auto"
                                    aria-label={`Apply proposed change: ${message.content}`}
                                    disabled={isApplying}
                                    onClick={() => void handleApply()}
                                >
                                    {isApplying ? (
                                        <Spinner className="mr-1" />
                                    ) : null}
                                    {t('app.ai.apply')}
                                </Button>
                            </div>
                            {isFailed && proposal.failure_reason && (
                                <p className="text-[13px] text-destructive">
                                    {t('app.ai.apply_failure', {
                                        reason: proposal.failure_reason,
                                    })}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </motion.div>
    );
}
