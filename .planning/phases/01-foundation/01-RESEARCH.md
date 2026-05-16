# Phase 1: Foundation - Research

**Researched:** 2026-05-16
**Domain:** Laravel RBAC, Localization, Design Tokens, Lookup Tables
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Role system & access control**
- Storage: Use spatie/laravel-permission (approved new dependency)
- Permission model: Define named permissions (e.g. `review-ingredients`, `manage-users`); gate on permissions, not raw role names
- Hierarchy: Admin ⊇ Moderator ⊇ User — realized via permission assignment, not built-in inheritance
- Default role: User role assigned on registration; no auto-promotion
- First Admin: Created via database seeder only
- Enforcement: 403 on unauthorized access

**Admin user management**
- Surface: Dedicated `/admin` section visible only to Admins
- User list: table with search + pagination from existing shadcn/ui primitives
- Operations (all three in Phase 1): assign role, deactivate/suspend, delete (SoftDeletes)
- Safeguards: Admin cannot demote/deactivate/delete themselves; system refuses to remove the last remaining Admin

**Design system**
- Direction: Warm-minimal — warm off-white background, very soft taupe accent
- Scope: Full CSS-variable token set (color, spacing, radius, typography) for light + dark
- Typography: Single clean sans-serif (Instrument Sans — already installed via Bunny Fonts in vite.config.ts)
- Verification artifact: Internal styleguide/component showcase page at a dev-only route

**Lookup tables**
- Unit lookup table and allergen lookup table, seeded and available app-wide
- Allergen lookup table follows EU Regulation 1169/2011 — the 14 mandatory allergens (hard compliance constraint)

### Claude's Discretion

- Localization approach (I18N-01, I18N-02): Standard Laravel + Inertia patterns. Must deliver: translatable UI, user-facing Greek/English switcher, persisted language preference, infrastructure for ingredient-name translations (data arrives Phase 2). Switcher placement and translation-file strategy are Claude's call.
- Exact role/permission enforcement mechanism (middleware vs Gate/policies vs both) — within "enforced consistently, returns 403"
- The 403 unauthorized experience (dedicated error page vs default)
- Exact token values (specific hex for warm off-white and taupe accent, spacing/radius scale numbers) — within the warm-minimal direction
- Styleguide page layout and component set

### Deferred Ideas (OUT OF SCOPE)

- Full account-management surface (bulk actions, role-filtered views, user detail pages, audit log)
- Granular per-feature permissions beyond Phase 1 needs — only permissions required by Phase 1 (and the Phase 7 hook `review-ingredients`) are seeded now
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ACCESS-01 | User account is assigned one of three roles — User, Moderator, or Admin | spatie/laravel-permission v7 installation; `HasRoles` trait; `CreateNewUser` hook for default role assignment |
| ACCESS-02 | Moderator can access the ingredient submission review queue | `review-ingredients` permission seeded; `permission` middleware on `/admin/ingredients` route group |
| ACCESS-03 | Admin can manage user accounts and assign roles | `manage-users` permission; `/admin/users` controller group; 3-operation pattern researched |
| ACCESS-04 | Each role's permitted actions are enforced consistently across the app | `permission` middleware in `bootstrap/app.php`; `HandleInertiaRequests` shares permissions to frontend for nav gating |
| I18N-01 | UI is translatable and user can switch language (Greek and English) | `laravel-react-i18n` v2.0.5 pattern; `SetLocale` middleware; `locale` column on users table; route to persist preference |
| I18N-02 | Ingredient names display in the user's selected language | `locale` shared via `HandleInertiaRequests`; infrastructure (column + shared prop) established — ingredient data arrives Phase 2 |
| UI-01 | Interface is built with shadcn/ui components on Tailwind v4 | Existing shadcn/ui primitives confirmed; Tailwind v4 `@theme` / OKLCH token approach documented |
| UI-02 | Interface follows a warm-minimal aesthetic consistently across light and dark themes | Full CSS-variable token set replacement in `resources/css/app.css`; OKLCH warm palette values researched |
| UI-03 | Interface is responsive and usable on desktop, tablet, and mobile browsers | Tailwind responsive utilities; shadcn/ui Radix primitives are responsive by default |
</phase_requirements>

---

## Summary

Phase 1 installs spatie/laravel-permission v7 (the current release that requires PHP 8.3+ and supports Laravel 12/13), wires it into the existing Laravel 13 + Fortify bootstrap, and seeds a three-role + named-permission hierarchy. The `CreateNewUser` Fortify action gets a one-line role assignment. The `bootstrap/app.php` gains three middleware aliases. A `HandleInertiaRequests` extension exposes `auth.permissions` and `locale` to every page so the React layer can gate nav and render the language switcher without extra round-trips.

Localization uses `laravel-react-i18n` v2.0.5 — a Vite-plugin-backed package that compiles Laravel's PHP lang files to JSON at build time and exposes a `useLaravelReactI18n` hook. Persistence is a `locale` varchar column on the users table (nullable, defaults to `en`), read by a `SetLocale` middleware that calls `App::setLocale()` on every authenticated request. An `UpdateLocaleController` handles the PUT request from the language switcher.

