# Phase 6: Publishing & Public Library - Research

**Researched:** 2026-05-18
**Domain:** Laravel 13 + Inertia v3 + React 19 — publish-state lifecycle, guest-accessible routes, policy extension, public recipe page
**Confidence:** HIGH — all findings from direct codebase inspection; no speculation

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- Publishing pins a specific committed version (version picker on first publish; one-click current on update)
- After publishing, new Saves stay pinned — owner is never auto-updated
- Updating the public version is one-click to current (no re-picker)
- Public recipe page is a dedicated page — NOT a reuse of `show.tsx`
- All cost/pricing data hidden from public (cost per portion, total cost, food cost %, selling price)
- Nutrition and allergens stay public
- Chef notes are never published
- Recipe tests and AI conversation are never public
- Sub-recipes must be published first before a recipe containing them can be published
- Public library and public recipe pages are reachable without login (guest routes OUTSIDE `auth`/`verified` middleware)
- Public recipe URLs are slug-based: `/library/{slug}`
- Deleting a published recipe is blocked — owner must unpublish first
- Publish/unpublish controls live in BOTH the recipe builder page AND the My Recipes card menu
- Author name is shown on library cards and public recipe page (no clickable profile pages)
- Default library sort: recently published first
- A new top-level nav entry for the public library

### Claude's Discretion

- Exact nav label ("Public Library" vs "Discover" vs "Explore") and exact route prefix (`/library` vs `/recipes/public`)
- Whether to keep the "ingredient" filter on the public library (it is in `RecipeFilters` but not required by PUB-04)
- Pagination size, search debounce timing
- Exact wording and placement of the "update public version" cue
- Confirmation-dialog wording for unpublish and blocked-delete message
- Visual treatment of the "Published" status badge on My Recipes cards
- Public library empty state

### Deferred Ideas (OUT OF SCOPE)

- Community feed, likes, comments, follows
- External social sharing (Instagram, TikTok forwarding)
- Cloning another chef's published recipe
- Clickable author profile pages
- Popularity/views/trending ranking
- Collaborative comments/annotations (COLLAB-01)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PUB-01 | Recipes are private by default | `recipes` table has no `is_published` column today; a migration adds `is_published`, `published_version_id`, `published_at`. Default = false. `RecipePolicy::view` currently owner-only — must be extended. |
| PUB-02 | User can publish a recipe to the public library | New `PublishRecipeController::store` — validates version choice and sub-recipe-published prerequisite. Adds publish columns migration. |
| PUB-03 | User can unpublish a previously published recipe | New `PublishRecipeController::destroy` — clears publish columns. `delete` policy also needs to block while `is_published = true`. |
| PUB-04 | Users can browse and search the public library of published recipes | New `LibraryController::index` (guest route) + `LibraryController::show` (slug-based, guest route). Reuses `RecipeFilters` component and the `index.tsx` pattern. |
</phase_requirements>

---

## Summary

Phase 6 grafts a publish-state lifecycle onto the existing recipe + version model, then exposes two new guest-accessible surfaces: a public library index and a public recipe show page. The work is architecturally well-prepared — the `recipes` table has a unique `slug` column, `RecipeVersion.snapshot` contains a full draft JSON including ingredient lines (though NOT denormalized nutrition/allergen values, which are stored separately in `cached_nutrition_json` and `cached_allergen_slugs`), and `RecipeListResource` already follows the pattern the public library card needs.

The two most important design points for planning: (1) the `RecipeVersion.snapshot` is the draft JSON blob, which records `ingredient_id` references — it does NOT embed nutrition/allergen data inline. The publish-time snapshot is therefore not automatically self-contained for ingredient data. The cached metrics columns (`cached_nutrition_json`, `cached_allergen_slugs`, `cached_cost_per_gram`) on `RecipeVersion` ARE the frozen artifact that makes a version self-contained — they are computed at commit time and never mutated. (2) The `HandleInertiaRequests::share` method already guards against null users for `permissions` (`$request->user() ? ... : []`), and `auth.user` itself can be null — so shared props are guest-safe today with no changes needed.

