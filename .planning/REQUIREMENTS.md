# Requirements: twosides

**Defined:** 2026-05-16
**Core Value:** A chef can build a structured, versioned recipe and trust the professional metrics computed from its ingredients (nutrition, cost, yield, allergens).

## v1 Requirements

Requirements for the initial release. The MVP is the full product vision,
including the AI agent. Each maps to a roadmap phase.

### Access & Roles

- [x] **ACCESS-01**: User account is assigned one of three roles — User, Moderator, or Admin
- [x] **ACCESS-02**: Moderator can access the ingredient submission review queue
- [x] **ACCESS-03**: Admin can manage user accounts and assign roles
- [x] **ACCESS-04**: Each role's permitted actions are enforced consistently across the app

### Ingredient Library

- [x] **INGR-01**: User can search the official ingredient library by name
- [x] **INGR-02**: Official ingredient library is seeded from CIQUAL, USDA FDC, and Open Food Facts
- [x] **INGR-03**: Each ingredient stores nutrition values (calories, macros, sugars, sodium, fiber, micronutrients)
- [x] **INGR-04**: Each ingredient stores allergen information using the EU 14-allergen model
- [x] **INGR-05**: Each ingredient stores unit-conversion data (portion/density) for normalizing quantities to grams
- [x] **INGR-06**: Ingredient names are stored and displayed in multiple languages (Greek and English at minimum)
- [x] **INGR-07**: User can create a private ingredient when the official library lacks one
- [x] **INGR-08**: User can record a price for an ingredient with date and currency
- [ ] **INGR-09**: User can submit a private ingredient for inclusion in the official library
- [ ] **INGR-10**: Moderator can review a submitted ingredient and approve or reject it
- [ ] **INGR-11**: An approved submitted ingredient is promoted into the official library

### Recipes

- [ ] **RECIPE-01**: User can create a recipe with structured ingredient lines (quantity, unit, ingredient)
- [ ] **RECIPE-02**: User can add ordered preparation steps to a recipe
- [ ] **RECIPE-03**: An ingredient line can use any unit — weight, volume, or count
- [x] **RECIPE-04**: User can set recipe yield, portion size, time, difficulty, cuisine/category, and tags
- [ ] **RECIPE-05**: A recipe can include another recipe as a nested sub-recipe component
- [x] **RECIPE-06**: Circular sub-recipe references are detected and rejected
- [x] **RECIPE-07**: User can scale a recipe up or down and see quantities and metrics recompute
- [ ] **RECIPE-08**: User can adjust portion count while viewing a recipe without creating a new version
- [ ] **RECIPE-09**: User can duplicate a recipe to create a new editable copy
- [ ] **RECIPE-10**: User can attach a hero image and optional step images to a recipe
- [ ] **RECIPE-11**: User can write free-text chef notes on a recipe
- [ ] **RECIPE-12**: User can search and filter recipes by tag, cuisine, allergen, ingredient, difficulty, and time
- [x] **RECIPE-13**: An ingredient line can carry a prep action and a yield/loss percentage

### Versioning & Working Draft

- [x] **VERSION-01**: Every recipe keeps an immutable history of saved versions
- [x] **VERSION-02**: Edits accumulate in a mutable working draft, separate from saved versions
- [ ] **VERSION-03**: User can Save the working draft, committing it as a new version
- [x] **VERSION-04**: User can Recall to undo the last applied edit on the working draft
- [ ] **VERSION-05**: User can view and compare past versions of a recipe
- [ ] **VERSION-06**: A sub-recipe reference pins to a specific version of the component recipe

### Metrics Engine

- [x] **METRIC-01**: App computes nutrition per portion and per 100 g for a recipe
- [x] **METRIC-02**: App computes cost per portion and total recipe cost from ingredient prices
- [x] **METRIC-03**: App computes food cost % from cost and a user-entered selling price per portion
- [x] **METRIC-04**: App computes recipe yield and supports scaling calculations
- [x] **METRIC-05**: App computes cooking loss / shrinkage from per-ingredient yield percentages
- [x] **METRIC-06**: App computes baker's percentages and hydration ratio for baking recipes
- [x] **METRIC-07**: App computes calorie / nutrient density
- [x] **METRIC-08**: All metrics roll up correctly through nested sub-recipes
- [x] **METRIC-09**: All metric arithmetic uses exact decimal math with no floating-point drift
- [x] **METRIC-10**: App normalizes every ingredient line quantity to grams via the unit converter

