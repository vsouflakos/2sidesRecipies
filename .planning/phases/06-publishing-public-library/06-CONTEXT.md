# Phase 6: Publishing & Public Library - Context

**Gathered:** 2026-05-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 6 lets a chef share recipes beyond their own account. It delivers:

- **Private-by-default visibility** — recipes remain private (owner-only) unless
  the owner explicitly publishes them (PUB-01).
- **Publish / unpublish** — the owner can publish a recipe to a public library
  and unpublish it at any time (PUB-02, PUB-03).
- **A public library** — a browsable, searchable catalogue of all published
  recipes across all users, filterable by name, tag, cuisine, allergen,
  difficulty, and time (PUB-04).
- **A dedicated public recipe page** — a clean, share-friendly read view of a
  published recipe for non-owners.

It does NOT build: a community feed, likes, comments, follows, or external
social sharing (all explicitly post-MVP social-platform scope); cloning another
chef's published recipe into your own account; or clickable author profile
pages. It does not change recipe authoring, versioning, metrics, tests, or the
AI agent — it consumes the existing Phase 3 recipe + version model.

</domain>

<decisions>
## Implementation Decisions

### Version semantics — what gets published and what the public sees

- **Publishing pins a specific committed version.** The public sees a frozen
  snapshot of one version; the owner's later working-draft edits never leak.
  This mirrors Phase 3's sub-recipe version pinning — a private edit must never
  silently change what is public.
- **The owner picks which version to publish.** The initial publish flow
  presents a version picker (e.g. publish v3, not necessarily the latest v5),
  not a one-click publish-current.
- **After publishing, new Saves stay pinned.** When the owner commits a newer
  version, the public recipe stays on the published version. The owner sees an
  "update public version" cue but is never auto-updated.
- **Updating the public version is one-click to current.** Acting on the update
  cue publishes the owner's current committed version in one click — it does
  **not** re-open the version picker. (Note the asymmetry, captured
  deliberately: initial publish = version picker; update = one-click current.)
- **The public sees a single static recipe.** No version numbers, no version
  history, no diffs on the public page. Versioning stays the owner's private
  working tool.

### Public recipe page — format and privacy

- **A dedicated public recipe page**, purpose-built and clean — NOT a read-only
  reuse of the owner's builder. Project.md asks for public pages that stay
  "clean and shareable."
- **All cost and pricing data is hidden from the public.** Cost per portion,
  total cost, food cost %, and selling price never appear publicly — they are
  competitive business data.
- **Nutrition and allergens stay public.** These are the shared product value
  and the reason a published recipe is worth reading.
- **Chef notes are never published.** Free-text chef notes stay an owner-only
  working artifact.
- **Recipe tests and the AI conversation are never public.** They are owner-only
  working artifacts (low-ambiguity — decided implicitly, not via the same
  publish/hide control).
- **Sub-recipes must be published first.** Publishing a recipe that contains a
  sub-recipe is blocked until every referenced sub-recipe is itself published;
  each sub-recipe links to its own public page.

### Public library — browse, search, access

- **A new top-level nav entry** ("Public Library" / "Discover") with its own
  route — alongside "My Recipes" and "Ingredients". Clear separation: My Recipes
  = the owner's recipes, Library = everyone's published recipes.
- **Default sort: recently published first.** No popularity/views/trending
  ranking — there are no social signals in the MVP.
- **Author name is shown** on library cards and the public recipe page. No
  clickable profile pages (post-MVP social scope) — attribution only.
- **The library and public recipe pages are reachable without login.** Truly
  shareable links. These routes live OUTSIDE the `auth`/`verified` middleware
  group; guest access is a first-class requirement, not an afterthought.

### Publish controls & lifecycle

- **The publish / unpublish control lives in both places** — the recipe builder
  page and the My Recipes card menu. Both code paths must stay consistent.
- **Deleting a published recipe is blocked.** The owner must unpublish first;
  deletion of a published recipe is refused with a clear message.