**Primary recommendation:** Three new migrations (publish columns on `recipes`, index on `published_at`), one new `PublishRecipeController` (POST publish, DELETE unpublish), one new `LibraryController` (index + show), one new `PublicRecipeResource`, extend `RecipePolicy`, add public routes outside the auth group, add library nav item, and create two new Inertia pages under `resources/js/pages/library/`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | v13 | Route declarations, policy extension, migration, FormRequest | Existing — no additions |
| Inertia Laravel | v3 | `Inertia::render()` for public library pages | Existing — no additions |
| React + Inertia React | v19 / v3 | Public library index and recipe page | Existing — no additions |
| Tailwind v4 / shadcn/ui | v4 | UI components (Card, Badge, Button, Pagination) | Existing — no additions |

No new packages required for this phase. All needed capabilities exist in the current stack.

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Wayfinder | v0 | Type-safe route helpers for new library routes | Required after adding routes — run `php artisan wayfinder:generate` |
| laravel-react-i18n | existing | New translation keys for publish/library UI | Existing i18n infrastructure |

**Installation:** No new packages. After adding routes, regenerate Wayfinder:
```bash
php artisan wayfinder:generate
```

---

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Controllers/Library/
├── LibraryController.php          # index + show (guest routes)
app/Http/Controllers/Recipes/
├── PublishRecipeController.php    # store (publish) + destroy (unpublish)
app/Http/Resources/
├── PublicRecipeListResource.php   # library card: adds author_name, no cost
├── PublicRecipeResource.php       # public show page: nutrition + allergens, no cost/notes
app/Http/Requests/Recipes/
├── PublishRecipeRequest.php       # validates version_id + sub-recipe check
database/migrations/
├── XXXX_add_publish_columns_to_recipes_table.php
resources/js/pages/library/
├── index.tsx                      # public library grid (reuses RecipeCard variant + RecipeFilters)
├── show.tsx                       # public recipe page (dedicated clean page)
resources/js/components/recipes/
├── publish-recipe-dialog.tsx      # version picker for initial publish
tests/Feature/Library/
├── LibraryBrowseTest.php          # PUB-04 guest access, filters, pagination
├── PublishRecipeTest.php          # PUB-01, 02, 03 publish lifecycle
```

### Pattern 1: Publish-State Migration
**What:** Three new nullable columns on `recipes` table.
**When to use:** New feature state stored at the aggregate root.
**Shape:**
```php
// migration: add_publish_columns_to_recipes_table
$table->boolean('is_published')->default(false)->after('selling_price');
$table->unsignedBigInteger('published_version_id')->nullable()->after('is_published');
$table->timestamp('published_at')->nullable()->after('published_version_id');
$table->index('published_at'); // for ORDER BY published_at DESC on library query
```
No FK constraint on `published_version_id` — follows the Phase 3 deferred-FK pattern for circular/nullable FKs. Add it as a plain `unsignedBigInteger` column, add a deferred FK in a separate migration if needed.

### Pattern 2: PublishRecipeController (thin, feature-grouped)
**What:** Dedicated single-responsibility controller for the publish lifecycle.
**When to use:** Actions that change a specific sub-state of a resource (established pattern: RecipeDuplicateController, RecipeDraftController).
```php
// app/Http/Controllers/Recipes/PublishRecipeController.php
class PublishRecipeController extends Controller
{
    // POST recipes/{recipe}/publish
    public function store(PublishRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('update', $recipe);
        // validate sub-recipe prerequisite, then:
        $recipe->update([
            'is_published' => true,
            'published_version_id' => $request->validated()['version_id'],
            'published_at' => now(),
        ]);
        return redirect()->route('recipes.show', $recipe);
    }

