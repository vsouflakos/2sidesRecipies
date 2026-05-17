<?php

namespace App\Support\Recipes;

use App\Models\Ingredient;
use App\Models\RecipeDraft;
use App\Models\RecipeVersion;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use RuntimeException;

class MetricsAggregator
{
    public function __construct(private readonly GramNormalizer $gramNormalizer) {}

    /**
     * Prepare the lines array from a draft for downstream metric calculators.
     *
     * Draft ingredient lines only store a reference (ingredient_id), a raw quantity,
     * and a unit_id — never denormalized nutrition/cost/allergen values. This method
     * resolves each line: it converts quantity + unit to grams via GramNormalizer and
     * attaches the ingredient's nutrition values, per-gram cost, and allergen set.
     *
     * For sub-recipe lines: loads the pinned RecipeVersion, computes
     *   scaleFactor = line.quantity_g / version.yield_g (Pitfall 4: asserts non-null yield_g),
     * then scales cached_nutrition_json and cached_cost_per_gram by scaleFactor.
     *
     * @return AggregatedMetrics Prepared lines plus portion/yield context.
     *
     * @throws RuntimeException If a sub-recipe version has a null yield_g.
     */
    public function prepareLines(RecipeDraft $draft): AggregatedMetrics
    {
        $data = $draft->data ?? [];
        $sections = $data['sections'] ?? [];

        // Batch-load every referenced ingredient (with allergens + prices) so metrics
        // resolve in a fixed number of queries regardless of how many lines exist.
        $ingredients = Ingredient::query()
            ->with(['allergens', 'prices'])
            ->whereIn('id', $this->collectIngredientIds($sections))
            ->get()
            ->keyBy('id');

        // The units table is tiny — load it whole so both gram normalization and
        // price-per-gram derivation can resolve any unit.
        $units = Unit::query()->get()->keyBy('id');

        $preparedLines = [];

        foreach ($sections as $section) {
            foreach ($section['lines'] ?? [] as $lineData) {
                if (! empty($lineData['sub_recipe_version_id'])) {
                    $preparedLines[] = $this->prepareSubRecipeLine($lineData);
                } else {
                    $preparedLines[] = $this->prepareIngredientLine($lineData, $ingredients, $units);
                }
            }
        }

        $portions = BigDecimal::of($this->numericOr($data['portions'] ?? null, '1'));

        // Total yield: the recipe's declared yield when set, otherwise the sum of
        // every resolved line's grams (so per-100g metrics still compute sensibly).
        $totalYieldG = isset($data['yield_g']) || isset($data['yield_amount'])
            ? BigDecimal::of($this->numericOr($data['yield_g'] ?? $data['yield_amount'], '0'))
            : $this->sumLineGrams($preparedLines);

        return new AggregatedMetrics($preparedLines, $portions, $totalYieldG);
    }

