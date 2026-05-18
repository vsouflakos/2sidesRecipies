<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Nutrient;
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
                            {--dir=* : Dataset directory holding the FDC CSVs (repeatable); relative names resolve under storage/app/private}
                            {--food-file= : Path to food.csv}
                            {--nutrient-file= : Path to nutrient.csv}
                            {--food-nutrient-file= : Path to food_nutrient.csv}
                            {--portion-file= : Path to food_portion.csv}
                            {--measure-unit-file= : Path to measure_unit.csv}
                            {--data-type=foundation_food,sr_legacy_food,survey_fndds_food : Comma-separated FDC data_type values to import}
                            {--download : Fetch the SR Legacy dataset on demand}';

    /**
     * Bundled FoodData Central dataset directories, relative to
     * storage/app/private. Imported by default when no other input is given.
     *
     * @var array<int, string>
     */
    private const DEFAULT_DATASET_DIRS = [
        'usda_food_data',      // Foundation Foods
        'usda_food_srlegacy',  // SR Legacy
    ];

    /**
     * FDC data_type values that represent provenance records rather than
     * actual food composition entries. Used as the default exclusion set
     * when food.csv carries no recognised data_type column.
     *
     * @var array<int, string>
     */
    private const PROVENANCE_DATA_TYPES = [
        'sub_sample_food',
        'market_acquisition',
        'sample_food',
        'agricultural_acquisition',
    ];

    /**
     * The console command description.
     */
    protected $description = 'Import USDA FoodData Central datasets (Foundation Foods + SR Legacy) into the ingredients library.';

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
        1008 => 'energy_kcal',     // Energy (kcal)
        2047 => 'energy_kcal',     // Energy, Atwater General Factors (Foundation Foods)
        2048 => 'energy_kcal',     // Energy, Atwater Specific Factors (Foundation Foods)
        1003 => 'protein_g',
        1004 => 'fat_g',
        1005 => 'carbs_g',
        1079 => 'fibre_g',
        1009 => 'starch_g',
        1063 => 'sugars_g',    // Sugars, Total — primary for Foundation Foods
        2000 => 'sugars_g',    // Sugars, total including NLEA — fallback
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
     * Keyed to the FoodData Central food_category.csv ids (1–28). Ids absent
     * from this map fall back to "other-uncategorised".
     *
     * @var array<int, string>
     */
    private const CATEGORY_MAP = [
        1 => 'dairy-eggs',                 // Dairy and Egg Products
        2 => 'herbs-spices',               // Spices and Herbs
        3 => 'prepared-convenience-foods', // Baby Foods
        4 => 'oils-fats-condiments',       // Fats and Oils
        5 => 'meat-poultry',               // Poultry Products
        6 => 'prepared-convenience-foods', // Soups, Sauces, and Gravies
        7 => 'meat-poultry',               // Sausages and Luncheon Meats
        8 => 'grains-starches',            // Breakfast Cereals
        9 => 'fruits',                     // Fruits and Fruit Juices
        10 => 'meat-poultry',              // Pork Products
        11 => 'vegetables',                // Vegetables and Vegetable Products
        12 => 'nuts-seeds',                // Nut and Seed Products
        13 => 'meat-poultry',              // Beef Products
        14 => 'beverages',                 // Beverages
        15 => 'fish-seafood',              // Finfish and Shellfish Products
        16 => 'vegetables',                // Legumes and Legume Products
        17 => 'meat-poultry',              // Lamb, Veal, and Game Products
        18 => 'prepared-convenience-foods', // Baked Products
        19 => 'sweeteners-sugar-products', // Sweets
        20 => 'grains-starches',           // Cereal Grains and Pasta
        21 => 'prepared-convenience-foods', // Fast Foods
        22 => 'prepared-convenience-foods', // Meals, Entrees, and Side Dishes
        23 => 'prepared-convenience-foods', // Snacks
        24 => 'other-uncategorised',       // American Indian/Alaska Native Foods
        25 => 'prepared-convenience-foods', // Restaurant Foods
        26 => 'other-uncategorised',       // Branded Food Products Database
        27 => 'other-uncategorised',       // Quality Control Materials
        28 => 'beverages',                 // Alcoholic Beverages
    ];

    /**
     * Execute the console command.
     *
     * Imports one or more FoodData Central datasets. With no input options
     * the bundled Foundation Foods and SR Legacy datasets are both imported.
     */
    public function handle(IngredientImporter $importer): int
    {
        // FoodData Central datasets are large (SR Legacy food_nutrient.csv is
        // ~36 MB); give the importer headroom above the default CLI limit.
        ini_set('memory_limit', '512M');

        $datasets = $this->resolveDatasets();

        if ($datasets === []) {
            $this->error('No USDA datasets to import. Pass --dir, the individual --*-file options, or --download.');

            return self::FAILURE;
        }

        $allowedDataTypes = array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('data-type')),
        ));

        $grandTotal = 0;
        $importedDatasets = 0;

        foreach ($datasets as $dataset) {
            $this->info("Importing USDA dataset: {$dataset['label']}");

            $count = $this->importDataset($dataset, $importer, $allowedDataTypes);

            if ($count > 0) {
                $importedDatasets++;
                $grandTotal += $count;
            }

            $this->newLine();
        }

        if ($importedDatasets === 0) {
            $this->warn('No ingredients were imported from any dataset.');

            return self::FAILURE;
        }

        $this->info("USDA import complete: {$grandTotal} ingredients from {$importedDatasets} dataset(s).");

        return self::SUCCESS;
    }

    /**
     * Import a single FoodData Central dataset.
     *
     * @param  array{label: string, food: string, nutrient: string, food_nutrient: string, portion: string, measure_unit: string}  $dataset
     * @param  array<int, string>  $allowedDataTypes
     * @return int Number of ingredients upserted (0 when the dataset is missing or empty).
     */
    private function importDataset(array $dataset, IngredientImporter $importer, array $allowedDataTypes): int
    {
        if (! file_exists($dataset['food'])) {
            $this->warn("  Skipped — food.csv not found at {$dataset['food']}");

            return 0;
        }

        $now = now()->toDateTimeString();

        $this->info('  Loading nutrient definitions…');
        $nutrientIdToColumn = $this->loadNutrientMap($dataset['nutrient'], $importer, $now);

        $this->info('  Loading food nutrients…');
        $nutritionByFdcId = $this->loadFoodNutrients($dataset['food_nutrient'], $nutrientIdToColumn);

        $this->info('  Loading measure units…');
        $measureUnits = $this->loadMeasureUnits($dataset['measure_unit']);

        $defaultCategoryId = $this->resolveDefaultCategoryId();
        $categoryCache = [];

        $this->info('  Parsing foods…');
        $rows = $this->parseFoods($dataset['food'], $nutritionByFdcId, $importer, $defaultCategoryId, $categoryCache, $allowedDataTypes);

        if (empty($rows)) {
            $this->warn('  No food rows found in the food CSV.');

            return 0;
        }

        $this->info(sprintf('  Parsed %d ingredients. Upserting…', count($rows)));

        // Two-pass: reset verified BEFORE upsert. The private _fdc_id/_name_en
        // keys are ignored here and stripped per-chunk before the upsert, so
        // no full copy of the (potentially large) row set is held.
        $importer->resetVerifiedForChangedRows($rows);

        $count = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $clean = array_map(function (array $row): array {
                unset($row['_fdc_id'], $row['_name_en']);

                return $row;
            }, $chunk);

            $count += $importer->upsertIngredients($clean);
        }

        // Resolve fdc_id → ingredient id for the rows just upserted.
        $ingredientIdByFdcId = $this->resolveIngredientIds($rows);

        // Capture the full nutrient set into ingredient_nutrients.
        $this->info('  Syncing full nutrient set…');
        $this->syncIngredientNutrients($dataset['food_nutrient'], $ingredientIdByFdcId, $importer, $now);

        // Sync translations.
        $this->syncTranslations($rows, $importer);

        // Sync food_portion conversions.
        $this->info('  Syncing food portion conversions…');
        $this->syncPortions($dataset['portion'], $measureUnits, $importer);

        return $count;
    }

    /**
     * Resolve the datasets to import from the command options.
     *
     * Precedence: --download, then explicit --*-file options (a single
     * dataset), then --dir directories, then the bundled default datasets.
     *
     * @return array<int, array{label: string, food: string, nutrient: string, food_nutrient: string, portion: string, measure_unit: string}>
     */
    private function resolveDatasets(): array
    {
        if ($this->option('download')) {
            [$food, $nutrient, $foodNutrient, $portion, $measureUnit] = $this->downloadAndExtract();

            return $food === null
                ? []
                : [$this->dataset('download', $food, $nutrient, $foodNutrient, $portion, $measureUnit)];
        }

        // Explicit per-file options resolve to a single dataset (used by the
        // test suite to run against small bundled fixtures).
        $fileOptions = ['food-file', 'nutrient-file', 'food-nutrient-file', 'portion-file', 'measure-unit-file'];

        foreach ($fileOptions as $option) {
            if ($this->option($option) !== null) {
                $base = storage_path('app/private/usda_food_data');

                return [$this->dataset(
                    'custom',
                    $this->option('food-file') ?? $base.'/food.csv',
                    $this->option('nutrient-file') ?? $base.'/nutrient.csv',
                    $this->option('food-nutrient-file') ?? $base.'/food_nutrient.csv',
                    $this->option('portion-file') ?? $base.'/food_portion.csv',
                    $this->option('measure-unit-file') ?? $base.'/measure_unit.csv',
                )];
            }
        }

        $dirs = $this->option('dir');

        if (empty($dirs)) {
            $dirs = self::DEFAULT_DATASET_DIRS;
        }

        $datasets = [];

        foreach ($dirs as $dir) {
            $path = $this->resolveDatasetDir($dir);

            $datasets[] = $this->dataset(
                basename($path),
                $path.'/food.csv',
                $path.'/nutrient.csv',
                $path.'/food_nutrient.csv',
                $path.'/food_portion.csv',
                $path.'/measure_unit.csv',
            );
        }

        return $datasets;
    }

    /**
     * Resolve a --dir value to an absolute directory path.
     *
     * Absolute paths are used as-is; relative names resolve under
     * storage/app/private.
     */
    private function resolveDatasetDir(string $dir): string
    {
        $trimmed = rtrim($dir, '/\\');

        if (is_dir($trimmed)) {
            return $trimmed;
        }

        return rtrim(storage_path('app/private/'.$trimmed), '/\\');
    }

    /**
     * Build a dataset descriptor from a label and the five FDC CSV paths.
     *
     * @return array{label: string, food: string, nutrient: string, food_nutrient: string, portion: string, measure_unit: string}
     */
    private function dataset(
        string $label,
        string $food,
        string $nutrient,
        string $foodNutrient,
        string $portion,
        string $measureUnit,
    ): array {
        return [
            'label' => $label,
            'food' => $food,
            'nutrient' => $nutrient,
            'food_nutrient' => $foodNutrient,
            'portion' => $portion,
            'measure_unit' => $measureUnit,
        ];
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
     * Load nutrient definitions from nutrient.csv.
     *
     * Upserts every nutrient definition into the `nutrients` reference table
     * (so the full nutrient set can be captured in ingredient_nutrients) and
     * returns the nutrient id → flat schema column map used to populate the
     * 29 nutrition columns on the ingredients table.
     *
     * @return array<int, string> nutrient_id → column name
     */
    private function loadNutrientMap(string $nutrientFile, IngredientImporter $importer, string $now): array
    {
        $map = self::NUTRIENT_MAP;

        if (! file_exists($nutrientFile)) {
            return $map;
        }

        $handle = fopen($nutrientFile, 'r');

        if ($handle === false) {
            return $map;
        }

        // Resolve columns by header: id, name, unit_name, nutrient_nbr, rank.
        $header = fgetcsv($handle);
        $cols = $header !== false ? array_flip(array_map('trim', $header)) : [];
        $idCol = $cols['id'] ?? 0;
        $nameCol = $cols['name'] ?? 1;
        $unitCol = $cols['unit_name'] ?? 2;
        $nbrCol = $cols['nutrient_nbr'] ?? null;
        $rankCol = $cols['rank'] ?? null;

        $definitions = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $usdaNutrientId = (int) ($row[$idCol] ?? 0);

            if ($usdaNutrientId === 0) {
                continue;
            }

            $nbr = $nbrCol !== null ? trim($row[$nbrCol] ?? '') : '';
            $rank = $rankCol !== null ? trim($row[$rankCol] ?? '') : '';

            $definitions[] = [
                'usda_nutrient_id' => $usdaNutrientId,
                'name' => trim($row[$nameCol] ?? ''),
                'unit' => trim($row[$unitCol] ?? ''),
                'nutrient_nbr' => $nbr !== '' ? $nbr : null,
                'rank' => $rank !== '' ? (float) $rank : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        $importer->upsertNutrientDefinitions($definitions);

        return $map;
    }

    /**
     * Load food_nutrient.csv into the flat nutrition map: fdc_id → [column => value].
     *
     * Only the ~29 mapped schema columns are kept here. The complete nutrient
     * set is streamed separately by syncIngredientNutrients so memory stays
     * bounded for large datasets (SR Legacy food_nutrient.csv is ~36 MB).
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

        // A food may carry energy or total sugars under several nutrient ids.
        // Prefer the highest-priority id so the chosen flat value is
        // deterministic regardless of CSV order.
        $energyPriority = [1008 => 3, 2048 => 2, 2047 => 1];
        $sugarsPriority = [1063 => 2, 2000 => 1];
        $priorityByColumn = ['energy_kcal' => $energyPriority, 'sugars_g' => $sugarsPriority];
        $chosenPriority = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Columns: id, fdc_id, nutrient_id, amount
            if (count($row) < 4) {
                continue;
            }

            $column = $nutrientIdToColumn[(int) $row[2]] ?? null;

            if ($column === null) {
                continue;
            }

            $fdcId = (int) $row[1];
            $nutrientId = (int) $row[2];
            $amount = $row[3] !== '' ? (float) $row[3] : null;

            if (! isset($nutritionByFdcId[$fdcId])) {
                $nutritionByFdcId[$fdcId] = [];
            }

            // Resolve flat-column conflicts (energy / sugars) by priority.
            if (isset($priorityByColumn[$column])) {
                $priority = $priorityByColumn[$column][$nutrientId] ?? 0;

                if (($chosenPriority[$fdcId][$column] ?? -1) >= $priority) {
                    continue;
                }

                $chosenPriority[$fdcId][$column] = $priority;
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
     * Columns are resolved by header name so the parser works against any
     * FoodData Central food.csv regardless of column ordering. Rows whose
     * `data_type` is not an actual food composition entry (e.g.
     * `sub_sample_food`, `market_acquisition`) are skipped.
     *
     * @param  array<int, array<string, float|null>>  $nutritionByFdcId
     * @param  array<string, int>  $categoryCache
     * @param  array<int, string>  $allowedDataTypes
     * @return array<int, array<string, mixed>>
     */
    private function parseFoods(
        string $foodFile,
        array $nutritionByFdcId,
        IngredientImporter $importer,
        int $defaultCategoryId,
        array &$categoryCache,
        array $allowedDataTypes = [],
    ): array {
        $rows = [];

        if (! file_exists($foodFile)) {
            return $rows;
        }

        $handle = fopen($foodFile, 'r');

        if ($handle === false) {
            return $rows;
        }

        // Resolve column positions from the header: fdc_id, data_type,
        // description, food_category_id, publication_date.
        $header = fgetcsv($handle);
        $cols = $header !== false ? array_flip(array_map('trim', $header)) : [];
        $fdcIdCol = $cols['fdc_id'] ?? 0;
        $dataTypeCol = $cols['data_type'] ?? null;
        $descCol = $cols['description'] ?? 1;
        $categoryCol = $cols['food_category_id'] ?? 2;

        $now = now()->toDateTimeString();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            // Skip provenance rows: FDC food.csv mixes real foods with lab
            // sub-samples and acquisition records.
            if ($dataTypeCol !== null) {
                $dataType = trim($row[$dataTypeCol] ?? '');

                if ($dataType !== '') {
                    if ($allowedDataTypes !== []) {
                        if (! in_array($dataType, $allowedDataTypes, true)) {
                            continue;
                        }
                    } elseif (in_array($dataType, self::PROVENANCE_DATA_TYPES, true)) {
                        continue;
                    }
                }
            }

            $fdcId = (int) ($row[$fdcIdCol] ?? 0);
            $description = trim($row[$descCol] ?? '');
            $foodCategoryId = (int) ($row[$categoryCol] ?? 0);

            if ($fdcId === 0 || $description === '') {
                continue;
            }

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
     * Resolve fdc_id → ingredient id for the rows that were just upserted.
     *
     * Queried in chunks so the source_id IN clause stays bounded.
     *
     * @param  array<int, array<string, mixed>>  $rows  Parsed food rows (carry _fdc_id).
     * @return array<int, int>
     */
    private function resolveIngredientIds(array $rows): array
    {
        $ingredientIdByFdcId = [];

        foreach (array_chunk($rows, 1000) as $chunk) {
            $idBySourceId = Ingredient::where('source', 'usda')
                ->whereIn('source_id', array_column($chunk, 'source_id'))
                ->pluck('id', 'source_id')
                ->all();

            foreach ($chunk as $row) {
                if (isset($idBySourceId[$row['source_id']])) {
                    $ingredientIdByFdcId[$row['_fdc_id']] = $idBySourceId[$row['source_id']];
                }
            }
        }

        return $ingredientIdByFdcId;
    }

    /**
     * Capture the full per-100g nutrient set into ingredient_nutrients.
     *
     * Streams food_nutrient.csv and upserts the pivot rows in batches, so the
     * payload stays bounded regardless of dataset size (SR Legacy carries
     * roughly half a million food_nutrient rows).
     *
     * @param  array<int, int>  $ingredientIdByFdcId  fdc_id → ingredient id
     */
    private function syncIngredientNutrients(
        string $foodNutrientFile,
        array $ingredientIdByFdcId,
        IngredientImporter $importer,
        string $now,
    ): void {
        if ($ingredientIdByFdcId === [] || ! file_exists($foodNutrientFile)) {
            return;
        }

        $handle = fopen($foodNutrientFile, 'r');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle); // Skip header.

        $nutrientIdByUsdaId = Nutrient::pluck('id', 'usda_nutrient_id')->all();
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Columns: id, fdc_id, nutrient_id, amount
            if (count($row) < 4 || $row[3] === '') {
                continue;
            }

            $ingredientId = $ingredientIdByFdcId[(int) $row[1]] ?? null;
            $nutrientId = $nutrientIdByUsdaId[(int) $row[2]] ?? null;

            if ($ingredientId === null || $nutrientId === null) {
                continue;
            }

            $batch[] = [
                'ingredient_id' => $ingredientId,
                'nutrient_id' => $nutrientId,
                'amount' => (float) $row[3],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 2000) {
                $importer->upsertIngredientNutrients($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            $importer->upsertIngredientNutrients($batch);
        }
    }

    /**
     * Parse food_portion.csv and sync ingredient_conversions rows.
     *
     * Foundation Foods reference a real measure_unit_id. SR Legacy marks every
     * portion's measure unit "undetermined" and carries the unit together with
     * a human-readable description as free text in the modifier column. Both
     * are handled: the unit is resolved from whichever text is available, and
     * portions whose text is not a recognised unit fall back to the generic
     * "piece" unit so the gram weight and description are still recorded.
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

        $ingredientIdByFdcId = [];
        $unitCache = [];
        $fallbackUnitId = $this->resolveFallbackUnitId();

        while (($row = fgetcsv($handle)) !== false) {
            // Columns: id, fdc_id, seq_num, amount, measure_unit_id,
            // portion_description, modifier, gram_weight
            if (count($row) < 8) {
                continue;
            }

            $fdcId = (int) $row[1];
            $seqNum = (int) $row[2];
            $amount = $row[3] !== '' ? (float) $row[3] : 1.0;
            $measureUnitId = (int) $row[4];
            $portionDescription = trim($row[5]);
            $modifierText = trim($row[6]);
            $gramWeight = $row[7] !== '' ? (float) $row[7] : null;

            if ($gramWeight === null || $gramWeight <= 0) {
                continue;
            }

            // Resolve ingredient id.
            if (! array_key_exists($fdcId, $ingredientIdByFdcId)) {
                $ingredientIdByFdcId[$fdcId] = Ingredient::where('usda_fdc_id', $fdcId)->value('id');
            }

            $ingredientId = $ingredientIdByFdcId[$fdcId];

            if ($ingredientId === null) {
                continue;
            }

            // Free-text portion label: SR Legacy uses the modifier column;
            // some datasets use portion_description instead.
            $freeText = $modifierText !== '' ? $modifierText : $portionDescription;

            // Resolve the unit from a real measure unit when the dataset
            // provides one, otherwise from the free-text portion label.
            $measureUnitName = strtolower(trim($measureUnits[$measureUnitId] ?? ''));
            $hasMeasureUnit = $measureUnitName !== '' && $measureUnitName !== 'undetermined';
            $unitText = $hasMeasureUnit ? $measureUnitName : $freeText;

            if ($unitText === '') {
                continue; // No unit and no description — nothing to record.
            }

            $resolvedUnitId = $this->resolveUnitId($unitText, $unitCache);
            $fromUnitId = $resolvedUnitId ?? $fallbackUnitId;

            if ($fromUnitId === null) {
                continue; // No matching unit and no fallback available.
            }

            // Preserve the description: keep the free text, or the unmatched
            // unit text so nothing is lost when it fell back to "piece".
            $modifier = $freeText !== ''
                ? $freeText
                : ($resolvedUnitId === null ? $unitText : null);

            $importer->syncConversion(
                $ingredientId,
                $amount,
                $fromUnitId,
                $gramWeight,
                'usda',
                "fdc:{$fdcId}:{$seqNum}",
                $modifier,
            );
        }

        fclose($handle);
    }

    /**
     * Resolve a units row id from a portion's unit text.
     *
     * Matches the whole string, then its leading word, against the units
     * table by name or symbol. Results (including misses) are cached.
     *
     * @param  array<string, int|null>  $cache
     */
    private function resolveUnitId(string $text, array &$cache): ?int
    {
        $text = strtolower(trim($text));

        if ($text === '') {
            return null;
        }

        if (array_key_exists($text, $cache)) {
            return $cache[$text];
        }

        $candidates = [$text];

        if (preg_match('/^[a-z]+/', $text, $matches) && $matches[0] !== $text) {
            $candidates[] = $matches[0];
        }

        $unitId = null;

        foreach ($candidates as $candidate) {
            $unitId = Unit::where('name', $candidate)
                ->orWhere('symbol', $candidate)
                ->value('id');

            if ($unitId !== null) {
                break;
            }
        }

        $cache[$text] = $unitId;

        return $unitId;
    }

    /**
     * Resolve the generic "piece" unit id, used as the fallback for portions
     * whose text is not a recognised unit.
     */
    private function resolveFallbackUnitId(): ?int
    {
        return Unit::where('name', 'piece')->value('id');
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