    // DELETE recipes/{recipe}/publish
    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('update', $recipe);
        $recipe->update([
            'is_published' => false,
            'published_version_id' => null,
            'published_at' => null,
        ]);
        return redirect()->route('recipes.show', $recipe);
    }
}
```

### Pattern 3: LibraryController (guest-accessible, outside auth group)
**What:** Owner-agnostic controller scoped to published recipes.
**When to use:** Public surfaces that must not require authentication.
```php
// app/Http/Controllers/Library/LibraryController.php
class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        // Mirrors RecipeController::index but scoped to is_published = true
        // across all users, ordered by published_at DESC
        $recipes = Recipe::query()
            ->where('is_published', true)
            ->with(['publishedVersion', 'cuisine', 'tags', 'user'])
            ->when(...)  // same filter pattern as RecipeController::index
            ->orderByDesc('published_at')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Recipe $r) => (new PublicRecipeListResource($r))->resolve());

        return Inertia::render('library/index', [...]);
    }

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
```

### Pattern 4: RecipePolicy Extension
**What:** Extend `view` to allow guests to view published recipes; gate `delete` on publish state.
**Critical:** Policy `view` currently receives `User $user` — Laravel only calls the policy method when a user is authenticated. For guest access, use the policy's `before` hook or declare the method with a nullable user type. In Laravel 13, nullable user is supported:
```php
// app/Policies/RecipePolicy.php
public function view(?User $user, Recipe $recipe): bool
{
    // Anyone can view a published recipe
    if ($recipe->is_published) {
        return true;
    }
    // Only the owner can view a private recipe
    return $user !== null && $recipe->user_id === $user->id;
}

public function delete(User $user, Recipe $recipe): bool
{
    // Block deletion of published recipes
    if ($recipe->is_published) {
        return false;
    }
    return $recipe->user_id === $user->id;
}
```
**Note:** The `LibraryController` does NOT call `Gate::authorize('view', $recipe)` since it filters by `is_published = true` directly in the query — the policy extension is for `RecipeController::show` which still uses `Gate::authorize('view', $recipe)`. The library controller's own scope IS the authorization: only published recipes are returned.

### Pattern 5: Route Declaration (static segments before wildcards)
**What:** Guest-accessible library routes outside the `auth`/`verified` middleware.
```php
// routes/web.php — OUTSIDE the auth group
// Static library routes declared BEFORE any {recipe} wildcard routes (established Phase 2/3 lesson)
Route::get('library', [LibraryController::class, 'index'])->name('library.index');
Route::get('library/{slug}', [LibraryController::class, 'show'])->name('library.show');

