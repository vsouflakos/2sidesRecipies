<?php

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\StoreRecipeRequest;
use App\Http\Resources\RecipeBuilderResource;
use App\Http\Resources\RecipeListResource;
use App\Models\Allergen;
use App\Models\Cuisine;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\RecipeSection;
use App\Models\Tag;
use App\Models\Unit;
use App\Support\Recipes\PrismAdapter;
use App\Support\Recipes\RecipeMetricsService;
use App\Support\Recipes\RecipeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function __construct(
        private readonly RecipeVersionService $versionService,
        private readonly RecipeMetricsService $metricsService,
    ) {}

    /**
     * Display a filterable, searchable list of recipes owned by the authenticated user.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $tag = $request->input('tag');
        $cuisine = $request->input('cuisine');
        $allergen = $request->string('allergen')->toString();
        $ingredient = $request->input('ingredient');
        $difficulty = $request->string('difficulty')->toString();
        $maxTotalTime = $request->integer('max_total_time', 0) ?: null;

        $recipes = Recipe::query()
            ->where('user_id', auth()->id())
            ->with(['currentVersion', 'cuisine', 'tags'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->when($cuisine, fn ($q) => $q->where('cuisine_id', $cuisine))
            ->when($allergen, fn ($q) => $q->whereHas('currentVersion', fn ($v) => $v->whereJsonContains('cached_allergen_slugs->contains', $allergen))
            )
            ->when($ingredient, fn ($q) => $q->whereHas('ingredientLines', fn ($l) => $l->where('ingredient_id', $ingredient)))
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
            ->when($maxTotalTime, fn ($q) => $q->whereRaw('(COALESCE(prep_time_minutes, 0) + COALESCE(cook_time_minutes, 0)) <= ?', [$maxTotalTime]))
            ->orderByDesc('updated_at')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Recipe $r) => (new RecipeListResource($r))->resolve());

        return Inertia::render('recipes/index', [
            'recipes' => $recipes,
            'filters' => [
                'search' => $search,
                'tag' => $tag,
                'cuisine' => $cuisine,
                'allergen' => $allergen,
                'ingredient' => $ingredient,
                'difficulty' => $difficulty,
                'max_total_time' => $maxTotalTime,
            ],
            'cuisines' => Cuisine::orderBy('name')->get(['id', 'name', 'slug']),
            'allergens' => Allergen::orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the recipe creation form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('recipes/create', [
            'cuisines' => Cuisine::orderBy('name')->get(['id', 'name', 'slug']),
            'units' => Unit::orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'ingredientCategories' => [],
        ]);
    }

    /**
     * Create a new recipe, its default section, a draft, and commit v1 immediately.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $recipe = DB::transaction(function () use ($validated, $request) {
            /** @var Recipe $recipe */
            $recipe = Recipe::create([
                'user_id' => auth()->id(),
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(6),
                'yield_amount' => $validated['yield_amount'] ?? 1000,
                'portions' => $validated['portions'] ?? 1,
                'prep_time_minutes' => $validated['prep_time_minutes'] ?? null,
                'cook_time_minutes' => $validated['cook_time_minutes'] ?? null,
                'difficulty' => $validated['difficulty'] ?? null,
                'cuisine_id' => $validated['cuisine_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'selling_price' => $validated['selling_price'] ?? null,
            ]);

            // Create the default first section
            RecipeSection::create([
                'recipe_id' => $recipe->id,
                'name' => 'Main',
                'order' => 1,
            ]);

            // Build initial draft data from the request
            $draftData = [
                'name' => $recipe->name,
                'yield_amount' => $recipe->yield_amount,
                'portions' => (int) ($recipe->portions ?? 1),
                'prep_time_minutes' => $recipe->prep_time_minutes,
                'cook_time_minutes' => $recipe->cook_time_minutes,
                'difficulty' => $validated['difficulty'] ?? null,
                'cuisine_id' => $recipe->cuisine_id,
                'notes' => $recipe->notes,
                'selling_price' => $validated['selling_price'] ?? null,
                'sections' => [
                    ['name' => 'Main', 'order' => 1, 'lines' => [], 'steps' => []],
                ],
            ];

            // Create the draft
            $draft = RecipeDraft::create([
                'recipe_id' => $recipe->id,
                'user_id' => auth()->id(),
                'data' => $draftData,
                'edit_sequence' => 0,
            ]);

            // Reload so versionService can access it via relation
            $recipe->load('draft');

            // Commit v1 immediately upon creation
            $this->versionService->commit($recipe, null, $request->user()->id);

            return $recipe;
        });

        return redirect()->route('recipes.show', $recipe);
    }

    /**
     * Show the recipe builder page.
     *
     * Accepts an optional ?portions= query param for view-only metric scaling
     * without touching the draft.
     */
    public function show(Request $request, Recipe $recipe): Response
    {
        Gate::authorize('view', $recipe);

        $recipe->load([
            'sections.ingredientLines',
            'steps',
            'draft',
            'currentVersion',
            'cuisine',
            'tags',
            'latestTest',
        ])->loadCount('tests');

        $draft = $recipe->draft;
        $metrics = null;

        if ($draft !== null) {
            $metrics = $this->metricsService->computeFor($draft);
            // Strip internal cache keys before passing to frontend
            unset($metrics['_raw_nutrition'], $metrics['_raw_cost_per_gram'], $metrics['_raw_allergen_slugs']);
        }

        // Normalize draft data so the frontend always receives sections with steps and lines arrays,
        // and includes edit_sequence from the draft model (stored as a separate DB column, not in data).
        $draftData = null;

        if ($draft !== null) {
            $data = $draft->data ?? [];

            // Ensure each section has both a lines and a steps array, and a non-null name.
            if (isset($data['sections']) && is_array($data['sections'])) {
                $data['sections'] = array_map(function (array $section) {
                    // Null section names crash the React Compiler-precomputed t() call.
                    $section['name'] = $section['name'] ?? '';
                    $section['lines'] = is_array($section['lines'] ?? null) ? $section['lines'] : [];
                    $section['steps'] = is_array($section['steps'] ?? null) ? $section['steps'] : [];

                    // Ensure step instructions are strings (null crashes controlled Textarea).
                    $section['steps'] = array_map(function (array $step) {
                        $step['instruction'] = $step['instruction'] ?? '';

                        return $step;
                    }, $section['steps']);

                    return $section;
                }, $data['sections']);
            }

            // Inject edit_sequence from the draft model row into the data payload
            // so the frontend RecipeDraft type can access it for Recall sequence guard.
            $data['edit_sequence'] = $draft->edit_sequence;

            $draftData = $data;
        }

        $currentVersion = $recipe->currentVersion;

        $versions = $recipe->versions()
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'change_note', 'committed_at'])
            ->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'change_note' => $v->change_note,
                // Frontend RecipeVersion type uses created_at; map committed_at to created_at.
                'created_at' => $v->committed_at,
                'is_current' => $currentVersion !== null && $v->id === $currentVersion->id,
            ])
            ->toArray();

        $testSummary = [
            'count' => $recipe->tests_count,
            'latest_score' => $recipe->latestTest?->overall_rating,
        ];

        return Inertia::render('recipes/show', [
            'recipe' => (new RecipeBuilderResource($recipe))->resolve(),
            'draft' => $draftData,
            'metrics' => $metrics,
            'versions' => $versions,
            'cuisines' => Cuisine::orderBy('name')->get(['id', 'name', 'slug']),
            'units' => Unit::orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'test_summary' => $testSummary,
            'ai_enabled' => app(PrismAdapter::class)->isConfigured() && $request->user()->id === $recipe->user_id,
            'can' => [
                'update' => $request->user()->can('update', $recipe),
                'delete' => $request->user()->can('delete', $recipe),
            ],
        ]);
    }

    /**
     * Soft-delete a recipe and redirect to the list.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        Gate::authorize('delete', $recipe);

        $recipe->delete();

        return redirect()->route('recipes.index');
    }
}
