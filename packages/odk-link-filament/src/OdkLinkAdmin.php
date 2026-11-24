<?php

namespace Stats4sd\OdkLink\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

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
                in: __DIR__ . '/OdkAdmin/Resources',
                for: 'Stats4sd\\OdkLink\\Filament\\OdkAdmin\\Resources'
            )
            ->discoverWidgets(
                in: __DIR__ . '/Widgets',
                for: 'Stats4sd\\OdkLink\\Filament\\Widgets'
            );
    }

    public function boot(Panel $panel): void {}
}
