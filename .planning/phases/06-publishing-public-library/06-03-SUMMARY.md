---
phase: 06-publishing-public-library
plan: 03
subsystem: ui
tags: [react, inertia, typescript, shadcn, wayfinder, laravel, api-resource, pest, i18n]

# Dependency graph
requires:
  - phase: 06-publishing-public-library/06-01
    provides: is_published/published_version_id/published_at columns, publishedVersion() relation, RecipePolicy nullable ?User, library stub pages
  - phase: 06-publishing-public-library/06-02
    provides: PublishRecipeController, LibraryController, PublicRecipeResource/PublicRecipeListResource, library + publish routes, Wayfinder @/routes/library
provides:
  - PublishRecipeDialog (version-picker) and UnpublishRecipeDialog (destructive confirm) components
  - Builder header publish controls — Publish button, Published badge, Unpublish button, Update-to-current cue
  - RecipeCard publish augmentation — Published badge and card actions menu with publish-blocked Delete
  - LibraryRecipeCard — public card linking to library.show with author attribution, no cost
  - GuestPublicLayout — minimal guest-safe layout for public pages
  - library/index.tsx — full filterable/paginated public library browse page
  - library/show.tsx — dedicated public recipe page (hero, header, metrics, sections, steps, nutrition tabs)
  - Library nav entry with Globe icon
  - Full EN/EL Phase 6 translations
  - PublicRecipeResource unit_id->symbol resolution
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Inertia page-level static `layout` property used to assign a non-default layout per page (LibraryIndex.layout / LibraryShow.layout = GuestPublicLayout)"
    - "Guest-safe layout pattern — public pages render through GuestPublicLayout instead of AppSidebarLayout so they never depend on the auth-only sidebar"
    - "API Resource id->symbol resolution — PublicRecipeResource builds a Unit id=>symbol lookup map to resolve snapshot unit_id values server-side (public page receives no units list)"

key-files:
  created:
    - resources/js/components/recipes/publish-recipe-dialog.tsx
    - resources/js/components/recipes/unpublish-recipe-dialog.tsx
    - resources/js/components/recipes/library-recipe-card.tsx
    - resources/js/layouts/guest-public-layout.tsx
  modified:
    - resources/js/pages/recipes/show.tsx
    - resources/js/components/recipes/recipe-card.tsx
    - resources/js/pages/library/index.tsx
    - resources/js/pages/library/show.tsx
    - resources/js/components/app-sidebar.tsx
    - resources/js/types/recipe.ts
    - app/Http/Resources/RecipeBuilderResource.php
    - app/Http/Resources/RecipeListResource.php
    - app/Http/Resources/PublicRecipeResource.php
    - lang/en/app.php
    - lang/el/app.php
    - tests/Feature/Library/LibraryBrowseTest.php

key-decisions:
  - "PublicRecipeResource resolves snapshot unit_id to a unit symbol via a Unit id=>symbol lookup map — snapshot ingredient lines store unit_id (numeric), not a unit string; the public page gets no units list so resolution must happen server-side"
  - "PublicRecipeResource.mapSection defensively accepts both `lines` (draft shape) and `ingredient_lines` (resource shape) snapshot keys"
  - "Public pages use a dedicated GuestPublicLayout (not AppSidebarLayout) so guest browse never depends on the auth-only sidebar/NavUser"
  - "Library index excludes the ingredient filter from its reload data payload per UI-SPEC — the ingredient filter is a private-recipe planning tool, not a public-browse signal"
  - "Update-to-current is a one-click action with no confirmation dialog — intentional asymmetry per CONTEXT.md (publish/unpublish confirm, version update does not)"
  - "Login link in GuestPublicLayout uses a plain /login href — the @/routes/login Wayfinder module exports only `store` (POST), not an `index`"

patterns-established:
  - "Inertia static layout property: LibraryIndex.layout / LibraryShow.layout = (page) => <GuestPublicLayout>{page}</GuestPublicLayout>"
  - "Resource-side id resolution: build a pluck('symbol','id') lookup map once in toArray and pass it into the per-section mapper"

