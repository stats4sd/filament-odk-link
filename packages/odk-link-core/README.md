# Manage ODK forms  in a harmonised way with Laravel Filament 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stats4sd/filament-odk-link.svg?style=flat-square)](https://packagist.org/packages/stats4sd/filament-odk-link)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stats4sd/filament-odk-link/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stats4sd/filament-odk-link/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stats4sd/filament-odk-link/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stats4sd/filament-odk-link/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/stats4sd/filament-odk-link.svg?style=flat-square)](https://packagist.org/packages/stats4sd/filament-odk-link)



A Laravel package and Filament plugin for importing XLSForm templates, configuring owner-specific forms, deploying them to ODK Central and ingesting submissions. Host contracts keep ownership, users and operational notification policy under application control.

## Installation

You can install the package via composer:

```bash
composer require stats4sd/filament-odk-link
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-odk-link-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-odk-link-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-odk-link-views"
```

## Host contracts and setup

The package owns XLSForm/ODK behavior; the host supplies its owner and user models, notification recipients and optional submission processing. Configure models before running migrations. The package does not infer `App\Models` classes, scan namespaces or require Spatie permission.

A minimal owner uses the existing relationship trait. The host owns the `teams` table and its `name` column; create that table before the published package migrations. Only one configured model owns deployable forms. Template ownership remains polymorphic: `Platform` implements `WithXlsformTemplates`, not `FormOwner`.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class Team extends Model implements FormOwner
{
    use HasXlsforms;

    protected $guarded = [];
}
```

The user contract extends Laravel authentication and notification routing. Laravel's `Notifiable` trait supplies compatible methods. Create the host's users and notifications tables and configure its broadcast transport for UI delivery. The package's ODK Central `AppUser` is a separate model.

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;

class User extends Authenticatable implements PlatformUser
{
    use Notifiable;
}
```

Publish configuration with `php artisan vendor:publish --tag=filament-odk-link-config`, then set these entries in `config/filament-odk-link.php`. Preserve the published package registry entries and append any host models that table-name lookup needs. The `ODK_FORM_OWNER_MODEL` and `ODK_USER_MODEL` environment overrides remain supported.

```php
'models' => [
    'form_owner' => env('ODK_FORM_OWNER_MODEL', App\Models\Team::class),
    'user_model' => env('ODK_USER_MODEL', App\Models\User::class),
    'registry' => [
        Stats4sd\FilamentOdkLink\Models\Country::class,
        // Keep the other package entries supplied in the published config here.
        App\Models\Team::class,
    ],
],
'contracts' => [
    'submission_processor' => App\OdkLink\ProcessSubmission::class,
    'role_resolver' => App\OdkLink\NotificationRecipients::class,
    'current_owner_resolver' => null,
    'operation_notifier' => null,
],
```

Each registry entry must be a concrete Eloquent model with no required constructor arguments. Repeated class entries are deduplicated; different classes mapping to the same table are rejected. `HelperService::getModels()` returns the validated explicit class list; `getModelByTablename()` returns a model instance or `null`. Configuration errors name the offending key and are validated when a capability is used; discovery, config publishing and config caching work before models are configured.

A processor resolves through Laravel's container, including constructor dependencies. It receives the saved submission after core ingestion succeeds. Exceptions propagate through normal queue failure/retry handling. It may run again on retries and must account for that; update paths do not gain an extra processing phase.

```php
namespace App\OdkLink;

use Psr\Log\LoggerInterface;
use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class ProcessSubmission implements SubmissionProcessor
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(Submission $submission): void
    {
        $this->logger->info('Submission ingested', ['id' => $submission->getKey()]);
        // Apply host-specific processing here.
    }
}
```

Despite its historical name, `RoleResolver` only selects operational notification recipients. For example, a host with its own `receives_odk_notifications` boolean column can implement it without a roles package:

```php
namespace App\OdkLink;

use App\Models\User;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;

class NotificationRecipients implements RoleResolver
{
    /** @return Collection<int, User> */
    public function notificationRecipients(): Collection
    {
        return User::query()->where('receives_odk_notifications', true)->get();
    }
}
```