// Publish/unpublish stay INSIDE the auth group
Route::middleware(['auth', 'verified'])->group(function () {
    // ... existing routes ...
    Route::post('recipes/{recipe}/publish', [PublishRecipeController::class, 'store'])->name('recipes.publish');
    Route::delete('recipes/{recipe}/publish', [PublishRecipeController::class, 'destroy'])->name('recipes.unpublish');
});
```

### Pattern 6: Public Resource — What to Include/Exclude
**What:** `PublicRecipeListResource` for library cards; `PublicRecipeResource` for the show page.

`PublicRecipeListResource` (library card) — parallel to `RecipeListResource` but:
- ADDS: `author_name` (from `recipe->user->name`)
- REMOVES: `cost_per_portion` (private business data)
- KEEPS: `id`, `name`, `slug`, `hero_image_path`, `cuisine`, `total_time`, `difficulty`, `calories_per_portion`, `allergen_slugs`

`PublicRecipeResource` (public show page) — renders from `publishedVersion`:
- `snapshot` JSON (the frozen draft data at publish time — contains sections, lines, steps, metadata)
- `cached_nutrition_json` (frozen at commit — safe to expose)
- `cached_allergen_slugs` (frozen at commit — safe to expose)
- OMITS: `cached_cost_per_gram`, `cached_cost_per_portion`, `cached_selling_price`, `notes`, tests, conversation
- ADDS: `author_name`, `published_at`

### Pattern 7: Snapshot vs. Cached Metric Columns
**CRITICAL FINDING:** The `RecipeVersion.snapshot` column stores the raw draft JSON (sections with ingredient line objects that have `ingredient_id` references and quantities — NOT embedded nutrition/allergen data). The frozen metric data lives in the **separate cached columns**: `cached_nutrition_json`, `cached_allergen_slugs`, `cached_cost_per_gram`, `cached_cost_per_portion`.

This means:
- The public page for nutrition/allergens reads from `publishedVersion->cached_nutrition_json` and `publishedVersion->cached_allergen_slugs` — both are frozen at commit time and immutable.
- Private ingredient edits/deletions after publish do NOT affect the public page's nutrition/allergen display — the cached values in the pinned version are already frozen.
- **No additional publish-time snapshot is needed for ingredient nutrition/allergen data.** The existing commit-time caching on `RecipeVersion` already provides the self-contained frozen artifact.
- The `snapshot` itself shows ingredient lines with `ingredient_id` references; those IDs may point to deleted/modified ingredients, but the public page should render from `cached_nutrition_json`, NOT by re-resolving ingredient IDs from the live `ingredients` table.

### Anti-Patterns to Avoid
- **Re-resolving ingredient IDs on the public page:** The public page must read nutrition/allergens from `RecipeVersion.cached_nutrition_json` / `cached_allergen_slugs`, not by joining `ingredients`. Those IDs can become stale.
- **Reusing `show.tsx` for the public page:** The public page is a dedicated new page (`library/show.tsx`) — CONTEXT.md is explicit.
- **Putting library routes inside the auth group:** The library index and show MUST be outside `['auth', 'verified']`.
- **Auto-updating the public version:** When the owner commits a new version, `is_published`, `published_version_id`, and `published_at` must NOT be touched.
- **Allowing deletion while published:** `RecipePolicy::delete` must return false when `is_published = true`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Guest policy access | Custom middleware | Nullable `?User $user` in policy method | Laravel 13 natively supports nullable-user policies for guest access |
| Slug uniqueness | Custom UUID slug generation | Already exists: `Str::slug($name).'-'.Str::random(6)` in `RecipeController::store` | Slug is already unique — no new slug logic needed |
| Pagination | Custom paginator | `->paginate(24)->withQueryString()` | Established pattern from `RecipeController::index` |
| Filter panel | New component | `RecipeFilters` component + `RecipeFiltersState` interface | Already handles all PUB-04 filters (name, tag, cuisine, allergen, difficulty, time) |
| Library card | New card component | Extend `RecipeCard` or pass `showAuthor` prop | `RecipeCard` already renders hero image, cuisine, time, difficulty, allergens — only needs `author_name` |

---

## Common Pitfalls

### Pitfall 1: Policy method signature for guest access
**What goes wrong:** `view(User $user, Recipe $recipe)` with non-nullable `User` causes Laravel to skip the policy for unauthenticated requests (returns false by default) instead of allowing published recipe access.
**Why it happens:** Laravel only invokes the policy method for authenticated users when the type hint is non-nullable.
**How to avoid:** Change `view` signature to `view(?User $user, Recipe $recipe)`. Test with an unauthenticated request.
**Warning signs:** `403` on `GET /library/{slug}` when the route is correctly outside the auth group.

### Pitfall 2: Static route `/library` shadowed by wildcard
**What goes wrong:** If `library/{slug}` is declared inside a group where another `{recipe}` wildcard already matches, or before the static segment, `GET /library` fails.
**Why it happens:** Route ordering matters in Laravel. Established lesson from Phase 2/3: static segments must come before wildcards.
**How to avoid:** Declare `Route::get('library', ...)` before `Route::get('library/{slug}', ...)` at top level.

### Pitfall 3: Wayfinder not regenerated after adding library routes
**What goes wrong:** TypeScript imports from `@/routes/library` fail with "module not found"; frontend build breaks.
**Why it happens:** Wayfinder generates TS files from route definitions; new routes need re-generation.
**How to avoid:** Run `php artisan wayfinder:generate` after adding any new named routes. Wave 0 stub pages must exist before the full build to avoid Vite manifest errors (Phase 4 lesson).

### Pitfall 4: Sub-recipe publish prerequisite check — query timing
**What goes wrong:** A sub-recipe ingredient line stores `sub_recipe_version_id` (a FK to `recipe_versions`), not `recipe_id`. The publish validation needs to check whether the *parent recipe* of that version is published.
**Why it happens:** The data model indirection: `RecipeIngredientLine.sub_recipe_version_id` → `RecipeVersion.recipe_id` → `Recipe.is_published`.
**How to avoid:** In `PublishRecipeRequest`, authorize by checking sub-recipe line data from the recipe's `snapshot` on the selected version, then load those `RecipeVersion` rows and their parent `Recipe.is_published`.

### Pitfall 5: `HandleInertiaRequests` shared props — already guest-safe
**What concerns:** `auth.permissions` in `HandleInertiaRequests::share` was a potential null-crash point for guest requests.
**Actual state:** Already guarded — `$request->user() ? $request->user()->getPermissionNames() : []`. No changes needed to `HandleInertiaRequests`.
**Warning sign:** If any future shared prop is added that calls a method on `$request->user()` without null-check, it will crash on public routes.

### Pitfall 6: `RecipeCard` link points to `recipes.show` (owner route, auth-required)
**What goes wrong:** The existing `RecipeCard` uses `show as recipesShow` from `@/routes/recipes` which generates `/recipes/{id}` — an auth-required URL. The library card must link to `/library/{slug}` instead.
**How to avoid:** Either pass a `href` prop override to `RecipeCard`, or create a separate `LibraryRecipeCard` that links to the Wayfinder-generated `library.show` route.

### Pitfall 7: `published_version_id` update-current flow
**What goes wrong:** The "update public version to current" one-click action must update `published_version_id` to `recipe->current_version_id` — if the owner has not yet saved a new version (only has draft changes), `current_version_id` still points to the old committed version, which is correct behavior but may confuse the user.
**How to avoid:** The update-current action should explicitly set `published_version_id = recipe->current_version_id` (the last committed version, not the live draft). Clear in the UI that "current" means last saved version, not working draft.

---

## Code Examples

Verified patterns from direct codebase inspection:

### Existing index filter pattern to mirror for LibraryController::index
```php
// Source: app/Http/Controllers/Recipes/RecipeController.php
$recipes = Recipe::query()
    ->where('user_id', auth()->id())              // <-- replace with: ->where('is_published', true)
    ->with(['currentVersion', 'cuisine', 'tags'])  // <-- replace currentVersion with publishedVersion
    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
    ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
    ->when($cuisine, fn ($q) => $q->where('cuisine_id', $cuisine))
    ->when($allergen, fn ($q) => $q->whereHas('publishedVersion',
        fn ($v) => $v->whereJsonContains('cached_allergen_slugs->contains', $allergen)))
    ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
    ->when($maxTotalTime, fn ($q) => $q->whereRaw(...))
    ->orderByDesc('published_at')                  // <-- replace updated_at with published_at
    ->paginate(24)
    ->withQueryString()
    ->through(fn (Recipe $r) => (new PublicRecipeListResource($r))->resolve());
