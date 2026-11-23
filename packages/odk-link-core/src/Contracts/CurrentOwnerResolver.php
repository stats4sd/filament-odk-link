<?php

namespace Stats4sd\FilamentOdkLink\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CurrentOwnerResolver
{
    public function current(): (Model & FormOwner) | null;
}
