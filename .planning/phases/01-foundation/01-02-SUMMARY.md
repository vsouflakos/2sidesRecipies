---
phase: 01-foundation
plan: 02
subsystem: auth
tags: [spatie-permission, rbac, fortify, soft-deletes, enum, middleware, inertia]

requires:
  - phase: 01-01
    provides: "RolesAndPermissionsSeeder with roles (User/Moderator/Admin) and permissions (manage-users, review-ingredients, create-recipes, manage-own-ingredients)"

provides:
  - "AccountStatus backed enum at app/Enums/AccountStatus.php"
  - "account_status column and soft_deletes on the users table"
  - "User model with HasRoles, SoftDeletes, and AccountStatus cast"
  - "deactivated() factory state on UserFactory"
  - "spatie middleware aliases (role, permission, role_or_permission) in bootstrap/app.php"
  - "Middleware priority list ensuring 403 (not 404) on permission-gated routes"
  - "User role assigned automatically on registration via CreateNewUser::assignRole"
  - "/admin route group gated on permission:manage-users (routes pre-declared for Plan 04)"
  - "EnsureUserIsActive Fortify pipeline step blocking deactivated user login"
  - "/admin/ingredients route gated on permission:review-ingredients"
  - "Admin/IngredientReviewController placeholder returning Inertia admin/ingredients page"
  - "resources/js/pages/admin/ingredients.tsx placeholder page"

affects: [01-04, 01-06, 07-moderation]

tech-stack:
  added: []
  patterns:
    - "Middleware aliases registered via bootstrap/app.php $middleware->alias()"
    - "Middleware priority list via $middleware->priority() ensuring permission runs before SubstituteBindings"
    - "Fortify custom pipeline via Fortify::authenticateThrough() in FortifyServiceProvider"
    - "PHP 8.1 backed enum (AccountStatus) with Eloquent cast"
    - "Route groups with permission middleware gate on named permission strings, not role names"

key-files:
  created:
    - app/Enums/AccountStatus.php
    - database/migrations/2026_05_16_024949_add_account_status_to_users_table.php
    - app/Actions/Fortify/EnsureUserIsActive.php
    - app/Http/Controllers/Admin/IngredientReviewController.php
    - resources/js/pages/admin/ingredients.tsx
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php
    - bootstrap/app.php
    - app/Actions/Fortify/CreateNewUser.php
    - app/Providers/FortifyServiceProvider.php
    - routes/web.php

key-decisions:
  - "Route declarations for admin.users.* are placed in Plan 02 so Plan 04 only adds the controller — no route changes needed at that point"
  - "EnsureUserIsActive placed after AttemptToAuthenticate and before PrepareAuthenticatedSession so deactivation check runs on a resolved user before session is written"
  - "Middleware priority list added to guarantee 403 (not 404) — PermissionMiddleware must run before SubstituteBindings to prevent route model binding failures on unauthorized access"
  - "AccountStatus uses PHP 8.1 backed enum cast (not raw string) for type safety in the deactivation check"

patterns-established:
  - "Gate on permissions, not role names: permission:manage-users not role:Admin"
  - "Fortify pipeline customization via FortifyServiceProvider::configureActions private method"
  - "PHP 8.1 backed enum + Eloquent cast for typed status columns"

requirements-completed: [ACCESS-01, ACCESS-02, ACCESS-04]

duration: 13min
completed: 2026-05-16
---

# Phase 01 Plan 02: RBAC Backbone Summary

**spatie RBAC wired into User model, Fortify login pipeline, and route layer — deactivated users blocked at login, Moderators gated to /admin/ingredients, plain Users get 403**

## Performance

- **Duration:** 13 min
- **Started:** 2026-05-16T05:49:02Z
- **Completed:** 2026-05-16T06:02:26Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- User model gains `HasRoles`, `SoftDeletes`, and typed `AccountStatus` enum cast with migration
- spatie middleware aliases registered with correct priority ensuring 403 on unauthorized admin access
- Deactivated users blocked before session establishment via `EnsureUserIsActive` Fortify pipeline step
- Moderators (and Admins) can reach `/admin/ingredients`; plain Users receive 403
- Default User role assigned on registration via `CreateNewUser::assignRole('User')`

