<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;

class CurrentOwner
{
    public function __construct(private ConfiguredModels $models) {}

    public function current(): (Model & FormOwner) | null
    {
        return $this->models->validateOwner(app(CurrentOwnerResolver::class)->current());
    }
}