```

### Existing slug generation (do not change)
```php
// Source: app/Http/Controllers/Recipes/RecipeController.php::store
'slug' => Str::slug($validated['name']).'-'.Str::random(6),
```
The slug is already unique (DB unique index on `recipes.slug`). `GET /library/{slug}` resolves via `Recipe::where('slug', $slug)->where('is_published', true)->firstOrFail()`.

### Policy nullable user pattern (Laravel 13)
```php
// Modify app/Policies/RecipePolicy.php
public function view(?User $user, Recipe $recipe): bool
{
    if ($recipe->is_published) {
        return true;
    }
    return $user !== null && $recipe->user_id === $user->id;
}
```

### HandleInertiaRequests — already guest-safe (no change needed)
```php
// Source: app/Http/Middleware/HandleInertiaRequests.php
'auth' => [
    'user' => $request->user(),               // null for guests — safe
    'permissions' => $request->user()
        ? $request->user()->getPermissionNames()
        : [],                                  // guarded — safe
],
```

### AppSidebar nav item addition pattern
```tsx
// Source: resources/js/components/app-sidebar.tsx
// Add to mainNavItems array:
{
    title: t('app.library.nav'),   // new translation key
    href: libraryIndex().url,       // Wayfinder-generated from new route
    icon: Globe,                    // or Library icon from lucide-react
},
```
The sidebar uses `usePage().props.auth` to conditionally show admin items. The library item is always shown (visible to guests and authenticated users alike). For guests, the sidebar is not rendered at all — the public library page uses a minimal guest layout or no sidebar.

### Translation key structure (EN)
```php
// lang/en/app.php — add under 'library' key (new top-level section)
'library' => [
    'nav' => 'Library',
    'page_title' => 'Public Library',
    // ...
],
// Extend 'recipes' section for publish controls:
'recipes' => [
    // existing keys...
    'publish_btn' => 'Publish',
    'unpublish_btn' => 'Unpublish',
    'published_badge' => 'Published',
    'publish_version_title' => 'Publish recipe',
    'publish_version_body' => 'Choose the version to publish publicly.',
    'publish_confirm' => 'Publish',
    'unpublish_dialog_body' => 'Remove this recipe from the public library?',
    'unpublish_confirm' => 'Unpublish',
    'unpublish_cancel' => 'Keep published',
    'delete_blocked_published' => 'Unpublish this recipe before deleting it.',
    'update_public_version_cue' => 'New version available — update public version?',
    'update_public_version_btn' => 'Update to current',
    'sub_recipe_not_published' => ':name must be published before this recipe can be published.',
],
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `view(User $user, ...)` (non-nullable) | `view(?User $user, ...)` (nullable) | Phase 6 | Enables guest access without custom middleware |
| All recipe routes inside auth group | Library routes outside auth group | Phase 6 | Guest-accessible public library |
| `RecipeCard` links to `recipes.show` | Library cards link to `library.show` | Phase 6 | Correct slug-based public URLs |

