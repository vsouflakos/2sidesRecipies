<?php

namespace App\Support\Recipes;

use App\Models\RecipeDraft;
use App\Models\RecipeVersion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;

class MetricsAggregator
{
    public function __construct(private readonly GramNormalizer $gramNormalizer) {}

    /**
     * Prepare the lines array from a draft for downstream metric calculators.
     *
     * For ingredient lines: resolves grams via GramNormalizer, attaches nutrition values,
     * per_gram_cost, allergen set, yield_pct, and is_flour_base.
     *
     * For sub-recipe lines: loads the pinned RecipeVersion, computes
     *   scaleFactor = line.quantity_g / version.yield_g (Pitfall 4: asserts non-null yield_g),
     * then scales cached_nutrition_json and cached_cost_per_gram by scaleFactor.
     * Allergens are carried through unchanged from cached_allergen_slugs.
     *
     * The resulting lines are in the shape NutritionCalculator and CostCalculator consume.
     *
     * @return AggregatedMetrics Prepared lines plus portion/yield context.
     *
     * @throws RuntimeException If a sub-recipe version has a null yield_g.
     */
    public function prepareLines(RecipeDraft $draft): AggregatedMetrics
    {
        $data = $draft->data;
        $sections = $data['sections'] ?? [];
        $preparedLines = [];

        foreach ($sections as $section) {
            foreach ($section['lines'] ?? [] as $lineData) {
                if (isset($lineData['sub_recipe_version_id'])) {
                    $preparedLines[] = $this->prepareSubRecipeLine($lineData);
                } else {
                    $preparedLines[] = $this->prepareIngredientLine($lineData);
                }
            }
        }

        $portions = BigDecimal::of($data['portions'] ?? '1');
        $totalYieldG = BigDecimal::of($data['yield_g'] ?? '0');

        return new AggregatedMetrics($preparedLines, $portions, $totalYieldG);
    }

    /**
     * Prepare a single ingredient line from the draft's JSON structure.
     *
     * @param  array<string, mixed>  $lineData
     * @return array<string, mixed>
     */
    private function prepareIngredientLine(array $lineData): array
    {
        return [
            'type' => 'ingredient',
            'ingredient_id' => $lineData['ingredient_id'] ?? null,
            'grams' => $lineData['quantity_g'] ?? $lineData['grams'] ?? null,
            'energy_kcal' => $lineData['energy_kcal'] ?? null,
            'protein_g' => $lineData['protein_g'] ?? null,
            'fat_g' => $lineData['fat_g'] ?? null,
            'carbs_g' => $lineData['carbs_g'] ?? null,
            'per_gram_cost' => $lineData['per_gram_cost'] ?? null,
            'allergens' => $lineData['allergens'] ?? [],
            'yield_pct' => $lineData['yield_pct'] ?? null,
            'is_flour_base' => $lineData['is_flour_base'] ?? false,
            'name' => $lineData['name_cache'] ?? $lineData['name'] ?? null,
        ];
    }

    /**
     * Prepare a sub-recipe line by loading the pinned version and scaling cached metrics.
     *
     * Pitfall 4: asserts yield_g is non-null before dividing to prevent scale-factor defaulting to 1.
     *
     * @param  array<string, mixed>  $lineData
     * @return array<string, mixed>
     *
     * @throws RuntimeException If yield_g is null on the pinned RecipeVersion.
     */
    private function prepareSubRecipeLine(array $lineData): array
    {
        $version = RecipeVersion::findOrFail($lineData['sub_recipe_version_id']);

        if ($version->yield_g === null) {
            throw new RuntimeException(
                "RecipeVersion [{$version->id}] has a null yield_g. Cannot compute sub-recipe scale factor."
            );
        }

        $lineGrams = BigDecimal::of($lineData['quantity_g'] ?? $lineData['grams'] ?? '0');
        $yieldGrams = BigDecimal::of($version->yield_g);
        $scaleFactor = $lineGrams->dividedBy($yieldGrams, 10, RoundingMode::HALF_UP);

        $cachedNutrition = $version->cached_nutrition_json ?? [];
        $scaledNutrition = $this->scaleNutritionTotals($cachedNutrition, $scaleFactor);

        $cachedCostPerGram = BigDecimal::of($version->cached_cost_per_gram ?? '0');
        $scaledCostPerGram = $cachedCostPerGram->multipliedBy($scaleFactor);

        return [
            'type' => 'sub_recipe',
            'sub_recipe_version_id' => $version->id,
            'grams' => (string) $lineGrams,
            'energy_kcal' => $scaledNutrition['energy_kcal'] ?? null,
            'protein_g' => $scaledNutrition['protein_g'] ?? null,
            'fat_g' => $scaledNutrition['fat_g'] ?? null,
            'carbs_g' => $scaledNutrition['carbs_g'] ?? null,
            'per_gram_cost' => (string) $scaledCostPerGram->toScale(8, RoundingMode::HALF_UP),
            'allergens' => $version->cached_allergen_slugs ?? [],
            'yield_pct' => null,
            'is_flour_base' => false,
            'name' => null,
        ];
    }

    /**
     * Scale nutrition totals from a cached_nutrition_json structure by a BigDecimal factor.
     *
     * @param  array<string, mixed>  $cachedNutrition
     * @return array<string, string> Scaled nutrition values as decimal strings.
     */
    private function scaleNutritionTotals(array $cachedNutrition, BigDecimal $scaleFactor): array
    {
        $total = $cachedNutrition['total'] ?? [];
        $scaled = [];

        foreach ($total as $key => $value) {
            if ($value !== null) {
                $scaled[$key] = BigDecimal::of($value)
                    ->multipliedBy($scaleFactor)
                    ->toScale(4, RoundingMode::HALF_UP)
                    ->__toString();
            }
        }

        return $scaled;
    }
}
