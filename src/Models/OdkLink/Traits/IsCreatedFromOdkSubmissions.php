<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;

interface IsCreatedFromOdkSubmissions
{
    public static function createFromOdkEntity(Entity $entity): self;
}
