import { ArrowUpRightIcon } from 'lucide-react';
import { motion } from 'motion/react';
import { useTranslations } from '@/hooks/use-translations';
import { AiAvatar } from './ai-avatar';

interface StarterPromptsProps {
    onSelectPrompt: (text: string) => void;
}

/**
 * Empty-state component shown when the conversation has no messages.
 * Displays the assistant identity and 4 tappable starter prompt chips
 * that animate in with a staggered cascade and populate the input textarea.
 */
export function StarterPrompts({ onSelectPrompt }: StarterPromptsProps) {
    const { t } = useTranslations();

    const prompts = [
        t('app.ai.starter.suggest_test'),
        t('app.ai.starter.lower_cost'),
        t('app.ai.starter.vegan_variant'),
        t('app.ai.starter.improve_texture'),
    ];

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-5 px-2 py-8">
            <motion.div
                initial={{ opacity: 0, scale: 0.7 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ type: 'spring', stiffness: 260, damping: 18 }}
            >
                <AiAvatar size="lg" />
            </motion.div>

            <motion.p
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.08 }}
                className="max-w-[280px] text-center text-[15px] leading-[1.5] text-muted-foreground"
            >
                {t('app.ai.empty_intro')}
            </motion.p>

            <div className="grid w-full grid-cols-1 gap-2">
                {prompts.map((prompt, index) => (
                    <motion.button
                        key={prompt}
                        type="button"
                        initial={{ opacity: 0, y: 14 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                            delay: 0.14 + index * 0.07,
                            type: 'spring',
                            stiffness: 300,
                            damping: 26,
                        }}
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={() => onSelectPrompt(prompt)}
                        className="group flex min-h-[44px] items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 text-left text-[14px] leading-[1.4] text-foreground shadow-sm transition-colors hover:border-violet-400/60 hover:bg-violet-50/60 dark:hover:bg-violet-950/20"
                    >
                        <span>{prompt}</span>
                        <ArrowUpRightIcon className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-violet-500" />
                    </motion.button>
                ))}
            </div>
        </div>
    );
}
