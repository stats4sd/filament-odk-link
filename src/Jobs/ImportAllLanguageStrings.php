<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateLanguageStringImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class ImportAllLanguageStrings implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string               $filePath,
        public XlsformModuleVersion $xlsformModuleVersion,
        public Collection           $translatableHeadings,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // import the language strings for all the translatable headings in the surveys tab;
        foreach ($this->translatableHeadings as $sheet => $headings) {
            foreach ($headings as $heading) {
                (new XlsformTemplateLanguageStringImport($this->xlsformModuleVersion, $heading, $sheet))->import($this->filePath);

                FinishLanguageStringImport::dispatchSync($this->xlsformModuleVersion, $heading);

            }
        }
    }
}
