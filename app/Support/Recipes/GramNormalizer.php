<?php

namespace App\Support\Recipes;

use App\Models\IngredientConversion;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class GramNormalizer
{
    /**
     * Resolve the total grams for a given quantity and unit.
     *
     * @return BigDecimal|null Grams, or null when unit is volume/count with no ingredient conversion.
     */
    public function normalize(BigDecimal $quantity, Unit $unit, ?int $ingredientId): ?BigDecimal
    {
        if ($unit->type === 'weight') {
            // base_factor is grams-per-unit (e.g., gram = 1, kilogram = 1000)
            return $quantity->multipliedBy(BigDecimal::of($unit->base_factor));
        }

        if ($ingredientId === null) {
            return null;
        }

        // For volume / count — look up an ingredient-specific conversion row
        $conversion = IngredientConversion::where('ingredient_id', $ingredientId)
            ->where('from_unit_id', $unit->id)
            ->first();

        if ($conversion === null) {
            return null;
        }

        // grams = quantity * (gram_weight / from_amount)
        $gramsPerFromAmount = BigDecimal::of($conversion->gram_weight)
            ->dividedBy(BigDecimal::of($conversion->from_amount), 10, RoundingMode::HALF_UP);

        return $quantity->multipliedBy($gramsPerFromAmount);
    }
}
