# CLAUDE.md

Project working patterns come from the root [CLAUDE.md](../../CLAUDE.md). The headless core this package builds on is described in [../odk-link-core/CLAUDE.md](../odk-link-core/CLAUDE.md).

## What lives here

The Filament UI for ODK Link, extracted from the core in restructuring step 5:

- `src/OdkLinkAdmin.php`: the panel plugin. Discovers resources in `src/OdkAdmin/Resources` and widgets in `src/Widgets`.
- `src/OdkLinkFilamentServiceProvider.php`: registers `OdkLinkCoreServiceProvider` first, then binds the Filament adapters in `src/Support` over core's null defaults. Loads the `odk-link::` views and the CSS asset.
- `src/Forms/`: custom form components. `src/Testing/`: Livewire `Testable` mixin.
- `resources/views/filament/`, `resources/css/index.css`, `resources/dist/odk-link.css` (built with `npm run build`).

## Namespaces

This package maps `Stats4sd\OdkLink\Filament\` to `src/`; the core maps `Stats4sd\OdkLink\Core\`. The pre-rename prefix `Stats4sd\FilamentOdkLink\` is forbidden by `tests/Architecture/ShippingBoundary.php`.

## Commands

Run inside this directory, or from the repo root with the `:filament` script variants.

```bash
composer test      # Pest + Testbench
composer analyse   # PHPStan / Larastan level 4; new errors must be fixed, not baselined
composer format    # Pint
```

Core is installed by path (`vendor/stats4sd/odk-link-core` is a symlink to `../odk-link-core`). After changing the core's `composer.json`, run `composer update stats4sd/odk-link-core` here and commit the lock.

Tests load core migrations from the symlinked vendor path; PHPStan reads the same path for model properties.
