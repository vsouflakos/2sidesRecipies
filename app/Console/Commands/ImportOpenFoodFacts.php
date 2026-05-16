<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Support\Ingredients\IngredientImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportOpenFoodFacts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ingredients:import-off
                            {--source-file= : Path to an OFF tab-separated CSV file}
                            {--download : Fetch the full OFF CSV gz on demand}
                            {--country=Greece : Filter to products available in this country}';

    /**
     * The console command description.
     */
    protected $description = 'Import Open Food Facts products (Greek enrichment and Greek-market products) into the ingredients library.';

    /**
     * OFF bulk CSV download URL (gzip compressed, tab-separated).
     */
    private const DOWNLOAD_URL = 'https://static.openfoodfacts.org/data/en.openfoodfacts.org.products.csv.gz';

    /**
     * Execute the console command.
     */
    public function handle(IngredientImporter $importer): int
    {
        $country = (string) $this->option('country');
        $sourceFile = $this->option('source-file');

        if ($this->option('download')) {
            $handle = $this->openDownloadStream();

            if ($handle === null) {
                return self::FAILURE;
            }
        } elseif ($sourceFile !== null && file_exists($sourceFile)) {
            $handle = fopen($sourceFile, 'r');

            if ($handle === false) {
                $this->error("Cannot open source file: {$sourceFile}");

                return self::FAILURE;
            }
        } else {
            $this->error('No source provided. Pass --source-file or use --download to fetch from Open Food Facts.');

            return self::FAILURE;
        }

        $this->info("Importing Open Food Facts products (country filter: {$country})…");

        $stats = $this->processStream($handle, $country, $importer);

        fclose($handle);

        $this->newLine();
        $this->info("OFF import complete: {$stats['processed']} products processed, {$stats['matched']} matched, {$stats['inserted']} new.");

        return self::SUCCESS;
    }

    /**
     * Open a streaming handle to the OFF bulk CSV gz download.
     *
     * Downloads the file to a temp path with Http::sink() then opens it for
     * reading. Does NOT buffer the full response in memory (RESEARCH Pitfall 3).
     *
     * @return resource|null
     */
    private function openDownloadStream()
    {
        $tmpDir = storage_path('app/tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpPath = $tmpDir.'/off-import.csv.gz';

        $this->info('Downloading Open Food Facts CSV (this may take a while)…');
        Http::sink($tmpPath)->get(self::DOWNLOAD_URL);

        $handle = fopen('compress.zlib://'.$tmpPath, 'r');

        if ($handle === false) {
            $this->error('Failed to open OFF CSV gz file.');
            @unlink($tmpPath);

            return null;
        }

        return $handle;
    }

    /**
     * Process the OFF CSV stream row by row.
     *
     * @param  resource  $handle
     * @return array{processed: int, matched: int, inserted: int}
     */
    private function processStream($handle, string $country, IngredientImporter $importer): array
    {
        // Read and parse the header row.
        $headerRaw = fgets($handle);

        if ($headerRaw === false) {
            return ['processed' => 0, 'matched' => 0, 'inserted' => 0];
        }

        // OFF uses tab-separated values.
        $headers = str_getcsv(rtrim($headerRaw, "\r\n"), "\t");
        $headers = array_map('trim', $headers);

        $columnIndex = array_flip($headers);

        $defaultCategoryId = $this->resolveDefaultCategoryId();
        $existingNameCache = $this->buildNameCache();

        $stats = ['processed' => 0, 'matched' => 0, 'inserted' => 0];

        while (($line = fgets($handle)) !== false) {
            $fields = str_getcsv(rtrim($line, "\r\n"), "\t");

            // Pad to header count to avoid undefined index errors.
            while (count($fields) < count($headers)) {
                $fields[] = '';
            }

            $row = [];

            foreach ($headers as $i => $header) {
                $row[$header] = $fields[$i] ?? '';
            }

            // Filter to the requested country.
            $countriesEn = $row['countries_en'] ?? '';

            if (stripos($countriesEn, $country) === false) {
                continue;
            }

            $stats['processed']++;

            $code = trim($row['code'] ?? '');
            $productName = trim($row['product_name'] ?? '');
            $productNameEl = trim($row['product_name_el'] ?? '');
            $productNameEn = trim($row['product_name_en'] ?? $productName);
            $allergensEn = trim($row['allergens_en'] ?? '');
            $tracesEn = trim($row['traces_en'] ?? '');

            $nutrition = $this->extractNutrition($row);

            // Try to match an existing ingredient by normalised English name.
            $normalised = strtolower(trim($productNameEn));
            $matchedId = $existingNameCache[$normalised] ?? null;

            if ($matchedId !== null) {
                // Enrichment path: add Greek translation and allergens to matched ingredient.
                if ($productNameEl !== '') {
                    $importer->syncTranslation($matchedId, 'el', $productNameEl);
                }

                $stats['matched']++;
            } else {
                // Insert path: new ingredient with source='off'.
                if ($productNameEn === '' && $productName === '') {
                    continue;
                }

                $nameCache = $productNameEn !== '' ? $productNameEn : $productName;
                $dataHash = $importer->dataHash($nutrition);
                $now = now()->toDateTimeString();

                $upsertRow = array_merge([
                    'source' => 'off',
                    'source_id' => $code !== '' ? $code : 'off-'.md5($productName),
                    'name_cache' => $nameCache,
                    'category_id' => $defaultCategoryId,
                    'data_hash' => $dataHash,
                    'verified' => false,
                    'verified_by' => null,
                    'verified_at' => null,
                    'user_id' => null,
                    'usda_fdc_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $this->nullNutritionColumns(), $nutrition);

                // Reset verified for changed rows (single row, two-pass).
                $importer->resetVerifiedForChangedRows([$upsertRow]);
                $importer->upsertIngredients([$upsertRow]);

                // Fetch the ingredient id after upsert.
                $ingredient = Ingredient::where('source', 'off')
                    ->where('source_id', $upsertRow['source_id'])
                    ->first();

                if ($ingredient) {
                    $importer->syncTranslation($ingredient->id, 'en', $nameCache);

                    if ($productNameEl !== '') {
                        $importer->syncTranslation($ingredient->id, 'el', $productNameEl);
                    }

                    // Update name cache for future rows in the same stream.
                    $existingNameCache[strtolower(trim($nameCache))] = $ingredient->id;
                    $matchedId = $ingredient->id;
                }

                $stats['inserted']++;
            }

            // Sync allergens for both matched and inserted ingredients.
            if ($matchedId !== null && ($allergensEn !== '' || $tracesEn !== '')) {
                $contains = $this->parseAllergenTags($allergensEn);
                $mayContain = $this->parseAllergenTags($tracesEn);

                $importer->syncAllergens($matchedId, $contains, $mayContain);
            }
        }

        return $stats;
    }

    /**
     * Build a normalised English name → ingredient id map for matching.
     *
     * Used for cross-source matching: OFF products are matched to existing
     * CIQUAL/USDA ingredients by their normalised English name.
     *
     * @return array<string, int>
     */
    private function buildNameCache(): array
    {
        $cache = [];

        Ingredient::whereIn('source', ['ciqual', 'usda'])
            ->select(['id', 'name_cache'])
            ->chunk(500, function ($ingredients) use (&$cache): void {
                foreach ($ingredients as $ingredient) {
                    $key = strtolower(trim($ingredient->name_cache));

                    if ($key !== '') {
                        $cache[$key] = $ingredient->id;
                    }
                }
            });

        return $cache;
    }

    /**
     * Extract nutrition values from an OFF row using the *_100g column names.
     *
     * @param  array<string, string>  $row
     * @return array<string, float|null>
     */
    private function extractNutrition(array $row): array
    {
        $mapping = [
            'energy-kcal_100g' => 'energy_kcal',
            'proteins_100g' => 'protein_g',
            'fat_100g' => 'fat_g',
            'carbohydrates_100g' => 'carbs_g',
            'sugars_100g' => 'sugars_g',
            'fiber_100g' => 'fibre_g',
            'sodium_100g' => 'sodium_mg',  // OFF is in g/100g; convert to mg
        ];

        $nutrition = [];

        foreach ($mapping as $offColumn => $schemaColumn) {
            $value = $row[$offColumn] ?? '';

            if ($value !== '' && is_numeric($value)) {
                $float = (float) $value;

                // OFF sodium is in g/100g; schema stores mg/100g.
                if ($offColumn === 'sodium_100g') {
                    $float = $float * 1000;
                }

                $nutrition[$schemaColumn] = $float;
            } else {
                $nutrition[$schemaColumn] = null;
            }
        }

        return $nutrition;
    }

    /**
     * Parse allergen tag strings (e.g., "en:gluten,en:milk") into slug lists.
     *
     * Strips the language prefix ("en:") and returns plain slug strings.
     *
     * @return list<string>
     */
    private function parseAllergenTags(string $tagsString): array
    {
        if ($tagsString === '') {
            return [];
        }

        $slugs = [];

        foreach (explode(',', $tagsString) as $tag) {
            $tag = trim($tag);

            // Strip language prefix (e.g., "en:gluten" → "gluten").
            if (str_contains($tag, ':')) {
                $tag = substr($tag, strpos($tag, ':') + 1);
            }

            $tag = trim($tag);

            if ($tag !== '') {
                $slugs[] = $tag;
            }
        }

        return $slugs;
    }

    /**
     * Return an array of all 29 nutrition columns set to null (schema defaults).
     *
     * @return array<string, null>
     */
    private function nullNutritionColumns(): array
    {
        return array_fill_keys([
            'energy_kcal', 'protein_g', 'fat_g', 'saturated_fat_g', 'monounsaturated_fat_g',
            'polyunsaturated_fat_g', 'carbs_g', 'sugars_g', 'starch_g', 'fibre_g',
            'sodium_mg', 'calcium_mg', 'iron_mg', 'magnesium_mg', 'phosphorus_mg',
            'potassium_mg', 'zinc_mg', 'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg',
            'vitamin_b3_mg', 'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug',
            'vitamin_c_mg', 'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug', 'cholesterol_mg',
        ], null);
    }

    /**
     * Resolve the "Other / Uncategorised" category id as the fallback.
     */
    private function resolveDefaultCategoryId(): int
    {
        $category = IngredientCategory::where('slug', 'other-uncategorised')
            ->whereNull('parent_id')
            ->first();

        return $category?->id ?? 1;
    }
}