requirements-completed: [PUB-01, PUB-02, PUB-03, PUB-04]

# Metrics
duration: 45min
completed: 2026-05-18
---

# Phase 06 Plan 03: Publishing & Public Library UI Summary

**Publish/unpublish controls (version-picker dialog, builder header badge/cue, card menu), a guest-accessible filterable public library, and a dedicated public recipe page — with a guest-safe layout, Library nav entry, full EN/EL copy, and a post-checkpoint fix for ingredient-line rendering**

## Performance

- **Duration:** ~45 min (including human-verify checkpoint round-trip)
- **Started:** 2026-05-18T16:33:22Z
- **Completed:** 2026-05-18T17:18:00Z
- **Tasks:** 3 (2 build tasks + 1 human-verify checkpoint)
- **Files modified:** 16 (4 created, 12 modified)

## Accomplishments
- Owners can publish a recipe via a version-picker dialog, see a Published badge, push a one-click version update, and unpublish — from both the builder header and the My Recipes card menu
- The Delete card-menu item is rendered disabled with a tooltip while a recipe is published
- Any visitor (logged in or out) can browse `/library`, search and filter published recipes, and open a clean public recipe page
- The public recipe page shows hero, author, published date, nutrition tabs, ingredient lines, and steps — with cost, selling price, food cost %, chef notes, tests, and AI all redacted
- A Globe-icon "Library" nav entry and a minimal GuestPublicLayout were added; all four Phase 6 UI surfaces from 06-UI-SPEC.md shipped
- EN/EL translations complete with verified parity (PARITY OK)
- Post-checkpoint fix: PublicRecipeResource now resolves snapshot `unit_id` to a unit symbol so ingredient lines render correctly on the public page

## Task Commits

Each task was committed atomically:

1. **Task 1: Publish/unpublish controls — dialogs, builder header, My Recipes card menu** - `448d285` (feat)
2. **Task 2: Public library index, public recipe page, library card, guest layout, nav, translations** - `ce36e14` (feat)
3. **Task 3: End-to-end publish + public library verification (human-verify checkpoint)** - PASSED; post-checkpoint fix `2d3ffc2` (fix)

**Plan metadata:** committed separately (docs: complete plan)

## Files Created/Modified
- `resources/js/components/recipes/publish-recipe-dialog.tsx` - Version-picker publish dialog; shadcn Select of committed versions, sub-recipe error Alert, posts version_id to recipes.publish
- `resources/js/components/recipes/unpublish-recipe-dialog.tsx` - Unpublish confirmation dialog with destructive confirm button
- `resources/js/components/recipes/library-recipe-card.tsx` - Public library card; links to library.show, author attribution, no cost row
- `resources/js/layouts/guest-public-layout.tsx` - Minimal guest-safe layout (AppLogo, Library link, conditional Sign in)
- `resources/js/pages/recipes/show.tsx` - Builder header gains Publish button / Published badge / Unpublish button / Update-to-current cue banner
- `resources/js/components/recipes/recipe-card.tsx` - Card gains Published badge and a DropdownMenu with publish/unpublish items and an aria-disabled publish-blocked Delete item
- `resources/js/pages/library/index.tsx` - Full filterable, searchable, paginated public library grid (replaces Plan 01 stub)
- `resources/js/pages/library/show.tsx` - Dedicated public recipe page: hero, header, metrics strip, sections + ingredient lines, steps, nutrition Tabs (replaces Plan 01 stub)
- `resources/js/components/app-sidebar.tsx` - Adds Globe-icon Library nav entry after Recipes
- `resources/js/types/recipe.ts` - Adds is_published/published_version_id/current_version_id to RecipeBuilderData, is_published to RecipeCardData; new PublicRecipeCardData and PublicRecipeData types
- `app/Http/Resources/RecipeBuilderResource.php` - Emits is_published, published_version_id, current_version_id
- `app/Http/Resources/RecipeListResource.php` - Emits is_published
- `app/Http/Resources/PublicRecipeResource.php` - Resolves snapshot unit_id to unit symbol; defensively reads `lines` or `ingredient_lines`; casts quantity to string
- `lang/en/app.php` / `lang/el/app.php` - New `library` section + `recipes` publish/unpublish keys, EN/EL parity verified
- `tests/Feature/Library/LibraryBrowseTest.php` - Added test asserting the public recipe payload includes ingredient lines (name/quantity/unit) and steps