The design system work replaces the current neutral OKLCH values in `resources/css/app.css` with a warm-minimal palette (warm off-white background `oklch(0.98 0.008 60)`, soft taupe accent `oklch(0.55 0.04 60)`) for both light and dark themes. The `@theme` block structure that maps CSS variables to Tailwind utility names is already in place — only the custom property values change. A dev-only styleguide route at `/dev/styleguide` serves as the verification artifact.

**Primary recommendation:** Install spatie/laravel-permission v7, `laravel-react-i18n`, and implement the four feature domains in this order: (1) RBAC — role seeding + middleware + CreateNewUser + admin section, (2) Lookup tables — unit + allergen seeders, (3) Localization — lang files + SetLocale middleware + switcher, (4) Design tokens — CSS variable palette replacement + styleguide.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| spatie/laravel-permission | ^7.4 (current: 7.4.1) | Role + permission storage, `HasRoles` trait, middleware | De facto Laravel RBAC standard; v7 requires PHP 8.3+, supports Laravel 12/13 explicitly |
| laravel-react-i18n | ^2.0.5 | Bridges Laravel PHP lang files to React via Vite; `useLaravelReactI18n` hook | Standard approach for Inertia + React localization; Vite plugin avoids runtime fetches |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Tailwind CSS v4 | already installed (`^4.0.0`) | CSS utility framework + `@theme` design tokens | All styling — tokens live in `resources/css/app.css` |
| shadcn/ui primitives | already installed | Admin table, select, badge, dialog, pagination | Admin user list; compose from existing `/components/ui/` |
| Instrument Sans (Bunny Fonts) | already configured in vite.config.ts | Typography | Already the starter-kit font — no new dependency |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| laravel-react-i18n | Manual JSON props in HandleInertiaRequests | Manual approach works but requires hand-maintaining translations in PHP and JSON; laravel-react-i18n keeps a single source of truth in `lang/` |
| laravel-react-i18n | erag/laravel-lang-sync-inertia | Similar concept; laravel-react-i18n has more active maintenance and hooks API |
| spatie/laravel-permission v7 | Custom roles table | Custom solution must replicate cache management, model binding, and guard support; spatie is battle-tested |

### Installation

```bash
# PHP package
composer require spatie/laravel-permission

# Publish config + migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan optimize:clear
php artisan migrate

# NPM package
npm install laravel-react-i18n
```

**Version verification (confirmed 2026-05-16):**
- `spatie/laravel-permission`: 7.4.1 (requires `php: ^8.3`, supports `laravel/framework: ^12.0|^13.0`)
- `laravel-react-i18n`: 2.0.5

---

## Architecture Patterns

### Recommended Project Structure (new additions only)

```
app/
├── Actions/Fortify/
│   └── CreateNewUser.php           # gains $user->assignRole('User')
├── Actions/Admin/
│   ├── AssignUserRole.php          # invokable: validates, applies role, checks last-admin guard
│   ├── DeactivateUser.php          # invokable: sets account_status = 'deactivated'
│   └── DeleteUser.php              # invokable: soft-deletes, checks last-admin guard
├── Concerns/
│   └── AdminUserGuards.php         # trait: isLastAdmin(), isSelf() — shared by Admin actions
├── Http/Controllers/Admin/
│   └── UserController.php          # index, update (role/status), destroy
├── Http/Middleware/
│   ├── SetLocale.php               # reads user.locale → App::setLocale()
│   └── HandleInertiaRequests.php   # extended: shares locale + permissions
├── Http/Requests/Admin/
│   ├── AssignRoleRequest.php
│   ├── DeactivateUserRequest.php
│   └── DeleteUserRequest.php
├── Models/
│   └── User.php                    # gains HasRoles, SoftDeletes, account_status cast

database/
├── migrations/
│   ├── xxxx_add_account_status_locale_to_users_table.php
│   └── (spatie migrations published via vendor:publish)
├── seeders/
│   ├── RolesAndPermissionsSeeder.php
│   ├── UnitSeeder.php
│   └── AllergenSeeder.php

resources/
├── css/app.css                     # CSS variable token values replaced for warm-minimal
├── js/
│   ├── pages/admin/
│   │   └── users.tsx               # Admin user list page
│   ├── pages/dev/
│   │   └── styleguide.tsx          # Internal component showcase
│   ├── components/
│   │   └── language-switcher.tsx   # Greek/English toggle; calls PUT /locale
│   └── types/
│       └── global.d.ts             # Extended with locale + permissions in sharedPageProps

lang/
├── en/                             # English translation files
│   └── app.php
└── el/                             # Greek translation files (el = ISO 639-1 for Greek)
    └── app.php
```

### Pattern 1: spatie/laravel-permission — Middleware Registration (Laravel 13)

