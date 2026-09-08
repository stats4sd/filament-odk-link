# ODK Link reference app

A thin Laravel 13 + Filament 5 application that consumes `packages/odk-link-core` by Composer path repository. It is what a client application looks like after installing the package, and it is the manual test surface and UI test host for the monorepo. See the root [RESTRUCTURING.md](../../RESTRUCTURING.md) for where it sits in the roadmap.

It is not published and never will be.

## What it owns

| Piece | Where | Why |
| --- | --- | --- |
| `Team` model, `teams` and `team_user` tables | `app/Models/Team.php`, `database/migrations/0001_01_01_000003_create_teams_table.php` | The package's form owner (`filament-odk-link.models.form_owner`) and the tenant of the team panel. Implements `WithXlsforms`, uses `HasXlsforms`. |
| `User` model | `app/Models/User.php` | Adds Spatie `HasRoles`, Filament `FilamentUser` and `HasTenants`. Every user may enter every panel; roles gate features inside the package. |
| `admin` panel at `/admin` | `app/Providers/Filament/AdminPanelProvider.php` | Loads the package plugin `OdkLinkAdmin` (templates, modules, module versions, datasets, choice lists). |
| `team` panel at `/team/{team}` | `app/Providers/Filament/TeamPanelProvider.php` | Tenancy on `Team`. The package ships no team plugin yet, so this panel only proves the tenant wiring. |
| Published package config | `config/filament-odk-link.php` | Edited: both storage disks default to `public` (env `ODK_XLSFORMS_DISK`, `ODK_MEDIA_DISK`). The package default reads a misspelled config key and falls back to `local`, which has no URL. |
| Published package migrations | `database/migrations/2026_09_08_*` | See "Re-publishing migrations". |
| Spatie permission config and migration | `config/permission.php`, `database/migrations/*_create_permission_tables.php` | The package hard-codes a `Super Admin` role and defaults the admin bypass role to `admin`. |
| `notifications` table | `database/migrations/0001_01_01_000004_create_notifications_table.php` | Every package job failure path writes a database notification. |
| Seeder | `database/seeders/DatabaseSeeder.php` | Roles `Super Admin` and `admin`, user `admin@example.com` / `password` holding both, team "Reference Team", and one `Platform` row (the admin template resource calls `Platform::first()`). |

No Node toolchain. Filament serves its own compiled assets, so the skeleton's Vite files were removed.

## Install and run

From the repo root:

```bash
composer install:reference          # or: cd apps/reference && composer install
cd apps/reference
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan storage:link
composer dev                        # server + queue worker + log tail
```

Then open <http://localhost:8000/admin> and log in as `admin@example.com` / `password`. The team panel is at <http://localhost:8000/team>.

`composer dev` runs a queue worker, which the import pipeline needs when `QUEUE_CONNECTION=database` (the skeleton default). If you use `php artisan serve` instead, set `QUEUE_CONNECTION=sync` in `.env` so uploads process inline.

Optional, for a `.test` domain under Herd: `herd link odk-link-reference` inside `apps/reference`.

### ODK Central

With `ODK_URL` unset or empty the package runs in local-only mode: no calls to ODK Central, no ODK project is created for a team, and template upload is unavailable because `XlsformTemplate::testOnOdkCentral()` calls the server unconditionally. To exercise the full pipeline add to `.env`:

```
ODK_URL=https://your-central.example.org
ODK_USERNAME=platform@example.org
ODK_PASSWORD=...
ODK_PLATFORM_PROJECT_ID=
```

Leave `ODK_PLATFORM_PROJECT_ID` empty on first run; the package's `PlatformSeeder` (`php artisan db:seed --class="Stats4sd\FilamentOdkLink\Database\Seeders\PlatformSeeder"`) creates the project and writes the id back. Note that `DatabaseSeeder` already creates one `Platform` row without an ODK project for local-only use; delete it before running `PlatformSeeder` against a real server.

## Tests

```bash
composer test:reference             # from the root
vendor/bin/pest                     # inside apps/reference
```

Pest with `RefreshDatabase` on SQLite in memory. `phpunit.xml` carries a fixed `APP_KEY` so CI needs no `.env`. The suite is a smoke layer: the package boots, published migrations create the schema, both panels serve their login pages, the seeded admin can open every package resource index and the template create page, and tenancy sends users to their team or 404s. Template view and edit pages are a `todo`: they need an imported xlsx, which is step 5 and 6 work.

CI: `.github/workflows/reference-ci.yml`, jobs `reference composer validate`, `reference tests (PHP 8.4)`, `reference pint`. Dependencies are installed from the committed `composer.lock`.

## Re-publishing migrations

When the core adds a migration, republish inside `apps/reference` and commit the new file:

```bash
php artisan vendor:publish --tag=filament-odk-link-migrations
```

Already-published files keep their timestamps; only new ones are added, stamped with the current time, so they sort last. If a new core migration must run before an existing one (a foreign key target, say), rename the published copy by hand so it sorts correctly, then `php artisan migrate:fresh --seed` to prove the order.

Never publish `spatie/laravel-medialibrary` migrations here: the package ships its own `create_media_table`.

## Known package issues surfaced by this app

Recorded for restructure step 4; none are fixed here except the first.

- `XlsformTemplateResource` imported `Schemas\XlsformTemplateInfolist` while the class is `XlsformTemplateInfoList`. Passes on macOS, fatal on Linux. Fixed in the package as a one-line case correction.
- `config/filament-odk-link.php` storage keys read `config('filesystem.default')`, a typo for `filesystems`, so they always fall back to `local`. Worked around in the published copy.
- `PlatformSeeder` returns early without `ODK_URL`, but `XlsformTemplateResource::getFormOwner()` requires a `Platform` row. Worked around in `DatabaseSeeder`.
- Opening a template's view or edit page calls ODK Central (`XlsformTemplate::getRequiredMedia()`), so those pages cannot render in local-only mode.
- `HasXlsforms::country()` is `belongsTo(Country::class, 'owner_id')`, which reads `teams.owner_id`. Any UI that touches `$team->country` will fail against this schema.
- `routes/web.php` registers `/odk/submissions/{submission}/update` with no middleware.
