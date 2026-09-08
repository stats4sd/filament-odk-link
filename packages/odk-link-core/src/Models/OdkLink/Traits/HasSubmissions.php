<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

/** @phpstan-require-extends Model */
trait HasSubmissions
{
    /** @return MorphMany<Submission, $this> */
    public function submissions(): MorphMany
    {
        return $this->morphMany(Submission::class, 'primary_data_subject');
    }
}
