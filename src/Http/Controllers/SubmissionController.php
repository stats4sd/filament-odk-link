<?php

namespace Stats4sd\FilamentOdkLink\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class SubmissionController
{
    /** Called when the submission is updated on ODK Central */
    public function update(int $submission) // for some reason route-model binding doesn't work here (we get an empty Submission)
    {
        $submission = Submission::find($submission);

        $odkLinkService = app()->make(OdkLinkService::class);

        $odkLinkService->updateSubmission($submission);

        $returnUrl = Session::get('submission_return_url', '/');

        return redirect($returnUrl);

    }
}
