<?php

namespace Stats4sd\OdkLink\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CurrentOwnerResolver
{
    public function current(): (Model & FormOwner) | null;
}