**What:** Register the three spatie middleware aliases in the fluent `bootstrap/app.php`.
**When to use:** Always — replaces the old `Kernel.php` approach removed in Laravel 11+.

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

    $middleware->web(append: [
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);

    $middleware->alias([
        'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
})
```

### Pattern 2: Route Gating via Permission Middleware

**What:** Gate admin routes on the `manage-users` named permission.
**When to use:** All `/admin` routes — middleware, not Gate::define in controllers.

```php
// routes/web.php
Route::middleware(['auth', 'verified', 'permission:manage-users'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::put('users/{user}/role', [Admin\UserController::class, 'assignRole'])->name('users.role');
        Route::put('users/{user}/status', [Admin\UserController::class, 'toggleStatus'])->name('users.status');
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
    });
```

### Pattern 3: Default Role on Registration

**What:** Assign `User` role in `CreateNewUser::create()` after `User::create()`.

```php
// app/Actions/Fortify/CreateNewUser.php
public function create(array $input): User
{
    // ... existing validation ...

    $user = User::create([
        'name'     => $input['name'],
        'email'    => $input['email'],
        'password' => $input['password'],
    ]);

    $user->assignRole('User');

    return $user;
}
```

### Pattern 4: RolesAndPermissionsSeeder — Hierarchical Superset

**What:** Create permissions first, then roles, then assign permissions to realize the superset hierarchy.
**When to use:** `DatabaseSeeder` calls this seeder; also called standalone for CI refresh.

```php
// database/seeders/RolesAndPermissionsSeeder.php
public function run(): void
{
    // 1. Reset cache BEFORE seeding to prevent conflicts
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // 2. Create all permissions (Phase 1 scope + Phase 7 hook)
    $userPermissions = [
        'create-recipes',
        'manage-own-ingredients',
    ];

    $moderatorPermissions = array_merge($userPermissions, [
        'review-ingredients',
    ]);

    $adminPermissions = array_merge($moderatorPermissions, [
        'manage-users',
    ]);

    foreach ($adminPermissions as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }

    // 3. Create roles and assign permission supersets
    $userRole      = Role::firstOrCreate(['name' => 'User']);
    $moderatorRole = Role::firstOrCreate(['name' => 'Moderator']);
    $adminRole     = Role::firstOrCreate(['name' => 'Admin']);

    $userRole->syncPermissions($userPermissions);
    $moderatorRole->syncPermissions($moderatorPermissions);
    $adminRole->syncPermissions($adminPermissions);

    // 4. Create the default dev Admin account
    $admin = User::firstOrCreate(
        ['email' => 'admin@twosides.test'],
        ['name' => 'Admin', 'password' => Hash::make('password')]
    );
    $admin->assignRole('Admin');
}
```

### Pattern 5: Deactivated-User Login Gate (Fortify Pipeline)

**What:** Custom pipeline step that blocks deactivated users before their session is established.
**When to use:** Add to `FortifyServiceProvider::boot()` via `Fortify::authenticateThrough()`.

The deactivation check goes **after** `AttemptToAuthenticate` (user is now resolved) but **before** `PrepareAuthenticatedSession` (session not yet written).

```php
// app/Actions/Fortify/EnsureUserIsActive.php
public function __invoke(Request $request, Closure $next): mixed
{
    $user = Auth::user();

    if ($user && $user->account_status === 'deactivated') {
        Auth::logout();

        return redirect()->route('login')->withErrors([
            'email' => 'Your account has been deactivated. Contact an administrator.',
        ]);
    }

    return $next($request);
}
```

```php
// In FortifyServiceProvider::configureActions()
Fortify::authenticateThrough(fn (Request $request) => array_filter([
    EnsureLoginIsNotThrottled::class,
    config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
    Features::enabled(Features::twoFactorAuthentication())
        ? RedirectIfTwoFactorAuthenticatable::class : null,
    AttemptToAuthenticate::class,
    EnsureUserIsActive::class,         // custom step
    PrepareAuthenticatedSession::class,
]));
```

### Pattern 6: Last-Admin Guard

**What:** Prevent removing/disabling the last Admin. Shared trait method used across AssignRoleRequest and DeleteUserRequest.

```php
// app/Concerns/AdminUserGuards.php
trait AdminUserGuards
{
    protected function isLastAdmin(User $target): bool
    {
        if (! $target->hasRole('Admin')) {
            return false;
        }

        return User::role('Admin')->count() <= 1;
    }

    protected function isActingOnSelf(User $target): bool
    {
        return auth()->id() === $target->id;
    }
}
```

Validation in the FormRequest:

```php
// app/Http/Requests/Admin/AssignRoleRequest.php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $v) {
        $target = $this->route('user');

        if ($this->isActingOnSelf($target)) {
            $v->errors()->add('role', 'You cannot change your own role.');
        }

        if ($this->isLastAdmin($target) && $this->input('role') !== 'Admin') {
            $v->errors()->add('role', 'Cannot remove the last Admin role.');
        }
    });
}
```

### Pattern 7: SetLocale Middleware + HandleInertiaRequests

**What:** Read locale from authenticated user record; apply and share on every request.

```php
// app/Http/Middleware/SetLocale.php
public function handle(Request $request, Closure $next): Response
{
    $locale = 'en';

    if ($request->user()) {
        $locale = $request->user()->locale ?? 'en';
    }

    App::setLocale(in_array($locale, ['en', 'el']) ? $locale : 'en');

    return $next($request);
}
```

```php
// app/Http/Middleware/HandleInertiaRequests.php — extended share()
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name'        => config('app.name'),
        'auth'        => [
            'user'        => $request->user(),
            'permissions' => $request->user()
                ? $request->user()->getPermissionNames()
                : [],
        ],
        'locale'      => App::getLocale(),
        'sidebarOpen' => ! $request->hasCookie('sidebar_state')
            || $request->cookie('sidebar_state') === 'true',
    ];
}
```

`SetLocale` must run **before** `HandleInertiaRequests` in the web middleware stack. Register it in `bootstrap/app.php` `->web(append: [...])` in the correct order.

### Pattern 8: laravel-react-i18n Setup

**What:** Wrap the Inertia app with `LaravelReactI18nProvider`; add Vite plugin.

```typescript
// vite.config.ts — add i18n plugin
import i18n from 'laravel-react-i18n/vite';

