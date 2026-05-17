<?php

namespace App\Support\Recipes;

use App\Models\RecipeDraft;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class RecipeMetricsService
{
    public function __construct(
        private readonly MetricsAggregator $metricsAggregator,
        private readonly NutritionCalculator $nutritionCalculator,
        private readonly CostCalculator $costCalculator,
        private readonly ShrinkageCalculator $shrinkageCalculator,
        private readonly BakersPercentageCalculator $bakersPercentageCalculator,
        private readonly AllergenRollupService $allergenRollupService,
    ) {}

    /**
     * Compute the full metrics prop array for the given draft.
     *
     * Uses the draft's selling_price (the mutable current value) for food cost %,
     * NOT the recipe's persisted selling_price.
     *
     * All BigDecimal values are converted to strings at the boundary — never floats.
     *
     * @return array{
     *     nutrition: array{per_portion: array<string, string>, per_100g: array<string, string>},
     *     cost: array{total_cost: string, cost_per_portion: string, food_cost_pct: string|null},
     *     shrinkage: array{raw_weight_g: string, finished_weight_g: string, loss_g: string, shrinkage_pct: string},
     *     bakers: array{flour_base_g: string, percentages: array<string, string>, hydration_pct: string|null}|null,
     *     allergens: array{contains: list<string>, may_contain: list<string>},
     *     missing_data: list<string>,
     *     _raw_nutrition: array<string, mixed>|null,
     *     _raw_cost_per_gram: string|null,
     *     _raw_allergen_slugs: array<string, mixed>|null,
     * }
     */
    public function computeFor(RecipeDraft $draft): array
    {
        $aggregated = $this->metricsAggregator->prepareLines($draft);

        $lines = $aggregated->lines;
        $portions = $aggregated->portions;
        $totalYieldG = $aggregated->totalYieldG;

        // Nutrition
        $nutritionResult = $this->nutritionCalculator->compute(
            lines: $this->mapLinesForNutrition($lines),
            portions: (string) $portions,
            totalYieldG: $totalYieldG->isZero() ? null : (string) $totalYieldG,
        );

        // Cost — use the DRAFT's selling_price (mutable current value, not recipe column)
        $data = $draft->data ?? [];
        $sellingPrice = isset($data['selling_price']) && $data['selling_price'] !== null
            ? (string) $data['selling_price']
            : null;

        $costResult = $this->costCalculator->compute(
            lines: $this->mapLinesForCost($lines),
            portions: (string) $portions,
            sellingPricePerPortion: $sellingPrice,
        );

        // Shrinkage
        $shrinkageResult = $this->shrinkageCalculator->compute(
            $this->mapLinesForShrinkage($lines),
        );

        // Baker's percentage
        $bakersResult = $this->bakersPercentageCalculator->compute(
            $this->mapLinesForBakers($lines),
        );

        // Allergens — each prepared line already carries a flat {slug, state} list.
        $allergenMerged = $this->allergenRollupService->merge(
            ...array_map(
                fn (array $line) => is_array($line['allergens'] ?? null) ? $line['allergens'] : [],
                $lines,
            ),
        );

        // Compute raw cost per gram for version caching
        $rawCostPerGram = $this->computeRawCostPerGram($lines, $totalYieldG);

        // Build allergen slugs structure for caching
        $rawAllergenSlugs = $this->buildAllergenSlugsFromLines($lines);

        // Collect missing data line names
        $missingData = array_unique(array_merge(
            $nutritionResult['missing_lines'] ?? [],
            $costResult['missing_lines'] ?? [],
        ));

        return [
            'nutrition' => [
                'per_portion' => $nutritionResult['per_portion'],
                'per_100g' => $nutritionResult['per_100g'],
            ],
            'cost' => [
                'total_cost' => $costResult['total_cost'],
                'cost_per_portion' => $costResult['cost_per_portion'],
                'food_cost_pct' => $costResult['food_cost_pct'],
            ],
            'shrinkage' => $shrinkageResult,
            'bakers' => $bakersResult,
            'allergens' => $this->buildAllergenDisplay($allergenMerged),
            'missing_data' => array_values($missingData),
            // Internal keys for RecipeVersionService to consume — prefixed with _ to signal non-display use
            '_raw_nutrition' => $nutritionResult,
            '_raw_cost_per_gram' => $rawCostPerGram,
            '_raw_allergen_slugs' => $rawAllergenSlugs,
        ];
    }

    /**
     * Map prepared lines to the shape NutritionCalculator expects.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mapLinesForNutrition(array $lines): array
    {
        return array_map(fn (array $line) => [
            'quantity_g' => (string) ($line['grams'] ?? '0'),
            'energy_kcal' => $line['energy_kcal'] ?? null,
            'protein_g' => $line['protein_g'] ?? null,
            'fat_g' => $line['fat_g'] ?? null,
            'carbs_g' => $line['carbs_g'] ?? null,
            'name' => $line['name'] ?? null,
        ], $lines);
    }

    /**
     * Map prepared lines to the shape CostCalculator expects.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mapLinesForCost(array $lines): array
    {
        return array_map(fn (array $line) => [
            'quantity_g' => (string) ($line['grams'] ?? '0'),
            'cost_per_gram' => isset($line['per_gram_cost']) && $line['per_gram_cost'] !== null
                ? (string) $line['per_gram_cost']
                : null,
            'name' => $line['name'] ?? null,
        ], $lines);
    }

    /**
     * Map prepared lines to the shape ShrinkageCalculator expects.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mapLinesForShrinkage(array $lines): array
    {
        return array_map(fn (array $line) => [
            'quantity_g' => (string) ($line['grams'] ?? '0'),
            'yield_pct' => isset($line['yield_pct']) && $line['yield_pct'] !== null
                ? (string) $line['yield_pct']
                : null,
        ], $lines);
    }

    /**
     * Map prepared lines to the shape BakersPercentageCalculator expects.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mapLinesForBakers(array $lines): array
    {
        return array_map(fn (array $line) => [
            'quantity_g' => (string) ($line['grams'] ?? '0'),
            'is_flour_base' => (bool) ($line['is_flour_base'] ?? false),
            'ingredient_name' => (string) ($line['name'] ?? 'unknown'),
        ], $lines);
    }

    /**
     * Compute the raw cost per gram of the entire recipe for version caching.
     *
     * cost_per_gram = total_cost / total_yield_g (never float, string output).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function computeRawCostPerGram(array $lines, BigDecimal $totalYieldG): ?string
    {
        if ($totalYieldG->isZero()) {
            return null;
        }

        $totalCost = BigDecimal::zero();

        foreach ($lines as $line) {
            if (! isset($line['per_gram_cost']) || $line['per_gram_cost'] === null) {
                continue;
            }

            $grams = BigDecimal::of((string) ($line['grams'] ?? '0'));
            $costPerGram = BigDecimal::of((string) $line['per_gram_cost']);
            $totalCost = $totalCost->plus($grams->multipliedBy($costPerGram));
        }

        return (string) $totalCost->dividedBy($totalYieldG, 8, RoundingMode::HALF_UP);
    }

    /**
     * Build the allergen slugs array structure for version caching.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{contains: list<string>, may_contain: list<string>}
     */
    private function buildAllergenSlugsFromLines(array $lines): array
    {
        $contains = [];
        $mayContain = [];

        foreach ($lines as $line) {
            $allergens = $line['allergens'] ?? [];

            if (is_array($allergens)) {
                foreach ($allergens as $allergen) {
                    if (is_array($allergen) && isset($allergen['slug'])) {
                        $state = $allergen['state'] ?? 'contains';
                        if ($state === 'contains') {
                            $contains[] = $allergen['slug'];
                        } else {
                            $mayContain[] = $allergen['slug'];
                        }
                    }
                }
            }
        }

        return [
            'contains' => array_unique($contains),
            'may_contain' => array_unique(array_diff($mayContain, $contains)),
        ];
    }

    /**
     * Build the display-friendly allergen structure from the merged allergen map.
     *
     * @param  array<string, string>  $merged  slug => state
     * @return array{contains: list<string>, may_contain: list<string>}
     */
    private function buildAllergenDisplay(array $merged): array
    {
        $contains = [];
        $mayContain = [];

        foreach ($merged as $slug => $state) {
            if ($state === 'contains') {
                $contains[] = $slug;
            } else {
                $mayContain[] = $slug;
            }
        }

        return [
            'contains' => $contains,
            'may_contain' => $mayContain,
        ];
    }
}
