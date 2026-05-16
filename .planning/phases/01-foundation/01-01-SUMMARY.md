---
phase: 01-foundation
plan: 01
subsystem: testing
tags: [spatie-laravel-permission, laravel-react-i18n, rbac, localization, pest, seeder]

# Dependency graph
requires: []
provides:
  - spatie/laravel-permission v7.4 installed with config published and migration run
  - laravel-react-i18n@2.0.5 installed with Vite plugin wired
  - RefreshDatabase enabled for the Feature test suite
  - RolesAndPermissionsSeeder with 3 roles (User/Moderator/Admin) and 4 named permissions
  - HasRoles trait added to User model
  - lang/en/app.php and lang/el/app.php with full parallel key structure
  - 9 Phase 1 test files (initially red) covering all requirements
affects:
  - 01-02 (RBAC middleware and admin controller — seeds this seeder)
  - 01-03 (Lookup table seeders — LookupTableSeedTest references AllergenSeeder and UnitSeeder)
  - 01-04 (Localization middleware — SetLocaleTest and LocaleUpdateTest assert the expected behavior)
  - 01-05 (Design tokens — StyleguideTest asserts /dev/styleguide renders)
  - 01-06 (i18n frontend — lang files feed the laravel-react-i18n Vite plugin)

# Tech tracking
tech-stack:
  added:
    - spatie/laravel-permission:^7.4 (PHP/Composer)
    - laravel-react-i18n@2.0.5 (npm)
  patterns:
    - RolesAndPermissionsSeeder hierarchical superset — Admin perms include Moderator which include User
    - Pest Feature suite uses RefreshDatabase per test via tests/Pest.php
    - beforeEach seeding pattern for RBAC tests: $this->seed(RolesAndPermissionsSeeder::class)
    - Lang files use :placeholder syntax for dynamic substitution (laravel-react-i18n compatible)

key-files:
  created:
    - config/permission.php (spatie permission config, published)
    - database/migrations/2026_05_16_023356_create_permission_tables.php (spatie tables)
    - database/seeders/RolesAndPermissionsSeeder.php (3 roles, 4 permissions, dev admin)
    - lang/en/app.php (English UI strings — nav, admin, auth, language sections)
    - lang/el/app.php (Greek UI strings — identical key structure)
    - tests/Feature/Access/RoleAssignmentTest.php
    - tests/Feature/Access/AccessControlTest.php
    - tests/Feature/Access/AdminUserManagementTest.php
    - tests/Feature/Access/DeactivatedUserTest.php
    - tests/Feature/Access/AdminSelfProtectionTest.php
    - tests/Feature/Localization/SetLocaleTest.php
    - tests/Feature/Localization/LocaleUpdateTest.php
    - tests/Feature/Lookup/LookupTableSeedTest.php
    - tests/Feature/Ui/StyleguideTest.php
    - tests/Feature/Ui/PageRenderTest.php
  modified:
    - composer.json (added spatie/laravel-permission)
    - package.json (added laravel-react-i18n)
    - tests/Pest.php (uncommented RefreshDatabase for Feature suite)
    - vite.config.ts (added i18n import and plugin call)
    - .gitignore (added /lang/php_*.json)
    - app/Models/User.php (added HasRoles trait — auto-fix Rule 2)
    - database/seeders/DatabaseSeeder.php (added RolesAndPermissionsSeeder call)

key-decisions:
  - "HasRoles trait added to User model immediately (required for seeder to function)"
  - "Permission hierarchy: Admin superset of Moderator superset of User via syncPermissions"
  - "Default dev admin seeded as admin@twosides.test with password 'password'"
  - "i18n plugin added after wayfinder in vite.config.ts plugins array, preserving existing plugin order"
  - "Wave 0 test files write real assertions (not ->skip()) so later waves have concrete red-to-green targets"

patterns-established:
  - "RBAC seeding: call forgetCachedPermissions first, use firstOrCreate for idempotency"
  - "Test beforeEach: $this->seed(RolesAndPermissionsSeeder::class) for all RBAC-dependent tests"
  - "Lang file keys: snake_case nested under section (nav, admin, auth, language)"

requirements-completed: [ACCESS-01, ACCESS-02, ACCESS-03, ACCESS-04, I18N-01, I18N-02, UI-01, UI-03]

# Metrics
duration: 11min
completed: 2026-05-16
---

# Phase 1 Plan 01: Foundation Wave 0 — Packages, Seeder, Lang Files, and Test Scaffolding Summary

