<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use App\Models\Dataset;

/**
 * Trait to use for models that have a 'main' linked dataset.
 */
trait HasLinkedDataset
{
    public static function getLinkedDataset(): ?Dataset
    {
        return Dataset::where('entity_model', static::class)
            ->orWhere('entity_model', '\\' . static::class)
            ->first();
    }


}
