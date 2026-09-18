<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class NullSubmissionProcessor implements SubmissionProcessor
{
    public function process(Submission $submission): void {}
}
