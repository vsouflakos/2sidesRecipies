import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { VersionCompare } from '@/components/recipes/version-compare';
import { Button } from '@/components/ui/button';
import { show as showRecipe } from '@/actions/App/Http/Controllers/Recipes/RecipeController';

/** A single changed field entry from the server-computed diff. */
interface DiffEntry {
    before: unknown;
    after: unknown;
}

/** Server-computed diff shape from RecipeVersionController@compare. */
type VersionDiff = Record<string, DiffEntry>;

/** Snapshot structure for a version. */
type VersionSnapshot = Record<string, unknown>;

interface VersionData {
    id: number;
    version_number: number;
    committed_at: string | null;
    change_note: string | null;
    snapshot: VersionSnapshot | null;
}

interface ComparePageProps {
    versionA: VersionData;
    versionB: VersionData;
    diff: VersionDiff;
}

/**
 * Inertia page for GET /recipes/{id}/versions/compare?a=&b=
 * Renders a side-by-side diff of two recipe versions.
 */
export default function RecipeVersionsCompare({
    versionA,
    versionB,
    diff,
}: ComparePageProps) {
    const { t } = useLaravelReactI18n();

    // "Comparing {vA} → {vB}" — translation key: app.recipes.version_compare_heading
    const headingText = t('app.recipes.version_compare_heading', {
        vA: `v${versionA.version_number}`,
        vB: `v${versionB.version_number}`,
    });

    /**
     * The recipe ID is embedded in both version IDs via the route — but the
     * controller embeds the recipe's snapshot, not a direct recipe_id.
     * We extract it from the current URL via the snapshot or use a back link.
     */
    const backUrl = typeof window !== 'undefined'
        ? (() => {
              const params = new URLSearchParams(window.location.search);
              const url = window.location.pathname;
              // Extract recipe ID from path: /recipes/{id}/versions/compare
              const match = url.match(/\/recipes\/(\d+)\//);
              if (match) {
                  return showRecipe({ recipe: parseInt(match[1], 10) }).url;
              }
              return '/recipes';
          })()
        : '/recipes';

    return (
        <>
            <Head title={headingText} />

            <div className="flex flex-col gap-6 p-6">
                {/* Page heading */}
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-[20px] font-semibold leading-[1.2]">
                        {headingText}
                    </h1>

                    <Button type="button" variant="outline" size="sm" asChild>
                        <Link href={backUrl}>Back to builder</Link>
                    </Button>
                </div>

                {/* Side-by-side version diff */}
                <VersionCompare
                    versionA={versionA}
                    versionB={versionB}
                    diff={diff}
                />
            </div>
        </>
    );
}
