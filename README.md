# Pio De Roda Cafe & Restaurant — Booking System

Reservation and event-booking system for Pio De Roda Cafe & Restaurant. It has a
public booking site, a role-based admin console, and a JSON API. Plain PHP and
MySQL: no framework and no Composer dependencies at runtime.

## What is in the box

| Area | Location | Purpose |
| --- | --- | --- |
| Public site | `customer/` | Landing page, booking flow, reservation lookup, self-service cancel/reschedule |
| Admin console | `admin/` | Dashboard, reservations, calendar, payments, reports, catalog, archive, audit log, staff |
| API | `api/` | Public booking/lookup endpoints and admin JSON endpoints |
| Shared config | `config/` | DB, env, pricing, payments, RBAC, CSRF, audit, mail, security headers |
| Database | `database/` | Canonical schema, versioned migrations, catalog seeder |
| Deploy | `deploy/` | Oracle Cloud and Cloudflare Tunnel guides, health check |
| Tests | `tests/` | PHPUnit unit suite (no database required) |

## Requirements

- PHP **8.1+** (developed and deployed on 8.2)
- PHP extensions: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`, `iconv`
  (enable `zip` too if you plan to run `composer install` on Windows)
- MySQL 5.7+ / MariaDB 10.4+
- Apache 2.4 with `.htaccess` enabled (`mod_headers`, `mod_deflate`, and
  `mod_rewrite` are used when available)
- Composer is needed **only for the test suite**, never to run the app

## Quick start (XAMPP on Windows)

The project is designed to be the Apache document root (for example
`C:\xampp\htdocs`), so the public site is `http://localhost/` and the console is
`http://localhost/admin/`.

1. **Create the database and import the schema**

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS pdrsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:\xampp\mysql\bin\mysql.exe -u root pdrsystem < database\schema.sql
   ```

   `database/schema.sql` is the canonical fresh-install schema. Do not run the
   migrations on a fresh import.

2. **Seed the service catalog** (needed before the booking flow can price a job)

   ```powershell
   C:\xampp\php\php.exe database\seed_catalog.php
   ```

3. **Create the local `.env`**

   ```powershell
   Copy-Item .env.example .env
   ```

   Fill in the values you need. Local XAMPP defaults work for the database when
   the file is left mostly blank. See [Configuration](#configuration).

4. **Create the first administrator**

   ```powershell
   C:\xampp\php\php.exe create_admin.php admin "choose-a-strong-password" "Administrator Name"
   ```

   Passwords must be **12 to 72 bytes**. Running the command again for the same
   username updates that account's password and display name.

5. **Verify the install**

   ```powershell
   C:\xampp\php\php.exe deploy\check.php
   ```

   It checks PHP extensions, `.env`, the database connection, expected tables,
   catalog rows, account count, and the mail outbox. Warnings are safe locally;
   failures are not.

6. **Open the app**

   - Public site: <http://localhost/>
   - Admin console: <http://localhost/admin/>

## Configuration

All environment-specific settings live in `.env`, which is **git-ignored**.
`.env.example` documents every key:

- **Database** — `PDR_DB_HOST`, `PDR_DB_PORT`, `PDR_DB_NAME`, `PDR_DB_USER`,
  `PDR_DB_PASS`
- **Mail** — `PDR_MAIL_*`. Leave the SMTP password blank to use the local
  outbox instead of sending real mail.
- **App URL** — `PDR_APP_BASE_URL` is used for Xendit payment redirects. No
  trailing slash. Use `https://your-domain.example` in production.
- **Lookup secret** — `PDR_LOOKUP_SECRET` salts hashed client IPs in
  `lookup_attempts` and `audit_logs`. Use a long random value in production.
- **Turnstile** — `PDR_TURNSTILE_SITE_KEY` / `PDR_TURNSTILE_SECRET`. Leave both
  blank to disable the bot challenge.
- **Contact/test recipients** — `PDR_CONTACT_RECIPIENT`,
  `PDR_MAIL_TEST_RECIPIENT`

Online payments also need `config/xendit.php` (copy `config/xendit.example.php`).
That file and `.env` hold live credentials and must never be committed.

### Mail modes

