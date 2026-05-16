---
phase: 01-foundation
plan: 04
subsystem: auth
tags: [admin, user-management, rbac, inertia, shadcn, table, pagination, soft-deletes, spatie-permission]

requires:
  - phase: 01-02
    provides: "User model with HasRoles + SoftDeletes + AccountStatus cast; admin route group pre-declared with manage-users permission gate; admin.users.* routes registered"

provides:
  - "AdminUserGuards trait with isLastAdmin() and isActingOnSelf() shared guard logic"
  - "AssignRoleRequest, DeactivateUserRequest, DeleteUserRequest FormRequests with withValidator() guard callbacks"
  - "Admin\UserController with index/assignRole/toggleStatus/destroy, DB::transaction + lockForUpdate on mutations"
  - "shadcn table and pagination UI primitives at resources/js/components/ui/"
  - "ConfirmActionDialog reusable confirmation dialog component"
  - "UserActionsMenu per-row dropdown with guard-disabled items + Tooltip"
  - "UserTable data-table component with Avatar+Name+Email, role Badge, Joined date, responsive column hiding"
  - "users.tsx page: searchable + debounced search, result count, pagination, flash toast wiring"
  - "Admin-only 'Users' sidebar nav entry gated on manage-users permission"

affects: [01-06, 07-moderation]

tech-stack:
  added: []
  patterns:
    - "AdminUserGuards trait centralises self-protection + last-admin guard logic reused across multiple FormRequests"
    - "DB::transaction + lockForUpdate on all mutation endpoints (assignRole, toggleStatus, destroy) to prevent race conditions"
    - "redirect back() (not JSON) from Inertia mutation endpoints so Inertia reloads shared props and re-renders the table"
    - "Guarded DropdownMenuItems rendered disabled + wrapped in Tooltip for WCAG-compliant guard messaging"
    - "Debounced router.get with preserveState:true + replace:true for search without polluting browser history"

key-files:
  created:
    - app/Concerns/AdminUserGuards.php
    - app/Http/Requests/Admin/AssignRoleRequest.php
    - app/Http/Requests/Admin/DeactivateUserRequest.php
    - app/Http/Requests/Admin/DeleteUserRequest.php
    - app/Http/Controllers/Admin/UserController.php
    - resources/js/components/ui/table.tsx
    - resources/js/components/ui/pagination.tsx
    - resources/js/components/admin/confirm-action-dialog.tsx
    - resources/js/components/admin/user-actions-menu.tsx
    - resources/js/components/admin/user-table.tsx
    - resources/js/pages/admin/users.tsx
  modified:
    - resources/js/components/app-sidebar.tsx

key-decisions:
  - "redirect back() (not JSON) from assignRole/toggleStatus/destroy — Inertia mutation endpoints must return a redirect so Inertia performs a full prop-refresh cycle and the table re-renders with updated data"
  - "AdminUserGuards trait shared across all three FormRequests rather than duplicating guard logic — single source of truth for isLastAdmin and isActingOnSelf"
  - "DB::transaction + lockForUpdate on every mutation to prevent last-admin race conditions identified in RESEARCH Pitfall 5"
  - "auth.permissions guarded defensively with (auth.permissions ?? []).includes() so the nav entry does not crash before Plan 06 lands the shared prop"

patterns-established:
  - "Redirect-not-JSON for Inertia mutation endpoints: controller mutations return back() with flash, never JsonResponse"
  - "AdminUserGuards trait pattern: shared guard logic in app/Concerns, consumed by FormRequest withValidator callbacks"
  - "Disabled+Tooltip guard UX: DropdownMenuItem disabled prop + Tooltip content for WCAG-accessible guard messaging"

requirements-completed: [ACCESS-03, ACCESS-04]

duration: ~45min
completed: 2026-05-16
---

# Phase 01 Plan 04: Admin User Management Summary

**Searchable, paginated /admin/users table with inline role change, deactivate + delete confirmation dialogs, and self/last-admin guards enforced on backend and surfaced as disabled dropdown items with tooltips**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-05-16
- **Completed:** 2026-05-16
- **Tasks:** 4 (3 auto + 1 human-verify checkpoint)
- **Files modified:** 12

## Accomplishments

