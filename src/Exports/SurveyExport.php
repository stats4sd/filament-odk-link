<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class SurveyExport implements WithMultipleSheets
{
    protected $xlsform;
    protected $entities;
    protected $mainSurveySection;

    public function __construct(Xlsform $xlsform = null)
    {

        $this->xlsform = $xlsform;

        $this->entities = $xlsform->submissions->map(function (Submission $submission) {
            return $submission->entities->load(['values.translation', 'submission']);
        })->flatten();

        $this->mainSurveySection = $this->xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('is_repeat', 0);

    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        // handle main survey
        $entities = $this->entities->filter(fn($entity) => $entity->dataset_id === $this->mainSurveySection->dataset_id);


        $sheets[] = new EntityExport($entities, 'Main Survey', $this->mainSurveySection);

        // handle repeat groups
        foreach ($this->xlsform->xlsformTemplate->xlsformTemplateSections as $section) {
            if ($section->id !== $this->mainSurveySection->id) {

                $entities = $this->entities->filter(fn($entity) => $entity->dataset_id === $section->dataset_id);

                $sheets[] = new EntityExport($entities, $section->structure_item, $section);
            }
        }

        return $sheets;
    }

}
