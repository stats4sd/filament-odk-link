<?php

namespace Stats4sd\OdkLink\Filament\Tests\Panel;

use Filament\Panel;
use Filament\PanelProvider;
use Stats4sd\OdkLink\Filament\OdkLinkAdmin;

/**
 * A panel deliberately not called "admin", so tests catch route names that assume the host's panel id.
 */
class OdkPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('odk')
            ->path('odk')
            ->default()
            ->plugin(OdkLinkAdmin::make());
    }
}
