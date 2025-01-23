<?php

namespace Stats4sd\FilamentOdkLink\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class TestCsvMediaGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odk:test-csv-media-generation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually create a csv file required for a given Xlsform to check if the output format is correct based on the ODK form';

    /**
     * Execute the console command.
     *
     * @throws BindingResolutionException
     */
    public function handle(): void
    {
        $xlsforms = Xlsform::all()
            ->pluck('title', 'id')
            ->toArray();

        $xlsformTitle = $this->choice('Which Xlsform would you like to generate the csv for?', $xlsforms);

        $xlsform = Xlsform::firstWhere('title', $xlsformTitle);

        $mediaName = $this->choice('Which media collection would you like to generate the csv for?', $xlsform->requiredMedia()->pluck('name')->toArray());

        $media = $xlsform->requiredMedia()->where('name', $mediaName)->first();

        if ($media) {
            $csv = app()->make(OdkLinkService::class)
                ->createCsvLookupFile($xlsform, $media);

            $this->info('Csv file created at ' . $csv);
        } else {
            $this->error('Media not found');
        }

        $this->info('Done!');
    }
}
