# Feature Research

**Domain:** Professional recipe management platform (chef-first, amateur-friendly)
**Researched:** 2026-05-16
**Confidence:** HIGH (cross-verified against meez, Apicbase, ChefTec, and academic/industry sources)

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features that users assume exist. Missing these = product feels incomplete or amateur.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Recipe CRUD with structured ingredient lines | Every recipe platform has this; baseline expectation | MEDIUM | Ingredient lines need quantity + unit + ingredient reference; free-text is insufficient for pros |
| Recipe scaling (batch size up/down) | Professional cooking requires batch adjustment; home cooks expect "serves X" | MEDIUM | All quantities, costs, and nutrition must recompute proportionally; unit system must handle it cleanly |
| Flexible unit entry per ingredient line | Pros measure flour in grams, milk in litres, eggs by count — a single unit system breaks real recipes | HIGH | Requires the custom converter service; ingredient-specific volume/count→weight conversion is the hard part |
| Recipe search and filtering | Users cannot manage a library without search; filtering by dietary, cuisine, or allergen is assumed | MEDIUM | Filter axes: dietary tag, allergen, cuisine, ingredient, author, difficulty, time |
| Allergen flagging on recipes | EU Regulation 1169/2011 compliance is a legal requirement for food businesses; chefs need it for menus | MEDIUM | Must follow the 14-allergen EU model with "contains" / "may contain" distinction; rolls up through sub-recipes |
| Nutritional data per recipe and portion | Consumers and professionals both expect calorie/macro breakdowns; menu labelling regulations reinforce this | HIGH | Requires the seeded ingredient library with nutrition values; must roll up through nested components |
| Cost per portion / food cost % | Food cost % is how restaurants survive; any professional tool without it is not taken seriously | HIGH | Requires ingredient cost data (user-entered in v1); cost rolls up through sub-recipes |
| Yield / portion size management | Chefs cook for variable guest counts; yield and portion size are foundational to professional planning | MEDIUM | Closely coupled to scaling; cooking loss / shrinkage is the professional extension |
| Ingredient library with search | Without a pre-populated ingredient database, data entry is painful and nutritional accuracy impossible | HIGH | Seed from CIQUAL + USDA FDC + Open Food Facts; private ingredients and submission workflow extend it |
| Private recipe storage (not public by default) | Chefs guard IP; a platform that exposes recipes without explicit consent is a non-starter | LOW | Privacy-by-default with explicit publish action |
| Recipe organization (collections/tags) | A library of 50+ recipes needs organization; professionals have hundreds | MEDIUM | Tags, categories, cuisine type; sorting by date, name, cost, difficulty |
| Preparation steps with order | Recipe = ingredients + method; steps are as fundamental as ingredient lines | LOW | Ordered list of text steps; rich-text or markdown for formatting |
| Time estimates (prep, cook, total) | Scheduling kitchen production requires time data; even home cooks filter by time | LOW | User-entered; total auto-computed from prep + cook |
| Difficulty rating | Helps amateur users self-select; part of recipe metadata on any professional platform | LOW | Simple scale (1–5 or label-based) |
| Responsive, works on mobile browser | Chefs reference recipes on tablets or phones during service; desktop-only is a UX failure | MEDIUM | Responsive layout; not native app, but must be usable with messy hands on a tablet |
| Light and dark theme | Industry standard for any modern tool; especially relevant in kitchen environments with varied lighting | LOW | Already scaffolded in the starter kit |

### Differentiators (Competitive Advantage)

