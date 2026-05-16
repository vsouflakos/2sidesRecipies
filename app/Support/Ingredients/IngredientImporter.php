<?php

namespace App\Support\Ingredients;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientConversion;
use App\Models\IngredientTranslation;
use Illuminate\Support\Facades\DB;

class IngredientImporter
{
    /** @var array<string, int>|null Allergen slug → id map; lazily loaded. */
    private ?array $allergenMap = null;

    /**
     * Upsert ingredient rows in chunks of 500.
     *
     * Each $row is an array with keys: source, source_id, category_id,
     * name_cache, data_hash, created_at, updated_at, plus the 29 nutrition columns.
     *
     * IMPORTANT: Two-pass ordering for verified-reset integrity:
     *   1. Call resetVerifiedForChangedRows($rows) BEFORE calling upsertIngredients($rows).
     *      This reads existing data_hash values, compares to new hashes, and marks rows
     *      verified=false where hashes differ — all BEFORE the upsert overwrites the hash.
     *   2. Then call upsertIngredients($rows) to write the new data.
     *
     * The `verified` column is NOT in the update list — it is handled exclusively by
     * resetVerifiedForChangedRows() so unchanged ingredients retain their verified badge.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertIngredients(array $rows): int
    {
        $updateColumns = [
            'name_cache',
            'data_hash',
            'category_id',
            'usda_fdc_id',
            // Nutrition columns
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
            'updated_at',
        ];

        $chunks = array_chunk($rows, 500);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $updateColumns): void {
                DB::table('ingredients')->upsert(
                    $chunk,
                    uniqueBy: ['source', 'source_id'],
                    update: $updateColumns,
                );
            });
        }

        return count($rows);
    }

    /**
     * Reset verified=false for rows whose data_hash has changed from the stored value.
     *
     * IMPORTANT: This must be called BEFORE upsertIngredients() so that the comparison
     * is made against the OLD stored hash. After upsert, both hashes would be equal and
     * no resets would occur.
     *
     * Two-pass process:
     *   Pass 1 (this method): read existing hashes → compare → reset verified where hash differs.
     *   Pass 2 (upsertIngredients): write the new data including the new hash.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function resetVerifiedForChangedRows(array $rows): void
    {
        // Build a map of source_id => new_data_hash for quick lookup
        $newHashesBySourceId = [];

        foreach ($rows as $row) {
            $key = $row['source'].'|'.$row['source_id'];
            $newHashesBySourceId[$key] = $row['data_hash'];
        }

        // Fetch existing hashes only for rows that will be upserted
        $sourceIds = array_column($rows, 'source_id');
        $source = $rows[0]['source'] ?? null;

        if ($source === null || empty($sourceIds)) {
            return;
        }

        $existing = DB::table('ingredients')
            ->where('source', $source)
            ->whereIn('source_id', $sourceIds)
            ->where('verified', true)
            ->get(['source_id', 'data_hash']);

        $sourceIdsToReset = [];

        foreach ($existing as $record) {
            $key = $source.'|'.$record->source_id;
            $newHash = $newHashesBySourceId[$key] ?? null;

            if ($newHash !== null && $newHash !== $record->data_hash) {
                $sourceIdsToReset[] = $record->source_id;
            }
        }

        if (! empty($sourceIdsToReset)) {
            DB::table('ingredients')
                ->where('source', $source)
                ->whereIn('source_id', $sourceIdsToReset)
                ->update([
                    'verified' => false,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
        }
    }

    /**
     * Add a translation if the (ingredient_id, locale) pair does not already exist.
     */
    public function syncTranslation(int $ingredientId, string $locale, string $name): void
    {
        IngredientTranslation::firstOrCreate(
            ['ingredient_id' => $ingredientId, 'locale' => $locale],
            ['name' => $name],
        );
    }

    /**
     * Sync allergen pivot rows from slug lists.
     *
     * $contains and $mayContain are arrays of allergen slugs. If a slug appears in
     * both lists, `contains` wins (stricter state). Uses sync() so imports own the
     * official allergen assignments for official ingredients.
     *
     * @param  list<string>  $contains
     * @param  list<string>  $mayContain
     */
    public function syncAllergens(int $ingredientId, array $contains, array $mayContain): void
    {
        if (empty($contains) && empty($mayContain)) {
            return;
        }

        $allergenMap = $this->getAllergenMap();
        $pivotData = [];

        foreach ($mayContain as $slug) {
            if (isset($allergenMap[$slug])) {
                $pivotData[$allergenMap[$slug]] = ['state' => 'may_contain'];
            }
        }

        // contains wins over may_contain when both present
        foreach ($contains as $slug) {
            if (isset($allergenMap[$slug])) {
                $pivotData[$allergenMap[$slug]] = ['state' => 'contains'];
            }
        }

        if (! empty($pivotData)) {
            $ingredient = Ingredient::find($ingredientId);
            $ingredient?->allergens()->sync($pivotData);
        }
    }

    /**
     * Insert a conversion row idempotently keyed on (ingredient_id, from_unit_id, source_ref).
     */
    public function syncConversion(
        int $ingredientId,
        float $fromAmount,
        int $fromUnitId,
        float $gramWeight,
        string $source,
        ?string $sourceRef,
        ?string $modifier,
    ): void {
        IngredientConversion::firstOrCreate(
            [
                'ingredient_id' => $ingredientId,
                'from_unit_id' => $fromUnitId,
                'source_ref' => $sourceRef,
            ],
            [
                'from_amount' => $fromAmount,
                'gram_weight' => $gramWeight,
                'source' => $source,
                'modifier' => $modifier,
            ],
        );
    }

    /**
     * Compute the md5 data hash from a nutrition payload array.
     *
     * @param  array<string, mixed>  $nutritionValues
     */
    public function dataHash(array $nutritionValues): string
    {
        return md5(serialize($nutritionValues));
    }

    /**
     * Get or lazily load the allergen slug → id map.
     *
     * @return array<string, int>
     */
    private function getAllergenMap(): array
    {
        if ($this->allergenMap === null) {
            $this->allergenMap = Allergen::pluck('id', 'slug')->all();
        }

        return $this->allergenMap;
    }
}
