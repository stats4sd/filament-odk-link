<?php

namespace Stats4sd\OdkLink\Core\Models\OdkLink\Interfaces;

use Stats4sd\OdkLink\Core\Models\OdkLink\Entity;

interface IsCreatedFromOdkSubmissions
{
    public static function createFromOdkEntity(Entity $entity): ?self;
}
