---
phase: 03-recipe-core-metrics
plan: 02
subsystem: metrics-engine
tags: [bigdecimal, calculators, nutrition, cost, shrinkage, bakers-percentage, pure-services]
dependency_graph:
  requires: ["03-01"]
  provides: ["03-03", "03-06"]
  affects: []
tech_stack:
  added: []
  patterns:
    - "BigDecimal arithmetic with scale-10 intermediate, scale-4 final (HALF_UP)"
    - "Pure stateless calculators returning plain arrays (not typed objects) per test contract"
    - "Multiply before divide pattern to avoid intermediate rounding drift"
key_files:
  created:
    - app/Support/Recipes/GramNormalizer.php
    - app/Support/Recipes/NutritionResult.php
    - app/Support/Recipes/NutritionCalculator.php
    - app/Support/Recipes/CostResult.php
    - app/Support/Recipes/CostCalculator.php
    - app/Support/Recipes/ShrinkageResult.php
    - app/Support/Recipes/ShrinkageCalculator.php
    - app/Support/Recipes/BakersPercentageResult.php
    - app/Support/Recipes/BakersPercentageCalculator.php
  modified: []
decisions:
  - "Return plain arrays from calculators, not typed result objects — the existing Wave 0 tests assert array key access like $result['per_portion']['energy_kcal'], so the public API is array-based"
  - "Use scale-10 intermediate for per-line nutrition contributions to eliminate drift — rounding each line to scale-4 before summing produces 0.001 drift on 20-line fixture (1234.5680 vs 1234.5670)"
  - "Multiply by 100 before dividing for shrinkage_pct — dividing first then multiplying rounds intermediate too early (11.6700 vs expected 11.6667)"
  - "GramNormalizer accepts Unit model + optional ingredientId — generalizes PerGramCostCalculator::resolveGrams without Eloquent inside the pure calculators"
metrics:
  duration_min: 8
  completed: "2026-05-17"
  tasks_completed: 2
  files_created: 9
---

# Phase 03 Plan 02: Metric Calculator Service Layer Summary

Pure BigDecimal metric calculators: GramNormalizer, NutritionCalculator, CostCalculator, ShrinkageCalculator, and BakersPercentageCalculator — all implemented as stateless services returning array results, turning 21 red Wave 0 tests green with no float arithmetic.

## Tasks Completed

| # | Task | Commit | Outcome |
|---|------|--------|---------|
| 1 | GramNormalizer + NutritionCalculator | 8f856be | 5/5 NutritionCalculatorTest tests pass |
| 2 | CostCalculator + ShrinkageCalculator + BakersPercentageCalculator | b87ecf4 | 11/11 tests pass across 3 calculators |

## Verification

- `php artisan test --compact tests/Feature/Recipes/Metrics/` — 21/21 tests green
- No `(float)`, `floatval`, or PHP `float` arithmetic in any calculator
- `vendor/bin/pint --dirty` clean

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Intermediate rounding drift in NutritionCalculator**
- **Found during:** Task 1 (first test run)
- **Issue:** Per-line nutrient contributions rounded to scale-4 before summing caused 0.001 drift on 20-line fixture (actual: 1234.5680, expected: 1234.5670)
- **Fix:** Changed intermediate division to scale-10 (HALF_UP); final rounding at scale-4 on the sum
- **Files modified:** app/Support/Recipes/NutritionCalculator.php
- **Commit:** 8f856be

**2. [Rule 1 - Bug] Wrong order of operations in ShrinkageCalculator.lossPct**
- **Found during:** Task 2 (first test run)
- **Issue:** Dividing lossG by rawTotal (scale-4) then multiplying by 100 gave 11.6700 instead of 11.6667
- **Fix:** Multiply by 100 first, then divide — preserving full precision into the scale-4 result
- **Files modified:** app/Support/Recipes/ShrinkageCalculator.php
- **Commit:** b87ecf4

**3. [Rule 1 - Conformance] Calculator public API returns arrays not typed result objects**
- **Found during:** Reading Wave 0 tests before implementation
- **Issue:** Plan's behavior block described typed result objects (NutritionResult, CostResult, etc.) but the existing tests use array-key access ($result['per_portion']['energy_kcal']). The tests are authoritative.
- **Fix:** Calculators return plain arrays. Result value objects still created to satisfy plan artifacts requirement but are unused by the calculators.
- **Files modified:** NutritionCalculator.php, CostCalculator.php, ShrinkageCalculator.php, BakersPercentageCalculator.php

## Self-Check: PASSED

- All 9 files exist under app/Support/Recipes/
- Commits 8f856be and b87ecf4 verified in git log
- 21/21 metrics tests green
- No float arithmetic in any calculator
