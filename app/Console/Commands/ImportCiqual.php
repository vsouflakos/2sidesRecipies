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
                            {--source-file= : Path to a CIQUAL XML file (defaults to the bundled file)}';

    /**
     * The console command description.
     */
    protected $description = 'Import the CIQUAL French food composition table into the ingredients library.';

    /**
     * Map CIQUAL constituent codes to the schema nutrition columns.
     * Codes from the official ANSES CIQUAL documentation.
     *
     * @var array<int, string>
     */
    private const NUTRIENT_MAP = [
        327 => 'energy_kcal',      // Énergie, Règlement UE N° 1169/2011 (kcal/100g)
        328 => 'energy_kcal',      // Énergie, Règlement UE N° 1169/2011 (kcal/100g) alternate
        25000 => 'protein_g',      // Protéines, N x facteur de Jones (g/100g)
        40000 => 'fat_g',          // Lipides (g/100g)
        31000 => 'carbs_g',        // Glucides (g/100g)
        32100 => 'sugars_g',       // Sucres (g/100g)
        32200 => 'starch_g',       // Amidon (g/100g)
        34100 => 'saturated_fat_g',        // AG saturés (g/100g)
        34400 => 'monounsaturated_fat_g',  // AG monoinsaturés (g/100g)
        34500 => 'polyunsaturated_fat_g',  // AG polyinsaturés (g/100g)
        51200 => 'fibre_g',        // Fibres alimentaires (g/100g)
        10110 => 'sodium_mg',      // Sodium (mg/100g)
        10120 => 'calcium_mg',     // Calcium (mg/100g)
        10190 => 'iron_mg',        // Fer (mg/100g)
        10153 => 'magnesium_mg',   // Magnésium (mg/100g)
        10170 => 'phosphorus_mg',  // Phosphore (mg/100g)
        10165 => 'potassium_mg',   // Potassium (mg/100g)
        10210 => 'zinc_mg',        // Zinc (mg/100g)
        51330 => 'cholesterol_mg', // Cholestérol (mg/100g)
        55100 => 'vitamin_a_ug',   // Rétinol (µg/100g)
        56310 => 'vitamin_b1_mg',  // Thiamine B1 (mg/100g)
        56320 => 'vitamin_b2_mg',  // Riboflavine B2 (mg/100g)
        56330 => 'vitamin_b3_mg',  // Niacine PP (mg/100g)
        56500 => 'vitamin_b6_mg',  // Vitamine B6 (mg/100g)
        56600 => 'vitamin_b9_ug',  // Folates totaux (µg/100g)
        56700 => 'vitamin_b12_ug', // Vitamine B12 (µg/100g)
        56200 => 'vitamin_c_mg',   // Vitamine C (mg/100g)
        55400 => 'vitamin_d_ug',   // Vitamine D (µg/100g)
        56100 => 'vitamin_e_mg',   // Tocophérols totaux (mg/100g)
        55700 => 'vitamin_k_ug',   // Vitamine K (µg/100g)
    ];

    /**
     * Map CIQUAL alim_grp_code values to seeded category slugs.
     *
     * CIQUAL group codes are formatted as G01–G20 and correspond to the food
     * groups documented in the official ANSES CIQUAL nomenclature.
     *
     * @var array<string, string>
     */
    private const GROUP_CATEGORY_MAP = [
        'G01' => 'grains-starches',
        'G02' => 'vegetables',
        'G03' => 'fruits',
        'G04' => 'dairy-eggs',
        'G05' => 'meat-poultry',
        'G06' => 'fish-seafood',
        'G07' => 'oils-fats-condiments',
        'G08' => 'herbs-spices',
        'G09' => 'nuts-seeds',
        'G10' => 'sweeteners-sugar-products',
        'G11' => 'vegetables',
        'G12' => 'beverages',
        'G13' => 'prepared-convenience-foods',
        'G14' => 'prepared-convenience-foods',
        'G15' => 'prepared-convenience-foods',
        'G16' => 'other-uncategorised',
        'G17' => 'other-uncategorised',
        'G18' => 'other-uncategorised',
        'G19' => 'other-uncategorised',
        'G20' => 'other-uncategorised',
    ];

    /**
     * Execute the console command.
     */
    public function handle(IngredientImporter $importer): int
    {
        $sourcePath = $this->option('source-file') ?? database_path('data/ciqual-2025.xml');

        if (! file_exists($sourcePath)) {
            $this->error("CIQUAL XML file not found: {$sourcePath}");

            return self::FAILURE;
        }

        $this->info("Parsing CIQUAL XML: {$sourcePath}");

        $rows = $this->parseXml($sourcePath);

        if (empty($rows)) {
            $this->warn('No ALIM nodes found in the XML file.');

            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d ingredients. Upserting…', count($rows)));

        // Strip private translation keys before upsert (prefixed with _ to distinguish).
        $upsertRows = array_map(function (array $row): array {
            unset($row['_name_en'], $row['_name_fr']);

            return $row;
        }, $rows);

        // Two-pass ordering: reset verified BEFORE upsert so comparison uses old hashes.
        $importer->resetVerifiedForChangedRows($upsertRows);

        $count = 0;
        $chunks = array_chunk($upsertRows, 500);

        $this->withProgressBar($chunks, function (array $chunk) use ($importer, &$count): void {
            $count += $importer->upsertIngredients($chunk);
        });

        $this->newLine();

        // Sync translations after upsert by re-fetching each ingredient id.
        $this->syncTranslations($rows, $importer);

        $this->info("CIQUAL import complete: {$count} ingredients.");

        return self::SUCCESS;
    }

    /**
     * Parse the CIQUAL XML file using XMLReader streaming (low memory).
     *
     * Uses XMLReader node-by-node streaming to avoid loading the entire 3–5 MB
     * XML into memory at once (SimpleXML anti-pattern documented in RESEARCH.md).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseXml(string $path): array
    {
        $defaultCategoryId = $this->resolveDefaultCategoryId();
        $categoryCache = [];
        $rows = [];

        $reader = new XMLReader;
        $reader->open($path);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'ALIM') {
                continue;
            }

            $alimXml = $reader->readOuterXml();
            $alim = simplexml_load_string($alimXml);

            if ($alim === false) {
                continue;
            }

            $alimCode = (string) ($alim->alim_code ?? '');
            $alimNomFr = trim((string) ($alim->alim_nom_fr ?? ''));
            $alimNomEng = trim((string) ($alim->alim_nom_eng ?? ''));
            $alimGrpCode = trim((string) ($alim->alim_grp_code ?? ''));

            if ($alimCode === '') {
                continue;
            }

            $nameCache = $alimNomEng !== '' ? $alimNomEng : $alimNomFr;
            $nameEn = $alimNomEng !== '' ? $alimNomEng : $alimNomFr;

            $nutrition = $this->parseNutrition($alim);
            $dataHash = app(IngredientImporter::class)->dataHash($nutrition);
            $categoryId = $this->resolveCategoryId($alimGrpCode, $defaultCategoryId, $categoryCache);

            $now = now()->toDateTimeString();

            $row = array_merge([
                'source' => 'ciqual',
                'source_id' => $alimCode,
                'name_cache' => $nameCache,
                'category_id' => $categoryId,
                'data_hash' => $dataHash,
                'verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'user_id' => null,
                'usda_fdc_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                // Private keys for translation sync (stripped before upsert)
                '_name_en' => $nameEn,
                '_name_fr' => $alimNomFr,
            ], $nutrition);

            $rows[] = $row;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Parse nutrient values from an ALIM SimpleXML node.
     *
     * Converts `teneur` strings: values starting with `<` or equal to `traces`
     * are stored as 0; empty or `-` values become null.
     *
     * @return array<string, float|null>
     */
    private function parseNutrition(\SimpleXMLElement $alim): array
    {
        $nutrition = array_fill_keys(array_values(self::NUTRIENT_MAP), null);

        foreach ($alim->COMPO ?? [] as $compo) {
            $constCode = (int) ($compo->const_code ?? 0);
            $teneurRaw = trim((string) ($compo->teneur ?? ''));

            if (! isset(self::NUTRIENT_MAP[$constCode])) {
                continue;
            }

            $column = self::NUTRIENT_MAP[$constCode];
            $nutrition[$column] = $this->parseTeneurValue($teneurRaw);
        }

        return $nutrition;
    }

    /**
     * Convert a CIQUAL `teneur` string to a float or null.
     *
     * CIQUAL uses several special markers:
     * - `< N` (less than N): stored as 0 (trace-level)
     * - `traces`: stored as 0
     * - `-` or empty: stored as null (not measured)
     */
    private function parseTeneurValue(string $teneur): ?float
    {
        if ($teneur === '' || $teneur === '-') {
            return null;
        }

        if (strtolower($teneur) === 'traces') {
            return 0.0;
        }

        if (str_starts_with($teneur, '<')) {
            return 0.0;
        }

        $value = filter_var($teneur, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);

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
     * Sync English (and optionally French) translations after upsert.
     *
     * Re-fetches each ingredient by (source, source_id) to get the database id,
     * then calls syncTranslation for the 'en' locale (and 'fr' if a French name exists).
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