plugins: [
    laravel({ ... }),
    inertia(),
    react({ ... }),
    tailwindcss(),
    wayfinder({ formVariants: true }),
    i18n(),   // compiles lang/*.php → lang/php_{locale}.json
],
```

```typescript
// resources/js/app.tsx — wrap with provider
import { LaravelReactI18nProvider } from 'laravel-react-i18n';

createInertiaApp({
    // ...
    withApp(app) {
        return (
            <LaravelReactI18nProvider
                locale={document.documentElement.lang || 'en'}
                fallbackLocale="en"
                files={import.meta.glob('/lang/php_*.json')}
            >
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </LaravelReactI18nProvider>
        );
    },
});
```

The `locale` prop on `<html lang="">` is set by the Blade layout root view — derive it from the `locale` shared prop.

Language switcher pattern:

```typescript
// resources/js/components/language-switcher.tsx
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { router } from '@inertiajs/react';

export function LanguageSwitcher() {
    const { currentLocale, setLocale } = useLaravelReactI18n();

    const handleChange = (locale: string) => {
        setLocale(locale);                               // optimistic React update
        router.put(route('locale.update'), { locale }); // persist to DB
    };

    return (/* EN / EL toggle buttons */);
}
```

### Pattern 9: Warm-Minimal Token Values (OKLCH)

**What:** Replace the current neutral OKLCH palette in `resources/css/app.css` `:root` and `.dark` blocks. The `@theme` mapping block stays untouched — only the custom property values change.

**Light mode warm tokens:**

```css
:root {
    --background:  oklch(0.981 0.007 60);   /* warm off-white, paper-like */
    --foreground:  oklch(0.168 0.010 50);   /* warm near-black */
    --card:        oklch(0.981 0.007 60);
    --card-foreground: oklch(0.168 0.010 50);
    --popover:     oklch(0.981 0.007 60);
    --popover-foreground: oklch(0.168 0.010 50);
    --primary:     oklch(0.210 0.010 50);   /* warm dark — used for primary buttons */
    --primary-foreground: oklch(0.981 0.007 60);
    --secondary:   oklch(0.948 0.010 60);   /* warm light grey */
    --secondary-foreground: oklch(0.210 0.010 50);
    --muted:       oklch(0.948 0.010 60);
    --muted-foreground: oklch(0.530 0.018 55);
    --accent:      oklch(0.550 0.035 60);   /* soft warm taupe — sparingly */
    --accent-foreground: oklch(0.981 0.007 60);
    --destructive: oklch(0.577 0.245 27.325);
    --destructive-foreground: oklch(0.577 0.245 27.325);
    --border:      oklch(0.900 0.012 58);   /* warm light border */
    --input:       oklch(0.900 0.012 58);
    --ring:        oklch(0.820 0.018 58);
    --radius:      0.5rem;                  /* slightly tighter for professional feel */
    /* sidebar tokens follow the warm palette */
    --sidebar:     oklch(0.970 0.009 60);
    --sidebar-foreground: oklch(0.168 0.010 50);
    --sidebar-primary: oklch(0.210 0.010 50);
    --sidebar-primary-foreground: oklch(0.981 0.007 60);
    --sidebar-accent: oklch(0.920 0.015 58);
    --sidebar-accent-foreground: oklch(0.210 0.010 50);
    --sidebar-border: oklch(0.900 0.012 58);
    --sidebar-ring:  oklch(0.820 0.018 58);
}
```

**Dark mode warm tokens:**

```css
.dark {
    --background:  oklch(0.148 0.008 50);   /* warm dark — not pure black */
    --foreground:  oklch(0.940 0.008 60);
    --card:        oklch(0.148 0.008 50);
    --card-foreground: oklch(0.940 0.008 60);
    --popover:     oklch(0.148 0.008 50);
    --popover-foreground: oklch(0.940 0.008 60);
    --primary:     oklch(0.940 0.008 60);
    --primary-foreground: oklch(0.210 0.010 50);
    --secondary:   oklch(0.225 0.010 52);
    --secondary-foreground: oklch(0.940 0.008 60);
    --muted:       oklch(0.225 0.010 52);
    --muted-foreground: oklch(0.650 0.015 56);
    --accent:      oklch(0.550 0.035 60);   /* same taupe works in dark */
    --accent-foreground: oklch(0.940 0.008 60);
    --destructive: oklch(0.396 0.141 25.723);
    --destructive-foreground: oklch(0.637 0.237 25.331);
    --border:      oklch(0.235 0.010 52);
    --input:       oklch(0.235 0.010 52);
    --ring:        oklch(0.395 0.015 54);
    --sidebar:     oklch(0.188 0.008 50);
    --sidebar-foreground: oklch(0.940 0.008 60);
    --sidebar-primary: oklch(0.940 0.008 60);
    --sidebar-primary-foreground: oklch(0.940 0.008 60);
    --sidebar-accent: oklch(0.255 0.010 52);
    --sidebar-accent-foreground: oklch(0.940 0.008 60);
    --sidebar-border: oklch(0.235 0.010 52);
    --sidebar-ring:  oklch(0.395 0.015 54);
}
```

**Key OKLCH intuition for warm colors:** Chroma 0 = neutral grey. Chroma 0.007–0.018 at hue 55–65 gives a warm tint without saturation. Background at `oklch(0.981 0.007 60)` is perceptually equivalent to `#FAF9F7` (paper-like warm off-white). The accent at `oklch(0.550 0.035 60)` is a soft taupe — visible but not saturated.

