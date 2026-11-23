<?php

namespace Stats4sd\OdkLink\Filament;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportTesting\Testable;
use Stats4sd\OdkLink\Core\Contracts\CurrentOwnerResolver;
use Stats4sd\OdkLink\Core\Contracts\OperationNotifier;
use Stats4sd\OdkLink\Core\OdkLinkCoreServiceProvider;
use Stats4sd\OdkLink\Core\Support\ConfiguredContracts;
use Stats4sd\OdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\OdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\OdkLink\Filament\Testing\TestsFilamentOdkLink;

class OdkLinkFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registering core here, ahead of the adapter bindings, guarantees the adapters
        // override core's null defaults regardless of package discovery order.
        $this->app->register(OdkLinkCoreServiceProvider::class);

        foreach ([
            CurrentOwnerResolver::class => ['current_owner_resolver', FilamentCurrentOwnerResolver::class],
            OperationNotifier::class => ['operation_notifier', FilamentOperationNotifier::class],
        ] as $contract => [$name, $default]) {
            $this->app->bind($contract, fn ($app) => $app->make(ConfiguredContracts::class)->resolve($app, $name, $contract, $default));
        }
    }

    public function boot(): void
    {
        $views = __DIR__ . '/../resources/views';
        $this->loadViewsFrom($views, 'odk-link');

        if ($this->app->runningInConsole()) {
            $this->publishes([$views => resource_path('views/vendor/odk-link')], 'odk-link-views');
        }

        FilamentAsset::register([
            Css::make('odk-link-styles', __DIR__ . '/../resources/dist/odk-link.css'),
        ], 'stats4sd/odk-link-filament');

        Testable::mixin(new TestsFilamentOdkLink);
    }
}