Features that set twosides apart. The project's core value lives here.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Recipe versioning with full history | No mainstream consumer recipe app does this; professional product development demands it; the AI agent requires a working draft layer to land edits safely | HIGH | Immutable version history + mutable working draft; Save commits, Recall undoes last applied edit; AI edits land in the draft, not directly in a version |
| Nested sub-recipes with metric roll-up | Stock → sauce → dish is the real professional structure; competitors (meez, Apicbase) support it but most consumer apps do not; correct roll-up of cost/nutrition/allergens through the tree is technically non-trivial | HIGH | Requires recursive metric computation; cycles must be detected; `brick/math` precision critical here |
| Working draft layer (Save / Recall) | Unique undo model tailored to recipe development workflow; ties AI edits to a safe, reversible staging area | MEDIUM | Three-layer state model: history / working draft / current active version; Recall = step-back, Save = commit |
| Integrated AI agent per recipe | The AI reads the recipe, notes, and test feedback, proposes improvements, and can edit the recipe directly via Laravel tools — closing the loop between AI suggestion and recipe mutation | HIGH | Provider-agnostic adapter; Laravel MCP tooling; agent actions land in the working draft; the user approves, recalls, or saves |
| Structured recipe testing (trials & experiments) | Most recipe platforms are static; none mainstream support structured kitchen trials with hypothesis → outcome recording; this is what separates recipe-as-data from recipe-as-text | HIGH | Trial runs (free-form) and structured experiments (A/B-style hypothesis + outcome); captures tasting notes, photos, structured ratings, delta-from-expected |
| Baker's percentages and hydration ratio | Professional pastry/baking metric; meez covers it but most platforms do not; integrated rather than separate calculator | MEDIUM | Computed from ingredient lines; flour baseline auto-detected or user-designated; requires baking-category awareness on ingredients |
| Cooking loss / shrinkage tracking | Food cost accuracy demands it; raw weight vs. finished weight is a professional-grade distinction that most consumer apps ignore | MEDIUM | Per-ingredient yield % (e.g. chicken breast loses 20% in roasting); propagates into cost and nutrition metrics |
| Official + user-extendable ingredient library with moderation | A curated, legally-sourced seeded library (CIQUAL + USDA + OFF) that users can extend and submit to, with moderator review — unusual combination for a product in this space | HIGH | Three-tier model: official (seeded), user-private, user-submitted-pending-moderation; multi-language names from day one |
| Multi-language ingredient names (Europe-first) | Greek market focus; international professional ingredients have local-language names; CIQUAL is French, USDA is English — a Greek-facing product needs a translation layer | MEDIUM | Language-tagged name variants per ingredient; UI locale-aware display; starts with Greek + English |
| EU allergen compliance by construction | 14-allergen model baked into the data model from day one, not bolted on; "contains" / "may contain" distinction; rolls up through sub-recipes | MEDIUM | Legal requirement for EU food businesses; most competitors support it but it must be correct, not approximate |
| Calorie / nutrient density metrics | Professional nutrition insight beyond macros; useful for dietary design | LOW | Derived from existing nutrition + yield data |
| Food cost % with selling price tracking | Links ingredient cost through to profitability; food cost % is the restaurant industry KPI | MEDIUM | Requires user-entered selling price per portion; computes margin; open question: cost data source beyond user entry |
| AI-suggested experiments | The agent proactively proposes structured tests based on recipe analysis and past test feedback — unique workflow not seen in existing tools | HIGH | Depends on: recipe data, test history, AI reasoning; agent must understand the experiment model |

### Anti-Features (Deliberately Excluded from v1)

Features to explicitly not build in v1, with rationale.