### Anti-Patterns to Avoid

- **Check roles, not permissions in routes:** `'role:Admin'` breaks the "gate on permissions" principle. Always use `'permission:manage-users'`.
- **Skipping `forgetCachedPermissions()` in seeders:** spatie caches permissions aggressively; omitting the cache reset causes stale-permission bugs in tests.
- **Editing Wayfinder output:** `resources/js/actions/` and `resources/js/routes/` are auto-generated. Add the `locale.update` route to `routes/web.php` and let Vite regenerate.
- **Hand-rolling OKLCH values without perceptual testing:** Values at hue 50–65 can drift warm-red vs. warm-yellow depending on chroma. Use a browser devtools color picker to verify the rendered hue looks paper-warm, not salmon.
- **Using `App::setLocale()` in a controller, not middleware:** If locale is set in the controller, any middleware-evaluated auth messages will already be in the wrong language.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Role/permission storage + caching | Custom `roles` table + cache logic | spatie/laravel-permission v7 | Package handles model binding, cache invalidation, guard awareness, and permission inheritance |
| Laravel ↔ React translation bridge | Manual JSON export + `window.translations` object | laravel-react-i18n | Vite plugin compiles lang/ PHP files to JSON at build time; hook handles pluralization, replacements, and locale switching |
| CSS variable-to-Tailwind-class mapping | Manual utility extensions | Tailwind v4 `@theme` (already configured) | `@theme` maps CSS vars to utility names automatically — the infrastructure is already in `app.css` |
| EU allergen seed data | Manual lookup | Hardcoded in `AllergenSeeder` from the canonical Annex II list | The 14 allergens are a fixed, regulation-defined list; no external API needed |

**Key insight:** The role/permission domain has enormous cache and guard complexity. spatie/laravel-permission has solved these problems across thousands of production apps. The cost of reimplementing — particularly permission cache invalidation on role changes — is disproportionate for a greenfield project.

---

## Common Pitfalls

### Pitfall 1: spatie Permission Cache Not Cleared in Tests

**What goes wrong:** Tests that assign roles in one test leave stale cached permissions that bleed into the next test, causing `assertFalse($user->can('manage-users'))` to fail inexplicably.
**Why it happens:** spatie caches permissions in the application cache. `RefreshDatabase` rolls back the DB, but cached objects in memory persist across test cases in the same process.
**How to avoid:** In `tests/Pest.php`, enable `RefreshDatabase` for the Feature suite (currently commented out). Add `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions()` in a `beforeEach` for RBAC tests, or use `$this->seed(RolesAndPermissionsSeeder::class)` + the package's test helpers.
**Warning signs:** Role/permission assertions fail intermittently depending on test execution order.

### Pitfall 2: 404 Instead of 403 on Permission Middleware

**What goes wrong:** Unauthorized access to `/admin/*` routes returns 404, not 403.
**Why it happens:** Middleware priority — spatie's `PermissionMiddleware` runs after `SubstituteBindings`, so route model binding resolves (or fails) before the permission check.
**How to avoid:** Register `'permission'` middleware **before** `SubstituteBindings` in the middleware priority list, or check that the route model binding on `{user}` is resolved from the authenticated user's role scope, not a global scope that would 404.
**Warning signs:** Users without permissions see 404 on admin user routes.

### Pitfall 3: Locale Not Set for Unauthenticated Users

**What goes wrong:** Guest users (login page, register page) see untranslated strings because `SetLocale` only reads `auth()->user()->locale`.
**Why it happens:** The `SetLocale` middleware short-circuits to `'en'` when no user is authenticated.
**How to avoid:** For unauthenticated users, fall back to a session-stored locale (`session('locale', 'en')`). The language switcher on guest pages writes to the session. On login, the authenticated user's DB preference takes over.
**Warning signs:** Returning Greek users see English strings on the login page.

### Pitfall 4: laravel-react-i18n `locale` Prop Mismatch

