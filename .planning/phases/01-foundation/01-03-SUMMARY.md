---
phase: 01-foundation
plan: 03
subsystem: database
tags: [eloquent, migrations, seeders, allergens, units, eu-regulation]

# Dependency graph
requires:
  - phase: 01-foundation plan 01
    provides: DatabaseSeeder with RolesAndPermissionsSeeder already wired; test scaffolding including LookupTableSeedTest

provides:
  - Unit Eloquent model with name/symbol/type/base_factor columns
  - Allergen Eloquent model with name/slug/note columns
  - UnitSeeder with 12 measurement units (weight, volume, count) idempotent via firstOrCreate
  - AllergenSeeder with exactly 14 EU Regulation 1169/2011 Annex II allergens idempotent via firstOrCreate
  - DatabaseSeeder wired to call UnitSeeder and AllergenSeeder after RolesAndPermissionsSeeder

affects: [02-ingredients, 03-recipes-metrics]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - firstOrCreate idempotent seeder pattern (slug key for allergens, name key for units)
    - decimal:6 cast on base_factor for precise measurement conversion factors

key-files:
  created:
    - app/Models/Unit.php
    - app/Models/Allergen.php
    - database/migrations/2026_05_16_024902_create_units_table.php
    - database/migrations/2026_05_16_024914_create_allergens_table.php
    - database/seeders/UnitSeeder.php
    - database/seeders/AllergenSeeder.php
  modified:
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "Allergen slug used as firstOrCreate key (unique per EU regulation) rather than name — slug is stable canonical identifier"
  - "Unit name used as firstOrCreate key because it is unique-constrained in the migration"
  - "base_factor cast as decimal:6 to preserve conversion precision without floating-point drift"

patterns-established:
  - "Idempotent seeder: use firstOrCreate with a stable unique key (slug for allergens, name for units)"
  - "Lookup model: fillable array declared, no factory needed for fixed regulatory data"

requirements-completed: [I18N-02]

# Metrics
duration: 15min
completed: 2026-05-16
---

# Phase 01 Plan 03: Lookup Tables (Units + Allergens) Summary

**Unit and Allergen Eloquent models with migrations and idempotent seeders covering 12 measurement units and exactly the 14 EU Regulation 1169/2011 mandatory allergens**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-05-16T02:49:00Z
- **Completed:** 2026-05-16T03:04:00Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Unit model (`name`, `symbol`, `type`, `base_factor`) with `decimal:6` cast for precision
- Allergen model (`name`, `slug`, `note`) enforcing EU Regulation 1169/2011 Annex II (14 allergens — hard compliance constraint)
- Both migrations applied; UnitSeeder and AllergenSeeder use `firstOrCreate` for full idempotency
- DatabaseSeeder wired to call both seeders; `LookupTableSeed` tests green (2/2)

## Task Commits

1. **Task 1: Create Unit and Allergen models and migrations** - `b515fa2` (feat)
2. **Task 2: Write seeders and wire into DatabaseSeeder** - `03db6d8` (feat)

## Files Created/Modified

- `app/Models/Unit.php` - Unit lookup model, fillable with base_factor decimal:6 cast
- `app/Models/Allergen.php` - Allergen lookup model, fillable with name/slug/note
- `database/migrations/2026_05_16_024902_create_units_table.php` - Units table: name/symbol/type/base_factor(decimal 16,6)/unique(name)
- `database/migrations/2026_05_16_024914_create_allergens_table.php` - Allergens table: name/slug(unique)/note
- `database/seeders/UnitSeeder.php` - 12 units (4 weight, 5 volume, 3 count) via firstOrCreate(name)
- `database/seeders/AllergenSeeder.php` - 14 EU allergens via firstOrCreate(slug)
- `database/seeders/DatabaseSeeder.php` - Added UnitSeeder::class and AllergenSeeder::class calls

## Decisions Made

- Allergen `slug` chosen as `firstOrCreate` key over `name` — slug is the canonical stable identifier (URL-safe, no spaces); name could vary in capitalization
- Unit `name` chosen as `firstOrCreate` key — matches the `unique(['name'])` constraint in the migration
- `base_factor` cast to `decimal:6` to preserve conversion precision without floating-point drift in future Phase 3 unit conversion math

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Unit and Allergen lookup tables seeded and queryable app-wide; Phase 2 (ingredients) and Phase 3 (recipes/metrics) can consume these immediately
- `Unit::all()` and `Allergen::all()` are available to any feature that needs them
- No blockers

---
*Phase: 01-foundation*
*Completed: 2026-05-16*
