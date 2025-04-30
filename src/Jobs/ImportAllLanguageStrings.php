<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use App\Models\Team;
use App\Services\HelperService;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateLanguageStringImport;

class ImportAllLanguageStrings implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string               $filePath,
        public XlsformModuleVersion | XlsformTemplate $model,
        public Collection           $translatableHeadings,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // custom questions excel file has been stored by Spatie media library, use model to find the corresponding team
        $currentOwner = Team::find($this->model->owner->id);

        // get the file path of custom questions excel file stored by Spatie media library
        $newFilePath = $currentOwner->getFirstMediaPath('custom_questions');

        // update filePath for testing, no error occurred. All jobs completed.
        $this->filePath = $newFilePath;


        // import the language strings for all the translatable headings in the surveys tab;
        foreach ($this->translatableHeadings as $sheet => $headings) {
            foreach ($headings as $heading) {
                (new XlsformTemplateLanguageStringImport($this->model, $heading, $sheet))->import($this->filePath);

                FinishLanguageStringImport::dispatchSync($this->model, $heading);
            }
        }
    }
}