Empty recipients are valid. Recipients must be instances of the configured `Model&PlatformUser`; duplicate model identities receive one delivery. Deployment/export failures prefer the initiating user; administrator recipients are used only when there was no actor. Notification-only jobs whose serialized user was deleted retain Laravel's missing-model queue failure behavior, with no invented replacement recipient. Failure-notification resolution or delivery errors are logged without replacing the operation error or preventing its existing cleanup. Success delivery failures still fail the job.

Recipient selection does not authorize operations. Hosts retain responsibility for policies, panel access and tenant-selection middleware. A selected owner scopes forms/submissions and sees shared plus owned choices. With no owner selected, administrative and CLI queries remain unscoped. Locale editing requires a selected creator. A resolver returning an incompatible model—including an unrelated `FormOwner` with the same primary key—raises an error.

### Providers and optional overrides

Composer still discovers `FilamentOdkLinkServiceProvider`, the compatibility composition root. It registers `OdkLinkCoreServiceProvider` plus `Filament\OdkLinkFilamentServiceProvider` once. The public `OdkLinkAdmin` plugin and config/migration/view publish tags are unchanged. Worker bindings are available without visiting a panel.

| Contract | Core-only default | Combined-provider default |
| --- | --- | --- |
| `SubmissionProcessor` | `NullSubmissionProcessor` (no post-processing) | Same |
| `RoleResolver` | `EmptyRoleResolver` (no administrator recipients) | Same; configure a host resolver |
| `CurrentOwnerResolver` | `NullCurrentOwnerResolver` (no current owner) | `FilamentCurrentOwnerResolver` (reads the active tenant each call) |
| `OperationNotifier` | `NullOperationNotifier` (delivery disabled) | `FilamentOperationNotifier` |

Explicit implementation class strings in `contracts.*` override these defaults and support constructor injection. Custom owner resolvers implement `current(): (Model&FormOwner)|null`; custom notifiers implement `send(OperationNotification $notification, Collection $recipients): void`. Domain notifications carry an immutable title/body/severity/ID, persistence and database/broadcast flags, and optional action URL. `HtmlString` bodies remain trusted HTML; ordinary strings retain ordinary escaping. Existing success notifications stay broadcast-only; failures retain database plus broadcast delivery and the database refresh event.

For core-only boot, disable Composer discovery of `stats4sd/filament-odk-link` in the host's `extra.laravel.dont-discover` and explicitly register `OdkLinkCoreServiceProvider` instead of the compatibility provider. A host that also wants no UI providers must disable discovery of installed Filament/Livewire packages too. Step 4 proves isolated boot and source independence with those providers absent; Filament remains an installed dependency until the Step 5 package extraction.

### Migration from the previous integration

1. Change the owner to `implements FormOwner`, retaining `HasXlsforms`. `WithXlsforms` remains a deprecated interface extending the canonical contract for the coordinated migration release. Change the human user to `implements PlatformUser` with `Notifiable`, and explicitly configure both model classes.
2. Move static submission callbacks into an injected `SubmissionProcessor`. Remove both `submission.process_method` and `submission.foreign_key_process_method` from published config; any populated legacy setting raises an actionable error during processing. The unused foreign-key hook has no replacement phase.
3. Register host models explicitly in `models.registry`; namespace discovery is gone. Core no longer installs `haydenpierce/class-finder` or `spatie/laravel-permission`. Hosts using roles must require Spatie themselves and configure their own `RoleResolver`. The old `roles.xlsform-admin` field was not an authorization contract and is removed from package defaults; host policies remain the source of access decisions.
4. Drain in-flight import/deployment/export jobs before deploying the changed serialized recipient types, then restart queue workers. Compatibility with old queued payloads is not promised.
5. Refresh published config and migration source copies while retaining host settings and migration ordering. This refactor changes owner-class validation in migration files, not their schema; already-run migrations do not need to run again. Include these integration changes in the planned coordinated migration release alongside future external-facing renames.

The [reference app](../../apps/reference/README.md) demonstrates a concrete Spatie recipient policy, panels, storage settings and published migrations. Package/config/namespace renames and UI extraction remain later roadmap work.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Stats4SD](https://github.com/stats4sd)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
