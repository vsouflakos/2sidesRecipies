---
phase: 06-publishing-public-library
verified: 2026-05-18T17:30:00Z
status: passed
score: 6/6 must-haves verified
human_verified: 2026-05-18 — all 6 items confirmed during the Plan 06-03 human-verify checkpoint (9-step walkthrough); user approved
human_verification:
  - test: "Publish recipe via builder header version-picker dialog"
    expected: "Version-picker dialog opens, selecting a version and confirming shows a Published badge in the header and a 'Recipe published.' toast"
    why_human: "Visual dialog interaction, toast display, and badge render cannot be verified programmatically"
  - test: "Update-to-current cue appears and works"
    expected: "After saving a new version post-publish, the update-cue banner appears below the header. Clicking 'Update to current' fires a toast 'Public version updated.' and the banner disappears"
    why_human: "Conditional banner render and one-click version update flow require live browser interaction"
  - test: "Published badge and disabled Delete in My Recipes card menu"
    expected: "Published recipe card shows 'Published' badge; the card's DropdownMenu Delete item is disabled with tooltip 'Unpublish this recipe before deleting it.'"
    why_human: "Card DropdownMenu interaction and Tooltip display are visual UI behaviors"
  - test: "Guest can browse /library without login redirect"
    expected: "Opening /library in a private/incognito window loads the library page with no redirect to /login"
    why_human: "Requires a real browser session without auth cookies"
  - test: "Public recipe page at /library/{slug} shows correct data and omits private fields"
    expected: "Page shows hero image (or placeholder), author 'by {name}', published date, cuisine badge, ingredient lines with units, steps, and a nutrition Tabs panel. No cost, selling price, food cost %, chef notes, tests, or AI chat appear."
    why_human: "Visual rendering of omitted fields and nutrition tab panel requires browser inspection"
  - test: "Unpublish flow clears badge and removes recipe from /library"
    expected: "Clicking 'Unpublish recipe' in builder → confirmation dialog → confirm clears the Published badge and the recipe no longer appears in /library"
    why_human: "Multi-step dialog confirmation flow and live library state change require browser verification"
---

# Phase 06: Publishing & Public Library — Verification Report

**Phase Goal:** Users can publish their recipes to a public library and browse other chefs' published recipes
**Verified:** 2026-05-18T17:30:00Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Recipes default to private and are not visible to other users until explicitly published | VERIFIED | Migration adds `is_published BOOLEAN DEFAULT FALSE`; factory sets `'is_published' => false`; RecipePolicy::view blocks private recipes for non-owners; PublishRecipeTest "a freshly created recipe has is_published === false" passes |
| 2 | User can publish a recipe to the public library and unpublish it at any time | VERIFIED | PublishRecipeController::store sets 3 publish columns; PublishRecipeController::destroy clears them; recipes.publish (POST) and recipes.unpublish (DELETE) routes confirmed inside auth+verified middleware group; PublishRecipeTest 7/7 pass |
| 3 | Any user can browse and search the public library of published recipes by name, tag, cuisine, allergen, difficulty, and time | VERIFIED | LibraryController::index applies all 6 filters on published-only scope; library.index and library.show routes confirmed outside auth middleware (middleware: ["web"] only); LibraryBrowseTest 11/11 pass covering all filter params and guest access |

