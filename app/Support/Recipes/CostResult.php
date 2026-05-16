<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;

readonly class CostResult
{
    /**
     * @param  list<string>  $missingLines
     */
    public function __construct(
        public BigDecimal $totalCost,
        public BigDecimal $costPerPortion,
        public ?BigDecimal $foodCostPct,
        public array $missingLines,
    ) {}
}
