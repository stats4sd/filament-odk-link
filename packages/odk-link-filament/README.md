# ODK Link for Filament

Filament 5 admin UI for ODK Link: the `OdkLinkAdmin` panel plugin, the XLSForm template, module, choice list and dataset resources, dashboard widgets, form components and their Blade views.

It sits on top of the headless core, [`stats4sd/odk-link-core`](../odk-link-core/README.md), which owns the models, the ODK Central client, jobs, migrations and configuration. Requiring this package pulls in the core.

## Installation

```bash
composer require stats4sd/odk-link-filament
```

Then follow the core's setup (host models, config, migrations) in the [core README](../odk-link-core/README.md).

Register the plugin on the panel that should hold the ODK admin resources:

```php
use Stats4sd\OdkLink\Filament\OdkLinkAdmin;

$panel->plugin(OdkLinkAdmin::make());
```

Optionally publish the views:

```bash
php artisan vendor:publish --tag="odk-link-views"
```

## Providers

Composer discovers `Stats4sd\OdkLink\Filament\OdkLinkFilamentServiceProvider`. It registers the core provider first, then binds the Filament implementations of two core contracts, so the adapters win regardless of package discovery order:

| Contract | Binding |
| --- | --- |
| `CurrentOwnerResolver` | `FilamentCurrentOwnerResolver` (reads the active tenant on each call) |
| `OperationNotifier` | `FilamentOperationNotifier` (Filament database and broadcast notifications) |

An explicit class in `odk-link.contracts.*` still overrides either binding.

The provider also loads the `odk-link::` view namespace and registers the package stylesheet with Filament's asset manager under `stats4sd/odk-link-filament` (stylesheet id `odk-link-styles`). Run `php artisan filament:assets` after installing or upgrading.

## Naming

This package uses the `Stats4sd\OdkLink\Filament\` namespace and the `odk-link::` view namespace. Core classes live under `Stats4sd\OdkLink\Core\` and its configuration in `config/odk-link.php`. Upgrading from `stats4sd/filament-odk-link`: see the core's `UPGRADING.md`.

## Development

```bash
composer install
composer test      # Pest + Testbench: adapters, provider boot, plugin registration
composer analyse   # PHPStan / Larastan level 4
composer format    # Pint
npm install && npm run build   # rebuild resources/dist/odk-link.css
```

Page and panel rendering is exercised in the monorepo's reference app, not here.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
