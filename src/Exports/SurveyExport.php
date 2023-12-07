<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class SurveyExport implements WithMultipleSheets
{
    protected $xlsform;
    protected $xlsformVersionIds;
    protected $submissionIds;
    protected $mainSurveySection;

    public function __construct(Xlsform $xlsform = null)
    {
        $this->xlsform = $xlsform;
        $this->xlsformVersionIds = $xlsform->xlsformVersions->pluck('id')->toArray();
        $this->submissionIds = Submission::select('id')->whereIn('xlsform_version_id', $this->xlsformVersionIds)->pluck('id')->toArray();
        $this->mainSurveySection = $this->xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('is_repeat', 0);
    }

    /**
    * @return array
    */
    public function sheets(): array
    {
        // Assumption: for a flatten approach, there is one main survey and multiple repeat groups

        $sheets = [];

        // handle main survey
        $sheets[] = new EntityExport($this->xlsform, $this->submissionIds, 'Main Survey', $this->mainSurveySection);

        // handle repeat groups
        foreach ($this->xlsform->xlsformTemplate->xlsformTemplateSections as $section) {
            if ($section->id != $this->mainSurveySection->id) {
                $sheets[] = new EntityExport($this->xlsform, $this->submissionIds, $section->structure_item, $section);
            }
        }

        return $sheets;
    }
    
}
