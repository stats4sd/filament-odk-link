<?php

namespace Stats4sd\FilamentOdkLink\Commands;

use Illuminate\Console\Command;

class FilamentOdkLinkCommand extends Command
{
    public $signature = 'filament-odk-link';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
