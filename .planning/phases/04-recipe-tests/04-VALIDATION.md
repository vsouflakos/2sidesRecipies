---
phase: 4
slug: recipe-tests
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-17
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4.7 |
| **Config file** | `phpunit.xml` + `tests/Pest.php` |
| **Quick run command** | `php artisan test --compact --filter=RecipeTestTest` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=RecipeTestTest`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

> Populated by gsd-planner once PLAN.md task IDs exist. Each phase requirement
> maps to at least one automated Pest feature test in
> `tests/Feature/Recipes/RecipeTestTest.php`.

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | TEST-01 | feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | TEST-02 | feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | TEST-03 | feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | TEST-04 | feature | `php artisan test --compact --filter=RecipeTestTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Recipes/RecipeTestTest.php` — feature test stubs for TEST-01, TEST-02, TEST-03, TEST-04
- [ ] `database/factories/RecipeTestFactory.php` — shared factory for test data (trial + experiment states)
- [ ] `database/factories/RecipeTestPhotoFactory.php` — photo factory for upload edge cases
- [ ] `php artisan storage:link` — one-time dev setup so uploaded photos resolve (not a test gap, but a Wave 0 setup requirement)

*Pest v4.7 framework already installed — no framework install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Drag-drop photo upload UX + local previews | TEST-03 | Browser drag-drop interaction not exercisable in Pest feature tests (file upload payload IS automatable via `UploadedFile::fake()`, but the drag-drop gesture and preview rendering are not) | In the record-test modal, drag 2–3 images onto the drop zone; confirm thumbnails preview before submit |
| Thumbnail grid + click-to-enlarge lightbox | TEST-03 | Visual/interaction behavior | Open a saved test with photos; confirm grid renders and clicking a thumbnail opens the lightbox |

*Photo upload persistence (file written to disk + `RecipeTestPhoto` row) IS automated via `UploadedFile::fake()` and `Storage::fake()`.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
