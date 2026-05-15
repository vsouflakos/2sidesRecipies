# Architecture

**Analysis Date:** 2026-05-16

## Pattern Overview

**Overall:** Full-stack Laravel + React SPA (Single Page Application) using Inertia.js v3 for server-side rendering of client components

**Key Characteristics:**
- Inertia.js acts as a bridge between Laravel backend and React frontend, eliminating traditional JSON API complexity
- Server-driven state with client-side interactivity via Fortify for authentication
- Form-centric approach with Laravel Form Requests for validation
- TypeScript/React components with Tailwind CSS for styling
- Wayfinder for automatic TypeScript route/action generation from Laravel controllers

## Layers

**Presentation (React/Frontend):**
- Purpose: User interface, forms, and client-side interactions
- Location: `resources/js/` 
- Contains: React pages (`resources/js/pages/`), components (`resources/js/components/`), layouts (`resources/js/layouts/`)
- Depends on: Inertia.js for server communication, Wayfinder for generated routes/actions, Radix UI primitives
- Used by: Browser clients rendering via Vite dev server or built assets

**HTTP Layer (Controllers & Requests):**
- Purpose: Request handling, validation, and response composition
- Location: `app/Http/` containing Controllers, Requests, and Middleware
- Contains: 
  - Controllers in `app/Http/Controllers/` (ProfileController, SecurityController)
  - Form Requests in `app/Http/Requests/` (ProfileUpdateRequest, PasswordUpdateRequest)
  - Middleware in `app/Http/Middleware/` (HandleInertiaRequests, HandleAppearance)
- Depends on: Models, Concerns (shared validation traits), Laravel Fortify
- Used by: Routes, browser requests

**Authentication & Authorization:**
- Purpose: User authentication, registration, password reset, 2FA
- Location: `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/`
- Contains: Fortify service provider configuration, user creation and password reset actions
- Depends on: Laravel Fortify, User model, validation traits
- Used by: Web routes middleware chain, form submissions

**Data Layer (Models & Database):**
- Purpose: Data persistence and Eloquent ORM interactions
- Location: `app/Models/` (User model), `database/migrations/`, `database/factories/`
- Contains: User model with TwoFactorAuthenticatable trait, migrations for users/sessions/cache, factories for testing
- Depends on: Laravel Eloquent, database
- Used by: Controllers for database operations

**Shared Concerns (Traits):**
- Purpose: Reusable validation rules and logic
- Location: `app/Concerns/`
- Contains: 
  - `PasswordValidationRules.php` - password and current password validation
  - `ProfileValidationRules.php` - name/email validation with uniqueness checks
- Depends on: Laravel validation, Illuminate Rules
- Used by: Multiple Form Requests and Actions

**Configuration & Bootstrapping:**
- Purpose: Application setup and initialization
- Location: `bootstrap/app.php` (Laravel 13 fluent config), `config/` directory
- Contains: Route definitions, middleware registration, exception handling, Fortify features config
- Depends on: Laravel framework core
- Used by: Application bootstrap process

## Data Flow

**Authentication Flow (Login):**
1. Browser request to `POST /login` route (handled by Fortify)
2. Fortify validates credentials using rate limiting
3. `Illuminate\Foundation\Auth` creates authenticated session
4. Redirect to `/dashboard` with authenticated user
5. Subsequent requests include authenticated user in Inertia props

**Profile Update Flow:**
1. Browser requests `GET /settings/profile` 
2. `ProfileController::edit()` renders via Inertia with user data
3. User modifies form in React component (`resources/js/pages/settings/profile.tsx`)
4. Form submission to `PATCH /settings/profile`
5. `ProfileUpdateRequest` validates using `ProfileValidationRules` trait
6. `ProfileController::update()` updates User model and flashes success toast
7. Inertia redirects back with flash message
8. React component displays toast notification via shared props

