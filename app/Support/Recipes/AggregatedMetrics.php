<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;

/**
 * Readonly value object holding the prepared per-line array plus portion/yield context.
 * The $lines array is in the shape the Plan 02 calculators (NutritionCalculator, etc.) consume.
 */
readonly class AggregatedMetrics
{
    /**
     * @param  array<int, array<string, mixed>>  $lines  Prepared lines for downstream calculators.
     * @param  BigDecimal  $portions  Number of portions the recipe yields.
     * @param  BigDecimal  $totalYieldG  Total gram yield of the recipe.
     */
    public function __construct(
        public array $lines,
        public BigDecimal $portions,
        public BigDecimal $totalYieldG,
    ) {}
}
