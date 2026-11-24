<?php

namespace Stats4sd\OdkLink\Filament\Tests;

use Stats4sd\OdkLink\Core\OdkLinkCoreServiceProvider;

class CoreRegisteredFirstTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [OdkLinkCoreServiceProvider::class, ...parent::getPackageProviders($app)];
    }
}
