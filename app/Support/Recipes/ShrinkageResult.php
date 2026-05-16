<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;

readonly class ShrinkageResult
{
    public function __construct(
        public BigDecimal $rawTotalG,
        public BigDecimal $cookedTotalG,
        public BigDecimal $lossG,
        public BigDecimal $lossPct,
    ) {}
}
