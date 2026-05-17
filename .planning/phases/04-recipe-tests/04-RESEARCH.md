# Phase 4: Recipe Tests - Research

**Researched:** 2026-05-17
**Domain:** Laravel file upload, polymorphic-style single-table test records, Inertia modal forms, React photo upload UX
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Test entity model**
- One unified `Test` entity with a `type` field — `trial` or `experiment`.
- Both types share the feedback fields; the experiment type adds extra fields.
- One model, one table, one form (mode-switched), so trials and experiments display together on the recipe.
- Shared on every test — version linkage, tasting notes, photos, structured ratings (overall + per-dimension).
- Experiment-only fields — a hypothesis and a what-changed-vs-expected record.
- Experiment outcome — a free-text outcome narrative plus a structured verdict: Worked / Didn't work / Inconclusive.
- Tests are fully editable and deletable by the recipe owner — standard owner-scoped CRUD.

**Feedback capture**
- Structured ratings use a 1–10 numeric scale.
- Rating dimensions: fixed defaults + custom additions. Chef can add a custom dimension on an individual test.
- Overall score + optional per-dimension scores. One required overall rating (1–10); per-dimension scores are optional.
- Tasting notes — free-text, on every test.
- What-changed-vs-expected (experiments) — recorded as structured change rows (what changed / expected effect / actual effect).

**UI placement & version linkage**
- Dedicated tests page per recipe — new `/recipes/{recipe}/tests` route.
- Recording a test happens in a modal dialog — consistent with app's existing dialog patterns.
- Version linkage: default to current, pickable. Always stores exact `recipe_version_id`.
- The builder (`recipes/show.tsx`) surfaces a compact test summary (test count + latest overall rating) that links to the full tests page.

