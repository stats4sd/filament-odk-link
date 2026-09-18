<?php

namespace Stats4sd\FilamentOdkLink;

use Illuminate\Support\ServiceProvider;
use Stats4sd\FilamentOdkLink\Filament\OdkLinkFilamentServiceProvider;

class FilamentOdkLinkServiceProvider extends ServiceProvider
{
    public static string $name = 'filament-odk-link';

    public static string $viewNamespace = 'filament-odk-link';

    public function register(): void
    {
        $this->app->register(OdkLinkCoreServiceProvider::class);
        $this->app->register(OdkLinkFilamentServiceProvider::class);
    }
}
