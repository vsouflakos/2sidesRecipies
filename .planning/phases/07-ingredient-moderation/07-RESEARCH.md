# Phase 7: Ingredient Moderation - Research

**Researched:** 2026-05-18
**Domain:** Laravel state-machine workflow / Inertia v3 shared props / in-app notifications
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- States: private → submitted → approved (now official) OR → rejected (reverts to private)
- Frozen while pending — submitter cannot edit data once submitted; submitter CAN still use ingredient in recipes
- Withdrawable — submitter can withdraw before moderator acts (reverts to plain private)
- Resubmission allowed — rejected reverts to private; resubmit is flagged to moderator with prior rejection history
- Full submission history — dedicated `ingredient_submissions` table (not just a status column), not discardable
- Dedicated per-submission review screen (not reuse of the read-only detail page)
- Queue rows show data-completeness signal (nutrition filled / allergens set / conversions added)
- FIFO ordering (oldest first)
- Pending-count nav badge on admin "Ingredient Review" nav entry
- Notes required on reject only; approval note is optional
- No moderator editing — approve or reject as-is
- Approval also marks ingredient `verified` (reuses `verified_by` / `verified_at` from Phase 2)
- Approval is final (no un-approve action)
- Convert in place — `user_id → null` on approval; no duplicate record
- Submit-time duplicate warning by name (nudge to use official ingredient instead)
- In-app notification + status badge when moderator decides
- "Contributed by" credit on promoted ingredient detail page

### Claude's Discretion

- Exact shape of the submissions table and submission-status representation (column on `ingredients` vs. derived from submissions table)
- Where the "Submit for inclusion" action lives (detail page, edit page, or both)
- Name-matching strategy for submit-time duplicate warning (Phase 2 fulltext/like search vs. Phase 5 tokenized matching)
- Exact completeness-signal presentation on queue rows
- Confirmation-dialog wording for submit, withdraw, approve, and reject
- Permission gate for submit action (any User on their own private ingredient) and for queue/decision actions (`review-ingredients`)
- Visual treatment of status badge and "contributed by" credit

### Deferred Ideas (OUT OF SCOPE)

- Reversible approvals / un-promote
- Moderator editing of submissions
- Email notification of decisions
- Public contributor profiles (clickable)
- A general-purpose notification system
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INGR-09 | User can submit a private ingredient for inclusion in the official library | Submit action on private ingredient; state change to "submitted"; freeze-while-pending enforced in IngredientPolicy::update |
| INGR-10 | Moderator can review a submitted ingredient and approve or reject it | IngredientReviewController queue (stub → real); per-submission review page; approve/reject FormRequests; `review-ingredients` permission gate already exists |
| INGR-11 | An approved submitted ingredient is promoted into the official library | Convert-in-place: `user_id → null`; `verified = true`, `verified_by`, `verified_at` written (reuses Phase 2 columns); unique index constraint handled |
</phase_requirements>

---

## Summary

Phase 7 wires a three-step moderation workflow on top of the Phase 2 ingredient model. The core data structure is a new `ingredient_submissions` table that records every submission attempt and every moderator decision — this gives full history, supports the "resubmit with prior context" UX, and lets submission status be derived from the table rather than from a status enum column on `ingredients` directly. A `submission_status` column IS still needed on `ingredients` to support efficient querying of "what state is this ingredient in?" without a join (see Standard Stack section for the recommended hybrid approach).

The PHP enum cast (`string` backed PHP enum + Eloquent `EnumCasts`) is the idiomatic Laravel 13 approach for the submission state, since the project does not add dependencies without approval — spatie/laravel-model-states is therefore out of scope. The state machine is thin enough that a PHP enum with guard methods in the model is all that is needed.

The in-app notification mechanism is built on Laravel's built-in `database` notification channel. No new Composer or npm packages are needed. The notification record is written when a moderator decides; the submitter sees it on their next Inertia page load via a shared prop in `HandleInertiaRequests`. This is a narrow, Phase-7-scoped notification surface — not a general notification centre (deferred).

**Primary recommendation:** Use a `ingredient_submissions` table for full history plus a `submission_status` column on `ingredients` for cheap status reads, implement state as a PHP backed enum with guard methods, deliver in-app notifications via the Laravel `database` channel and expose unread count + latest notification via `HandleInertiaRequests` shared props.

---

## Standard Stack

### Core

