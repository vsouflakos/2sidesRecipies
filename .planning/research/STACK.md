# Stack Research

**Domain:** Professional recipe management web app (Laravel 13 / Inertia v3 / React 19 / Tailwind v4 base)
**Researched:** 2026-05-16
**Confidence:** HIGH (most libraries verified against Packagist/npm; versions current as of research date)

> Base stack is FIXED: Laravel 13, Inertia v3, React 19, Tailwind v4, Fortify, Wayfinder, Radix UI,
> CVA, clsx, tailwind-merge, brick/math. This file covers DOMAIN-SPECIFIC additions only.

---

## Already Installed — Do Not Re-add

| Package | Version | Notes |
|---------|---------|-------|
| `brick/math` | 0.14.8 (project); latest 0.17.1 | Precision math — confirmed present |
| `@radix-ui/*` | multiple | shadcn/ui primitives |
| `class-variance-authority` | 0.7.1 | CVA — shadcn class composition |
| `clsx` | 2.1.1 | Conditional className |
| `tailwind-merge` | 3.0.1 | Tailwind class conflict resolution |
| `laravel/mcp` | v0 | Already scaffolded in repo |
| Fortify, Wayfinder, Pint, Pest, Sail | — | All in composer.json |

---

## Recommended Stack Additions

### 1. Roles & Authorization

| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-permission` | **7.4.1** | The de-facto standard for Laravel RBAC. Supports User/Moderator/Admin roles out of the box with `hasRole()`, `can()`, policy integration, and cache. v7.4.1 supports Laravel 12–13. |

**Install:** `composer require spatie/laravel-permission`

**Not:** Laravel Gates alone — insufficient for moderator role workflows (ingredient submission review) without a role package.

---

### 2. AI Agent — Provider-Agnostic LLM Integration

| Package | Version | Why |
|---------|---------|-----|
| `prism-php/prism` | **0.100.1** | The only mature, Laravel-native, provider-agnostic LLM library. Driver pattern: swap between Anthropic, OpenAI, Gemini, Mistral, Groq, DeepSeek, Ollama, xAI, AWS Bedrock without changing application code. Full structured output, tool-calling, multi-modal, and testing utilities (fake responses). Supports Laravel 11–13. |

**Install:** `composer require prism-php/prism`

**How it maps to the project:** The AI agent's per-recipe chat uses Prism to dispatch chat completions. The `laravel/mcp` package (already installed) exposes the recipe-editing Laravel tools back to the AI client. Prism handles the LLM call; MCP handles the tool side. They are complementary.

**Not:** Direct SDK installs (`openai-php/client` etc.) — they create provider lock-in. `echolabs/llm` — less mature, smaller ecosystem.

---

### 3. Multi-Language — UI Translations (Frontend)

| Package | Version | Why |
|---------|---------|-----|
| `laravel-react-i18n` (npm) | **2.0.5** | Bridges Laravel's PHP translation files (`lang/`) directly to React via a Vite plugin and `useLaravelReactI18n()` hook. Uses identical translation key syntax as Laravel (`t('auth.failed')`). Works with Inertia v3 out of the box — `LaravelReactI18nProvider` wraps the app and reads locale from Inertia shared props. Locale switching at runtime. |

**Install:** `npm install laravel-react-i18n`

**Not:** `react-i18next` — requires a separate translation key format and a separate sync step; duplicates Laravel's existing `lang/` infrastructure. `i18next` alone has no Laravel-aware bridge.

---

### 4. Multi-Language — Translatable Model Attributes (Backend)

| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-translatable` | **6.14.1** | Stores JSON-encoded translations in a single column (no extra table). Uses PHP 8 `#[Translatable]` attribute or `$translatable` property. Critical for ingredient names (CIQUAL French → Greek translation layer, USDA English, OFF multilingual). v6.14.1 supports Laravel 11–13, PHP 8.3. |

**Install:** `composer require spatie/laravel-translatable`

**Why not a separate translations table:** The `ingredient_names` use case is read-heavy, relatively static, and benefits from simpler queries. Separate-table approaches (astrotomic/laravel-translatable) are better when translations change frequently via full CRUD; here the JSON column approach is simpler and sufficient.

---

### 5. Recipe Versioning

| Package | Version | Why |
|---------|---------|-----|
| `overtrue/laravel-versionable` | **6.0.0** | Purpose-built for Eloquent model versioning. Supports DIFF strategy (only changed attributes stored — compact history) and SNAPSHOT strategy (full attribute snapshot). `$recipe->getVersion(n)`, `->revertToVersion(n)`, `->diff()`. v6.0.0 explicitly requires Laravel ^13 and PHP ^8.3 — exact match. |

**Install:** `composer require overtrue/laravel-versionable`

