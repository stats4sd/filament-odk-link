<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Panel;
use Filament\PanelProvider;
use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;

class FilamentOdkLinkPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('odk-link')
            ->path('odk')
            ->resources([
                DatasetResource::class,
                TeamResource::class,
                XlsformTemplateResource::class,
            ]);
    }
}