- **Public recipe URLs are slug-based** — e.g. `/library/wild-mushroom-risotto-a1b2c3`.
  Recipes already carry a unique `slug` column; reuse it. Readable, shareable.

### Private ingredients inside a published recipe

- **Publishing is allowed, and the private ingredient's data is snapshotted into
  the published version.** The public recipe stays correct even if the owner
  later edits or deletes that private ingredient. This extends the pinned-
  snapshot principle: a published version is a self-contained, frozen artifact.
  (Planner: check whether `RecipeVersion` already snapshots ingredient
  nutrition/allergen data; if not, a publish-time snapshot is needed.)

### Claude's Discretion

- The exact nav label ("Public Library" vs "Discover" vs "Explore") and the
  exact route prefix (`/library` vs `/recipes/public`).
- The public-library filter set: PUB-04 mandates name, tag, cuisine, allergen,
  difficulty, time. The existing `RecipeFilters` component also has an
  "ingredient" filter — keeping it (a harmless superset) or dropping it is
  discretion.
- Pagination size, search debounce timing, and search ranking for the library.
- Exact wording and placement of the "update public version" cue.
- Confirmation-dialog wording for unpublish and for the blocked-delete message.
- Visual treatment of the "Published" status badge on My Recipes cards.
- The public library's empty state.

</decisions>

<specifics>
## Specific Ideas

- **Public pages must be clean and share-friendly** (Project.md §7). Slug URLs,
  author attribution, and guest (no-login) access all reinforce this. The design
  should anticipate the post-MVP social platform without building any of it.
- **A published recipe is a self-contained frozen artifact** — a pinned version
  plus snapshotted ingredient data. This is the same principle as Phase 3's "a
  sub-recipe change must never silently alter a parent": a private edit must
  never silently change what is public.
- **Asymmetry, intentional:** the first publish is a deliberate act with a
  version picker; pushing a later update is a quick one-click. The owner chooses
  carefully the first time, then updates cheaply.

</specifics>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec — visibility & publishing
- `Project.md` (repo root) — **§3.2 Recipes** (visibility: recipes private by
  default, user can publish specific recipes to a public library), **§3.3
  Working Draft vs Saved Versions** (the version/draft model publishing pins
  against), **§7 Design Direction** ("keep public recipe pages clean and
  shareable"; the post-MVP social-platform direction and what is explicitly out
  of MVP).
- `.planning/PROJECT.md` — Key Decisions table: "Recipes private by default,
  publishable to a public library"; the social platform deferred post-MVP.
- `.planning/REQUIREMENTS.md` — definitions for **PUB-01 … PUB-04**, and the
  Out-of-Scope table (social platform, external sharing, real-time
  collaboration) bounding this phase.

### Roadmap
- `.planning/ROADMAP.md` §"Phase 6: Publishing & Public Library" — goal, the 3
  success criteria, and the dependency on Phase 3 (not Phase 5).