## Decisions Made
- **PublicRecipeResource unit_id→symbol resolution**: snapshot ingredient lines store `unit_id` (numeric), not a `unit` string. The public page receives no units list, so the resource resolves the id to a symbol server-side via a `Unit::pluck('symbol','id')` lookup map.
- **GuestPublicLayout instead of AppSidebarLayout for public pages**: guest browse must never depend on the auth-only sidebar; a dedicated minimal layout is the clean approach (matches UI-SPEC Surface 3).
- **Login link uses a plain `/login` href**: the `@/routes/login` Wayfinder module exports only `store` (POST), so a Wayfinder helper for the GET login page is not available.
- **Update-to-current has no confirmation dialog**: intentional asymmetry per CONTEXT.md — publish and unpublish confirm; the version update is one-click.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Ingredient lines did not render on the public recipe page**
- **Found during:** Task 3 (human-verify checkpoint)
- **Issue:** `PublicRecipeResource::mapSection()` emitted `'unit' => $line['unit'] ?? null`, reading a `unit` key that does not exist in the version snapshot. Snapshot ingredient lines store `unit_id` (a numeric id), so every line's unit resolved to `null`. The section name, lines array, and per-line name/quantity were all correct — only the unit was broken. (A secondary data factor: the verification recipe had an empty early version published rather than the populated current version.)
- **Fix:** `PublicRecipeResource` now builds a `Unit` id=>symbol lookup map and resolves `unit_id` to its symbol; defensively accepts both `lines` and `ingredient_lines` snapshot keys; casts `quantity` to string for a stable payload.
- **Files modified:** `app/Http/Resources/PublicRecipeResource.php`, `tests/Feature/Library/LibraryBrowseTest.php`
- **Verification:** Resource output confirmed to emit `lines: 5, steps: 8` with resolved units (e.g. `"unit": "g"`); new LibraryBrowseTest case GREEN; all 18 Library tests pass; human re-verified and approved.
- **Committed in:** `2d3ffc2` (fix commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug surfaced at the human-verify checkpoint)
**Impact on plan:** The fix was necessary for correctness — the public recipe page must show ingredient lines per 06-UI-SPEC. No scope creep; the fix is confined to the resource and a new test.

## Issues Encountered
- **Pre-existing TS error pattern (`preserveState` in `router.reload`)**: `library/index.tsx` mirrors the established `recipes/index.tsx` / `ingredients/index.tsx` reload pattern, which reports a known Inertia v3 type-definition false-positive for `preserveState`. The option works correctly at runtime and `npm run build` succeeds. Left as-is for consistency with the existing pages — out of scope to fix the shared type-definition issue.
- **Acceptance-criteria grep for `ingredient` in `library/index.tsx`**: the criterion expects no `ingredient` match, but the necessary `AllergenOption` import from `@/types/ingredient` contains the substring. The functional intent — the `ingredient` filter is excluded from the reload data payload — is satisfied.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 6 (Publishing & Public Library) is complete — all four PUB requirements (PUB-01..PUB-04) delivered across plans 01–03
- The publish lifecycle (publish, version update, unpublish, publish-blocked delete) and the public browse/read experience are live and human-verified
- Next is Phase 7 (Moderation), the final phase of the v1.0 milestone

## Self-Check: PASSED

All created files verified present on disk; all task commits (`448d285`, `ce36e14`, `2d3ffc2`) verified in git history.

---
*Phase: 06-publishing-public-library*
*Completed: 2026-05-18*
