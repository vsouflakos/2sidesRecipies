# Testing

Test framework, structure, and practices for the `twosides` codebase.

## Framework

- **Pest v4** — primary test framework. Config in `tests/Pest.php`.
- **PHPUnit v12** — underlying engine. Config in `phpunit.xml`.
- **Base class:** `Tests\TestCase` (`tests/TestCase.php`), extends `Illuminate\Foundation\Testing\TestCase`.

## Database

- Test database: **SQLite in-memory** (configured in `phpunit.xml`).
- Tests use Laravel's transaction/refresh behavior — no manual teardown.

## Structure

```
tests/
├── Feature/        # Most tests live here — HTTP + integration
│   ├── Auth/       # AuthenticationTest, RegistrationTest, PasswordResetTest,
│   │               # PasswordConfirmationTest, TwoFactorChallengeTest,
│   │               # EmailVerificationTest, VerificationNotificationTest
│   ├── Settings/   # ProfileUpdateTest, SecurityTest
│   ├── DashboardTest.php
│   └── ExampleTest.php
├── Unit/           # Pure unit tests (ExampleTest.php)
├── Pest.php        # Shared config, custom expectations, helpers
└── TestCase.php    # Base test case
```

Test directory mirrors `app/` structure. **Most tests are feature tests** — prefer feature over unit.

## Test Style

- **Closure-based:** `test('description', function () { ... });`
- **Assertions:** Pest expectations + Laravel response assertions:
  - `expect($user->name)->toBe('Test User')`
  - `$response->assertOk()`, `$response->assertRedirect()`
  - `$this->assertAuthenticated()`, `$this->assertGuest()`
- **Setup:** `beforeEach()` for shared setup.
- **Feature gates:** `skipUnlessFortifyHas()` helper skips tests when a Fortify feature is disabled.

## Test Data

- Always use **factories** — `User::factory()->create()`.
- Use factory **states** when available — e.g. `User::factory()->withTwoFactor()->create()`.
- Faker: follow existing convention (`fake()->word()` / `$this->faker`).

## Running Tests

```bash
php artisan test --compact                          # full suite
php artisan test --compact --filter=ProfileUpdate   # single test/filter
php artisan test --compact tests/Feature/Auth       # directory
```

Create new tests with `php artisan make:test --pest SomeFeatureTest` (`--unit` for unit tests; omit the suite directory from the name).

## Enforcement

- Every code change must be programmatically tested — write or update a test and run it.
- Run the **minimum** tests needed to verify the change (use `--filter` / specific files).
- Do not delete tests without approval.
- Do not write throwaway verification scripts when a test covers the functionality.

## Coverage Notes

Current coverage is auth/settings scaffolding only (registration, login, password reset, password confirmation, 2FA challenge, email verification, profile update, security, dashboard). New domain features will need fresh feature tests — there is no domain logic tested yet.

---
*Mapped: 2026-05-16*
