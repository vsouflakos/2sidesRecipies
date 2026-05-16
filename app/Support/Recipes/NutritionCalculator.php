<?php

namespace App\Support\Recipes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class NutritionCalculator
{
    /**
     * All nutritional keys tracked from the Ingredient model.
     *
     * @var list<string>
     */
    private const NUTRIENT_KEYS = [
        'energy_kcal',
        'protein_g',
        'fat_g',
        'saturated_fat_g',
        'monounsaturated_fat_g',
        'polyunsaturated_fat_g',
        'carbs_g',
        'sugars_g',
        'starch_g',
        'fibre_g',
        'sodium_mg',
        'calcium_mg',
        'iron_mg',
        'magnesium_mg',
        'phosphorus_mg',
        'potassium_mg',
        'zinc_mg',
        'vitamin_a_ug',
        'vitamin_b1_mg',
        'vitamin_b2_mg',
        'vitamin_b3_mg',
        'vitamin_b6_mg',
        'vitamin_b9_ug',
        'vitamin_b12_ug',
        'vitamin_c_mg',
        'vitamin_d_ug',
        'vitamin_e_mg',
        'vitamin_k_ug',
        'cholesterol_mg',
    ];

    /**
     * Compute nutrition totals, per-portion values, and per-100g values.
     *
     * Line shape: { quantity_g: string, energy_kcal: ?string, protein_g: ?string, ... }
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{per_portion: array<string, string>, per_100g: array<string, string>, totals: array<string, string>, missing_lines: list<string>}
     */
    public function compute(array $lines, int|string $portions = 1, ?string $totalYieldG = null): array
    {
        $portionsBD = BigDecimal::of((string) $portions);
        $hundred = BigDecimal::of('100');

        // Initialise totals to zero for each nutrient key
        $totals = [];
        foreach (self::NUTRIENT_KEYS as $key) {
            $totals[$key] = BigDecimal::zero();
        }

        $missingLines = [];

        foreach ($lines as $line) {
            $quantityG = BigDecimal::of((string) $line['quantity_g']);

            // Track lines missing energy data
            if (! isset($line['energy_kcal']) || $line['energy_kcal'] === null) {
                $name = $line['name'] ?? $line['ingredient_name'] ?? 'unknown';
                $missingLines[] = (string) $name;
            }

            foreach (self::NUTRIENT_KEYS as $key) {
                if (! isset($line[$key]) || $line[$key] === null) {
                    continue;
                }

                // contribution = value_per_100g * quantity_g / 100
                // Use scale 10 for intermediate to avoid per-line rounding drift.
                $valuePer100g = BigDecimal::of((string) $line[$key]);
                $contribution = $valuePer100g
                    ->multipliedBy($quantityG)
                    ->dividedBy($hundred, 10, RoundingMode::HALF_UP);

                $totals[$key] = $totals[$key]->plus($contribution);
            }
        }

        // Guard against zero portions
        $perPortion = [];
        $per100g = [];

        $portionsIsZero = $portionsBD->isZero();

        foreach (self::NUTRIENT_KEYS as $key) {
            if ($portionsIsZero) {
                $perPortion[$key] = '0.0000';
            } else {
                $perPortion[$key] = $totals[$key]
                    ->dividedBy($portionsBD, 4, RoundingMode::HALF_UP)
                    ->__toString();
            }
        }

        if ($totalYieldG !== null) {
            $yieldBD = BigDecimal::of($totalYieldG);
            $yieldIsZero = $yieldBD->isZero();

            foreach (self::NUTRIENT_KEYS as $key) {
                if ($yieldIsZero) {
                    $per100g[$key] = '0.0000';
                } else {
                    $per100g[$key] = $totals[$key]
                        ->dividedBy($yieldBD, 4, RoundingMode::HALF_UP)
                        ->multipliedBy($hundred)
                        ->__toString();
                }
            }
        }

        $totalsStrings = [];
        foreach (self::NUTRIENT_KEYS as $key) {
            $totalsStrings[$key] = $totals[$key]->__toString();
        }

        return [
            'totals' => $totalsStrings,
            'per_portion' => $perPortion,
            'per_100g' => $per100g,
            'missing_lines' => $missingLines,
        ];
    }
}