    /**
     * Collect the distinct ingredient IDs referenced by every section line.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, int>
     */
    private function collectIngredientIds(array $sections): array
    {
        $ids = [];

        foreach ($sections as $section) {
            foreach ($section['lines'] ?? [] as $line) {
                if (! empty($line['ingredient_id'])) {
                    $ids[] = (int) $line['ingredient_id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Prepare a single ingredient line by resolving its grams, nutrition, cost,
     * and allergens from the referenced Ingredient.
     *
     * @param  array<string, mixed>  $lineData
     * @param  Collection<int, Ingredient>  $ingredients
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    private function prepareIngredientLine(array $lineData, Collection $ingredients, Collection $units): array
    {
        $ingredientId = ! empty($lineData['ingredient_id']) ? (int) $lineData['ingredient_id'] : null;
        $ingredient = $ingredientId !== null ? $ingredients->get($ingredientId) : null;

        $grams = $this->resolveGrams($lineData, $ingredient, $units);

        return [
            'type' => 'ingredient',
            'ingredient_id' => $ingredientId,
            'grams' => $grams !== null ? (string) $grams : '0',
            'energy_kcal' => $this->ingredientValue($ingredient, 'energy_kcal'),
            'protein_g' => $this->ingredientValue($ingredient, 'protein_g'),
            'fat_g' => $this->ingredientValue($ingredient, 'fat_g'),
            'carbs_g' => $this->ingredientValue($ingredient, 'carbs_g'),
            'per_gram_cost' => $this->resolveCostPerGram($ingredient, $units),
            'allergens' => $this->resolveAllergens($ingredient),
            'yield_pct' => $lineData['yield_pct'] ?? null,
            'is_flour_base' => (bool) ($lineData['is_flour_base'] ?? false),
            'name' => $lineData['name'] ?? $ingredient?->name_cache ?? null,
        ];
    }

    /**
     * Resolve the gram weight of a draft line.
     *
     * Honours a pre-resolved gram weight when present (committed-line data), otherwise
     * converts the raw quantity + unit via GramNormalizer. Returns null when the line
     * has no unit or the conversion cannot be performed.
     *
     * @param  array<string, mixed>  $lineData
     * @param  Collection<int, Unit>  $units
     */
    private function resolveGrams(array $lineData, ?Ingredient $ingredient, Collection $units): ?BigDecimal
    {
        foreach (['quantity_g', 'grams'] as $key) {
            if (isset($lineData[$key]) && is_numeric($lineData[$key])) {
                return BigDecimal::of((string) $lineData[$key]);
            }
        }

        $rawQuantity = $lineData['quantity'] ?? null;

        if (! is_numeric($rawQuantity)) {
            return null;
        }

        $unitId = ! empty($lineData['unit_id']) ? (int) $lineData['unit_id'] : null;
        $unit = $unitId !== null ? $units->get($unitId) : null;

        if ($unit === null) {
            return null;
        }

        return $this->gramNormalizer->normalize(
            BigDecimal::of((string) $rawQuantity),
            $unit,
            $ingredient?->id,
        );
    }

    /**
     * Read a per-100g nutrition value off the ingredient as a decimal string.
     */
    private function ingredientValue(?Ingredient $ingredient, string $attribute): ?string
    {
        if ($ingredient === null || $ingredient->{$attribute} === null) {
            return null;
        }

        return (string) $ingredient->{$attribute};
    }

    /**
     * Resolve the per-gram cost for an ingredient from its most recent price.
     *
     * Prefers the precomputed per_gram_cost column; falls back to deriving it from
     * the price amount and the quantity normalised to grams.
     *
     * @param  Collection<int, Unit>  $units
     */
    private function resolveCostPerGram(?Ingredient $ingredient, Collection $units): ?string
    {
        if ($ingredient === null) {
            return null;
        }

        $price = $ingredient->prices->sortByDesc('recorded_at')->first();

        if ($price === null) {
            return null;
        }

        if ($price->per_gram_cost !== null) {
            return (string) $price->per_gram_cost;
        }

        // Derive: cost per gram = amount / (priced quantity in grams).
        if (! is_numeric($price->amount) || ! is_numeric($price->quantity)) {
            return null;
        }

        $unit = $price->unit_id !== null ? $units->get($price->unit_id) : null;

        if ($unit === null) {
            return null;
        }

        $grams = $this->gramNormalizer->normalize(
            BigDecimal::of((string) $price->quantity),
            $unit,
            $ingredient->id,
        );

        if ($grams === null || $grams->isZero()) {
            return null;
        }

        return (string) BigDecimal::of((string) $price->amount)
            ->dividedBy($grams, 8, RoundingMode::HALF_UP);
    }

    /**
     * Build the flat allergen list ({slug, state}) for an ingredient.
     *
     * @return array<int, array{slug: string, state: string}>
     */
    private function resolveAllergens(?Ingredient $ingredient): array
    {
        if ($ingredient === null) {
            return [];
        }

        $result = [];

        foreach ($ingredient->allergens as $allergen) {
            $result[] = [
                'slug' => $allergen->slug,
                'state' => $allergen->pivot->state ?? 'contains',
            ];
        }

        return $result;
    }

    /**
     * Sum the resolved grams across prepared lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sumLineGrams(array $lines): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($lines as $line) {
            if (isset($line['grams']) && is_numeric($line['grams'])) {
                $sum = $sum->plus(BigDecimal::of((string) $line['grams']));
            }
        }

        return $sum;
    }

    /**
     * Coerce a value to a numeric string, falling back to a default when non-numeric.
     */
    private function numericOr(mixed $value, string $default): string
    {
        return is_numeric($value) ? (string) $value : $default;
    }

    /**
     * Prepare a sub-recipe line by loading the pinned version and scaling cached metrics.
     *
     * Pitfall 4: asserts yield_g is non-null before dividing to prevent scale-factor defaulting to 1.
     *
     * @param  array<string, mixed>  $lineData
     * @return array<string, mixed>
     *
     * @throws RuntimeException If yield_g is null on the pinned RecipeVersion.
     */
    private function prepareSubRecipeLine(array $lineData): array
    {
        $version = RecipeVersion::findOrFail($lineData['sub_recipe_version_id']);

        if ($version->yield_g === null) {
            throw new RuntimeException(
                "RecipeVersion [{$version->id}] has a null yield_g. Cannot compute sub-recipe scale factor."
            );
        }

        $lineGrams = BigDecimal::of($lineData['quantity_g'] ?? $lineData['grams'] ?? $lineData['quantity'] ?? '0');
        $yieldGrams = BigDecimal::of($version->yield_g);
        $scaleFactor = $lineGrams->dividedBy($yieldGrams, 10, RoundingMode::HALF_UP);

        $cachedNutrition = $version->cached_nutrition_json ?? [];
        $scaledNutrition = $this->scaleNutritionTotals($cachedNutrition, $scaleFactor);

        $cachedCostPerGram = BigDecimal::of($version->cached_cost_per_gram ?? '0');
        $scaledCostPerGram = $cachedCostPerGram->multipliedBy($scaleFactor);

        return [
            'type' => 'sub_recipe',
            'sub_recipe_version_id' => $version->id,
            'grams' => (string) $lineGrams,
            'energy_kcal' => $scaledNutrition['energy_kcal'] ?? null,
            'protein_g' => $scaledNutrition['protein_g'] ?? null,
            'fat_g' => $scaledNutrition['fat_g'] ?? null,
            'carbs_g' => $scaledNutrition['carbs_g'] ?? null,
            'per_gram_cost' => (string) $scaledCostPerGram->toScale(8, RoundingMode::HALF_UP),
            'allergens' => $this->flattenAllergenSlugs($version->cached_allergen_slugs),
            'yield_pct' => null,
            'is_flour_base' => false,
            'name' => $lineData['name'] ?? null,
        ];
    }

    /**
     * Flatten a cached_allergen_slugs structure ({contains: [], may_contain: []})
     * into the flat {slug, state} list shape the rest of the pipeline expects.
     *
     * @param  array<string, mixed>|null  $cached
     * @return array<int, array{slug: string, state: string}>
     */
    private function flattenAllergenSlugs(?array $cached): array
    {
        if ($cached === null) {
            return [];
        }

        $result = [];

        foreach ($cached['contains'] ?? [] as $slug) {
            $result[] = ['slug' => (string) $slug, 'state' => 'contains'];
        }

        foreach ($cached['may_contain'] ?? [] as $slug) {
            $result[] = ['slug' => (string) $slug, 'state' => 'may_contain'];
        }

        return $result;
    }

    /**
     * Scale nutrition totals from a cached_nutrition_json structure by a BigDecimal factor.
     *
     * @param  array<string, mixed>  $cachedNutrition
     * @return array<string, string> Scaled nutrition values as decimal strings.
     */
    private function scaleNutritionTotals(array $cachedNutrition, BigDecimal $scaleFactor): array
    {
        $total = $cachedNutrition['total'] ?? [];
        $scaled = [];

        foreach ($total as $key => $value) {
            if ($value !== null) {
                $scaled[$key] = BigDecimal::of($value)
                    ->multipliedBy($scaleFactor)
                    ->toScale(4, RoundingMode::HALF_UP)
                    ->__toString();
            }
        }

        return $scaled;
    }
}