## Task Commits

1. **Task 1: AccountStatus enum, migration, User model updates, deactivated factory state** - `dfaf718` (feat)
2. **Task 2: Middleware aliases, admin route group, role-on-registration** - `83a8f1b` (feat)
3. **Task 3: EnsureUserIsActive pipeline, IngredientReviewController, ingredients page** - `0209ef2` (feat)

## Files Created/Modified

- `app/Enums/AccountStatus.php` - PHP 8.1 backed enum: Active/Deactivated
- `database/migrations/2026_05_16_024949_add_account_status_to_users_table.php` - Adds account_status + soft_deletes to users table
- `app/Models/User.php` - Added HasRoles, SoftDeletes, AccountStatus cast, account_status fillable
- `database/factories/UserFactory.php` - Added deactivated() state method
- `bootstrap/app.php` - Registered spatie middleware aliases and priority list
- `app/Actions/Fortify/CreateNewUser.php` - Added $user->assignRole('User') on registration
- `app/Providers/FortifyServiceProvider.php` - Added Fortify::authenticateThrough with EnsureUserIsActive
- `app/Actions/Fortify/EnsureUserIsActive.php` - Pipeline step: logs out and redirects deactivated users
- `app/Http/Controllers/Admin/IngredientReviewController.php` - Placeholder returning Inertia admin/ingredients page
- `resources/js/pages/admin/ingredients.tsx` - Minimal placeholder page for Phase 7 review queue
- `routes/web.php` - Added /admin route group (manage-users) and /admin/ingredients route (review-ingredients)

## Decisions Made

- **Route pre-declaration pattern:** Admin user management routes (`admin.users.*`) declared here so Plan 04 only adds the controller, not routes. Routes are expected to 500 until Plan 04 lands.
- **EnsureUserIsActive pipeline position:** After AttemptToAuthenticate (user resolved), before PrepareAuthenticatedSession (session not yet written) — ensures deactivated users never get a session.
- **Middleware priority list:** Added `PermissionMiddleware` before `SubstituteBindings` — prevents route model binding failures from manifesting as 404 instead of 403.
- **PHP 8.1 backed enum:** Used for AccountStatus with Eloquent cast for type safety in pipeline comparison (`=== AccountStatus::Deactivated` not string equality).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Built Vite manifest to include admin/ingredients.tsx**
- **Found during:** Task 3 verification
- **Issue:** The `AccessControlTest::moderator_can_reach_the_ingredient_review_queue_placeholder` test failed with ViteException because `admin/ingredients.tsx` was not in the built Vite manifest
- **Fix:** Ran `npm run build` to compile all assets including the new admin/ingredients page
- **Files modified:** public/build/* (gitignored, not committed)
- **Verification:** AccessControl test passed after build
- **Committed in:** N/A (Vite output is gitignored)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Build step was required to make tests pass — no scope creep.

## Issues Encountered

- Accidentally created files from tinker output (`getTable()`, `assignRole('User')`, etc.) in project root — deleted before commit. These are side effects of PowerShell's bash emulation capturing command output as filenames.
- The `php artisan route:list --path=admin` command errors with `ReflectionException` because `UserController` doesn't exist yet (expected per plan — routes are pre-declared, controller arrives in Plan 04).
- Pre-existing test failures: `AdminSelfProtectionTest` and `AdminUserManagementTest` fail because `UserController` doesn't exist — these are wave 0 stub tests intentionally written ahead of their implementation in Plan 04.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- RBAC backbone complete: role assignment, middleware, permission-gated routes all functional
- Plan 03 (Lookup Tables) and Plan 04 (Admin UserController) can proceed independently
- Plan 04 only needs to create `Admin\UserController` with the index/assignRole/toggleStatus/destroy methods — routes are already in place
- Plan 07 (Moderation) has its route hook (`admin.ingredients.index`) ready

---
*Phase: 01-foundation*
*Completed: 2026-05-16*
