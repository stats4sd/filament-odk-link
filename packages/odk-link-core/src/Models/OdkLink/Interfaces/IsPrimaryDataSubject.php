<?php

namespace Stats4sd\OdkLink\Core\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @phpstan-require-extends Model
 */
interface IsPrimaryDataSubject
{
    public function submissions(): MorphMany;

    public function updateCompletionStatus(): void;
}
