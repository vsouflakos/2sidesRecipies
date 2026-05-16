<?php

namespace App\Http\Controllers\Ingredients;

use App\Http\Controllers\Controller;
use App\Http\Resources\IngredientDetailResource;
use App\Http\Resources\IngredientListResource;
use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    /**
     * Display a searchable, filterable list of ingredients.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $source = $request->string('source', 'all')->toString();
        $verifiedOnly = $request->boolean('verified_only');
        $allergenFree = $request->input('allergen_free', []);

        $ingredients = Ingredient::query()
            ->with(['translations', 'allergens', 'category'])
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', auth()->id()))
            ->when($search, fn ($q) => $q->whereHas('translations', fn ($t) => $t
                ->when(
                    DB::getDriverName() !== 'sqlite',
                    fn ($w) => $w->whereFullText('name', $search),
                    fn ($w) => $w->where('name', 'like', "%{$search}%")
                )
            ))
            ->when($source === 'official', fn ($q) => $q->whereNull('user_id'))
            ->when($source === 'private', fn ($q) => $q->where('user_id', auth()->id()))
            ->when($verifiedOnly, fn ($q) => $q->where('verified', true))
            ->when($allergenFree, fn ($q) => $q->whereDoesntHave('allergens', fn ($a) => $a->whereIn('slug', (array) $allergenFree)->wherePivot('state', 'contains')
            ))
            ->orderBy('name_cache')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Ingredient $i) => (new IngredientListResource($i))->resolve());

        return Inertia::render('ingredients/index', [
            'ingredients' => $ingredients,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'verified_only' => $verifiedOnly,
                'allergen_free' => (array) $allergenFree,
            ],
            'allergens' => Allergen::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Display the detail page for a single ingredient.
     */
    public function show(Request $request, Ingredient $ingredient): Response
    {
        // Private ingredients of other users must not be viewable.
        if ($ingredient->user_id !== null && $ingredient->user_id !== auth()->id()) {
            abort(404);
        }

        $ingredient->load([
            'translations',
            'allergens',
            'conversions.unit',
            'category.parent',
            'verifiedBy',
            'prices' => fn ($q) => $q->where('user_id', auth()->id())->orderByDesc('recorded_at'),
        ]);

        return Inertia::render('ingredients/show', [
            'ingredient' => (new IngredientDetailResource($ingredient))->resolve(),
            'can' => [
                'verify' => $request->user()->can('verify-ingredients'),
                'manage' => $request->user()->can('update', $ingredient),
            ],
            'units' => Unit::orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
        ]);
    }
}
