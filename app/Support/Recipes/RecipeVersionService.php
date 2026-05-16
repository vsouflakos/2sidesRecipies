<?php

namespace App\Support\Recipes;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class RecipeVersionService
{
    public function __construct(private readonly RecipeMetricsService $metricsService) {}

    /**
     * Commit the current draft as a new immutable numbered version.
     *
     * Snapshots the draft data, computes and caches all metrics, updates the
     * recipe's current_version_id and selling_price. Versions are append-only —
     * this method only ever inserts a new row, never updates an existing one.
     *
     * @param  Recipe  $recipe  The recipe whose draft will be committed.
     * @param  string|null  $changeNote  Optional human-readable change summary.
     * @param  int  $committedBy  User ID of the committing user.
     */
    public function commit(Recipe $recipe, ?string $changeNote, int $committedBy): RecipeVersion
    {
        return DB::transaction(function () use ($recipe, $changeNote, $committedBy) {
            $draft = $recipe->draft;

            $data = $draft->data ?? [];

            // Compute the next version number (max + 1) — never update an existing version
            $nextVersionNumber = (RecipeVersion::where('recipe_id', $recipe->id)->max('version_number') ?? 0) + 1;

            // Compute yield_g from the draft data
            $yieldG = $this->computeYieldG($data);

            // Compute metrics using the draft
            $metrics = $this->metricsService->computeFor($draft);

            // Extract cached values from computed metrics
            $cachedNutritionJson = $metrics['_raw_nutrition'] ?? null;
            $cachedCostPerGram = $metrics['_raw_cost_per_gram'] ?? null;
            $cachedAllergenSlugs = $metrics['_raw_allergen_slugs'] ?? null;

            // Selling price: snapshot from draft data (the mutable current value)
            $sellingPrice = isset($data['selling_price']) && $data['selling_price'] !== null
                ? (string) $data['selling_price']
                : null;

            // Compute cached_cost_per_portion: cost_per_gram * yield_g / portions
            $cachedCostPerPortion = $this->computeCostPerPortion(
                $cachedCostPerGram,
                $yieldG,
                $data['portions'] ?? 1
            );

            // Create the new version row (append-only — never update)
            $version = RecipeVersion::create([
                'recipe_id' => $recipe->id,
                'version_number' => $nextVersionNumber,
                'committed_by' => $committedBy,
                'committed_at' => now(),
                'change_note' => $changeNote,
                'snapshot' => $data,
                'yield_g' => $yieldG,
                'cached_nutrition_json' => $cachedNutritionJson,
                'cached_cost_per_gram' => $cachedCostPerGram,
                'cached_cost_per_portion' => $cachedCostPerPortion,
                'cached_allergen_slugs' => $cachedAllergenSlugs,
                'cached_selling_price' => $sellingPrice,
            ]);

            // Update the recipe's current version and persist the selling price default
            $recipe->current_version_id = $version->id;
            if ($sellingPrice !== null) {
                $recipe->selling_price = $sellingPrice;
            }
            $recipe->save();

            return $version;
        });
    }

    /**
     * Compute the yield in grams from the draft data.
     *
     * Uses yield_g directly from the draft data if present (pre-computed by the
     * client), or falls back to yield_amount from the draft metadata.
     *
     * @param  array<string, mixed>  $data
     */
    private function computeYieldG(array $data): ?string
    {
        // If yield_g is already computed in the draft data, use it directly
        if (isset($data['yield_g']) && $data['yield_g'] !== null) {
            return (string) BigDecimal::of((string) $data['yield_g'])
                ->toScale(4, RoundingMode::HALF_UP);
        }

        // Fall back to yield_amount if present
        if (isset($data['yield_amount']) && $data['yield_amount'] !== null) {
            return (string) BigDecimal::of((string) $data['yield_amount'])
                ->toScale(4, RoundingMode::HALF_UP);
        }

        return null;
    }

    /**
     * Compute cached_cost_per_portion = cached_cost_per_gram × yield_g ÷ portions.
     *
     * Uses BigDecimal with scale 4, HALF_UP to avoid float drift.
     */
    private function computeCostPerPortion(?string $costPerGram, ?string $yieldG, int|string $portions): ?string
    {
        if ($costPerGram === null || $yieldG === null) {
            return null;
        }

        $portionsBD = BigDecimal::of((string) $portions);

        if ($portionsBD->isZero()) {
            return null;
        }

        $costPerGramBD = BigDecimal::of($costPerGram);
        $yieldGBD = BigDecimal::of($yieldG);

        return (string) $costPerGramBD
            ->multipliedBy($yieldGBD)
            ->dividedBy($portionsBD, 4, RoundingMode::HALF_UP);
    }
}
