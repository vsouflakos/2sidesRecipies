---
phase: 1
slug: foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-16
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 + Laravel plugin (phpunit v12 under it) |
| **Config file** | `tests/Pest.php` — `RefreshDatabase` currently commented out for the Feature suite; Wave 0 must enable it |
| **Quick run command** | `php artisan test --compact --filter=<FeatureUnderTest>` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds (full suite, current size) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<FeatureUnderTest>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

> Task IDs are assigned by the planner. The rows below map each phase requirement
> to the automated check that proves it. The planner attaches each row to the task
> that delivers the behavior and fills the Task ID + Plan + Wave columns.

| Task ID | Plan | Wave | Requirement | Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|----------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | ACCESS-01 | New registration is assigned the User role | feature | `php artisan test --compact --filter=RoleAssignment` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-01 | User model has `HasRoles` trait | feature | `php artisan test --compact --filter=RoleAssignment` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-02 | Moderator can reach `/admin/ingredients` review-queue placeholder | feature | `php artisan test --compact --filter=AccessControl` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-02 | Plain User cannot reach `/admin/ingredients` (403) | feature | `php artisan test --compact --filter=AccessControl` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-03 | Admin can list users at `GET /admin/users` | feature | `php artisan test --compact --filter=AdminUserManagement` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-03 | Admin can assign a role via `PUT /admin/users/{user}/role` | feature | `php artisan test --compact --filter=AdminUserManagement` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-03 | Admin can deactivate a user | feature | `php artisan test --compact --filter=AdminUserManagement` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-03 | Admin can soft-delete a user | feature | `php artisan test --compact --filter=AdminUserManagement` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-04 | Non-admin gets 403 on every `/admin/*` route | feature | `php artisan test --compact --filter=AccessControl` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-04 | Deactivated user cannot log in | feature | `php artisan test --compact --filter=DeactivatedUser` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-04 | Admin cannot demote / deactivate / delete themselves | feature | `php artisan test --compact --filter=AdminSelfProtection` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | ACCESS-04 | System refuses to remove / disable the last remaining Admin | feature | `php artisan test --compact --filter=AdminSelfProtection` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | I18N-01 | Lookup tables (unit + allergen) seeded — allergen has 14 EU rows | feature | `php artisan test --compact --filter=LookupTableSeed` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | I18N-01 | Locale switcher persists choice to the user record | feature | `php artisan test --compact --filter=LocaleUpdate` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | I18N-01 | `SetLocale` middleware applies the user's locale to `App::getLocale()` | feature | `php artisan test --compact --filter=SetLocale` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | I18N-02 | `locale` shared prop present in `HandleInertiaRequests` payload | feature | `php artisan test --compact --filter=SetLocale` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | UI-01 | Styleguide page renders at `/dev/styleguide` in the local env | feature | `php artisan test --compact --filter=Styleguide` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | UI-02 | Warm-minimal token values applied (`--background`, `--accent`) | manual | Visual review on `/dev/styleguide` in light + dark | n/a | ⬜ pending |
| TBD | TBD | TBD | UI-03 | Dashboard / admin / settings pages return 200 for authenticated users | feature | `php artisan test --compact --filter=PageRender` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Wave 0 must land before any feature wave so every later task has a failing-then-passing test to sample against.

- [ ] `tests/Pest.php` — uncomment `->use(RefreshDatabase::class)` for the `Feature` suite (RBAC tests need a clean DB per test)
- [ ] `config/permission.php` — published via `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` — roles + permissions; required by every RBAC test
- [ ] `tests/Feature/Access/RoleAssignmentTest.php` — stubs for ACCESS-01, ACCESS-03
- [ ] `tests/Feature/Access/AccessControlTest.php` — stubs for ACCESS-02, ACCESS-04
- [ ] `tests/Feature/Access/AdminUserManagementTest.php` — stubs for ACCESS-03 operations
- [ ] `tests/Feature/Access/DeactivatedUserTest.php` — stubs for ACCESS-04 login gate
- [ ] `tests/Feature/Access/AdminSelfProtectionTest.php` — stubs for ACCESS-04 self-protection + last-admin guards
- [ ] `tests/Feature/Localization/SetLocaleTest.php` — stubs for I18N-01, I18N-02
- [ ] `tests/Feature/Localization/LocaleUpdateTest.php` — stubs for I18N-01 switcher persistence
- [ ] `tests/Feature/Lookup/LookupTableSeedTest.php` — stubs for unit + allergen seed coverage
- [ ] `tests/Feature/Ui/StyleguideTest.php` — stub for UI-01 styleguide route
- [ ] `tests/Feature/Ui/PageRenderTest.php` — stub for UI-03 page render check
- [ ] `lang/en/app.php` + `lang/el/app.php` — minimum translation key sets so I18N tests and the Vite i18n plugin have files to compile

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Warm-minimal aesthetic — token hue/lightness looks paper-warm, not clinical or salmon | UI-02 | Color correctness is perceptual; asserting exact OKLCH values in a test proves the string, not the look | Open `/dev/styleguide` in the local env, toggle light ↔ dark, confirm background reads as warm off-white and the accent is a soft taupe (not a saturated color) |
| Responsive layout at desktop / tablet / mobile breakpoints | UI-03 | Layout reflow is visual; automated viewport tests confirm render but not usability | Resize the browser (or use devtools device toolbar) across ~375px / ~768px / ~1280px on dashboard, `/admin/users`, settings — confirm no overflow, nav collapses, tables stay usable |
| Language switch updates all visible strings live | I18N-01 | End-to-end string swap across a rendered page is verified by eye | Switch EN ↔ EL via the switcher, confirm UI strings update without a hard reload and the choice survives a page refresh |

---

## Validation Sign-Off

- [ ] All tasks have an `<automated>` verify command or a Wave 0 dependency
- [ ] Sampling continuity: no 3 consecutive tasks without an automated verify
- [ ] Wave 0 covers all MISSING (`❌ W0`) references in the map above
- [ ] No watch-mode flags in any command
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
