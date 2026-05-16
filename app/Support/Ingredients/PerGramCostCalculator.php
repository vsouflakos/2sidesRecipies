<?php

namespace App\Support\Ingredients;

use App\Models\Ingredient;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class PerGramCostCalculator
{
    /**
     * Compute the per-gram cost of an ingredient price.
     *
     * @param  BigDecimal  $amount  Money paid.
     * @param  BigDecimal  $quantity  How much was bought.
     * @param  Unit  $unit  The unit of `quantity`.
     * @param  Ingredient  $ingredient  The ingredient being priced.
     * @return BigDecimal|null Per-gram cost, or null if grams cannot be resolved (no conversion for non-weight unit).
     */
    public function perGramCost(
        BigDecimal $amount,
        BigDecimal $quantity,
        Unit $unit,
        Ingredient $ingredient
    ): ?BigDecimal {
        $grams = $this->resolveGrams($quantity, $unit, $ingredient);

        if ($grams === null) {
            return null;
        }

        return $amount->dividedBy($grams, 8, RoundingMode::HALF_UP);
    }

    /**
     * Resolve the total grams represented by the given quantity and unit.
     *
     * @return BigDecimal|null Grams, or null when no conversion exists.
     */
    private function resolveGrams(BigDecimal $quantity, Unit $unit, Ingredient $ingredient): ?BigDecimal
    {
        if ($unit->type === 'weight') {
            // base_factor is grams-per-unit (e.g., gram = 1, kilogram = 1000)
            return $quantity->multipliedBy(BigDecimal::of($unit->base_factor));
        }

        // For volume / count — look up an ingredient-specific conversion row
        $conversion = $ingredient->conversions()
            ->where('from_unit_id', $unit->id)
            ->first();

        if ($conversion === null) {
            return null;
        }

        // grams = quantity * (conversion.gram_weight / conversion.from_amount)
        $gramsPerFromAmount = BigDecimal::of($conversion->gram_weight)
            ->dividedBy(BigDecimal::of($conversion->from_amount), 10, RoundingMode::HALF_UP);

        return $quantity->multipliedBy($gramsPerFromAmount);
    }
}
