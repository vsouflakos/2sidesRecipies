import { router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { update as updateDraft } from '@/actions/App/Http/Controllers/Recipes/RecipeDraftController';

export type AutosaveStatus = 'idle' | 'saving' | 'saved';

/**
 * Provides a debounced auto-save mechanism for the recipe builder.
 *
 * @param recipeId - The ID of the recipe being edited.
 * @returns save callback and current status indicator.
 */
export function useRecipeAutosave(recipeId: number): {
    save: (action: string, draftData: Record<string, unknown>, expectedSequence?: number) => void;
    status: AutosaveStatus;
} {
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const save = useCallback(
        (action: string, draftData: Record<string, unknown>, expectedSequence?: number) => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }

            setStatus('saving');

            // 600 ms debounce before firing the network request
            debounceTimerRef.current = setTimeout(() => {
                const payload: Record<string, unknown> = {
                    action,
                    data: draftData,
                };

                if (expectedSequence !== undefined) {
                    payload.expected_sequence = expectedSequence;
                }

                router.put(updateDraft({ recipe: recipeId }).url, payload, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['draft', 'metrics'],
                    onSuccess: () => {
                        setStatus('saved');

                        // Clear "Saved" indicator after 2 seconds
                        if (savedTimerRef.current) {
                            clearTimeout(savedTimerRef.current);
                        }
                        savedTimerRef.current = setTimeout(() => {
                            setStatus('idle');
                        }, 2000);
                    },
                    onError: () => {
                        setStatus('idle');
                    },
                });
            }, 600);
        },
        [recipeId],
    );

    return { save, status };
}