| Feature | Why Requested | Why Problematic / Deferred | Alternative / Plan |
|---------|---------------|----------------------------|--------------------|
| Inventory management (stock levels, purchase orders) | Restaurants need it; competitors (Apicbase, ChefTec) bundle it | Out of scope for the recipe-as-data vision; a complex separate domain; building it in v1 would fragment focus and delay the core | Deferred to a post-MVP milestone; ingredient cost data stays user-entered in v1 |
| Supplier / vendor database with live pricing | Ingredient cost accuracy requires it for restaurants | Requires supplier integrations, invoice scanning, or API connections — a separate product; live pricing varies by region and contract | User-entered ingredient costs in v1; revisit supplier integration in a future milestone |
| Meal planning / weekly menu scheduling | Home cooks and caterers want it | Significant scope addition; requires calendar, production planning, shopping list generation — all separate features; dilutes the recipe-as-data focus | Explicitly deferred; foundation in recipes and library is the prerequisite |
| Shopping list generation | Naturally follows meal planning; users ask for it | Depends on meal planning feature which is deferred; standalone shopping list from a single recipe is marginal value | Deferred alongside meal planning |
| POS / sales system integration | Restaurants want cost linked to sales data | Enterprise integration layer; out of scope for a greenfield product | Future milestone |
| Nutrition label printing (FDA/EU compliant print-ready format) | Food businesses need it for packaging | Regulatory label formatting (FDA Nutrition Facts, EU format) is a separate specialization; the nutrition data is in scope but the label-generation and print workflow is not | Nutrition data is computed; label printing can be a later export feature |
| Real-time team collaboration (live multi-cursor editing) | Enterprise restaurant groups use it | Significant engineering complexity (CRDTs or OT); the versioning + draft model already provides safe multi-step editing without real-time sync | Role-based permissions + versioning covers the professional workflow without CRDT complexity |
| Carbon footprint / sustainability scoring | Demand growing; Apicbase offers it | Requires ingredient-level CO2 data (a separate dataset to source, license, and maintain); not part of MVP value proposition | Flag as a future enrichment layer for the ingredient model |
| Social platform features (community feed, follows, likes, comments) | Discovery and community are valuable | Explicitly deferred in PROJECT.md; social mechanics would require content moderation, spam control, and community management at launch | The private/publish foundation in v1 is the prerequisite; social features are a post-MVP milestone |
| External social sharing (Instagram, TikTok forwarding) | Chefs want to share their work externally | Explicitly out of scope per PROJECT.md; platform-specific integrations and content formatting for social media are a separate concern | Design keeps public recipe pages clean and share-friendly for when this ships |
| Native mobile app (iOS / Android) | Mobile-first users expect native apps | Explicitly out of scope per PROJECT.md; responsive web app is the platform; native would double the surface area | Responsive web first; native is a future decision |
| Frozen-dessert balancing engine (PAC/POD/overrun for gelato/ice cream/sorbet) | Distinct professional specialization with its own science | Explicitly deferred per PROJECT.md; ingredient schema reserves the fields so it ships without a migration | Post-MVP milestone; schema reserves `pac_coefficient`, `pod_coefficient`, `de_value`, etc. |
| HelTH (Hellenic Food Thesaurus) integration | Greek market coverage | No open license; blocked pending a data-sharing agreement with Agricultural University of Athens | Do not block development; revisit if the AUA agreement materializes |
| FatSecret paid API | Best global coverage for world cuisines | Paid tier requires negotiated storage clause; deferred until coverage gaps are confirmed insufficient | Revisit if CIQUAL + USDA + OFF proves insufficient for world-cuisine ingredients |
| Monetization / paid tiers | Users expect to know pricing | Not defined for v1 per PROJECT.md | Future business decision |

---

## Feature Dependencies

```
Ingredient Library (official seed)
    └──required by──> Ingredient lines in recipes
                          └──required by──> Metrics engine (nutrition, cost, allergens)
                                                └──required by──> Sub-recipe metric roll-up
                                                └──required by──> Baker's percentages
                                                └──required by──> Cooking loss / shrinkage
                                                └──required by──> Food cost %

Recipe versioning (history + working draft)
    └──required by──> AI agent (edits land in working draft)
    └──required by──> Save / Recall workflow

Recipe tests (trials & experiments)
    └──enhances──> AI agent (agent reads test feedback)
    └──requires──> Recipes (tests are attached to recipe versions)

User-private ingredients
    └──required by──> Ingredient submission workflow
                          └──requires──> Moderator role

Structured recipe (ingredient lines + steps + yield)
    └──required by──> Recipe scaling
    └──required by──> Metrics engine
    └──required by──> Recipe tests
    └──required by──> AI agent (reads recipe structure)

AI agent
    └──requires──> Recipe versioning (working draft target)
    └──enhanced by──> Recipe tests (feedback context)
    └──requires──> Ingredient library (nutritional awareness)

Public library (publish)
    └──requires──> Recipes (private by default)
    └──foundation for──> Social platform (post-MVP)
```

### Dependency Notes

