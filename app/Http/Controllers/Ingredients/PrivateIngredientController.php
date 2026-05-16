<?php

namespace App\Http\Controllers\Ingredients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ingredients\StoreIngredientRequest;
use App\Http\Requests\Ingredients\UpdateIngredientRequest;
use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PrivateIngredientController extends Controller
{
    /**
     * Show the create private ingredient form.
     *
     * If ?duplicate={id} is provided, loads that ingredient as a pre-fill template.
     */
    public function create(Request $request): Response
    {
        $categories = IngredientCategory::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug']);

        $allergens = Allergen::orderBy('name')->get(['id', 'name', 'slug']);
        $units = Unit::orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']);

        $duplicate = null;

        if ($request->filled('duplicate')) {
            $duplicate = Ingredient::query()
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', auth()->id()))
                ->with(['translations', 'allergens', 'conversions'])
                ->find($request->integer('duplicate'));
        }

        return Inertia::render('ingredients/create', [
            'categories' => $categories,
            'allergens' => $allergens,
            'units' => $units,
            'duplicate' => $duplicate,
        ]);
    }

    /**
     * Store a new private ingredient.
     */
    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $ingredient = DB::transaction(function () use ($validated) {
            $nameEn = $validated['name_en'] ?? $validated['name'] ?? null;
            $nameEl = $validated['name_el'] ?? null;
            $nameCache = $nameEn ?? $nameEl;

            $nutritionFields = [
                'energy_kcal', 'protein_g', 'fat_g', 'saturated_fat_g',
                'monounsaturated_fat_g', 'polyunsaturated_fat_g', 'carbs_g',
                'sugars_g', 'starch_g', 'fibre_g', 'sodium_mg', 'calcium_mg',
                'iron_mg', 'magnesium_mg', 'phosphorus_mg', 'potassium_mg',
                'zinc_mg', 'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg',
                'vitamin_b3_mg', 'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug',
                'vitamin_c_mg', 'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug',
                'cholesterol_mg',
            ];

            $nutritionData = collect($nutritionFields)
                ->filter(fn ($field) => isset($validated[$field]))
                ->mapWithKeys(fn ($field) => [$field => $validated[$field]])
                ->all();

            /** @var Ingredient $ingredient */
            $ingredient = Ingredient::create([
                'user_id' => auth()->id(),
                'category_id' => $validated['category_id'],
                'source' => 'user',
                'source_id' => (string) Str::uuid(),
                'name_cache' => $nameCache,
                ...$nutritionData,
            ]);

            // Create translations for each locale that has a name
            if ($nameEn !== null) {
                $ingredient->translations()->create(['locale' => 'en', 'name' => $nameEn]);
            }

            if ($nameEl !== null) {
                $ingredient->translations()->create(['locale' => 'el', 'name' => $nameEl]);
            }

            // Sync allergens pivot
            if (! empty($validated['allergens'])) {
                $allergenSync = collect($validated['allergens'])
                    ->mapWithKeys(fn ($item) => [
                        $item['allergen_id'] => ['state' => $item['state']],
                    ])
                    ->all();

                $ingredient->allergens()->sync($allergenSync);
            }

            // Create conversion rows
            if (! empty($validated['conversions'])) {
                foreach ($validated['conversions'] as $row) {
                    $ingredient->conversions()->create([
                        'from_amount' => $row['from_amount'],
                        'from_unit_id' => $row['from_unit_id'],
                        'gram_weight' => $row['gram_weight'],
                        'modifier' => $row['modifier'] ?? null,
                        'source' => 'user',
                    ]);
                }
            }

            return $ingredient;
        });

        return redirect()->route('ingredients.index')
            ->with('success', __('app.ingredients.create_toast'));
    }

    /**
     * Show the edit form for a private ingredient.
     */
    public function edit(Ingredient $ingredient): Response
    {
        Gate::authorize('update', $ingredient);

        $categories = IngredientCategory::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug']);

        $allergens = Allergen::orderBy('name')->get(['id', 'name', 'slug']);
        $units = Unit::orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']);

        $ingredient->load(['translations', 'allergens', 'conversions']);

        return Inertia::render('ingredients/create', [
            'categories' => $categories,
            'allergens' => $allergens,
            'units' => $units,
            'ingredient' => $ingredient,
            'isEdit' => true,
        ]);
    }

    /**
     * Update a private ingredient (authorization enforced by UpdateIngredientRequest).
     */
    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $ingredient) {
            $nameEn = $validated['name_en'] ?? $validated['name'] ?? null;
            $nameEl = $validated['name_el'] ?? null;
            $nameCache = $nameEn ?? $nameEl ?? $ingredient->name_cache;

            $nutritionFields = [
                'energy_kcal', 'protein_g', 'fat_g', 'saturated_fat_g',
                'monounsaturated_fat_g', 'polyunsaturated_fat_g', 'carbs_g',
                'sugars_g', 'starch_g', 'fibre_g', 'sodium_mg', 'calcium_mg',
                'iron_mg', 'magnesium_mg', 'phosphorus_mg', 'potassium_mg',
                'zinc_mg', 'vitamin_a_ug', 'vitamin_b1_mg', 'vitamin_b2_mg',
                'vitamin_b3_mg', 'vitamin_b6_mg', 'vitamin_b9_ug', 'vitamin_b12_ug',
                'vitamin_c_mg', 'vitamin_d_ug', 'vitamin_e_mg', 'vitamin_k_ug',
                'cholesterol_mg',
            ];

            $nutritionData = collect($nutritionFields)
                ->filter(fn ($field) => isset($validated[$field]))
                ->mapWithKeys(fn ($field) => [$field => $validated[$field]])
                ->all();

            $ingredient->update([
                'category_id' => $validated['category_id'],
                'name_cache' => $nameCache,
                ...$nutritionData,
            ]);

            // Re-sync translations
            $ingredient->translations()->delete();

            if ($nameEn !== null) {
                $ingredient->translations()->create(['locale' => 'en', 'name' => $nameEn]);
            }

            if ($nameEl !== null) {
                $ingredient->translations()->create(['locale' => 'el', 'name' => $nameEl]);
            }

            // Re-sync allergens
            if (isset($validated['allergens'])) {
                $allergenSync = collect($validated['allergens'])
                    ->mapWithKeys(fn ($item) => [
                        $item['allergen_id'] => ['state' => $item['state']],
                    ])
                    ->all();

                $ingredient->allergens()->sync($allergenSync);
            }

            // Re-create conversion rows
            if (isset($validated['conversions'])) {
                $ingredient->conversions()->delete();

                foreach ($validated['conversions'] as $row) {
                    $ingredient->conversions()->create([
                        'from_amount' => $row['from_amount'],
                        'from_unit_id' => $row['from_unit_id'],
                        'gram_weight' => $row['gram_weight'],
                        'modifier' => $row['modifier'] ?? null,
                        'source' => 'user',
                    ]);
                }
            }
        });

        return redirect()->route('ingredients.index')
            ->with('success', __('app.ingredients.edit_toast'));
    }

    /**
     * Soft-delete a private ingredient.
     */
    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        Gate::authorize('delete', $ingredient);

        $ingredient->delete();

        return redirect()->route('ingredients.index')
            ->with('success', __('app.ingredients.delete_toast'));
    }
}
