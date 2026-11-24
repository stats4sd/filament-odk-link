<?php

namespace Stats4sd\OdkLink\Filament\Tests;

use Stats4sd\OdkLink\Filament\Tests\Panel\OdkPanelProvider;

/**
 * Boots the plugin inside a registered panel so resource pages can be rendered with Livewire.
 */
class PanelTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            OdkPanelProvider::class,
        ];
    }
}