| Library / Feature | Version | Purpose | Why Standard |
|-------------------|---------|---------|--------------|
| PHP backed enum (`enum SubmissionStatus: string`) | PHP 8.3 | Represent the four states; Eloquent casts it via `casts()` | Native PHP 8.1+; no dependency; exactly what Laravel 13 docs recommend |
| `Illuminate\Notifications\Notifiable` | Laravel 13 | Enables `$user->notify(new IngredientDecisionNotification(...))` | Already on User model via starter kit (Fortify notifications) |
| Laravel `database` notification channel | Laravel 13 | Stores notification rows in `notifications` table; no SMTP/queue required | Built-in; persists to DB; queryable; zero new dependencies |
| `Illuminate\Notifications\DatabaseNotification` | Laravel 13 | Eloquent model for `notifications` table; has `read_at` column | Built-in; already resolves `$user->notifications()` and `$user->unreadNotifications()` |
| `php artisan notifications:table` | Laravel 13 | Generates the `notifications` migration (uuid PK, morph, type, data JSON, read_at) | Official Artisan command — creates the table correctly |

### Supporting

| Library / Feature | Version | Purpose | When to Use |
|-------------------|---------|---------|-------------|
| `DB::transaction()` (existing pattern) | Laravel 13 | Wrap promote (user_id→null + verified write + submission update) atomically | Any multi-table write path |
| shadcn/ui Badge | existing | Status badge (Submitted / Rejected / Official) on ingredient detail | Consistent with existing `badge_verified` badge |
| shadcn/ui Dialog | existing | Confirm-submit, confirm-withdraw, approve, reject-with-notes dialogs | Consistent with existing delete / verify dialogs |
| shadcn/ui Textarea | existing | Rejection note input inside reject dialog | Already used in recipe-test modal |
| shadcn/ui Table | existing | Review queue rows | Consistent with existing admin users table |
| sonner toast | existing | Success/error feedback after submit/withdraw/approve/reject | Consistent with all existing mutation toasts |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP backed enum | `spatie/laravel-model-states` | Spatie adds formal state machine transitions with guards; overkill for 4 states; project does not add dependencies without approval |
| `database` notification channel | Broadcasting / pusher | Broadcasting is real-time but needs a WebSocket server; not needed here — decision notifications are not time-critical |
| Hybrid (`submission_status` column + submissions table) | Submissions table only (derive status via latest row) | Pure-derive requires a correlated subquery on every ingredient list load; the column is a cheap denormalized cache of truth |

**Installation:**

No new packages. The `notifications` table migration is generated via:

```bash
php artisan notifications:table
php artisan migrate
```

---

## Architecture Patterns

### Recommended Project Structure

New files this phase adds (following existing feature-grouped pattern):

```
app/
├── Models/
│   ├── IngredientSubmission.php       # new — the submissions table model
├── Enums/
│   └── SubmissionStatus.php           # new — private|submitted|approved|rejected
├── Notifications/
│   └── IngredientDecisionNotification.php  # new
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── IngredientReviewController.php   # existing stub — expanded
│   │   │   └── IngredientSubmissionController.php  # new — approve/reject actions
│   │   └── Ingredients/
│   │       └── IngredientSubmissionController.php  # new — submit/withdraw actions
│   └── Requests/
│       ├── Admin/
│       │   ├── ApproveIngredientRequest.php    # new
│       │   └── RejectIngredientRequest.php     # new
│       └── Ingredients/
│           ├── SubmitIngredientRequest.php     # new
│           └── WithdrawIngredientRequest.php   # new
├── Policies/
│   └── IngredientPolicy.php           # existing — add submit, withdraw, frozen-update guard
database/
├── migrations/
│   ├── 2026_05_18_000001_create_ingredient_submissions_table.php  # new
│   └── 2026_05_18_000002_add_submission_status_to_ingredients_table.php  # new
│   └── notifications table migration (from artisan command)
resources/js/
├── pages/
│   └── admin/
│       ├── ingredients.tsx            # existing placeholder — replaced with queue table
│       └── ingredients/
│           └── show.tsx               # new — per-submission review page
├── components/
│   └── ingredients/
│       ├── submit-action.tsx          # new — submit/withdraw control on detail page
│       ├── submission-status-badge.tsx # new — status badge for owner view
│       └── submission-completeness.tsx # new — data completeness signal for queue rows
tests/
└── Feature/
    └── Ingredients/
        ├── IngredientSubmissionTest.php    # new — INGR-09 coverage
        ├── IngredientModerationTest.php    # new — INGR-10 coverage
        └── IngredientPromotionTest.php     # new — INGR-11 coverage
```

### Pattern 1: PHP Backed Enum for Submission Status

**What:** A `string`-backed PHP enum cast via Eloquent — the state lives on two places: the `submission_status` column on `ingredients` (current state, cheap read) and the `ingredient_submissions` table (immutable history rows).

