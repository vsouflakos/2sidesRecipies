import { MessageCircleIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';

interface StarterPromptsProps {
    onSelectPrompt: (text: string) => void;
}

/**
 * Empty-state component shown when the conversation has no messages.
 * Displays 4 tappable starter prompt chips that populate the input textarea.
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
        <div className="flex flex-col items-center gap-4 py-8">
            <MessageCircleIcon className="size-8 text-muted-foreground" />
            <p className="text-center text-[16px] text-muted-foreground">
                {t('app.ai.empty_intro')}
            </p>
            <div className="grid w-full grid-cols-1 gap-2 sm:grid-cols-2">
                {prompts.map((prompt) => (
                    <Button
                        key={prompt}
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-auto min-h-[44px] text-left whitespace-normal"
                        onClick={() => onSelectPrompt(prompt)}
                    >
                        {prompt}
                    </Button>
                ))}
            </div>
        </div>
    );
}
