<?php

namespace App\Http\Controllers\Ingredients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ingredients\StorePriceRequest;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Support\Ingredients\PerGramCostCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IngredientPriceController extends Controller
{
    /**
     * Record a new price for an ingredient.
     *
     * Any authenticated user may record a price on any ingredient they can view.
     * Prices are per-user private — user_id is always set to the authenticated user.
     */
    public function store(StorePriceRequest $request, Ingredient $ingredient): RedirectResponse
    {
        // Visibility guard: private ingredients are only accessible to their owner
        if ($ingredient->user_id !== null && $ingredient->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validated();

        $unit = Unit::findOrFail($validated['unit_id']);

        $calculator = new PerGramCostCalculator;

        $perGramCost = $calculator->perGramCost(
            BigDecimal::of($validated['amount']),
            BigDecimal::of($validated['quantity']),
            $unit,
            $ingredient,
        );

        DB::transaction(function () use ($validated, $ingredient, $unit, $perGramCost) {
            $ingredient->prices()->create([
                'user_id' => auth()->id(),
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'quantity' => $validated['quantity'],
                'unit_id' => $unit->id,
                'per_gram_cost' => $perGramCost !== null ? (string) $perGramCost : null,
                'recorded_at' => $validated['recorded_at'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return back()->with('success', __('app.ingredients.price_recorded'));
    }
}
