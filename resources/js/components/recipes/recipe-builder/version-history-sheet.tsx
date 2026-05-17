import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { compare as compareVersions } from '@/actions/App/Http/Controllers/Recipes/RecipeVersionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { RecipeVersion } from '@/types/recipe';

interface VersionHistorySheetProps {
    recipeId: number;
    versions: RecipeVersion[];
    /** The current version number shown in the trigger button label. */
    currentVersionNumber: number;
}

/**
 * Version history Sheet (side="right", 320px).
 * Lists all versions with version number, date, and change note.
 * The "current" version gets a "Current" badge.
 * Selecting two versions via "Compare" buttons navigates to the compare view.
 */
export function VersionHistorySheet({
    recipeId,
    versions,
    currentVersionNumber,
}: VersionHistorySheetProps) {
    const { t } = useTranslations();
    const [selectedVersionIds, setSelectedVersionIds] = useState<number[]>([]);

    function handleCompareClick(versionId: number) {
        setSelectedVersionIds((prev) => {
            const alreadySelected = prev.includes(versionId);

            if (alreadySelected) {
                return prev.filter((id) => id !== versionId);
            }

            const next = [...prev, versionId].slice(-2);

            if (next.length === 2) {
                const [a, b] = next;
                router.visit(
                    compareVersions({ recipe: recipeId }, { query: { a, b } }).url,
                );
                return [];
            }

            return next;
        });
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    const triggerLabel = t('app.recipes.version_history_btn', {
        vN: `v${currentVersionNumber}`,
    });

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button type="button" variant="ghost" size="sm">
                    {triggerLabel}
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-[320px] sm:w-[320px]">
                <SheetHeader>
                    <SheetTitle>{t('app.recipes.version_history_heading')}</SheetTitle>
                </SheetHeader>

                <div className="mt-4 flex flex-col gap-1 overflow-y-auto">
                    {versions.map((version) => {
                        const isSelected = selectedVersionIds.includes(version.id);
                        const formattedDate = formatDate(version.created_at);
                        const noteDisplay = version.change_note ?? '—';

                        return (
                            <div
                                key={version.id}
                                className="flex items-start justify-between gap-2 rounded-md px-2 py-2 hover:bg-muted/50"
                            >
                                <div className="flex flex-1 flex-col gap-0.5 text-sm">
                                    <div className="flex items-center gap-1.5">
                                        <span className="font-medium">
                                            v{version.version_number}
                                        </span>
                                        <span className="text-muted-foreground">·</span>
                                        <span className="text-muted-foreground text-xs">
                                            {formattedDate}
                                        </span>
                                        {version.is_current && (
                                            <Badge variant="secondary" className="text-xs py-0">
                                                {t('app.recipes.version_history_current')}
                                            </Badge>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground text-xs truncate">
                                        {noteDisplay}
                                    </span>
                                </div>

                                <Button
                                    type="button"
                                    variant={isSelected ? 'default' : 'outline'}
                                    size="sm"
                                    className="shrink-0 text-xs"
                                    onClick={() => handleCompareClick(version.id)}
                                >
                                    {t('app.recipes.version_history_compare')}
                                </Button>
                            </div>
                        );
                    })}
                </div>

                {selectedVersionIds.length === 1 && (
                    <p className="mt-3 px-2 text-xs text-muted-foreground">
                        Select one more version to compare.
                    </p>
                )}
            </SheetContent>
        </Sheet>
    );
}