**Two-Factor Setup:**
1. User navigates to security settings
2. `SecurityController::edit()` calls `$request->ensureStateIsValid()` to verify 2FA state
3. Inertia renders security page with 2FA status and requirements
4. Modal component handles 2FA setup/confirmation flow
5. Recovery codes displayed and stored via Fortify trait on User model

**State Management:**
- Server-side state: Authenticated user, session data, application configuration
- Client-side state: Form processing status, UI toggles (sidebar open/close via cookies)
- Flash data: Success/error messages passed via Inertia flash mechanism
- Persistent state: Appearance preference (system/light/dark) and sidebar state stored as non-encrypted cookies

## Key Abstractions

**Inertia Middleware (HandleInertiaRequests):**
- Purpose: Share data between server and client automatically
- Location: `app/Http/Middleware/HandleInertiaRequests.php`
- Pattern: Extends Inertia\Middleware, implements share() method
- Provides globally: app name, authenticated user, sidebar open state

**Appearance Middleware (HandleAppearance):**
- Purpose: Handle theme/appearance cookie and share with views
- Location: `app/Http/Middleware/HandleAppearance.php`
- Pattern: View::share() approach for server-side rendering
- Manages: Appearance cookie value passed to layout components

**Fortify Actions:**
- Purpose: Encapsulate user creation and password reset logic
- Location: `app/Actions/Fortify/`
- Examples: `CreateNewUser.php`, `ResetUserPassword.php`
- Pattern: Implements Fortify contracts (CreatesNewUsers, ResetsUserPasswords)
- Reuses: Validation traits from Concerns

**Wayfinder Routes & Actions:**
- Purpose: Auto-generated TypeScript functions for calling Laravel routes/actions
- Location: `resources/js/actions/` and `resources/js/routes/`
- Pattern: Generated from Laravel controller class structure
- Usage: Import in React components to type-safe route/form generation

## Entry Points

**Web Entry (Web Routes):**
- Location: `routes/web.php`, `routes/settings.php`
- Triggers: Browser HTTP requests
- Responsibilities: 
  - Public routes: home page with optional registration notice
  - Authenticated routes: dashboard, profile settings, security settings
  - Verified routes: account deletion, 2FA management

**Frontend Entry (React App):**
- Location: `resources/js/app.tsx`
- Triggers: Vite dev server or built assets loaded in browser
- Responsibilities: Bootstrap Inertia SPA, register layouts, render pages based on route

**Fortify Entry (Authentication):**
- Location: `app/Providers/FortifyServiceProvider.php`
- Triggers: Fortify middleware and routes (handled automatically by Fortify)
- Responsibilities: Configure login/register/password reset views via Inertia, set rate limiting

**CLI Entry (Artisan):**
- Location: `routes/console.php`, `bootstrap/app.php`
- Triggers: `php artisan [command]`
- Responsibilities: Define CLI commands (if any custom commands added)

## Error Handling

**Strategy:** Server-side validation with client-side feedback

**Patterns:**
- Form Request validation: Returns 422 with error bag to client
- Inertia automatically re-populates form with errors via `$errors` prop
- Flash messages for success/error feedback via `Inertia::flash()`
- Middleware can apply password confirmation middleware for sensitive operations
- Rate limiting on login and 2FA via `throttle` middleware

## Cross-Cutting Concerns

**Logging:** 
- Default Laravel logging via `config/logging.php`
- Single channel configuration (typically file or stack)
- Accessible via `Log` facade in controllers/actions

**Validation:** 
- Centralized via Concerns traits (PasswordValidationRules, ProfileValidationRules)
- Form Request classes leverage traits for DRY validation
- Password validation uses Laravel Password rule with default strength rules

**Authentication:** 
- Laravel Fortify handles all auth flows (login, register, password reset, 2FA)
- User model uses TwoFactorAuthenticatable trait from Fortify
- Session-based authentication via 'web' guard
- Protected routes use `auth` and `verified` middleware
- Password confirmation middleware available for sensitive operations

---

*Architecture analysis: 2026-05-16*