### Allergens

- [x] **ALLG-01**: App derives a recipe's allergens from its ingredients using the EU 14-allergen model
- [x] **ALLG-02**: App distinguishes "contains" from "may contain" (precautionary) allergens
- [x] **ALLG-03**: Allergen information rolls up through nested sub-recipes, preserving both states

### Recipe Tests

- [ ] **TEST-01**: User can record a trial run against a specific recipe version
- [ ] **TEST-02**: User can record a structured experiment with a hypothesis and an outcome
- [ ] **TEST-03**: A test captures tasting notes, photos, and structured ratings
- [ ] **TEST-04**: A test records what changed versus what was expected

### AI Agent

- [ ] **AI-01**: User can chat with an AI agent attached to a recipe
- [ ] **AI-02**: The agent reads the recipe, chef notes, and test feedback as context
- [ ] **AI-03**: The agent can suggest tests/experiments and recipe improvements
- [ ] **AI-04**: User can accept an agent suggestion, applying the edit to the recipe's working draft
- [ ] **AI-05**: The agent can create a recipe variant as a working draft
- [ ] **AI-06**: The AI provider is configurable via a provider-agnostic adapter
- [ ] **AI-07**: Agent edits pass through the same validation as user edits before touching the draft

### Publishing & Public Library

- [ ] **PUB-01**: Recipes are private by default
- [ ] **PUB-02**: User can publish a recipe to the public library
- [ ] **PUB-03**: User can unpublish a previously published recipe
- [ ] **PUB-04**: Users can browse and search the public library of published recipes

### Localization

- [x] **I18N-01**: UI is translatable and the user can switch language (Greek and English at minimum)
- [x] **I18N-02**: Ingredient names display in the user's selected language

### Design System

- [x] **UI-01**: Interface is built with shadcn/ui components on Tailwind v4
- [x] **UI-02**: Interface follows a warm-minimal aesthetic consistently across light and dark themes
- [x] **UI-03**: Interface is responsive and usable on desktop, tablet, and mobile browsers

## v2 Requirements

Deferred to a future release. Tracked but not in the current roadmap.

### Nutrition Export

- **NUTR-01**: User can export an EU-format nutrition label for a recipe

### Cost

- **COST-01**: Ingredient cost data sourced beyond manual entry (supplier integration or regional benchmarks)

### Collaboration

- **COLLAB-01**: User can leave comments / collaborative annotations on a recipe

### Sustainability

- **SUST-01**: App computes a carbon-footprint metric per recipe

### Taxonomy

- **TAX-01**: Ingredients enriched with FoodEx2 classification codes

### Production

- **PROD-01**: User can export a printable prep / production sheet scaled to batch size

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Frozen-dessert balancing engine (PAC/POD/overrun) | Distinct professional specialization; post-MVP milestone. Ingredient schema reserves its fields to avoid a later migration. |
| Social platform (community feed, follows, likes, comments) | Deferred per PROJECT.md; needs moderation and community infrastructure. v1 ships the private/publish foundation. |
| External social sharing (Instagram, TikTok forwarding) | Platform-specific integrations; post-MVP. Public recipe pages stay share-friendly by design. |
| Meal planning / weekly menu scheduling | Significant separate scope; the recipe library is the prerequisite. |
| Shopping list generation | Depends on meal planning, which is deferred. |
| Inventory management (stock levels, purchase orders) | Separate domain; pushes the product into enterprise territory. |
| Supplier / vendor database with live pricing | Requires supplier integrations; v1 uses user-entered ingredient costs. |
| POS / sales system integration | Enterprise integration layer; out of scope for a greenfield product. |
| Real-time collaborative editing (multi-cursor) | The versioning + working-draft model covers the professional workflow without CRDT complexity. |
| Native mobile apps (iOS / Android) | Responsive web app is the platform; native doubles the surface area. |
| Recipe import via URL scraping | Low value for professional users who create from scratch. |
| HelTH (Hellenic Food Thesaurus) integration | No open license; blocked pending a data-sharing agreement with the Agricultural University of Athens. |
| FatSecret paid API | Paid tier needs a negotiated storage clause; revisit only if CIQUAL+USDA+OFF coverage proves insufficient. |
| Monetization / paid tiers | Not defined for v1; a future business decision. |

