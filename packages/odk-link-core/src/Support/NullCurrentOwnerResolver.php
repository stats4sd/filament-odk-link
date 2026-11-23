<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;

class NullCurrentOwnerResolver implements CurrentOwnerResolver
{
    public function current(): (Model & FormOwner) | null
    {
        return null;
    }
}
