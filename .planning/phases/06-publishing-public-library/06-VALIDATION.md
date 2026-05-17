---
phase: 6
slug: publishing-public-library
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-18
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.x (PHPUnit 12) |
| **Config file** | `phpunit.xml` (existing) |
| **Quick run command** | `php artisan test --compact --filter=Publish` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact` filtered to the touched feature
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

*The gsd-planner fills this table when plans are created — one row per task. Use the requirement IDs PUB-01 … PUB-04.*

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | PUB-XX | feature | `php artisan test --compact --filter=...` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Red-test scaffold for PUB-01 (private-by-default visibility, guest cannot see unpublished recipe)
- [ ] Red-test scaffold for PUB-02 / PUB-03 (publish + unpublish lifecycle, version pinning)
- [ ] Red-test scaffold for PUB-04 (public library browse + 6-filter search)
- [ ] Guest-access tests (library index + public recipe page reachable without login)
- [ ] Privacy-redaction tests (cost/pricing, chef notes, tests, AI never appear on public page)

*Existing Pest infrastructure (`phpunit.xml`, factories) covers framework setup — only test files are new.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Public recipe page visual cleanliness / share-friendliness | PUB-02 | Subjective layout quality not assertable in code | Open a published recipe slug URL while logged out; confirm clean read view, no builder chrome |

*All functional phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