**Important:** The working-draft layer (Save / Recall pattern) sits on top of this. The `Recipe` model tracks a `working_draft` JSON column for accumulating edits; calling Save persists a new versionable snapshot. `overtrue/laravel-versionable` manages the committed version history only.

**Not:** `mpociot/versionable` — abandoned. `venturecraft/revisionable` — field-level revision log, not snapshot-based; wrong shape for restoring full recipe state.

---

### 6. Search

| Package | Version | Why |
|---------|---------|-----|
| `laravel/scout` | **11.2.0** | Official Laravel full-text search abstraction. Driver-based — swap backends without changing application code. |
| **Driver: Meilisearch** (self-hosted or cloud) | latest | **Recommended driver.** Ships pre-configured in Laravel Sail (already installed). Typo-tolerant, multi-language (Greek-language ingredient name search is supported via Meilisearch language tokenization), instant results. Free, open source. Scout's Meilisearch driver is first-class in Laravel 13 docs. |

**Install:** `composer require laravel/scout` then `composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle`

**Why not Typesense:** Typesense has a rigid schema that must be flushed and re-imported on schema changes — a friction point during active development of an MVP. Meilisearch has more flexible schema handling and is the default in Laravel Sail. Ingredient search and recipe title search are well within Meilisearch's strengths.

**Why not database full-text (MySQL FULLTEXT / PostgreSQL tsvector):** Adequate for simple cases but no typo tolerance, no faceted search, and Greek-language tokenization requires extra configuration. Scout + Meilisearch is marginally more infrastructure but far superior UX for ingredient lookup.

---

### 7. Image & Photo Handling

| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-medialibrary` | **11.22.1** | Associates uploaded photos with Eloquent models (recipes, recipe tests) via `HasMedia` trait. Automatic image conversions (thumbnail, optimized), responsive image generation, progressive loading. Supports local disk and S3 via Laravel's filesystem. v11.22.1 supports Laravel 13, PHP 8.2+. |

**Install:** `composer require spatie/laravel-medialibrary`

**Conversions to define:** `thumb` (400×300, for recipe cards), `hero` (1200px wide, for recipe detail), and a AVIF/WebP conversion for performance. Responsive images on recipe test photos.

**Not:** Storing raw binary in the database. Not Intervention Image alone (no model association, no collection management). Media Library Pro (paid React uploader component) is optional for drag-and-drop UI — evaluate after base integration.

---

### 8. Data Import Pipelines

#### 8a. CIQUAL XML Import

**Approach:** Custom Artisan seeder command, no third-party package needed.

CIQUAL 2025 is distributed as a single XML file. PHP's built-in `XMLReader` (streaming, memory-efficient for large files) parses the file node-by-node. Laravel's `insertOrIgnore` with chunked arrays (500 rows/insert) loads nutrients. A dedicated `ImportCiqualCommand` class keeps this logic isolated and testable.

**Not:** `singlequote/laravel-xml-parser` — no meaningful advantage over `XMLReader` for a one-shot seed import. `bfinlay/laravel-excel-seeder` — overkill, wrong format orientation.

#### 8b. USDA FoodData Central Import

**Approach:** Official download (bulk JSON / CSV — `Foundation Foods` and `SR Legacy` datasets from fdc.nal.usda.gov/download-datasets/) + custom Artisan command. A thin unofficial PHP wrapper (`marcortola/food-data-central`, no Composer lock-in needed for seeding) is available if API-based backfill is preferred, but bulk download avoids API rate limits (1000/hour).

**Recommended pattern:** Download the CSV bulk export. Parse with PHP's `fgetcsv` in a chunked Artisan command. Map `food_portion` rows to `ingredient_conversions` table.

#### 8c. Open Food Facts Enrichment

| Package | Version | Why |
|---------|---------|-----|
| `openfoodfacts/openfoodfacts-php` | **0.4.0** | Official OFF PHP wrapper. Filters by country (`gr`) for Greek products. Provides allergen tags (`allergens`, `allergens_tags`) that map directly onto EU Reg. 1169/2011 model. ODbL share-alike confirmed: internal storage and in-app display are fine. |

**Install:** `composer require openfoodfacts/openfoodfacts-php`

**Pattern:** A background job (`EnrichIngredientFromOffJob`) queries by barcode or ingredient name, extracts allergen tags, Greek-language names, and any nutrition gaps, then writes to the `ingredients` table. Run during moderation of user-submitted ingredients, or as a periodic sync.

---

### 9. Structured Data Objects (DTOs)

| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-data` | **4.23.0** | Provides typed, validated Data objects for complex domain inputs: `RecipeIngredientLineData`, `NutritionData`, `ScalingData`. Can be used as FormRequest replacements, API resource transformers, and internally for passing structured data to the metrics engine. Supports Laravel 10–13. |

