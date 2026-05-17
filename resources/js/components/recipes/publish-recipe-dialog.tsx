import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { toast } from 'sonner';
import { store as publishStore } from '@/actions/App/Http/Controllers/Recipes/PublishRecipeController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface PublishRecipeDialogProps {
    recipeId: number;
    versions: Array<{ id: number; version_number: number; created_at: string }>;
    subRecipeError?: string[];
    open: boolean;
    onOpenChange: (v: boolean) => void;
}

/**
 * Version-picker publish dialog.
 * Lists committed versions for selection; blocks publish if sub-recipe prerequisites are unmet.
 */
export function PublishRecipeDialog({
    recipeId,
    versions,
    subRecipeError,
    open,
    onOpenChange,
}: PublishRecipeDialogProps) {
    const { t } = useTranslations();

    const latestVersion = versions.length > 0
        ? versions.reduce((max, v) => (v.version_number > max.version_number ? v : max), versions[0])
        : null;

    const [selectedVersionId, setSelectedVersionId] = useState<string>(
        latestVersion ? String(latestVersion.id) : '',
    );
    const [isPublishing, setIsPublishing] = useState(false);

    const hasSubRecipeError = Array.isArray(subRecipeError) && subRecipeError.length > 0;

    function handlePublish() {
        if (!selectedVersionId || isPublishing || hasSubRecipeError) {
            return;
        }

        setIsPublishing(true);

        router.post(
            publishStore({ recipe: recipeId }).url,
            { version_id: Number(selectedVersionId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    toast.success(t('app.recipes.publish_toast'));
                },
                onFinish: () => {
                    setIsPublishing(false);
                },
            },
        );
    }

    function formatVersionLabel(v: { version_number: number; created_at: string }): string {
        const date = new Date(v.created_at).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

        return `v${v.version_number} — saved ${date}`;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('app.recipes.publish_dialog_title')}</DialogTitle>
                    <DialogDescription>
                        {t('app.recipes.publish_dialog_body')}
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2 flex flex-col gap-4">
                    {/* Sub-recipe prerequisite error */}
                    {hasSubRecipeError && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                <ul className="list-disc pl-4 space-y-1">
                                    {subRecipeError!.map((name, i) => (
                                        <li key={i}>
                                            {t('app.recipes.sub_recipe_not_published', { name })}
                                        </li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Version picker */}
                    {versions.length > 0 && (
                        <Select
                            value={selectedVersionId}
                            onValueChange={setSelectedVersionId}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[...versions]
                                    .sort((a, b) => b.version_number - a.version_number)
                                    .map((v) => (
                                        <SelectItem key={v.id} value={String(v.id)}>
                                            {formatVersionLabel(v)}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={isPublishing}
                    >
                        {t('app.recipes.publish_dialog_dismiss')}
                    </Button>
                    <Button
                        type="button"
                        variant="default"
                        onClick={handlePublish}
                        disabled={isPublishing || hasSubRecipeError || !selectedVersionId}
                    >
                        {t('app.recipes.publish_dialog_confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