**spatie/laravel-permission v7 and laravel-react-i18n installed, RolesAndPermissionsSeeder seeding 3 roles + 4 permissions, bilingual lang files live, and 9 red test files covering every Phase 1 requirement**

## Performance

- **Duration:** 11 min
- **Started:** 2026-05-16T02:32:20Z
- **Completed:** 2026-05-16T02:43:30Z
- **Tasks:** 3 of 3
- **Files modified:** 17

## Accomplishments

- spatie/laravel-permission v7.4.0 and laravel-react-i18n@2.0.5 installed; config published; permission tables migrated
- RolesAndPermissionsSeeder creates 3 roles (User/Moderator/Admin) with 4 named permissions in a superset hierarchy plus a default dev admin; DatabaseSeeder calls it first
- lang/en/app.php and lang/el/app.php created with 30+ parallel keys (nav, admin, auth, language sections) sourced from the UI-SPEC copywriting contract
- 9 Pest feature test files created and verified to execute (intentionally red — sampling targets for later waves)

## Task Commits

Each task was committed atomically:

1. **Task 1: Install packages, publish config, enable RefreshDatabase** - `6a68284` (feat)
2. **Task 2: RolesAndPermissionsSeeder and bilingual lang files** - `2211adb` (feat)
3. **Task 3: Scaffold 9 failing test files** - `7a781da` (test)

**Plan metadata:** (pending — created in final commit)

## Files Created/Modified

- `config/permission.php` - Published spatie permission config
- `database/migrations/2026_05_16_023356_create_permission_tables.php` - Spatie RBAC tables
- `database/seeders/RolesAndPermissionsSeeder.php` - 3 roles, 4 permissions, dev admin seed
- `database/seeders/DatabaseSeeder.php` - Now calls RolesAndPermissionsSeeder first
- `app/Models/User.php` - Added HasRoles trait (auto-fix)
- `lang/en/app.php` - English UI strings with full copywriting contract
- `lang/el/app.php` - Greek translations with identical key structure
- `tests/Pest.php` - RefreshDatabase enabled for Feature suite
- `vite.config.ts` - Added laravel-react-i18n/vite import and i18n() plugin call
- `.gitignore` - Added /lang/php_*.json
- `tests/Feature/Access/` - 5 test files (RoleAssignment, AccessControl, AdminUserManagement, DeactivatedUser, AdminSelfProtection)
- `tests/Feature/Localization/` - 2 test files (SetLocale, LocaleUpdate)
- `tests/Feature/Lookup/LookupTableSeedTest.php` - Allergen and Unit seeder coverage
- `tests/Feature/Ui/` - 2 test files (Styleguide, PageRender)

## Decisions Made

- HasRoles trait added to User model in Task 2 (required for seeder — auto-fixed as Rule 2 missing critical)
- Wave 0 tests write real assertions rather than using `->skip()`, ensuring later waves have concrete failing targets to turn green
- i18n Vite plugin inserted after wayfinder to preserve existing plugin ordering
- Default admin seeded as `admin@twosides.test` / `password` for development use only

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added HasRoles trait to User model**
- **Found during:** Task 2 (RolesAndPermissionsSeeder implementation)
- **Issue:** `User::assignRole()` method unavailable — `HasRoles` trait from spatie/laravel-permission not yet added to User model
- **Fix:** Added `use Spatie\Permission\Traits\HasRoles;` import and `HasRoles` to the `use` trait list in `app/Models/User.php`
- **Files modified:** `app/Models/User.php`
- **Verification:** Seeder ran successfully; `Role::count()` returned 3; `RoleAssignmentTest::user model uses HasRoles trait` passes
- **Committed in:** `2211adb` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical — Rule 2)
**Impact on plan:** Essential for basic seeder functionality. No scope creep — the trait is an expected part of spatie/laravel-permission installation.

## Issues Encountered

- PowerShell pipe character in `php artisan test --filter` caused shell parsing errors; resolved by running individual filter commands per test file group

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Package foundation complete: spatie/laravel-permission and laravel-react-i18n are installed and wired
- RefreshDatabase enabled: all RBAC tests now get a clean DB per test
- Seeder ready: Plans 02-06 can call `$this->seed(RolesAndPermissionsSeeder::class)` in tests
- Lang files ready: laravel-react-i18n Vite plugin will compile them; Plan 06 (i18n frontend) can build on this
- 9 red test files established as sampling targets for Plans 02-06

---
*Phase: 01-foundation*
*Completed: 2026-05-16*
