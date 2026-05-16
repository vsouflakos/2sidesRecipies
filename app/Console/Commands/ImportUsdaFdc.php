<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Support\Ingredients\IngredientImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportUsdaFdc extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ingredients:import-usda
                            {--food-file= : Path to food.csv}
                            {--nutrient-file= : Path to nutrient.csv}
                            {--food-nutrient-file= : Path to food_nutrient.csv}
                            {--portion-file= : Path to food_portion.csv}
                            {--measure-unit-file= : Path to measure_unit.csv}
                            {--download : Fetch the SR Legacy dataset on demand}';

    /**
     * The console command description.
     */
    protected $description = 'Import USDA FoodData Central SR Legacy dataset into the ingredients library.';

    /**
     * USDA SR Legacy download URL.
     */
    private const DOWNLOAD_URL = 'https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_sr_legacy_food_csv_2018-04.zip';

    /**
     * Map USDA nutrient ids to schema nutrition columns.
     *
     * @var array<int, string>
     */
    private const NUTRIENT_MAP = [
        1008 => 'energy_kcal',
        1003 => 'protein_g',
        1004 => 'fat_g',
        1005 => 'carbs_g',
        1079 => 'fibre_g',
        2000 => 'sugars_g',
        1093 => 'sodium_mg',
        1087 => 'calcium_mg',
        1089 => 'iron_mg',
        1090 => 'magnesium_mg',
        1091 => 'phosphorus_mg',
        1092 => 'potassium_mg',
        1095 => 'zinc_mg',
        1106 => 'vitamin_a_ug',
        1165 => 'vitamin_b1_mg',
        1166 => 'vitamin_b2_mg',
        1167 => 'vitamin_b3_mg',
        1175 => 'vitamin_b6_mg',
        1177 => 'vitamin_b9_ug',
        1178 => 'vitamin_b12_ug',
        1162 => 'vitamin_c_mg',
        1114 => 'vitamin_d_ug',
        1109 => 'vitamin_e_mg',
        1185 => 'vitamin_k_ug',
        1253 => 'cholesterol_mg',
        1258 => 'saturated_fat_g',
        1292 => 'monounsaturated_fat_g',
        1293 => 'polyunsaturated_fat_g',
    ];

    /**
     * Map USDA food category ids to seeded ingredient category slugs.
     *
     * @var array<int, string>
     */
    private const CATEGORY_MAP = [
        1 => 'dairy-eggs',         // Dairy and Egg Products
        2 => 'herbs-spices',       // Spices and Herbs
        3 => 'prepared-convenience-foods', // Baby Foods
        4 => 'oils-fats-condiments', // Fats and Oils
        5 => 'meat-poultry',       // Poultry Products
        6 => 'fish-seafood',       // Soups, Sauces, and Gravies
        7 => 'other-uncategorised', // Sausages and Luncheon Meats
        8 => 'prepared-convenience-foods', // Breakfast Cereals
        9 => 'fruits',             // Fruits and Fruit Juices
        10 => 'grains-starches',   // Pork Products
        11 => 'vegetables',        // Vegetables and Vegetable Products
        12 => 'sweeteners-sugar-products', // Nut and Seed Products
        13 => 'sweeteners-sugar-products', // Sweets
        14 => 'oils-fats-condiments',      // Beverages
        15 => 'fish-seafood',      // Finfish and Shellfish Products
        16 => 'meat-poultry',      // Legumes and Legume Products
        17 => 'grains-starches',   // Lamb, Veal, and Game Products
        18 => 'prepared-convenience-foods', // Baked Products
        19 => 'meat-poultry',      // Beef Products
        20 => 'grains-starches',   // Cereals, Grains and Pasta
        21 => 'prepared-convenience-foods', // Fast Foods
        22 => 'prepared-convenience-foods', // Meals, Entrees, and Side Dishes
        23 => 'nuts-seeds',        // Snacks
        24 => 'meat-poultry',      // American Indian/Alaska Native Foods
        25 => 'other-uncategorised', // Restaurant Foods
        26 => 'grains-starches',   // Grain Products
    ];

    /**
     * Execute the console command.
     */
    public function handle(IngredientImporter $importer): int
    {
        [$foodFile, $nutrientFile, $foodNutrientFile, $portionFile, $measureUnitFile] = $this->resolveFiles();

        if ($foodFile === null) {
            $this->error('No input files provided. Pass --food-file, --nutrient-file, --food-nutrient-file, --portion-file, and --measure-unit-file, or use --download to fetch the SR Legacy dataset.');

            return self::FAILURE;
        }

        $this->info('Loading USDA nutrient map…');
        $nutrientIdToColumn = $this->loadNutrientMap($nutrientFile);

        $this->info('Loading USDA food nutrients…');
        $nutritionByFdcId = $this->loadFoodNutrients($foodNutrientFile, $nutrientIdToColumn);

        $this->info('Loading USDA measure units…');
        $measureUnits = $this->loadMeasureUnits($measureUnitFile);

        $defaultCategoryId = $this->resolveDefaultCategoryId();
        $categoryCache = [];

        $this->info('Parsing USDA foods…');
        $rows = $this->parseFoods($foodFile, $nutritionByFdcId, $importer, $defaultCategoryId, $categoryCache);

        if (empty($rows)) {
            $this->warn('No food rows found in the food CSV.');

            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d ingredients. Upserting…', count($rows)));

        // Two-pass: reset verified BEFORE upsert.
        $upsertRows = array_map(function (array $row): array {
            unset($row['_fdc_id'], $row['_name_en']);

            return $row;
        }, $rows);

        $importer->resetVerifiedForChangedRows($upsertRows);

        $count = 0;
        $chunks = array_chunk($upsertRows, 500);

        $this->withProgressBar($chunks, function (array $chunk) use ($importer, &$count): void {
            $count += $importer->upsertIngredients($chunk);
        });

        $this->newLine();

        // Sync translations.
        $this->syncTranslations($rows, $importer);

        // Sync food_portion conversions.
        $this->info('Syncing food portion conversions…');
        $this->syncPortions($portionFile, $measureUnits, $importer);

        $this->info("USDA import complete: {$count} ingredients.");

        return self::SUCCESS;
    }

    /**
     * Resolve file paths from options or download.
     *
     * @return array<int, string|null>
     */
    private function resolveFiles(): array
    {
        if ($this->option('download')) {
            return $this->downloadAndExtract();
        }

        $foodFile = $this->option('food-file');
        $nutrientFile = $this->option('nutrient-file');
        $foodNutrientFile = $this->option('food-nutrient-file');
        $portionFile = $this->option('portion-file');
        $measureUnitFile = $this->option('measure-unit-file');

        return [$foodFile, $nutrientFile, $foodNutrientFile, $portionFile, $measureUnitFile];
    }

    /**
     * Download and extract the USDA SR Legacy zip to a temp directory.
     *
     * @return array<int, string|null>
     */
    private function downloadAndExtract(): array
    {
        $tmpDir = storage_path('app/tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir.'/usda-sr-legacy.zip';
        $extractDir = $tmpDir.'/usda-sr-legacy';

        $this->info('Downloading USDA SR Legacy dataset…');
        Http::withOptions(['stream' => true])->sink($zipPath)->get(self::DOWNLOAD_URL);

        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->error('Failed to open downloaded zip file.');

            return [null, null, null, null, null];
        }

        $zip->extractTo($extractDir);
        $zip->close();
        @unlink($zipPath);

        return [
            $extractDir.'/food.csv',
            $extractDir.'/nutrient.csv',
            $extractDir.'/food_nutrient.csv',
            $extractDir.'/food_portion.csv',
            $extractDir.'/measure_unit.csv',
        ];
    }

    /**
     * Load the USDA nutrient id → schema column map from nutrient.csv.
     *
     * Supplements the hardcoded NUTRIENT_MAP with any nutrient ids found in
     * the CSV whose name/unit_name combination can be matched.
     *
     * @return array<int, string> nutrient_id → column name
     */
    private function loadNutrientMap(string $nutrientFile): array
    {
        $map = self::NUTRIENT_MAP;

        if (! file_exists($nutrientFile)) {
            return $map;
        }

        $handle = fopen($nutrientFile, 'r');

        if ($handle === false) {
            return $map;
        }

        // Skip header row.
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $nutrientId = (int) $row[0];

            // If we already have a mapping for this id, keep the hardcoded one.
            if (isset($map[$nutrientId])) {
                continue;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Load food_nutrient.csv into a map: fdc_id → [column => value].
     *
     * @param  array<int, string>  $nutrientIdToColumn
     * @return array<int, array<string, float|null>>
     */
    private function loadFoodNutrients(string $foodNutrientFile, array $nutrientIdToColumn): array
    {
        $nutritionByFdcId = [];

        if (! file_exists($foodNutrientFile)) {
            return $nutritionByFdcId;
        }

        $handle = fopen($foodNutrientFile, 'r');

        if ($handle === false) {
            return $nutritionByFdcId;
        }

        // Skip header row.
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            // Columns: id, fdc_id, nutrient_id, amount
            if (count($row) < 4) {
                continue;
            }

            $fdcId = (int) $row[1];
            $nutrientId = (int) $row[2];
            $amount = $row[3] !== '' ? (float) $row[3] : null;

            $column = $nutrientIdToColumn[$nutrientId] ?? null;

            if ($column === null) {
                continue;
            }

            if (! isset($nutritionByFdcId[$fdcId])) {
                $nutritionByFdcId[$fdcId] = [];
            }

            $nutritionByFdcId[$fdcId][$column] = $amount;
        }

        fclose($handle);

        return $nutritionByFdcId;
    }

    /**
     * Load measure_unit.csv into a map: measure_unit_id → name.
     *
     * @return array<int, string>
     */
    private function loadMeasureUnits(string $measureUnitFile): array
    {
        $units = [];

        if (! file_exists($measureUnitFile)) {
            return $units;
        }

        $handle = fopen($measureUnitFile, 'r');

        if ($handle === false) {
            return $units;
        }

        fgetcsv($handle); // Skip header.

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $units[(int) $row[0]] = trim($row[1]);
        }

        fclose($handle);

        return $units;
    }

    /**
     * Parse food.csv and build ingredient rows.
     *
     * @param  array<int, array<string, float|null>>  $nutritionByFdcId
     * @param  array<string, int>  $categoryCache
     * @return array<int, array<string, mixed>>
     */
    private function parseFoods(
        string $foodFile,
        array $nutritionByFdcId,
        IngredientImporter $importer,
        int $defaultCategoryId,
        array &$categoryCache,
    ): array {
        $rows = [];

        if (! file_exists($foodFile)) {
            return $rows;
        }

        $handle = fopen($foodFile, 'r');

        if ($handle === false) {
            return $rows;
        }

        fgetcsv($handle); // Skip header: fdc_id, description, food_category_id

        $now = now()->toDateTimeString();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $fdcId = (int) $row[0];
            $description = trim($row[1]);
            $foodCategoryId = isset($row[2]) ? (int) $row[2] : 0;

            $nutrition = $nutritionByFdcId[$fdcId] ?? [];
            $dataHash = $importer->dataHash($nutrition);
            $categoryId = $this->resolveCategoryId($foodCategoryId, $defaultCategoryId, $categoryCache);

            $allNutritionColumns = array_fill_keys([
                'energy_kcal', 'protein_g', 'fat_g', 'saturated_fat_g', 'monounsaturated_fat_g',
                'polyunsaturated_fat_g', 'carbs_g', 'sugars_g', 'starch_g', 'fibre_g',
                'sodium_mg', 'calcium_mg', 'iron_mg', 'magnesium_mg', 'phosphorus_mg',
                'potassium_mg', 'zinc_mg', 'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg',
                'vitamin_b3_mg', 'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug',
                'vitamin_c_mg', 'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug', 'cholesterol_mg',
            ], null);

            $rows[] = array_merge($allNutritionColumns, $nutrition, [
                'source' => 'usda',
                'source_id' => (string) $fdcId,
                'usda_fdc_id' => $fdcId,
                'name_cache' => $description,
                'category_id' => $categoryId,
                'data_hash' => $dataHash,
                'verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                // Private keys stripped before upsert.
                '_fdc_id' => $fdcId,
                '_name_en' => $description,
            ]);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Sync English translations for all upserted USDA ingredients.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTranslations(array $rows, IngredientImporter $importer): void
    {
        $sourceIds = array_column($rows, 'source_id');

        $idsBySourceId = Ingredient::where('source', 'usda')
            ->whereIn('source_id', $sourceIds)
            ->pluck('id', 'source_id')
            ->all();

        foreach ($rows as $row) {
            $id = $idsBySourceId[$row['source_id']] ?? null;

            if ($id === null) {
                continue;
            }

            $importer->syncTranslation($id, 'en', $row['_name_en']);
        }
    }

    /**
     * Parse food_portion.csv and sync ingredient_conversions rows.
     *
     * For each portion row, resolves the ingredient by usda_fdc_id and the
     * unit by matching the measure-unit name to a Phase 1 units row.
     * Skips unmapped measure units.
     *
     * @param  array<int, string>  $measureUnits
     */
    private function syncPortions(
        string $portionFile,
        array $measureUnits,
        IngredientImporter $importer,
    ): void {
        if (! file_exists($portionFile)) {
            return;
        }

        $handle = fopen($portionFile, 'r');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle); // Skip header.

        // Build ingredient id cache: fdc_id → ingredient.id
        $ingredientIdByFdcId = [];
        $unitIdByName = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Columns: id, fdc_id, seq_num, amount, measure_unit_id, portion_description, modifier, gram_weight
            if (count($row) < 8) {
                continue;
            }

            $portionId = (int) $row[0];
            $fdcId = (int) $row[1];
            $seqNum = (int) $row[2];
            $amount = $row[3] !== '' ? (float) $row[3] : 1.0;
            $measureUnitId = (int) $row[4];
            $modifier = trim($row[6]) !== '' ? trim($row[6]) : null;
            $gramWeight = $row[7] !== '' ? (float) $row[7] : null;

            if ($gramWeight === null || $gramWeight <= 0) {
                continue;
            }

            // Resolve ingredient id.
            if (! isset($ingredientIdByFdcId[$fdcId])) {
                $ingredient = Ingredient::where('usda_fdc_id', $fdcId)->first();
                $ingredientIdByFdcId[$fdcId] = $ingredient?->id;
            }

            $ingredientId = $ingredientIdByFdcId[$fdcId];

            if ($ingredientId === null) {
                continue;
            }

            // Resolve unit id by measure unit name.
            $measureUnitName = strtolower(trim($measureUnits[$measureUnitId] ?? ''));

            if (! isset($unitIdByName[$measureUnitName])) {
                $unit = Unit::where('name', $measureUnitName)
                    ->orWhere('symbol', $measureUnitName)
                    ->first();
                $unitIdByName[$measureUnitName] = $unit?->id;
            }

            $fromUnitId = $unitIdByName[$measureUnitName];

            if ($fromUnitId === null) {
                continue; // Skip unmapped units.
            }

            $sourceRef = "fdc:{$fdcId}:{$seqNum}";

            $importer->syncConversion(
                $ingredientId,
                $amount,
                $fromUnitId,
                $gramWeight,
                'usda',
                $sourceRef,
                $modifier,
            );
        }

        fclose($handle);
    }

    /**
     * Resolve category id from a USDA food category id.
     *
     * @param  array<int, int>  $cache
     */
    private function resolveCategoryId(int $usdaCategoryId, int $defaultId, array &$cache): int
    {
        if (isset($cache[$usdaCategoryId])) {
            return $cache[$usdaCategoryId];
        }

        $slug = self::CATEGORY_MAP[$usdaCategoryId] ?? null;

        if ($slug !== null) {
            $category = IngredientCategory::where('slug', $slug)->whereNull('parent_id')->first();

            if ($category) {
                $cache[$usdaCategoryId] = $category->id;

                return $category->id;
            }
        }

        $cache[$usdaCategoryId] = $defaultId;

        return $defaultId;
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
