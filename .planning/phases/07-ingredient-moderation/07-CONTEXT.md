# Phase 7: Ingredient Moderation - Context

**Gathered:** 2026-05-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 7 delivers the **submission → review → promotion** workflow that lets a
user-created private ingredient enter the official ingredient library:

- **Submit** — a user submits one of their own private ingredients for inclusion
  in the official library (INGR-09). Its state changes to "submitted"; it
  remains usable by the submitting user.
- **Review** — a moderator opens a review queue, inspects a submitted
  ingredient's full data, and **approves** or **rejects** it with notes
  (INGR-10).
- **Promote** — an approved submission is promoted into the official library and
  becomes visible to all users; a rejected submission reverts to private
  (INGR-11).

It does NOT build: the **`verified` flag** or the moderator **verify action** —
those shipped in Phase 2 and are a *separate* concept (data-correctness of an
existing ingredient, not promotion of a private one). It does not add new
ingredient data fields, change the import pipeline, or alter recipe authoring.
It consumes the existing Phase 2 `ingredients` model and the Phase 1
role/permission system.

</domain>

<decisions>
## Implementation Decisions

### Submission lifecycle & states

- An ingredient moves through states: **private → submitted → approved** (now
  official) **or → rejected** (reverts to private).
- **Frozen while pending** — once submitted, the ingredient is locked from
  submitter edits. The moderator reviews a stable snapshot; what they approve is
  exactly what they saw. The submitter can still *use* the ingredient in recipes
  while it is pending — only its data is locked.
- **Withdrawable** — the submitter can withdraw a pending submission at any time
  before a moderator acts; it reverts to a plain private ingredient.
- **Resubmission allowed** — a rejected ingredient reverts to private; the user
  can fix it and submit again. A resubmission is **flagged as a resubmit** to
  the moderator, who sees it was rejected before and the prior rejection note(s)
  for context.
- **Full submission history** — every submission attempt and every moderator
  decision (approve/reject, note, who, when) is recorded. Implies a **dedicated
  submissions table**, not just a status column on `ingredients`. The history
  powers the "rejected before" context and the audit trail.

### Review queue & inspection (moderator)

- **Dedicated per-submission review screen** — a purpose-built page showing the
  ingredient's full data (nutrition, allergens, conversions, category) plus the
  approve/reject controls and prior-rejection context in one place. NOT a reuse
  of the read-only ingredient detail page.
- **Queue rows show a data-completeness signal** — beyond name / submitter /
  category / submitted-date, each row indicates how complete the data is
  (nutrition fields filled, allergens set, conversions added) so a moderator can
  triage rich submissions from thin ones at a glance. Resubmissions are also
  surfaced on the row (per the lifecycle decision above).
- **FIFO ordering** — oldest submission first; nothing languishes unseen.
- **Pending-count nav badge** — the admin "Ingredient Review" nav entry shows a
  count of pending submissions so moderators see waiting work without opening
  the page.

### The moderator's decision

- **Notes required on reject only** — a rejection must carry a note so the
  submitter knows what to fix before resubmitting. Approval can be a clean
  action with an optional note.
- **No moderator editing** — the moderator approves or rejects the submission
  **as-is**. They do not correct the data themselves; a flawed value means a
  reject-with-note, and the submitter fixes and resubmits. Keeps data ownership
  clean and the frozen-snapshot principle intact.
- **Approval also marks the ingredient `verified`** — a moderator who approves
  has just inspected all the data on the review screen; that act IS
  verification. The promoted ingredient enters the official library already
  verified, with the deciding moderator recorded as `verified_by` /
  `verified_at` (reusing the Phase 2 columns).
- **Approval is final** — promotion to official is permanent. The ingredient is
  now official and recipes across all users may reference it; reverting is not a
  routine moderator action. Mistakes are an admin/data-fix concern out of band.

### Promotion mechanics & submitter feedback

- **Convert in place** — on approval the *same* ingredient record becomes
  official: ownership is cleared (`user_id → null`). The submitter's existing
  recipes that already reference it keep working seamlessly, now pointing at an
  official ingredient. No duplicate record is created.
- **Submit-time duplicate warning** — when a user is about to submit, the system
  surfaces likely existing official-library matches (by name) and nudges them to
  use the official ingredient instead. Duplicate prevention happens **upstream**,
  reducing queue noise, rather than relying solely on moderator judgement.