**When to use:** Any time you read or compare status on an ingredient record.

```php
// app/Enums/SubmissionStatus.php
namespace App\Enums;

enum SubmissionStatus: string
{
    case Private    = 'private';
    case Submitted  = 'submitted';
    case Approved   = 'approved';
    case Rejected   = 'rejected';
}
```

Add to `Ingredient` model `casts()`:
```php
'submission_status' => SubmissionStatus::class,
```

And a helper method for the frozen-while-pending rule:
```php
public function isPendingReview(): bool
{
    return $this->submission_status === SubmissionStatus::Submitted;
}
```

### Pattern 2: Submissions Table Shape

**What:** `ingredient_submissions` records every submission attempt and every decision. The current active submission is the latest row for the ingredient.

```
ingredient_submissions
  id                  bigint unsigned auto_increment PK
  ingredient_id       bigint unsigned FK ingredients.id cascadeOnDelete
  submitted_by        bigint unsigned FK users.id nullOnDelete
  reviewed_by         bigint unsigned FK users.id nullable nullOnDelete
  status              enum('submitted','approved','rejected')
  notes               text nullable          -- moderator note (required on reject)
  submission_number   tinyint unsigned default 1  -- 1=first, 2=resubmit, etc.
  submitted_at        timestamp
  reviewed_at         timestamp nullable
  timestamps (created_at / updated_at)
  INDEX (ingredient_id, status)
  INDEX (ingredient_id, submitted_at)   -- for FIFO ordering
```

**Why this shape:**
- `submission_number` surfaces resubmit count to moderator without a count query
- The `notes` column is on the submission row so the full approve/reject note is preserved in history
- `reviewed_by` / `reviewed_at` on the submission row records exactly who decided and when (audit trail)
- The latest `submitted` status row IS the pending review item — no separate "current" pointer needed

### Pattern 3: Convert-in-Place Promotion Mechanics

**What:** On approval, the same `ingredients` row becomes official. The three-step atomic transaction:

```php
DB::transaction(function () use ($ingredient, $submission, $moderator) {
    // 1. Promote to official
    $ingredient->update([
        'user_id'           => null,          // becomes official
        'submission_status' => SubmissionStatus::Approved,
        'verified'          => true,          // approval IS verification (CONTEXT decision)
        'verified_by'       => $moderator->id,
        'verified_at'       => now(),
    ]);

    // 2. Close the submission record
    $submission->update([
        'status'       => 'approved',
        'reviewed_by'  => $moderator->id,
        'reviewed_at'  => now(),
        'notes'        => $request->validated('notes'),
    ]);

    // 3. Notify submitter
    $submitter = $submission->submittedBy;
    $submitter?->notify(new IngredientDecisionNotification($ingredient, 'approved', null));
});
```

**The `unique(['source', 'source_id'])` index:** Private user ingredients already have `source = 'user'` and `source_id = UUID`. Setting `user_id = null` does NOT change either of those columns — the unique key is on `(source, source_id)`, not `(user_id, source_id)`. The index remains valid after promotion. No collision risk.

### Pattern 4: Frozen-While-Pending in `IngredientPolicy`

**What:** `IngredientPolicy::update` must return `false` when the ingredient is in `submitted` state, even for the owner.

```php
public function update(User $user, Ingredient $ingredient): bool
{
    if ($ingredient->isPendingReview()) {
        return false;  // frozen while under review
    }

    return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
}
```

`IngredientPolicy::delete` should also block deletion while pending (cannot delete what is under review):

```php
public function delete(User $user, Ingredient $ingredient): bool
{
    if ($ingredient->isPendingReview()) {
        return false;
    }

    return $ingredient->user_id !== null && $ingredient->user_id === $user->id;
}
```

New policy methods to add:

```php
public function submit(User $user, Ingredient $ingredient): bool
{
    // Only the owner of a private ingredient that is NOT already submitted/approved
    return $ingredient->user_id === $user->id
        && $ingredient->submission_status === SubmissionStatus::Private
        || $ingredient->submission_status === SubmissionStatus::Rejected;
}

public function withdraw(User $user, Ingredient $ingredient): bool
{
    // Only the owner, only while pending
    return $ingredient->user_id === $user->id
        && $ingredient->isPendingReview();
}
```

### Pattern 5: In-App Notifications via Laravel Database Channel

**What:** Laravel's built-in `database` channel writes a row to the `notifications` table. The submitter's unread notifications (and count) are shared via `HandleInertiaRequests`.

