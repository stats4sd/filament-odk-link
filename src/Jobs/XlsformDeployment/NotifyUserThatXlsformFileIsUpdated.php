<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class NotifyUserThatXlsformFileIsUpdated implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public Authenticatable $user)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Notification::make('xlsform_file_updated')
            ->title('Xlsform File Updated')
            ->body('The Xlsform ' . $this->xlsform->title . ' belonging to ' . $this->xlsform->owner->name . ' has been updated.')
            ->success()
            ->broadcast($this->user);
    }
}