1. Complete SMTP settings -> mail is sent for real.
2. SMTP missing or failing -> the message is written to
   `api/mail/outbox/` as an `.eml` file.

The outbox contains customer PII and booking PINs. Apache denies it over HTTP,
and its contents are git-ignored.

## Database changes

For an **existing** database, use the migration runner:

```powershell
C:\xampp\php\php.exe database\migrate.php --status   # applied / pending
C:\xampp\php\php.exe database\migrate.php            # apply pending
```

- `database/migrations/*.sql` run in filename order.
- Applied files are recorded in `schema_migrations`, so re-running is a no-op.
- Never edit a migration that has already been applied; add the next numbered
  file instead.
- A new migration should also be reflected in `database/schema.sql` for fresh
  installs.

## Tests and CI

The unit suite is dependency-free at runtime and needs **no database, no
`.env`, and no network**.

```powershell
composer install
composer test
```

PHPUnit 10.5 lives in `require-dev`; `vendor/` and `.phpunit.cache/` are
ignored. The suite covers the pure business rules:

- password policy boundaries (including the 72-byte bcrypt cap)
- role normalisation and the capability matrix
- payment status and the cent-level tolerance
- booking lead/horizon window and day offsets
- PDF text escaping and pagination

Add new tests as `tests/*Test.php`; shared includes are in
`tests/bootstrap.php`.

GitHub Actions (`.github/workflows/ci.yml`) runs on every push and pull request:

- `php -l` over every PHP file
- `composer install` + `composer test` on PHP 8.2
- `composer validate`

## Project layout

```text
.
├── admin/               Admin console pages, sidebar, shared CSS/JS
│   └── audit.php        Admin-only audit log viewer
├── api/                 JSON endpoints (public + admin)
│   ├── admin/           Auth-guarded admin API
│   └── mail/            Mailer and local outbox
├── config/              DB, env, pricing, RBAC, CSRF, audit, mail, headers
├── customer/            Public site and booking flow
├── database/            schema.sql, migrations/, migrate.php, seeders
├── deploy/              Health check and deployment/tunnel guides
├── lib/                 PDF writer and other dependency-free helpers
├── tests/               PHPUnit unit tests
├── create_admin.php     CLI account creator / password reset
├── index.php            Redirects `/` to the public site
└── .htaccess            Security headers, caching, denied paths, 404 page
```

## Roles and access

| Role | Scope |
| --- | --- |
| `admin` | Everything: reservations, payments, catalog, staff accounts, audit log |
| `staff` | Daily operations: reservations, events, calendar/blackouts, payments, reviews, reports |
| `viewer` | Read-only access to pages and reports |

- Unknown or empty role strings fall back to `viewer`, never `admin`.
- The audit log is admin-only (`view_audit`) and append-only.
- Every role can change its own password under **My Account** (`admin/profile.php`).

## Security and operations notes

- `.env` and `config/xendit.php` are git-ignored; never commit credentials.
- `config/` denies direct HTTP access via `config/.htaccess`.
- `vendor/` and `tests/` are denied by the root `.htaccess`.
- Database dumps, logs, backups, and editor files are denied by extension.
- Security headers and the CSP are set in `.htaccess` and
  `config/security_headers.php`.
- Login, customer lookup/PIN, and password change are rate-limited.
- Client IPs in `lookup_attempts` and `audit_logs` are stored as salted hashes.
- `deploy/check.php` is a post-deploy smoke test; see
  `deploy/DEPLOY-ORACLE.md` and `deploy/TUNNEL-WINDOWS.md` for production and
  tunnel setup.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 403 at `/` | Apache must load `index.php` (`DirectoryIndex`); then `/` redirects to `customer/nav.html` |
| `Database Connection Error` | MySQL is running and the `PDR_DB_*` values in `.env` are correct |
| CSS looks stale | Assets are versioned with `?v=`; check `config/assets.php` |
| Emails do not send | SMTP values in `.env`; otherwise inspect `api/mail/outbox/` |
| 403 when opening `config/...` | Expected. Configuration files are server-side only |
| `composer install` cannot extract archives | Enable `extension=zip` in `php.ini` (or install `unzip`/`7z`) and rerun |

## License

Proprietary. This repository is a private client project; it is not published
under an open-source license.