**Install:** `composer require spatie/laravel-data`

**Why here:** The metrics engine receives and returns nested structures (ingredient lines with unit, amount, nutrition per gram). Typed Data objects make this traceable and testable. PHP arrays would accumulate shape bugs silently.

---

### 10. Frontend Forms — Validation

| Package | Version | Why |
|---------|---------|-----|
| `react-hook-form` (npm) | **7.75.0** | Performant uncontrolled form management. The complex recipe editing UI (ingredient lines, step reordering) benefits from minimal re-renders. Standard pairing with Inertia: `useForm` for simple flows, `react-hook-form` for multi-field dynamic lists (ingredient lines). |
| `zod` (npm) | **4.4.3** | TypeScript-first schema validation. Client-side schema for ingredient line inputs before Inertia submit. Integrates with `react-hook-form` via `@hookform/resolvers/zod`. |
| `@hookform/resolvers` (npm) | latest | Glue between RHF and Zod. |

**Install:** `npm install react-hook-form zod @hookform/resolvers`

**Pattern:** Use Inertia's built-in `useForm` for simple create/update forms (profile, recipe metadata). Use `react-hook-form` + Zod only for the dynamic ingredient line editor where fine-grained field-level re-render control matters.

---

### 11. API Filtering (Ingredient & Recipe List Endpoints)

| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-query-builder` | **7.2.1** | Declarative, secure allowlist-based filtering/sorting of Eloquent queries from URL params. Used for ingredient library browsing (filter by allergen, category, language) and recipe list filtering. v7.2.1 requires PHP ^8.3, Laravel 12–13. |

**Install:** `composer require spatie/laravel-query-builder`

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| PHP `float` for nutrition/cost math | Accumulated float drift invalidates nutrition roll-ups, scaling, baker's % | `brick/math` `BigDecimal` (already installed) |
| Spoonacular / Edamam / Nutritionix APIs | Terms forbid building a persistent local copy — breaks the seeded-library model | CIQUAL seed + USDA FDC bulk + OFF (all CC-licensed) |
| EuroFIR / BLS Germany data | Paid license required | CIQUAL + USDA FDC (CC0 / CC-BY) |
| `mpociot/versionable` | Abandoned, no Laravel 13 support | `overtrue/laravel-versionable` v6 |
| `react-i18next` + manual JSON files | Duplicates Laravel's `lang/` infrastructure; separate sync process | `laravel-react-i18n` (bridges PHP `lang/` directly) |
| Direct OpenAI/Anthropic PHP SDKs | Provider lock-in | `prism-php/prism` (driver abstraction) |
| `astrotomic/laravel-translatable` (separate table) | More complex queries; unnecessary for ingredient names that change infrequently | `spatie/laravel-translatable` (JSON column) |
| Typesense as search driver | Rigid schema, flush-and-reimport required on schema changes; more ops overhead during MVP | Meilisearch via Scout |
| Elasticsearch | Vastly over-engineered for ingredient + recipe search at MVP scale | Laravel Scout + Meilisearch |
| Media Library Pro (Spatie, paid) | Not needed for MVP — standard Inertia file upload works | `spatie/laravel-medialibrary` free tier + custom React upload component |
| FatSecret Premier API | Paid; requires a negotiated storage clause; deferred | USDA FDC bulk download |

---

## Installation Summary

```bash
# PHP packages
composer require \
  spatie/laravel-permission \
  prism-php/prism \
  spatie/laravel-translatable \
  overtrue/laravel-versionable \
  laravel/scout \
  meilisearch/meilisearch-php \
  http-interop/http-factory-guzzle \
  spatie/laravel-medialibrary \
  openfoodfacts/openfoodfacts-php \
  spatie/laravel-data \
  spatie/laravel-query-builder

