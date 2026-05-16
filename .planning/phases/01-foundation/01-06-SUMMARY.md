---
phase: 01-foundation
plan: 06
subsystem: ui
tags: [i18n, localization, laravel-react-i18n, middleware, inertia, shared-props, react, typescript]

requires:
  - phase: 01-01
    provides: "lang/en/app.php + lang/el/app.php with nav/admin/auth/language keys; laravel-react-i18n npm package installed; i18n() Vite plugin in vite.config.ts"
  - phase: 01-02
    provides: "User model with HasRoles; getPermissionNames() available; bootstrap/app.php web middleware append list"
  - phase: 01-04
    provides: "Admin nav entry using manage-users permission guard defensively checking (auth.permissions ?? [])"

provides:
  - "locale column on users table (string, default 'en', after account_status)"
  - "SetLocale middleware reading user's locale (or session fallback for guests) and applying via App::setLocale"
  - "HandleInertiaRequests extended to share locale and auth.permissions to every Inertia page"
  - "UpdateLocaleRequest FormRequest validating locale against ['en', 'el']"
  - "UpdateLocaleController (invokable) persisting locale to user record and session (PUT /locale)"
  - "locale.update named route (PUT /locale, auth-gated)"
  - "<html lang> attribute set dynamically from app()->getLocale() in app.blade.php"
  - "LaravelReactI18nProvider wrapping the app in app.tsx with import.meta.glob for lang/php_*.json"
  - "SharedData TypeScript type extended with locale: string and auth.permissions: string[]"
  - "EN/EL LanguageSwitcher component in sidebar bottom area with WCAG-compliant touch targets"

affects: [02-ingredient-library, 03-recipe-core]

tech-stack:
  added: []
  patterns:
    - "SetLocale middleware inserted before HandleInertiaRequests in bootstrap/app.php web append list so locale is resolved before shared props are built"
    - "Guest locale fallback via session ('locale' key) — authenticated users use DB column, guests use session"
    - "LaravelReactI18nProvider wraps TooltipProvider+Toaster in app.tsx with import.meta.glob('/lang/php_*.json')"
    - "LanguageSwitcher uses optimistic setLocale() then silent fetch() for locale persistence (no Inertia page reload)"
    - "React deduplication via Vite resolve.dedupe so laravel-react-i18n shares the app's React instance"

key-files:
  created:
    - database/migrations/xxxx_add_locale_to_users_table.php
    - app/Http/Middleware/SetLocale.php
    - app/Http/Requests/Settings/UpdateLocaleRequest.php
    - app/Http/Controllers/Settings/UpdateLocaleController.php
    - resources/js/components/language-switcher.tsx
  modified:
    - app/Models/User.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - bootstrap/app.php
    - routes/web.php
    - resources/views/app.blade.php
    - resources/js/app.tsx
    - resources/js/types/index.d.ts
    - resources/js/components/app-sidebar.tsx

key-decisions:
  - "React deduplication via vite.config.ts resolve.dedupe — laravel-react-i18n bundles its own React copy which breaks the hook contract; deduplication ensures a single React instance across the app"
  - "UpdateLocaleController returns 204 No Content (not a redirect) because LanguageSwitcher uses a silent fetch(), not router.put() — a redirect response would cause Inertia to navigate"
  - "LanguageSwitcher uses silent fetch() instead of router.put() — router.put() triggers an Inertia visit and causes a full page reload, defeating the live string-swap UX"
  - "SetLocale positioned before HandleInertiaRequests in middleware chain so App::getLocale() is correct when share() builds the locale prop"
  - "Session-based locale fallback for guests prevents resetting locale to 'en' on every unauthenticated request"

patterns-established:
  - "Silent fetch() for fire-and-forget persistence: use fetch() not router.put() when you need to persist a preference without triggering an Inertia navigation"
  - "Middleware ordering via bootstrap/app.php web append list: SetLocale before HandleInertiaRequests"
  - "Vite resolve.dedupe for third-party packages that bundle React: add the package to dedupe array in vite.config.ts"

