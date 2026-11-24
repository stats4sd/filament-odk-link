<?php

namespace Stats4sd\OdkLink\Core\Jobs\XlsformDeployment;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\OdkLink\Core\Contracts\PlatformUser;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Support\OperationNotification;
use Stats4sd\OdkLink\Core\Support\OperationNotifications;

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
