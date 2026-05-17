# Phase 4: Recipe Tests - Context

**Gathered:** 2026-05-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 4 lets a chef record and review **structured trial runs and experiments
against specific recipe versions**. A "test" is how a recipe version is
validated in the real kitchen.

It delivers:

- **Trial runs** — free-form: the chef cooks a recipe version and logs the
  result with feedback (TEST-01).
- **Structured experiments** — a defined hypothesis, a recorded outcome, and
  what changed versus what was expected (TEST-02, TEST-04).
- **Test feedback** — tasting notes, photos, and structured ratings on every
  test (TEST-03).
- **Version linkage** — every test is linked to the exact recipe version it was
  run against, and tests are surfaced on the recipe (success criterion 3).

It does NOT build: the AI agent that *reads* test feedback to suggest
improvements (Phase 5 — Phase 4 only records the feedback), publishing or the
public library (Phase 6), or ingredient moderation (Phase 7). Phase 4 records
tests; it does not apply test outcomes back into the recipe draft (that wiring
belongs to Phase 5's agent flow).

</domain>

<decisions>
## Implementation Decisions

### Test entity model

- **One unified `Test` entity** with a `type` field — `trial` or `experiment`.
  Both types share the feedback fields; the experiment type adds extra fields.
  One model, one table, one form (mode-switched), so trials and experiments
  display together on the recipe.
- **Shared on every test** — version linkage, tasting notes, photos, structured
  ratings (overall + per-dimension).
- **Experiment-only fields** — a **hypothesis** and a **what-changed-vs-expected**
  record. A trial run is pure feedback with no hypothesis and no formal
  change record.
- **Experiment outcome** — a free-text **outcome narrative** plus a structured
  **verdict**: Worked / Didn't work / Inconclusive. The verdict is a
  machine-readable signal Phase 5's AI agent can act on.
- **Tests are fully editable and deletable** by the recipe owner — standard
  owner-scoped CRUD. A test is the chef's own kitchen log; typos can be fixed
  and photos added later. (This is deliberately *unlike* immutable recipe
  versions.)

### Feedback capture

- **Structured ratings use a 1–10 numeric scale.**
- **Rating dimensions: fixed defaults + custom additions.** A fixed set of
  default dimensions (e.g. Taste, Texture, Appearance, Aroma) applies to every
  test, and the chef can add a custom dimension on an individual test (e.g.
  "crumb", "spice level"). Exact default dimension list is Claude's discretion.
- **Overall score + optional per-dimension scores.** Each test has one
  **required overall rating** (1–10) for an at-a-glance result; per-dimension
  scores are **optional** detail.
- **Tasting notes** — free-text, on every test.
- **What-changed-vs-expected (experiments)** — recorded as **structured change
  rows**. Each deviation is a row capturing: *what changed* / *expected effect*
  / *actual effect*. Machine-readable for Phase 5; maps directly to TEST-04.

### UI placement & version linkage

- **Dedicated tests page per recipe** — a new `/recipes/{recipe}/tests` route
  holding the test list and the recording entry point. The existing
  `recipes/show.tsx` is already a heavy single-page builder; tests get their own
  page rather than adding weight to it.
- **Recording a test happens in a modal dialog** — a "Record test" button opens
  a dialog containing the form (type toggle, version picker, ratings, notes,
  photos, and the experiment fields when type = experiment). Consistent with the
  app's existing dialog patterns.
- **Version linkage: default to current, pickable.** A new test pre-selects the
  recipe's current version; the chef can pick an older version instead (e.g.
  logging a test retroactively). A test always stores the exact
  `recipe_version_id` it was run against.
- **The builder surfaces a compact test summary.** `recipes/show.tsx` shows an
  at-a-glance summary (test count + latest overall rating) that links to the
  full tests page — this is how tests are "visible on the recipe detail page"
  (success criterion 3), given there is no separate read-only detail page.

### Photo handling (first file-upload feature in the app)

- **Multiple photos per test, with a sensible cap** (e.g. up to ~8–10) — a
  trial usually has both process and finished-dish shots. Exact cap is Claude's
  discretion.
