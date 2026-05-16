<?php

namespace App\Http\Controllers\Recipes;

use App\Exceptions\DraftSequenceMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\UpdateRecipeDraftRequest;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use App\Support\Recipes\CircularReferenceDetector;
use App\Support\Recipes\RecipeDraftManager;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RecipeDraftController extends Controller
{
    public function __construct(
        private readonly RecipeDraftManager $draftManager,
        private readonly CircularReferenceDetector $circularReferenceDetector,
    ) {}

    /**
     * Update the recipe draft (auto-save) without creating a new version.
     *
     * When the action adds a sub-recipe line, validates there is no circular reference first.
     * Handles the apply_scale action inline using BigDecimal arithmetic to avoid float drift.
     */
    public function update(UpdateRecipeDraftRequest $request, Recipe $recipe): Response|JsonResponse
    {
        $action = $request->string('action')->toString();

        // Ensure a draft exists — create a default one if not yet present
        $draft = $recipe->draft ?? RecipeDraft::create([
            'recipe_id' => $recipe->id,
            'user_id' => auth()->id(),
            'data' => [],
            'edit_sequence' => 0,
        ]);

        // Check for circular references when attaching a sub-recipe
        if ($action === 'add_sub_recipe' || $action === 'attach_sub_recipe') {
            // Resolve candidate recipe from sub_recipe_version_id or candidate_recipe_id
            $candidateRecipeId = null;

            if ($request->has('sub_recipe_version_id')) {
                $subRecipeVersion = RecipeVersion::find($request->integer('sub_recipe_version_id'));
                $candidateRecipeId = $subRecipeVersion?->recipe_id;
            } elseif ($request->has('candidate_recipe_id') || $request->has('data.candidate_recipe_id')) {
                $candidateRecipeId = $request->input('candidate_recipe_id')
                    ?? $request->input('data.candidate_recipe_id');
            }

            if ($candidateRecipeId !== null) {
                // Check cycles using committed relational data AND draft JSON data
                if ($this->wouldCreateCycleIncludingDrafts($recipe->id, (int) $candidateRecipeId)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'sub_recipe_version_id' => ['Cannot add this recipe — it would create a circular reference.'],
                        ],
                    ], 422);
                }
            }
        }

        // Handle apply_scale action with BigDecimal to prevent float drift
        if ($action === 'apply_scale') {
            $numerator = $request->integer('scale_numerator', 1);
            $denominator = $request->integer('scale_denominator', 1);

            $newData = $this->applyScale($draft->data ?? [], $numerator, $denominator);
        } elseif ($action === 'add_sub_recipe' || $action === 'attach_sub_recipe') {
            // Store sub-recipe line in the first section's lines (or flat ingredient_lines)
            $newData = $draft->data ?? [];
            $subRecipeLine = [
                'sub_recipe_version_id' => $request->input('sub_recipe_version_id'),
                'quantity' => $request->input('quantity', 0),
            ];

            if (isset($newData['sections']) && ! empty($newData['sections'])) {
                $newData['sections'][0]['lines'][] = $subRecipeLine;
            } else {
                $lines = $newData['ingredient_lines'] ?? [];
                $lines[] = $subRecipeLine;
                $newData['ingredient_lines'] = $lines;
            }
        } else {
            // Generic action: the caller provides the full new data payload
            $newData = $request->input('data', $draft->data ?? []);

            // If the action updates a single field, apply it inline
            if ($request->has('field') && $request->has('value')) {
                $newData = $draft->data ?? [];
                data_set($newData, $request->string('field')->toString(), $request->input('value'));
            }
        }

        $this->draftManager->applyEdit($draft, $action, $newData);

        // Return 200 with empty content so the client can do a partial Inertia reload
        return response()->noContent();
    }

    /**
     * Recall (undo) the last draft edit.
     *
     * Returns HTTP 409 on a sequence mismatch — the client must refresh before retrying.
     */
    public function recall(Request $request, Recipe $recipe): Response|JsonResponse
    {
        Gate::authorize('update', $recipe);

        $expectedSequence = $request->integer('expected_sequence', $recipe->draft?->edit_sequence ?? 0);

        try {
            $this->draftManager->recall($recipe->draft, $expectedSequence);
        } catch (DraftSequenceMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        // Return 200 with empty content so the client can do a partial Inertia reload
        return response()->noContent();
    }

    /**
     * Check if adding candidateRecipeId as a sub-recipe of parentRecipeId would create a cycle.
     *
     * Augments the relational graph (recipe_ingredient_lines) with draft JSON data,
     * so draft-only sub-recipe additions are also considered during cycle detection.
     *
     * BFS traversal from candidateRecipeId — if parentRecipeId is reachable, a cycle exists.
     */
    private function wouldCreateCycleIncludingDrafts(int $parentRecipeId, int $candidateRecipeId): bool
    {
        if ($parentRecipeId === $candidateRecipeId) {
            return true;
        }

        $visited = [];
        $queue = [$candidateRecipeId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $parentRecipeId) {
                return true;
            }

            // Collect sub-recipe IDs from committed ingredient lines
            $committedIds = RecipeIngredientLine::query()
                ->where('recipe_ingredient_lines.recipe_id', $current)
                ->whereNotNull('sub_recipe_version_id')
                ->join('recipe_versions', 'recipe_versions.id', '=', 'recipe_ingredient_lines.sub_recipe_version_id')
                ->pluck('recipe_versions.recipe_id')
                ->toArray();

            // Collect sub-recipe IDs from the recipe's draft JSON data
            $draftIds = [];
            $draftRecord = RecipeDraft::where('recipe_id', $current)->first();

            if ($draftRecord !== null) {
                $sections = $draftRecord->data['sections'] ?? [];

                foreach ($sections as $section) {
                    foreach ($section['lines'] ?? [] as $line) {
                        if (isset($line['sub_recipe_version_id'])) {
                            $version = RecipeVersion::find($line['sub_recipe_version_id']);
                            if ($version !== null) {
                                $draftIds[] = $version->recipe_id;
                            }
                        }
                    }
                }

                // Also check flat ingredient_lines
                foreach ($draftRecord->data['ingredient_lines'] ?? [] as $line) {
                    if (isset($line['sub_recipe_version_id'])) {
                        $version = RecipeVersion::find($line['sub_recipe_version_id']);
                        if ($version !== null) {
                            $draftIds[] = $version->recipe_id;
                        }
                    }
                }
            }

            foreach (array_unique(array_merge($committedIds, $draftIds)) as $subRecipeId) {
                if (! isset($visited[$subRecipeId])) {
                    $queue[] = $subRecipeId;
                }
            }
        }

        return false;
    }

    /**
     * Scale every ingredient line's quantity by the given rational factor (numerator/denominator).
     *
     * Uses BigDecimal with scale 6, HALF_UP to prevent float drift.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyScale(array $data, int $numerator, int $denominator): array
    {
        $num = BigDecimal::of((string) $numerator);
        $den = BigDecimal::of((string) $denominator);

        if (isset($data['ingredient_lines']) && is_array($data['ingredient_lines'])) {
            $data['ingredient_lines'] = array_map(function (array $line) use ($num, $den) {
                if (isset($line['quantity'])) {
                    $quantity = BigDecimal::of((string) $line['quantity']);
                    $line['quantity'] = (string) $quantity
                        ->multipliedBy($num)
                        ->dividedBy($den, 6, RoundingMode::HALF_UP);
                }

                return $line;
            }, $data['ingredient_lines']);
        }

        // Scale lines within sections too
        if (isset($data['sections']) && is_array($data['sections'])) {
            $data['sections'] = array_map(function (array $section) use ($num, $den) {
                if (isset($section['lines']) && is_array($section['lines'])) {
                    $section['lines'] = array_map(function (array $line) use ($num, $den) {
                        if (isset($line['quantity'])) {
                            $quantity = BigDecimal::of((string) $line['quantity']);
                            $line['quantity'] = (string) $quantity
                                ->multipliedBy($num)
                                ->dividedBy($den, 6, RoundingMode::HALF_UP);
                        }

                        return $line;
                    }, $section['lines']);
                }

                return $section;
            }, $data['sections']);
        }

        return $data;
    }
}
