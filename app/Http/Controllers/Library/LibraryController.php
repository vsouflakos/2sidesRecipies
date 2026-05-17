<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicRecipeListResource;
use App\Http\Resources\PublicRecipeResource;
use App\Models\Allergen;
use App\Models\Cuisine;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    /**
     * Display the public library index — a filterable, paginated list of all published recipes.
     *
     * No authentication required — the where('is_published', true) scope IS the
     * authorization. Mirrors RecipeController::index filter chain, excluding the
     * ingredient filter (excluded per UI-SPEC for the public library).
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $tag = $request->input('tag');
        $cuisine = $request->input('cuisine');
        $allergen = $request->string('allergen')->toString();
        $difficulty = $request->string('difficulty')->toString();
        $maxTotalTime = $request->integer('max_total_time', 0) ?: null;

        $recipes = Recipe::query()
            ->where('is_published', true)
            ->with(['publishedVersion', 'cuisine', 'tags', 'user'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->when($cuisine, fn ($q) => $q->where('cuisine_id', $cuisine))
            ->when($allergen, fn ($q) => $q->whereHas('publishedVersion', fn ($v) => $v->whereJsonContains('cached_allergen_slugs->contains', $allergen)))
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
            ->when($maxTotalTime, fn ($q) => $q->whereRaw('(COALESCE(prep_time_minutes, 0) + COALESCE(cook_time_minutes, 0)) <= ?', [$maxTotalTime]))
            ->orderByDesc('published_at')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Recipe $r) => (new PublicRecipeListResource($r))->resolve());

        return Inertia::render('library/index', [
            'recipes' => $recipes,
            'filters' => [
                'search' => $search,
                'tag' => $tag,
                'cuisine' => $cuisine,
                'allergen' => $allergen,
                'difficulty' => $difficulty,
                'max_total_time' => $maxTotalTime,
            ],
            'cuisines' => Cuisine::orderBy('name')->get(['id', 'name', 'slug']),
            'allergens' => Allergen::orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Display a single published recipe by its slug.
     *
     * No authentication required — the where('is_published', true) scope IS the
     * authorization; an unpublished or unknown slug yields 404 automatically via firstOrFail().
     */
    public function show(string $slug): Response
    {
        $recipe = Recipe::where('slug', $slug)
            ->where('is_published', true)
            ->with(['publishedVersion', 'cuisine', 'tags', 'user'])
            ->firstOrFail();

        return Inertia::render('library/show', [
            'recipe' => (new PublicRecipeResource($recipe))->resolve(),
        ]);
    }
}
