<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class SurveyExport implements WithMultipleSheets
{
    protected $xlsform;
    protected $mainSurveySection;

    public function __construct(Xlsform $xlsform = null)
    {
        $this->xlsform = $xlsform;
        $this->mainSurveySection = $this->xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('is_repeat', 0);
    }

    /**
    * @return array
    */
    public function sheets(): array
    {
        // Assumption: for a flatten approach, there is one main survey and multiple repeat groups
        // 1. Xlsform template must have a root section, which is defined as a non repeat group
        // 2. There is one and only one section is not repeat group for a xlsform template, all other sections are defined for repeat groups

        $sheets = [];

        // handle main survey
        $sheets[] = new EntityExport($this->xlsform, 'Main Survey', $this->mainSurveySection);

        // handle repeat groups
        foreach ($this->xlsform->xlsformTemplate->xlsformTemplateSections as $section) {
            if ($section->id != $this->mainSurveySection->id) {
                $sheets[] = new EntityExport($this->xlsform, $section->structure_item, $section);
            }
        }

        return $sheets;
    }
    
}
