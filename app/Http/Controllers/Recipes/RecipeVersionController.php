<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\StoreRecipeVersionRequest;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Support\Recipes\RecipeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecipeVersionController extends Controller
{
    public function __construct(private readonly RecipeVersionService $versionService) {}

    /**
     * Commit the current draft as a new immutable numbered version.
     *
     * Versions are append-only — this action never modifies existing version rows.
     */
    public function store(StoreRecipeVersionRequest $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('update', $recipe);

        $recipe->load('draft');

        $this->versionService->commit(
            $recipe,
            $request->input('change_note'),
            $request->user()->id,
        );

        return redirect()->route('recipes.show', $recipe);
    }

    /**
     * Show a single recipe version's snapshot.
     */
    public function show(Request $request, Recipe $recipe, RecipeVersion $version): Response
    {
        Gate::authorize('view', $recipe);

        // Ensure the version belongs to this recipe
        if ($version->recipe_id !== $recipe->id) {
            abort(404);
        }

        return Inertia::render('recipes/versions/show', [
            'version' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'committed_at' => $version->committed_at?->toISOString(),
                'change_note' => $version->change_note,
                'snapshot' => $version->snapshot,
            ],
        ]);
    }

    /**
     * Compare two recipe versions side-by-side using their snapshot JSON blobs.
     *
     * Reads directly from snapshot columns — never from the live relational tables.
     * Accepts ?a= and ?b= version IDs.
     */
    public function compare(Request $request, Recipe $recipe): Response
    {
        Gate::authorize('view', $recipe);

        $versionA = RecipeVersion::where('recipe_id', $recipe->id)
            ->findOrFail($request->integer('a'));

        $versionB = RecipeVersion::where('recipe_id', $recipe->id)
            ->findOrFail($request->integer('b'));

        $diff = $this->computeSnapshotDiff($versionA->snapshot ?? [], $versionB->snapshot ?? []);

        return Inertia::render('recipes/versions/compare', [
            'versionA' => [
                'id' => $versionA->id,
                'version_number' => $versionA->version_number,
                'committed_at' => $versionA->committed_at?->toISOString(),
                'change_note' => $versionA->change_note,
                'snapshot' => $versionA->snapshot,
            ],
            'versionB' => [
                'id' => $versionB->id,
                'version_number' => $versionB->version_number,
                'committed_at' => $versionB->committed_at?->toISOString(),
                'change_note' => $versionB->change_note,
                'snapshot' => $versionB->snapshot,
            ],
            'diff' => $diff,
        ]);
    }

    /**
     * Compute a field-by-field diff between two version snapshots.
     *
     * Returns an array of changed field paths with before/after values.
     *
     * @param  array<string, mixed>  $snapshotA
     * @param  array<string, mixed>  $snapshotB
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function computeSnapshotDiff(array $snapshotA, array $snapshotB): array
    {
        $diff = [];

        $metadataFields = ['name', 'portions', 'yield_amount', 'prep_time_minutes', 'cook_time_minutes', 'difficulty', 'notes'];

        foreach ($metadataFields as $field) {
            $valA = $snapshotA[$field] ?? null;
            $valB = $snapshotB[$field] ?? null;

            if ($valA !== $valB) {
                $diff[$field] = ['before' => $valA, 'after' => $valB];
            }
        }

        // Sections comparison (structure-level — number of sections changed)
        $sectionsA = $snapshotA['sections'] ?? [];
        $sectionsB = $snapshotB['sections'] ?? [];

        if (count($sectionsA) !== count($sectionsB)) {
            $diff['sections_count'] = [
                'before' => count($sectionsA),
                'after' => count($sectionsB),
            ];
        }

        return $diff;
    }
}