- **In-app notification + status badge** — the submitter's ingredient shows its
  state (Submitted / Rejected with the moderator's note / now Official) as a
  badge, AND the submitter receives an in-app notification when a moderator
  decides. (No notification system exists yet — see Open Items.)
- **"Contributed by" credit** — a promoted ingredient credits the chef who
  contributed it on the ingredient detail page. The submission history retains
  the full audit link regardless.

### Claude's Discretion

- The exact shape of the submissions table and the submission-status
  representation (column on `ingredients` vs. derived from the submissions
  table).
- Where the "Submit for inclusion" action lives in the private-ingredient UI
  (detail page, edit page, or both).
- The name-matching strategy for the submit-time duplicate warning (may reuse
  the Phase 2 search matching or the Phase 5 agent's tokenized name matching).
- The exact completeness-signal presentation on queue rows.
- Confirmation-dialog wording for submit, withdraw, approve, and reject.
- The permission gate for the submit action (any User on their own private
  ingredient) and for the queue/decision actions (existing `review-ingredients`
  permission — confirm during planning).
- Visual treatment of the status badge and the "contributed by" credit.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — ingredient submission & moderation
- `Project.md` (repo root) — **§3.1 Ingredients** (official vs. private
  ingredients, the user-submission / moderator-promotion concept), **§3.4 Roles
  & Permissions** (Moderator reviews ingredient submissions), **§9 Key
  Decisions**.
- `.planning/PROJECT.md` — Active requirements: "Users can submit a private
  ingredient for inclusion; moderators review and promote it"; the role model.
- `.planning/REQUIREMENTS.md` — definitions for this phase's requirements:
  **INGR-09** (user submits a private ingredient for inclusion), **INGR-10**
  (moderator reviews and approves/rejects), **INGR-11** (approved submission is
  promoted into the official library).

### Roadmap
- `.planning/ROADMAP.md` §"Phase 7: Ingredient Moderation" — goal, the 3 success
  criteria, dependency on Phase 2.

### Phase 2 foundations this phase builds on (CRITICAL — separation of concepts)
- `.planning/phases/02-ingredient-library/02-CONTEXT.md` — the `ingredients`
  model (one table; `user_id` null = official, set = private; private visible
  only to creator), and the explicit decision that **verification (Phase 2) and
  the submission/review/promotion queue (Phase 7) are deliberately separate**.
  Phase 2's "Deferred Ideas" section names this exact phase.
- `.planning/phases/02-ingredient-library/02-VERIFICATION.md` — what Phase 2
  actually shipped (the `verified` flag, `verified_by`, `verified_at`, the
  moderator verify action) so Phase 7 reuses rather than rebuilds it.

### Phase 1 foundations
- `.planning/phases/01-foundation/01-CONTEXT.md` — role/permission model
  (spatie/laravel-permission; the `review-ingredients` permission for
  Moderators + Admins; the `/admin` section), warm-minimal design system, EN/EL
  localization infrastructure.

### Codebase maps — existing patterns to follow
- `.planning/codebase/STRUCTURE.md` — directory layout (feature-grouped
  controllers, Inertia pages under `resources/js/pages/`, `app/Support/`).
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, FormRequest per
  action, thin controllers returning `Inertia::render()` / redirects,
  shadcn/ui composition, Wayfinder routes, traits in `app/Concerns/`.
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `INTEGRATIONS.md`,
  `TESTING.md`, `CONCERNS.md` — Inertia v3 bridge, shared props, Pest testing.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`Ingredient` model** (`app/Models/Ingredient.php`) — has `user_id`
  (null = official), `verified` / `verified_by` / `verified_at`, `source` /
  `source_id`, and `isOfficial()` / `isPrivate()` helpers. The submission state
  layers onto this model; "convert in place" sets `user_id` to null and reuses
  the verification columns.
- **`ingredients` table** (migration `2026_05_16_140144`) — note the
  `unique(['source', 'source_id'])` index; "convert in place" must keep that key
  valid (private ingredients carry their own `source` value).
- **`IngredientReviewController`** (`app/Http/Controllers/Admin/`) — currently a
  STUB returning `Inertia::render('admin/ingredients')`. The `index()` review
  queue is built out here.
- **`resources/js/pages/admin/ingredients.tsx`** — an explicit placeholder page
  ("The review queue arrives in Phase 7."). This is the queue page to build.
- **`IngredientVerificationController`** + **`VerifyIngredientRequest`**
  (`app/Http/Controllers/Admin/`, `app/Http/Requests/Admin/`) — the existing
  verify action; the approval path reuses the same `verified` / `verified_by` /
  `verified_at` write.
- **`IngredientPolicy`** (`app/Policies/`) — owner-only `update` / `delete`.
  Needs a `submit` (and likely `withdraw`) ability for the owner, and the
  pending-frozen rule must be reflected in `update` (no edits while submitted).
- **`PrivateIngredientController`** (`app/Http/Controllers/Ingredients/`) — the
  private-ingredient CRUD; the "Submit for inclusion" action attaches near here.
- **`RolesAndPermissionsSeeder`** — `review-ingredients` + `verify-ingredients`
  permissions already granted to Moderators (and Admins).
- **shadcn/ui primitives** — table, badge, dialog, textarea, tooltip, sonner
  toasts — the queue, review screen, decision dialogs, and status badges
  compose from these.

### Established Patterns
- **Models / migrations** via `php artisan make:` — the submissions table +
  model + factory are new.
- **Validation** — a `FormRequest` per action (submit, withdraw, approve,
  reject); shared rules into `app/Concerns/` traits.
- **Controllers** — thin, feature-grouped; Inertia mutations return `back()` /
  redirects, never JSON (Phase 1 lesson).
- **Routing** — the review queue + decision routes sit in the existing
  `['auth','verified','permission:review-ingredients']` admin group
  (`routes/web.php` ~line 83). The submit/withdraw routes are owner actions in
  the authenticated ingredient group. Declare static segments before `{ingredient}`
  wildcards (Phase 2/3 lesson).
- **Testing** — Pest feature tests mirror `app/`; Wave 0 RED-test scaffold
  pattern. State transitions, the frozen-while-pending rule, permission gating,
  and the convert-in-place promotion all need explicit tests.

### Integration Points
- **`ingredients` table / `Ingredient` model** — submission state + the
  submissions table relate to it; promotion mutates `user_id`.
- **`IngredientReviewController` + `admin/ingredients.tsx`** — stub → real queue.
- **`routes/web.php`** — submit/withdraw routes (owner) + approve/reject routes
  (moderator, admin group).
- **App-shell admin navigation** — the "Ingredient Review" entry gains a
  pending-count badge; the count is shared (likely via `HandleInertiaRequests`
  shared props, gated to users with `review-ingredients`).
- **Notification mechanism** — none exists; an in-app notification path must be
  introduced for the submitter-decision notification (see Open Items).
- **Ingredient detail page** — gains a status badge for the owner and a
  "contributed by" credit once promoted.
- **EN/EL translation files** — new keys for submit/withdraw/approve/reject,
  queue, review screen, status badges, notifications.

</code_context>

<specifics>
## Specific Ideas

- **Verification and promotion are different things, intentionally.** Phase 2's
  `verified` flag asserts "this ingredient's stored data is correct." Phase 7's
  promotion asserts "this private ingredient belongs in the shared library."
  They were split on purpose — but they connect here: approving a submission
  *also* sets `verified`, because the moderator inspected the full data to
  decide.
- **A frozen snapshot is the contract of review.** Once submitted, the data the
  moderator sees is the data they approve — the submitter cannot change it
  underneath them. This mirrors Phase 3/6's pinned-version principle: a private
  edit must never silently change what is under review or what is public.
- **Duplicate prevention belongs upstream.** Warning the submitter at submit
  time (rather than only catching duplicates in the queue) keeps the official
  library clean and the moderator's queue focused on genuinely new ingredients.
- **Resubmission is a conversation, not a verdict.** A rejection carries a note,
  the submitter fixes and resubmits, and the moderator sees the prior history —
  the workflow is designed for iteration, not a single pass/fail gate.

</specifics>

<deferred>
## Deferred Ideas

- **Reversible approvals / un-promote** — once approved, promotion is final;
  there is no moderator un-approve action. A future admin tooling concern if it
  ever proves necessary.
- **Moderator editing of submissions** — the moderator approves/rejects as-is
  and cannot correct data themselves. An "edit-then-approve" path was considered
  and deliberately rejected for clean data ownership.
- **Email notification of decisions** — submitters are notified in-app only;
  email-on-decision was considered and deferred.
- **Public contributor profiles** — a promoted ingredient credits its
  contributor by name, but clickable contributor profile pages are post-MVP
  social-platform scope.
- **A general-purpose notification system** — Phase 7 introduces in-app
  notifications only for the submission-decision case. A broader notification
  centre (for recipe tests, AI, publishing events) is out of scope here.

</deferred>

---

*Phase: 07-ingredient-moderation*
*Context gathered: 2026-05-18*
