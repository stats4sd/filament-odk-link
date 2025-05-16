<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Contracts\Plugin;
use Filament\Panel;

class OdkLinkTeam implements Plugin
{

    protected bool $shouldRegisterNavigation = true;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'stats4sd-odk-link-team';
    }

    /**
     * @throws \Exception
     */
    public function register(Panel $panel): void
    {
        // check if the panel has tenancy
        if (! $panel->hasTenancy()) {
            throw new \Exception('The Filament ODK Link Team plugin requires tenancy to be enabled on the panel. Please enable tenancy in the panel configuration.');
        }

        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/OdkTeam/Resources',
                for: 'Stats4sd\\FilamentOdkLink\\Filament\\OdkTeam\\Resources'
            )
            ->discoverWidgets(
                in: __DIR__.'/Filament/Widgets',
                for: 'Stats4sd\\FilamentOdkLink\\Filament\\Widgets'
            )
            ->discoverWidgets(
                in: __DIR__.'/Filament/OdkTeam/Widgets',
                for: 'Stats4sd\\FilamentOdkLink\\Filament\\OdkTeam\\Widgets'
            );
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }

    public function shouldRegisterNavigation(bool $should): static
    {
        $this->shouldRegisterNavigation = $should;

        return $this;
    }

    public function getShouldRegisterNavigation(): bool
    {
        return $this->shouldRegisterNavigation;
    }
}
