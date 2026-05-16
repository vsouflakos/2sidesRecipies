# Roadmap: twosides

## Overview

Build a professional recipe management platform from the existing Laravel 13 React starter kit outward. The sequence follows hard domain dependencies: shared lookup tables and roles first, then the ingredient library that every recipe needs, then recipe core and the metrics engine together (they are inseparable by design), then recipe tests, then the AI agent that consumes test feedback, then publishing, and finally the moderation workflow that promotes user-contributed ingredients into the official library.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Foundation** - Role system, unit/allergen lookup tables, design system, and localization scaffolding
- [ ] **Phase 2: Ingredient Library** - Official ingredient library with CIQUAL/USDA/OFF import pipeline and private ingredient creation
- [ ] **Phase 3: Recipe Core & Metrics** - Structured recipes, versioning/draft layer, nested sub-recipes, and the full metrics engine
- [ ] **Phase 4: Recipe Tests** - Trial run and structured experiment recording against recipe versions
- [ ] **Phase 5: AI Agent** - Per-recipe conversational AI agent with scoped draft-editing tools
- [ ] **Phase 6: Publishing & Public Library** - Publish/unpublish recipes and browse the public library
- [ ] **Phase 7: Ingredient Moderation** - User ingredient submission and moderator review/promote workflow

## Phase Details

### Phase 1: Foundation
**Goal**: The shared infrastructure every other phase depends on exists and is verified — role enforcement, lookup tables, localization, and a consistent design system
**Depends on**: Nothing (first phase)
**Requirements**: ACCESS-01, ACCESS-02, ACCESS-03, ACCESS-04, I18N-01, I18N-02, UI-01, UI-02, UI-03
**Success Criteria** (what must be TRUE):
  1. A registered user has a role (User, Moderator, or Admin) and role-gated routes return 403 for unauthorized roles
  2. The unit lookup table and allergen lookup table are seeded and available to the rest of the app
  3. A user can switch the UI language between Greek and English and all translatable strings update
  4. Every page renders correctly in light and dark themes on desktop, tablet, and mobile using shadcn/ui components with the warm-minimal aesthetic
**Plans**: TBD

### Phase 2: Ingredient Library
**Goal**: Users can search and use a rich official ingredient library, and can create private ingredients when something is missing
**Depends on**: Phase 1
**Requirements**: INGR-01, INGR-02, INGR-03, INGR-04, INGR-05, INGR-06, INGR-07, INGR-08
**Success Criteria** (what must be TRUE):
  1. User can search the official ingredient library by name and get results in Greek and English
  2. Ingredients returned show nutrition values (calories, macros, micronutrients), allergen flags, and unit-conversion data
  3. An Artisan import command can be run for each of CIQUAL, USDA FDC, and Open Food Facts independently and idempotently
  4. User can create a private ingredient with nutrition, allergen, and conversion data that only they can see
  5. User can record a price for any ingredient (private or official) with date and currency
**Plans**: TBD

### Phase 3: Recipe Core & Metrics
**Goal**: A chef can build a fully structured, versioned recipe and trust the computed professional metrics — nutrition, cost, yield, allergens, and baker's percentages — including correct roll-up through nested sub-recipes
**Depends on**: Phase 2
**Requirements**: RECIPE-01, RECIPE-02, RECIPE-03, RECIPE-04, RECIPE-05, RECIPE-06, RECIPE-07, RECIPE-08, RECIPE-09, RECIPE-10, RECIPE-11, RECIPE-12, RECIPE-13, VERSION-01, VERSION-02, VERSION-03, VERSION-04, VERSION-05, VERSION-06, METRIC-01, METRIC-02, METRIC-03, METRIC-04, METRIC-05, METRIC-06, METRIC-07, METRIC-08, METRIC-09, METRIC-10, ALLG-01, ALLG-02, ALLG-03
**Success Criteria** (what must be TRUE):
  1. User can create a recipe with ingredient lines (any unit — weight, volume, or count), ordered preparation steps, yield, portion size, time, difficulty, cuisine, tags, hero image, step images, and chef notes
  2. A recipe can include another recipe as a sub-recipe component pinned to a specific version; circular references are rejected with a clear error
  3. Every edit accumulates in a working draft; Save commits an immutable version; Recall undoes the last edit; the user can view and compare past versions
  4. Scaling a recipe or adjusting portion count instantly recomputes all quantities and metrics on screen with no floating-point drift
  5. The metrics panel shows nutrition per portion and per 100 g, cost per portion, food cost %, cooking loss/shrinkage, baker's percentages, and allergens — all rolled up correctly through nested sub-recipes
  6. User can search and filter the recipe list by tag, cuisine, allergen, ingredient, difficulty, and time
