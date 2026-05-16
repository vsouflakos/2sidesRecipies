---
phase: 02-ingredient-library
plan: "06"
subsystem: ingredient-pricing
tags: [pricing, brick-math, per-gram-cost, inertia-form, tdd]
dependency_graph:
  requires: [02-01, 02-05]
  provides: [per-user-price-recording, per-gram-cost-computation]
  affects: [phase-3-cost-metrics]
tech_stack:
  added: []
  patterns:
    - brick/math BigDecimal for all decimal price arithmetic (no PHP floats)
    - Inertia useForm with partial reload (only ingredient prop) on price submit
    - withValidator() after-hook in FormRequest for cross-field business rule (non-weight unit conversion check)
    - PerGramCostCalculator plain class injected/newed in controller
key_files:
  created:
    - app/Support/Ingredients/PerGramCostCalculator.php
    - app/Http/Controllers/Ingredients/IngredientPriceController.php
    - app/Http/Requests/Ingredients/StorePriceRequest.php
    - resources/js/components/ingredients/price-form.tsx
    - resources/js/components/ingredients/price-history.tsx
  modified:
    - routes/web.php
    - tests/Feature/Ingredients/IngredientPriceTest.php
    - resources/js/pages/ingredients/show.tsx
    - resources/js/types/ingredient.ts
    - app/Http/Resources/IngredientDetailResource.php
    - lang/en/app.php
    - lang/el/app.php
decisions:
  - PerGramCostCalculator newed directly in controller (not injected) — simple calculator with no state or swappable dependencies; injection adds no value
  - withValidator() after-hook used (not a custom Rule class) — the check is tightly coupled to the request context (needs route ingredient); keeps all validation logic in one place
  - prices.unit normalized to {name, symbol} in IngredientDetailResource — avoids leaking Unit model columns to the frontend; consistent with conversions pattern
  - recorded_at serialized as toDateString() in the resource — prices recorded_at is a date (not datetime), toDateString() gives YYYY-MM-DD without timezone drift
metrics:
  duration_min: 13
  completed_date: "2026-05-16"
  tasks_completed: 2
  files_changed: 12
---

# Phase 2 Plan 06: Ingredient Price Recording Summary

Per-user ingredient pricing with brick/math precision: PerGramCostCalculator, StorePriceRequest, IngredientPriceController, and a Prices tab UI (PriceForm + PriceHistory) on the detail page.

## Tasks Completed

| # | Task | Commit | Status |
|---|------|--------|--------|
| 1 | PerGramCostCalculator, StorePriceRequest, price controller, route, tests (TDD) | 3181733 | Done |
| 2 | Prices tab, recording form, history table on detail page | 0343912 | Done |

## What Was Built

**Task 1 — Backend (TDD: RED then GREEN)**

- `PerGramCostCalculator` — resolves grams from weight unit (base_factor) or non-weight unit (ingredient_conversions lookup), then divides amount/grams to 8 decimal places with `RoundingMode::HALF_UP`. Zero PHP floats.
- `StorePriceRequest` — validates amount/quantity (positive numeric), unit_id (exists), currency (3-char string), recorded_at (date, before_or_equal:today), notes (optional 500-char). `withValidator()` after-hook checks non-weight units have an ingredient conversion row, adding a `unit_id` error with `app.ingredients.price_no_conversion` message if missing.
- `IngredientPriceController::store` — visibility guard for private ingredients, resolves Unit, computes per_gram_cost via calculator, wraps IngredientPrice creation in `DB::transaction()`, returns `back()->with('success')`.
- Route: `POST ingredients/{ingredient}/prices` → `ingredients.prices.store`
- 8 tests covering: basic price recording, per-gram computation (€4.20/500g = 0.0084), non-weight unit with conversion, validation rejection for missing conversion, per-user privacy on official ingredients, full dated history ordering, amount/quantity positive validation, future date rejection.
- Added `price_recorded` and `price_no_conversion` lang strings to EN and EL.

**Task 2 — Frontend**

- `PriceForm` — Inertia `useForm` form with 6 fields (amount, quantity, unit combobox, currency, date, notes). Unit combobox uses shadcn Command grouped by weight/volume/count. On success: `reset()`, sonner toast, Inertia partial reload `only: ['ingredient']`. Validation errors render inline.
- `PriceHistory` — shadcn Table with Date/Amount/Unit/Currency/Per-gram cost columns. Most-recent row bold (`font-medium`). Empty state renders `prices_empty` copy string.
- `show.tsx` — 4th "Prices" tab added; `units` prop destructured; `<PriceForm>` and `<PriceHistory>` rendered in `<TabsContent value="prices">`.
- `IngredientDetailResource` — prices.unit normalized to `{name, symbol}`, `recorded_at` serialized as `toDateString()`.
- `ingredient.ts` — `IngredientPrice` and `PriceFormData` interfaces added; `IngredientDetail.prices` typed as `IngredientPrice[]`.

## Verification

- `php artisan test --compact --filter=IngredientPriceTest` — 8 tests PASSED
- `php artisan test --compact` (full suite) — 115 tests PASSED
- `npm run build` — exits 0
- `php artisan route:list --path=ingredients` — `ingredients.prices.store` (POST) present
- No PHP floats in PerGramCostCalculator
- `before_or_equal:today` in StorePriceRequest

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Normalized prices.unit in IngredientDetailResource**
- **Found during:** Task 2
- **Issue:** Resource passed `$p->unit` directly (full Unit model serialization), while the plan specified `unit: { name, symbol }` — inconsistent with conversions pattern and leaks internal columns.
- **Fix:** Replaced with `$p->unit ? ['name' => $p->unit->name, 'symbol' => $p->unit->symbol] : null`
- **Files modified:** `app/Http/Resources/IngredientDetailResource.php`
- **Commit:** 0343912

**2. [Rule 1 - Bug] Added `toDateString()` for recorded_at in resource**
- **Found during:** Task 2
- **Issue:** Without explicit serialization, the Carbon date cast returns a full ISO datetime string which the history table shows with timestamps. Price dates are date-only.
- **Fix:** `$p->recorded_at?->toDateString()` for clean YYYY-MM-DD output
- **Files modified:** `app/Http/Resources/IngredientDetailResource.php`
- **Commit:** 0343912

## Self-Check: PASSED

All key files verified present. Both task commits verified in git log.