**What goes wrong:** React renders in English despite the backend setting Greek — or React updates locale but the next full page load reverts to English.
**Why it happens:** The `locale` passed to `<LaravelReactI18nProvider>` comes from `document.documentElement.lang`, which is set by the Blade root view on the initial visit. If `HandleInertiaRequests` shares `locale` as a prop but the HTML `lang` attribute is not updated, the provider initializes with the wrong locale.
**How to avoid:** The Blade root view (`resources/views/app.blade.php`) must set `<html lang="{{ app()->getLocale() }}">`. The `SetLocale` middleware must run before `HandleInertiaRequests` in the middleware stack.
**Warning signs:** Hard reload changes locale, but soft Inertia navigation doesn't.

### Pitfall 5: Last-Admin Guard Race Condition

**What goes wrong:** Two admin tabs simultaneously demote the last admin — both pass the `User::role('Admin')->count() > 1` check and both succeed.
**Why it happens:** The check and the update are not atomic.
**How to avoid:** Wrap the guard check and role assignment in a DB transaction with a `lockForUpdate()` on the user record. Not critical for MVP scale but worth noting.
**Warning signs:** Admin count drops to zero unexpectedly.

### Pitfall 6: `account_status` Column Missing from User Casts

**What goes wrong:** Deactivation check `$user->account_status === 'deactivated'` always evaluates to false because the column is cast to something unexpected.
**Why it happens:** Using PHP 8.1 enum cast for a string enum without the correct cast configuration.
**How to avoid:** Add `'account_status' => 'string'` to the User model's `casts()` method (or use a PHP enum backed by string). Use `'deactivated'` string comparison, not an uncast enum comparison.
**Warning signs:** Deactivated users can still log in.

---

## Code Examples

### Assigning a Permission Check in React (via Shared Props)

```typescript
// Using the permissions array from shared props
import { usePage } from '@inertiajs/react';

export function usePermission(permission: string): boolean {
    const { auth } = usePage().props;
    return (auth.permissions as string[]).includes(permission);
}

// In a nav component:
const canManageUsers = usePermission('manage-users');
```

### EU 14 Allergens Seeder

```php
// database/seeders/AllergenSeeder.php
// Source: EU Regulation 1169/2011 Annex II
private array $allergens = [
    ['name' => 'Gluten',       'slug' => 'gluten',     'note' => 'Cereals containing gluten (wheat, rye, barley, oats, spelt)'],
    ['name' => 'Crustaceans',  'slug' => 'crustaceans'],
    ['name' => 'Eggs',         'slug' => 'eggs'],
    ['name' => 'Fish',         'slug' => 'fish'],
    ['name' => 'Peanuts',      'slug' => 'peanuts'],
    ['name' => 'Soybeans',     'slug' => 'soybeans'],
    ['name' => 'Milk',         'slug' => 'milk',       'note' => 'Including lactose'],
    ['name' => 'Tree nuts',    'slug' => 'tree-nuts',  'note' => 'Almonds, hazelnuts, walnuts, cashews, pecans, Brazil nuts, pistachios, macadamia nuts'],
    ['name' => 'Celery',       'slug' => 'celery'],
    ['name' => 'Mustard',      'slug' => 'mustard'],
    ['name' => 'Sesame seeds', 'slug' => 'sesame'],
    ['name' => 'Sulphites',    'slug' => 'sulphites',  'note' => 'Sulphur dioxide and sulphites above 10 mg/kg'],
    ['name' => 'Lupin',        'slug' => 'lupin'],
    ['name' => 'Molluscs',     'slug' => 'molluscs'],
];
```

### Unit Table Seed (representative rows)

The unit table stores measurement units grouped by type. Phase 1 establishes the lookup table; Phase 3 adds conversion math.

