<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class CostCalculator
{
    /**
     * Compute total cost, cost per portion, and optional food cost percentage.
     *
     * Line shape: { quantity_g: string, cost_per_gram: ?string }
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{total_cost: string, cost_per_portion: string, food_cost_pct: string|null, missing_lines: list<string>}
     */
    public function compute(
        array $lines,
        string $portions = '1',
        ?string $sellingPricePerPortion = null
    ): array {
        $portionsBD = BigDecimal::of($portions);
        $totalCost = BigDecimal::zero();
        $missingLines = [];

        foreach ($lines as $line) {
            if (! isset($line['cost_per_gram']) || $line['cost_per_gram'] === null) {
                $name = $line['name'] ?? $line['ingredient_name'] ?? 'unknown';
                $missingLines[] = (string) $name;

                continue;
            }

            $quantityG = BigDecimal::of((string) $line['quantity_g']);
            $costPerGram = BigDecimal::of((string) $line['cost_per_gram']);

            $totalCost = $totalCost->plus($quantityG->multipliedBy($costPerGram));
        }

        // Guard against zero portions
        if ($portionsBD->isZero()) {
            $costPerPortion = BigDecimal::zero();
        } else {
            $costPerPortion = $totalCost->dividedBy($portionsBD, 4, RoundingMode::HALF_UP);
        }

        // Food cost percentage = (cost per portion / selling price) * 100
        $foodCostPct = null;

        if ($sellingPricePerPortion !== null) {
            $sellingBD = BigDecimal::of($sellingPricePerPortion);

            if (! $sellingBD->isZero() && ! $portionsBD->isZero()) {
                $foodCostPct = $costPerPortion
                    ->dividedBy($sellingBD, 4, RoundingMode::HALF_UP)
                    ->multipliedBy(BigDecimal::of('100'))
                    ->dividedBy(BigDecimal::one(), 2, RoundingMode::HALF_UP);
            }
        }

        return [
            'total_cost' => $totalCost->dividedBy(BigDecimal::one(), 2, RoundingMode::HALF_UP)->__toString(),
            'cost_per_portion' => $costPerPortion->dividedBy(BigDecimal::one(), 2, RoundingMode::HALF_UP)->__toString(),
            'food_cost_pct' => $foodCostPct?->__toString(),
            'missing_lines' => $missingLines,
        ];
    }
}
