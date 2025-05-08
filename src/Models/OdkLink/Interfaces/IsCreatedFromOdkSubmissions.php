<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;

interface IsCreatedFromOdkSubmissions
{
    public static function createFromOdkEntity(Entity $entity): ?self;
}
