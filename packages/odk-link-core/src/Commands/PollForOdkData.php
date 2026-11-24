<?php

namespace Stats4sd\OdkLink\Core\Commands;

use Illuminate\Console\Command;
use Stats4sd\OdkLink\Core\Jobs\PullSubmissionsFromXlsform;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;

class PollForOdkData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odk:poll-for-odk-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all active xlsforms for new submissions and downloads them to the database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Xlsform::where('is_active', true)->get()
            ->each(function (Xlsform $xlsform) {
                $this->info("Processing {$xlsform->title}...");
                PullSubmissionsFromXlsform::dispatch($xlsform);
            });
    }
}