```php
// database/seeders/UnitSeeder.php (representative rows)
private array $units = [
    // Weight
    ['name' => 'gram',       'symbol' => 'g',    'type' => 'weight', 'base_factor' => 1.0],
    ['name' => 'kilogram',   'symbol' => 'kg',   'type' => 'weight', 'base_factor' => 1000.0],
    ['name' => 'ounce',      'symbol' => 'oz',   'type' => 'weight', 'base_factor' => 28.3495],
    ['name' => 'pound',      'symbol' => 'lb',   'type' => 'weight', 'base_factor' => 453.592],
    // Volume
    ['name' => 'milliliter', 'symbol' => 'ml',   'type' => 'volume', 'base_factor' => 1.0],
    ['name' => 'liter',      'symbol' => 'l',    'type' => 'volume', 'base_factor' => 1000.0],
    ['name' => 'teaspoon',   'symbol' => 'tsp',  'type' => 'volume', 'base_factor' => 4.92892],
    ['name' => 'tablespoon', 'symbol' => 'tbsp', 'type' => 'volume', 'base_factor' => 14.7868],
    ['name' => 'cup',        'symbol' => 'cup',  'type' => 'volume', 'base_factor' => 236.588],
    // Count
    ['name' => 'piece',      'symbol' => 'pc',   'type' => 'count',  'base_factor' => null],
    ['name' => 'slice',      'symbol' => 'sl',   'type' => 'count',  'base_factor' => null],
    ['name' => 'bunch',      'symbol' => 'bunch','type' => 'count',  'base_factor' => null],
];
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| spatie/laravel-permission v6 (PHP ^8.0) | v7 (PHP ^8.3, typed signatures) | April 2026 (v7.4.1) | Must use v7 — v6 does not declare PHP 8.3 as a hard requirement the way v7 does; v7 adds strict types throughout |
| HSL CSS variables in shadcn/ui | OKLCH CSS variables | March 2025 (shadcn update) | The existing `app.css` already uses OKLCH — research confirms we should stay in OKLCH, not convert to HSL |
| `Kernel.php` middleware aliases | `bootstrap/app.php` `->withMiddleware(alias:[...])` | Laravel 11 | Already the pattern in this repo; spatie v7 docs show this approach |
| `import.meta.glob('/lang/*.json')` (manual) | Vite plugin from `laravel-react-i18n/vite` that auto-compiles PHP to JSON | 2024 | Plugin handles the PHP→JSON compile step; `.gitignore` the generated `lang/php_*.json` files |
| `lang/en.json` flat key format | `lang/en/app.php` PHP arrays (nested) | Current spatie recommendation | PHP arrays support nesting with dot notation; laravel-react-i18n handles both formats |

**Deprecated/outdated:**
- `Kernel.php` for middleware registration: removed in Laravel 11 — not present in this repo.
- `Inertia::lazy()` / `LazyProp`: removed in Inertia v3 — use `Inertia::optional()`.
- spatie `HasPermissions` blade directives: not used — React components gate via shared props array instead.

---

## Open Questions

1. **`lang/` directory bootstrap**
   - What we know: No `lang/` directory exists yet (`resources/lang/` not found in the project). Laravel 13 ships without a lang directory by default — it is created on first `php artisan lang:publish` or by `laravel-react-i18n`.
   - What's unclear: Whether `php artisan lang:publish` is needed before the i18n vite plugin runs, or the plugin bootstraps the directory.
   - Recommendation: Wave 0 task should create `lang/en/app.php` and `lang/el/app.php` manually with a minimum key set, then verify the Vite plugin compiles them correctly.

2. **Styleguide route — dev-only protection**
   - What we know: The plan calls for a dev-only `/dev/styleguide` route.
   - What's unclear: Whether to gate it with `app()->isLocal()` check in the route closure or a dedicated middleware. Both are valid Laravel 13 patterns.
   - Recommendation: Use `Route::middleware(['auth'])->when(app()->isLocal(), ...)` to avoid shipping the route in production. This is Claude's discretion.

3. **`account_status` as string enum vs PHP 8.1 BackedEnum**
   - What we know: The simplest migration uses `$table->string('account_status')->default('active')` with an application-level enum validation.
   - What's unclear: Whether a PHP-backed `AccountStatus` enum (`enum AccountStatus: string { case Active = 'active'; case Deactivated = 'deactivated'; }`) with a model cast is overkill for two values.
   - Recommendation: Use PHP 8.1 backed enum + model cast for type safety. The cast is `'account_status' => AccountStatus::class` and the migration is a `string` column.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest v4.7 + Laravel plugin |
| Config file | `tests/Pest.php` (RefreshDatabase currently commented out — must be enabled) |
| Quick run command | `php artisan test --compact --filter=Access` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ACCESS-01 | New registration is assigned User role | Feature | `php artisan test --compact --filter=RegistrationAssignsUserRole` | Wave 0 |
| ACCESS-01 | User model has HasRoles trait | Unit | `php artisan test --compact --filter=UserHasRolesTest` | Wave 0 |
| ACCESS-02 | Moderator can reach `/admin/ingredients` review queue placeholder | Feature | `php artisan test --compact --filter=ModeratorCanAccessReviewQueue` | Wave 0 |
| ACCESS-02 | User cannot reach `/admin/ingredients` | Feature | `php artisan test --compact --filter=UserCannotAccessReviewQueue` | Wave 0 |
| ACCESS-03 | Admin can list users at `GET /admin/users` | Feature | `php artisan test --compact --filter=AdminCanListUsers` | Wave 0 |
| ACCESS-03 | Admin can assign role via PUT `/admin/users/{user}/role` | Feature | `php artisan test --compact --filter=AdminCanAssignRole` | Wave 0 |
| ACCESS-03 | Admin can deactivate user | Feature | `php artisan test --compact --filter=AdminCanDeactivateUser` | Wave 0 |
| ACCESS-03 | Admin can soft-delete user | Feature | `php artisan test --compact --filter=AdminCanDeleteUser` | Wave 0 |
| ACCESS-04 | Non-admin cannot access `/admin/*` routes | Feature | `php artisan test --compact --filter=AccessControlTest` | Wave 0 |
| ACCESS-04 | Deactivated user cannot log in | Feature | `php artisan test --compact --filter=DeactivatedUserCannotLogin` | Wave 0 |
| ACCESS-04 | Admin cannot demote themselves | Feature | `php artisan test --compact --filter=AdminCannotDemoteThemselves` | Wave 0 |
| ACCESS-04 | System refuses to remove last Admin | Feature | `php artisan test --compact --filter=CannotRemoveLastAdmin` | Wave 0 |
| I18N-01 | Locale switcher PUT `/locale` persists to user record | Feature | `php artisan test --compact --filter=LocaleUpdateTest` | Wave 0 |
| I18N-01 | SetLocale middleware applies user locale to App::getLocale() | Feature | `php artisan test --compact --filter=SetLocaleMiddlewareTest` | Wave 0 |
| I18N-02 | `locale` shared prop present in HandleInertiaRequests | Feature | `php artisan test --compact --filter=LocaleSharedPropTest` | Wave 0 |
| UI-01 | Styleguide page renders at `/dev/styleguide` in local env | Feature | `php artisan test --compact --filter=StyleguidePageTest` | Wave 0 |
| UI-02 | CSS variables `--background` and `--accent` match warm-minimal values | Unit | Manual verification via styleguide page — not automated | Manual |
| UI-03 | Dashboard, admin, settings pages return 200 for authenticated users | Feature | `php artisan test --compact --filter=PageRenderTest` | Wave 0 |

**Note on UI-02:** Token values are visual — automated assertion of hex/OKLCH correctness is not practical. The styleguide page is the verification artifact. Automated tests confirm the styleguide route renders; visual correctness is manual.

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=[feature-under-test]`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Access/AccessControlTest.php` — covers ACCESS-02, ACCESS-04
- [ ] `tests/Feature/Access/RoleAssignmentTest.php` — covers ACCESS-01, ACCESS-03
- [ ] `tests/Feature/Access/AdminUserManagementTest.php` — covers ACCESS-03 operations
- [ ] `tests/Feature/Access/DeactivatedUserTest.php` — covers ACCESS-04 login gate
- [ ] `tests/Feature/Localization/SetLocaleTest.php` — covers I18N-01, I18N-02
- [ ] `tests/Feature/Admin/UserControllerTest.php` — covers ACCESS-03 HTTP layer
- [ ] `tests/Pest.php` — uncomment `->use(RefreshDatabase::class)` in Feature suite
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` — required by all RBAC tests
- [ ] `lang/en/app.php` + `lang/el/app.php` — minimum key sets for I18N tests
- [ ] `config/permission.php` — published via `php artisan vendor:publish`

---

## Sources

### Primary (HIGH confidence)

- [spatie/laravel-permission v7 installation docs](https://spatie.be/docs/laravel-permission/v7/installation-laravel) — installation, migration, HasRoles
- [spatie/laravel-permission v7 middleware docs](https://spatie.be/docs/laravel-permission/v7/basic-usage/middleware) — bootstrap/app.php alias registration, route protection
- [spatie/laravel-permission v7 seeding docs](https://spatie.be/docs/laravel-permission/v6/advanced-usage/seeding) — forgetCachedPermissions pattern (v6 doc, pattern confirmed in v7)
- [Packagist: spatie/laravel-permission 7.4.1](https://packagist.org/packages/spatie/laravel-permission) — confirmed version, PHP ^8.3, Laravel ^12/^13
- [GitHub: EugeneMeles/laravel-react-i18n](https://github.com/EugeneMeles/laravel-react-i18n) — installation, Vite plugin, hook API
- [shadcn/ui Tailwind v4 docs](https://ui.shadcn.com/docs/tailwind-v4) — OKLCH migration, @theme directive
- [shadcn/ui Theming docs](https://ui.shadcn.com/docs/theming) — full CSS variable list
- EU Regulation 1169/2011 Annex II (canonical source) — 14 mandatory allergens
- Existing codebase: `bootstrap/app.php`, `app.css`, `app.tsx`, `vite.config.ts`, `FortifyServiceProvider.php`, `HandleInertiaRequests.php` — confirmed current patterns

### Secondary (MEDIUM confidence)

- [Laravel Fortify docs — customizing auth pipeline](https://laravel.com/docs/13.x/fortify#customizing-user-authentication) — EnsureUserIsActive pipeline position
- [DEV: Laravel Multilang with Inertia + React](https://dev.to/abdasis/laravel-multilang-with-inertia-react-a-real-world-guide-51jg) — SetLocale middleware + HandleInertiaRequests share pattern + router.post locale persistence
- [Spatie role/permission basic usage](https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions) — syncPermissions hierarchy pattern

### Tertiary (LOW confidence)

- OKLCH token values in the "Pattern 9" section — derived from OKLCH color space theory + existing app.css chroma/lightness pattern. These are reasoned starting points; exact hue should be visually verified in the styleguide.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — versions confirmed from Packagist (7.4.1) and npm (2.0.5); compatibility verified
- Architecture: HIGH — patterns derived from official docs and existing codebase analysis
- Pitfalls: HIGH — most pitfalls sourced from official spatie docs or verified Inertia patterns; OKLCH token values are MEDIUM (visual verification required)
- EU allergen list: HIGH — sourced from official EU Commission / UK legislation mirror

**Research date:** 2026-05-16
**Valid until:** 2026-06-16 (30 days — packages are stable; spatie v7.x patch releases are non-breaking)