requirements-completed: [I18N-01, I18N-02]

duration: ~60min
completed: 2026-05-16
---

# Phase 01 Plan 06: Localization Summary

**Full EN/EL UI localization: locale column, SetLocale middleware, shared locale+permissions props, PUT /locale persistence endpoint, LaravelReactI18nProvider, and LanguageSwitcher with live string swap and persisted preference**

## Performance

- **Duration:** ~60 min
- **Started:** 2026-05-16
- **Completed:** 2026-05-16
- **Tasks:** 4 (3 auto + 1 human-verify checkpoint)
- **Files modified:** 13

## Accomplishments

- `locale` column on users table with `SetLocale` middleware applying it per-request before Inertia shares props
- `HandleInertiaRequests` extended to share `locale` (app locale) and `auth.permissions` (permission names) to every Inertia page — the permissions prop unblocks Plan 04's admin nav gating
- Invokable `UpdateLocaleController` with `PUT /locale` persisting locale to user record and session (guest fallback)
- `LaravelReactI18nProvider` wired in `app.tsx` with `import.meta.glob` for the Vite-compiled `lang/php_*.json` files
- EN/EL `LanguageSwitcher` in sidebar bottom area: optimistic `setLocale()` + silent `fetch()` for seamless live string swap with no page reload

## Task Commits

1. **Task 1: locale column, SetLocale middleware, HandleInertiaRequests extension** - `6f79260` (feat)
2. **Task 2: UpdateLocaleController + FormRequest + locale.update route** - `0187b7a` (feat)
3. **Task 3: LaravelReactI18nProvider, TS types, LanguageSwitcher** - `37541cb` (feat)
4. **Human-verify fix: dedupe React for laravel-react-i18n** - `52fcae7` (fix)
5. **Human-verify fix: return 204 No Content from UpdateLocaleController** - `186ec30` (fix)
6. **Human-verify fix: replace router.put() with silent fetch() in LanguageSwitcher** - `ed307d7` (fix)

## Files Created/Modified

- `database/migrations/xxxx_add_locale_to_users_table.php` - Adds locale string column (default 'en') after account_status
- `app/Http/Middleware/SetLocale.php` - Reads user locale or session fallback; applies via App::setLocale with validation against ['en', 'el']
- `app/Http/Requests/Settings/UpdateLocaleRequest.php` - Validates locale against Rule::in(['en', 'el'])
- `app/Http/Controllers/Settings/UpdateLocaleController.php` - Invokable: persists locale to user + session, returns 204
- `resources/js/components/language-switcher.tsx` - EN/EL Toggle pair: useLaravelReactI18n, role="group", min-h-[44px] WCAG targets, silent fetch() persistence
- `app/Models/User.php` - Added 'locale' to $fillable
- `app/Http/Middleware/HandleInertiaRequests.php` - Extended share() with locale and auth.permissions keys
- `bootstrap/app.php` - Inserted SetLocale::class before HandleInertiaRequests in web append list
- `routes/web.php` - Added PUT /locale route (locale.update, auth-gated)
- `resources/views/app.blade.php` - Set <html lang> to app()->getLocale()
- `resources/js/app.tsx` - Wrapped app in LaravelReactI18nProvider; added React dedupe to vite.config.ts
- `resources/js/types/index.d.ts` - Added locale: string and auth.permissions: string[] to SharedData
- `resources/js/components/app-sidebar.tsx` - Rendered LanguageSwitcher in sidebar bottom area

## Decisions Made