---

## Open Questions

1. **Public library page layout for guests**
   - What we know: The app shell uses `AppSidebarLayout` which calls `usePage().props.auth` and renders `NavUser`. For guests, `auth.user` is null — `NavUser` needs to handle null or the library pages must use a different layout.
   - What's unclear: Whether `NavUser` already handles null gracefully, or whether a separate minimal layout is needed for the public pages.
   - Recommendation: Inspect `NavUser` during Wave 0. If it crashes on null user, create a minimal `GuestLayout` (header with logo + nav link to library, no sidebar) for `library/index.tsx` and `library/show.tsx`. Alternatively, hide `NavUser` when `auth.user` is null.

2. **Sub-recipe published prerequisite — snapshot vs. live lines**
   - What we know: `RecipeVersion.snapshot` stores the draft JSON with `sub_recipe_version_id` references. The publish validation checks whether all sub-recipes are published.
   - What's unclear: Whether to check the `snapshot` of the chosen publish version (the pinned version at publish time), or the current live ingredient lines on the recipe model.
   - Recommendation: Check the `snapshot` of the chosen `version_id` being published. This ensures the check is consistent with what the public page will actually render from, not from a more recent draft.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=Library` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PUB-01 | Recipe is private by default; owner-only access | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-01 | Guest cannot access private recipe URL | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-02 | Owner can publish a recipe (picks version) | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-02 | Publish blocked when sub-recipe is unpublished | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-02 | New version commit does not change published version | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-03 | Owner can unpublish; recipe becomes private again | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-03 | Delete blocked while published | Feature | `php artisan test --compact --filter=PublishRecipeTest` | ❌ Wave 0 |
| PUB-04 | Guest can browse library index (200 without auth) | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |
| PUB-04 | Library only shows published recipes | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |
| PUB-04 | Library filters: name, tag, cuisine, allergen, difficulty, time | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |
| PUB-04 | Guest can view public recipe page by slug | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |
| PUB-04 | Public recipe page omits cost, notes, tests | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |
| PUB-04 | 404 for slug of unpublished recipe | Feature | `php artisan test --compact --filter=LibraryBrowseTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=Library`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Library/PublishRecipeTest.php` — covers PUB-01, PUB-02, PUB-03
- [ ] `tests/Feature/Library/LibraryBrowseTest.php` — covers PUB-04
- [ ] `resources/js/pages/library/index.tsx` — stub (prevents Vite manifest error on library.index route test)
- [ ] `resources/js/pages/library/show.tsx` — stub (prevents Vite manifest error on library.show route test)

