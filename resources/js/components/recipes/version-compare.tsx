import { ArrowRightIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/** A single changed field entry from the server-computed diff. */
interface DiffEntry {
    before: unknown;
    after: unknown;
}

/** Server-computed diff shape from RecipeVersionController@compare. */
type VersionDiff = Record<string, DiffEntry>;

/** Snapshot structure for a version (mirrors RecipeVersion snapshot). */
interface VersionSnapshot {
    name?: string | null;
    portions?: number | null;
    yield_amount?: string | null;
    prep_time_minutes?: number | null;
    cook_time_minutes?: number | null;
    difficulty?: string | null;
    notes?: string | null;
    sections?: Array<{
        name?: string;
        lines?: Array<{ name?: string; quantity?: string }>;
        steps?: Array<{ instruction?: string }>;
    }>;
    [key: string]: unknown;
}

/** A single version passed to the compare view. */
interface VersionData {
    id: number;
    version_number: number;
    committed_at: string | null;
    change_note: string | null;
    snapshot: VersionSnapshot | null;
}

interface VersionCompareProps {
    versionA: VersionData;
    versionB: VersionData;
    diff: VersionDiff;
}

function formatDate(dateString: string | null): string {
    if (!dateString) {
        return '—';
    }

    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatFieldLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatFieldValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return String(value);
}

interface MetadataRowProps {
    label: string;
    valueA: unknown;
    valueB: unknown;
    isDiff: boolean;
}

function MetadataRow({ label, valueA, valueB, isDiff }: MetadataRowProps) {
    return (
        <div
            className={cn(
                'grid grid-cols-2 gap-4 rounded-md px-3 py-2 text-sm',
                isDiff &&
                    'bg-yellow-50 dark:bg-yellow-900/20',
            )}
        >
            <div className="flex items-center gap-2">
                {isDiff && (
                    <ArrowRightIcon
                        className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                        aria-label="Changed"
                    />
                )}
                <div>
                    <span className="text-xs font-medium text-muted-foreground">
                        {label}
                    </span>
                    <p className="font-medium">{formatFieldValue(valueA)}</p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                {isDiff && (
                    <ArrowRightIcon
                        className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                        aria-label="Changed"
                    />
                )}
                <div>
                    <span className="text-xs font-medium text-muted-foreground">
                        {label}
                    </span>
                    <p className="font-medium">{formatFieldValue(valueB)}</p>
                </div>
            </div>
        </div>
    );
}

/**
 * Side-by-side version diff component.
 *
 * Left column = version A, right column = version B.
 * Changed rows are highlighted bg-yellow-50 / dark:bg-yellow-900/20 AND
 * get a leading ArrowRightIcon so changes are not signalled by color alone (accessibility).
 */
export function VersionCompare({ versionA, versionB, diff }: VersionCompareProps) {
    const snapshotA = versionA.snapshot ?? {};
    const snapshotB = versionB.snapshot ?? {};

    const metadataFields: Array<keyof VersionSnapshot> = [
        'name',
        'portions',
        'yield_amount',
        'prep_time_minutes',
        'cook_time_minutes',
        'difficulty',
        'notes',
    ];

    const changedFields = new Set(Object.keys(diff));

    return (
        <div className="flex flex-col gap-4">
            {/* Column headers */}
            <div className="grid grid-cols-2 gap-4">
                <div className="rounded-md border border-border bg-muted p-3">
                    <p className="text-sm font-semibold">
                        v{versionA.version_number}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {formatDate(versionA.committed_at)}
                    </p>
                    {versionA.change_note && (
                        <p className="mt-1 text-xs text-muted-foreground italic">
                            {versionA.change_note}
                        </p>
                    )}
                </div>
                <div className="rounded-md border border-border bg-muted p-3">
                    <p className="text-sm font-semibold">
                        v{versionB.version_number}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {formatDate(versionB.committed_at)}
                    </p>
                    {versionB.change_note && (
                        <p className="mt-1 text-xs text-muted-foreground italic">
                            {versionB.change_note}
                        </p>
                    )}
                </div>
            </div>

            {/* Metadata rows */}
            <div className="flex flex-col gap-1 rounded-md border border-border overflow-hidden">
                {metadataFields.map((field) => {
                    const isDiff = changedFields.has(field);

                    return (
                        <MetadataRow
                            key={field}
                            label={formatFieldLabel(field)}
                            valueA={snapshotA[field]}
                            valueB={snapshotB[field]}
                            isDiff={isDiff}
                        />
                    );
                })}
            </div>

            {/* Sections count diff */}
            {changedFields.has('sections_count') && (
                <div className="grid grid-cols-2 gap-4 rounded-md bg-yellow-50 dark:bg-yellow-900/20 px-3 py-2">
                    <div className="flex items-center gap-2 text-sm">
                        <ArrowRightIcon
                            className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                            aria-label="Changed"
                        />
                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                Sections
                            </span>
                            <p className="font-medium">
                                {formatFieldValue(diff['sections_count']?.before)} sections
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <ArrowRightIcon
                            className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                            aria-label="Changed"
                        />
                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                Sections
                            </span>
                            <p className="font-medium">
                                {formatFieldValue(diff['sections_count']?.after)} sections
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Other diff fields not covered above */}
            {Object.entries(diff)
                .filter(
                    ([key]) =>
                        !metadataFields.includes(key as keyof VersionSnapshot) &&
                        key !== 'sections_count',
                )
                .map(([key, entry]) => (
                    <div
                        key={key}
                        className="grid grid-cols-2 gap-4 rounded-md bg-yellow-50 dark:bg-yellow-900/20 px-3 py-2 text-sm"
                    >
                        <div className="flex items-center gap-2">
                            <ArrowRightIcon
                                className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                                aria-label="Changed"
                            />
                            <div>
                                <span className="text-xs font-medium text-muted-foreground">
                                    {formatFieldLabel(key)}
                                </span>
                                <p className="font-medium">{formatFieldValue(entry.before)}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <ArrowRightIcon
                                className="size-3.5 shrink-0 text-yellow-600 dark:text-yellow-400"
                                aria-label="Changed"
                            />
                            <div>
                                <span className="text-xs font-medium text-muted-foreground">
                                    {formatFieldLabel(key)}
                                </span>
                                <p className="font-medium">{formatFieldValue(entry.after)}</p>
                            </div>
                        </div>
                    </div>
                ))}
        </div>
    );
}