- **Storage: local `public` disk, disk name read from filesystem config.** Uses
  Laravel's standard public disk (`storage/app/public` + symlink), which works
  immediately in Herd/dev. The disk is resolved from config (not hard-coded) so
  a later move to S3 / Laravel Cloud is a config change, not a code change.
- **Upload UX: drag-drop + file picker, upload on form submit.** The chef adds
  photos (drag or browse), sees local previews, and all photos upload atomically
  when the test is saved. A cancelled modal leaves no orphaned files.
- **Display: thumbnail grid + click-to-enlarge lightbox** on the saved test
  record.

### Claude's Discretion

- The exact **default rating dimension list** (taste, texture, appearance,
  aroma, …) and how custom dimensions are stored.
- The exact **photo cap** number, accepted image formats, max file size, and
  whether thumbnails are generated server-side.
- **Tests list organization** on the tests page (chronological vs grouped by
  version, sorting, any filtering).
- The **data model / schema** for tests, ratings, change rows, and photos —
  table structure, JSON vs relational for ratings/change-rows, within the
  decisions above.
- Empty-state design for a recipe with no tests yet.
- Whether the version history sheet additionally shows a per-version test count
  (the builder summary is required; per-version indicators are optional).
- Exact verdict enum naming and i18n strings.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — recipe tests
- `Project.md` (repo root) — **§3.4 Recipe Tests (Trials & Experiments)**: the
  authoritative description of trial runs vs structured experiments, the
  feedback captured (tasting notes, photos, structured ratings, what-changed-
  vs-expected), and the note that test feedback feeds Phase 5's AI agent.
- `.planning/REQUIREMENTS.md` — definitions for this phase's requirements:
  **TEST-01, TEST-02, TEST-03, TEST-04** (see the "Recipe Tests" section).
- `.planning/ROADMAP.md` §"Phase 4: Recipe Tests" — goal, the 3 success
  criteria, dependency on Phase 3.

### Phase 3 foundations this phase builds on
- `.planning/phases/03-recipe-core-metrics/03-CONTEXT.md` — the recipe domain,
  the versioning model (Save commits an immutable, auto-numbered `RecipeVersion`;
  tests attach to one of these), the single-page builder (`recipes/show.tsx` IS
  the recipe detail surface — there is no separate read-only page), and the
  version history sheet pattern.

### Phase 1 foundations
- `.planning/phases/01-foundation/01-CONTEXT.md` — role/permission model
  (recipes and their tests are owner-scoped), localization infra (test UI
  strings are translatable EN/EL), and the warm-minimal design system.

### Codebase maps — existing patterns to follow
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, `FormRequest` per
  action, shared rules in `app/Concerns/` traits, thin controllers returning
  `Inertia::render()` or redirects, shadcn/ui composition, `cn()` helper,
  Wayfinder routes (never hand-edit `@/actions/` `@/routes/`).
- `.planning/codebase/STRUCTURE.md` — directory layout (models, feature-grouped
  controllers, Inertia pages under `resources/js/pages/`, `app/Support/` for
  service classes).
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `TESTING.md`,
  `CONCERNS.md` — Inertia v3 bridge, shared props, the Pest testing approach
  (every change programmatically tested).

### No external spec for file uploads
- This phase introduces the **first real file-upload feature** in the app
  (`hero_image_path` / `step_image_path` columns exist from Phase 3 but were
  never wired to actual uploads). There is no external spec — the photo-handling
  decisions above are authoritative; follow standard Laravel filesystem
  conventions (`config/filesystems.php` `public` disk).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`Recipe` + `RecipeVersion` models** (`app/Models/`) — `RecipeVersion` is the
  entity a test links to (`recipe_version_id`); versions are auto-numbered
  (`version_number`) and immutable. `Recipe::versions()` and
  `Recipe::currentVersion()` relations already exist.
- **`recipes/show.tsx`** — the single-page recipe builder; a compact test
  summary block + "Tests" link is added here. The page already loads `versions`
  and `currentVersion` props the test summary can reuse.
