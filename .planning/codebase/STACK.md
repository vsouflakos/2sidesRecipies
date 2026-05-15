# Technology Stack

**Analysis Date:** 2026-05-16

## Languages

**Primary:**
- PHP 8.3 - Backend application runtime
- TypeScript 5.7.2 - Type-safe JavaScript for frontend
- React 19.2.0 - Frontend UI framework and component library

**Secondary:**
- CSS (Tailwind CSS) - Styling and design system
- JavaScript (ESNext) - Runtime compilation target

## Runtime

**Environment:**
- Laravel Herd - Local development environment via `twosides.test`
- Node.js (implied by npm/Vite) - Frontend build tooling

**Package Managers:**
- Composer 2.x - PHP dependencies
  - Lockfile: `composer.lock` present
- npm - JavaScript dependencies
  - Lockfile: `package-lock.json` (standard npm lockfile)
  - Script: `ignore-scripts=true` in `.npmrc` - scripts disabled for security

## Frameworks

**Backend (Core):**
- Laravel 13.7 - Full-stack PHP framework with routing, database, auth
- Inertia.js (Laravel adapter) 3.0 - Server-side rendering bridge for SPA

**Authentication & Authorization:**
- Laravel Fortify 1.34 - Headless authentication scaffolding (login, registration, password reset)
- Laravel Boost 2.4 (dev) - Enhancement tools for Laravel development

**Frontend (Core):**
- React 19.2.0 - Component library and UI framework
- Inertia.js (React adapter) 3.0 - React client for server-rendered pages
- TailwindCSS 4.0.0 - Utility-first CSS framework

**Build/Dev Tools:**
- Vite 8.0.0 - Frontend asset bundler
- Laravel Vite Plugin 3.1 - Laravel integration for Vite
- @inertiajs/vite 3.0.0 - Inertia SSR and build support
- Tailwind CSS Vite Plugin (@tailwindcss/vite) 4.1.11 - Optimized Tailwind compilation

**Component Library:**
- Radix UI (React components) - Multiple components: Avatar, Checkbox, Collapsible, Dialog, Dropdown Menu, Label, Navigation Menu, Select, Separator, Slot, Toggle, Toggle Group, Tooltip
- Lucide React 0.475.0 - Icon library
- Headless UI 2.2.0 - Unstyled accessible components
- Sonner 2.0.0 - Toast notifications

**Testing:**
- Pest 4.7 (dev) - PHP testing framework built on PHPUnit
- PHPUnit 12 (dev) - Underlying testing library for Pest
- Pest Plugin for Laravel 4.1 (dev) - Laravel-specific testing utilities
- Faker (fakerphp/faker 1.24) - Test data generation

**Code Quality & Linting:**
- ESLint 9.17.0 - JavaScript/TypeScript linting
  - Plugins: @stylistic/eslint-plugin 5.10.0, react, react-hooks, import
- Prettier 3.4.2 - Code formatter
  - Plugin: prettier-plugin-tailwindcss 0.6.11 - Tailwind class sorting
- Laravel Pint 1.27 (dev) - PHP code formatter
- TypeScript compiler (tsc) 5.7.2 - Type checking (via `npm run types:check`)

**Utilities & Libraries:**
- Inertia Wayfinder (Route generation) 0.1.14 - Type-safe Laravel route generation
- @laravel/vite-plugin-wayfinder 0.1.3 - Vite integration for Wayfinder
- Class Variance Authority 0.7.1 - CSS class composition
- clsx 2.1.1 - Conditional className utility
- tailwind-merge 3.0.1 - Merge Tailwind classes without conflicts
- input-otp 1.4.2 - OTP input component
- tw-animate-css 1.4.0 - Tailwind animation utilities
- Bacon QR Code 3.1.1 - QR code generation (for 2FA)

**Development Tools:**
- Laravel Tinker 3.0 (dev) - REPL for Laravel
- Laravel Pail 1.2.5 (dev) - Log viewer
- Laravel PAO 1.0.6 (dev) - Property accessor optimization
- Laravel Sail 1.53 (dev) - Docker environment (optional)
- Concurrently 9.0.1 - Run multiple npm scripts concurrently
- Babel Plugin React Compiler 1.0.0 - React 19 compiler for optimizations

## Key Dependencies

**Critical (Backend):**
- Illuminate framework components (routing, database, validation, etc.)
- Laravel Fortify - Authentication system
- Inertia Laravel adapter - Server-side page rendering

**Critical (Frontend):**
- React 19 - UI library with React Compiler support
- Inertia React - Page component framework
- TailwindCSS - Styling solution
- TypeScript - Type safety

**Infrastructure:**
- Brick Math 0.14.8 - Arbitrary precision arithmetic (via dependencies)
- Mockery 1.6 - PHP mocking library for tests
- Nunomaduro Collision 8.9.3 - Better error reporting
- Illuminate Database - Query builder and ORM (Eloquent)

## Configuration

**Environment:**
- `.env` file - Environment variables (not tracked in git)
- `.env.example` - Example configuration template
- Configuration stored in `config/` directory with PHP files

**Build:**
- `vite.config.ts` - Vite configuration with plugins: inertia, react, tailwindcss, wayfinder, laravel-vite-plugin
- `tsconfig.json` - TypeScript configuration with path aliases (`@/*` → `resources/js/*`)
- `eslint.config.js` - ESLint flat config with TypeScript, React, and stylistic rules
- `.prettierrc` - Prettier config: 4-space tabs, single quotes, 80 char width
- `tailwind.config.js` or PostCSS - Tailwind CSS configuration (standard Laravel setup)

**Composer:**
- `composer.json` - PHP dependency definitions
- `composer.lock` - Locked version constraints
- PSR-4 autoloading: `App\` → `app/`, `Tests\` → `tests/`

**NPM:**
- `package.json` - JavaScript dependency definitions
- Dev scripts: `build`, `dev`, `build:ssr`, `format`, `format:check`, `lint`, `lint:check`, `types:check`
- Composer dev scripts: `setup`, `dev`, `lint`, `lint:check`, `ci:check`, `test`

## Platform Requirements

**Development:**
- PHP 8.3
- Node.js (version not explicitly specified but implied compatible with npm scripts)
- Composer
- npm
- Laravel Herd for local serving (`twosides.test`)

**Production:**
- PHP 8.3+
- Web server (nginx/Apache) with Laravel support
- Database (SQLite for default, MySQL/PostgreSQL configurable)
- Node.js (optional, for production asset building)
- Redis (optional, for queue/cache if configured)

---

*Stack analysis: 2026-05-16*
