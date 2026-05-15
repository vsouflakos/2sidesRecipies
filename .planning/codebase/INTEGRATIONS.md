# External Integrations

**Analysis Date:** 2026-05-16

## APIs & External Services

**Email/Transactional:**
- Postmark - Email delivery service
  - SDK/Client: Laravel Mail (built-in)
  - Auth: `POSTMARK_API_KEY` environment variable
  - Config: `config/services.php`

- Resend - Email platform (alternative)
  - SDK/Client: Laravel Mail (built-in)
  - Auth: `RESEND_API_KEY` environment variable
  - Config: `config/services.php`

- AWS SES - Amazon Simple Email Service (alternative)
  - SDK/Client: Laravel Mail (built-in)
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`
  - Config: `config/services.php`

**Notifications:**
- Slack - Team notifications
  - SDK/Client: Laravel Notifications (built-in)
  - Auth: `SLACK_BOT_USER_OAUTH_TOKEN`
  - Config: Channel via `SLACK_BOT_USER_DEFAULT_CHANNEL` env var
  - Location: `config/services.php`

## Data Storage

**Databases:**
- SQLite (default) - Local file-based database
  - Connection: `DB_DATABASE` env var (defaults to `database/database.sqlite`)
  - Client: Illuminate Database (Eloquent ORM)
  - Config: `config/database.php`

- MySQL (optional) - Relational database for production
  - Connection: `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`
  - Charset: `utf8mb4` (default)
  - Client: Illuminate Database (Eloquent ORM)
  - Config: `config/database.php`

**File Storage:**
- Local filesystem only
  - Private storage: `storage/app/private`
  - Public storage: `storage/app/public`
  - Symlinked as: `/storage` URL path (via `config/filesystems.php`)
  - S3 configured but not active: `config/filesystems.php` includes S3 (commented/not enabled)

**Caching:**
- Database cache (default) - Uses cache table in database
  - Driver: `database`
  - Table: `cache` (configurable via `DB_CACHE_TABLE`)
  - Config: `config/cache.php`

- File cache (available) - Filesystem-based caching
  - Driver: `file`
  - Directory: `storage/framework/cache`
  - Config: `config/cache.php`

- Array cache (in-memory) - Development/testing only
  - Driver: `array`
  - Config: `config/cache.php`

**Session Storage:**
- Database sessions (configured) - Sessions stored in `sessions` table
  - Driver: `database`
  - Table: `sessions`
  - Created by migration: `0001_01_01_000000_create_users_table.php`

## Authentication & Identity

**Auth Provider:**
- Custom Laravel Fortify implementation
  - Features: Registration, login, password reset, email verification, two-factor authentication
  - Implementation: `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/` classes
  - Guard: Session-based (`web` guard)
  - User model: `app/Models/User.php`

**Two-Factor Authentication:**
- TOTP-based 2FA via Fortify
  - Columns: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`
  - QR generation: Bacon QR Code library
  - Migration: `2025_08_14_170933_add_two_factor_columns_to_users_table.php`
  - Routes: `routes/settings.php` - SecurityController

**Email Verification:**
- Laravel built-in email verification
  - Column: `email_verified_at` on users table
  - Notifications: Resent via Fortify

## Monitoring & Observability

**Error Tracking:**
- Not detected - No error tracking service configured

**Logs:**
- Monolog (Laravel's logging facade)
  - Default channel: `stack` (via `LOG_CHANNEL` env var)
  - Supported drivers: single, daily, slack, syslog, errorlog, monolog, custom, stack
  - Deprecation warnings: Optional separate channel via `LOG_DEPRECATIONS_CHANNEL`
  - Handler: `Monolog\Handler\StreamHandler` (to files), `Monolog\Handler\SyslogUdpHandler`
  - Config: `config/logging.php`

- Laravel Pail (dev tool) - Real-time log viewer
  - Package: `laravel/pail` v1.2.5
  - Usage: Command-line log streaming

## CI/CD & Deployment

**Hosting:**
- Laravel Herd (development)
  - Served at: `https://twosides.test` (auto-generated from directory name)
  - No external deployment service integrated

**CI Pipeline:**
- Not detected - No GitHub Actions, GitLab CI, or similar configured

**Build Process:**
- Frontend: `npm run build` → Vite bundling
- Backend: `composer install` → Composer autoloading
- Database: `php artisan migrate` → Migrations
- Combined dev: `composer run dev` → Concurrent vite, artisan serve, queue listener

## Environment Configuration

**Required env vars:**
- `APP_NAME` - Application name
- `APP_ENV` - Environment (production, local, testing)
- `APP_DEBUG` - Debug mode toggle
- `APP_URL` - Application URL
- `APP_KEY` - Encryption key (generated via `php artisan key:generate`)
- `DB_CONNECTION` - Database driver (default: sqlite)
- `DB_DATABASE` - Database file path or name
- `MAIL_MAILER` - Mail driver (default: log)
- Optional: `POSTMARK_API_KEY`, `RESEND_API_KEY`, `AWS_*`, `SLACK_*`

**Secrets location:**
- `.env` file (git-ignored)
- Environment variables from hosting platform (for production)

## Webhooks & Callbacks

**Incoming:**
- Not detected - No webhook endpoints configured

**Outgoing:**
- Not detected - No external webhook calls identified

## Queue System

**Driver:**
- Database queue (default) - Jobs stored in `jobs` table
  - Connection: Default database or `DB_QUEUE_CONNECTION`
  - Table: `jobs` (configurable via `DB_QUEUE_TABLE`)
  - Retry delay: 90 seconds (configurable via `DB_QUEUE_RETRY_AFTER`)
  - Config: `config/queue.php`

- Available alternatives: sync (synchronous), beanstalkd, SQS, Redis, deferred, background, failover, null
  - Not currently enabled but configured
  - Dev mode: `php artisan queue:listen --tries=1` (via `composer run dev`)

**Jobs Table:**
- Created by migration: `0001_01_01_000002_create_jobs_table.php`

---

*Integration audit: 2026-05-16*
