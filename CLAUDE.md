# CLAUDE.md

Monorepo for the ODK Link packages and their reference host application. See [RESTRUCTURING.md](RESTRUCTURING.md) for the current shape, the target end state and the roadmap; keep its Status line and roadmap checkboxes current when a step lands.

# PHP / Laravel Coding Guidelines

Follow the coding guidelines in @.claude/laravel-php-guidelines.md

# Code Project Working Patterns

Follow the working patterns in @.claude/code-project-working-patterns.md

`docs/` is gitignored. Plans, change logs and code reviews live there on the machine where they were written; anything that must survive goes in a tracked file (`RESTRUCTURING.md`, `.github/workflows/README.md`, package READMEs).

# Layout

| Path | What | Guidance |
| --- | --- | --- |
| `packages/odk-link-core/` | The Laravel package and Filament plugin (`stats4sd/filament-odk-link`, namespace `Stats4sd\FilamentOdkLink`). Own `composer.json`, `vendor/`, Pest + Testbench suite, PHPStan, Pint. | [packages/odk-link-core/CLAUDE.md](packages/odk-link-core/CLAUDE.md) |
| `apps/reference/` | Thin Laravel 13 + Filament 5 host app consuming the core by Composer path repo. Owns `Team` and `User`, an `admin` panel with the package plugin, and a tenant `team` panel. Manual test surface and UI test host. | [apps/reference/README.md](apps/reference/README.md) |
| `.github/workflows/` | Per-package CI. | [.github/workflows/README.md](.github/workflows/README.md) |
| `composer.json` (root) | Workspace shell. Delegates `test:*`, `analyse:*`, `format:*`, `install:*` scripts to the package and app directories. Not published. | |

# Commands

Run from the repo root. Each delegates into the right directory; per-directory `vendor/` is intentional (see RESTRUCTURING.md, Decisions).

```bash
composer install:all        # core package vendor + reference app vendor
composer test               # core Pest suite (alias of test:core)
composer test:reference     # reference app Pest suite
composer test:all
composer analyse            # PHPStan on the core
composer format             # Pint on the core; format:reference for the app
```

The core suite must run inside `packages/odk-link-core`, never against the root `vendor/`: its `post-autoload-dump` (`testbench package:discover`) only fires when the package is the Composer root.

# Conventions that span directories

- Root `.gitignore` patterns are mostly unanchored (`vendor`, `node_modules`, `docs`, `build`) and apply to every nested directory. `phpunit.xml` is anchored to `packages/*/` so the reference app can commit its own.
- The reference app holds published copies of the package config and migrations. When the core adds a migration, re-publish in the app (`php artisan vendor:publish --tag=filament-odk-link-migrations`) and commit the new file. See the app README for the ordering caveat.
- CI job names are per check-run, not per workflow. A job in a new workflow must not reuse a name that the `dev` ruleset already requires (`tests (PHP 8.4)`, `phpstan`, `pint`).
