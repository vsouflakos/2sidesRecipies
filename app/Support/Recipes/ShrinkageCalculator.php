<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ShrinkageCalculator
{
    /**
     * Compute raw weight, cooked weight, loss in grams, and shrinkage percentage.
     *
     * Line shape: { quantity_g: string, yield_pct: ?string }
     * Lines with null yield_pct are treated as 100% yield (no loss).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{raw_weight_g: string, finished_weight_g: string, loss_g: string, shrinkage_pct: string}
     */
    public function compute(array $lines): array
    {
        $rawTotal = BigDecimal::zero();
        $cookedTotal = BigDecimal::zero();
        $hundred = BigDecimal::of('100');

        foreach ($lines as $line) {
            $quantityG = BigDecimal::of((string) $line['quantity_g']);
            $rawTotal = $rawTotal->plus($quantityG);

            $yieldPct = isset($line['yield_pct']) && $line['yield_pct'] !== null
                ? BigDecimal::of((string) $line['yield_pct'])
                : $hundred;

            // cooked contribution = quantity_g * yield_pct / 100
            $cookedContribution = $quantityG
                ->multipliedBy($yieldPct)
                ->dividedBy($hundred, 10, RoundingMode::HALF_UP);

            $cookedTotal = $cookedTotal->plus($cookedContribution);
        }

        $lossG = $rawTotal->minus($cookedTotal);

        // Guard against zero raw total
        // Multiply by 100 before dividing to keep full precision in the final scale-4 result.
        if ($rawTotal->isZero()) {
            $lossPct = BigDecimal::zero();
        } else {
            $lossPct = $lossG
                ->multipliedBy($hundred)
                ->dividedBy($rawTotal, 4, RoundingMode::HALF_UP);
        }

        return [
            'raw_weight_g' => $rawTotal->dividedBy(BigDecimal::one(), 6, RoundingMode::HALF_UP)->__toString(),
            'finished_weight_g' => $cookedTotal->dividedBy(BigDecimal::one(), 6, RoundingMode::HALF_UP)->__toString(),
            'loss_g' => $lossG->dividedBy(BigDecimal::one(), 6, RoundingMode::HALF_UP)->__toString(),
            'shrinkage_pct' => $lossPct->__toString(),
        ];
    }
}
