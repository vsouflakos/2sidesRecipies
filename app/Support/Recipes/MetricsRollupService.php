<?php

namespace App\Support\Recipes;

use App\Models\RecipeIngredientLine;
use App\Models\RecipeVersion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;

class MetricsRollupService
{
    /**
     * Compute the scaled metric contribution of a sub-recipe line.
     *
     * The sub-recipe version's cached metrics are scaled by:
     *   scaleFactor = line.quantity_g / version.yield_g
     *
     * Pitfall 4: yield_g must be non-null. An exception is thrown if it is null,
     * because a null yield_g silently defaults to scale=1 and doubles metrics.
     *
     * @param  RecipeIngredientLine  $line  The sub-recipe ingredient line (must be a sub-recipe line).
     * @param  RecipeVersion  $version  The pinned RecipeVersion for the sub-recipe.
     * @return array{energy_kcal: string, protein_g: string, cost: string}
     *
     * @throws RuntimeException If yield_g is null on the version.
     */
    public function computeForLine(RecipeIngredientLine $line, RecipeVersion $version): array
    {
        if ($version->yield_g === null) {
            throw new RuntimeException(
                "RecipeVersion [{$version->id}] has a null yield_g. Cannot compute sub-recipe scale factor."
            );
        }

        $lineGrams = BigDecimal::of($line->quantity_g);
        $yieldGrams = BigDecimal::of($version->yield_g);

        $scaleFactor = $lineGrams->dividedBy($yieldGrams, 10, RoundingMode::HALF_UP);

        $cachedNutrition = $version->cached_nutrition_json ?? [];
        $total = $cachedNutrition['total'] ?? [];

        $energyKcal = isset($total['energy_kcal'])
            ? BigDecimal::of($total['energy_kcal'])->multipliedBy($scaleFactor)->toScale(4, RoundingMode::HALF_UP)->__toString()
            : '0.0000';

        $proteinG = isset($total['protein_g'])
            ? BigDecimal::of($total['protein_g'])->multipliedBy($scaleFactor)->toScale(4, RoundingMode::HALF_UP)->__toString()
            : '0.0000';

        // Cost: line_grams × cached_cost_per_gram
        $costPerGram = BigDecimal::of($version->cached_cost_per_gram ?? '0');
        $cost = $lineGrams->multipliedBy($costPerGram)->toScale(8, RoundingMode::HALF_UP)->__toString();

        return [
            'energy_kcal' => $energyKcal,
            'protein_g' => $proteinG,
            'cost' => $cost,
        ];
    }
}
