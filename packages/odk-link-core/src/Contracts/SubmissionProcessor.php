<?php

namespace Stats4sd\FilamentOdkLink\Contracts;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

interface SubmissionProcessor
{
    public function process(Submission $submission): void;
}
