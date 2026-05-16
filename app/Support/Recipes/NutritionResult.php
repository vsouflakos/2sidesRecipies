<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;

readonly class NutritionResult
{
    /**
     * @param  array<string, BigDecimal>  $totals
     * @param  array<string, BigDecimal>  $perPortion
     * @param  array<string, BigDecimal>  $per100g
     * @param  list<string>  $missingLines
     */
    public function __construct(
        public array $totals,
        public array $perPortion,
        public array $per100g,
        public array $missingLines,
    ) {}
}
