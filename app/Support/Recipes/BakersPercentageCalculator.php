<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class BakersPercentageCalculator
{
    /**
     * Compute baker's percentages and hydration ratio against flour base weight.
     *
     * Line shape: { quantity_g: string, is_flour_base: bool, ingredient_name: string, is_water?: bool }
     * Returns null when no flour lines are present (not applicable).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{flour_base_g: string, percentages: array<string, string>, hydration_pct: string|null}|null
     */
    public function compute(array $lines): ?array
    {
        $hundred = BigDecimal::of('100');

        // Sum all flour base lines
        $flourBase = BigDecimal::zero();
        $waterTotal = BigDecimal::zero();

        foreach ($lines as $line) {
            if (! empty($line['is_flour_base'])) {
                $flourBase = $flourBase->plus(BigDecimal::of((string) $line['quantity_g']));
            }
        }

        // If no flour base, baker's percentages are not applicable
        if ($flourBase->isZero()) {
            return null;
        }

        $percentages = [];

        foreach ($lines as $line) {
            $quantityG = BigDecimal::of((string) $line['quantity_g']);
            $name = (string) $line['ingredient_name'];

            $pct = $quantityG
                ->dividedBy($flourBase, 4, RoundingMode::HALF_UP)
                ->multipliedBy($hundred)
                ->dividedBy(BigDecimal::one(), 2, RoundingMode::HALF_UP);

            $percentages[$name] = $pct->__toString();

            if (! empty($line['is_water'])) {
                $waterTotal = $waterTotal->plus($quantityG);
            }
        }

        // Hydration = total water / flour base * 100
        $hydrationPct = $waterTotal->isZero()
            ? null
            : $waterTotal
                ->dividedBy($flourBase, 4, RoundingMode::HALF_UP)
                ->multipliedBy($hundred)
                ->dividedBy(BigDecimal::one(), 2, RoundingMode::HALF_UP)
                ->__toString();

        return [
            'flour_base_g' => $flourBase->dividedBy(BigDecimal::one(), 6, RoundingMode::HALF_UP)->__toString(),
            'percentages' => $percentages,
            'hydration_pct' => $hydrationPct,
        ];
    }
}