- Backend user management surface: `UserController` with all four CRUD methods wrapped in DB transactions, `AdminUserGuards` trait, and three `FormRequests` with withValidator guard callbacks
- shadcn Table + Pagination primitives installed; `UserTable` component with responsive column hiding (Joined hides below 1024px, Role hides below 768px)
- `ConfirmActionDialog` and `UserActionsMenu` components with guard-disabled items + Tooltip for both self-protection and last-admin guard messaging
- `/admin/users` page with debounced search, result count, flash toast wiring, and pagination
- Admin-only "Users" sidebar nav entry gated on `manage-users` permission

## Task Commits

1. **Task 1: Backend — AdminUserGuards trait, FormRequests, UserController** - `f11ebc7` (feat)
2. **Task 2: Table/pagination primitives, ConfirmActionDialog, UserActionsMenu** - `754b34d` (feat)
3. **Task 3: UserTable component, users.tsx page, Admin nav entry** - `3444bd6` (feat)
4. **Human-verify checkpoint fix: redirect return from mutation endpoints** - `c4b92df` (fix)

## Files Created/Modified

- `app/Concerns/AdminUserGuards.php` - Trait with isLastAdmin() and isActingOnSelf() shared guard methods
- `app/Http/Requests/Admin/AssignRoleRequest.php` - Validates role; withValidator blocks self-demotion and last-admin demotion
- `app/Http/Requests/Admin/DeactivateUserRequest.php` - withValidator blocks self-deactivation and last-admin deactivation
- `app/Http/Requests/Admin/DeleteUserRequest.php` - withValidator blocks self-deletion and last-admin deletion
- `app/Http/Controllers/Admin/UserController.php` - index/assignRole/toggleStatus/destroy with DB transactions
- `resources/js/components/ui/table.tsx` - shadcn table primitive (installed, not modified)
- `resources/js/components/ui/pagination.tsx` - shadcn pagination primitive (installed, not modified)
- `resources/js/components/admin/confirm-action-dialog.tsx` - Reusable confirmation dialog (deactivate + delete)
- `resources/js/components/admin/user-actions-menu.tsx` - Per-row ellipsis dropdown with guard-disabled items + Tooltip
- `resources/js/components/admin/user-table.tsx` - Data table: Avatar+Name+Email, role Badge, Joined, actions, responsive columns, empty + skeleton loading states
- `resources/js/pages/admin/users.tsx` - User management page: debounced search, result count, UserTable, pagination, flash toasts
- `resources/js/components/app-sidebar.tsx` - Added Users nav entry guarded by manage-users permission

## Decisions Made

- **Redirect-not-JSON pattern:** At the human-verify checkpoint, the table was not refreshing after role/status/delete mutations. Root cause: the controller was returning JSON responses. Fixed to `return back()->with('success', ...)` so Inertia performs a full prop-refresh cycle and the table re-renders. This is the canonical Inertia mutation pattern.
- **AdminUserGuards trait:** Centralises guard logic in a single location rather than duplicating across three FormRequests. Each FormRequest uses it via `use AdminUserGuards` without coupling to a base class.
- **DB::transaction + lockForUpdate:** All mutations wrap the target user in a transaction with `lockForUpdate()` to prevent race conditions when two admins simultaneously try to demote/delete the last admin.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed mutation endpoints returning JSON instead of redirects**
- **Found during:** Task 4 (human-verify checkpoint — user reported table was not refreshing after role/status/delete actions)
- **Issue:** `assignRole`, `toggleStatus`, and `destroy` controller methods were returning responses that Inertia treated as non-navigating (JSON), so it did not re-fetch shared props and the table stayed stale
- **Fix:** Changed all three mutation methods to `return back()->with('success', ...)` — the canonical Inertia redirect-back pattern that triggers a prop-refresh cycle
- **Files modified:** `app/Http/Controllers/Admin/UserController.php`
- **Verification:** After fix, role badge, account status, and row count all updated immediately after each action without manual page refresh
- **Committed in:** `c4b92df`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Fix was essential for correct Inertia behaviour. No scope creep — the controller logic was otherwise correct.

## Issues Encountered

- The `php artisan route:list --path=admin` command errors with `ReflectionException` before Plan 04 lands (expected — routes are pre-declared in Plan 02, controller arrives here). Not a blocker.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Admin user management surface is complete and human-verified
- Plan 06 can now assume `auth.permissions` is in the shared Inertia props (added in Plan 06's HandleInertiaRequests extension)
- The `manage-users` permission guard on the sidebar nav entry is already in place and will activate automatically once Plan 06 shares `auth.permissions`

---
*Phase: 01-foundation*
*Completed: 2026-05-16*
