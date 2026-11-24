<?php

namespace Stats4sd\OdkLink\Core\Contracts;

use Stats4sd\OdkLink\Core\Models\OdkLink\Submission;

interface SubmissionProcessor
{
    public function process(Submission $submission): void;
}
