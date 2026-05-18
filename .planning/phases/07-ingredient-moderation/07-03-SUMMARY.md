---
phase: 07-ingredient-moderation
plan: 03
subsystem: ui
tags: [react, inertia, tailwind, permissions, spatie, moderation, notifications]

# Dependency graph
requires:
  - phase: 07-ingredient-moderation
    plan: 02
    provides: backend routes, policies, IngredientSubmissionResource, shared Inertia props (pendingIngredientReviewCount, ingredientNotifications, auth.permissions)
provides:
  - Owner-facing Submit for Inclusion / Withdraw CTA with duplicate-warning dialog and frozen data banner
  - SubmissionStatusBadge component (private/submitted/approved/rejected states)
  - SubmissionCompleteness component with three tooltip-wrapped dot indicators
  - Moderator FIFO review queue page with completeness signal, resubmit badge, and FIFO ordering
  - Per-submission review screen (two-column desktop layout) with approve/reject dialogs and required-notes-on-reject validation
  - Pending-count nav badge on Ingredient Review sidebar entry (moderators only)
  - In-app notification bell surface surfacing decision notifications in sidebar footer
  - Contributed-by credit on approved ingredient detail page
  - Frozen data banner when ingredient is under review
  - EN/EL moderation copy (50+ keys under ingredients.* and nav.*)
  - IngredientDetailResource enriched with submission_status and contributed_by fields
