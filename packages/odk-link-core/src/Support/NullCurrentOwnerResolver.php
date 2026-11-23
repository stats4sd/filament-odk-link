<?php

namespace Stats4sd\OdkLink\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\OdkLink\Core\Contracts\CurrentOwnerResolver;
use Stats4sd\OdkLink\Core\Contracts\FormOwner;

class NullCurrentOwnerResolver implements CurrentOwnerResolver
{
    public function current(): (Model & FormOwner) | null
    {
        return null;
    }
}
