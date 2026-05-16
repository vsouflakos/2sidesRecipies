<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeSearchController extends Controller
{
    /**
     * Return a unified flat list of ingredients and recipes for the component picker.
     *
     * Ingredients are listed first (using the Phase 2 owner-visibility scope),
     * then the authenticated user's own recipes, both matching the search query.
     * Results are limited to 10 total items.
     *
     * Client-side: apply a 300 ms debounce before calling this endpoint.
     *
     * @return JsonResponse Array of {type, id, name, unit_hint}
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();
        $limit = 10;

        // Ingredients — owner-visibility scope: official (user_id null) or own private
        $ingredients = Ingredient::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', auth()->id()))
            ->when($query, fn ($q) => $q->where('name_cache', 'like', "%{$query}%"))
            ->limit($limit)
            ->get(['id', 'name_cache'])
            ->map(fn (Ingredient $i) => [
                'type' => 'ingredient',
                'id' => $i->id,
                'name' => $i->name_cache,
                'unit_hint' => 'g',
            ]);

        // Recipes — only the auth user's own recipes
        $remaining = $limit - $ingredients->count();

        $recipes = $remaining > 0
            ? Recipe::query()
                ->where('user_id', auth()->id())
                ->when($query, fn ($q) => $q->where('name', 'like', "%{$query}%"))
                ->limit($remaining)
                ->get(['id', 'name'])
                ->map(fn (Recipe $r) => [
                    'type' => 'recipe',
                    'id' => $r->id,
                    'name' => $r->name,
                    'unit_hint' => 'g',
                ])
            : collect();

        return response()->json($ingredients->merge($recipes)->values());
    }
}
