# Phase 1: Foundation - Context

**Gathered:** 2026-05-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 1 builds the shared infrastructure every later phase depends on. It delivers:

- A three-tier role system (User / Moderator / Admin) with enforced access control
- The unit lookup table and allergen lookup table, seeded and available app-wide
- UI localization (Greek + English) — translatable strings and a language switch
- A consistent warm-minimal design system on shadcn/ui with light + dark themes

It does NOT build the features those foundations serve — the ingredient review
queue (Phase 7), the ingredient library (Phase 2), recipes/metrics (Phase 3),
etc. Phase 1 stands up the scaffolding and proves it works.

</domain>

<decisions>
## Implementation Decisions

### Role system & access control

- **Storage:** Use the **spatie/laravel-permission** package. This is an
  approved new dependency (the user explicitly chose it over an enum column or a
  hand-rolled roles table).
- **Permission model:** Define **named permissions** (e.g. `review-ingredients`,
  `manage-users`) and assign them to roles in the seeder. Gate routes and
  actions on **permissions**, not raw role names — spatie's idiomatic approach,
  and makes future fine-grained access cheap.
- **Hierarchy:** Roles are hierarchical — **Admin ⊇ Moderator ⊇ User**. One role
  per account. Hierarchy is realized by permission assignment: the Admin role
  holds every permission, the Moderator role holds Moderator + User permissions,
  etc. (spatie has no built-in role inheritance — assign permissions so the
  superset relationship holds).
- **Default role:** New registrations are assigned the **User** role. No
  account is ever Moderator/Admin without a deliberate grant.
- **First Admin:** Created via a **database seeder** (a default Admin account for
  local/dev). No "first user becomes admin" auto-promotion.
- **Enforcement:** Role/permission gates must be enforced **consistently across
  the app** (ACCESS-04). Unauthorized access to a gated route returns **403**
  (per ROADMAP success criterion 1).

### Admin user management

- **Surface:** A dedicated **`/admin`** section with its own nav entry, visible
  only to Admins. The Moderator review queue (Phase 7) is expected to slot into
  a similar admin-style area later — `/admin` is the forward-looking home.
- **User list:** A table of all users (name, email, role, joined date) with a
  **search box** and **pagination**. Built from existing shadcn/ui table/select
  primitives.
- **Account operations (all three in Phase 1):**
  1. **Assign role** — inline role change from the user list.
  2. **Deactivate / suspend** — disable a user's login. Introduces an
     account-status concept (status column + a login gate).
  3. **Delete account** — uses Laravel **SoftDeletes** (recoverable, not a hard
     delete).
- **Safeguards:** An Admin **cannot demote, deactivate, or delete themselves**,
  and the system **refuses to remove/disable the final remaining Admin**. These
  guards apply to role changes, deactivation, AND deletion — not just role
  changes.

### Design system

- **Overall direction:** Warm-minimal — professional, quiet, minimal. Warmth is
  carried by neutral tones, not a loud accent.
- **Backgrounds:** Light mode uses a **warm off-white** — soft, paper-like
  warmth, NOT a clinical pure white.
- **Accent:** A **warm taupe / near-neutral** accent — very soft, low
  saturation, barely distinct from the neutrals. The most minimal option: the
  warm grey-brown neutral palette does the work; the accent is used sparingly.
- **Scope of Phase 1 design work:** Establish the **full CSS-variable token set**
  — color, spacing, radius, typography scale — for **both light and dark**
  themes, and tune shadcn/ui defaults to the warm-minimal look. Apply across
  existing pages (dashboard, settings, auth). The design system must be real and
  verifiable, not a placeholder.
