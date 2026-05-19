import { SparklesIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AiAvatarProps {
    size?: 'sm' | 'md' | 'lg';
    className?: string;
    /** Show a soft pulsing halo (e.g. while the agent is thinking). */
    active?: boolean;
}

const SIZES = {
    sm: { box: 'size-7', icon: 'size-3.5' },
    md: { box: 'size-9', icon: 'size-4' },
    lg: { box: 'size-12', icon: 'size-6' },
} as const;

/**
 * Branded AI assistant avatar — a gradient orb with a sparkle glyph.
 * Shared across chat message rows, the sheet header, and the streaming indicator
 * so the assistant has one consistent visual identity.
 */
export function AiAvatar({
    size = 'md',
    className,
    active = false,
}: AiAvatarProps) {
    const s = SIZES[size];

    return (
        <div
            className={cn(
                'relative flex shrink-0 items-center justify-center rounded-full',
                'bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500',
                'text-white shadow-sm ring-1 shadow-violet-500/30 ring-white/25',
                s.box,
                className,
            )}
        >
            {active && (
                <span className="absolute inset-0 animate-ping rounded-full bg-violet-500/40" />
            )}
            <SparklesIcon className={cn('relative', s.icon)} />
        </div>
    );
}
