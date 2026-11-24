<?php

namespace Stats4sd\OdkLink\Core\Jobs\XlsformDeployment;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\OdkLink\Core\Contracts\PlatformUser;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Support\OperationNotification;
use Stats4sd\OdkLink\Core\Support\OperationNotifications;

class NotifyUserThatXlsformFileIsUpdated implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public Model & PlatformUser $user) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(OperationNotifications::class)->send(new OperationNotification(
            title: 'Xlsform File Updated',
            body: 'The Xlsform ' . $this->xlsform->title . ' belonging to ' . $this->xlsform->owner->name . ' has been updated.',
            severity: 'success',
            id: 'xlsform_file_updated',
        ), collect([$this->user]));
    }
}
