<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;
use Stats4sd\FilamentOdkLink\Support\OperationNotifications;

class NotifyUserThatXlsformFileIsDeployedAsDraft implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public Model & PlatformUser $user) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(OperationNotifications::class)->send(new OperationNotification(
            title: 'Xlsform File Draft Ready',
            body: 'The Xlsform ' . $this->xlsform->title . ' belonging to ' . $this->xlsform->owner->name . ' is now available as a draft to test.',
            severity: 'success',
            id: 'xlsform_form_deployed_as_draft',
        ), collect([$this->user]));
    }
}
