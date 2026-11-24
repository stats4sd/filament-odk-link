<?php

namespace Stats4sd\OdkLink\Core\Support;

use Stats4sd\OdkLink\Core\Contracts\SubmissionProcessor;
use Stats4sd\OdkLink\Core\Models\OdkLink\Submission;

class NullSubmissionProcessor implements SubmissionProcessor
{
    public function process(Submission $submission): void {}
}