**Score:** 6/6 truths verified (each truth has its artifact+wiring chain fully proven)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrations/2026_05_18_000001_add_publish_columns_to_recipes_table.php` | Publish-state columns on recipes table | VERIFIED | Exists; contains `is_published`, `published_version_id`, `published_at`; migration status shows "[2] Ran" |
| `app/Models/Recipe.php` | is_published/published_version_id/published_at fillable+cast + publishedVersion() relation | VERIFIED | All three in `$fillable`; `is_published => boolean`, `published_at => datetime` in casts(); `publishedVersion()` BelongsTo present |
| `app/Policies/RecipePolicy.php` | Guest-viewable published recipes; delete blocked while published | VERIFIED | `view(?User $user, Recipe $recipe)` uses nullable `?User`; `is_published` checked in both `view()` and `delete()`; `update()` unchanged |
| `app/Http/Requests/Recipes/PublishRecipeRequest.php` | Version-choice validation + sub-recipe-published prerequisite | VERIFIED | `Rule::exists('recipe_versions')` scoped to recipe_id; `withValidator` walks snapshot for `sub_recipe_version_id`; throws `HttpResponseException` 422 on unpublished sub-recipe |
| `app/Http/Controllers/Recipes/PublishRecipeController.php` | Publish (store) and unpublish (destroy) lifecycle actions | VERIFIED | `store` sets 3 columns + redirects to recipes.show; `destroy` uses Gate::authorize('update') + clears 3 columns |
| `app/Http/Controllers/Library/LibraryController.php` | Guest-accessible library index + public recipe show | VERIFIED | `index` has 6-filter chain with `where('is_published', true)`, `orderByDesc('published_at')`, no ingredient filter; `show` uses slug + is_published scope + `firstOrFail()` for 404 |
| `app/Http/Resources/PublicRecipeListResource.php` | Library card data — adds author_name, omits cost | VERIFIED | `author_name => $this->user?->name` present; no `cost_per_portion` key; reads from `publishedVersion` |
| `app/Http/Resources/PublicRecipeResource.php` | Public recipe page data — nutrition+allergens, no cost/notes/tests | VERIFIED | `sections`, `nutrition`, `allergen_slugs` present; no `cost_per_portion`, `selling_price`, `notes`, `tests`, or `conversation` keys; resolves snapshot `unit_id` to symbol via Unit lookup map |
| `routes/web.php` | Guest library routes outside auth group + publish routes inside it | VERIFIED | `library.index` and `library.show` declared outside auth group (middleware: ["web"]); `recipes.publish` and `recipes.unpublish` declared inside auth+verified group |
| `tests/Feature/Library/PublishRecipeTest.php` | 7 real-assertion Pest tests covering PUB-01/02/03 | VERIFIED | 205 lines; 7/7 pass; 0 skip() or markTestIncomplete() calls |
| `tests/Feature/Library/LibraryBrowseTest.php` | 11 real-assertion Pest tests covering PUB-04 | VERIFIED | 327 lines; 11/11 pass; covers all 6 filters, guest access, 404 for unpublished, cost redaction, ingredient lines/steps |
| `resources/js/components/recipes/publish-recipe-dialog.tsx` | Version-picker publish dialog | VERIFIED | Exists; imports `store as publishStore` from Wayfinder PublishRecipeController; Select component for version picker; subRecipeError Alert; posts version_id |
| `resources/js/components/recipes/unpublish-recipe-dialog.tsx` | Unpublish confirmation dialog | VERIFIED | Exists; `variant="destructive"` confirm button; DELETEs to unpublish route |
| `resources/js/components/recipes/library-recipe-card.tsx` | Library card with author name, links to library.show | VERIFIED | 114 lines; imports `show as libraryShow` from `@/routes/library`; Link wraps to `libraryShow({ slug }).url`; `author_name` rendered; no cost row |
| `resources/js/layouts/guest-public-layout.tsx` | Minimal guest-safe layout for public pages | VERIFIED | Exists; imports AppLogo; `auth.user === null` check for Sign in link; renders children in max-w-7xl container |
| `resources/js/pages/library/index.tsx` | Full filterable/paginated public library browse page | VERIFIED | 315 lines (>80); imports LibraryRecipeCard, RecipeFilters, GuestPublicLayout; `LibraryIndex.layout = GuestPublicLayout`; no ingredient filter in reload payload |
| `resources/js/pages/library/show.tsx` | Dedicated public recipe page | VERIFIED | 233 lines (>80); imports Tabs, GuestPublicLayout; `LibraryShow.layout = GuestPublicLayout`; no cost/selling_price/notes/conversation/tests matches in grep |
| `app/Http/Resources/RecipeBuilderResource.php` | Emits is_published, published_version_id, current_version_id | VERIFIED | All three keys present in toArray output |
| `app/Http/Resources/RecipeListResource.php` | Emits is_published | VERIFIED | `'is_published' => (bool) $this->is_published` at line 77 |
| `resources/js/types/recipe.ts` | PublicRecipeCardData, PublicRecipeData, is_published on RecipeCardData and RecipeBuilderData | VERIFIED | All types present; `is_published` added to RecipeCardData (line 19) and builder type (line 134); `published_version_id` on builder type |
| `resources/js/components/app-sidebar.tsx` | Library nav entry with Globe icon | VERIFIED | `Globe` from lucide-react; `index as libraryIndex` from `@/routes/library`; entry added to mainNavItems |
| `resources/js/routes/library/index.ts` | Wayfinder library route helpers | VERIFIED | Exports `index` (GET /library) and `show` (GET /library/{slug}) |
| `resources/js/actions/App/Http/Controllers/Recipes/PublishRecipeController.ts` | Wayfinder publish action helpers | VERIFIED | File exists |
| `lang/en/app.php` / `lang/el/app.php` | EN/EL translation parity for library + recipes publish keys | VERIFIED | Both files contain `library` section and `recipes.publish_btn`, `published_badge`, all dialog keys; EL translation present |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `routes/web.php` | `LibraryController` | `library.index` / `library.show` outside auth group | VERIFIED | Route list confirms middleware: ["web"] only — no auth, no verified |
| `routes/web.php` | `PublishRecipeController` | `recipes.publish` / `recipes.unpublish` inside auth+verified | VERIFIED | Route list confirms auth,verified middleware on both routes |
| `PublishRecipeController` | `recipes.is_published` | Recipe::update of publish columns | VERIFIED | `'is_published' => true` in store, `'is_published' => false` in destroy |
| `LibraryController` | `recipes.is_published` | `where('is_published', true)` scope | VERIFIED | Applied in both `index()` and `show()` methods |
| `library-recipe-card.tsx` | `library.show` route | `libraryShow({ slug }).url` Link href | VERIFIED | `import { show as libraryShow } from '@/routes/library'`; used in Link href |
| `publish-recipe-dialog.tsx` | `recipes.publish` route | `router.post(publishStore({ recipe }).url, { version_id })` | VERIFIED | `import { store as publishStore } from '@/actions/.../PublishRecipeController'`; used in confirm handler |
| `library/index.tsx` | `library.index` controller props | `router.reload({ only:['recipes'] })` debounced search | VERIFIED | 315-line page uses router.reload pattern mirroring recipes/index.tsx |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| PUB-01 | 06-01, 06-03 | Recipes are private by default | SATISFIED | `is_published DEFAULT FALSE` in migration; factory explicit default; RecipePolicy::view blocks private recipes for non-owners; PublishRecipeTest PUB-01 case passes |
| PUB-02 | 06-01, 06-02, 06-03 | User can publish a recipe to the public library | SATISFIED | PublishRecipeController::store; version-picker dialog in UI; sub-recipe prerequisite validation; 7/7 publish tests pass including version pinning and sub-recipe rejection |
| PUB-03 | 06-01, 06-02, 06-03 | User can unpublish a previously published recipe | SATISFIED | PublishRecipeController::destroy; RecipePolicy::delete blocks while published; UnpublishRecipeDialog in UI; tests for unpublish and blocked delete both pass |
| PUB-04 | 06-01, 06-02, 06-03 | Users can browse and search the public library of published recipes | SATISFIED | LibraryController with 6 filters; guest-accessible routes; library/index.tsx with all filters; library/show.tsx with cost/notes redaction; 11/11 LibraryBrowseTest pass |

All four PUB requirements satisfied. No orphaned requirements found — all PUB-01..PUB-04 appear in plan frontmatter requirements fields and are marked Complete in REQUIREMENTS.md.

### Anti-Patterns Found

None identified. Scanned all phase key-files:

- No `TODO`, `FIXME`, `PLACEHOLDER`, `return null` stubs, or empty implementations found in the critical path files
- `PublicRecipeResource` and `PublicRecipeListResource` confirmed to never include cost/notes/tests keys (grep returns no matches)
- `library/show.tsx` confirmed free of cost/selling_price/notes (grep returns no matches)
- `LibraryController` has no `ingredient` filter (confirmed absent from query chain)
- Test files have 0 `skip()` or `markTestIncomplete()` calls

### Human Verification Required

#### 1. Publish recipe via builder header version-picker dialog

**Test:** Log in, open a recipe with at least two committed versions in the builder. Confirm a "Publish recipe" button appears in the header action area.
**Expected:** Clicking opens a Dialog with a Select dropdown listing committed versions. Selecting a version and clicking "Publish recipe" posts to `recipes.publish`, then a "Recipe published." toast appears and the header shows a "Published" badge with Globe icon and an "Unpublish recipe" button.
**Why human:** Dialog interaction, version list render, toast display, and badge replacement cannot be verified programmatically.

#### 2. Update-to-current cue banner

**Test:** After publishing, make an edit and save a new version. Observe the header area below the action row.
**Expected:** A banner reading "New version available — update the public version?" appears with an "Update to current" button. Clicking it immediately fires a "Public version updated." toast and the banner disappears.
**Why human:** Conditional banner state depends on `published_version_id !== current_version_id` after a live version commit.

#### 3. Published badge and disabled Delete in My Recipes card menu

**Test:** Navigate to My Recipes. Find the published recipe card. Open the card's DropdownMenu (three-dot or similar trigger).
**Expected:** The card body shows a "Published" badge. The menu contains an "Unpublish recipe" item. The "Delete" item is visually disabled and shows a Tooltip "Unpublish this recipe before deleting it." on hover.
**Why human:** DropdownMenu render, Tooltip display, and aria-disabled visual state require browser interaction.

#### 4. Guest browse of /library without login redirect

**Test:** Open a private/incognito browser window (logged out). Navigate directly to /library.
**Expected:** The library page loads with the GuestPublicLayout (AppLogo top bar, "Library" link, "Sign in" link) and published recipe cards are displayed. No redirect to /login occurs.
**Why human:** Requires an unauthenticated browser session to verify the guest-accessible route works end-to-end.

#### 5. Public recipe page content and cost/notes redaction

**Test:** From /library, click a published recipe card to navigate to /library/{slug}.
**Expected:** Page shows: full-width hero image (or UtensilsCrossedIcon placeholder), recipe name as `<h1>`, "by {author_name}" line, "Published {date}" line, cuisine Badge, ingredient lines with quantity/unit/name resolved, numbered steps, and a Tabs panel ("Per portion" / "Per 100 g") for nutrition. Confirm: NO cost per portion, NO food cost %, NO selling price, NO chef notes section, NO recipe tests panel, NO AI chat UI.
**Why human:** Confirming the absence of private data sections and the correct render of ingredient unit symbols requires visual browser inspection.

#### 6. Unpublish flow removes recipe from library

**Test:** In the builder, click "Unpublish recipe" → confirmation dialog appears → click "Unpublish recipe" confirm button.
**Expected:** The Published badge clears from the header. Navigating to /library confirms the recipe no longer appears in the grid. Navigating directly to the slug URL (/library/{slug}) returns a 404.
**Why human:** Multi-step confirmation dialog + live library state verification require browser interaction.

### Gaps Summary

No gaps found. All automated checks pass across the full artifact chain:

- The schema foundation (migration, model, policy) is correct and the migration has run
- The backend controllers, FormRequest, and resources are fully implemented and correctly wired to routes
- Routes are positioned correctly (library outside auth, publish inside auth+verified)
- Wayfinder helpers are generated and imported in the React components
- The frontend components (dialogs, pages, card, layout) exist with substantive implementations
- All 18 Library tests (7 PublishRecipe + 11 LibraryBrowse) pass with 77 assertions
- EN/EL translations are present; no parity issues detected
- No placeholder implementations, stubs, or anti-patterns found

The only items remaining are the six human-verification flows listed above, which cover the visible UI behavior, guest browser session, and visual content redaction.

---

_Verified: 2026-05-18T17:30:00Z_
_Verifier: Claude (gsd-verifier)_
