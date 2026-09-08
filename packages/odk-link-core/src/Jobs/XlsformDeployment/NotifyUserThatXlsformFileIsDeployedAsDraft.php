<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class NotifyUserThatXlsformFileIsDeployedAsDraft implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public Authenticatable $user) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Notification::make('xlsform_form_deployed_as_draft')
            ->title('Xlsform File Draft Ready')
            ->body('The Xlsform ' . $this->xlsform->title . ' belonging to ' . $this->xlsform->owner->name . ' is now available as a draft to test.')
            ->success()
            ->broadcast($this->user);
    }
}
