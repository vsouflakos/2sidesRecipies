<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Support\Ingredients\IngredientImporter;
use Illuminate\Console\Command;
use XMLReader;

class ImportCiqual extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ingredients:import-ciqual
                            {--alim-file= : Path to the CIQUAL alim XML (foods)}
                            {--compo-file= : Path to the CIQUAL compo XML (composition values)}';

    /**
     * The console command description.
     */
    protected $description = 'Import the CIQUAL French food composition table from the official ANSES alim + compo XML exports.';

    /**
     * Map CIQUAL constituent codes to the schema nutrition columns.
     *
     * Codes verified against the official ANSES CIQUAL 2025 constituent
     * table (const XML). Note that code 327 is energy in kJ and 328 is
     * energy in kcal — only kcal is stored.
     *
     * @var array<int, string>
     */
    private const NUTRIENT_MAP = [
        328 => 'energy_kcal',             // Energy, Regulation EU No 1169/2011 (kcal/100g)
        25000 => 'protein_g',             // Protein
        40000 => 'fat_g',                 // Fat
        40302 => 'saturated_fat_g',       // FA saturated
        40303 => 'monounsaturated_fat_g', // FA mono
        40304 => 'polyunsaturated_fat_g', // FA poly
        31000 => 'carbs_g',               // Carbohydrate
        32000 => 'sugars_g',              // Sugars
        33110 => 'starch_g',              // Starch
        34100 => 'fibre_g',               // Fibres
        10110 => 'sodium_mg',             // Sodium
        10200 => 'calcium_mg',            // Calcium
        10260 => 'iron_mg',               // Iron
        10120 => 'magnesium_mg',          // Magnesium
        10150 => 'phosphorus_mg',         // Phosphorus
        10190 => 'potassium_mg',          // Potassium
        10300 => 'zinc_mg',               // Zinc
        51104 => 'vitamin_a_ug',          // Vitamin A activity, retinol equivalent
        56100 => 'vitamin_b1_mg',         // Vitamin B1 (Thiamin)
        56200 => 'vitamin_b2_mg',         // Vitamin B2 (Riboflavin)
        56310 => 'vitamin_b3_mg',         // Vitamin B3 (Niacin)
        56500 => 'vitamin_b6_mg',         // Vitamin B6
        56700 => 'vitamin_b9_ug',         // Vitamin B9 (total folates)
        56600 => 'vitamin_b12_ug',        // Vitamin B12
        55100 => 'vitamin_c_mg',          // Vitamin C
        52100 => 'vitamin_d_ug',          // Vitamin D
        53100 => 'vitamin_e_mg',          // Vitamin E
        54101 => 'vitamin_k_ug',          // Vitamin K1
        75100 => 'cholesterol_mg',        // Cholesterol
    ];

    /**
     * Map CIQUAL top-level food group codes to seeded category slugs.
     *
     * Group codes are the official ANSES CIQUAL `alim_grp_code` values
     * (01–11).
     *
     * @var array<string, string>
     */
    private const GROUP_CATEGORY_MAP = [
        '01' => 'prepared-convenience-foods', // starters and dishes
        '02' => 'vegetables',                 // fruits, vegetables, legumes and nuts
        '03' => 'grains-starches',            // cereal products
        '04' => 'meat-poultry',               // meat, egg and fish
        '05' => 'dairy-eggs',                 // milk and milk products
        '06' => 'beverages',                  // beverages
        '07' => 'sweeteners-sugar-products',  // sugar and confectionery
        '08' => 'sweeteners-sugar-products',  // ice cream and sorbet
        '09' => 'oils-fats-condiments',       // fats and oils
        '10' => 'other-uncategorised',        // miscellaneous
        '11' => 'prepared-convenience-foods', // baby food
    ];

    /**
     * Execute the console command.
     */
    public function handle(IngredientImporter $importer): int
    {
        $alimFile = $this->option('alim-file');
        $compoFile = $this->option('compo-file');

        if (! $alimFile || ! $compoFile) {
            $this->error('Both --alim-file and --compo-file are required (official ANSES CIQUAL XML exports).');

            return self::FAILURE;
        }

        if (! file_exists($alimFile)) {
            $this->error("CIQUAL alim file not found: {$alimFile}");

            return self::FAILURE;
        }

        if (! file_exists($compoFile)) {
            $this->error("CIQUAL compo file not found: {$compoFile}");

            return self::FAILURE;
        }

        $this->info("Parsing CIQUAL foods: {$alimFile}");
        $foods = $this->parseAlimFile($alimFile);

        if ($foods === []) {
            $this->warn('No ALIM nodes found in the alim file.');

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d foods. Parsing composition values…', count($foods)));
        $nutritionByCode = $this->parseCompoFile($compoFile);

        $rows = $this->buildRows($foods, $nutritionByCode, $importer);

        $this->info(sprintf('Upserting %d ingredients…', count($rows)));

        // Strip private translation keys before upsert.
        $upsertRows = array_map(function (array $row): array {
            unset($row['_name_en'], $row['_name_fr']);

            return $row;
        }, $rows);

        // Two-pass ordering: reset verified BEFORE upsert so the comparison
        // uses the previously stored data hashes.
        $importer->resetVerifiedForChangedRows($upsertRows);

        $count = 0;
        $chunks = array_chunk($upsertRows, 500);

        $this->withProgressBar($chunks, function (array $chunk) use ($importer, &$count): void {
            $count += $importer->upsertIngredients($chunk);
        });

        $this->newLine();

        $this->syncTranslations($rows, $importer);

        $this->info("CIQUAL import complete: {$count} ingredients.");

        return self::SUCCESS;
    }

    /**
     * Parse the CIQUAL alim XML into a map keyed by alim_code.
     *
     * Uses XMLReader streaming so the parser stays low-memory regardless of
     * file size.
     *
     * @return array<string, array{name_cache: string, name_en: string, name_fr: string, grp: string}>
     */
    private function parseAlimFile(string $path): array
    {
        $foods = [];

        $reader = new XMLReader;
        $reader->open($path);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'ALIM') {
                continue;
            }

            $alim = simplexml_load_string($reader->readOuterXml());

            if ($alim === false) {
                continue;
            }

            $code = trim((string) ($alim->alim_code ?? ''));

            if ($code === '') {
                continue;
            }

            $nameFr = trim((string) ($alim->alim_nom_fr ?? ''));
            $nameEn = trim((string) ($alim->alim_nom_eng ?? ''));
            $grp = trim((string) ($alim->alim_grp_code ?? ''));

            $foods[$code] = [
                'name_cache' => $nameEn !== '' ? $nameEn : $nameFr,
                'name_en' => $nameEn !== '' ? $nameEn : $nameFr,
                'name_fr' => $nameFr,
                'grp' => $grp,
            ];
        }

        $reader->close();

        return $foods;
    }

    /**
     * Parse the CIQUAL compo XML into a map: alim_code → [column => value].
     *
     * The compo file is large (tens of MB / hundreds of thousands of nodes);
     * XMLReader streaming keeps memory flat.
     *
     * @return array<string, array<string, float|null>>
     */
    private function parseCompoFile(string $path): array
    {
        $nutrition = [];

        $reader = new XMLReader;
        $reader->open($path);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'COMPO') {
                continue;
            }

            $compo = simplexml_load_string($reader->readOuterXml());

            if ($compo === false) {
                continue;
            }

            $constCode = (int) trim((string) ($compo->const_code ?? '0'));
            $column = self::NUTRIENT_MAP[$constCode] ?? null;

            if ($column === null) {
                continue;
            }

            $alimCode = trim((string) ($compo->alim_code ?? ''));

            if ($alimCode === '') {
                continue;
            }

            $nutrition[$alimCode][$column] = $this->parseTeneurValue((string) ($compo->teneur ?? ''));
        }

        $reader->close();

        return $nutrition;
    }

    /**
     * Merge parsed foods and composition values into upsertable rows.
     *
     * @param  array<string, array{name_cache: string, name_en: string, name_fr: string, grp: string}>  $foods
     * @param  array<string, array<string, float|null>>  $nutritionByCode
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(array $foods, array $nutritionByCode, IngredientImporter $importer): array
    {
        $defaultCategoryId = $this->resolveDefaultCategoryId();
        $categoryCache = [];
        $now = now()->toDateTimeString();
        $emptyNutrition = array_fill_keys(array_values(self::NUTRIENT_MAP), null);
        $rows = [];

        foreach ($foods as $code => $food) {
            $nutrition = array_merge($emptyNutrition, $nutritionByCode[$code] ?? []);

            $rows[] = array_merge($nutrition, [
                'source' => 'ciqual',
                'source_id' => $code,
                'name_cache' => $food['name_cache'],
                'category_id' => $this->resolveCategoryId($food['grp'], $defaultCategoryId, $categoryCache),
                'data_hash' => $importer->dataHash($nutrition),
                'verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'user_id' => null,
                'usda_fdc_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                // Private keys for translation sync (stripped before upsert).
                '_name_en' => $food['name_en'],
                '_name_fr' => $food['name_fr'],
            ]);
        }

        return $rows;
    }

    /**
     * Convert a CIQUAL `teneur` string to a float or null.
     *
     * CIQUAL uses French decimal commas and several special markers:
     * - `< N` (less than N): stored as 0 (trace-level)
     * - `traces`: stored as 0
     * - `-` or empty: stored as null (not measured)
     */
    private function parseTeneurValue(string $teneur): ?float
    {
        $teneur = trim($teneur);

        if ($teneur === '' || $teneur === '-') {
            return null;
        }

        if (strtolower($teneur) === 'traces') {
            return 0.0;
        }

        if (str_starts_with($teneur, '<')) {
            return 0.0;
        }

        // CIQUAL uses a comma as the decimal separator.
        $normalised = str_replace(',', '.', $teneur);
        $value = filter_var($normalised, FILTER_VALIDATE_FLOAT);

        return $value !== false ? $value : null;
    }

    /**
     * Resolve the category id for a given CIQUAL group code.
     *
     * @param  array<string, int>  $cache
     */
    private function resolveCategoryId(string $grpCode, int $defaultId, array &$cache): int
    {
        if (isset($cache[$grpCode])) {
            return $cache[$grpCode];
        }

        $slug = self::GROUP_CATEGORY_MAP[$grpCode] ?? null;

        if ($slug !== null) {
            $category = IngredientCategory::where('slug', $slug)->whereNull('parent_id')->first();

            if ($category) {
                $cache[$grpCode] = $category->id;

                return $category->id;
            }
        }

        $cache[$grpCode] = $defaultId;

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

    /**
     * Sync English and French translations after upsert.
     *
     * Re-fetches each ingredient by (source, source_id) to resolve the
     * database id, then syncs the 'en' locale (and 'fr' when present).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTranslations(array $rows, IngredientImporter $importer): void
    {
        $sourceIds = array_column($rows, 'source_id');

        $idsBySourceId = Ingredient::where('source', 'ciqual')
            ->whereIn('source_id', $sourceIds)
            ->pluck('id', 'source_id')
            ->all();

        foreach ($rows as $row) {
            $id = $idsBySourceId[$row['source_id']] ?? null;

            if ($id === null) {
                continue;
            }

            $importer->syncTranslation($id, 'en', $row['_name_en']);

            if (! empty($row['_name_fr'])) {
                $importer->syncTranslation($id, 'fr', $row['_name_fr']);
            }
        }
    }
}
