# CLAUDE.md

Monorepo for the ODK Link packages and their reference host application. See [RESTRUCTURING.md](RESTRUCTURING.md) for the current shape, the target end state and the roadmap; keep its Status line and roadmap checkboxes current when a step lands.

# Code Project Working Patterns

Follow the working patterns in @.claude/code-project-working-patterns.md

# Layout

| Path | What | Guidance |
| --- | --- | --- |
| `packages/odk-link-core/` | Headless Laravel core (`stats4sd/odk-link-core`, namespace `Stats4sd\OdkLink\Core`): models, ODK Central client, jobs, migrations, config. No Filament or Livewire installed. Own `composer.json`, `vendor/`, Pest + Testbench suite, PHPStan, Pint. | [packages/odk-link-core/CLAUDE.md](packages/odk-link-core/CLAUDE.md) |
| `packages/odk-link-filament/` | Filament UI (`stats4sd/odk-link-filament`, namespace `Stats4sd\OdkLink\Filament`): `OdkLinkAdmin` plugin, resources, widgets, views, CSS. Requires the core by path. Own `composer.json`, `vendor/`, Pest + Testbench suite, PHPStan, Pint. | [packages/odk-link-filament/CLAUDE.md](packages/odk-link-filament/CLAUDE.md) |
| `apps/reference/` | Thin Laravel 13 + Filament 5 host app consuming both packages by Composer path repo. Owns `Team` and `User`, an `admin` panel with the `OdkLinkAdmin` plugin, and a tenant `team` panel. Manual test surface and UI test host. | [apps/reference/README.md](apps/reference/README.md) |
| `.github/workflows/` | Per-package CI. | [.github/workflows/README.md](.github/workflows/README.md) |
| `composer.json` (root) | Workspace shell. Delegates `test:*`, `analyse:*`, `format:*`, `install:*` scripts to the package and app directories. Not published. |  |

# Commands

Run from the repo root. Each delegates into the right directory; per-directory `vendor/` is intentional (see RESTRUCTURING.md, Decisions). Script names are in the root `composer.json`.

Each package suite must run inside its own directory, never against the root `vendor/`: their `post-autoload-dump` (`testbench package:discover`) only fires when the package is the Composer root.

# Conventions that span directories

- Root `.gitignore` patterns are mostly unanchored (`vendor`, `node_modules`, `build`) and apply to every nested directory. `phpunit.xml` is anchored to `packages/*/` so the reference app can commit its own. `testbench.yaml` is not ignored: `packages/odk-link-filament/testbench.yaml` is committed because Larastan needs it to resolve the `odk-link::` view namespace on a clean install.
- The reference app holds published copies of the package config and migrations. When the core adds a migration, re-publish in the app (`php artisan vendor:publish --tag=odk-link-migrations`) and commit the new file. See the app README for the ordering caveat.