## Traceability

Maps requirements to roadmap phases. Populated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ACCESS-01 | Phase 1 | Complete |
| ACCESS-02 | Phase 1 | Complete |
| ACCESS-03 | Phase 1 | Complete |
| ACCESS-04 | Phase 1 | Complete |
| I18N-01 | Phase 1 | Complete |
| I18N-02 | Phase 1 | Complete |
| UI-01 | Phase 1 | Complete |
| UI-02 | Phase 1 | Complete |
| UI-03 | Phase 1 | Complete |
| INGR-01 | Phase 2 | Complete |
| INGR-02 | Phase 2 | Complete |
| INGR-03 | Phase 2 | Complete |
| INGR-04 | Phase 2 | Complete |
| INGR-05 | Phase 2 | Complete |
| INGR-06 | Phase 2 | Complete |
| INGR-07 | Phase 2 | Complete |
| INGR-08 | Phase 2 | Complete |
| RECIPE-01 | Phase 3 | Pending |
| RECIPE-02 | Phase 3 | Pending |
| RECIPE-03 | Phase 3 | Pending |
| RECIPE-04 | Phase 3 | Complete |
| RECIPE-05 | Phase 3 | Pending |
| RECIPE-06 | Phase 3 | Complete |
| RECIPE-07 | Phase 3 | Complete |
| RECIPE-08 | Phase 3 | Pending |
| RECIPE-09 | Phase 3 | Pending |
| RECIPE-10 | Phase 3 | Pending |
| RECIPE-11 | Phase 3 | Pending |
| RECIPE-12 | Phase 3 | Pending |
| RECIPE-13 | Phase 3 | Complete |
| VERSION-01 | Phase 3 | Complete |
| VERSION-02 | Phase 3 | Complete |
| VERSION-03 | Phase 3 | Pending |
| VERSION-04 | Phase 3 | Complete |
| VERSION-05 | Phase 3 | Pending |
| VERSION-06 | Phase 3 | Pending |
| METRIC-01 | Phase 3 | Complete |
| METRIC-02 | Phase 3 | Complete |
| METRIC-03 | Phase 3 | Complete |
| METRIC-04 | Phase 3 | Complete |
| METRIC-05 | Phase 3 | Complete |
| METRIC-06 | Phase 3 | Complete |
| METRIC-07 | Phase 3 | Complete |
| METRIC-08 | Phase 3 | Complete |
| METRIC-09 | Phase 3 | Complete |
| METRIC-10 | Phase 3 | Complete |
| ALLG-01 | Phase 3 | Complete |
| ALLG-02 | Phase 3 | Complete |
| ALLG-03 | Phase 3 | Complete |
| TEST-01 | Phase 4 | Pending |
| TEST-02 | Phase 4 | Pending |
| TEST-03 | Phase 4 | Pending |
| TEST-04 | Phase 4 | Pending |
| AI-01 | Phase 5 | Pending |
| AI-02 | Phase 5 | Pending |
| AI-03 | Phase 5 | Pending |
| AI-04 | Phase 5 | Pending |
| AI-05 | Phase 5 | Pending |
| AI-06 | Phase 5 | Pending |
| AI-07 | Phase 5 | Pending |
| PUB-01 | Phase 6 | Pending |
| PUB-02 | Phase 6 | Pending |
| PUB-03 | Phase 6 | Pending |
| PUB-04 | Phase 6 | Pending |
| INGR-09 | Phase 7 | Pending |
| INGR-10 | Phase 7 | Pending |
| INGR-11 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 67 total
- Mapped to phases: 67
- Unmapped: 0

---
*Requirements defined: 2026-05-16*
*Last updated: 2026-05-16 — traceability populated after roadmap creation*
