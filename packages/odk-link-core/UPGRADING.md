# Upgrading

This file collects breaking changes as they land. It becomes the migration guide for the first release of `stats4sd/odk-link-core` and `stats4sd/odk-link-filament` (restructuring step 8).

## From `stats4sd/filament-odk-link` to `stats4sd/odk-link-core` + `stats4sd/odk-link-filament`

### Before deploying

Drain the queue (`php artisan queue:work --stop-when-empty`, or let Horizon finish). Queued jobs, queued Excel exports and failed jobs store serialized class names under the old namespace. They cannot be migrated and will fail after the upgrade.

### Composer

```bash
composer remove stats4sd/filament-odk-link
composer require stats4sd/odk-link-core            # headless core only
composer require stats4sd/odk-link-filament        # Filament UI; pulls in the core
```

### Names

| Thing | Before | After |
| --- | --- | --- |
| Core PHP namespace | `Stats4sd\FilamentOdkLink\` | `Stats4sd\OdkLink\Core\` |
| Filament PHP namespace | `Stats4sd\FilamentOdkLink\Filament\`, `\Forms\`, `\Testing\`, `\OdkLinkAdmin` | `Stats4sd\OdkLink\Filament\` (the `Filament\` level is dropped: `...\Filament\OdkAdmin\...` becomes `Stats4sd\OdkLink\Filament\OdkAdmin\...`) |
| Seeders | `Stats4sd\FilamentOdkLink\Database\Seeders\` | `Stats4sd\OdkLink\Core\Database\Seeders\` |
| Config file and key | `config/filament-odk-link.php`, `config('filament-odk-link.*')` | `config/odk-link.php`, `config('odk-link.*')` |
| Publish tags | `filament-odk-link-config`, `filament-odk-link-migrations`, `filament-odk-link-views` | `odk-link-config`, `odk-link-migrations`, `odk-link-views` |
| Install command | `filament-odk-link:install` | `odk-link:install` |
| View namespace | `filament-odk-link::` | `odk-link::` |
| Filament asset package and stylesheet id | `stats4sd/filament-odk-link`, `filament-odk-link-styles` | `stats4sd/odk-link-filament`, `odk-link-styles` |
| Broadcast event names (`broadcastAs`) | `FilamentOdkLink.XlsformTemplateWasImported` etc. | `OdkLink.XlsformTemplateWasImported` etc. |
| Event service provider | `FilamentOdkLinkEventServiceProvider` | `OdkLinkEventServiceProvider` |
| Facade | `FilamentOdkLink` alias and `Stats4sd\FilamentOdkLink\Facades\FilamentOdkLink` | removed (it was empty) |
| Env keys | `ODK_*`, `MAIN_OWNER_TYPE` | unchanged |

### Steps

1. Replace `Stats4sd\FilamentOdkLink\` across your app with `Stats4sd\OdkLink\Core\`. For UI classes, use `Stats4sd\OdkLink\Filament\` as shown above. That includes published migrations, which import `Support\ConfiguredModels`, and your config file.
2. Rename `config/filament-odk-link.php` to `config/odk-link.php`, and replace every `config('filament-odk-link.` call in your app.
3. If you published the views, move `resources/views/vendor/filament-odk-link` to `resources/views/vendor/odk-link`.
4. Publish and run the new migration that rewrites stored class names:
   ```bash
   php artisan vendor:publish --tag=odk-link-migrations   # existing files are skipped
   php artisan migrate
   ```
   `*_rename_odk_link_class_names` rewrites `Stats4sd\FilamentOdkLink\…` values in the polymorphic type columns `media.model_type`, `xlsform_templates.owner_type`, `submissions.primary_data_subject_type`, `odk_projects.owner_type`, `entities.model_type`, `language_strings.linked_entry_type` and `datasets.model_type`. Your own class names are left alone. If you store package class names in columns of your own, rewrite them yourself.
5. `php artisan filament:assets` to publish the stylesheet under its new key. Delete the old `public/css/stats4sd/filament-odk-link/` directory.
6. Front-end listeners for the package's broadcast events must use the new names, with a leading dot in Echo: `.OdkLink.XlsformTemplateWasImported`.
7. `php artisan optimize:clear`.
