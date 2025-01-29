<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\Widgets\AvailableOdkTemplatesWidget;
use Stats4sd\FilamentOdkLink\Filament\Widgets\OdkUrlAlertWidget;

class OdkLinkAdmin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'stats4sd-odk-link';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__ . '/Filament/OdkAdmin/Resources',
                for: 'Stats4sd\\FilamentOdkLink\\Filament\\OdkAdmin\\Resources'
            )
            ->discoverWidgets(
                in: __DIR__ . '/Filament/Widgets',
                for: 'Stats4sd\\FilamentOdkLink\\Filament\\Widgets'
            );
    }

    public function boot(Panel $panel): void {}
}