- **React deduplication:** `laravel-react-i18n` ships its own React bundle. Without deduplication, the package's `useLaravelReactI18n` hook ran against a different React instance than the app, causing a white screen. Fixed by adding `['react', 'react-dom']` to `resolve.dedupe` in `vite.config.ts`.
- **204 No Content response:** `UpdateLocaleController` initially returned `back()` (a redirect). The `LanguageSwitcher` used `router.put()` which Inertia intercepted and performed a page visit — causing a full reload and resetting the live-swap UX. Changed controller to return `response()->noContent()` and switcher to `fetch()`.
- **Silent fetch() in LanguageSwitcher:** `router.put()` is an Inertia visit; `fetch()` is a plain HTTP request. For fire-and-forget locale persistence where we want optimistic `setLocale()` + no navigation, `fetch()` is the correct primitive.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Deduplicated React to fix app-wide white screen**
- **Found during:** Task 4 (human-verify checkpoint — app showed a white screen after LanguageSwitcher was rendered)
- **Issue:** `laravel-react-i18n` bundles its own React copy. When Vite resolves two separate React instances, React hooks throw invariant violations causing a complete render failure
- **Fix:** Added `resolve: { dedupe: ['react', 'react-dom'] }` to `vite.config.ts` so all packages share the app's single React instance
- **Files modified:** `vite.config.ts`
- **Verification:** White screen resolved; LanguageSwitcher rendered and `useLaravelReactI18n` hook functioned correctly
- **Committed in:** `52fcae7`

**2. [Rule 1 - Bug] Changed UpdateLocaleController to return 204 No Content**
- **Found during:** Task 4 (human-verify checkpoint — language switch caused a full page reload)
- **Issue:** Controller returned `back()` (a redirect response). Inertia's `router.put()` intercepted the redirect and performed a full Inertia visit, causing a page reload and breaking the live string-swap UX
- **Fix:** Changed `return back()` to `return response()->noContent()` (HTTP 204)
- **Files modified:** `app/Http/Controllers/Settings/UpdateLocaleController.php`
- **Verification:** Controller returns 204; no Inertia navigation triggered
- **Committed in:** `186ec30`

**3. [Rule 1 - Bug] Replaced router.put() with silent fetch() in LanguageSwitcher**
- **Found during:** Task 4 (human-verify checkpoint — even with 204 from controller, router.put() still caused navigation)
- **Issue:** Inertia's `router.put()` is always a visit — it navigates regardless of response status. For fire-and-forget persistence with optimistic UI, a plain `fetch()` is required
- **Fix:** Replaced `router.put(route('locale.update'), { locale }, { preserveScroll: true })` with a silent `fetch()` call that ignores the response
- **Files modified:** `resources/js/components/language-switcher.tsx`
- **Verification:** Locale switch updates UI strings instantly with no page reload; choice persists to the user record
- **Committed in:** `ed307d7`

---

**Total deviations:** 3 auto-fixed (3 bugs)
**Impact on plan:** All three fixes were discovered at the human-verify checkpoint and are necessary for correct UX. The pattern (silent fetch() for fire-and-forget + 204 from controller + React dedupe) is documented and applies to any future third-party i18n or UI-state persistence scenario.

## Issues Encountered

- **Stale dev server confusion during verification:** The `laravel-react-i18n` Vite plugin generates `lang/php_*.json` files at Vite startup by compiling `lang/*.php`. If the dev server was started before the lang files existed or before the plugin was wired, the JSON files are absent and translations silently fall back to keys. This is an environment caveat — restarting the dev server after all changes are in place resolves it. This is not a code defect.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Full localization infrastructure is in place: `locale` column, `SetLocale` middleware, shared `locale` prop, `LaravelReactI18nProvider`, and the `LanguageSwitcher`
- All Inertia pages now receive `locale` and `auth.permissions` — the latter was the missing prop that Plan 04's admin nav guard was waiting for
- Phase 2 (Ingredient Library) can use `useLaravelReactI18n` for ingredient name translation (I18N-02 infrastructure is ready)
- Adding new translated keys is a matter of adding entries to `lang/en/app.php` and `lang/el/app.php`

---
*Phase: 01-foundation*
*Completed: 2026-05-16*