**Step 1 — Create the notifications table (one-time per project):**
```bash
php artisan notifications:table
php artisan migrate
```

**Step 2 — Notification class:**
```php
// app/Notifications/IngredientDecisionNotification.php
namespace App\Notifications;

use App\Models\Ingredient;
use Illuminate\Notifications\Notification;

class IngredientDecisionNotification extends Notification
{
    public function __construct(
        public readonly Ingredient $ingredient,
        public readonly string $decision,    // 'approved' | 'rejected'
        public readonly ?string $notes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'ingredient_id'   => $this->ingredient->id,
            'ingredient_name' => $this->ingredient->name_cache,
            'decision'        => $this->decision,
            'notes'           => $this->notes,
        ];
    }
}
```

**Step 3 — Share unread count + latest notification in `HandleInertiaRequests::share()`:**

```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        // ... existing shared props ...
        'pendingIngredientReviewCount' => $user && $user->can('review-ingredients')
            ? \App\Models\Ingredient::where('submission_status', 'submitted')->count()
            : null,
        'ingredientNotifications' => $user
            ? $user->unreadNotifications()
                   ->where('type', \App\Notifications\IngredientDecisionNotification::class)
                   ->latest()
                   ->take(5)
                   ->get(['id', 'data', 'created_at'])
                   ->values()
            : null,
    ];
}
```

**Key points:**
- The `pendingIngredientReviewCount` is gated to `review-ingredients` permission holders — returns `null` for regular users so no unnecessary DB query
- The `ingredientNotifications` are fetched for any authenticated user (submitters need to see their decisions)
- Both props should use `Inertia::lazy()` equivalent pattern if they prove expensive — however for v1 a direct count is acceptable
- Marking as read: call `$user->unreadNotifications()->where('type', ...)->update(['read_at' => now()])` after user views the notification (on the ingredient detail page load or a dedicated dismiss endpoint)

**Frontend consumption (AppSidebar pending badge):**

```tsx
// In app-sidebar.tsx, read from usePage().props
const pendingIngredientReviewCount = usePage().props.pendingIngredientReviewCount as number | null;
const canReviewIngredients = permissions.includes('review-ingredients');

// Nav item with badge
{
    title: t('app.nav.ingredient_review'),
    href: adminIngredientsIndex().url,
    icon: ClipboardList,
    badge: canReviewIngredients && pendingIngredientReviewCount
        ? String(pendingIngredientReviewCount)
        : undefined,
}
```

The `NavItem` type in `resources/js/types/navigation.ts` needs a `badge?: string` field added.

### Pattern 6: Submit-Time Duplicate Warning Strategy

**Decision:** Use the Phase 2 fulltext/LIKE search (same as `IngredientController::index`) against official ingredients only. This is already proven, tested, and requires no new infrastructure.

**Implementation:** When the user clicks "Submit for inclusion", the `SubmitIngredientRequest` (or a standalone check endpoint) performs a name search against `ingredients` where `user_id IS NULL` using the ingredient's `name_cache` value. Up to 5 matches are returned as a warning list. The user confirms submission despite matches, or cancels to use the official ingredient instead.

**Why not the Phase 5 tokenized matching?** Phase 5's tokenized matching lives in the AI agent's `search_ingredients` tool and is not exposed as a standalone service. Reusing it here would create a coupling between the moderation workflow and the AI subsystem. The fulltext search is sufficient for the duplicate-warning use case — exact semantic deduplication is the moderator's job.

```php
// In SubmitIngredientController or as a named route
public function duplicateCheck(Ingredient $ingredient): JsonResponse
{
    $matches = Ingredient::query()
        ->whereNull('user_id')  // official only
        ->whereHas('translations', fn ($t) => $t
            ->when(
                DB::getDriverName() !== 'sqlite',
                fn ($w) => $w->whereFullText('name', $ingredient->name_cache),
                fn ($w) => $w->where('name', 'like', "%{$ingredient->name_cache}%")
            )
        )
        ->limit(5)
        ->get(['id', 'name_cache']);

    return response()->json(['matches' => $matches]);
}
```

The submit dialog shows matches (if any) and requires the user to explicitly confirm before proceeding.

### Pattern 7: Queue Completeness Signal

**What:** Computed server-side; passed as part of each queue row. No DB joins required — the signal reads existing columns on the already-loaded ingredient.

```php
// In a IngredientSubmissionQueueResource or inline in IngredientReviewController::index()
'completeness' => [
    'nutrition_filled' => $ingredient->energy_kcal !== null
                          && $ingredient->protein_g !== null
                          && $ingredient->fat_g !== null
                          && $ingredient->carbs_g !== null,
    'allergens_set'    => $ingredient->allergens->isNotEmpty(),
    'conversions_added'=> $ingredient->conversions->isNotEmpty(),
],
```

