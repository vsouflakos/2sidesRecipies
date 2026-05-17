import { useState } from 'react';
import { PencilIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import type { ConversationMessage } from '@/types/ai-chat';

interface ProposalCardProps {
    message: ConversationMessage;
    onApply: (messageId: number) => Promise<boolean>;
    onDismiss: (messageId: number) => void;
    onApplyVariant: (messageId: number) => Promise<boolean>;
}

/**
 * Structured proposal card rendered inside an agent message.
 * Supports pending, applied, dismissed, and failed states.
 */
export function ProposalCard({ message, onApply, onDismiss, onApplyVariant }: ProposalCardProps) {
    const { t } = useTranslations();
    const [isApplying, setIsApplying] = useState(false);

    const proposal = message.proposal_state;

    if (!proposal) {
        return null;
    }

    const isVariant = proposal.kind === 'variant';
    const status = proposal.status;

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

    const isApplied = status === 'applied';
    const isDismissed = status === 'dismissed';
    const isFailed = status === 'failed';

    return (
        <Card
            className={[
                'mt-2 rounded-md border p-4',
                isApplied ? 'border-accent/30 pointer-events-none' : 'border-border',
                isDismissed ? 'opacity-60 pointer-events-none' : '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {/* Heading */}
            <div className="mb-2 flex items-center gap-2 text-[20px] font-semibold leading-[1.2]">
                <PencilIcon className="size-4 shrink-0" />
                <span>
                    {isVariant
                        ? t('app.ai.variant_heading')
                        : t('app.ai.proposal_heading')}
                </span>
            </div>

            {/* Body */}
            <p className="mb-4 text-[16px] leading-[1.5] text-muted-foreground">
                {message.content}
            </p>

            {/* Action row */}
            {isApplied ? (
                <div className="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        className="border-accent/30 text-accent-foreground"
                    >
                        {isVariant
                            ? t('app.ai.variant_applied_badge')
                            : t('app.ai.applied_badge')}
                    </Badge>
                    {isVariant && proposal.variant_url && (
                        <a
                            href={proposal.variant_url}
                            className="text-[16px] text-primary underline"
                        >
                            {t('app.ai.open_variant')}
                        </a>
                    )}
                </div>
            ) : isDismissed ? (
                <Badge variant="outline" className="text-muted-foreground">
                    {t('app.ai.dismissed_badge')}
                </Badge>
            ) : (
                <div className="flex flex-col gap-2">
                    <div className="flex justify-end gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="default"
                            className="min-h-[44px] sm:w-auto w-full"
                            aria-label={`Apply proposed change: ${message.content}`}
                            disabled={isApplying}
                            onClick={() => void handleApply()}
                        >
                            {isApplying ? (
                                <Spinner className="mr-2" />
                            ) : null}
                            {t('app.ai.apply')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-[44px] sm:w-auto w-full"
                            disabled={isApplying}
                            onClick={handleDismiss}
                        >
                            {t('app.ai.dismiss')}
                        </Button>
                    </div>
                    {isFailed && proposal.failure_reason && (
                        <p className="text-[14px] text-destructive">
                            {t('app.ai.apply_failure', { reason: proposal.failure_reason })}
                        </p>
                    )}
                </div>
            )}
        </Card>
    );
}
