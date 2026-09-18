<?php

namespace Stats4sd\FilamentOdkLink\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModels;

class FilamentCurrentOwnerResolver implements CurrentOwnerResolver
{
    public function __construct(private ConfiguredModels $models) {}

    public function current(): (Model & FormOwner) | null
    {
        if (! Filament::getCurrentPanel()?->hasTenancy()) {
            return null;
        }

        return $this->models->validateOwner(Filament::getTenant());
    }
}
