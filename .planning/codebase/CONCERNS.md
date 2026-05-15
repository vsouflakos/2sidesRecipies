# Concerns

Technical debt, known issues, and risk areas in the `twosides` codebase. Identified during codebase mapping — addressing these is optional but informs planning.

> **Context:** This is an early-stage app derived from the Laravel + Inertia React starter kit. Most "concerns" are starter-kit defaults that are fine for development but need attention before production or before building real domain features on top.

## High Priority

### 1. XSS risk in two-factor setup
`resources/js/components/two-factor-setup-modal.tsx` (~lines 81–83) uses `dangerouslySetInnerHTML` to render the QR-code SVG without sanitization. The SVG comes from Fortify (trusted server), so risk is currently low, but the pattern is fragile — if the source ever changes it becomes an injection vector. Consider rendering the QR as an `<img>` from a data URI or sanitizing the SVG.

### 2. Email verification not enforced
The `User` model does not implement the `MustVerifyEmail` contract even though Fortify's email-verification feature is wired up. Verification emails may send but routes are not gated on verified status. Decide explicitly: enforce it (implement the contract + `verified` middleware) or disable the feature.

### 3. No authentication audit logging
There are no event listeners for login, logout, failed login, 2FA enable/disable, or password change. For any app handling real user accounts this is a gap — no trail for security incidents.

### 4. SQLite as default database
`database/database.sqlite` is the default connection. SQLite serializes writes and handles only ~1–3 concurrent writers — unsuitable for a multi-user production deployment. Plan a migration to PostgreSQL/MySQL before launch.

## Medium Priority

### 5. Two-factor recovery codes — no per-code tracking
Recovery codes are stored as a set with no individual used/unused tracking. A user who exhausts codes has no graceful regeneration path mid-session and can be locked out.

### 6. User deletion is hard delete
Account deletion removes the row outright — no soft deletes, no grace period, no recovery window. Reconsider if user data should be recoverable.

### 7. Rate limiting may be lenient
Auth rate limiting defaults to ~5/minute. Adequate for casual abuse, weak against distributed credential-stuffing. Revisit thresholds and consider per-account lockout.

### 8. Default error pages
No custom 403/404/419/500 pages. Default Laravel error pages can leak framework/stack details depending on `APP_DEBUG`. Add branded error pages before production.

## Low Priority / Scalability

### 9. QR code generated on-demand
The 2FA QR is generated when the modal opens rather than prefetched — adds perceptible latency to the setup flow.

### 10. No CDN / asset optimization
Static assets are served locally with no CDN configuration.

### 11. File-based sessions
Sessions use the file driver — cannot span multiple app servers. Move to `database` or `redis` driver before horizontal scaling.

### 12. No caching strategy
No application/response caching configured beyond defaults.

## Summary

| # | Concern | Severity | Area |
|---|---------|----------|------|
| 1 | XSS risk — `dangerouslySetInnerHTML` for QR SVG | High | Security |
| 2 | Email verification not enforced | High | Auth |
| 3 | No auth audit logging | High | Security |
| 4 | SQLite default DB | High | Scalability |
| 5 | 2FA recovery codes — no per-code tracking | Medium | Auth |
| 6 | Hard-delete user accounts | Medium | Data |
| 7 | Lenient auth rate limiting | Medium | Security |
| 8 | No custom error pages | Medium | Security/UX |
| 9 | QR generated on-demand | Low | Performance |
| 10 | No CDN / asset optimization | Low | Performance |
| 11 | File-based sessions | Low | Scalability |
| 12 | No caching strategy | Low | Performance |

**Overall:** The codebase is clean starter-kit scaffolding with no domain logic yet. None of these block building new features — but items 2, 3, and 4 should be resolved before this app holds real users in production.

---
*Mapped: 2026-05-16*