affects: [any future phase referencing ingredient moderation, auth.permissions propagation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Permission propagation via getAllPermissions()->pluck('name') (not getPermissionNames()) for role-derived permissions
    - NavItem badge field pattern for sidebar pending-count badges
    - Sticky right-panel review layout (lg:grid-cols-[1fr_320px] + lg:sticky lg:top-16)
    - In-app notification bell in SidebarFooter (above NavUser) — outside primary nav

key-files:
  created:
    - resources/js/components/ingredient-notifications.tsx
    - resources/js/components/ingredients/submission-status-badge.tsx
    - resources/js/components/ingredients/submission-completeness.tsx
    - resources/js/components/ingredients/submit-action.tsx
    - resources/js/pages/admin/ingredients/show.tsx
  modified:
    - resources/js/types/navigation.ts
    - resources/js/components/nav-main.tsx
    - resources/js/components/app-sidebar.tsx
    - resources/js/pages/admin/ingredients.tsx
    - resources/js/pages/ingredients/show.tsx
    - resources/js/types/ingredient.ts
    - app/Http/Resources/IngredientDetailResource.php
    - app/Http/Controllers/Ingredients/IngredientController.php
    - lang/en/app.php
    - lang/el/app.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - database/seeders/RolesAndPermissionsSeeder.php

key-decisions:
  - "getAllPermissions()->pluck('name') used instead of getPermissionNames() in HandleInertiaRequests — spatie/laravel-permission getPermissionNames() returns only direct permissions, ignoring role-derived ones; since all permissions in this app are assigned via roles, auth.permissions was always empty until this fix"
  - "RolesAndPermissionsSeeder committed with demo moderator and user accounts to enable end-to-end manual verification without needing manual DB seeding"

patterns-established:
  - "NavItem badge?: string pattern — numeric badge count rendered as destructive Badge next to sidebar menu item title"
  - "IngredientNotifications in SidebarFooter above NavUser — notification surface outside primary nav area per UI-SPEC constraint"
  - "Two-column review screen: lg:grid-cols-[1fr_320px] data panel left, lg:sticky lg:top-16 moderation panel right"

requirements-completed: [INGR-09, INGR-10, INGR-11]

# Metrics
duration: ~90min
completed: 2026-05-18
---

# Phase 7 Plan 03: Moderation Frontend Summary

**Full ingredient moderation UI: owner submit/withdraw CTA with duplicate-warning dialog and frozen banner, moderator FIFO review queue with completeness dots and pending-count nav badge, per-submission review screen with approve/reject dialogs, in-app notification bell, contributed-by credit, and complete EN/EL copy for all 50+ moderation keys.**

## Performance

- **Duration:** ~90 min
- **Started:** 2026-05-18T13:40:00Z
- **Completed:** 2026-05-18T15:10:00Z
- **Tasks:** 4/4 (3 auto + 1 human-verify checkpoint, APPROVED)
- **Files modified:** 17

## Accomplishments

- Delivered the complete owner-facing submission flow: Submit for Inclusion button with duplicate-warning dialog, Under Review badge with frozen data banner, Withdraw Submission button with confirmation
- Delivered the moderator review surface: FIFO queue table with SubmissionCompleteness dot indicators and resubmit badges, pending-count nav badge (sidebar, moderators only), per-submission review screen with approve/reject panels and required-notes-on-reject client-side validation
- Fixed a latent bug where `auth.permissions` was always empty due to `getPermissionNames()` returning only direct permissions — fixed to `getAllPermissions()->pluck('name')` so sidebar permission gates (Ingredient Review, admin Users) function correctly for role-based permissions
- Added in-app notification bell in SidebarFooter surfacing moderator approve/reject decisions to submitters
- EN/EL moderation copy complete with 50+ keys under `ingredients.*` and `nav.*` namespaces
- Full Ingredients + Access feature suite: 91/91 GREEN post-fix; `npm run build` exits 0

## Task Commits

Each task was committed atomically:

1. **Task 1: NavItem badge support + Ingredient Review nav entry + notification surface** — `37f04f8` (feat)
2. **Task 2: Review queue page + per-submission review screen + completeness signal** — `1c9e6a5` (feat)
3. **Task 3: Submit/Withdraw action + Contributed-by credit + EN/EL copy** — `90519c0` (feat)
4. **Task 4: End-to-end human verification** — APPROVED by user (no code commit)

**Checkpoint deviation fix:** `8399453` (fix — role-derived permissions + seeder)

## Files Created/Modified

- `resources/js/types/navigation.ts` — Added `badge?: string` to NavItem type
- `resources/js/components/nav-main.tsx` — Renders destructive Badge when `item.badge` is set
- `resources/js/components/app-sidebar.tsx` — Ingredient Review nav entry (moderator-gated), IngredientNotifications mount, pendingIngredientReviewCount badge wiring
- `resources/js/components/ingredient-notifications.tsx` — Bell-icon notification popover reading `ingredientNotifications` shared prop; links each entry to ingredient detail
- `resources/js/components/ingredients/submission-status-badge.tsx` — Status badge (submitted=secondary, rejected=destructive, approved=default, private=nothing)
- `resources/js/components/ingredients/submission-completeness.tsx` — Three tooltip-wrapped 16px dots (green-500=complete, muted=absent) for nutrition/allergens/conversions
- `resources/js/components/ingredients/submit-action.tsx` — Submit/Withdraw CTA: duplicate-check fetch, confirmation dialogs, router.post/router.delete mutations
- `resources/js/pages/admin/ingredients.tsx` — Full FIFO review queue table replacing stub (SubmissionCompleteness, resubmit badges, empty state)
- `resources/js/pages/admin/ingredients/show.tsx` — Per-submission review screen (two-column desktop, approve/reject dialogs, prior rejections as Alerts)
- `resources/js/pages/ingredients/show.tsx` — SubmitAction integration, frozen banner (submitted state), contributed-by credit (approved state)
- `resources/js/types/ingredient.ts` — Added `submission_status`, `contributed_by`, `can.submit`, `can.withdraw` fields
- `app/Http/Resources/IngredientDetailResource.php` — Added `submission_status` and `contributed_by` fields
- `app/Http/Controllers/Ingredients/IngredientController.php` — Eager-loads `latestSubmission.submittedBy`; adds `can.submit`/`can.withdraw` flags
- `app/Http/Middleware/HandleInertiaRequests.php` — Fixed `auth.permissions` to use `getAllPermissions()->pluck('name')` (see Deviations)
- `database/seeders/RolesAndPermissionsSeeder.php` — Demo moderator and regular user accounts committed
- `lang/en/app.php` — 50+ moderation keys under `ingredients.*` and `nav.*`
- `lang/el/app.php` — Matching 50+ EL translations

## Decisions Made

- `getAllPermissions()->pluck('name')` replaces `getPermissionNames()` in `HandleInertiaRequests` — the spatie method returns only directly-assigned permissions, not role-derived ones; since all permissions in this app flow through roles, the shared `auth.permissions` array was always empty before this fix.
- RolesAndPermissionsSeeder committed with demo moderator/user accounts so end-to-end verification can be repeated without manual DB intervention.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed auth.permissions always empty — getPermissionNames() → getAllPermissions()->pluck('name')**

- **Found during:** Task 4 (End-to-end human verification)
- **Issue:** During live verification, the Ingredient Review sidebar entry was invisible to the moderator and the admin Users entry was also missing. `HandleInertiaRequests::share()` was calling `getPermissionNames()`, which in this version of spatie/laravel-permission returns **only direct permissions** and ignores role-derived permissions. Because all permissions in this app are assigned via roles (not directly), `auth.permissions` was always an empty array — causing every permission gate in `app-sidebar.tsx` to fail silently.
- **Fix:** Changed `$user->getPermissionNames()` to `$user->getAllPermissions()->pluck('name')` in `HandleInertiaRequests::share()`. Also committed the previously-uncommitted RolesAndPermissionsSeeder demo accounts in the same fix commit.
- **Files modified:** `app/Http/Middleware/HandleInertiaRequests.php`, `database/seeders/RolesAndPermissionsSeeder.php`
- **Verification:** After fix, moderator account shows Ingredient Review nav entry with pending-count badge; admin account shows Users nav entry. Full test suite 91/91 GREEN. User approved the complete submit→review→approve/reject→promote flow in EN and EL.
- **Committed in:** `8399453` (fix(07-03): share role-derived permissions, seed demo moderator/user)

---

**Total deviations:** 1 auto-fixed (1 bug — latent permissions propagation)
**Impact on plan:** Fix was essential for correctness — without it, no permission-gated UI was functional. No scope creep.

## Issues Encountered

None beyond the deviation documented above.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Phase 7 is now complete (all 3 plans: 07-01 backend foundation, 07-02 backend moderation engine, 07-03 frontend moderation UI).
- The full ingredient moderation lifecycle (submit → review → approve/reject → promote/revert → notify) is operational.
- The `auth.permissions` fix is applied globally — all future plans can rely on role-derived permissions being correctly shared to the frontend.

---
*Phase: 07-ingredient-moderation*
*Completed: 2026-05-18*

## Self-Check: PASSED

- `37f04f8` exists: CONFIRMED (git log)
- `1c9e6a5` exists: CONFIRMED (git log)
- `90519c0` exists: CONFIRMED (git log)
- `8399453` exists: CONFIRMED (git log)
- SUMMARY.md created at `.planning/phases/07-ingredient-moderation/07-03-SUMMARY.md`
