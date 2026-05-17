---
phase: 5
slug: ai-agent
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-17
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.x / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter={filter}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~TBD seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={filter}`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** TBD seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | AI-XX | unit/feature | `{command}` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Planner fills this table from PLAN.md tasks.*

---

## Wave 0 Requirements

- [ ] Test stubs for AI-01 through AI-07
- [ ] Shared fixtures / factories for `recipe_conversations` + `recipe_conversation_messages`
- [ ] `Prism::fake()` setup for AI provider tests

*If none: "Existing infrastructure covers all phase requirements."*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| TBD | AI-XX | TBD | TBD |

*If none: "All phase behaviors have automated verification."*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < TBDs
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
