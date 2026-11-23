# ODK Link for Filament

Filament 5 admin UI for ODK Link: the `OdkLinkAdmin` panel plugin, the XLSForm template, module, choice list and dataset resources, dashboard widgets, form components and their Blade views.

It sits on top of the headless core, [`stats4sd/filament-odk-link`](../odk-link-core/README.md), which owns the models, the ODK Central client, jobs, migrations and configuration. Requiring this package pulls in the core.

## Installation

```bash
composer require stats4sd/odk-link-filament
```

Then follow the core's setup (host models, config, migrations) in the [core README](../odk-link-core/README.md).

Register the plugin on the panel that should hold the ODK admin resources:

```php
use Stats4sd\FilamentOdkLink\OdkLinkAdmin;

$panel->plugin(OdkLinkAdmin::make());
```

Optionally publish the views:

```bash
php artisan vendor:publish --tag="filament-odk-link-views"
```

## Providers

Composer discovers `Stats4sd\FilamentOdkLink\Filament\OdkLinkFilamentServiceProvider`. It registers the core provider first, then binds the Filament implementations of two core contracts, so the adapters win regardless of package discovery order:

| Contract | Binding |
| --- | --- |
| `CurrentOwnerResolver` | `FilamentCurrentOwnerResolver` (reads the active tenant on each call) |
| `OperationNotifier` | `FilamentOperationNotifier` (Filament database and broadcast notifications) |

An explicit class in `filament-odk-link.contracts.*` still overrides either binding.

The provider also loads the `filament-odk-link::` view namespace and registers the package stylesheet with Filament's asset manager under `stats4sd/filament-odk-link`. Run `php artisan filament:assets` after installing or upgrading.

## Naming

The PHP namespace (`Stats4sd\FilamentOdkLink\...`), view namespace, asset key and config file still carry the pre-split names, shared with the core. They are renamed together in a later release; see `RESTRUCTURING.md` at the monorepo root.

## Development

```bash
composer install
composer test      # Pest + Testbench: adapters, provider boot, plugin registration
composer analyse   # PHPStan / Larastan level 4
composer format    # Pint
npm install && npm run build   # rebuild resources/dist/filament-odk-link.css
```

Page and panel rendering is exercised in the monorepo's reference app, not here.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
