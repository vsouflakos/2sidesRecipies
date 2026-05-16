import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { toast } from 'sonner';
import { store as storeVersion } from '@/actions/App/Http/Controllers/Recipes/RecipeVersionController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

interface SaveVersionDialogProps {
    recipeId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

const MAX_NOTE_LENGTH = 140;

/**
 * Dialog for committing the working draft as a new immutable version.
 * Offers an optional ≤140-char change note, with a "Save without note" secondary action.
 * POSTs to recipes.versions.store with partial reload on success.
 */
export function SaveVersionDialog({
    recipeId,
    open,
    onOpenChange,
}: SaveVersionDialogProps) {
    const { t } = useLaravelReactI18n();
    const [note, setNote] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    function handleSave(withNote: boolean) {
        if (isSaving) {
            return;
        }

        setIsSaving(true);

        const payload: Record<string, unknown> = {};

        if (withNote && note.trim()) {
            payload.change_note = note.trim();
        }

        router.post(storeVersion({ recipe: recipeId }).url, payload, {
            preserveState: true,
            preserveScroll: true,
            only: ['versions', 'draft', 'metrics'],
            onSuccess: (page) => {
                const versions = (page.props.versions as Array<{ version_number: number }>) ?? [];
                const latestVersion = versions.find((v) => v.version_number === Math.max(...versions.map((v) => v.version_number)));
                const vN = latestVersion ? `v${latestVersion.version_number}` : '';
                toast.success(t('app.recipes.save_version_toast', { vN }));
                setNote('');
                onOpenChange(false);
            },
            onError: () => {
                toast.error(t('app.recipes.error_state'));
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('app.recipes.save_version_title')}</DialogTitle>
                    <DialogDescription>
                        {t('app.recipes.save_version_body')}
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <Textarea
                        value={note}
                        onChange={(e) =>
                            setNote(e.target.value.slice(0, MAX_NOTE_LENGTH))
                        }
                        placeholder={t('app.recipes.save_version_note_placeholder')}
                        rows={3}
                        maxLength={MAX_NOTE_LENGTH}
                    />
                    <p className="mt-1 text-right text-xs text-muted-foreground">
                        {note.length}/{MAX_NOTE_LENGTH}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => handleSave(false)}
                        disabled={isSaving}
                    >
                        {t('app.recipes.save_version_skip')}
                    </Button>
                    <Button
                        type="button"
                        variant="default"
                        onClick={() => handleSave(true)}
                        disabled={isSaving}
                    >
                        {t('app.recipes.save_version_confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
