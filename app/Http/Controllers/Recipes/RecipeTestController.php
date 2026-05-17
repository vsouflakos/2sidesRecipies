<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\StoreRecipeTestRequest;
use App\Http\Requests\Recipes\UpdateRecipeTestRequest;
use App\Http\Resources\RecipeTestResource;
use App\Models\Recipe;
use App\Models\RecipeTest;
use App\Models\RecipeTestPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RecipeTestController extends Controller
{
    /**
     * Display the tests index page for a recipe.
     */
    public function index(Request $request, Recipe $recipe): Response
    {
        Gate::authorize('view', $recipe);

        $tests = $recipe->tests()
            ->with(['recipeVersion', 'photos'])
            ->orderByDesc('tested_at')
            ->get()
            ->map(fn (RecipeTest $t) => (new RecipeTestResource($t))->resolve());

        $versions = $recipe->versions()
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'committed_at'])
            ->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'committed_at' => $v->committed_at?->toDateString(),
            ]);

        return Inertia::render('recipes/tests/index', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'current_version_id' => $recipe->current_version_id,
            ],
            'tests' => $tests,
            'versions' => $versions,
        ]);
    }

    /**
     * Store a new test for a recipe.
     *
     * Photos are stored atomically inside a DB transaction.
     * A failed store triggers cleanup of any already-stored files.
     */
    public function store(StoreRecipeTestRequest $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('update', $recipe);

        $validated = $request->validated();
        $storedPaths = [];
        $disk = config('filesystems.default', 'public');

        try {
            DB::transaction(function () use ($recipe, $validated, $request, $disk, &$storedPaths) {
                /** @var RecipeTest $test */
                $test = RecipeTest::create([
                    'recipe_id' => $recipe->id,
                    'user_id' => auth()->id(),
                    'recipe_version_id' => $validated['recipe_version_id'],
                    'type' => $validated['type'],
                    'tested_at' => $validated['tested_at'],
                    'overall_rating' => $validated['overall_rating'],
                    'tasting_notes' => $validated['tasting_notes'] ?? null,
                    'ratings' => $validated['ratings'] ?? null,
                    'hypothesis' => $validated['hypothesis'] ?? null,
                    'outcome_narrative' => $validated['outcome_narrative'] ?? null,
                    'verdict' => $validated['verdict'] ?? null,
                    'change_rows' => $validated['change_rows'] ?? null,
                ]);

                foreach ($request->file('photos', []) as $order => $photo) {
                    $path = $photo->store('recipe-tests/'.$test->id, $disk);
                    $storedPaths[] = $path;
                    RecipeTestPhoto::create([
                        'recipe_test_id' => $test->id,
                        'path' => $path,
                        'order' => $order,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        return redirect()->route('recipes.tests.index', $recipe);
    }

    /**
     * Update an existing test.
     *
     * Deleted photos are removed from disk and DB. New photos are appended.
     */
    public function update(UpdateRecipeTestRequest $request, Recipe $recipe, RecipeTest $test): RedirectResponse
    {
        abort_unless($test->recipe_id === $recipe->id, 404);

        Gate::authorize('update', $test);

        $validated = $request->validated();
        $storedPaths = [];
        $disk = config('filesystems.default', 'public');

        try {
            DB::transaction(function () use ($test, $validated, $request, $disk, &$storedPaths) {
                $test->update([
                    'recipe_version_id' => $validated['recipe_version_id'] ?? $test->recipe_version_id,
                    'type' => $validated['type'] ?? $test->type,
                    'tested_at' => $validated['tested_at'] ?? $test->tested_at,
                    'overall_rating' => $validated['overall_rating'] ?? $test->overall_rating,
                    'tasting_notes' => array_key_exists('tasting_notes', $validated) ? $validated['tasting_notes'] : $test->tasting_notes,
                    'ratings' => $validated['ratings'] ?? $test->ratings,
                    'hypothesis' => array_key_exists('hypothesis', $validated) ? $validated['hypothesis'] : $test->hypothesis,
                    'outcome_narrative' => array_key_exists('outcome_narrative', $validated) ? $validated['outcome_narrative'] : $test->outcome_narrative,
                    'verdict' => array_key_exists('verdict', $validated) ? $validated['verdict'] : $test->verdict,
                    'change_rows' => $validated['change_rows'] ?? $test->change_rows,
                ]);

                foreach ($validated['deleted_photo_ids'] ?? [] as $id) {
                    $photo = $test->photos()->find($id);
                    if ($photo) {
                        Storage::disk($disk)->delete($photo->path);
                        $photo->delete();
                    }
                }

                $maxOrder = $test->photos()->max('order') ?? -1;

                foreach ($request->file('photos', []) as $offset => $photo) {
                    $path = $photo->store('recipe-tests/'.$test->id, $disk);
                    $storedPaths[] = $path;
                    RecipeTestPhoto::create([
                        'recipe_test_id' => $test->id,
                        'path' => $path,
                        'order' => $maxOrder + 1 + $offset,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        return redirect()->route('recipes.tests.index', $recipe);
    }

    /**
     * Delete a test and clean up its photo files.
     */
    public function destroy(Request $request, Recipe $recipe, RecipeTest $test): RedirectResponse
    {
        abort_unless($test->recipe_id === $recipe->id, 404);

        Gate::authorize('delete', $test);

        $disk = config('filesystems.default', 'public');

        foreach ($test->photos as $photo) {
            Storage::disk($disk)->delete($photo->path);
        }

        $test->delete();

        return redirect()->route('recipes.tests.index', $recipe);
    }
}