Display as three icon/dot indicators on the queue row — a visual triage signal, not a blocking gate.

### Anti-Patterns to Avoid

- **Storing submission state ONLY as a derivation from the latest submissions table row for every ingredient list query:** This requires a correlated subquery or LEFT JOIN on every page load. The `submission_status` column on `ingredients` is a cheap denormalized cache that avoids this.
- **Using `Inertia::lazy()` for the pending count incorrectly:** The pending count is a small integer. Use a direct count in `share()`. Lazy props are for heavy payloads that can be deferred to a second request.
- **Sending the notification synchronously outside a DB transaction:** Always `notify()` AFTER the transaction commits. If `notify()` is inside the transaction and the notification write fails, it rolls back the promotion. Move the notify call after `DB::transaction()` returns.
- **Not nulling `user_id` in a transaction with the submission status update:** The two writes must be atomic. A crash between them leaves the ingredient in an inconsistent state (official but no submission record closed, or vice versa).
- **Forgetting to update `submission_status` on the ingredient when withdrawing:** Withdrawal must set `submission_status = SubmissionStatus::Private` on `ingredients` AND create/update the submission row as `withdrawn` (or simply delete the open submission row — simpler, but loses the withdrawal event from history). Recommended: keep the submission row and set it to a `withdrawn` status. However, since the CONTEXT only requires history of submission attempts and decisions, a simpler approach is to soft-delete the open submission row on withdrawal and reset `submission_status` to `private`. Either is defensible; the planner should pick one and be consistent.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Notification persistence | Custom `ingredient_notifications` table | Laravel `database` channel + `notifications` table | Built-in, morphable, has `read_at`, works with `$user->unreadNotifications()` |
| State enum | String constant class or hand-checked strings | PHP backed enum + Eloquent cast | Type-safe; PHPDoc-friendly; eliminates magic strings throughout the codebase |
| Permission check for submit | Inline `if ($ingredient->user_id !== auth()->id()) abort(403)` | `Gate::authorize('submit', $ingredient)` + `IngredientPolicy::submit()` | Consistent with existing update/delete/verify patterns in the codebase |
| Duplicate name check | Fuzzy string matching algorithm | Fulltext/LIKE search already used by `IngredientController::index()` | Already tested; already handles SQLite/MySQL driver difference |

---

## Common Pitfalls

### Pitfall 1: The `unique(['source', 'source_id'])` Index on Promotion

**What goes wrong:** Developer assumes setting `user_id = null` on promotion causes a unique constraint violation because another official ingredient could share `(source, source_id)`.

**Why it happens:** Misreading the unique index — it is on `(source, source_id)`, not `(user_id, source)`.

**How to avoid:** Private user ingredients have `source = 'user'` and `source_id = UUID` (set at creation in `PrivateIngredientController::store()`). Official ingredients from CIQUAL/USDA/OFF have `source = 'ciqual'|'usda'|'off'` and dataset-specific IDs. No collision is possible because the UUID is unique. Setting `user_id = null` does not touch either column — the index remains valid.

**Warning signs:** A test that creates an official ingredient with `source = 'user'` and the same UUID as the private one being promoted — this would be an invalid fixture. Real data never has this shape.

### Pitfall 2: Notification Written Inside the DB Transaction

**What goes wrong:** `$user->notify(...)` is called inside `DB::transaction()`. The notification involves a DB write to `notifications`. If anything in the transaction throws, the notification write is also rolled back — fine. BUT: if `notify()` is synchronous and dispatches a queued job (e.g., if the project enables a queue driver), the job may run before the transaction commits, seeing the DB in an uncommitted state.

**Why it happens:** The `database` channel writes synchronously and does not use a queue. This is safe. But if the channel is ever swapped to a queued notifiable, the call inside a transaction is dangerous.

**How to avoid:** Call `$user->notify()` AFTER `DB::transaction()` commits, not inside it. Pattern:

```php
$submitter = null;
DB::transaction(function () use (..., &$submitter) {
    // ... all DB writes ...
    $submitter = $submission->submittedBy;
});
// Notification outside the transaction
$submitter?->notify(new IngredientDecisionNotification(...));
```

### Pitfall 3: Shared Prop Query N+1 on Pending Count

**What goes wrong:** `HandleInertiaRequests::share()` runs on every request. If the pending count query is not properly guarded (e.g., runs even for guests or non-moderators), it adds an unnecessary DB query to every page load.

