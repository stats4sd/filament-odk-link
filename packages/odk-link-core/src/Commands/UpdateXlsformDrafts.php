<?php

namespace Stats4sd\OdkLink\Core\Commands;

use Illuminate\Console\Command;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;

class UpdateXlsformDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-xlsform-drafts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all xlsforms and updates drafts that need it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Xlsform::query()
            ->where('draft_needs_update', true)
            ->get()
            ->each(function (Xlsform $xlsform) {
                $this->info("Processing {$xlsform->title}...");
                $xlsform->deployDraft();
            });

        $this->info('Done!');
    }
}