- **Ingredient library is foundational**: Nutrition, allergen, cost, and unit conversion all depend on it. The seeder must run before any metric can be computed.
- **Unit conversion precedes metrics**: Without normalizing ingredient lines to grams, no metric (nutrition, cost, baker's %) can be calculated accurately.
- **Versioning is required before AI edits**: The working draft layer is the safe landing zone for AI mutations. Building the AI agent before versioning is architecturally dangerous.
- **Recipe tests enhance the AI but do not block it**: The agent can function with just the recipe; test feedback is additive context. However, building tests before AI makes the agent more useful from day one.
- **Moderator role enables the submission workflow**: Private ingredient submission to the official library requires a moderator to review. This requires the role/permissions system to be in place.
- **Nested sub-recipes require recursive metric computation**: This must be solved once at the metrics engine level; it is not an add-on.

---

## MVP Definition

### Launch With (v1)

The full vision is the v1 scope per PROJECT.md. Features below are the agreed MVP.

- [x] **Roles & permissions** (User / Moderator / Admin) — gates the submission workflow and public library
- [x] **Official ingredient library** — seeded from CIQUAL 2025 + USDA FDC backfill + Open Food Facts (Greek products, allergen tags, Greek names); multi-language names
- [x] **User private ingredients** — when the official library lacks an ingredient
- [x] **Ingredient submission workflow** — user submits → moderator reviews → promoted to official
- [x] **Structured recipes** — ingredient lines (flexible units), ordered preparation steps, yield, metadata
- [x] **Nested sub-recipes** — a recipe usable as a component of another with full metric roll-up
- [x] **Unit conversion** — custom converter service normalizing to grams via USDA portion data + override table
- [x] **Recipe versioning** — full immutable version history
- [x] **Working draft layer** — Save commits, Recall undoes the last applied edit
- [x] **Metrics engine** — nutrition (per portion + per 100 g), cost per portion, food cost %, yield & scaling, allergens / dietary tags, cooking loss / shrinkage, baker's percentages, hydration ratio, time & difficulty
- [x] **EU allergen compliance** — 14 allergens per EU Reg. 1169/2011; contains / may contain; rolls up through sub-recipes
- [x] **Recipe tests** — trial runs and structured experiments; tasting notes, photos, structured ratings, delta-from-expected
- [x] **AI agent** — per-recipe chat; reads recipe + notes + test feedback; suggests experiments / improvements; applies edits to working draft; creates variants; provider-agnostic adapter
- [x] **Recipe visibility** — private by default; user can publish to a public library
- [x] **Recipe organization** — tags, categories, search, filter
- [x] **Multi-language UI and ingredient names** — translatable from day one; Greek + English priority
- [x] **Design system** — shadcn/ui on Tailwind v4; warm-minimal aesthetic; light + dark themes

### Add After Validation (v1.x)

Features to add once core is working and validated by users.

- [ ] **Nutrition label export (EU format)** — add when regulatory demand from food business users is confirmed; data is already computed
- [ ] **Cost data beyond manual entry** — supplier integration or regional price benchmarks; trigger: user feedback that manual cost entry is a pain point
- [ ] **Recipe comments / chef notes** — collaborative annotations without full real-time sync; trigger: team use cases emerge
- [ ] **Carbon footprint metric** — requires sourcing CO2-per-ingredient dataset; trigger: sustainability becomes a user request
- [ ] **Ingredient taxonomy enrichment** — FoodEx2 codes populated; trigger: EU interoperability use cases emerge

### Future Consideration (v2+)

Features deferred to post-MVP milestones, documented in PROJECT.md Out of Scope.

- [ ] **Frozen-dessert balancing** (PAC/POD/overrun/Brix engine for gelato/ice cream/sorbet) — schema fields reserved; a full milestone
- [ ] **Social platform** (community feed, follows, comments, likes) — the publish foundation is v1; community mechanics are v2
- [ ] **External social sharing** (Instagram, TikTok forwarding from recipe pages) — design keeps public pages share-friendly
- [ ] **Meal planning / weekly menu scheduling** — significant scope; recipe library is the prerequisite
- [ ] **Inventory management** — separate domain; integrate after recipe platform is validated
- [ ] **Native mobile apps** — after product-market fit is established on web

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Structured recipe with ingredient lines + steps | HIGH | MEDIUM | P1 |
| Official ingredient library (seeded) | HIGH | HIGH | P1 |
| Unit conversion (flexible units → grams) | HIGH | HIGH | P1 |
| Metrics engine — nutrition, cost, allergens | HIGH | HIGH | P1 |
| EU allergen compliance (14 allergens) | HIGH | MEDIUM | P1 |
| Recipe versioning + working draft | HIGH | HIGH | P1 |
| Nested sub-recipes with roll-up | HIGH | HIGH | P1 |
| AI agent (per-recipe chat + edits) | HIGH | HIGH | P1 |
| Recipe tests (trials + experiments) | HIGH | HIGH | P1 |
| Baker's percentages + hydration ratio | HIGH | MEDIUM | P1 |
| Cooking loss / shrinkage tracking | HIGH | MEDIUM | P1 |
| Food cost % (cost vs. selling price) | HIGH | LOW | P1 |
| User private ingredients + submission workflow | MEDIUM | MEDIUM | P1 |
| Multi-language UI + ingredient names | MEDIUM | MEDIUM | P1 |
| Recipe organization (tags, search, filter) | MEDIUM | MEDIUM | P1 |
| Recipe visibility (private / publish) | MEDIUM | LOW | P1 |
| Roles & permissions (User / Mod / Admin) | MEDIUM | MEDIUM | P1 |
| Design system (shadcn/ui, warm-minimal, light/dark) | MEDIUM | MEDIUM | P1 |
| Nutrition label export (EU format) | MEDIUM | MEDIUM | P2 |
| Carbon footprint metric | LOW | HIGH | P3 |
| Meal planning / production scheduling | MEDIUM | HIGH | P3 |
| Inventory management | HIGH (ops) | HIGH | P3 |

**Priority key:**
- P1: Must have for v1 launch
- P2: Should have; add in v1.x after validation
- P3: Future milestone consideration

---

## Competitor Feature Analysis

Key competitors surveyed: **meez**, **Apicbase**, **ChefTec Ultra**, **ReciProfity**, **Kafoodle**, **Galley Solutions**.

| Feature | meez | Apicbase | ChefTec | twosides approach |
|---------|------|----------|---------|-------------------|
| Sub-recipes / components | Yes | Yes | Yes | Yes — with recursive metric roll-up |
| Recipe versioning | Basic (searchable version hub) | Approval-based versioning | Limited | Full immutable history + working draft layer with Save/Recall |
| Baker's percentages | Yes (built-in calculator) | No | No | Yes — computed from ingredient lines, in-sync with scaling |
| Cooking loss / shrinkage | Yes | Yes | Yes | Yes — per-ingredient yield % propagated to cost and nutrition |
| Food cost % | Yes | Yes | Yes | Yes — requires selling price per portion |
| Nutrition roll-up | Yes | Yes | Yes | Yes — through nested sub-recipes |
| EU allergen compliance | Partial | Yes (EU labels) | No | Yes — 14 allergens, contains / may contain, full roll-up |
| Official ingredient library | Platform-curated | Platform-curated | Licensed DB | Seeded from CIQUAL + USDA + OFF; legally storable |
| User-submitted ingredients | No | No | No | Yes — with moderation workflow |
| Multi-language ingredient names | No | Partial | No | Yes — Greek + English from day one |
| AI agent integrated | No (external AI tools) | AI cost-saving suggestions | No | Yes — conversational, recipe-aware, edits working draft |
| Structured recipe testing | No | No | No | Yes — trials + experiments with hypothesis/outcome model |
| Working draft / safe undo model | No | No | No | Yes — unique to twosides |
| Private + publish visibility | No (team-focused) | Org-scoped | No | Yes — private by default, explicit publish to public library |
| Pricing | $$$  enterprise | $$$  enterprise | $$ mid-market | TBD |

**Key insight:** No single competitor combines versioned recipes, nested sub-recipe roll-up, structured testing, a moderated ingredient library, and an integrated AI agent that can mutate the recipe directly. The meez + AI agent combination is the closest proxy, but it lacks the testing model and the working draft / version history depth. Apicbase covers the enterprise food-ops angle (inventory, procurement) that twosides explicitly defers.

---

## Adjacent Features the Vision May Have Missed

Research surfaced the following feature areas not explicitly called out in PROJECT.md that appear in professional and prosumer recipe platforms. Listed for awareness, not necessarily for v1.

| Feature | Where It Appears | Recommendation |
|---------|-----------------|----------------|
| **Guided cook mode** (step-by-step, screen always on, timers embedded in steps) | SideChef, meez | Deferred — valuable for home cooks and training, but professional chefs don't follow step-by-step on screen during service. Add in a later milestone for the amateur audience. |
| **Inline prep yield actions** (e.g. "trimmed", "peeled", "cooked" modifiers on ingredient lines) | meez (prep-action per ingredient) | This is essentially the cooking-loss feature already in scope. Ensure the ingredient line model supports a `prep_action` modifier alongside the yield percentage. |
| **Recipe import (URL scraping)** | Most consumer apps | Low priority for professional tool; pros create from scratch. Deferred. |
| **Cost data: ingredient price per unit, date-stamped** | meez, Apicbase, MarketMan | User must enter a price per unit for each ingredient. The model should support a price-history record per ingredient (date, price, currency) so cost trends can be tracked. This is missing from the current spec but low-complexity to add to the data model upfront. |
| **Selling price per portion** | ChefTec, meez, Apicbase | Required for food cost %. PROJECT.md mentions the metric but the data field (selling_price) needs to be on the recipe/recipe version. Recommend adding explicitly. |
| **Prep sheet / production sheet export** | ChefTec, meez, Galley | A printable prep sheet (ingredients scaled to batch, with prep actions and yield) is standard in professional kitchens. Printable recipe card is a natural v1.x feature. |
| **Recipe duplication / fork** | Most platforms | Not mentioned in PROJECT.md but essential workflow. A chef creates a variant by duplicating and editing, not starting from scratch. The AI agent's "create variant" feature satisfies this, but a manual duplicate action should also exist. |
| **Cuisine / category taxonomy for recipes** | Universal | Needs a controlled vocabulary (French, Greek, Italian, Pastry, Bread, etc.) plus free-form tags. Not explicitly mentioned but implied by search and filter. |
| **Difficulty calibration** | Most platforms | Subjective user-set rating is fine; some platforms auto-compute from technique keywords. User-set is simpler and more honest. |
| **Photo / media on recipe** | Universal | One hero image minimum; some platforms support step-level photos. Recipe tests already capture photos; the recipe itself should also support a hero image and optionally step-level images. |
| **Recipe notes field** (chef's personal notes, not AI chat) | Most platforms | Free-text chef notes attached to a recipe (distinct from the AI chat) are expected. Useful for the AI agent context too. |
| **Portion count adjustment on view** | Universal | Quick "serves X" adjustment without saving a new version; ephemeral scaling for service | Ties to scaling engine; display-only, no version created. |

---

## Sources

- [meez — Recipe Management & Food Costing Software](https://www.getmeez.com/)
- [meez: Adding ingredients and sub recipes to recipes](https://intercom.help/getmeez/en/articles/5761292-adding-ingredients-and-sub-recipes-to-recipes)
- [meez: Baker's Percentage Calculator](https://www.getmeez.com/blog/percentage-of-total-ingredients)
- [meez: Yield in a Recipe](https://www.getmeez.com/blog/never-assume-100-ingredient-yield)
- [Apicbase Recipe Management Software](https://get.apicbase.com/recipe-management-software/)
- [Apicbase Allergen Management](https://get.apicbase.com/allergen-management-software/)
- [ChefTec Ultra Foodservice Management Software](https://www.cheftec.com/cheftec-ultra)
- [Kafoodle Recipe & Menu Management](https://www.kafoodle.com/products/recipe-menu-management-software)
- [ECS Solutions: Recipe Version Control](https://ecssolutions.com/recipe-version-control-and-released-to-production-functionality/)
- [CIA Chef: Kitchen Calculations (Cooking Loss Test)](https://www.ciachef.edu/wp-content/uploads/2024/07/kitchen-calculations.pdf)
- [King Arthur Baking: Baker's Percentage](https://www.kingarthurbaking.com/pro/reference/bakers-percentage)
- [Paytronix: Recipe Management Software Challenges](https://www.paytronix.com/blog/recipe-management-software)
- [Food Recipe Management Systems Overview 2024 — jalebi.io](https://jalebi.io/recipe-management/)
- [Supy: Restaurant Recipe Management Software](https://supy.io/blog/restaurant-recipe-management-software)
- [Chefs Resources: Recipe Evaluation Form](https://www.chefs-resources.com/kitchen-forms/recipe-evaluation-form/)
- [gitnux: Best Chef Recipe Software 2026](https://gitnux.org/best/chef-recipe-software/)
- [Galley Solutions: Central Kitchen Management Software](https://www.galleysolutions.com/blog/the-best-central-kitchen-management-software-of-2024)

---

*Feature research for: professional recipe management platform (twosides)*
*Researched: 2026-05-16*
