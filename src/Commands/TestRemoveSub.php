<?php

namespace Stats4sd\FilamentOdkLink\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class TestRemoveSub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odk:trs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all records from table entity_values, entities, submissions; Flush cache';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // ask to clear all submissions or only from a specific xlsform
        $option = $this->choice('Do you want to completely clear the submissions, entities and entity_values tables, or only remove submissions from a specific xlsform?', ['All', 'Specific'], 'All');

        if ($option === 'Specific') {
            $teamName = $this->choice('Which team does the form belong to?', Team::all()->pluck('name', 'id')->toArray());

            $team = Team::firstWhere('name', $teamName);

            $xlsformTitle = $this->choice('Which xlsform does the form belong to?', $team->xlsforms->pluck('title', 'id')->toArray());

            $xlsform = Xlsform::firstWhere('title', $xlsformTitle);

            $xlsform->submissions->each(function (Submission $submission) {
                $submission->entities()->delete();
            })
                ->forceDelete();
        }

        Submission::all()->each(function (Submission $submission) {
            $submission->entities()->delete();
            $submission->forceDelete();
        });

        Cache::flush();
    }
}
