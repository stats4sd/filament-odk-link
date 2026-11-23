# CLAUDE.md

Project working patterns come from the root [CLAUDE.md](../../CLAUDE.md). The headless core this package builds on is described in [../odk-link-core/CLAUDE.md](../odk-link-core/CLAUDE.md).

## What lives here

The Filament UI for ODK Link, extracted from the core in restructuring step 5:

- `src/OdkLinkAdmin.php`: the panel plugin. Discovers resources in `src/Filament/OdkAdmin/Resources` and widgets in `src/Filament/Widgets`.
- `src/Filament/OdkLinkFilamentServiceProvider.php`: registers `OdkLinkCoreServiceProvider` first, then binds the Filament adapters in `src/Filament/Support` over core's null defaults. Loads the `filament-odk-link::` views and the CSS asset.
- `src/Forms/`: custom form components. `src/Testing/`: Livewire `Testable` mixin.
- `resources/views/filament/`, `resources/css/index.css`, `resources/dist/filament-odk-link.css` (built with `npm run build`).

## Namespace overlap with core

This package and the core both map `Stats4sd\FilamentOdkLink\` to their own `src/`. Composer merges the directories, which is what lets the moved classes keep their names. It is temporary, removed by the namespace rename in a later step. Do not add classes here whose names collide with core classes.

## Commands

Run inside this directory, or from the repo root with the `:filament` script variants.

```bash
composer test      # Pest + Testbench
composer analyse   # PHPStan / Larastan level 4; new errors must be fixed, not baselined
composer format    # Pint
```

Core is installed by path (`vendor/stats4sd/filament-odk-link` is a symlink to `../odk-link-core`). After changing the core's `composer.json`, run `composer update stats4sd/filament-odk-link` here and commit the lock.

Tests load core migrations from the symlinked vendor path; PHPStan reads the same path for model properties.