**Why it happens:** Forgetting the permission gate before the count query.

**How to avoid:** Gate the count behind `$user && $user->can('review-ingredients')`. Return `null` for users without that permission so the frontend knows to hide the badge entirely.

### Pitfall 4: Static Route Declaration Order — Submit/Withdraw Routes

**What goes wrong:** Adding `/ingredients/{ingredient}/submit` after `/ingredients/{ingredient}` causes the wildcard to shadow the new routes.

**Why it happens:** Documented Phase 2/3 lesson — static segments must precede `{ingredient}` wildcards.

**How to avoid:** In `routes/web.php`, declare all `/ingredients/{ingredient}/static-action` routes BEFORE the `/ingredients/{ingredient}` show route. The current web.php already has `ingredients/create` before `{ingredient}/edit` before `{ingredient}` — follow the same pattern:

```php
// Static-segment routes FIRST
Route::post('ingredients/{ingredient}/submit',   [IngredientSubmissionController::class, 'store'])->name('ingredients.submit');
Route::delete('ingredients/{ingredient}/submit', [IngredientSubmissionController::class, 'destroy'])->name('ingredients.withdraw');
// Wildcard show LAST (already at line 40 in web.php)
Route::get('ingredients/{ingredient}',           [IngredientController::class, 'show'])->name('ingredients.show');
```

### Pitfall 5: `isPendingReview()` Called Before `submission_status` is Loaded

**What goes wrong:** `$ingredient->isPendingReview()` relies on `submission_status` being a cast enum. If the ingredient is loaded without the column (e.g., `Ingredient::query()->select(['id', 'user_id', 'name_cache'])`), the cast will return `null` and the frozen check fails silently.

**Why it happens:** Selective `select()` calls omitting the new column.

**How to avoid:** Ensure any query path that feeds `IngredientPolicy::update()` or `IngredientPolicy::delete()` loads `submission_status`. The easiest way: don't use `select()` for policy-gated models, or ensure `submission_status` is always in the select list when modifying policy queries.

### Pitfall 6: Resubmission Number Off-by-One

**What goes wrong:** `submission_number` on the new `IngredientSubmission` row is computed incorrectly (e.g., counts all rows including the current one before inserting).

**How to avoid:** Query the count of EXISTING submission rows for the ingredient before inserting the new one:

```php
$submissionNumber = IngredientSubmission::where('ingredient_id', $ingredient->id)->count() + 1;
```

---

## Code Examples

Verified patterns from existing codebase:

### Gate::authorize pattern (matches existing codebase — NOT $this->authorize())

```php
// From PrivateIngredientController and IngredientController (Phase 2)
// Note: base Controller has no AuthorizesRequests trait; use Gate facade
use Illuminate\Support\Facades\Gate;

Gate::authorize('submit', $ingredient);   // new submit ability
Gate::authorize('withdraw', $ingredient); // new withdraw ability
```

### FormRequest with permission check (matches VerifyIngredientRequest)

```php
// app/Http/Requests/Admin/ApproveIngredientRequest.php
class ApproveIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review-ingredients');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

// app/Http/Requests/Admin/RejectIngredientRequest.php
class RejectIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review-ingredients');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:1000'],  // required on reject
        ];
    }
}
```

### IngredientFactory state additions

```php
// Add to existing IngredientFactory
public function submitted(): static
{
    return $this->state(fn (array $attributes) => [
        'submission_status' => SubmissionStatus::Submitted,
    ]);
}

public function rejected(): static
{
    return $this->state(fn (array $attributes) => [
        'submission_status' => SubmissionStatus::Rejected,
    ]);
}
```

### HandleInertiaRequests shared props (matching existing pattern)

```php
// Matches existing HandleInertiaRequests::share() structure
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'name'        => config('app.name'),
        'auth'        => [
            'user'        => $user,
            'permissions' => $user ? $user->getPermissionNames() : [],
        ],
        'locale'      => app()->getLocale(),
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        // Phase 7 additions:
        'pendingIngredientReviewCount' => $user && $user->can('review-ingredients')
            ? Ingredient::where('submission_status', SubmissionStatus::Submitted->value)->count()
            : null,
        'ingredientNotifications' => $user
            ? $user->unreadNotifications()
                   ->where('type', IngredientDecisionNotification::class)
                   ->latest()
                   ->take(5)
                   ->get(['id', 'data', 'created_at'])
                   ->values()
            : null,
    ];
}
```

### Notification class (database channel)