**Photo handling**
- Multiple photos per test, with a sensible cap (exact cap is Claude's discretion).
- Storage: local `public` disk, disk name read from filesystem config (not hard-coded).
- Upload UX: drag-drop + file picker, upload on form submit. Cancelled modal leaves no orphaned files.
- Display: thumbnail grid + click-to-enlarge lightbox on the saved test record.

### Claude's Discretion
- Exact default rating dimension list (taste, texture, appearance, aroma, …) and how custom dimensions are stored.
- Exact photo cap number, accepted image formats, max file size, and whether thumbnails are generated server-side.
- Tests list organization on the tests page (chronological vs grouped by version, sorting, any filtering).
- Data model / schema for tests, ratings, change rows, and photos — table structure, JSON vs relational for ratings/change-rows.
- Empty-state design for a recipe with no tests yet.
- Whether the version history sheet additionally shows a per-version test count.
- Exact verdict enum naming and i18n strings.

### Deferred Ideas (OUT OF SCOPE)
- AI agent reading test feedback to suggest improvements / variants (AI-01…07) — Phase 5.
- Applying a test outcome back into the recipe draft — Phase 5's agent flow.
- Wiring up Phase 3's `hero_image_path` / `step_image_path` to real uploads.
- Publishing tests / sharing test results publicly — Phase 6.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TEST-01 | User can record a trial run against a specific recipe version | Unified `Test` model with `type = trial`, version picker defaulting to current version, `RecipeTestController::store`, Pest feature test for POST /recipes/{recipe}/tests |
| TEST-02 | User can record a structured experiment with a hypothesis and an outcome | Same model, `type = experiment`, `hypothesis` + `outcome_narrative` + `verdict` columns, mode-switched form on the frontend |
| TEST-03 | A test captures tasting notes, photos, and structured ratings | `tasting_notes` text column, `overall_rating` integer, ratings JSON or relational table for per-dimension, photos stored via Laravel Storage public disk with `recipe_test_photos` table |
| TEST-04 | A test records what changed versus what was expected | `change_rows` JSON column (array of `{what_changed, expected_effect, actual_effect}`) on experiment tests, machine-readable for Phase 5 |
</phase_requirements>

---

## Summary

Phase 4 introduces three interconnected features built on the existing Phase 3 recipe/version infrastructure: a single-table `Test` model, a photo upload pipeline (the app's first real file storage feature), and a dedicated tests page with a record-test modal. All three are well-supported by the existing stack — no new packages are needed.

The most technically novel part is the photo upload feature. Laravel's `Storage` facade with the `public` disk is already configured (`config/filesystems.php`). The disk name should be read from config, not hard-coded. `php artisan storage:link` must run once in dev to create the `public/storage` symlink. On the frontend, the drag-drop photo picker must be a pure React component (no `router.visit` involvement during photo selection); the actual upload happens as a multipart POST when the form is submitted.

The data schema decision with the highest downstream consequence is whether ratings and change rows are relational (separate tables) or JSON columns. Given the small cardinality (typically 4–5 default dimensions + optional custom, and 1–6 change rows), JSON columns are the right call here — they avoid three extra joins on every test list load and are sufficient for Phase 5's AI agent to read. The verdict should be a PHP backed enum to stay consistent with `Difficulty`.

**Primary recommendation:** Use JSON columns for ratings (array of `{dimension, score}`) and change rows (array of `{what_changed, expected_effect, actual_effect}`). Use a separate `recipe_test_photos` table (not JSON) for photos because photo management (add/remove individual photos) needs row-level deletes. One migration per new table: `recipe_tests`, `recipe_test_photos`.

---

## Standard Stack

### Core (no new packages — everything already installed)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Storage | Laravel 13 | File upload / retrieval via public disk | Already configured; disk-agnostic API means S3 swap is config-only |
| Pest v4 | 4.7 | Feature tests for all new routes | Already installed; existing tests are the pattern |
| shadcn/ui dialog | (already in repo) | Record-test modal | `dialog.tsx` already exists in `components/ui/` |
| shadcn/ui toggle-group | (already in repo) | Trial / Experiment type toggle | `toggle-group.tsx` already exists |
| shadcn/ui tabs | (already in repo) | Optional tab organization on tests page | `tabs.tsx` already exists |
| shadcn/ui skeleton | (already in repo) | Loading state for deferred props | `skeleton.tsx` already exists |
| Inertia v3 router | 3.0 | Multipart form submission with photos | `useForm` + `post()` handles `multipart/form-data` |

### No New Packages Required

The full stack for this phase already exists. Installing new packages requires user approval per CLAUDE.md conventions.

**Installation needed:** None.

**One Artisan command needed (one-time dev setup):**
```bash
php artisan storage:link
```

---

## Architecture Patterns

### Recommended Project Structure (new files only)

```
app/
├── Enums/
│   └── TestVerdict.php                          # Worked / DidntWork / Inconclusive
├── Http/
│   ├── Controllers/Recipes/
│   │   └── RecipeTestController.php             # index, store, update, destroy
│   └── Requests/Recipes/
│       ├── StoreRecipeTestRequest.php
│       └── UpdateRecipeTestRequest.php
├── Models/
│   ├── RecipeTest.php
│   └── RecipeTestPhoto.php
└── Policies/
    └── RecipeTestPolicy.php                     # owner-scoped; view/update/delete

database/
└── migrations/
    ├── 2026_05_17_000011_create_recipe_tests_table.php
    └── 2026_05_17_000012_create_recipe_test_photos_table.php

database/factories/
├── RecipeTestFactory.php
└── RecipeTestPhotoFactory.php

resources/js/
├── pages/recipes/tests/
│   └── index.tsx                               # Tests list page
├── components/recipes/
│   ├── test-record-modal.tsx                   # Record / Edit dialog
│   ├── test-card.tsx                           # Individual test display card
│   ├── test-photo-grid.tsx                     # Thumbnail grid + lightbox trigger
│   └── test-summary-block.tsx                  # Compact summary for show.tsx
└── types/
    └── recipe-test.ts                          # TypeScript types for all test shapes

tests/Feature/Recipes/
└── RecipeTestTest.php                          # Feature tests covering TEST-01..04
```

### Pattern 1: Single-Table Test with Type Discriminator

The `recipe_tests` table uses a `type` column to distinguish trial from experiment. The Eloquent model exposes both types via scopes.

```php
// app/Models/RecipeTest.php — key shape
protected $fillable = [
    'recipe_id', 'recipe_version_id', 'user_id', 'type',
    'tasting_notes', 'overall_rating', 'ratings',
    'hypothesis', 'outcome_narrative', 'verdict', 'change_rows',
    'tested_at',
];

protected function casts(): array
{
    return [
        'type'        => TestType::class,      // backed enum: trial|experiment
        'verdict'     => TestVerdict::class,   // backed enum: worked|didnt_work|inconclusive
        'ratings'     => 'array',              // [{dimension: string, score: int}]
        'change_rows' => 'array',              // [{what_changed, expected_effect, actual_effect}]
        'tested_at'   => 'datetime',
        'overall_rating' => 'integer',
    ];
}
```

`hypothesis`, `outcome_narrative`, `verdict`, and `change_rows` are nullable — they are only populated when `type = experiment`.

### Pattern 2: Separate Photos Table (Row-Level Deletes)

```php
// database/migrations/2026_05_17_000012_create_recipe_test_photos_table.php
Schema::create('recipe_test_photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_test_id')->constrained('recipe_tests')->cascadeOnDelete();
    $table->string('path');           // relative to disk root, e.g. 'recipe-tests/123/photo-uuid.jpg'
    $table->unsignedInteger('order')->default(0);
    $table->timestamps();
});
```

The controller stores uploaded files, creates `RecipeTestPhoto` rows, and on delete cascades automatically. Orphan prevention: photos only persist after successful test creation — the controller wraps store in a DB transaction; if anything fails, uploaded files are cleaned up in a `finally` block via `Storage::disk(config('filesystems.default'))->delete(...)`.

**Reading disk name from config (never hard-code):**
```php
$disk = config('filesystems.default', 'public');
$path = $request->file('photo')->store('recipe-tests/'.$test->id, $disk);
```

### Pattern 3: Multipart Form Submission via Inertia useForm

Inertia v3's `useForm` hook supports file uploads natively. When the form contains files, Inertia automatically submits as `multipart/form-data`.

```typescript
// Inertia v3 multipart pattern (source: Inertia v3 docs)
const form = useForm({
    type: 'trial' as 'trial' | 'experiment',
    recipe_version_id: currentVersionId,
    overall_rating: 5,
    tasting_notes: '',
    ratings: [] as RatingDimension[],
    photos: [] as File[],
    // experiment-only
    hypothesis: '',
    outcome_narrative: '',
    verdict: null as string | null,
    change_rows: [] as ChangeRow[],
});

// Submit with files — Inertia detects File objects and sends multipart
form.post(route('recipes.tests.store', { recipe: recipeId }), {
    forceFormData: true,  // explicit safety: always multipart even if no files yet
});
```

**Key insight:** `forceFormData: true` ensures multipart encoding even when a user submits without photos. Without it, if all photos are removed, Inertia might fall back to JSON and the Laravel controller's `$request->files` will be empty.

### Pattern 4: Owner-Scoped Policy (mirrors RecipePolicy)

```php
// app/Policies/RecipeTestPolicy.php
public function view(User $user, RecipeTest $test): bool
{
    return $test->recipe->user_id === $user->id;
}

public function update(User $user, RecipeTest $test): bool
{
    return $test->recipe->user_id === $user->id;
}

public function delete(User $user, RecipeTest $test): bool
{
    return $test->recipe->user_id === $user->id;
}
```

Authorization in the controller uses `Gate::authorize()` (not `$this->authorize()`) — matches existing `RecipeController` pattern since the base `Controller` does not carry `AuthorizesRequests` trait.

### Pattern 5: Controller Thin, Feature-Grouped

```php
// app/Http/Controllers/Recipes/RecipeTestController.php — method signatures
public function index(Request $request, Recipe $recipe): Response      // GET /recipes/{recipe}/tests
public function store(StoreRecipeTestRequest $request, Recipe $recipe): RedirectResponse
public function update(UpdateRecipeTestRequest $request, Recipe $recipe, RecipeTest $test): RedirectResponse
public function destroy(Request $request, Recipe $recipe, RecipeTest $test): RedirectResponse
```

`index` returns `Inertia::render('recipes/tests/index', [...])`. Mutations return `redirect()->route('recipes.tests.index', $recipe)` — consistent with Inertia redirect-not-JSON pattern.

### Pattern 6: Route Declaration (Static Before Wildcard)

The existing `routes/web.php` recipe block already respects static-before-wildcard ordering. New test routes slot in before the `{recipe}` wildcard catch-alls:

```php
// Slot BEFORE the existing 'recipes/{recipe}' wildcard
Route::get('recipes/{recipe}/tests', [RecipeTestController::class, 'index'])->name('recipes.tests.index');
Route::post('recipes/{recipe}/tests', [RecipeTestController::class, 'store'])->name('recipes.tests.store');
Route::put('recipes/{recipe}/tests/{test}', [RecipeTestController::class, 'update'])->name('recipes.tests.update');
Route::delete('recipes/{recipe}/tests/{test}', [RecipeTestController::class, 'destroy'])->name('recipes.tests.destroy');
```

### Pattern 7: Tests Page — Chronological by Default

Based on Claude's discretion, the tests page shows:
- Tests ordered by `tested_at DESC` (most recent first).
- Type badge (Trial / Experiment) + verdict badge on experiment cards.
- Empty state: warm-minimal illustration + "Record your first test" CTA.
- No filtering on the tests page in Phase 4 (keep it simple; filtering is a Phase 5+ concern unless very few records makes it pointless).

### Pattern 8: Default Rating Dimensions

Based on Claude's discretion:
- **Default dimensions:** Taste, Texture, Appearance, Aroma (4 dimensions covering the core sensory spectrum).
- Custom dimensions are stored inline in the same `ratings` JSON array alongside defaults — no separate table needed. Each rating row carries `{dimension: string, score: int | null, is_custom: boolean}`. The `is_custom` flag lets Phase 5's AI know which dimensions are standard vs chef-specific.
- Photo cap: **8 photos** per test (covers process shots + finished dish with headroom). Accepted formats: JPEG, PNG, WebP. Max size: 5MB per photo. No server-side thumbnail generation in Phase 4 — use CSS `object-fit: cover` on img elements for consistent thumbnail sizing.

### Anti-Patterns to Avoid

- **Hand-rolling multipart upload with fetch():** Inertia's `useForm` handles this; do not bypass it for photo submission.
- **Hard-coding `'public'` disk name:** Always `config('filesystems.default', 'public')` so a future S3 migration is config-only.
- **Storing photos in the JSON `ratings` column:** Photos need row-level lifecycle management; they belong in a separate table.
- **Relational tables for ratings/change-rows:** Creates 3+ join overhead per test list render for small fixed-cardinality data. JSON columns handle Phase 5 AI reads just fine.
- **Axios for multipart:** Axios was removed in Inertia v3. Use `useForm` exclusively.
- **`$this->authorize()` in controllers:** Base `Controller` lacks `AuthorizesRequests` trait. Use `Gate::authorize()`.
- **Inertia mutations returning JSON:** Mutations must `return redirect()` — not `response()->json()` — so Inertia's prop-refresh cycle fires.
- **Missing `php artisan storage:link`:** Without the symlink, uploaded photos return 404. The plan must include this step for first-run dev setup.
- **Forgetting `forceFormData: true`:** If the user saves a test with no photos, Inertia may submit JSON instead of multipart and `$request->file()` returns null even when photos existed before.
- **Photo orphans on cancelled modal:** Never upload on file selection; always upload atomically on form submit. A cancelled modal must leave no files in storage.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| File storage | Custom filesystem abstraction | `Storage::disk(config(...))` | Laravel's disk API is already config-driven and S3-compatible |
| Drag-drop upload | Custom JS drag-drop handler | Browser `<input type="file" multiple>` + dragover/drop events on a wrapper div | Sufficient for Phase 4; no extra package needed |
| Lightbox / image enlarge | Custom modal | Native `<dialog>` or shadcn `Dialog` with `<img>` | A full-screen image in a dialog is two dozen lines of React |
| Multipart form | `fetch()` or `axios` | Inertia `useForm` with `forceFormData: true` | Inertia v3 removed Axios; `useForm` encodes File objects natively |
| Policy enforcement | Manual `$recipe->user_id === auth()->id()` in controller | `Gate::authorize('view', $test)` | Consistent with Phase 3 pattern; centralizes auth logic |
| Verdict status display | Custom status logic | PHP backed enum `TestVerdict` + badge map in TS | Consistent with `Difficulty` enum pattern already in the codebase |

**Key insight:** The "first file-upload feature" is deceptively simple in Laravel. The only non-obvious steps are: `storage:link`, reading disk from config, using `forceFormData: true` in Inertia, and wrapping photo persistence in a transaction.

---

## Common Pitfalls

### Pitfall 1: Photo Orphans on Exception

**What goes wrong:** A test record is created but an exception occurs while storing photos, leaving orphaned files in `storage/app/public/recipe-tests/`.
**Why it happens:** File storage and DB write are not wrapped in a single atomic unit.
**How to avoid:** Wrap `DB::transaction()` around the entire store action. Collect stored paths in an array; if the transaction rolls back or any step fails, delete the collected paths in a `try/finally`.
**Warning signs:** Files accumulating in `storage/app/public/recipe-tests/` with no matching DB rows.

### Pitfall 2: Missing Storage Symlink in Dev

**What goes wrong:** Photos upload successfully (200 response) but the browser returns 404 for the image URL.
**Why it happens:** `public/storage` symlink doesn't exist until `php artisan storage:link` is run.
**How to avoid:** Include `php artisan storage:link` in Wave 0 setup steps. Verify by checking that `public/storage` is a symlink pointing to `storage/app/public`.
**Warning signs:** `Storage::url()` returns a URL with `/storage/...` path that 404s in the browser.

### Pitfall 3: Inertia Submitting JSON Instead of Multipart

**What goes wrong:** Photo files are not received by the controller (`$request->files` is empty) even though the user selected photos.
**Why it happens:** Inertia v3's `useForm.post()` falls back to JSON encoding when no `File` objects are present at submit time. If the user removes all photos from the selection, the form reverts to JSON.
**How to avoid:** Always pass `forceFormData: true` in the submit options for any form that may include photos.
**Warning signs:** `$request->hasFile('photos')` is always false; no files in `$_FILES`.

### Pitfall 4: Policy on Nested Resource

**What goes wrong:** A user calls `DELETE /recipes/{recipe}/tests/{test}` on a test belonging to another recipe, and the policy passes because it only checks `test.user_id`.
**Why it happens:** The `RecipeTest` model may not have a direct `user_id`; ownership flows through `recipe.user_id`.
**How to avoid:** Policy checks `$test->recipe->user_id === $user->id`. Load the `recipe` relation eager in the controller to avoid N+1. Alternatively scope the test query in the controller: `$recipe->tests()->findOrFail($test->id)` before authorizing.
**Warning signs:** Tests from one recipe are accessible via another recipe's URL.

### Pitfall 5: Route Wildcard Shadowing

**What goes wrong:** `GET /recipes/{recipe}/tests` is matched by `GET /recipes/{recipe}` if the test route is declared after the show route.
**Why it happens:** The existing `recipes/{recipe}` wildcard in `web.php` will consume any segment after `{recipe}` unless static-segment routes come first.
**How to avoid:** Declare all `recipes/{recipe}/tests` routes before `Route::get('recipes/{recipe}', ...)` in `web.php`.
**Warning signs:** Tests page renders the recipe builder instead of the tests index; 404 on tests routes.

### Pitfall 6: SQLite JSON Column Compatibility in Tests

**What goes wrong:** `whereJsonContains` or JSON casting on `ratings` / `change_rows` columns errors in the SQLite test database.
**Why it happens:** SQLite has limited JSON function support compared to MySQL. However, Laravel's `'array'` cast (serialize/unserialize via JSON) works fine for read/write in tests. The issue only arises if you write raw `whereJsonContains` queries.
**How to avoid:** For the test suite, use Eloquent casting rather than raw JSON SQL queries on `ratings` and `change_rows`. Only use JSON path queries when Phase 5 requires it, and add a DB driver guard if needed.
**Warning signs:** `SQLSTATE[HY000]: General error: 1 no such function: JSON_CONTAINS` in test runs.

---

## Code Examples

### Migration: recipe_tests table

```php
// database/migrations/2026_05_17_000011_create_recipe_tests_table.php
Schema::create('recipe_tests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
    $table->foreignId('recipe_version_id')->constrained('recipe_versions')->restrictOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('type');                        // 'trial' | 'experiment'
    $table->timestamp('tested_at');
    $table->text('tasting_notes')->nullable();
    $table->unsignedTinyInteger('overall_rating'); // 1–10
    $table->json('ratings')->nullable();           // [{dimension, score, is_custom}]
    // Experiment-only (nullable on trials)
    $table->text('hypothesis')->nullable();
    $table->text('outcome_narrative')->nullable();
    $table->string('verdict')->nullable();         // 'worked' | 'didnt_work' | 'inconclusive'
    $table->json('change_rows')->nullable();        // [{what_changed, expected_effect, actual_effect}]
    $table->timestamps();
});
```

### Migration: recipe_test_photos table

```php
// database/migrations/2026_05_17_000012_create_recipe_test_photos_table.php
Schema::create('recipe_test_photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_test_id')->constrained('recipe_tests')->cascadeOnDelete();
    $table->string('path');           // relative path within disk, e.g. 'recipe-tests/5/abc123.jpg'
    $table->unsignedSmallInteger('order')->default(0);
    $table->timestamps();
});
```

### Enums

```php
// app/Enums/TestType.php
enum TestType: string
{
    case Trial = 'trial';
    case Experiment = 'experiment';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial Run',
            self::Experiment => 'Experiment',
        };
    }
}

// app/Enums/TestVerdict.php
enum TestVerdict: string
{
    case Worked = 'worked';
    case DidntWork = 'didnt_work';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Worked => 'Worked',
            self::DidntWork => "Didn't work",
            self::Inconclusive => 'Inconclusive',
        };
    }
}
```

### StoreRecipeTestRequest validation

```php
// app/Http/Requests/Recipes/StoreRecipeTestRequest.php
public function rules(): array
{
    return [
        'type'               => ['required', Rule::enum(TestType::class)],
        'recipe_version_id'  => ['required', 'integer', 'exists:recipe_versions,id'],
        'tested_at'          => ['required', 'date'],
        'overall_rating'     => ['required', 'integer', 'min:1', 'max:10'],
        'tasting_notes'      => ['nullable', 'string', 'max:5000'],
        'ratings'            => ['nullable', 'array', 'max:20'],
        'ratings.*.dimension'=> ['required_with:ratings.*', 'string', 'max:100'],
        'ratings.*.score'    => ['nullable', 'integer', 'min:1', 'max:10'],
        'ratings.*.is_custom'=> ['boolean'],
        // experiment-only
        'hypothesis'         => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn() => $this->input('type') === 'experiment')],
        'outcome_narrative'  => ['nullable', 'string', 'max:5000'],
        'verdict'            => ['nullable', Rule::enum(TestVerdict::class)],
        'change_rows'        => ['nullable', 'array', 'max:20'],
        'change_rows.*.what_changed'    => ['required_with:change_rows.*', 'string', 'max:500'],
        'change_rows.*.expected_effect' => ['nullable', 'string', 'max:500'],
        'change_rows.*.actual_effect'   => ['nullable', 'string', 'max:500'],
        // photos
        'photos'             => ['nullable', 'array', 'max:8'],
        'photos.*'           => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
    ];
}
```

### Controller store action (key shape)

```php
// app/Http/Controllers/Recipes/RecipeTestController.php
public function store(StoreRecipeTestRequest $request, Recipe $recipe): RedirectResponse
{
    Gate::authorize('update', $recipe);

    $validated = $request->validated();
    $storedPaths = [];

    try {
        $test = DB::transaction(function () use ($validated, $request, $recipe, &$storedPaths) {
            $test = RecipeTest::create([
                'recipe_id'         => $recipe->id,
                'recipe_version_id' => $validated['recipe_version_id'],
                'user_id'           => auth()->id(),
                'type'              => $validated['type'],
                'tested_at'         => $validated['tested_at'],
                'tasting_notes'     => $validated['tasting_notes'] ?? null,
                'overall_rating'    => $validated['overall_rating'],
                'ratings'           => $validated['ratings'] ?? null,
                'hypothesis'        => $validated['hypothesis'] ?? null,
                'outcome_narrative' => $validated['outcome_narrative'] ?? null,
                'verdict'           => $validated['verdict'] ?? null,
                'change_rows'       => $validated['change_rows'] ?? null,
            ]);

            $disk = config('filesystems.default', 'public');

            foreach ($request->file('photos', []) as $order => $photo) {
                $path = $photo->store('recipe-tests/'.$test->id, $disk);
                $storedPaths[] = $path;
                RecipeTestPhoto::create([
                    'recipe_test_id' => $test->id,
                    'path'           => $path,
                    'order'          => $order,
                ]);
            }

            return $test;
        });
    } catch (\Throwable $e) {
        $disk = config('filesystems.default', 'public');
        foreach ($storedPaths as $path) {
            Storage::disk($disk)->delete($path);
        }
        throw $e;
    }

    return redirect()->route('recipes.tests.index', $recipe);
}
```

### Frontend: Inertia useForm with photos

```typescript
// resources/js/components/recipes/test-record-modal.tsx (key pattern)
import { useForm } from '@inertiajs/react';

const form = useForm({
    type: 'trial' as 'trial' | 'experiment',
    recipe_version_id: props.currentVersionId,
    tested_at: new Date().toISOString().split('T')[0],
    overall_rating: 5,
    tasting_notes: '',
    ratings: defaultDimensions.map(d => ({ dimension: d, score: null, is_custom: false })),
    photos: [] as File[],
    hypothesis: '',
    outcome_narrative: '',
    verdict: null as string | null,
    change_rows: [] as ChangeRow[],
});

function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    form.post(route('recipes.tests.store', { recipe: props.recipeId }), {
        forceFormData: true,
        onSuccess: () => props.onClose(),
    });
}
```

### TypeScript types

```typescript
// resources/js/types/recipe-test.ts
export type TestType = 'trial' | 'experiment';
export type TestVerdict = 'worked' | 'didnt_work' | 'inconclusive';

export interface RatingDimension {
    dimension: string;
    score: number | null;
    is_custom: boolean;
}

export interface ChangeRow {
    what_changed: string;
    expected_effect: string | null;
    actual_effect: string | null;
}

export interface RecipeTestPhoto {
    id: number;
    path: string;
    url: string;    // Storage::url(path) resolved server-side
    order: number;
}

export interface RecipeTest {
    id: number;
    recipe_id: number;
    recipe_version_id: number;
    version_number: number;  // joined from recipe_versions
    type: TestType;
    tested_at: string;
    tasting_notes: string | null;
    overall_rating: number;
    ratings: RatingDimension[] | null;
    hypothesis: string | null;
    outcome_narrative: string | null;
    verdict: TestVerdict | null;
    change_rows: ChangeRow[] | null;
    photos: RecipeTestPhoto[];
    created_at: string;
    updated_at: string;
}

export interface RecipeTestsIndexProps {
    recipe: { id: number; name: string; current_version_id: number | null };
    tests: RecipeTest[];
    versions: { id: number; version_number: number; committed_at: string }[];
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Axios for file upload in Inertia | Inertia `useForm` with `forceFormData: true` | Inertia v3 removed Axios | No separate HTTP client needed |
| `Inertia::lazy()` for deferred props | `Inertia::optional()` | Inertia v3 | `lazy()` removed — tests page can use `optional()` if deferred loading needed |
| Separate thumbnail generation (Intervention Image etc.) | CSS `object-fit: cover` for display | Always available | No image processing package needed in Phase 4 |
| `$this->authorize()` in controllers | `Gate::authorize()` | Phase 3 established | Base `Controller` lacks `AuthorizesRequests`; `Gate` facade works identically |

**Deprecated/outdated for this project:**
- `Inertia::lazy()` → replaced by `Inertia::optional()` in v3
- `router.cancel()` → replaced by `router.cancelAll()` in v3
- Hard-coded disk names → always read from `config('filesystems.default')`

---

## Open Questions

1. **Photo deletion on edit (update action)**
   - What we know: The update form should show existing photos and allow adding/removing them.
   - What's unclear: How to handle deletions of existing photos — the simplest approach is to accept a `deleted_photo_ids[]` array in the update request and delete those `RecipeTestPhoto` rows + disk files.
   - Recommendation: Include `deleted_photo_ids` as an optional array in `UpdateRecipeTestRequest`. Plan a dedicated task for the update flow since it's more complex than the store flow.

2. **Tests summary block data shape on `recipes/show.tsx`**
   - What we know: The builder needs test count + latest overall rating. This requires a DB query on every recipe show load.
   - What's unclear: Whether to eager-load via a new `tests()` relation on `RecipeController::show()` (adds a query) or use a `withCount` + `withAggregate` approach.
   - Recommendation: Add `withCount('tests')` and a `latestTest` `HasOne` (ordered by `tested_at DESC`) to the Recipe model. Load in `RecipeController::show()` so the compact summary block renders without an extra request. This is a single aggregate query.

3. **`tested_at` date vs. timestamp**
   - What we know: The chef should be able to log a test retroactively (e.g., yesterday's kitchen trial). A date-only field is friendlier than a full datetime picker.
   - What's unclear: Whether Phase 5's AI agent needs timestamp precision.
   - Recommendation: Store as `timestamp` in the DB (full precision, nullable time portion), accept date string from the frontend form. The frontend date picker emits `YYYY-MM-DD`; the controller can append `00:00:00` or let Carbon parse it.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest v4.7 |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=RecipeTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TEST-01 | POST /recipes/{recipe}/tests creates a trial run linked to a specific version | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-01 | GET /recipes/{recipe}/tests returns the tests index page | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-02 | POST with type=experiment stores hypothesis, outcome_narrative, verdict | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-02 | experiment type requires hypothesis field | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-03 | POST stores tasting_notes and overall_rating | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-03 | POST stores ratings JSON array with per-dimension scores | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-03 | Photo upload stores file on disk and creates RecipeTestPhoto row | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-04 | POST with type=experiment stores change_rows JSON | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-04 | change_rows contain what_changed, expected_effect, actual_effect keys | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-01..04 | Non-owner cannot access /recipes/{recipe}/tests (403) | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-01..04 | DELETE /recipes/{recipe}/tests/{test} removes test and cascades photos | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |
| TEST-01..04 | PUT /recipes/{recipe}/tests/{test} updates test fields | Feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=RecipeTestTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Recipes/RecipeTestTest.php` — covers TEST-01, TEST-02, TEST-03, TEST-04
- [ ] `database/factories/RecipeTestFactory.php` — shared factory for test data
- [ ] `database/factories/RecipeTestPhotoFactory.php` — photo factory for edge cases
- [ ] Run `php artisan storage:link` once in dev environment (not a test gap, but a Wave 0 setup requirement)

---

## Sources

### Primary (HIGH confidence)

- Codebase read — `app/Models/RecipeVersion.php`, `Recipe.php`, `app/Http/Controllers/Recipes/RecipeController.php`, `app/Policies/RecipePolicy.php`, `routes/web.php`, `config/filesystems.php` — existing patterns confirmed directly from source
- Codebase read — `resources/js/components/ui/*.tsx` — confirmed all required shadcn/ui primitives already present
- Codebase read — `resources/js/pages/recipes/show.tsx` — confirmed Dialog import pattern and Inertia `router` usage
- `.planning/phases/04-recipe-tests/04-CONTEXT.md` — authoritative decisions document
- `.planning/codebase/CONVENTIONS.md`, `TESTING.md`, `STRUCTURE.md` — established project conventions
- `.planning/codebase/STACK.md` — confirmed Inertia v3, React 19, Pest v4 versions

### Secondary (MEDIUM confidence)

- Inertia v3 release notes (from CLAUDE.md system context) — confirmed Axios removal, `forceFormData`, `useForm` multipart behavior, `optional()` replacing `lazy()`
- Laravel Storage docs pattern — standard `Storage::disk()->store()` API is stable across Laravel 10–13

### Tertiary (LOW confidence)

- None — all critical claims verified from codebase reads or official version documentation in CLAUDE.md context

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — confirmed from direct codebase reads; no new packages needed
- Architecture: HIGH — mirrors Phase 3 patterns exactly; confirmed from existing controller, policy, and route structures
- File upload pattern: HIGH — Laravel Storage is well-established; `forceFormData` pattern confirmed from Inertia v3 docs in project context
- Pitfalls: HIGH — orphan prevention, symlink, wildcard shadowing all verified against actual codebase state

**Research date:** 2026-05-17
**Valid until:** 2026-06-17 (stable stack; 30-day window)
