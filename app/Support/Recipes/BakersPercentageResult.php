<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;

readonly class BakersPercentageResult
{
    /**
     * @param  array<string, BigDecimal>  $linePercentages  Name-keyed percentages.
     */
    public function __construct(
        public bool $applicable,
        public ?BigDecimal $flourBaseG,
        public array $linePercentages,
        public ?BigDecimal $hydrationPct,
    ) {}
}