---

## Sources

### Primary (HIGH confidence)
- Direct inspection: `app/Http/Controllers/Recipes/RecipeController.php` — exact `index` filter pattern, 7 `when()` filters, `paginate(24)`, `RecipeListResource`
- Direct inspection: `app/Models/Recipe.php` — confirmed `slug` (unique), `SoftDeletes`, no `is_published` column today, `current_version_id` FK
- Direct inspection: `app/Models/RecipeVersion.php` — confirmed `snapshot` (array), `cached_nutrition_json` (array), `cached_allergen_slugs` (array), `cached_cost_per_gram`, `cached_cost_per_portion`, `cached_selling_price`
- Direct inspection: `database/migrations/2026_05_17_000003_create_recipes_table.php` — confirmed no publish columns exist
- Direct inspection: `database/migrations/2026_05_17_000007_create_recipe_versions_table.php` — confirmed cached metric columns exist and are frozen at commit
- Direct inspection: `app/Policies/RecipePolicy.php` — confirmed all three methods owner-only, non-nullable User
- Direct inspection: `app/Http/Middleware/HandleInertiaRequests.php` — confirmed `permissions` guard is already guest-safe
- Direct inspection: `routes/web.php` — confirmed all recipe routes inside `['auth','verified']` group; confirmed static-segment-before-wildcard pattern comment
- Direct inspection: `resources/js/components/recipes/recipe-card.tsx` — confirmed `recipesShow(recipe).url` link (needs override for library)
- Direct inspection: `resources/js/components/recipes/recipe-filters.tsx` — confirmed `RecipeFiltersState` interface with all 7 filter fields
- Direct inspection: `resources/js/pages/recipes/index.tsx` — confirmed `router.reload({ only: ['recipes'] })` debounce pattern, 300ms
- Direct inspection: `resources/js/components/app-sidebar.tsx` — confirmed nav item structure, `usePage().props.auth` for permissions
- Direct inspection: `lang/en/app.php` — confirmed `recipes.nav`, `ingredients` as existing nav keys; `library` key does not exist yet
- Direct inspection: `app/Http/Resources/RecipeListResource.php` — confirmed cost_per_portion is included (must be omitted from public resource)
- Direct inspection: `app/Support/Recipes/RecipeVersionService.php` — confirmed metrics are cached at commit time in `RecipeVersion` columns

### Secondary (MEDIUM confidence)
- Laravel 13 docs: nullable policy user type (`?User`) for guest access — established Laravel pattern for public resources

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; direct codebase inspection
- Architecture: HIGH — patterns derived from existing controllers and resources
- Pitfalls: HIGH — all based on confirmed code behavior, not speculation
- Snapshot/caching analysis: HIGH — read both the RecipeVersionService commit method and MetricsAggregator to confirm frozen cached columns vs. live ingredient ID references

**Research date:** 2026-05-18
**Valid until:** 2026-06-18 (stable domain; only invalidated by schema changes to `recipe_versions`)