```php
// Confirmed safe: database channel writes synchronously, no queue required
class IngredientDecisionNotification extends Notification
{
    public function __construct(
        public readonly int $ingredientId,
        public readonly string $ingredientName,
        public readonly string $decision,    // 'approved' | 'rejected'
        public readonly ?string $notes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'ingredient_id'   => $this->ingredientId,
            'ingredient_name' => $this->ingredientName,
            'decision'        => $this->decision,
            'notes'           => $this->notes,
        ];
    }
}
```

Note: Pass primitive values (not Eloquent models) to the notification constructor to avoid serialization issues if a queue driver is ever added later. Store the IDs and names directly.

### NavItem type extension (navigation.ts)

```typescript
// resources/js/types/navigation.ts — add badge field
export interface NavItem {
    title: string;
    href: string;
    icon?: React.ComponentType;
    badge?: string;  // numeric badge count as string, e.g. "3"
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `spatie/laravel-model-states` for state machines | Native PHP backed enum + Eloquent cast | PHP 8.1+ / Laravel 10+ | No package needed for simple state machines with 4 states |
| `Notification::fake()` with `assertSentTo()` | Same — still the standard assertion | Laravel 13 | `Notification::fake(); ... Notification::assertSentTo($user, IngredientDecisionNotification::class)` |
| `$user->notifications()` collection | `$user->unreadNotifications()` scoped query | Laravel (stable) | Use `unreadNotifications()` + `markAsRead()` on the model |

**Deprecated/outdated:**
- `Inertia::lazy()`: Renamed to `Inertia::optional()` in Inertia v3 / Laravel Inertia v3. The CONTEXT notes this — `LazyProp` is removed. Use `Inertia::optional()` for any deferred prop if needed.

---

## Open Questions

1. **`withdrawal` as a `submission_status` value or not?**
   - What we know: The CONTEXT says withdrawal reverts to "plain private ingredient." The submissions table needs to record that the submission was withdrawn (for the resubmission-count and audit trail).
   - What's unclear: Should `SubmissionStatus` have a `Withdrawn` case, or should withdrawn submissions just be soft-deleted from the table?
   - Recommendation: Add `Withdrawn = 'withdrawn'` as a case. Soft-delete on the submission row only (not the ingredient). The ingredient `submission_status` returns to `Private`. This preserves full audit history without complicating the status enum on `ingredients`.

2. **Where exactly does the status badge and "Submit for inclusion" action live?**
   - What we know: CONTEXT says Claude's Discretion, either detail page, edit page, or both.
   - Recommendation: Place both on the **ingredient detail page** (`ingredients/show.tsx`). The edit page is for data editing (which is frozen while submitted anyway). The detail page is where the owner sees the ingredient's current state. This mirrors the verify action placement (also on the detail page).

3. **Should the admin review route for a single submission be `/admin/ingredients/{submission}` or `/admin/ingredients/{ingredient}`?**
   - What we know: The CONTEXT says a "per-submission review screen." The existing review controller is at `/admin/ingredients`.
   - Recommendation: Use `/admin/ingredients/{submission}` where `{submission}` is the `IngredientSubmission` ID. The controller loads the related ingredient via `$submission->ingredient`. This keeps the URL tied to the moderation event, not the ingredient — important because the same ingredient can have multiple submission records.

4. **`NavItem` badge rendering in `nav-main.tsx`**
   - What we know: `NavMain` currently renders a simple link with title and icon. It does not support a badge prop.
   - Recommendation: Extend `NavItem` type with `badge?: string` and update `nav-main.tsx` to render a small `Badge` next to the title when `badge` is set. This is a minimal change, consistent with shadcn/ui Badge already used elsewhere.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest v4 + PHPUnit v12 |
| Config file | `phpunit.xml` (SQLite in-memory) |
| Quick run command | `php artisan test --compact --filter=IngredientSubmission` |
| Full suite command | `php artisan test --compact tests/Feature/Ingredients/` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INGR-09 | Owner can submit a private ingredient; status becomes `submitted`; ingredient is frozen | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-09 | Non-owner cannot submit another user's ingredient | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-09 | Owner cannot submit an already-submitted ingredient | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-09 | Frozen ingredient cannot be edited by owner while submitted | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-09 | Owner can withdraw a pending submission; status reverts to private; ingredient unfreezes | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-09 | Resubmission after rejection increments `submission_number` and resets status to submitted | Feature | `php artisan test --compact --filter=IngredientSubmission` | Wave 0 |
| INGR-10 | Moderator can view the review queue (GET /admin/ingredients); items are FIFO ordered | Feature | `php artisan test --compact --filter=IngredientModeration` | Wave 0 |
| INGR-10 | Moderator can approve a submission with optional notes | Feature | `php artisan test --compact --filter=IngredientModeration` | Wave 0 |
| INGR-10 | Moderator cannot reject without notes | Feature | `php artisan test --compact --filter=IngredientModeration` | Wave 0 |
| INGR-10 | Moderator can reject with notes | Feature | `php artisan test --compact --filter=IngredientModeration` | Wave 0 |
| INGR-10 | Plain User cannot access the review queue | Feature | `php artisan test --compact --filter=IngredientModeration` | Wave 0 |
| INGR-11 | Approved ingredient: `user_id` becomes null; `verified = true`; `verified_by/at` set | Feature | `php artisan test --compact --filter=IngredientPromotion` | Wave 0 |
| INGR-11 | Promoted ingredient is visible to all users (official library) | Feature | `php artisan test --compact --filter=IngredientPromotion` | Wave 0 |
| INGR-11 | Submitter's recipes that reference the ingredient continue to work after promotion | Feature | `php artisan test --compact --filter=IngredientPromotion` | Wave 0 |
| INGR-11 | Rejected ingredient: `submission_status` reverts to `private`; `user_id` unchanged | Feature | `php artisan test --compact --filter=IngredientPromotion` | Wave 0 |
| INGR-11 | Submitter receives a database notification on approve and on reject | Feature | `php artisan test --compact --filter=IngredientPromotion` | Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --compact tests/Feature/Ingredients/`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Ingredients/IngredientSubmissionTest.php` — covers INGR-09 submit/withdraw/freeze/resubmit
- [ ] `tests/Feature/Ingredients/IngredientModerationTest.php` — covers INGR-10 queue/approve/reject permission gates
- [ ] `tests/Feature/Ingredients/IngredientPromotionTest.php` — covers INGR-11 convert-in-place + notifications
- [ ] `app/Enums/SubmissionStatus.php` — enum must exist before tests reference it
- [ ] `app/Models/IngredientSubmission.php` — model must exist before factories reference it
- [ ] `database/migrations/*_create_ingredient_submissions_table.php` — schema must exist before any DB test runs
- [ ] `database/migrations/*_add_submission_status_to_ingredients_table.php` — column must exist
- [ ] Notifications table migration (`php artisan notifications:table`) — required for notification tests with `Notification::fake()` + `assertSentTo`
- [ ] Stub page `resources/js/pages/admin/ingredients/show.tsx` — Inertia `assertInertia()` triggers Vite manifest lookup; must exist before tests hit that route

---

## Sources

### Primary (HIGH confidence)

- Codebase direct read — `app/Models/Ingredient.php`, `app/Policies/IngredientPolicy.php`, `app/Http/Controllers/Admin/IngredientVerificationController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`, `database/factories/IngredientFactory.php`, `database/migrations/2026_05_16_140144_create_ingredients_table.php`, `resources/js/components/app-sidebar.tsx`, `resources/js/types/ingredient.ts`
- `.planning/phases/07-ingredient-moderation/07-CONTEXT.md` — locked decisions and discretion areas
- `.planning/phases/02-ingredient-library/02-VERIFICATION.md` — Phase 2 shipped artifacts; verified_by/verified_at columns confirmed
- `.planning/STATE.md` — accumulated project decisions (route ordering, Gate::authorize(), Inertia mutation redirect patterns)
- `database/seeders/RolesAndPermissionsSeeder.php` — `review-ingredients` permission confirmed on Moderator and Admin

### Secondary (MEDIUM confidence)

- Laravel 13 official notifications documentation (Database channel, `notifications` table structure, `$user->unreadNotifications()`, `Notification::fake()`) — HIGH confidence based on training knowledge + confirmed stable API surface
- PHP 8.3 backed enums + Eloquent `EnumCasts` — HIGH confidence; native PHP feature, stable Eloquent cast since Laravel 9

### Tertiary (LOW confidence)

- None — all claims either directly verified from codebase or from stable Laravel APIs that have not changed in multiple major versions

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries/features are built-in; no new dependencies
- Architecture: HIGH — directly derived from existing codebase patterns (Gate::authorize, FormRequest, Inertia redirects, HandleInertiaRequests share)
- Pitfalls: HIGH — uniqueness index shape verified from migration file; notification-in-transaction pitfall is a documented Laravel pattern; route ordering is a project-documented lesson

**Research date:** 2026-05-18
**Valid until:** Stable (Laravel 13 + PHP 8.3 + Inertia v3 + Pest v4 — no fast-moving pieces)