**Plans**: TBD

### Phase 4: Recipe Tests
**Goal**: Users can record and review structured trial runs and experiments against specific recipe versions
**Depends on**: Phase 3
**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04
**Success Criteria** (what must be TRUE):
  1. User can record a trial run against a specific recipe version with tasting notes, photos, and structured ratings
  2. User can record a structured experiment with a hypothesis, an outcome, and what changed versus what was expected
  3. Test records are linked to the exact recipe version they were run against and are visible on the recipe detail page
**Plans**: TBD

### Phase 5: AI Agent
**Goal**: Users can have a conversational AI session attached to a recipe; the agent can read the recipe and test feedback, suggest improvements, and apply accepted edits directly to the working draft
**Depends on**: Phase 4
**Requirements**: AI-01, AI-02, AI-03, AI-04, AI-05, AI-06, AI-07
**Success Criteria** (what must be TRUE):
  1. User can open a chat panel on a recipe and send messages to an AI agent that has read the recipe's current draft, chef notes, and all test feedback
  2. The agent suggests tests or recipe improvements in natural language and the user can accept a suggestion, which applies the edit to the working draft through the same validation path as a manual edit
  3. The agent can create a recipe variant (e.g. ingredient swaps) as a new working draft the user can review
  4. The AI provider can be changed by updating config without touching agent code
**Plans**: TBD

### Phase 6: Publishing & Public Library
**Goal**: Users can publish their recipes to a public library and browse other chefs' published recipes
**Depends on**: Phase 3
**Requirements**: PUB-01, PUB-02, PUB-03, PUB-04
**Success Criteria** (what must be TRUE):
  1. Recipes default to private and are not visible to other users until explicitly published
  2. User can publish a recipe to the public library and unpublish it at any time
  3. Any user can browse and search the public library of published recipes by name, tag, cuisine, allergen, difficulty, and time
**Plans**: TBD

### Phase 7: Ingredient Moderation
**Goal**: Users can submit private ingredients for inclusion in the official library, and moderators can review, approve, or reject submissions
**Depends on**: Phase 2
**Requirements**: INGR-09, INGR-10, INGR-11
**Success Criteria** (what must be TRUE):
  1. User can submit a private ingredient for review; its visibility changes to "submitted" and it remains usable by the submitting user
  2. A moderator can open the review queue, inspect a submitted ingredient's data, and approve or reject it with notes
  3. An approved submission is promoted to the official library and becomes visible to all users; a rejected submission reverts to private
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7
Note: Phase 6 depends on Phase 3 (not 5), so Phases 6 and 7 may be worked after Phase 3 completes and in parallel with Phases 4 and 5 if desired.

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 0/TBD | Not started | - |
| 2. Ingredient Library | 0/TBD | Not started | - |
| 3. Recipe Core & Metrics | 0/TBD | Not started | - |
| 4. Recipe Tests | 0/TBD | Not started | - |
| 5. AI Agent | 0/TBD | Not started | - |
| 6. Publishing & Public Library | 0/TBD | Not started | - |
| 7. Ingredient Moderation | 0/TBD | Not started | - |
