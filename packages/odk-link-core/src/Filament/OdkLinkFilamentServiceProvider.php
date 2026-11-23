<?php

namespace Stats4sd\FilamentOdkLink\Filament;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportTesting\Testable;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\FilamentOdkLink\Support\ConfiguredContracts;
use Stats4sd\FilamentOdkLink\Testing\TestsFilamentOdkLink;

class OdkLinkFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            CurrentOwnerResolver::class => ['current_owner_resolver', FilamentCurrentOwnerResolver::class],
            OperationNotifier::class => ['operation_notifier', FilamentOperationNotifier::class],
        ] as $contract => [$name, $default]) {
            $this->app->bind($contract, fn ($app) => $app->make(ConfiguredContracts::class)->resolve($app, $name, $contract, $default));
        }
    }

    public function boot(): void
    {
        $views = __DIR__ . '/../../resources/views';
        $this->loadViewsFrom($views, 'filament-odk-link');

        if ($this->app->runningInConsole()) {
            $this->publishes([$views => resource_path('views/vendor/filament-odk-link')], 'filament-odk-link-views');
        }

        FilamentAsset::register([
            Css::make('filament-odk-link-styles', __DIR__ . '/../../resources/dist/filament-odk-link.css'),
            Js::make('filament-odk-link-scripts', __DIR__ . '/../../resources/dist/filament-odk-link.js'),
        ], 'stats4sd/filament-odk-link');

        Testable::mixin(new TestsFilamentOdkLink);
    }
}
