# Code Conventions

Coding style, naming, and patterns used in the `twosides` codebase. Follow these when adding or editing code.

## Naming

| Element | Convention | Example |
|---------|-----------|---------|
| PHP files / classes | PascalCase | `ProfileController`, `ProfileUpdateRequest` |
| PHP methods | camelCase | `update()`, `destroy()` |
| PHP variables | camelCase | `$validated`, `$request` |
| TypeScript files | kebab-case | `two-factor-setup-modal.tsx`, `use-appearance.tsx` |
| React components | PascalCase | `TwoFactorSetupModal` |
| TS functions / variables | camelCase, descriptive | `canManageTwoFactor`, `isDarkMode` |
| Enum keys | TitleCase | `Monthly`, `FavoritePerson` |
| Booleans | `is`/`can`/`has` prefix | `isRegisteredForDiscounts`, `canManageTwoFactor` |

## PHP Style

- **Formatter:** Laravel Pint (`pint.json`, Laravel preset). Run `vendor/bin/pint --dirty --format agent` before finalizing.
- **Curly braces:** Always, even for single-line control structures.
- **Constructor promotion:** Use PHP 8 promoted properties — `public function __construct(public GitHub $github) {}`.
- **Type hints:** Explicit return types and parameter type hints on every method — `function isAccessible(User $user, ?string $path = null): bool`.
- **Comments:** PHPDoc blocks over inline comments; inline only for genuinely complex logic.
- **PHPDoc:** Use array shape definitions for typed arrays.

## TypeScript / React Style

- **Formatter:** Prettier (`.prettierrc`) — 4-space tabs, 80-char line width, single quotes, semicolons, Tailwind class sorting plugin.
- **Linter:** ESLint v9 (`eslint.config.js`) — 1tbs brace style, control-statement padding, TypeScript `import type` for type-only imports.
- **Import order:** builtin → external → internal → parent → sibling → index, grouped with blank lines.
- **Components:** Function components with hooks; no class components.

## Backend Patterns

- **Validation:** `FormRequest` classes per action (`ProfileUpdateRequest`, `PasswordUpdateRequest`) — keep `rules()` there, not in controllers.
- **Shared rules:** Extracted into traits under `app/Concerns/` (`ProfileValidationRules`, `PasswordValidationRules`) and mixed into FormRequests.
- **Controllers:** Thin — resolve the FormRequest, act, return `Inertia::render()` or redirect.
- **Auth scaffolding:** Authentication lives in **Laravel Fortify**; custom logic goes in `app/Actions/Fortify/` action classes, not controllers.
- **Routes:** Use named routes and the `route()` helper for URL generation.
- **Models:** Create via `php artisan make:model` with factories; only `User` exists today.

## Frontend Patterns

- **Routing:** Server-driven via Inertia — `Inertia::render('settings/profile', [...])` maps to `resources/js/pages/settings/profile.tsx`.
- **Route helpers:** Import from Wayfinder-generated `@/actions/` (controllers) and `@/routes/` (named routes). Never hand-edit those folders.
- **State / forms:** Inertia form helpers and v3 hooks (`useHttp`, `useLayoutProps`).
- **UI primitives:** shadcn/ui components in `components/ui/`; compose new components from these before writing fresh ones.
- **Class merging:** Use the `cn()` helper from `lib/utils.ts`.
- **Deferred props:** Pair with an animated skeleton empty state.

## Error Handling

- Validation errors flow through `FormRequest` → Inertia error bag → `input-error.tsx` / `alert-error.tsx` components.
- Flash messages surfaced via the `use-flash-toast` hook.
- Theme/appearance handled by `HandleAppearance` middleware + `use-appearance` hook.

## Reuse Rule

Before writing a new component, controller, or trait, check sibling files for an existing one. Match the structure, approach, and naming of neighboring files.

---
*Mapped: 2026-05-16*