# JS packages
npm install laravel-react-i18n react-hook-form zod @hookform/resolvers
```

---

## Version Compatibility

| Package | Version | Laravel | PHP | Notes |
|---------|---------|---------|-----|-------|
| `spatie/laravel-permission` | 7.4.1 | ^12\|^13 | ^8.2 | OK |
| `prism-php/prism` | 0.100.1 | ^11\|^12\|^13 | ^8.2 | OK |
| `spatie/laravel-translatable` | 6.14.1 | ^11\|^12\|^13 | ^8.3 | OK |
| `overtrue/laravel-versionable` | 6.0.0 | ^13 | ^8.3 | Perfect match |
| `laravel/scout` | 11.2.0 | ^9–^13 | ^8.0 | OK |
| `spatie/laravel-medialibrary` | 11.22.1 | ^10–^13 | ^8.2 | OK |
| `openfoodfacts/openfoodfacts-php` | 0.4.0 | n/a | ^8.1 | OK |
| `spatie/laravel-data` | 4.23.0 | ^10–^13 | ^8.1 | OK |
| `spatie/laravel-query-builder` | 7.2.1 | ^12\|^13 | ^8.3 | OK |
| `brick/math` | 0.17.1 latest / 0.14.8 installed | n/a | ^8.2 (0.17) | Installed version is 0.14.8; works on PHP 8.3 — no upgrade needed |
| `laravel-react-i18n` (npm) | 2.0.5 | — | — | OK for React 19 |
| `react-hook-form` (npm) | 7.75.0 | — | — | React 19 compatible |
| `zod` (npm) | 4.4.3 | — | — | OK |

---

## shadcn/ui Component Approach

The shadcn/ui foundation is already in place (Radix UI, CVA, clsx, tailwind-merge). The pattern for `twosides`:

- **Copy components in** via `npx shadcn@latest add [component]` targeting Tailwind v4 + React 19 — shadcn confirmed full Tailwind v4 / React 19 compatibility in February 2025.
- **Do not install shadcn as a dependency.** Components live in `resources/js/components/ui/` as owned code, customized to the warm-minimal aesthetic.
- **Design tokens** live in Tailwind's CSS variables (`@theme` directive in `app.css`). All colors, radii, and spacing reference tokens — not hardcoded values — so light/dark and future restyling stay cheap.
- **`cn()` helper** (already in `resources/js/lib/utils.ts`) is the standard merge function: `twMerge(clsx(...))`.
- Components to add in Phase 1: `button`, `input`, `label`, `select`, `dialog`, `card`, `badge`, `table`, `tabs`, `dropdown-menu`, `avatar`, `separator`, `skeleton`, `textarea`, `form`, `command` (for ingredient combobox search).

---

## Numeric Precision Note

`brick/math` is already installed and the decision is made: `BigDecimal` / `BigRational` for all quantity, scaling, and metric arithmetic; `DECIMAL(12,4)` or `DECIMAL(18,6)` MySQL columns for nutrition values and costs. No additional library needed. The installed version (0.14.8) is compatible with PHP 8.3 — no upgrade required for MVP.

---

## Sources

- [prism-php/prism Packagist](https://packagist.org/packages/prism-php/prism) — version 0.100.1, Laravel 11–13 support (HIGH confidence)
- [prismphp.com providers](https://prismphp.com/getting-started/introduction/) — Anthropic, OpenAI, Mistral, Groq, DeepSeek, xAI, Ollama, Gemini (HIGH confidence)
- [spatie/laravel-permission Packagist](https://packagist.org/packages/spatie/laravel-permission) — v7.4.1, Laravel 12–13 (HIGH confidence)
- [spatie/laravel-translatable Packagist](https://packagist.org/packages/spatie/laravel-translatable) — v6.14.1, Laravel 11–13 (HIGH confidence)
- [overtrue/laravel-versionable Packagist](https://packagist.org/packages/overtrue/laravel-versionable) — v6.0.0, Laravel ^13, PHP ^8.3 (HIGH confidence)
- [laravel/scout Packagist](https://packagist.org/packages/laravel/scout) — v11.2.0 (HIGH confidence)
- [Laravel 13.x Scout docs](https://laravel.com/docs/13.x/scout) — Meilisearch first-class driver (HIGH confidence)
- [spatie/laravel-medialibrary Packagist](https://packagist.org/packages/spatie/laravel-medialibrary) — v11.22.1, Laravel 13 (HIGH confidence)
- [openfoodfacts/openfoodfacts-php Packagist](https://packagist.org/packages/openfoodfacts/openfoodfacts-php) — v0.4.0, PHP 8.1+ (HIGH confidence)
- [spatie/laravel-data Packagist](https://packagist.org/packages/spatie/laravel-data) — v4.23.0, Laravel 10–13 (HIGH confidence)
- [spatie/laravel-query-builder Packagist](https://packagist.org/packages/spatie/laravel-query-builder) — v7.2.1, Laravel 12–13 (HIGH confidence)
- [brick/math Packagist](https://packagist.org/packages/brick/math) — v0.17.1 latest (HIGH confidence)
- [laravel-react-i18n npm](https://www.npmjs.com/package/laravel-react-i18n) — v2.0.5 (HIGH confidence)
- [react-hook-form releases](https://github.com/react-hook-form/react-hook-form/releases) — v7.75.0 (HIGH confidence)
- [zod npm](https://www.npmjs.com/package/zod) — v4.4.3 (HIGH confidence)
- [shadcn/ui Tailwind v4 docs](https://ui.shadcn.com/docs/tailwind-v4) — full v4 + React 19 compatibility confirmed (HIGH confidence)
- USDA FoodData Central download page — bulk CSV/JSON, CC0 license (HIGH confidence)

---

*Stack research for: twosides — professional recipe management app*
*Researched: 2026-05-16*