### Phase 3 foundations this phase builds on
- `.planning/phases/03-recipe-core-metrics/03-CONTEXT.md` — the recipe +
  version + working-draft model, sub-recipe version pinning ("a sub-recipe
  change must never silently alter a parent"), and the recipe-list pattern this
  phase reuses: visual card grid with hero image, collapsible six-filter panel,
  live debounced name search, recently-updated sort.

### Phase 1 foundations
- `.planning/phases/01-foundation/01-CONTEXT.md` — role/permission model
  (publishing is an owner action; any User role may publish their own recipes),
  warm-minimal design system, EN/EL localization infrastructure.

### Codebase maps — existing patterns to follow
- `.planning/codebase/STRUCTURE.md` — directory layout (feature-grouped
  controllers, Inertia pages under `resources/js/pages/`, `app/Support/`).
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, FormRequest per
  action, thin controllers returning `Inertia::render()` / redirects,
  shadcn/ui composition, Wayfinder routes.
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `INTEGRATIONS.md`,
  `TESTING.md`, `CONCERNS.md` — Inertia v3 bridge, shared props, Pest testing.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`RecipeController::index`** (`app/Http/Controllers/Recipes/RecipeController.php`)
  — an owner-scoped, filtered, paginated card-grid query (`paginate(24)`,
  `withQueryString`, six `when()` filters, `RecipeListResource`). The public
  library index is a parallel controller/method reusing this exact pattern but
  scoped to published recipes across all users.
- **`resources/js/pages/recipes/index.tsx`** + **`RecipeCard`** +
  **`RecipeFilters`** components — the card grid, filter panel, debounced
  search, skeleton loading, and pagination are directly reusable for the public
  library page (the library card adds an author name).
- **`RecipeListResource`** — the list-card data shape; the public library needs
  a similar resource (likely a variant adding author name, omitting nothing
  cost-sensitive at the card level — verify what the card surfaces).
- **`RecipeBuilderResource`** / `resources/js/pages/recipes/show.tsx` — the
  owner builder. The public recipe page is a NEW dedicated Inertia page, NOT a
  reuse of `show.tsx`; it must strip cost/pricing, notes, tests, AI, and version
  history.
- **`Recipe` model** — has a unique `slug` column already (used in `store()`),
  ready to serve the slug-based public URL. `SoftDeletes` is in use.
- **`RecipeVersion` model** — the pinned published version; the snapshot the
  public page renders from.
- **`RecipePolicy`** — currently `view`/`update`/`delete` are all owner-only.
  `view` must be extended so anyone (including guests) can view a published
  recipe; `delete` must refuse while the recipe is published.

### Established Patterns
- **Models / migrations** via `php artisan make:` ; the publish state needs new
  `recipes` columns (e.g. published flag, `published_version_id` FK to
  `recipe_versions`, `published_at`).
- **Validation** — a `FormRequest` per action; the publish request validates the
  chosen version and the sub-recipes-published prerequisite.
- **Controllers** — thin, feature-grouped (`app/Http/Controllers/Recipes/` or a
  new public-facing group); return `Inertia::render()` or a redirect.
- **Routing** — named routes, Wayfinder regeneration. Note the Phase 2/3
  lesson: declare static-segment routes before `{recipe}` wildcards.
- **Testing** — Pest feature tests mirror `app/`; Wave 0 red-test scaffold
  pattern. Guest-access paths and the privacy redactions need explicit tests.

### Integration Points
- **`routes/web.php`** — recipe routes currently sit inside the
  `['auth','verified']` group. The public library index and public recipe page
  routes must live OUTSIDE that group (guest-accessible). Publish/unpublish and
  the owner-facing controls stay inside it.
- **`Recipe` migration** — new publish-state columns.
- **`RecipePolicy`** — `view` extended for published recipes; `delete` gated on
  publish state.
- **App-shell navigation** — a new "Public Library" entry; must render sensibly
  for guests as well as authenticated users.
- **`HandleInertiaRequests`** — shared props must be guest-safe on the public
  routes (no assumption of an authenticated user).
- **`resources/js/pages/`** — a new public library index page and a new public
  recipe page (likely under a `library/` directory).
- **EN/EL translation files** — new keys for publishing, the library, and the
  public page.

</code_context>

<deferred>
## Deferred Ideas

- **Community feed, likes, comments, follows** — post-MVP social platform
  (PROJECT.md / Project.md §7); explicitly out of MVP scope.
- **External social sharing** (Instagram, TikTok forwarding) — post-MVP;
  public pages are designed share-friendly so this evolves naturally.
- **Cloning / duplicating another chef's published recipe** into your own
  account — not in PUB-01…04; borders social scope. Phase 3's Duplicate is
  owner-own only. Note for the roadmap backlog.
- **Clickable author profile pages** — post-MVP social scope; Phase 6 shows the
  author name as plain attribution only.
- **Popularity / views / trending ranking** for the library — needs social
  signals that do not exist in the MVP; post-MVP.
- **Collaborative comments / annotations** (COLLAB-01) — v2 requirement.

</deferred>

---

*Phase: 06-publishing-public-library*
*Context gathered: 2026-05-18*
