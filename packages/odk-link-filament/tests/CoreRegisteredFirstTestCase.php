<?php

namespace Stats4sd\FilamentOdkLink\Filament\Tests;

use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;

class CoreRegisteredFirstTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [OdkLinkCoreServiceProvider::class, ...parent::getPackageProviders($app)];
    }
}