- **Typography:** Keep a **single clean sans-serif** throughout (the starter
  kit's Instrument Sans, or similar — no new font dependency). Headings get
  contrast via weight/size, not a different family.
- **Verification artifact:** Build an **internal styleguide / component showcase
  page** (a dev-only route) that renders the token palette and shadcn components
  in both themes. This is the concrete proof the design system exists and a
  reference for later frontend phases.

### Lookup tables (mechanical — no discussion needed)

- Seed a **unit lookup table** and an **allergen lookup table**; both must be
  available to the rest of the app (ROADMAP success criterion 2).
- The allergen lookup table follows the **EU Regulation 1169/2011** model — the
  **14 mandatory allergens** (a hard project compliance constraint).

### Claude's Discretion

- **Localization approach (I18N-01, I18N-02):** Not discussed — left to research
  and planning. Use standard Laravel + Inertia patterns. The phase must deliver:
  a translatable UI, a user-facing Greek/English language switch, persisted
  language preference, and the infrastructure for ingredient names to display in
  the selected language (the ingredient data itself arrives in Phase 2). Switcher
  placement and the translation-file strategy are Claude's call.
- Exact role/permission enforcement mechanism (middleware vs Gate/policies vs
  both) — Claude's discretion within "enforced consistently, returns 403".
- The 403 unauthorized experience (dedicated error page vs default) — Claude's
  discretion.
- Exact token values (specific hex for the warm off-white and taupe accent,
  spacing/radius scale numbers) — Claude's discretion within the warm-minimal,
  soft-accent, whitish-background direction above.
- The styleguide page's exact layout and which components it showcases.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Product spec & requirements
- `Project.md` (repo root) — Full product spec: vision, domain model, roles,
  design direction. **§7 "Design & UX"** is the authoritative source for the
  warm-minimal aesthetic, component approach, and color-mode direction.
- `.planning/REQUIREMENTS.md` — Definitions for this phase's requirements:
  ACCESS-01..04, I18N-01, I18N-02, UI-01..03.
- `.planning/PROJECT.md` — GSD context summary; Key Decisions table (allergen
  model, multi-language-from-start, shadcn/ui foundation).

### Codebase maps (existing patterns to follow)
- `.planning/codebase/STRUCTURE.md` — Directory layout, key locations, naming
  conventions for new models/controllers/pages.
- `.planning/codebase/CONVENTIONS.md` — PHP + TS/React style, backend patterns
  (FormRequest per action, traits in `app/Concerns/`), frontend patterns
  (shadcn/ui composition, `cn()` helper, Wayfinder routes).
- `.planning/codebase/ARCHITECTURE.md` — Inertia v3 bridge, Fortify auth,
  HandleInertiaRequests shared props, HandleAppearance theme handling.

### Compliance
- **EU Regulation 1169/2011** (external) — The 14 mandatory food allergens.
  Defines the rows of the allergen lookup table. No project file; the regulation
  itself is the source of truth.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **shadcn/ui primitives** (`resources/js/components/ui/`): button, card, badge,
  dialog, dropdown-menu, input, select, separator, sidebar, skeleton, sonner,
  table-adjacent primitives, tooltip, etc. — the design-system foundation is
  already installed. The Admin user list and styleguide page compose from these.
- **`use-appearance` hook** (`resources/js/hooks/use-appearance.tsx`) +
  **`HandleAppearance` middleware** — light/dark/system theming already works
  and persists via cookie. Phase 1 tunes the *tokens* these drive, not the
  switching mechanism.
- **`cn()` helper** (`resources/js/lib/utils.ts`) — class merging for all
  component work.
- **`use-flash-toast` hook** + `sonner` — flash feedback for Admin actions
  (role changed, user deactivated).
- **Settings layout** (`resources/js/layouts/settings/`) and app layouts
  (`layouts/app/`) — patterns to mirror when building the `/admin` shell.

### Established Patterns
- **Validation:** `FormRequest` class per action; shared rules extracted into
  traits under `app/Concerns/`. Admin actions (role change, deactivate, delete)
  should each get a FormRequest.
- **Controllers:** Thin — resolve FormRequest, act, return `Inertia::render()`
  or redirect. Group by feature in a subdirectory (e.g. `Http/Controllers/Admin/`).
- **Routing:** Server-driven Inertia; `Inertia::render('admin/users', …)` maps
  to `resources/js/pages/admin/users.tsx`. Named routes + `route()` helper.
  Wayfinder regenerates `@/actions/` and `@/routes/` — never hand-edit.
- **Shared props:** `HandleInertiaRequests::share()` exposes the auth user
  globally — extend it to expose the user's role/permissions and locale so the
  frontend can gate nav and render the language switcher.
- **Migrations:** timestamped snake_case; create models via
  `php artisan make:model` with factories.
- **Testing:** Pest feature tests mirror `app/` structure; every change is
  programmatically tested (project rule).

### Integration Points
- **`User` model** — gains the spatie `HasRoles` trait, a SoftDeletes trait, and
  an account-status concept (active/deactivated). Currently the only model.
- **`HandleInertiaRequests` middleware** — the place to share role/permission
  and locale with every page.
- **Registration** (`app/Actions/Fortify/CreateNewUser.php`) — assign the
  default `User` role here on account creation.
- **`routes/web.php`** — new `/admin` route group behind an Admin permission
  gate; localization may add a route to switch language.
- **`database/seeders/DatabaseSeeder.php`** — seeds roles + permissions, the
  default Admin account, and the unit + allergen lookup tables.
- **`resources/css/`** — the CSS-variable token set lives here (Tailwind v4
  `@theme` / CSS custom properties).

</code_context>

<specifics>
## Specific Ideas

- The design must feel **professional but minimalistic and warm** — a credible
  tool for working chefs, not a casual hobby app. Warmth comes from a **whitish
  (warm off-white) background** and soft neutral tones, with a **very soft
  accent** rather than a bold color.
- `/admin` is intended as the long-term home for admin/moderation surfaces — the
  Phase 7 ingredient review queue should fit naturally beside the user list.

</specifics>

<deferred>
## Deferred Ideas

- **Full account-management surface** (bulk actions, role-filtered views, user
  detail pages, audit log of admin actions) — Phase 1 ships search + pagination
  + the three per-user operations only. Richer admin tooling is a later concern.
- **Granular per-feature permissions beyond Phase 1 needs** — only the
  permissions required by Phase 1 (and the obvious Phase 7 hook,
  `review-ingredients`) are seeded now; more permissions are added as later
  phases need them.

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-05-16*
