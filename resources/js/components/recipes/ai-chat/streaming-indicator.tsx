import { motion } from 'motion/react';
import { useTranslations } from '@/hooks/use-translations';
import { AiAvatar } from './ai-avatar';

/** Stagger delays for the three bouncing dots. */
const DOT_DELAYS = [0, 0.16, 0.32];

/**
 * Animated typing indicator shown while the AI agent is preparing a response,
 * before the first streamed token arrives. Three dots bounce in a staggered
 * wave inside an agent-styled bubble shell.
 */
export function StreamingIndicator() {
    const { t } = useTranslations();

    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ type: 'spring', stiffness: 420, damping: 32 }}
            className="flex items-start gap-2.5"
        >
            <AiAvatar size="sm" active className="mt-0.5" />
            <div
                className="inline-flex items-center gap-1.5 rounded-2xl rounded-tl-md border border-border bg-card px-4 py-3.5 shadow-sm"
                aria-label={t('app.ai.typing')}
                role="status"
            >
                {DOT_DELAYS.map((delay) => (
                    <motion.span
                        key={delay}
                        className="size-2 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500"
                        animate={{ y: [0, -5, 0], opacity: [0.45, 1, 0.45] }}
                        transition={{
                            duration: 0.9,
                            repeat: Infinity,
                            ease: 'easeInOut',
                            delay,
                        }}
                    />
                ))}
            </div>
        </motion.div>
    );
}