- **Version history sheet** (`components/recipes/recipe-builder/
  version-history-sheet.tsx`) — pattern for any per-version test surfacing.
- **shadcn/ui primitives** (`resources/js/components/ui/`) — `dialog` (record-
  test modal), `card`, `badge`, `tabs`/`toggle-group` (trial/experiment type
  toggle), `textarea`, `input`, `select` (version picker), `skeleton`,
  `sonner`, `tooltip` — the tests page, modal, and photo grid compose from these.
- **Feature-grouped controllers** — `app/Http/Controllers/Recipes/` already
  holds `RecipeController`, `RecipeDraftController`, etc.; a `RecipeTestController`
  (or `Tests/` subfolder) follows the precedent.
- **Owner-scoped policies** — `app/Policies/` + spatie/laravel-permission;
  recipes are owner-scoped and tests inherit that scoping.
- **`HandleInertiaRequests`** shares the user's `locale` — test UI renders in the
  selected language with no new plumbing.

### Established Patterns
- **Models** via `php artisan make:model` with factories + seeders; Pest feature
  tests mirror `app/`.
- **Validation** — a `FormRequest` per action; shared rules into
  `app/Concerns/` traits.
- **Controllers** — thin, feature-grouped, return `Inertia::render(...)` or a
  redirect (Inertia mutations redirect, not JSON).
- **Routing** — server-driven Inertia, named routes, Wayfinder regeneration;
  static route segments declared before `{recipe}` wildcards (see `routes/web.php`
  recipe block).
- **Decimal precision** — note this phase is mostly qualitative; ratings are
  small integers (1–10), so `brick/math` / DECIMAL precision concerns do not
  apply to test data.

### Integration Points
- **`RecipeVersion` table** — tests reference `recipe_version_id`; planner adds
  the relationship.
- **`Recipe` / `User` models** — gain a `tests()` relationship; tests are
  owner-scoped.
- **`routes/web.php`** — new `recipes/{recipe}/tests` routes (index + store +
  update + destroy); declare static segments correctly relative to the existing
  `recipes/{recipe}` wildcard.
- **`recipes/show.tsx`** — the builder gains a test-summary block + link.
- **`config/filesystems.php`** — the `public` disk; `php artisan storage:link`
  is required for the first upload feature.
- **`resources/js/pages/recipes/tests/`** — new Inertia page(s) for the tests
  list; new components under `resources/js/components/recipes/` for the record-
  test modal and photo grid.

</code_context>

<specifics>
## Specific Ideas

- A test is the **chef's own kitchen log** — that is why tests are freely
  editable and deletable, unlike the immutable recipe versions they attach to.
- The **verdict** (Worked / Didn't work / Inconclusive) and the **structured
  change rows** exist specifically so Phase 5's AI agent gets machine-readable
  signal, not just prose, when it reads test feedback.
- `recipes/show.tsx` IS the recipe detail surface — there is no separate
  read-only page — so "tests visible on the recipe detail page" is satisfied by
  a compact summary block on the builder linking to the dedicated tests page.
- Photo upload is the **first file-storage feature** in the whole app; doing it
  on the config-driven `public` disk keeps a future S3 / Laravel Cloud move to a
  config change.

</specifics>

<deferred>
## Deferred Ideas

- **AI agent reading test feedback** to suggest improvements / variants
  (AI-01…07) — Phase 5. Phase 4 only records feedback; the verdict and
  structured change rows are shaped to feed it.
- **Applying a test outcome back into the recipe draft** — Phase 5's agent flow;
  Phase 4 records what changed but does not mutate the recipe.
- **Wiring up Phase 3's `hero_image_path` / `step_image_path`** to real uploads
  — out of scope here; this is a pre-existing Phase 3 gap. Phase 4's photo
  upload may establish a reusable upload pattern, but retrofitting recipe images
  is not Phase 4 work.
- **Publishing tests / sharing test results publicly** — relates to Phase 6;
  tests are owner-private in Phase 4.

</deferred>

---

*Phase: 04-recipe-tests*
*Context gathered: 2026-05-17*
